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
    $onlyActive = isset($_GET['active_only']);
    $sql = "SELECT * FROM agenda_external_agents WHERE user_id = ?" . ($onlyActive ? " AND active = 1" : "") . " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ownerUserId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    // Crear/editar/borrar agentes externos es configuración: solo admin. La
    // lectura sigue abierta para que el subscriber pueda ASIGNAR un agente
    // ya cargado al agendar manualmente (eso no es crear configuración nueva).
    AgendaAuth::requireAdmin($session);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'delete' && !empty($data['id'])) {
        $pdo->prepare("DELETE FROM agenda_external_agents WHERE id = ? AND user_id = ?")->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    if ($name === '' || $phone === '') respond(['error' => 'Nombre y teléfono son obligatorios'], 400);

    $email = trim($data['email'] ?? '') ?: null;
    $notes = $data['notes'] ?? null;
    $active = isset($data['active']) ? (int)!!$data['active'] : 1;

    if (!empty($data['id'])) {
        $pdo->prepare("UPDATE agenda_external_agents SET name=?, phone=?, email=?, notes=?, active=? WHERE id=? AND user_id=?")
            ->execute([$name, $phone, $email, $notes, $active, (int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $stmt = $pdo->prepare("INSERT INTO agenda_external_agents (user_id, name, phone, email, notes, active) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ownerUserId, $name, $phone, $email, $notes, $active]);
    respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

respond(['error' => 'Método no soportado'], 405);
