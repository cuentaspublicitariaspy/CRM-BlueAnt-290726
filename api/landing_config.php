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
$is_admin = (($_SESSION['user_role'] ?? 'subscriber') === 'admin');
$method = $_SERVER['REQUEST_METHOD'];

/**
 * El dueño de una landing no tiene fila propia en landing_subscriptions —
 * esa tabla es para asignaciones a OTROS usuarios — así que nunca podía
 * configurar la acción post-registro de su propio enlace (ni el modal
 * cargaba nada, ni el guardado tocaba ninguna fila). Se la creamos on
 * demand la primera vez que la pide, con valores por defecto. Solo aplica
 * a admin: un suscriptor no debe poder auto-asignarse a una landing_id
 * arbitraria llamando a este endpoint directamente.
 */
function ensureOwnSubscription(PDO $pdo, int $landingId, int $userId): bool {
    $chk = $pdo->prepare("SELECT id FROM landing_subscriptions WHERE landing_id = ? AND user_id = ?");
    $chk->execute([$landingId, $userId]);
    if ($chk->fetchColumn()) return true;

    $exists = $pdo->prepare("SELECT id FROM landings WHERE id = ?");
    $exists->execute([$landingId]);
    if (!$exists->fetchColumn()) return false;

    $token = bin2hex(random_bytes(12));
    $pdo->prepare("INSERT IGNORE INTO landing_subscriptions (landing_id, user_id, token) VALUES (?, ?, ?)")
        ->execute([$landingId, $userId, $token]);
    return true;
}

if ($method === 'GET') {
    $landing_id = (int)($_GET['landing_id'] ?? 0);
    if ($landing_id <= 0) {
        echo json_encode(['error' => 'Landing ID inválido']);
        exit;
    }

    if ($is_admin && !ensureOwnSubscription($pdo, $landing_id, $user_id)) {
        echo json_encode(['error' => 'Landing no encontrada']);
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

    $chk = $pdo->prepare("SELECT id FROM landing_subscriptions WHERE user_id = ? AND landing_id = ?");
    $chk->execute([$user_id, $landing_id]);
    $exists = (bool)$chk->fetchColumn();

    if (!$exists) {
        if (!$is_admin || !ensureOwnSubscription($pdo, $landing_id, $user_id)) {
            echo json_encode(['error' => 'Suscripción no encontrada']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE landing_subscriptions
        SET redirect_type = ?, redirect_url = ?, whatsapp_number = ?, whatsapp_message = ?
        WHERE user_id = ? AND landing_id = ?
    ");

    $stmt->execute([
        $redirect_type,
        $redirect_url,
        $whatsapp_number,
        $whatsapp_message,
        $user_id,
        $landing_id
    ]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Método no permitido']);
?>
