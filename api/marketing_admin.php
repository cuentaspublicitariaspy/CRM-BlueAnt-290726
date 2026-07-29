<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $name = $_POST['name'] ?? '';
    $qr_x = (int)($_POST['qr_x'] ?? 0);
    $qr_y = (int)($_POST['qr_y'] ?? 0);
    $qr_size = (int)($_POST['qr_size'] ?? 200);
    $output_format = $_POST['output_format'] ?? 'jpg';

    if (empty($name) || !isset($_FILES['base_image']) || $_FILES['base_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos o error en la imagen']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/visual/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['base_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        echo json_encode(['success' => false, 'error' => 'Solo JPG, PNG o PDF permitidos']);
        exit;
    }

    $filename = 'template_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['base_image']['tmp_name'], $targetPath)) {
        $baseImagePath = 'visual/' . $filename;
        
        $stmt = $pdo->prepare("INSERT INTO marketing_templates (name, base_image_path, qr_x, qr_y, qr_size, output_format) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $baseImagePath, $qr_x, $qr_y, $qr_size, $output_format]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al mover el archivo subido']);
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("SELECT base_image_path FROM marketing_templates WHERE id = ?");
        $stmt->execute([$id]);
        $tpl = $stmt->fetch();
        if ($tpl) {
            $path = dirname(__DIR__) . '/' . ltrim($tpl['base_image_path'], '/');
            if (file_exists($path)) {
                @unlink($path);
            }
            $pdo->prepare("DELETE FROM marketing_templates WHERE id = ?")->execute([$id]);
        }
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
