<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Helpers/OAuthState.php';
require_once __DIR__ . '/../agenda/Services/GoogleCalendarService.php';

/**
 * api/agenda-google-callback.php
 * Vuelta del consentimiento de Google. Redirect URI registrada tal cual en
 * Google Cloud Console. Siempre termina en un redirect de página completa
 * de vuelta a agenda.php — nunca devuelve JSON (esto no lo llama el
 * frontend por fetch, lo llama el navegador de Google).
 */

function backToAgenda(int $resourceId, string $status, string $msg = ''): void {
    $qs = http_build_query(['tab' => 'config', 'resource_id' => $resourceId, 'google' => $status, 'msg' => $msg]);
    header('Location: ' . rtrim(CRM_URL, '/') . '/agenda.php?' . $qs);
    exit;
}

$session = AgendaAuth::requireSession();
AgendaAuth::requireAdmin($session);
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, null);

if (!empty($_GET['error'])) {
    backToAgenda(0, 'error', 'Cancelaste el permiso en Google.');
}

$rawState = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
if ($rawState === '' || $code === '') {
    backToAgenda(0, 'error', 'Faltan parámetros de Google.');
}

try {
    $state = OAuthState::decode($rawState);
} catch (\Throwable $e) {
    backToAgenda(0, 'error', $e->getMessage());
}

$resourceId = (int)($state['resource_id'] ?? 0);
$stateUserId = (int)($state['user_id'] ?? 0);
if (!$resourceId || $stateUserId !== $ownerUserId) {
    backToAgenda($resourceId, 'error', 'La sesión cambió durante la conexión, intentá de nuevo.');
}

$stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
$stmt->execute([$resourceId, $ownerUserId]);
if (!$stmt->fetch()) {
    backToAgenda($resourceId, 'error', 'El recurso ya no existe.');
}

try {
    (new AgendaGoogleCalendarService($pdo))->connectResource($resourceId, $ownerUserId, $code);
} catch (\Throwable $e) {
    error_log('Agenda Google Calendar connect error: ' . $e->getMessage());
    backToAgenda($resourceId, 'error', $e->getMessage());
}

backToAgenda($resourceId, 'connected');
