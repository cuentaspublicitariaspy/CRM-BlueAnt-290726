<?php
header('Content-Type: application/json');
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$landing_id = $data['landing_id'] ?? null;
$action = $data['action'] ?? null;

if ($landing_id && $action === 'view') {
    try {
        // Incrementar el contador de vistas en la tabla landings
        $stmt = $pdo->prepare("UPDATE landings SET views = views + 1 WHERE id = ?");
        $stmt->execute([$landing_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Parámetros inválidos']);
}
?>
