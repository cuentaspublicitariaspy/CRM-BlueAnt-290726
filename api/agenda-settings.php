<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // ajustes generales de la agenda: solo admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM agenda_settings WHERE user_id = ?");
    $stmt->execute([$ownerUserId]);
    $row = $stmt->fetch();
    if (!$row) {
        $row = ['user_id' => $ownerUserId, 'enabled' => 1, 'hold_minutes' => 5, 'min_lead_minutes' => 60, 'reminder_hours_before' => '24,2'];
    }
    respond($row);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $enabled = isset($data['enabled']) ? (int)!!$data['enabled'] : 1;
    $holdMinutes = max(1, (int)($data['hold_minutes'] ?? 5));
    $minLeadMinutes = max(0, (int)($data['min_lead_minutes'] ?? 60));

    $hours = $data['reminder_hours_before'] ?? '24,2';
    if (is_array($hours)) $hours = implode(',', array_map('intval', $hours));
    $hours = implode(',', array_filter(array_map('intval', explode(',', (string)$hours)), fn($h) => $h > 0));
    if ($hours === '') $hours = '24';

    $stmt = $pdo->prepare("
        INSERT INTO agenda_settings (user_id, enabled, hold_minutes, min_lead_minutes, reminder_hours_before)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), hold_minutes = VALUES(hold_minutes),
            min_lead_minutes = VALUES(min_lead_minutes), reminder_hours_before = VALUES(reminder_hours_before)
    ");
    $stmt->execute([$ownerUserId, $enabled, $holdMinutes, $minLeadMinutes, $hours]);
    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
