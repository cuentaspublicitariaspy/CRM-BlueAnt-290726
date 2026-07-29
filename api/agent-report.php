<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }

$email    = $_GET['email'] ?? '';
$whatsapp = $_GET['whatsapp'] ?? '';

if (!$email && !$whatsapp) {
    echo json_encode(['error' => 'Se requiere email o whatsapp']);
    exit;
}

try {
    $sql = "SELECT cm.session_id, cm.intent, cm.topic, cm.lead_score_delta, cm.next_action
            FROM chat_message_metadata cm
            WHERE ";
    $params = [];
    if ($email) {
        $sql .= "JSON_UNQUOTE(JSON_EXTRACT(cm.extracted_fields, '$.email')) = ?";
        $params[] = $email;
    } else {
        $phoneClean = preg_replace('/[^0-9]/', '', $whatsapp);
        $sql .= "REPLACE(REPLACE(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(cm.extracted_fields, '$.phone')), ' ', ''), '+', ''), '-', '') LIKE ?";
        $params[] = '%' . $phoneClean . '%';
    }
    $sql .= " ORDER BY cm.created_at DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $session = $stmt->fetch();

    if (!$session) {
        echo json_encode(['error' => 'No se encontraron datos de agente para este prospecto']);
        exit;
    }

    $sessionId = (int)$session['session_id'];

    $stmt = $pdo->prepare("SELECT role, content, created_at FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT name, email, phone, company, lead_stage, lead_score, urgency, service_interest, main_problem, conversation_summary FROM lead_profiles WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $profile = $stmt->fetch();

    echo json_encode([
        'session' => $session,
        'messages' => $messages,
        'profile' => $profile ?: null,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
