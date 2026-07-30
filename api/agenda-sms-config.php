<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Helpers/Crypto.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // credenciales Twilio: solo admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT account_sid, from_number FROM agenda_twilio_config WHERE user_id = ?");
    $stmt->execute([$ownerUserId]);
    $row = $stmt->fetch();
    respond([
        'configured' => (bool)$row,
        'account_sid' => $row['account_sid'] ?? '',
        'from_number' => $row['from_number'] ?? '',
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $accountSid = trim($data['account_sid'] ?? '');
    $fromNumber = trim($data['from_number'] ?? '');
    if ($accountSid === '' || $fromNumber === '') respond(['error' => 'Account SID y número de origen son obligatorios'], 400);

    $authToken = (string)($data['auth_token'] ?? '');

    if ($authToken !== '') {
        $tokenEncrypted = Crypto::encrypt($authToken);
        $stmt = $pdo->prepare("
            INSERT INTO agenda_twilio_config (user_id, account_sid, auth_token_encrypted, from_number)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE account_sid=VALUES(account_sid), auth_token_encrypted=VALUES(auth_token_encrypted), from_number=VALUES(from_number)
        ");
        $stmt->execute([$ownerUserId, $accountSid, $tokenEncrypted, $fromNumber]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM agenda_twilio_config WHERE user_id = ?");
        $stmt->execute([$ownerUserId]);
        if (!$stmt->fetch()) respond(['error' => 'El auth token es obligatorio en la primera configuración'], 400);

        $pdo->prepare("UPDATE agenda_twilio_config SET account_sid=?, from_number=? WHERE user_id=?")
            ->execute([$accountSid, $fromNumber, $ownerUserId]);
    }

    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
