<?php
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/SmsService.php';

/**
 * agenda/Services/NotificationService.php
 * Resuelve hasta 3 destinatarios (owner/client/external_agent) y despacha
 * por el canal configurado en agenda_notification_rules — ahora con
 * granularidad por recurso (resource_id = 0 es la regla por defecto del
 * negocio, para cualquier recurso sin override propio) y por tipo de
 * evento (trigger_type: confirmed/rescheduled/cancelled/reminder), con
 * plantilla de asunto/cuerpo editable por variables {{cliente}} {{servicio}}
 * {{agenda}} {{sucursal}} {{negocio}} {{fecha}} {{link}} {{zoom_link}}
 * {{horas}}. Todo el envío es best-effort: un fallo de notificación nunca
 * debe revertir ni bloquear la operación de reserva que la disparó — solo
 * se loguea.
 *
 * El agente externo solo se notifica si el contacto de la reserva tiene
 * external_agent_id asignado; si no lo tiene, simplemente no hay 3er
 * destinatario (no es un error).
 */
class AgendaNotificationService {

    private PDO $pdo;

    /**
     * Plantillas por defecto (trigger_type -> recipient_type -> [subject, body]).
     * Se usan cuando la regla no tiene subject_template/body_template propios
     * (o cuando no existe ninguna regla configurada todavía). Los bloques
     * {{#var}}...{{/var}} solo se incluyen si esa variable tiene valor —
     * así {{zoom_link}} no deja una línea colgada en reservas no virtuales.
     */
    private const DEFAULT_SUBJECTS = [
        'confirmed'   => 'Reserva confirmada: {{servicio}} — {{fecha}}',
        'rescheduled' => 'Reserva reprogramada: {{servicio}} — {{fecha}}',
        'cancelled'   => 'Reserva cancelada: {{servicio}} — {{fecha}}',
        'reminder'    => 'Recordatorio de turno: {{servicio}} — {{fecha}}',
    ];

    private const DEFAULT_BODIES = [
        'confirmed' => [
            'owner'           => "Una reserva se confirmó.\nCliente: {{cliente}}\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}}",
            'client'          => "Hola {{cliente}},\ntu reserva fue confirmada.\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}}\n{{#zoom_link}}Unite por Zoom: {{zoom_link}}\n{{/zoom_link}}Para reprogramar o cancelar: {{link}}",
            'external_agent'  => "Tu referido/a {{cliente}} confirmó una reserva.\nServicio: {{servicio}} — {{fecha}}",
        ],
        'rescheduled' => [
            'owner'           => "Una reserva se reprogramó.\nCliente: {{cliente}}\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nNueva fecha y hora: {{fecha}}",
            'client'          => "Hola {{cliente}},\ntu reserva fue reprogramada.\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nNueva fecha y hora: {{fecha}}\n{{#zoom_link}}Unite por Zoom: {{zoom_link}}\n{{/zoom_link}}Para reprogramar o cancelar: {{link}}",
            'external_agent'  => "Tu referido/a {{cliente}} reprogramó su reserva.\nServicio: {{servicio}} — nueva fecha {{fecha}}",
        ],
        'cancelled' => [
            'owner'           => "Una reserva se canceló.\nCliente: {{cliente}}\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}}",
            'client'          => "Hola {{cliente}},\ntu reserva fue cancelada.\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}}",
            'external_agent'  => "Tu referido/a {{cliente}} canceló su reserva.\nServicio: {{servicio}} — {{fecha}}",
        ],
        'reminder' => [
            'owner'           => "Se recuerda una reserva próxima.\nCliente: {{cliente}}\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}} (faltan aprox. {{horas}} hs)",
            'client'          => "Hola {{cliente}},\nte recordamos tu próximo turno.\nServicio: {{servicio}} con {{agenda}} en {{sucursal}}\nFecha y hora: {{fecha}} (faltan aprox. {{horas}} hs)\n{{#zoom_link}}Unite por Zoom: {{zoom_link}}\n{{/zoom_link}}",
            'external_agent'  => "Se acerca el turno de tu referido/a {{cliente}}.\nServicio: {{servicio}} — {{fecha}}",
        ],
    ];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function notifyBookingEvent(array $booking, string $eventType, array $meta = []): void {
        if (!in_array($eventType, ['confirmed', 'rescheduled', 'cancelled', 'reminder'], true)) return;

        $context = $this->loadContext((int)$booking['id']);
        $recipients = $this->resolveRecipients($booking);
        $vars = $this->buildVars($booking, $context, $meta);

        foreach ($recipients as $recipientType => $recipient) {
            $rule = $this->resolveRule((int)$booking['user_id'], (int)$booking['resource_id'], $eventType, $recipientType);
            if (!$rule['enabled']) continue;

            $subjectTpl = $rule['subject_template'] ?: self::DEFAULT_SUBJECTS[$eventType];
            $bodyTpl = $rule['body_template'] ?: self::DEFAULT_BODIES[$eventType][$recipientType];
            $subject = $this->renderTemplate($subjectTpl, $vars);
            $body = $this->renderTemplate($bodyTpl, $vars);

            $this->dispatch($booking, $recipientType, $rule['channel'], $recipient, $subject, $body);
        }
    }

