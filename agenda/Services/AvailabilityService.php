<?php
/**
 * agenda/Services/AvailabilityService.php
 * Motor de disponibilidad: dado un recurso + servicio + rango de fechas,
 * devuelve los slots libres. Toda la aritmética de fechas se hace en la
 * timezone de la sucursal (agenda_branches.timezone) — las columnas
 * DATETIME de agenda_bookings/agenda_blocks/agenda_schedules se guardan y
 * se leen SIEMPRE como hora local de esa sucursal (sin conversión a UTC),
 * así se evita el bug de wabot-app de mezclar UTC puro con horarios locales.
 *
 * agenda_schedules.weekday usa la misma convención que DateTime::format('w'):
 * 0 = domingo ... 6 = sábado.
 */
class AvailabilityService {

    /** Máximo de días que se puede consultar de una sola vez. */
    private const MAX_RANGE_DAYS = 60;

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Carga resource + branch + service + settings del negocio. Devuelve
     * null si el recurso/servicio no existe, está inactivo, o el recurso
     * no ofrece ese servicio.
     */
    public function loadContext(int $resourceId, int $serviceId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT r.id AS resource_id, r.user_id, r.capacity, r.active AS resource_active,
                   r.buffer_before_min, r.buffer_after_min,
                   b.id AS branch_id, b.timezone,
                   s.id AS service_id, s.duration_min, s.active AS service_active
            FROM agenda_resources r
            JOIN agenda_branches b ON b.id = r.branch_id
            JOIN agenda_resource_services rs ON rs.resource_id = r.id AND rs.service_id = ?
            JOIN agenda_services s ON s.id = rs.service_id
            WHERE r.id = ?
        ");
        $stmt->execute([$serviceId, $resourceId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if (!(int)$row['resource_active'] || !(int)$row['service_active']) return null;

        $row['settings'] = $this->loadSettings((int)$row['user_id']);
        return $row;
    }

    public function loadSettings(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) return $row;
        return [
            'user_id' => $userId,
            'enabled' => 1,
            'hold_minutes' => 5,
            'min_lead_minutes' => 60,
            'reminder_hours_before' => '24,2',
        ];
    }

    /**
     * Devuelve los slots libres entre $fromDate y $toDate (formato 'Y-m-d',
     * interpretadas en la timezone de la sucursal). Cada slot es
     * ['starts_at' => 'Y-m-d H:i:s', 'ends_at' => 'Y-m-d H:i:s'] en hora local.
     */
    public function getSlots(int $resourceId, int $serviceId, string $fromDate, string $toDate): array {
        $ctx = $this->loadContext($resourceId, $serviceId);
        if (!$ctx) return [];
        if (empty($ctx['settings']['enabled'])) return [];

        $tz = new DateTimeZone($ctx['timezone'] ?: 'America/Asuncion');
        $now = new DateTimeImmutable('now', $tz);
        $minStart = $now->modify('+' . (int)$ctx['settings']['min_lead_minutes'] . ' minutes');

        try {
            $rangeStart = new DateTimeImmutable($fromDate . ' 00:00:00', $tz);
            $rangeEnd = new DateTimeImmutable($toDate . ' 00:00:00', $tz);
        } catch (\Exception $e) {
            return [];
        }
        if ($rangeStart < $now->setTime(0, 0)) {
            $rangeStart = $now->setTime(0, 0);
        }
        $maxEnd = $rangeStart->modify('+' . self::MAX_RANGE_DAYS . ' days');
        if ($rangeEnd > $maxEnd) $rangeEnd = $maxEnd;
        if ($rangeEnd <= $rangeStart) return [];

        $schedulesByWeekday = $this->loadSchedulesByWeekday($resourceId);
        if (empty($schedulesByWeekday)) return [];

        $durationMin = (int)$ctx['duration_min'];
        $bufferBefore = (int)$ctx['buffer_before_min'];
        $bufferAfter = (int)$ctx['buffer_after_min'];
        $capacity = max(1, (int)$ctx['capacity']);

        $blocks = $this->loadBlocks($resourceId, $rangeStart, $rangeEnd, $tz);
        $occupying = $this->loadOccupyingBookings($resourceId, $bufferBefore, $bufferAfter, $rangeStart, $rangeEnd, $tz, $now, null);

        $slots = [];
        $cursorDay = $rangeStart->setTime(0, 0);
        while ($cursorDay < $rangeEnd) {
            $weekday = (int)$cursorDay->format('w');
            $ranges = $schedulesByWeekday[$weekday] ?? [];

            foreach ($ranges as $range) {
                [$startTime, $endTime] = $range;
                $dayStart = $this->combineDayAndTime($cursorDay, $startTime, $tz);
                $dayEnd = $this->combineDayAndTime($cursorDay, $endTime, $tz);
                if ($dayEnd <= $dayStart) continue;

                $slotStart = $dayStart;
                while (true) {
                    $slotEnd = $slotStart->modify('+' . $durationMin . ' minutes');
                    if ($slotEnd > $dayEnd) break;

                    if ($slotStart < $minStart) {
                        $slotStart = $slotStart->modify('+' . $durationMin . ' minutes');
                        continue;
                    }

                    $occStart = $slotStart->modify('-' . $bufferBefore . ' minutes');
                    $occEnd = $slotEnd->modify('+' . $bufferAfter . ' minutes');

                    $blocked = false;
                    foreach ($blocks as $block) {
                        if ($occStart < $block[1] && $occEnd > $block[0]) { $blocked = true; break; }
                    }

                    if (!$blocked) {
                        $overlapCount = 0;
                        foreach ($occupying as $occ) {
                            if ($occStart < $occ[1] && $occEnd > $occ[0]) $overlapCount++;
                        }
                        if ($overlapCount < $capacity) {
                            $slots[] = [
                                'starts_at' => $slotStart->format('Y-m-d H:i:s'),
                                'ends_at' => $slotEnd->format('Y-m-d H:i:s'),
                            ];
                        }
                    }

                    $slotStart = $slotStart->modify('+' . $durationMin . ' minutes');
                }
            }

            $cursorDay = $cursorDay->modify('+1 day');
        }

        return $slots;
    }

    /**
     * Revalida disponibilidad de un slot puntual dentro de una transacción
     * activa, bloqueando las filas ocupantes del recurso (SELECT ... FOR
     * UPDATE) para evitar que dos requests concurrentes reserven el mismo
     * horario. Debe llamarse DENTRO de $pdo->beginTransaction().
     */
    public function isSlotFreeForUpdate(int $resourceId, int $capacity, int $bufferBefore, int $bufferAfter, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, DateTimeZone $tz, ?int $excludeBookingId = null): bool {
        $occStart = $slotStart->modify('-' . $bufferBefore . ' minutes');
        $occEnd = $slotEnd->modify('+' . $bufferAfter . ' minutes');
        $now = new DateTimeImmutable('now', $tz);

        // El buffer es una propiedad del recurso (uniforme para todas sus
        // reservas, sin importar el servicio), así que se aplica igual a
        // starts_at/ends_at de cada reserva ocupante — sin JOIN a servicios.
        $sql = "
            SELECT COUNT(*) FROM agenda_bookings b
            WHERE b.resource_id = ?
              AND (b.status IN ('confirmed','rescheduled') OR (b.status = 'held' AND b.hold_expires_at > ?))
              AND DATE_SUB(b.starts_at, INTERVAL ? MINUTE) < ?
              AND DATE_ADD(b.ends_at, INTERVAL ? MINUTE) > ?
        ";
        $params = [$resourceId, $now->format('Y-m-d H:i:s'), $bufferBefore, $occEnd->format('Y-m-d H:i:s'), $bufferAfter, $occStart->format('Y-m-d H:i:s')];
        if ($excludeBookingId !== null) {
            $sql .= " AND b.id != ?";
            $params[] = $excludeBookingId;
        }
        $sql .= " FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int)$stmt->fetchColumn();
        return $count < max(1, $capacity);
    }

    private function combineDayAndTime(DateTimeImmutable $day, string $time, DateTimeZone $tz): DateTimeImmutable {
        $parts = explode(':', $time);
        return $day->setTime((int)($parts[0] ?? 0), (int)($parts[1] ?? 0), (int)($parts[2] ?? 0));
    }

    private function loadSchedulesByWeekday(int $resourceId): array {
        $stmt = $this->pdo->prepare("SELECT weekday, start_time, end_time FROM agenda_schedules WHERE resource_id = ? ORDER BY weekday, start_time");
        $stmt->execute([$resourceId]);
        $byDay = [];
        while ($row = $stmt->fetch()) {
            $byDay[(int)$row['weekday']][] = [$row['start_time'], $row['end_time']];
        }
        return $byDay;
    }

    /** Devuelve bloqueos como pares [DateTimeImmutable start, DateTimeImmutable end]. */
    private function loadBlocks(int $resourceId, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, DateTimeZone $tz): array {
        $stmt = $this->pdo->prepare("
            SELECT starts_at, ends_at FROM agenda_blocks
            WHERE resource_id = ? AND starts_at < ? AND ends_at > ?
        ");
        $stmt->execute([$resourceId, $rangeEnd->format('Y-m-d H:i:s'), $rangeStart->format('Y-m-d H:i:s')]);
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[] = [
                new DateTimeImmutable($row['starts_at'], $tz),
                new DateTimeImmutable($row['ends_at'], $tz),
            ];
        }
        return $out;
    }

    /**
     * Reservas ocupantes (confirmadas/reprogramadas, o holds vigentes),
     * ya extendidas por el buffer del recurso (uniforme para todas sus
     * reservas, sin importar qué servicio se haya reservado).
     */
    private function loadOccupyingBookings(int $resourceId, int $bufferBefore, int $bufferAfter, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, DateTimeZone $tz, DateTimeImmutable $now, ?int $excludeBookingId): array {
        $sql = "
            SELECT DATE_SUB(b.starts_at, INTERVAL ? MINUTE) AS occ_start,
                   DATE_ADD(b.ends_at, INTERVAL ? MINUTE) AS occ_end
            FROM agenda_bookings b
            WHERE b.resource_id = ?
              AND (b.status IN ('confirmed','rescheduled') OR (b.status = 'held' AND b.hold_expires_at > ?))
              AND DATE_SUB(b.starts_at, INTERVAL ? MINUTE) < ?
              AND DATE_ADD(b.ends_at, INTERVAL ? MINUTE) > ?
        ";
        $params = [$bufferBefore, $bufferAfter, $resourceId, $now->format('Y-m-d H:i:s'), $bufferBefore, $rangeEnd->format('Y-m-d H:i:s'), $bufferAfter, $rangeStart->format('Y-m-d H:i:s')];
        if ($excludeBookingId !== null) {
            $sql .= " AND b.id != ?";
            $params[] = $excludeBookingId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch()) {
            $out[] = [
                new DateTimeImmutable($row['occ_start'], $tz),
                new DateTimeImmutable($row['occ_end'], $tz),
            ];
        }
        return $out;
    }
}
