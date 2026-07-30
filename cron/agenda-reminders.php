<?php
/**
 * cron/agenda-reminders.php
 * Job de recordatorios del módulo Agenda. Corre por CLI:
 *   php cron/agenda-reminders.php
 * o por HTTP (hosting que solo permite cron por URL) con el secreto:
 *   https://tu-dominio/crm/cron/agenda-reminders.php?token=AGENDA_CRON_SECRET
 *
 * Itera negocios con la agenda habilitada, y por cada umbral configurado en
 * reminder_hours_before busca reservas confirmadas/reprogramadas cuyo inicio
 * ya está dentro de esa ventana y que todavía no tengan un evento
 * 'reminder_sent' logueado para ese mismo umbral (dedup vía bitácora, así
 * el job es idempotente sin importar cada cuánto corra el cron real).
 *
 * Si el hosting solo permite una corrida diaria, los umbrales cortos (ej. 2
 * horas antes) van a ser imprecisos — para precisión real, programar este
 * script cada 15-30 minutos.
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../agenda/Services/NotificationService.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    header('Content-Type: application/json');
    $secret = env('AGENDA_CRON_SECRET', '');
    if (empty($secret) || ($_GET['token'] ?? '') !== $secret) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
}

function out(string $line): void {
    global $isCli;
    if ($isCli) echo $line . "\n";
}

$notifier = new AgendaNotificationService($pdo);
$sentCount = 0;
$errorCount = 0;

$ownerStmt = $pdo->query("
    SELECT DISTINCT user_id FROM agenda_bookings WHERE status IN ('confirmed', 'rescheduled')
");
$ownerIds = $ownerStmt->fetchAll(PDO::FETCH_COLUMN);

// Ventana de preselección amplia en SQL (solo para no traer toda la tabla) —
// la comparación EXACTA de "ahora" contra starts_at se hace en PHP con la
// timezone de la sucursal de cada reserva, nunca con el reloj del servidor
// de base de datos (que puede no coincidir con la timezone de PHP/negocio,
// como pasa en este mismo entorno de desarrollo).
$phpNow = new DateTimeImmutable('now');
$looseFrom = $phpNow->modify('-1 day')->format('Y-m-d H:i:s');

foreach ($ownerIds as $ownerId) {
    $settingsStmt = $pdo->prepare("SELECT enabled, reminder_hours_before FROM agenda_settings WHERE user_id = ?");
    $settingsStmt->execute([$ownerId]);
    $settings = $settingsStmt->fetch();
    $enabled = $settings ? (bool)$settings['enabled'] : true;
    $hoursConfig = $settings['reminder_hours_before'] ?? '24,2';

    if (!$enabled) { out("Negocio #$ownerId: agenda deshabilitada, se omite."); continue; }

    $thresholds = array_filter(array_map('intval', explode(',', $hoursConfig)), fn($h) => $h > 0);
    if (empty($thresholds)) continue;

    $looseTo = $phpNow->modify('+' . (max($thresholds) + 48) . ' hours')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT bk.*, br.timezone
        FROM agenda_bookings bk
        JOIN agenda_branches br ON br.id = bk.branch_id
        WHERE bk.user_id = ?
          AND bk.status IN ('confirmed', 'rescheduled')
          AND bk.starts_at BETWEEN ? AND ?
    ");
    $stmt->execute([$ownerId, $looseFrom, $looseTo]);
    $candidates = $stmt->fetchAll();

    foreach ($candidates as $booking) {
        $tz = new DateTimeZone($booking['timezone'] ?: 'America/Asuncion');
        $nowLocal = new DateTimeImmutable('now', $tz);
        $startsAtLocal = new DateTimeImmutable($booking['starts_at'], $tz);
        if ($startsAtLocal <= $nowLocal) continue; // ya pasó

        foreach ($thresholds as $hours) {
            if ($startsAtLocal > $nowLocal->modify("+$hours hours")) continue; // todavía no entra en este umbral

            $dedupStmt = $pdo->prepare("SELECT 1 FROM agenda_booking_events WHERE booking_id = ? AND type = 'reminder_sent' AND meta = ?");
            $dedupStmt->execute([$booking['id'], (string)$hours]);
            if ($dedupStmt->fetch()) continue; // ya se envió este umbral para esta reserva

            try {
                $notifier->notifyBookingEvent($booking, 'reminder', ['hours_before' => $hours]);
                $pdo->prepare("INSERT INTO agenda_booking_events (booking_id, type, actor, meta) VALUES (?, 'reminder_sent', 'system', ?)")
                    ->execute([$booking['id'], (string)$hours]);
                $sentCount++;
                out("Recordatorio ({$hours}h) enviado — reserva #{$booking['id']}");
            } catch (\Throwable $e) {
                $errorCount++;
                error_log('Agenda cron reminder error booking #' . $booking['id'] . ': ' . $e->getMessage());
                out("ERROR reserva #{$booking['id']}: " . $e->getMessage());
            }
        }
    }
}

$summary = ['sent' => $sentCount, 'errors' => $errorCount, 'businesses_checked' => count($ownerIds)];
if ($isCli) {
    out("Listo. Recordatorios enviados: $sentCount. Errores: $errorCount. Negocios revisados: " . count($ownerIds));
} else {
    echo json_encode($summary);
}