    /**
     * Devuelve la plantilla efectiva (propia si existe, sino la de sistema)
     * para mostrar en el panel de configuración — así el admin ve el texto
     * real que se va a enviar, no un placeholder vacío, y puede editarlo a
     * partir de ahí.
     */
    public static function effectiveTemplate(?string $subjectTemplate, ?string $bodyTemplate, string $triggerType, string $recipientType): array {
        return [
            'subject' => $subjectTemplate ?: (self::DEFAULT_SUBJECTS[$triggerType] ?? ''),
            'body' => $bodyTemplate ?: (self::DEFAULT_BODIES[$triggerType][$recipientType] ?? ''),
        ];
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

    /**
     * Regla efectiva para (negocio, recurso, evento, destinatario): prioriza
     * la regla propia del recurso sobre la regla por defecto (resource_id =
     * 0). Si no hay ninguna configurada todavía, cae al valor de sistema
     * (email, habilitado) — mismo comportamiento que antes de esta migración.
     */
    private function resolveRule(int $userId, int $resourceId, string $triggerType, string $recipientType): array {
        $stmt = $this->pdo->prepare("
            SELECT channel, enabled, subject_template, body_template
            FROM agenda_notification_rules
            WHERE user_id = ? AND resource_id IN (?, 0) AND trigger_type = ? AND recipient_type = ?
            ORDER BY (resource_id != 0) DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $resourceId, $triggerType, $recipientType]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'channel' => $row['channel'],
                'enabled' => (bool)$row['enabled'],
                'subject_template' => $row['subject_template'] ?: null,
                'body_template' => $row['body_template'] ?: null,
            ];
        }
        return ['channel' => 'email', 'enabled' => true, 'subject_template' => null, 'body_template' => null];
    }

    private function buildVars(array $booking, array $context, array $meta): array {
        $when = date('d/m/Y H:i', strtotime($booking['starts_at']));
        $manageUrl = defined('CRM_URL') ? rtrim(CRM_URL, '/') . '/reservar-gestionar.php?token=' . $booking['manage_token'] : '';

        $ownerStmt = $this->pdo->prepare("SELECT name FROM users WHERE id = ?");
        $ownerStmt->execute([$booking['user_id']]);
        $negocio = $ownerStmt->fetchColumn() ?: '';

        return [
            'cliente' => $booking['contact_name'] ?: 'Cliente',
            'servicio' => $context['service_name'] ?? 'el servicio',
            'agenda' => $context['resource_name'] ?? '',
            'sucursal' => $context['branch_name'] ?? '',
            'negocio' => $negocio,
            'fecha' => $when,
            'link' => $manageUrl,
            'zoom_link' => $booking['zoom_join_url'] ?? '',
            'horas' => $meta['hours_before'] ?? '',
        ];
    }

    /**
     * Sustitución de variables {{var}} y bloques condicionales
     * {{#var}}...{{/var}} (el bloque solo queda si esa variable tiene un
     * valor no vacío) — permite que la plantilla por defecto incluya la
     * línea de Zoom solo cuando la reserva efectivamente tiene una.
     */
    private function renderTemplate(string $tpl, array $vars): string {
        $tpl = preg_replace_callback('/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s', function ($m) use ($vars) {
            return !empty($vars[$m[1]]) ? $m[2] : '';
        }, $tpl);

        foreach ($vars as $key => $value) {
            $tpl = str_replace('{{' . $key . '}}', (string)$value, $tpl);
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", $tpl));
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
