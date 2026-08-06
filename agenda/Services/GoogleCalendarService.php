<?php
require_once __DIR__ . '/../Helpers/Crypto.php';

/**
 * agenda/Services/GoogleCalendarService.php
 * Sincronización push (unidireccional, nunca lee el calendario externo) de
 * reservas hacia Google Calendar. Conexión OAuth2 por recurso (cada
 * profesional/sala conecta su propio calendario) — credenciales en
 * agenda_google_calendar_config, tokens encriptados con Crypto. cURL crudo
 * (mismo patrón que SmsService/Twilio), sin SDK de Google.
 */
class AgendaGoogleCalendarService {

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/userinfo.email';

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public static function isConfigured(): bool {
        return env('GOOGLE_CLIENT_ID', '') !== '' && env('GOOGLE_CLIENT_SECRET', '') !== '';
    }

    public static function redirectUri(): string {
        return rtrim(CRM_URL, '/') . '/api/agenda-google-callback.php';
    }

    public static function buildAuthUrl(string $state): string {
        $params = [
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /** Intercambia el code del callback por tokens y guarda la conexión. */
    public function connectResource(int $resourceId, int $userId, string $code): void {
        $tokens = $this->httpPost(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (empty($tokens['refresh_token'])) {
            throw new \RuntimeException('Google no devolvió un permiso permanente. Si ya habías conectado esta cuenta antes, revocá el acceso en myaccount.google.com/permissions y volvé a intentar.');
        }

        $email = $this->fetchEmail($tokens['access_token']);
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . (int)($tokens['expires_in'] ?? 3600) . ' seconds');

        $stmt = $this->pdo->prepare("
            INSERT INTO agenda_google_calendar_config (resource_id, user_id, calendar_id, google_email, access_token_encrypted, refresh_token_encrypted, token_expires_at)
            VALUES (?, ?, 'primary', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE google_email = VALUES(google_email), access_token_encrypted = VALUES(access_token_encrypted),
                refresh_token_encrypted = VALUES(refresh_token_encrypted), token_expires_at = VALUES(token_expires_at)
        ");
        $stmt->execute([
            $resourceId, $userId, $email,
            Crypto::encrypt($tokens['access_token']),
            Crypto::encrypt($tokens['refresh_token']),
            $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function disconnectResource(int $resourceId, int $userId): void {
        $this->pdo->prepare("DELETE FROM agenda_google_calendar_config WHERE resource_id = ? AND user_id = ?")->execute([$resourceId, $userId]);
    }

    public function getConnection(int $resourceId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM agenda_google_calendar_config WHERE resource_id = ?");
        $stmt->execute([$resourceId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crea, actualiza o borra el evento de Google Calendar correspondiente a
     * una reserva, según si el recurso está conectado. No hace nada (sin
     * error) si el recurso no tiene Google Calendar conectado — es un
     * estado normal, no una falla.
     */
    public function syncBooking(array $booking, string $eventType): void {
        $conn = $this->getConnection((int)$booking['resource_id']);
        if (!$conn) return;

        if ($eventType === 'cancelled') {
            if (!empty($booking['google_event_id'])) {
                $this->deleteEvent($conn, $booking['google_event_id']);
                $this->clearEventId((int)$booking['id']);
            }
            return;
        }

        $context = $this->loadContext((int)$booking['id']);
        $accessToken = $this->getValidAccessToken($conn);
        $payload = $this->buildEventPayload($booking, $context);

        if (!empty($booking['google_event_id'])) {
            $this->httpJson('PATCH', self::CALENDAR_API . '/calendars/' . rawurlencode($conn['calendar_id']) . '/events/' . rawurlencode($booking['google_event_id']), $payload, $accessToken);
        } else {
            $event = $this->httpJson('POST', self::CALENDAR_API . '/calendars/' . rawurlencode($conn['calendar_id']) . '/events', $payload, $accessToken);
            if (!empty($event['id'])) {
                $this->pdo->prepare("UPDATE agenda_bookings SET google_event_id = ? WHERE id = ?")->execute([$event['id'], $booking['id']]);
            }
        }
    }

    private function deleteEvent(array $conn, string $eventId): void {
        $accessToken = $this->getValidAccessToken($conn);
        $ch = curl_init(self::CALENDAR_API . '/calendars/' . rawurlencode($conn['calendar_id']) . '/events/' . rawurlencode($eventId));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // 404/410: el evento ya no existía del lado de Google (borrado a mano) — no es una falla real.
        if ($httpCode >= 400 && !in_array($httpCode, [404, 410], true)) {
            throw new \RuntimeException('Google Calendar delete error (' . $httpCode . ')');
        }
    }

    private function clearEventId(int $bookingId): void {
        $this->pdo->prepare("UPDATE agenda_bookings SET google_event_id = NULL WHERE id = ?")->execute([$bookingId]);
    }

    private function buildEventPayload(array $booking, array $context): array {
        $tz = $context['timezone'] ?: 'America/Asuncion';
        $summary = ($context['service_name'] ?? 'Reserva') . ' — ' . ($booking['contact_name'] ?: 'Cliente');
        $description = trim(implode("\n", array_filter([
            'Recurso: ' . ($context['resource_name'] ?? ''),
            'Sucursal: ' . ($context['branch_name'] ?? ''),
            !empty($booking['contact_phone']) ? 'Teléfono: ' . $booking['contact_phone'] : null,
            !empty($booking['zoom_join_url']) ? 'Zoom: ' . $booking['zoom_join_url'] : null,
            !empty($booking['notes']) ? 'Notas: ' . $booking['notes'] : null,
        ])));

        $payload = [
            'summary' => $summary,
            'description' => $description,
            'start' => ['dateTime' => str_replace(' ', 'T', $booking['starts_at']), 'timeZone' => $tz],
            'end' => ['dateTime' => str_replace(' ', 'T', $booking['ends_at']), 'timeZone' => $tz],
        ];
        if (!empty($booking['contact_email'])) {
            $payload['attendees'] = [['email' => $booking['contact_email']]];
        }
        return $payload;
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

    /** Refresca el access_token si venció (o falta), y persiste el nuevo. */
    private function getValidAccessToken(array $conn): string {
        $expiresAt = $conn['token_expires_at'] ? new DateTimeImmutable($conn['token_expires_at']) : null;
        if (!empty($conn['access_token_encrypted']) && $expiresAt && $expiresAt > new DateTimeImmutable('+60 seconds')) {
            return Crypto::decrypt($conn['access_token_encrypted']);
        }

        $tokens = $this->httpPost(self::TOKEN_URL, [
            'refresh_token' => Crypto::decrypt($conn['refresh_token_encrypted']),
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'grant_type' => 'refresh_token',
        ]);
        $accessToken = $tokens['access_token'] ?? '';
        if ($accessToken === '') throw new \RuntimeException('No se pudo refrescar el token de Google.');

        $newExpiresAt = (new DateTimeImmutable('now'))->modify('+' . (int)($tokens['expires_in'] ?? 3600) . ' seconds');
        $this->pdo->prepare("UPDATE agenda_google_calendar_config SET access_token_encrypted = ?, token_expires_at = ? WHERE resource_id = ?")
            ->execute([Crypto::encrypt($accessToken), $newExpiresAt->format('Y-m-d H:i:s'), $conn['resource_id']]);

        return $accessToken;
    }

    private function fetchEmail(string $accessToken): ?string {
        try {
            $resp = $this->httpJson('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', null, $accessToken);
            return $resp['email'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isLocalEnv(): bool {
        return (strpos(__DIR__, 'xampp') !== false) ||
               (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
    }

    private function httpPost(string $url, array $fields): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isLocalEnv());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) throw new \RuntimeException('cURL error (Google): ' . $curlError);
        $data = json_decode($response, true) ?: [];
        if ($httpCode >= 400) {
            throw new \RuntimeException('Google OAuth error (' . $httpCode . '): ' . ($data['error_description'] ?? $data['error'] ?? $response));
        }
        return $data;
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
        if ($curlError) throw new \RuntimeException('cURL error (Google Calendar): ' . $curlError);
        $data = json_decode($response, true) ?: [];
        if ($httpCode >= 400) {
            throw new \RuntimeException('Google Calendar API error (' . $httpCode . '): ' . ($data['error']['message'] ?? $response));
        }
        return $data;
    }
}
