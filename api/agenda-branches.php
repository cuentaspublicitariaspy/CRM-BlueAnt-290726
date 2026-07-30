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
    // Lectura abierta: Resumen/Turnos/Consolidada (visibles también para el
    // subscriber) resuelven nombre de sucursal y zona horaria vía
    // refreshCatalog(), no solo la pantalla de Configuración.
    $stmt = $pdo->prepare("SELECT * FROM agenda_branches WHERE user_id = ? ORDER BY name");
    $stmt->execute([$ownerUserId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    // Crear/editar/borrar sucursales es configuración: solo admin.
    AgendaAuth::requireAdmin($session);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'delete' && !empty($data['id'])) {
        $pdo->prepare("DELETE FROM agenda_branches WHERE id = ? AND user_id = ?")->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $name = trim($data['name'] ?? '');
    if ($name === '') respond(['error' => 'El nombre es obligatorio'], 400);
    $address = $data['address'] ?? null;
    $city = $data['city'] ?? null;
    $timezone = trim($data['timezone'] ?? '') ?: 'America/Asuncion';
    try { new DateTimeZone($timezone); } catch (\Exception $e) { respond(['error' => 'Zona horaria inválida'], 400); }
    $phone = $data['phone'] ?? null;
    $active = isset($data['active']) ? (int)!!$data['active'] : 1;

    if (!empty($data['id'])) {
        $pdo->prepare("UPDATE agenda_branches SET name=?, address=?, city=?, timezone=?, phone=?, active=? WHERE id=? AND user_id=?")
            ->execute([$name, $address, $city, $timezone, $phone, $active, (int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $stmt = $pdo->prepare("INSERT INTO agenda_branches (user_id, name, address, city, timezone, phone, active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ownerUserId, $name, $address, $city, $timezone, $phone, $active]);
    respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

respond(['error' => 'Método no soportado'], 405);
