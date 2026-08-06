<?php
require_once __DIR__ . '/../Helpers/Crypto.php';

/**
 * agenda/Services/ZoomService.php
 * Crea/actualiza/borra la reunión Zoom de una reserva de un servicio
 * marcado como virtual (agenda_services.is_virtual). Una sola cuenta Zoom
 * por negocio (Server-to-Server OAuth) — no por recurso, a diferencia de
 * Google Calendar: toda reunión sale del mismo host_user_id configurado.
 *
 * Server-to-Server OAuth es a nivel de cuenta (no hay "usuario actual"):
 * el token se pide con account_credentials y expira en ~1h — no hace falta
 * refresh_token, se pide uno nuevo cada vez que hace falta (esto no es un
 * endpoint de alto tráfico, solo corre en confirm/reschedule/cancel de
 * reservas de servicios virtuales).
 */
class AgendaZoomService {

    private const TOKEN_URL = 'https://zoom.us/oauth/token';
    private const API_BASE = 'https://api.zoom.us/v2';

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getConfig(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_zoom_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crea, actualiza o borra la reunión Zoom de una reserva, según
     * corresponda. No hace nada si el servicio no es virtual o si Zoom no
     * está configurado — son estados normales, no fallas.
     */
    public function syncBooking(array $booking, string $eventType): void {
        $config = $this->getConfig((int)$booking['user_id']);
        if (!$config) return;

        if ($eventType === 'cancelled') {
            if (!empty($booking['zoom_meeting_id'])) {
                $this->deleteMeeting($config, $booking['zoom_meeting_id']);
                $this->clearMeeting((int)$booking['id']);
            }
            return;
        }

        $stmt = $this->pdo->prepare("SELECT is_virtual FROM agenda_services WHERE id = ?");
        $stmt->execute([$booking['service_id']]);
        if (!(int)$stmt->fetchColumn()) return; // servicio no virtual: nada que hacer

        $context = $this->loadContext((int)$booking['id']);
        if (!empty($booking['zoom_meeting_id'])) {
            $this->updateMeeting($config, $booking, $context);
        } else {
            $this->createMeeting($config, $booking, $context);
        }
    }

    private function createMeeting(array $config, array $booking, array $context): void {
        $accessToken = $this->getAccessToken($config);
        $body = $this->buildMeetingPayload($booking, $context);

        $meeting = $this->httpJson('POST', self::API_BASE . '/users/' . rawurlencode($config['host_user_id']) . '/meetings', $body, $accessToken);

        $this->pdo->prepare("UPDATE agenda_bookings SET zoom_meeting_id = ?, zoom_join_url = ?, zoom_start_url = ? WHERE id = ?")
            ->execute([$meeting['id'] ?? null, $meeting['join_url'] ?? null, $meeting['start_url'] ?? null, $booking['id']]);
    }

    private function updateMeeting(array $config, array $booking, array $context): void {
        $accessToken = $this->getAccessToken($config);
        $body = $this->buildMeetingPayload($booking, $context);
        $this->httpJson('PATCH', self::API_BASE . '/meetings/' . rawurlencode($booking['zoom_meeting_id']), $body, $accessToken);
        // PATCH de Zoom no devuelve el objeto actualizado (204 sin body) — el
        // join_url/start_url no cambian al reprogramar, así que no hace
        // falta re-guardarlos.
    }

    private function deleteMeeting(array $config, string $meetingId): void {
        $accessToken = $this->getAccessToken($config);
        $ch = curl_init(self::API_BASE . '/meetings/' . rawurlencode($meetingId));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // 404: la reunión ya no existía del lado de Zoom (borrada a mano) — no es una falla real.
        if ($httpCode >= 400 && $httpCode !== 404) {
            throw new \RuntimeException('Zoom delete error (' . $httpCode . ')');
        }
    }

    private function clearMeeting(int $bookingId): void {
        $this->pdo->prepare("UPDATE agenda_bookings SET zoom_meeting_id = NULL, zoom_join_url = NULL, zoom_start_url = NULL WHERE id = ?")
            ->execute([$bookingId]);
    }

    private function buildMeetingPayload(array $booking, array $context): array {
        $tz = new DateTimeZone($context['timezone'] ?: 'America/Asuncion');
        $startLocal = new DateTimeImmutable($booking['starts_at'], $tz);
        $endLocal = new DateTimeImmutable($booking['ends_at'], $tz);
        $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'));
        $diffSeconds = $endLocal->getTimestamp() - $startLocal->getTimestamp();
        $durationMin = max(1, (int)round($diffSeconds / 60));

        return [
            'topic' => ($context['service_name'] ?? 'Reserva') . ' — ' . ($booking['contact_name'] ?: 'Cliente'),
            'type' => 2, // reunión programada (horario fijo)
            'start_time' => $startUtc->format('Y-m-d\TH:i:s\Z'),
            'duration' => $durationMin,
            'timezone' => 'UTC',
            'agenda' => 'Con ' . ($context['resource_name'] ?? '') . ' — ' . ($context['branch_name'] ?? ''),
            'settings' => [
                'join_before_host' => true,
                'waiting_room' => false,
                'approval_type' => 2, // sin aprobación manual, cualquiera con el link entra
            ],
        ];
    }

    private function loadContext(int $bookingId): array {
        $stmt = $this->pdo->prepare("
            SELECT br.name AS branch_name, br.timezone, r.name AS resource_name, s.name AS service_name
            FROM agenda_bookings bk
            JOIN agenda_branches br ON br.id = bk.branch_id
            JOIN agenda_resources r ON r.id = bk.resource_id
            JOIN agenda_services s ON s.id = bk.service_id
            WHERE bk.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: [];
    }

    private function getAccessToken(array $config): string {
        $ch = curl_init(self::TOKEN_URL . '?' . http_build_query(['grant_type' => 'account_credentials', 'account_id' => $config['account_id']]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . Crypto::decrypt($config['client_secret_encrypted']));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isLocalEnv());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) throw new \RuntimeException('cURL error (Zoom OAuth): ' . $curlError);
        $data = json_decode($response, true) ?: [];
        if ($httpCode >= 400 || empty($data['access_token'])) {
            throw new \RuntimeException('Zoom OAuth error (' . $httpCode . '): ' . ($data['reason'] ?? $data['message'] ?? $response));
        }
        return $data['access_token'];
    }

    private function isLocalEnv(): bool {
        return (strpos(__DIR__, 'xampp') !== false) ||
               (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
    }

    private function httpJson(string $method, string $url, ?array $body, string $accessToken): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isLocalEnv());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) throw new \RuntimeException('cURL error (Zoom): ' . $curlError);
        $data = json_decode($response, true) ?: [];
        if ($httpCode >= 400) {
            throw new \RuntimeException('Zoom API error (' . $httpCode . '): ' . ($data['message'] ?? $response));
        }
        return $data;
    }
}
