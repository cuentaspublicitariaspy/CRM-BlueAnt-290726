<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Helpers/OAuthState.php';
require_once __DIR__ . '/../agenda/Services/GoogleCalendarService.php';

/**
 * api/agenda-google-connect.php
 * Arranca el flujo OAuth de Google Calendar para un recurso puntual —
 * navegación de página completa (no fetch/AJAX), porque termina en la
 * pantalla de consentimiento de Google. Vuelve a agenda-google-callback.php.
 */

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session);
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);

$resourceId = (int)($_GET['resource_id'] ?? 0);
if (!$resourceId) {
    http_response_code(400);
    die('Recurso requerido.');
}

$stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
$stmt->execute([$resourceId, $ownerUserId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die('Recurso no encontrado.');
}

if (!AgendaGoogleCalendarService::isConfigured()) {
    http_response_code(500);
    die('Google Calendar no está configurado en este servidor. Faltan GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET en el .env.');
}

$state = OAuthState::encode(['resource_id' => $resourceId, 'user_id' => $ownerUserId]);
header('Location: ' . AgendaGoogleCalendarService::buildAuthUrl($state));
exit;
