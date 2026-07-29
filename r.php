<?php
require_once __DIR__ . '/api/config.php';

$slug = $_GET['slug'] ?? null;
$campana = $_GET['campana'] ?? null;

if (!$slug || !$campana) {
    http_response_code(404);
    die("Página no encontrada.");
}

try {
    // 1. Buscar distribuidor por slug
    $stmt = $pdo->prepare("SELECT id, slug FROM users WHERE slug = ? AND active = 1");
    $stmt->execute([$slug]);
    $distribuidor = $stmt->fetch();

    if (!$distribuidor) {
        http_response_code(404);
        die("Distribuidor no encontrado.");
    }

    // 2. Buscar la landing y la suscripción del distribuidor
    $stmt = $pdo->prepare("SELECT ls.token, ls.id as sub_id FROM landings l INNER JOIN landing_subscriptions ls ON l.id = ls.landing_id WHERE l.id = ? AND ls.user_id = ?");
    $stmt->execute([$campana, $distribuidor['id']]);
    $landing = $stmt->fetch();

    if (!$landing) {
        http_response_code(404);
        die("Campaña no encontrada para este distribuidor.");
    }

    // 3. Registrar analítica en la suscripción
    $stmt = $pdo->prepare("UPDATE landing_subscriptions SET views = views + 1 WHERE id = ?");
    $stmt->execute([$landing['sub_id']]);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    error_log("Escaneo QR Prospeccion: Distribuidor {$distribuidor['id']}, Landing {$campana}, IP $ip, UA $ua");

    // 4. Redireccionar a la landing pública por slug
    $slug = $distribuidor['slug'] ?? 'usuario-' . $distribuidor['id'];
    $destino = CRM_URL . '/landings_gen/' . urlencode($slug) . '?lp=' . $campana;

    // 5. Redirección 302
    header("Location: " . $destino, true, 302);
    exit;

} catch (Exception $e) {
    error_log("Error en redirect QR: " . $e->getMessage());
    http_response_code(500);
    die("Error del servidor.");
}
