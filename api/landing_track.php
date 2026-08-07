<?php
/**
 * Landing Track API
 * Maneja visitas y leads mediante token de suscriptor
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'config.php';

// Crear tabla landing_subscriptions si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS landing_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landing_id INT NOT NULL,
        user_id INT NOT NULL,
        token VARCHAR(32) UNIQUE NOT NULL,
        views INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_landing_user (landing_id, user_id)
    )");
} catch (Exception $e) {}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? '';
$token  = trim($data['token'] ?? '');

if (!$token) { echo json_encode(['error' => 'Token requerido']); exit; }

// Obtener suscripción
$stmtSub = $pdo->prepare("SELECT * FROM landing_subscriptions WHERE token = ?");
$stmtSub->execute([$token]);
$sub = $stmtSub->fetch(PDO::FETCH_ASSOC);

if (!$sub) { echo json_encode(['error' => 'Token inválido']); exit; }

// REGISTRAR VISITA
if ($action === 'view') {
    $pdo->prepare("UPDATE landing_subscriptions SET views = views + 1 WHERE token = ?")
        ->execute([$token]);
    echo json_encode(['success' => true]);
    exit;
}

// REGISTRAR LEAD
if ($action === 'lead') {
    $name     = trim($data['name']     ?? '');
    $email    = trim($data['email']    ?? '');
    // Whatsapp es opcional: el modal propio del CRM siempre lo pide, pero
    // cuando la landing usa su propio formulario (ver api/landings.php) ese
    // formulario puede no tener un campo de teléfono reconocible.
    $whatsapp = trim($data['whatsapp'] ?? '');

    if (!$name || !$email) {
        echo json_encode(['error' => 'Campos incompletos']); exit;
    }

    try {
        // Verificar si ya existe el email para esta suscripción (evitar duplicados)
        $chk = $pdo->prepare("SELECT id FROM prospects WHERE email = ? AND landing_id = ? AND user_id = ?");
        $chk->execute([$email, $sub['landing_id'], $sub['user_id']]);
        $existing_id = $chk->fetchColumn();

        $response = ['success' => true];

        if ($existing_id) {
            $response['note'] = 'Ya registrado';
            $response['id'] = (int)$existing_id;
        } else {
            $pdo->prepare("INSERT INTO prospects (user_id, name, email, whatsapp, landing_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$sub['user_id'], $name, $email, $whatsapp, $sub['landing_id']]);
            $response['id'] = (int)$pdo->lastInsertId();
        }

        // Procesar redirección personalizada
        $redirect_type = $sub['redirect_type'] ?? 'default';
        if ($redirect_type === 'url' && !empty($sub['redirect_url'])) {
            $response['redirect_url'] = $sub['redirect_url'];
        } elseif ($redirect_type === 'whatsapp' && !empty($sub['whatsapp_number'])) {
            $wa_num = preg_replace('/[^0-9]/', '', $sub['whatsapp_number']);
            $wa_msg = urlencode($sub['whatsapp_message'] ?? '');
            $response['redirect_url'] = "https://wa.me/{$wa_num}?text={$wa_msg}";
        }

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
?>
