<?php
require_once __DIR__ . '/GoogleCalendarService.php';
require_once __DIR__ . '/ZoomService.php';

/**
 * agenda/Services/BookingIntegrations.php
 * Punto único donde se disparan las integraciones externas (Google
 * Calendar, Zoom) después de confirmar/reprogramar/cancelar una reserva —
 * se llama desde api/agenda-public.php (flujo del cliente) y
 * api/agenda-bookings.php (flujo del panel). Corre ANTES de notificar, así
 * el mensaje de confirmación ya puede incluir el enlace de Zoom si
 * corresponde.
 *
 * Best-effort: un fallo acá nunca debe revertir ni bloquear la reserva que
 * lo disparó, solo queda logueado — igual criterio que las notificaciones.
 * Devuelve la reserva relackeada desde la base (con zoom_join_url /
 * google_event_id ya actualizados) para que el endpoint responda con el
 * estado final al cliente.
 */
function agendaSyncIntegrations(PDO $pdo, array $booking, string $eventType): array {
    $bookingId = (int)$booking['id'];

    try {
        (new AgendaGoogleCalendarService($pdo))->syncBooking($booking, $eventType);
    } catch (\Throwable $e) {
        error_log('Agenda Google Calendar sync error booking #' . $bookingId . ': ' . $e->getMessage());
    }

    try {
        (new AgendaZoomService($pdo))->syncBooking($booking, $eventType);
    } catch (\Throwable $e) {
        error_log('Agenda Zoom sync error booking #' . $bookingId . ': ' . $e->getMessage());
    }

    $stmt = $pdo->prepare("SELECT * FROM agenda_bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    return $stmt->fetch() ?: $booking;
}
