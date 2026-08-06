<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Services/NotificationService.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // reglas de notificación: solo configuración de admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

const RECIPIENT_TYPES = ['owner', 'client', 'external_agent'];
const TRIGGER_TYPES = ['confirmed', 'rescheduled', 'cancelled', 'reminder'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

function resolveResourceScope(PDO $pdo, int $ownerUserId, $raw): int {
    $resourceId = (int)($raw ?? 0);
    if ($resourceId === 0) return 0; // regla por defecto del negocio
    $stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $ownerUserId]);
    if (!$stmt->fetch()) respond(['error' => 'Recurso inválido'], 400);
    return $resourceId;
}

if ($method === 'GET') {
    $resourceId = resolveResourceScope($pdo, $ownerUserId, $_GET['resource_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT trigger_type, recipient_type, channel, enabled, subject_template, body_template
        FROM agenda_notification_rules WHERE user_id = ? AND resource_id = ?
    ");
    $stmt->execute([$ownerUserId, $resourceId]);
    $byKey = [];
    while ($row = $stmt->fetch()) { $byKey[$row['trigger_type'] . '|' . $row['recipient_type']] = $row; }

    $result = [];
    foreach (TRIGGER_TYPES as $trigger) {
        foreach (RECIPIENT_TYPES as $recipient) {
            $existing = $byKey[$trigger . '|' . $recipient] ?? null;
            $tpl = AgendaNotificationService::effectiveTemplate(
                $existing['subject_template'] ?? null,
                $existing['body_template'] ?? null,
                $trigger,
                $recipient
            );
            $result[] = [
                'trigger_type' => $trigger,
                'recipient_type' => $recipient,
                'channel' => $existing['channel'] ?? 'email',
                'enabled' => $existing ? (bool)$existing['enabled'] : true,
                'subject_template' => $existing['subject_template'] ?? null,
                'body_template' => $existing['body_template'] ?? null,
                'default_subject' => $tpl['subject'],
                'default_body' => $tpl['body'],
                'has_override' => $existing !== null,
            ];
        }
    }
    respond(['resource_id' => $resourceId, 'rules' => $result]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $resourceId = resolveResourceScope($pdo, $ownerUserId, $data['resource_id'] ?? 0);
    $rules = $data['rules'] ?? [];
    if (!is_array($rules)) respond(['error' => 'Datos inválidos'], 400);

    $stmt = $pdo->prepare("
        INSERT INTO agenda_notification_rules
            (user_id, resource_id, trigger_type, recipient_type, channel, enabled, subject_template, body_template)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE channel = VALUES(channel), enabled = VALUES(enabled),
            subject_template = VALUES(subject_template), body_template = VALUES(body_template)
    ");

    foreach ($rules as $rule) {
        $trigger = $rule['trigger_type'] ?? null;
        $recipient = $rule['recipient_type'] ?? null;
        if (!in_array($trigger, TRIGGER_TYPES, true) || !in_array($recipient, RECIPIENT_TYPES, true)) continue;

        $channel = in_array($rule['channel'] ?? '', ['email', 'sms'], true) ? $rule['channel'] : 'email';
        $enabled = isset($rule['enabled']) ? (int)!!$rule['enabled'] : 1;
        // Plantilla vacía o sin cambios respecto del default = usar el
        // default del sistema (subject_template/body_template en NULL), así
        // una edición futura del texto por defecto se sigue aplicando acá.
        $subjectTemplate = trim((string)($rule['subject_template'] ?? '')) ?: null;
        $bodyTemplate = trim((string)($rule['body_template'] ?? '')) ?: null;

        $stmt->execute([$ownerUserId, $resourceId, $trigger, $recipient, $channel, $enabled, $subjectTemplate, $bodyTemplate]);
    }

    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
