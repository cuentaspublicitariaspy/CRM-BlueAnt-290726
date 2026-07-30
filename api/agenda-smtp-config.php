<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Helpers/Crypto.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // credenciales SMTP: solo admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT host, port, username, from_email, from_name, encryption FROM agenda_smtp_config WHERE user_id = ?");
    $stmt->execute([$ownerUserId]);
    $row = $stmt->fetch();
    respond([
        'configured' => (bool)$row,
        'host' => $row['host'] ?? '',
        'port' => $row['port'] ?? 587,
        'username' => $row['username'] ?? '',
        'from_email' => $row['from_email'] ?? '',
        'from_name' => $row['from_name'] ?? '',
        'encryption' => $row['encryption'] ?? 'tls',
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;

    $host = trim($data['host'] ?? '');
    $username = trim($data['username'] ?? '');
    $fromEmail = trim($data['from_email'] ?? '');
    if ($host === '' || $username === '' || $fromEmail === '') {
        respond(['error' => 'Host, usuario y email remitente son obligatorios'], 400);
    }

    $port = max(1, (int)($data['port'] ?? 587));
    $fromName = trim($data['from_name'] ?? '');
    $encryption = in_array($data['encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $data['encryption'] : 'tls';
    $password = (string)($data['password'] ?? '');

    if ($password !== '') {
        $passwordEncrypted = Crypto::encrypt($password);
        $stmt = $pdo->prepare("
            INSERT INTO agenda_smtp_config (user_id, host, port, username, password_encrypted, from_email, from_name, encryption)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE host=VALUES(host), port=VALUES(port), username=VALUES(username),
                password_encrypted=VALUES(password_encrypted), from_email=VALUES(from_email), from_name=VALUES(from_name), encryption=VALUES(encryption)
        ");
        $stmt->execute([$ownerUserId, $host, $port, $username, $passwordEncrypted, $fromEmail, $fromName, $encryption]);
    } else {
        // No se mandó password nueva: solo actualizar si ya existe una fila (no pisar con vacío)
        $stmt = $pdo->prepare("SELECT user_id FROM agenda_smtp_config WHERE user_id = ?");
        $stmt->execute([$ownerUserId]);
        if (!$stmt->fetch()) respond(['error' => 'La contraseña SMTP es obligatoria en la primera configuración'], 400);

        $pdo->prepare("UPDATE agenda_smtp_config SET host=?, port=?, username=?, from_email=?, from_name=?, encryption=? WHERE user_id=?")
            ->execute([$host, $port, $username, $fromEmail, $fromName, $encryption, $ownerUserId]);
    }

    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
