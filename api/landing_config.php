<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { 
    http_response_code(401); 
    echo json_encode(['error' => 'No autorizado']); 
    exit; 
}

$user_id = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $landing_id = (int)($_GET['landing_id'] ?? 0);
    if ($landing_id <= 0) {
        echo json_encode(['error' => 'Landing ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT redirect_type, redirect_url, whatsapp_number, whatsapp_message FROM landing_subscriptions WHERE user_id = ? AND landing_id = ?");
    $stmt->execute([$user_id, $landing_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        echo json_encode(['success' => true, 'config' => $config]);
    } else {
        echo json_encode(['error' => 'Suscripción no encontrada']);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }

    $landing_id = (int)($data['landing_id'] ?? 0);
    if ($landing_id <= 0) {
        echo json_encode(['error' => 'Landing ID inválido']);
        exit;
    }

    $redirect_type = $data['redirect_type'] ?? 'default';
    if (!in_array($redirect_type, ['default', 'url', 'whatsapp'])) {
        $redirect_type = 'default';
    }

    $redirect_url = trim($data['redirect_url'] ?? '');
    $whatsapp_number = trim($data['whatsapp_number'] ?? '');
    $whatsapp_message = trim($data['whatsapp_message'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE landing_subscriptions 
        SET redirect_type = ?, redirect_url = ?, whatsapp_number = ?, whatsapp_message = ?
        WHERE user_id = ? AND landing_id = ?
    ");

    $success = $stmt->execute([
        $redirect_type, 
        $redirect_url, 
        $whatsapp_number, 
        $whatsapp_message, 
        $user_id, 
        $landing_id
    ]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Error al guardar la configuración']);
    }
    exit;
}

echo json_encode(['error' => 'Método no permitido']);
?>
