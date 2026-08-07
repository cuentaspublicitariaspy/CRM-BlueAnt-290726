<?php
require_once __DIR__ . '/AvailabilityService.php';

/**
 * Error de negocio del motor de reservas — el endpoint que la atrapa decide
 * el código HTTP según $code (ver mapeo en cada api/agenda-*.php).
 */
class AgendaBookingException extends RuntimeException {
    public string $errorCode;
    public function __construct(string $errorCode, string $message) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}

/**
 * agenda/Services/BookingService.php
 * Mutaciones del ciclo de vida de una reserva: hold, confirmar, reprogramar,
 * cancelar, confirmar asistencia, y creación manual desde el panel. Todas
 * revalidan disponibilidad dentro de una transacción con SELECT ... FOR
 * UPDATE (ver AvailabilityService::isSlotFreeForUpdate).
 */
class BookingService {

    private PDO $pdo;
    private AvailabilityService $availability;

    public function __construct(PDO $pdo, AvailabilityService $availability) {
        $this->pdo = $pdo;
        $this->availability = $availability;
    }

    public function getByManageToken(string $token): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE manage_token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createHold(int $resourceId, int $serviceId, string $startsAtLocal, string $actor = 'client'): array {
        $ctx = $this->availability->loadContext($resourceId, $serviceId);
        if (!$ctx) throw new AgendaBookingException('not_found', 'Recurso o servicio no disponible.');
        if (empty($ctx['settings']['enabled'])) throw new AgendaBookingException('disabled', 'La agenda no está habilitada.');

        $tz = new DateTimeZone($ctx['timezone'] ?: 'America/Asuncion');
        try {
            $slotStart = new DateTimeImmutable($startsAtLocal, $tz);
        } catch (\Exception $e) {
            throw new AgendaBookingException('invalid', 'Horario inválido.');
        }
        $slotEnd = $slotStart->modify('+' . (int)$ctx['duration_min'] . ' minutes');

        $now = new DateTimeImmutable('now', $tz);
        $minStart = $now->modify('+' . (int)$ctx['settings']['min_lead_minutes'] . ' minutes');
        if ($slotStart < $minStart) {
            throw new AgendaBookingException('too_soon', 'El horario elegido ya no cumple la anticipación mínima requerida.');
        }

        $this->pdo->beginTransaction();
        try {
            $free = $this->availability->isSlotFreeForUpdate(
                $resourceId, (int)$ctx['capacity'], (int)$ctx['buffer_before_min'], (int)$ctx['buffer_after_min'],
                $slotStart, $slotEnd, $tz, null
            );
            if (!$free) {
                $this->pdo->rollBack();
                throw new AgendaBookingException('slot_taken', 'Ese horario ya no está disponible, elegí otro.');
            }

            $holdExpires = $now->modify('+' . (int)$ctx['settings']['hold_minutes'] . ' minutes');
            $manageToken = $this->generateToken();

            $stmt = $this->pdo->prepare("
                INSERT INTO agenda_bookings
                    (user_id, branch_id, resource_id, service_id, starts_at, ends_at, status, hold_expires_at, manage_token, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'held', ?, ?, ?)
            ");
            $stmt->execute([
                $ctx['user_id'], $ctx['branch_id'], $resourceId, $serviceId,
                $slotStart->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'),
                $holdExpires->format('Y-m-d H:i:s'), $manageToken, $actor,
            ]);
            $bookingId = (int)$this->pdo->lastInsertId();
            $this->logEvent($bookingId, 'slot_held', $actor);

            $this->pdo->commit();
            $result = $this->getById($bookingId);
            // Segundos restantes calculados en el servidor (con la timezone
            // correcta de la sucursal) para que el cliente solo tenga que
            // contar hacia atrás un número, sin parsear fechas locales — así
            // se evita el bug de que el navegador interprete "hold_expires_at"
            // (hora local de la sucursal) como si fuera su propia hora local.
            $result['hold_seconds'] = max(0, $holdExpires->getTimestamp() - $now->getTimestamp());
            return $result;
        } catch (AgendaBookingException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new AgendaBookingException('error', 'No se pudo retener el horario: ' . $e->getMessage());
        }
    }

    public function confirmByManageToken(string $manageToken, array $contact): array {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE manage_token = ? FOR UPDATE");
            $stmt->execute([$manageToken]);
            $booking = $stmt->fetch();
            if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
            if ($booking['status'] !== 'held') {
                if ($booking['status'] === 'confirmed') {
                    $this->pdo->commit();
                    return $booking; // idempotente
                }
                throw new AgendaBookingException('invalid_state', 'Esta reserva ya no puede confirmarse.');
            }
            $tz = $this->branchTimezone((int)$booking['branch_id']);
            $now = new DateTimeImmutable('now', $tz);
            if ($booking['hold_expires_at'] && new DateTimeImmutable($booking['hold_expires_at'], $tz) < $now) {
                throw new AgendaBookingException('expired', 'El tiempo para confirmar este horario venció, elegí uno nuevo.');
            }

            $name = trim((string)($contact['name'] ?? ''));
            $phone = trim((string)($contact['phone'] ?? ''));
            $email = trim((string)($contact['email'] ?? ''));
            $contactId = $this->resolveOrCreateContact((int)$booking['user_id'], $name, $phone, $email);

            $this->pdo->prepare("
                UPDATE agenda_bookings
                SET status = 'confirmed', hold_expires_at = NULL, contact_id = ?, contact_name = ?, contact_phone = ?, contact_email = ?
                WHERE id = ?
            ")->execute([$contactId, $name ?: null, $phone ?: null, $email ?: null, $booking['id']]);

            $this->logEvent((int)$booking['id'], 'booking_confirmed', 'client');
            $this->pdo->commit();
            return $this->getById((int)$booking['id']);
        } catch (AgendaBookingException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new AgendaBookingException('error', 'No se pudo confirmar: ' . $e->getMessage());
        }
    }

    public function rescheduleByManageToken(string $manageToken, string $newStartsAtLocal): array {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE manage_token = ? FOR UPDATE");
            $stmt->execute([$manageToken]);
            $booking = $stmt->fetch();
            if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
            if (!in_array($booking['status'], ['confirmed', 'rescheduled'], true)) {
                throw new AgendaBookingException('invalid_state', 'Esta reserva no se puede reprogramar.');
            }

            $ctx = $this->availability->loadContext((int)$booking['resource_id'], (int)$booking['service_id']);
            if (!$ctx) throw new AgendaBookingException('not_found', 'Recurso o servicio ya no disponible.');
            $tz = new DateTimeZone($ctx['timezone'] ?: 'America/Asuncion');

            try {
                $newStart = new DateTimeImmutable($newStartsAtLocal, $tz);
            } catch (\Exception $e) {
                throw new AgendaBookingException('invalid', 'Horario inválido.');
            }
            $newEnd = $newStart->modify('+' . (int)$ctx['duration_min'] . ' minutes');

            $now = new DateTimeImmutable('now', $tz);
            $minStart = $now->modify('+' . (int)$ctx['settings']['min_lead_minutes'] . ' minutes');
            if ($newStart < $minStart) {
                throw new AgendaBookingException('too_soon', 'El horario elegido ya no cumple la anticipación mínima requerida.');
            }

            $free = $this->availability->isSlotFreeForUpdate(
                (int)$booking['resource_id'], (int)$ctx['capacity'], (int)$ctx['buffer_before_min'], (int)$ctx['buffer_after_min'],
                $newStart, $newEnd, $tz, (int)$booking['id']
            );
            if (!$free) throw new AgendaBookingException('slot_taken', 'Ese horario ya no está disponible, elegí otro.');

            $this->pdo->prepare("UPDATE agenda_bookings SET starts_at = ?, ends_at = ?, status = 'rescheduled', attendance_confirmed = 0 WHERE id = ?")
                ->execute([$newStart->format('Y-m-d H:i:s'), $newEnd->format('Y-m-d H:i:s'), $booking['id']]);

            $this->logEvent((int)$booking['id'], 'booking_rescheduled', 'client');
            $this->pdo->commit();
            return $this->getById((int)$booking['id']);
        } catch (AgendaBookingException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new AgendaBookingException('error', 'No se pudo reprogramar: ' . $e->getMessage());
        }
    }

