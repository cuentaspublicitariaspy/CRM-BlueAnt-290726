<?php
/**
 * api/elevenlabs.php
 * Endpoint seguro para que el widget obtenga una signed URL de ElevenLabs.
 * La API key NUNCA se expone al cliente.
 *
 * GET  /api/elevenlabs.php?agent_id=ag_xxx&action=signed-url  ? { signed_url: "..." }
 * POST /api/elevenlabs.php?action=verify                      ? { valid: true }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agentes/Services/ElevenLabsService.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'signed-url';

// ?? Obtener Signed URL (llamada del widget, NO requiere sesión de admin) ???????
if ($action === 'signed-url' && $method === 'GET') {
    $agentId = trim($_GET['agent_id'] ?? '');
    if (!$agentId || !preg_match('/^ag_[a-f0-9]{28}$/', $agentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'agent_id inválido']);
        exit;
    }

    // Buscar el elevenlabs_agent_id en la BD
    $stmt = $pdo->prepare("SELECT elevenlabs_agent_id, voice_mode, is_active FROM agents WHERE id = ? LIMIT 1");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
        http_response_code(404);
        echo json_encode(['error' => 'Agente no encontrado']);
        exit;
    }
    if (empty($agent['elevenlabs_agent_id'])) {
        http_response_code(503);
        echo json_encode(['error' => 'Este agente no tiene voz ElevenLabs configurada todavía']);
        exit;
    }
    if ($agent['voice_mode'] !== 'elevenlabs') {
        http_response_code(403);
        echo json_encode(['error' => 'El modo de voz no es ElevenLabs']);
        exit;
    }

    try {
        $el = ElevenLabsService::fromDatabase($pdo);
        $signedUrl = $el->getSignedUrl($agent['elevenlabs_agent_id']);
        echo json_encode(['signed_url' => $signedUrl]);
    } catch (\RuntimeException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ?? Verificar API Key (solo admins) ????????????????????????????????????????????
if ($action === 'verify' && $method === 'POST') {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Solo administradores']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $apiKey = trim($input['api_key'] ?? '');
    if (empty($apiKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'api_key requerida']);
        exit;
    }

    $el = new ElevenLabsService($apiKey);
    try {
        $el->getProfile();
        echo json_encode(['valid' => true]);
    } catch (\RuntimeException $e) {
        echo json_encode([
            'valid' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida']);
