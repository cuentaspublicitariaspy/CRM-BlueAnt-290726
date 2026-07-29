<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';

// Migración: Crear tabla de settings si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT
    )");
    // Valor por defecto para card_bg
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('card_bg', '#1e293b')");
} catch (Exception $e) {}

$method = $_SERVER['REQUEST_METHOD'];

// Keys that are NEVER returned in plaintext to the browser (not even to admins)
define('SETTINGS_SENSITIVE_KEYS', ['elevenlabs_api_key']);

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $key = $row['setting_key'];
            if (in_array($key, SETTINGS_SENSITIVE_KEYS, true)) {
                // Never return the value — only whether it's configured
                if ($is_admin) {
                    $settings[$key . '_set'] = !empty($row['setting_value']);
                }
                continue;
            }
            $settings[$key] = $row['setting_value'];
        }
        echo json_encode($settings);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    if (!$is_admin) { http_response_code(403); echo json_encode(['error' => 'Solo administradores']); exit; }
    
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    try {
        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) { http_response_code(400); echo json_encode(['error' => 'Datos inválidos']); exit; }
            foreach ($data as $key => $value) {
                // Don't overwrite a sensitive key with an empty value
                if (in_array($key, SETTINGS_SENSITIVE_KEYS, true) && empty(trim((string)$value))) continue;
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        // Handle Form Data (POST & FILES)
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        if (isset($_POST['crm_name'])) {
            $name = trim($_POST['crm_name']);
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_name', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$name, $name]);
        }
        if (isset($_POST['card_bg'])) {
            $bg = trim($_POST['card_bg']);
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('card_bg', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$bg, $bg]);
        }

        if (isset($_FILES['crm_logo']) && $_FILES['crm_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['crm_logo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $fn   = 'logo_' . time() . '.' . $ext;
                $dest = $uploadDir . $fn;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $logoUrl = 'uploads/' . $fn;
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_logo', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$logoUrl, $logoUrl]);
                }
            }
        }

        if (isset($_FILES['crm_favicon']) && $_FILES['crm_favicon']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['crm_favicon'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','ico'])) {
                $fn   = 'favicon_' . time() . '.' . $ext;
                $dest = $uploadDir . $fn;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $favUrl = 'uploads/' . $fn;
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('crm_favicon', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$favUrl, $favUrl]);
                }
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
