<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // reglas de notificación: solo configuración de admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

const RECIPIENT_TYPES = ['owner', 'client', 'external_agent'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT recipient_type, channel, enabled FROM agenda_notification_rules WHERE user_id = ?");
    $stmt->execute([$ownerUserId]);
    $byType = [];
    while ($row = $stmt->fetch()) { $byType[$row['recipient_type']] = $row; }

    $result = [];
    foreach (RECIPIENT_TYPES as $type) {
        $result[] = $byType[$type] ?? ['recipient_type' => $type, 'channel' => 'email', 'enabled' => 1];
    }
    respond($result);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $rules = $data['rules'] ?? $data;
    if (!is_array($rules)) respond(['error' => 'Datos inválidos'], 400);

    $stmt = $pdo->prepare("
        INSERT INTO agenda_notification_rules (user_id, recipient_type, channel, enabled)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE channel = VALUES(channel), enabled = VALUES(enabled)
    ");

    foreach ($rules as $rule) {
        $type = $rule['recipient_type'] ?? null;
        if (!in_array($type, RECIPIENT_TYPES, true)) continue;
        $channel = in_array($rule['channel'] ?? '', ['email', 'sms'], true) ? $rule['channel'] : 'email';
        $enabled = isset($rule['enabled']) ? (int)!!$rule['enabled'] : 1;
        $stmt->execute([$ownerUserId, $type, $channel, $enabled]);
    }

    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
