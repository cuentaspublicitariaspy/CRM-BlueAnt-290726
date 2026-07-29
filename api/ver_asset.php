<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("SELECT base_image_path FROM marketing_templates WHERE id = ?");
$stmt->execute([$id]);
$tpl = $stmt->fetch();

if (!$tpl) {
    http_response_code(404);
    exit;
}

$filePath = dirname(__DIR__) . '/' . ltrim($tpl['base_image_path'], '/');

if (!file_exists($filePath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = 'image/jpeg';
if ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'pdf') {
    $mime = 'application/pdf';
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($filePath);
exit;
