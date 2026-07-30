<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session); // horarios: solo configuración de admin
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

function resourceBelongsToOwner(PDO $pdo, int $resourceId, int $ownerUserId): bool {
    $stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $ownerUserId]);
    return (bool)$stmt->fetch();
}

if ($method === 'GET') {
    $resourceId = (int)($_GET['resource_id'] ?? 0);
    if (!$resourceId || !resourceBelongsToOwner($pdo, $resourceId, $ownerUserId)) respond(['error' => 'Recurso inválido'], 400);

    $stmt = $pdo->prepare("SELECT * FROM agenda_schedules WHERE resource_id = ? ORDER BY weekday, start_time");
    $stmt->execute([$resourceId]);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'delete' && !empty($data['id'])) {
        $pdo->prepare("
            DELETE s FROM agenda_schedules s
            JOIN agenda_resources r ON r.id = s.resource_id
            WHERE s.id = ? AND r.user_id = ?
        ")->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    $resourceId = (int)($data['resource_id'] ?? 0);
    $weekday = (int)($data['weekday'] ?? -1);
    $startTime = trim($data['start_time'] ?? '');
    $endTime = trim($data['end_time'] ?? '');

    if (!$resourceId || $weekday < 0 || $weekday > 6 || $startTime === '' || $endTime === '' || $startTime >= $endTime) {
        respond(['error' => 'Datos de horario inválidos'], 400);
    }
    if (!resourceBelongsToOwner($pdo, $resourceId, $ownerUserId)) respond(['error' => 'Recurso inválido'], 400);

    $stmt = $pdo->prepare("INSERT INTO agenda_schedules (resource_id, weekday, start_time, end_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$resourceId, $weekday, $startTime, $endTime]);
    respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

respond(['error' => 'Método no soportado'], 405);
