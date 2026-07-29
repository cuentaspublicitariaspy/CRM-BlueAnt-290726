<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }
$user_id  = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
$method   = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $prospect_id = $_GET['prospect_id'] ?? null;
    try {
        if ($prospect_id) {
            if (!$is_admin) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE id = ? AND user_id = ?");
                $chk->execute([$prospect_id, $user_id]);
                if (!$chk->fetchColumn()) { echo json_encode([]); exit; }
            }

            $stmt = $pdo->prepare("SELECT id, prospect_id, description AS note, activity_type AS type, created_at 
                                   FROM activities WHERE prospect_id = ? ORDER BY created_at DESC");
            $stmt->execute([$prospect_id]);
        } else {
            if ($is_admin) {
                $stmt = $pdo->prepare("SELECT a.id, a.prospect_id, a.description AS note, a.activity_type AS type, 
                                               a.created_at, p.name AS prospect_name
                                        FROM activities a
                                        JOIN prospects p ON a.prospect_id = p.id
                                        ORDER BY a.created_at DESC LIMIT 100");
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("SELECT a.id, a.prospect_id, a.description AS note, a.activity_type AS type, 
                                               a.created_at, p.name AS prospect_name
                                        FROM activities a
                                        JOIN prospects p ON a.prospect_id = p.id
                                        WHERE p.user_id = ?
                                        ORDER BY a.created_at DESC LIMIT 100");
                $stmt->execute([$user_id]);
            }
        }
        echo json_encode($stmt->fetchAll());
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = $_POST;

    $prospect_id = $data['prospect_id'] ?? null;
    $note = $data['note'] ?? $data['description'] ?? $data['content'] ?? null;
    $type = $data['type'] ?? $data['activity_type'] ?? 'nota';

    if (!$prospect_id || !$note) {
        http_response_code(400); echo json_encode(['error' => 'Faltan datos (prospect_id, note)']); exit;
    }

    if (!$is_admin) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE id = ? AND user_id = ?");
        $chk->execute([$prospect_id, $user_id]);
        if (!$chk->fetchColumn()) {
            http_response_code(403); echo json_encode(['error' => 'Sin acceso']); exit;
        }
    }

    try {
        $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, ?, ?)")
            ->execute([$prospect_id, $note, $type]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
