<?php
// api/services.php - Endpoint para gestionar servicios del CRM
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

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── GET: listar servicios del usuario ──
if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM services ORDER BY name ASC");
        $stmt->execute();
        respond($stmt->fetchAll());
    } catch (Exception $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}


// ── POST: Crear, actualizar o eliminar ──
if ($method === 'POST') {
    $is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
    if (!$is_admin) {
        respond(['error' => 'No autorizado para realizar modificaciones'], 403);
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }

    $action = $data['action'] ?? null;
    $id = $data['id'] ?? null;

    // 1. ELIMINAR
    if ($action === 'delete' && $id) {
        try {
            // Verificar pertenencia del servicio
            $chk = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id = ? AND user_id = ?");
            $chk->execute([$id, $user_id]);
            if (!$chk->fetchColumn()) {
                respond(['error' => 'Servicio no encontrado o sin acceso'], 403);
            }

            $stmt = $pdo->prepare("DELETE FROM services WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            respond(['success' => true]);
        } catch (Exception $e) {
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // 2. CREAR o ACTUALIZAR
    $name = $data['name'] ?? null;
    $description = $data['description'] ?? '';
    $price = isset($data['price']) ? (float)$data['price'] : 0.00;

    if (empty($name)) {
        respond(['error' => 'El nombre del servicio es requerido'], 400);
    }

    try {
        if ($id) {
            // Actualizar
            // Verificar pertenencia del servicio
            $chk = $pdo->prepare("SELECT COUNT(*) FROM services WHERE id = ? AND user_id = ?");
            $chk->execute([$id, $user_id]);
            if (!$chk->fetchColumn()) {
                respond(['error' => 'Servicio no encontrado o sin acceso'], 403);
            }

            $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $description, $price, $id, $user_id]);
            respond(['success' => true]);
        } else {
            // Crear nuevo
            $stmt = $pdo->prepare("INSERT INTO services (user_id, name, description, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $description, $price]);
            respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
    } catch (Exception $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}
?>
