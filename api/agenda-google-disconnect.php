<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Services/GoogleCalendarService.php';

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session);
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resourceId = (int)($_GET['resource_id'] ?? 0);
    if (!$resourceId) respond(['error' => 'Recurso requerido'], 400);
    $conn = (new AgendaGoogleCalendarService($pdo))->getConnection($resourceId);
    respond(['connected' => (bool)$conn, 'google_email' => $conn['google_email'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $resourceId = (int)($data['resource_id'] ?? 0);
    if (!$resourceId) respond(['error' => 'Recurso requerido'], 400);

    $stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $ownerUserId]);
    if (!$stmt->fetch()) respond(['error' => 'Recurso no encontrado'], 404);

    (new AgendaGoogleCalendarService($pdo))->disconnectResource($resourceId, $ownerUserId);
    respond(['success' => true]);
}

respond(['error' => 'Método no soportado'], 405);
