<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Helpers/Crypto.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * agenda/Services/MailService.php
 * Envío de email vía SMTP genérico (el negocio carga sus propias
 * credenciales: Gmail, Zoho, el hosting propio, o el relay SMTP de un
 * proveedor transaccional). Credenciales en agenda_smtp_config, password
 * encriptado con Crypto.
 */
class AgendaMailService {

    private PHPMailer $mailer;

    public function __construct(array $config) {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->Port = (int)$config['port'];

        if (($config['encryption'] ?? 'tls') === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (($config['encryption'] ?? 'tls') === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
        }

        // Evitar fallos de cURL/SSL por certificados en entornos locales (XAMPP/Windows)
        $isLocal = (strpos(__DIR__, 'xampp') !== false) ||
                   (isset($_SERVER['HTTP_HOST']) && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true));
        if ($isLocal) {
            $mail->SMTPOptions = [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
            ];
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name'] ?: '');
        $this->mailer = $mail;
    }

    public function send(string $toEmail, string $toName, string $subject, string $bodyHtml): void {
        $this->mailer->clearAddresses();
        $this->mailer->addAddress($toEmail, $toName);
        $this->mailer->Subject = $subject;
        $this->mailer->isHTML(true);
        $this->mailer->Body = $bodyHtml;
        $this->mailer->AltBody = trim(strip_tags($bodyHtml));

        if (!$this->mailer->send()) {
            throw new \RuntimeException('Error SMTP: ' . $this->mailer->ErrorInfo);
        }
    }

    public static function fromDatabase(\PDO $pdo, int $userId): self {
        $stmt = $pdo->prepare("SELECT * FROM agenda_smtp_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('SMTP no configurado para este negocio. Configuralo en Agenda → Notificaciones → SMTP.');
        }
        return new self([
            'host' => $row['host'],
            'port' => $row['port'],
            'username' => $row['username'],
            'password' => Crypto::decrypt($row['password_encrypted']),
            'from_email' => $row['from_email'],
            'from_name' => $row['from_name'],
            'encryption' => $row['encryption'],
        ]);
    }
}
