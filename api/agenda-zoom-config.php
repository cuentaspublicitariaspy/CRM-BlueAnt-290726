<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Helpers/Crypto.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // credenciales Zoom: solo admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT account_id, client_id, host_user_id FROM agenda_zoom_config WHERE user_id = ?");
    $stmt->execute([$ownerUserId]);
    $row = $stmt->fetch();
    respond([
        'configured' => (bool)$row,
        'account_id' => $row['account_id'] ?? '',
        'client_id' => $row['client_id'] ?? '',
        'host_user_id' => $row['host_user_id'] ?? '',
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $accountId = trim($data['account_id'] ?? '');
    $clientId = trim($data['client_id'] ?? '');
    $hostUserId = trim($data['host_user_id'] ?? '');
    if ($accountId === '' || $clientId === '' || $hostUserId === '') {
        respond(['error' => 'Account ID, Client ID y el usuario host son obligatorios'], 400);
    }

    $clientSecret = (string)($data['client_secret'] ?? '');

    if ($clientSecret !== '') {
        $secretEncrypted = Crypto::encrypt($clientSecret);
        $stmt = $pdo->prepare("
            INSERT INTO agenda_zoom_config (user_id, account_id, client_id, client_secret_encrypted, host_user_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE account_id=VALUES(account_id), client_id=VALUES(client_id),
                client_secret_encrypted=VALUES(client_secret_encrypted), host_user_id=VALUES(host_user_id)
        ");
        $stmt->execute([$ownerUserId, $accountId, $clientId, $secretEncrypted, $hostUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM agenda_zoom_config WHERE user_id = ?");
        $stmt->execute([$ownerUserId]);
        if (!$stmt->fetch()) respond(['error' => 'El Client Secret es obligatorio en la primera configuración'], 400);

        $pdo->prepare("UPDATE agenda_zoom_config SET account_id=?, client_id=?, host_user_id=? WHERE user_id=?")
            ->execute([$accountId, $clientId, $hostUserId, $ownerUserId]);
    }

    respond(['success' => true]);
}

if ($method === 'DELETE') {
    $pdo->prepare("DELETE FROM agenda_zoom_config WHERE user_id = ?")->execute([$ownerUserId]);
    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
