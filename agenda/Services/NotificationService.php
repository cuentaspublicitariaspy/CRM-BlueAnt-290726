<?php
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/SmsService.php';

/**
 * agenda/Services/NotificationService.php
 * Resuelve hasta 3 destinatarios (owner/client/external_agent) y despacha
 * por el canal configurado en agenda_notification_rules. Todo el envío es
 * best-effort: un fallo de notificación nunca debe revertir ni bloquear la
 * operación de reserva que la disparó — solo se loguea.
 *
 * El agente externo solo se notifica si el contacto de la reserva tiene
 * external_agent_id asignado; si no lo tiene, simplemente no hay 3er
 * destinatario (no es un error).
 */
class AgendaNotificationService {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function notifyBookingEvent(array $booking, string $eventType, array $meta = []): void {
        $context = $this->loadContext((int)$booking['id']);
        $recipients = $this->resolveRecipients($booking);

        foreach ($recipients as $recipientType => $recipient) {
            $rule = $this->loadRule((int)$booking['user_id'], $recipientType);
            if (!$rule['enabled']) continue;

            [$subject, $body] = $this->buildMessage($eventType, $recipientType, $booking, $context, $meta);
            $this->dispatch($booking, $recipientType, $rule['channel'], $recipient, $subject, $body);
        }
    }

    private function loadContext(int $bookingId): array {
        $stmt = $this->pdo->prepare("
            SELECT br.name AS branch_name, r.name AS resource_name, s.name AS service_name
            FROM agenda_bookings bk
            JOIN agenda_branches br ON br.id = bk.branch_id
            JOIN agenda_resources r ON r.id = bk.resource_id
            JOIN agenda_services s ON s.id = bk.service_id
            WHERE bk.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch() ?: [];
    }

    private function resolveRecipients(array $booking): array {
        $recipients = [];

        $stmt = $this->pdo->prepare("SELECT name, email, whatsapp FROM users WHERE id = ?");
        $stmt->execute([$booking['user_id']]);
        $owner = $stmt->fetch();
        if ($owner) {
            $recipients['owner'] = ['name' => $owner['name'], 'email' => $owner['email'], 'phone' => $owner['whatsapp']];
        }

        $client = null;
        if (!empty($booking['contact_id'])) {
            $stmt = $this->pdo->prepare("SELECT name, email, whatsapp, external_agent_id FROM prospects WHERE id = ?");
            $stmt->execute([$booking['contact_id']]);
            $client = $stmt->fetch();
        }
        $clientName = $client['name'] ?? ($booking['contact_name'] ?? '');
        $clientEmail = $client['email'] ?? ($booking['contact_email'] ?? '');
        $clientPhone = $client['whatsapp'] ?? ($booking['contact_phone'] ?? '');
        if ($clientEmail || $clientPhone) {
            $recipients['client'] = ['name' => $clientName, 'email' => $clientEmail, 'phone' => $clientPhone];
        }

        if (!empty($client['external_agent_id'])) {
            $stmt = $this->pdo->prepare("SELECT name, email, phone FROM agenda_external_agents WHERE id = ? AND active = 1");
            $stmt->execute([$client['external_agent_id']]);
            $agent = $stmt->fetch();
            if ($agent) {
                $recipients['external_agent'] = ['name' => $agent['name'], 'email' => $agent['email'], 'phone' => $agent['phone']];
            }
        }

        return $recipients;
    }

    private function loadRule(int $userId, string $recipientType): array {
        $stmt = $this->pdo->prepare("SELECT channel, enabled FROM agenda_notification_rules WHERE user_id = ? AND recipient_type = ?");
        $stmt->execute([$userId, $recipientType]);
        $row = $stmt->fetch();
        if ($row) return ['channel' => $row['channel'], 'enabled' => (bool)$row['enabled']];
        return ['channel' => 'email', 'enabled' => true];
    }

    private function buildMessage(string $eventType, string $recipientType, array $booking, array $context, array $meta): array {
        $when = date('d/m/Y H:i', strtotime($booking['starts_at']));
        $service = $context['service_name'] ?? 'el servicio';
        $resource = $context['resource_name'] ?? '';
        $branch = $context['branch_name'] ?? '';
        $who = $booking['contact_name'] ?: 'Cliente';

        $labels = [
            'confirmed' => 'Reserva confirmada',
            'rescheduled' => 'Reserva reprogramada',
            'cancelled' => 'Reserva cancelada',
            'reminder' => 'Recordatorio de turno',
        ];
        $subject = ($labels[$eventType] ?? 'Actualización de reserva') . ': ' . $service . ' — ' . $when;

        $lines = [];
        if ($recipientType === 'owner') {
            $verb = match ($eventType) {
                'confirmed' => 'se confirmó',
                'rescheduled' => 'se reprogramó',
                'cancelled' => 'se canceló',
                'reminder' => 'se recuerda',
                default => 'tuvo una novedad',
            };
            $lines[] = "Una reserva $verb.";
            $lines[] = "Cliente: $who" . (!empty($booking['contact_phone']) ? " ({$booking['contact_phone']})" : '');
        } elseif ($recipientType === 'external_agent') {
            $lines[] = "Tu referido/a $who tiene una novedad en su reserva.";
        } else {
            $lines[] = "Hola $who,";
            $lines[] = match ($eventType) {
                'confirmed' => 'tu reserva fue confirmada.',
                'rescheduled' => 'tu reserva fue reprogramada.',
                'cancelled' => 'tu reserva fue cancelada.',
                'reminder' => 'te recordamos tu próximo turno.',
                default => 'hay una novedad sobre tu reserva.',
            };
        }
        $lines[] = 'Servicio: ' . $service . ($resource ? " con $resource" : '') . ($branch ? " en $branch" : '');
        $lines[] = "Fecha y hora: $when";
        if ($eventType === 'reminder' && !empty($meta['hours_before'])) {
            $lines[] = "(faltan aproximadamente {$meta['hours_before']} hs)";
        }

        return [$subject, implode("\n", $lines)];
    }

    private function dispatch(array $booking, string $recipientType, string $channel, array $recipient, string $subject, string $body): void {
        $success = false;
        $errorMsg = null;
        try {
            if ($channel === 'sms') {
                if (empty($recipient['phone'])) throw new \RuntimeException('Destinatario sin teléfono');
                AgendaSmsService::fromDatabase($this->pdo, (int)$booking['user_id'])->send($recipient['phone'], $subject . "\n" . $body);
            } else {
                if (empty($recipient['email'])) throw new \RuntimeException('Destinatario sin email');
                AgendaMailService::fromDatabase($this->pdo, (int)$booking['user_id'])
                    ->send($recipient['email'], $recipient['name'] ?: '', $subject, nl2br(htmlspecialchars($body)));
            }
            $success = true;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            error_log("Agenda notify [$recipientType/$channel] booking #{$booking['id']}: " . $errorMsg);
        }

        $metaJson = json_encode(['recipient_type' => $recipientType, 'success' => $success, 'error' => $errorMsg], JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("INSERT INTO agenda_booking_events (booking_id, type, actor, channel, meta) VALUES (?, 'notification_sent', 'system', ?, ?)")
            ->execute([$booking['id'], $channel, $metaJson]);
    }
}
