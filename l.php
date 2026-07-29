<?php
/**
 * Landing Router - Redirige al nuevo formato landings_gen/{slug}?lp={id}
 * URL antigua: /crm/l.php?t=TOKEN
 */
require_once 'api/config.php';

$token = trim($_GET['t'] ?? '');
if (!$token) { http_response_code(404); echo "Token inválido."; exit; }

try {
    $stmt = $pdo->prepare("
        SELECT ls.landing_id, ls.user_id, u.slug
        FROM landing_subscriptions ls
        JOIN landings l ON ls.landing_id = l.id
        JOIN users u ON ls.user_id = u.id
        WHERE ls.token = ?
    ");
    $stmt->execute([$token]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500); echo "Error de servidor."; exit;
}

if (!$sub) { http_response_code(404); echo "Landing no encontrada o enlace inválido."; exit; }

$slug = $sub['slug'] ?: 'usuario-' . $sub['user_id'];
$destino = CRM_URL . '/landings_gen/' . urlencode($slug) . '?lp=' . $sub['landing_id'];

header("Location: " . $destino, true, 302);
exit;
