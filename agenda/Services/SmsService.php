<?php
require_once __DIR__ . '/../Helpers/Crypto.php';

/**
 * agenda/Services/SmsService.php
 * Envío de SMS vía Twilio, cURL crudo (mismo patrón que
 * agentes/Services/ElevenLabsService.php, sin SDK). Credenciales en
 * agenda_twilio_config, auth_token encriptado con Crypto.
 */
class AgendaSmsService {

    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber) {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
    }

    public function send(string $toNumber, string $message): void {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . urlencode($this->accountSid) . '/Messages.json';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->accountSid . ':' . $this->authToken);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'To' => $toNumber,
            'From' => $this->fromNumber,
            'Body' => $message,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $isLocal = (strpos(__DIR__, 'xampp') !== false) ||
                   (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error (Twilio): ' . $curlError);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $response ?: 'Error desconocido';
            throw new \RuntimeException('Twilio API error (' . $httpCode . '): ' . $msg);
        }
    }

    public static function fromDatabase(\PDO $pdo, int $userId): self {
        $stmt = $pdo->prepare("SELECT * FROM agenda_twilio_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Twilio no configurado para este negocio. Configuralo en Agenda → Notificaciones → SMS.');
        }
        return new self($row['account_sid'], Crypto::decrypt($row['auth_token_encrypted']), $row['from_number']);
    }
}