    public function cancelByManageToken(string $manageToken, ?string $reason = null, string $actor = 'client'): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE manage_token = ?");
        $stmt->execute([$manageToken]);
        $booking = $stmt->fetch();
        if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
        if (in_array($booking['status'], ['cancelled', 'completed'], true)) {
            return $booking; // idempotente
        }

        $this->pdo->prepare("UPDATE agenda_bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking['id']]);
        $this->logEvent((int)$booking['id'], 'booking_cancelled', $actor, null, $reason);
        return $this->getById((int)$booking['id']);
    }

    public function confirmAttendanceByManageToken(string $manageToken): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE manage_token = ?");
        $stmt->execute([$manageToken]);
        $booking = $stmt->fetch();
        if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
        if (!in_array($booking['status'], ['confirmed', 'rescheduled'], true)) {
            throw new AgendaBookingException('invalid_state', 'Esta reserva no admite confirmar asistencia.');
        }

        $this->pdo->prepare("UPDATE agenda_bookings SET attendance_confirmed = 1 WHERE id = ?")->execute([$booking['id']]);
        $this->logEvent((int)$booking['id'], 'asistencia_confirmada', 'client');
        return $this->getById((int)$booking['id']);
    }

    /**
     * Reserva creada manualmente desde el panel (admin o subscriber, sobre
     * su propio negocio) — se crea directamente 'confirmed', sin fase de
     * hold, porque quien la carga ya está mirando el resultado final.
     */
    public function createManual(int $ownerUserId, int $resourceId, int $serviceId, string $startsAtLocal, array $contact, ?int $contactId, ?string $notes, ?int $externalAgentId = null): array {
        $stmt = $this->pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
        $stmt->execute([$resourceId, $ownerUserId]);
        if (!$stmt->fetch()) throw new AgendaBookingException('not_found', 'Recurso no encontrado para este negocio.');

        if ($externalAgentId !== null) {
            $stmt = $this->pdo->prepare("SELECT id FROM agenda_external_agents WHERE id = ? AND user_id = ?");
            $stmt->execute([$externalAgentId, $ownerUserId]);
            if (!$stmt->fetch()) $externalAgentId = null; // ignorar si no pertenece a este negocio
        }

        $ctx = $this->availability->loadContext($resourceId, $serviceId);
        if (!$ctx) throw new AgendaBookingException('not_found', 'Recurso o servicio no disponible.');

        $tz = new DateTimeZone($ctx['timezone'] ?: 'America/Asuncion');
        try {
            $slotStart = new DateTimeImmutable($startsAtLocal, $tz);
        } catch (\Exception $e) {
            throw new AgendaBookingException('invalid', 'Horario inválido.');
        }
        $slotEnd = $slotStart->modify('+' . (int)$ctx['duration_min'] . ' minutes');

        $this->pdo->beginTransaction();
        try {
            $free = $this->availability->isSlotFreeForUpdate(
                $resourceId, (int)$ctx['capacity'], (int)$ctx['buffer_before_min'], (int)$ctx['buffer_after_min'],
                $slotStart, $slotEnd, $tz, null
            );
            if (!$free) {
                $this->pdo->rollBack();
                throw new AgendaBookingException('slot_taken', 'Ese horario ya está ocupado en este recurso.');
            }

            $name = trim((string)($contact['name'] ?? ''));
            $phone = trim((string)($contact['phone'] ?? ''));
            $email = trim((string)($contact['email'] ?? ''));
            if (!$contactId && ($name !== '' || $phone !== '' || $email !== '')) {
                $contactId = $this->resolveOrCreateContact($ownerUserId, $name, $phone, $email, $externalAgentId);
            } elseif ($contactId && $externalAgentId !== null) {
                $this->pdo->prepare("UPDATE prospects SET external_agent_id = ? WHERE id = ? AND user_id = ?")->execute([$externalAgentId, $contactId, $ownerUserId]);
            }

            $manageToken = $this->generateToken();
            $stmt = $this->pdo->prepare("
                INSERT INTO agenda_bookings
                    (user_id, branch_id, resource_id, service_id, contact_id, contact_name, contact_phone, contact_email,
                     starts_at, ends_at, status, manage_token, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, 'staff')
            ");
            $stmt->execute([
                $ownerUserId, $ctx['branch_id'], $resourceId, $serviceId, $contactId,
                $name ?: null, $phone ?: null, $email ?: null,
                $slotStart->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'),
                $manageToken, $notes ?: null,
            ]);
            $bookingId = (int)$this->pdo->lastInsertId();
            $this->logEvent($bookingId, 'booking_confirmed', 'staff');

            $this->pdo->commit();
            return $this->getById($bookingId);
        } catch (AgendaBookingException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw new AgendaBookingException('error', 'No se pudo crear la reserva: ' . $e->getMessage());
        }
    }

    public function cancelByOwner(int $ownerUserId, int $bookingId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $ownerUserId]);
        $booking = $stmt->fetch();
        if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
        if (in_array($booking['status'], ['cancelled', 'completed'], true)) return $booking;

        $this->pdo->prepare("UPDATE agenda_bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
        $this->logEvent($bookingId, 'booking_cancelled', 'staff');
        return $this->getById($bookingId);
    }

    /**
     * Resultado de asistencia de un turno ya pasado, cargado por el staff:
     * 'completed' (vino) o 'no_show' (no vino). Solo aplica a reservas que
     * todavía estaban abiertas (confirmed/rescheduled) — una vez cancelada
     * o ya resuelta, no tiene sentido pisar el resultado.
     */
    public function markAttendanceOutcome(int $ownerUserId, int $bookingId, bool $attended): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $ownerUserId]);
        $booking = $stmt->fetch();
        if (!$booking) throw new AgendaBookingException('not_found', 'Reserva no encontrada.');
        if (!in_array($booking['status'], ['confirmed', 'rescheduled'], true)) {
            throw new AgendaBookingException('invalid_state', 'Esta reserva no admite marcar asistencia.');
        }

        $newStatus = $attended ? 'completed' : 'no_show';
        $this->pdo->prepare("UPDATE agenda_bookings SET status = ? WHERE id = ?")->execute([$newStatus, $bookingId]);
        $this->logEvent($bookingId, $attended ? 'marked_completed' : 'marked_no_show', 'staff');
        return $this->getById($bookingId);
    }

    public function getById(int $id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_bookings WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function logEvent(int $bookingId, string $type, string $actor, ?string $channel = null, ?string $meta = null): void {
        $this->pdo->prepare("INSERT INTO agenda_booking_events (booking_id, type, actor, channel, meta) VALUES (?, ?, ?, ?, ?)")
            ->execute([$bookingId, $type, $actor, $channel, $meta]);
    }

    private function branchTimezone(int $branchId): DateTimeZone {
        $stmt = $this->pdo->prepare("SELECT timezone FROM agenda_branches WHERE id = ?");
        $stmt->execute([$branchId]);
        $tz = $stmt->fetchColumn();
        return new DateTimeZone($tz ?: 'America/Asuncion');
    }

    /**
     * Busca un prospect existente del negocio por teléfono o email; si no
     * hay coincidencia, crea uno nuevo (`prospects.origin_type = 'agenda'`).
     * Devuelve null si no hay ningún dato de contacto.
     *
     * $externalAgentId solo se aplica si viene explícitamente seteado (no
     * null): en un contacto nuevo lo asigna de una, en uno existente lo
     * actualiza. Si viene null, nunca toca el agente externo ya asignado —
     * así una reserva manual sin ese campo no borra una asignación previa.
     */
    private function resolveOrCreateContact(int $ownerUserId, string $name, string $phone, string $email, ?int $externalAgentId = null): ?int {
        if ($phone === '' && $email === '') return null;

        $conditions = [];
        $params = [$ownerUserId];
        if ($phone !== '') { $conditions[] = 'whatsapp = ?'; $params[] = $phone; }
        if ($email !== '') { $conditions[] = 'email = ?'; $params[] = $email; }
        $stmt = $this->pdo->prepare("SELECT id FROM prospects WHERE user_id = ? AND (" . implode(' OR ', $conditions) . ") LIMIT 1");
        $stmt->execute($params);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            if ($externalAgentId !== null) {
                $this->pdo->prepare("UPDATE prospects SET external_agent_id = ? WHERE id = ?")->execute([$externalAgentId, $existing]);
            }
            return (int)$existing;
        }

        $stmt = $this->pdo->prepare("INSERT INTO prospects (user_id, name, email, whatsapp, origin_type, external_agent_id) VALUES (?, ?, ?, ?, 'agenda', ?)");
        $stmt->execute([$ownerUserId, $name ?: 'Contacto de Agenda', $email, $phone, $externalAgentId]);
        return (int)$this->pdo->lastInsertId();
    }

    private function generateToken(): string {
        return bin2hex(random_bytes(24));
    }
}
