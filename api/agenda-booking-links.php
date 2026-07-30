<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // enlaces: solo configuración de admin (el auto-generado desde la vista de recurso también corre con sesión de admin)
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT l.*, b.name AS branch_name, r.name AS resource_name, s.name AS service_name
        FROM agenda_booking_links l
        LEFT JOIN agenda_branches b ON b.id = l.branch_id
        LEFT JOIN agenda_resources r ON r.id = l.resource_id
        LEFT JOIN agenda_services s ON s.id = l.service_id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$ownerUserId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['url'] = rtrim(CRM_URL, '/') . '/reservar.php?token=' . $row['token'];
    }
    respond($rows);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'revoke' && !empty($data['id'])) {
        $pdo->prepare("UPDATE agenda_booking_links SET status = 'revoked' WHERE id = ? AND user_id = ?")
            ->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : null;
    $resourceId = !empty($data['resource_id']) ? (int)$data['resource_id'] : null;
    $serviceId = !empty($data['service_id']) ? (int)$data['service_id'] : null;
    $sourceChannel = $data['source_channel'] ?? null;

    foreach (['branch_id' => 'agenda_branches', 'resource_id' => 'agenda_resources', 'service_id' => 'agenda_services'] as $field => $table) {
        $val = $$field;
        if ($val !== null) {
            $stmt = $pdo->prepare("SELECT id FROM $table WHERE id = ? AND user_id = ?");
            $stmt->execute([$val, $ownerUserId]);
            if (!$stmt->fetch()) respond(['error' => "$field inválido"], 400);
        }
    }

    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare("
        INSERT INTO agenda_booking_links (user_id, token, branch_id, resource_id, service_id, source_channel, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$ownerUserId, $token, $branchId, $resourceId, $serviceId, $sourceChannel]);
    respond(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'token' => $token, 'url' => rtrim(CRM_URL, '/') . '/reservar.php?token=' . $token]);
}

respond(['error' => 'Método no soportado'], 405);
