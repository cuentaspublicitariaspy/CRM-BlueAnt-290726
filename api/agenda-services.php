<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM agenda_services WHERE user_id = ? ORDER BY name");
    $stmt->execute([$ownerUserId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    // Crear/editar/borrar servicios es configuración: solo admin. La lectura
    // sigue abierta para que el subscriber pueda elegir un servicio ya
    // creado al agendar manualmente.
    AgendaAuth::requireAdmin($session);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'delete' && !empty($data['id'])) {
        $pdo->prepare("DELETE FROM agenda_services WHERE id = ? AND user_id = ?")->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $name = trim($data['name'] ?? '');
    $duration = (int)($data['duration_min'] ?? 0);
    if ($name === '' || $duration <= 0) respond(['error' => 'Nombre y duración son obligatorios'], 400);

    $price = ($data['price'] ?? '') !== '' ? (float)$data['price'] : null;
    $currency = trim($data['currency'] ?? '') ?: 'PYG';
    $active = isset($data['active']) ? (int)!!$data['active'] : 1;

    if (!empty($data['id'])) {
        $pdo->prepare("UPDATE agenda_services SET name=?, duration_min=?, price=?, currency=?, active=? WHERE id=? AND user_id=?")
            ->execute([$name, $duration, $price, $currency, $active, (int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $stmt = $pdo->prepare("INSERT INTO agenda_services (user_id, name, duration_min, price, currency, active) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ownerUserId, $name, $duration, $price, $currency, $active]);
    respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

respond(['error' => 'Método no soportado'], 405);
