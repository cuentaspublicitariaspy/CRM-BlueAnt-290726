<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];
$uploadDir = dirname(__DIR__) . '/uploads/';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Asegurar columnas en tabla users
$cols = [
    "whatsapp" => "ALTER TABLE users ADD COLUMN whatsapp VARCHAR(50)",
    "avatar"   => "ALTER TABLE users ADD COLUMN avatar VARCHAR(255)",
    "role"     => "ALTER TABLE users ADD COLUMN role VARCHAR(30) DEFAULT 'subscriber'",
];
foreach ($cols as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

// ── GET: obtener perfil ──
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT name, email, whatsapp, avatar, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && $user['avatar']) {
        // Construir URL absoluta para el avatar
        $user['avatar_url'] = CRM_URL . '/uploads/' . basename($user['avatar']);
    } else {
        $user['avatar_url'] = null;
    }
    echo json_encode($user);
    exit;
}

// ── POST: guardar datos ──
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // ── Rama A: JSON → datos de texto (nombre, whatsapp, rol, contraseña) ──
    if (stripos($contentType, 'application/json') !== false) {
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $data['action'] ?? '';

        // Cambio de contraseña
        if ($action === 'change_password') {
            $current = $data['current_password'] ?? '';
            $new     = $data['new_password']     ?? '';

            if (!$current || !$new) {
                echo json_encode(['error' => 'Faltan datos']); exit;
            }

            // Verificar actual
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current, $user['password'])) {
                echo json_encode(['error' => 'La contraseña actual es incorrecta']); exit;
            }

            if (strlen($new) < 6) {
                echo json_encode(['error' => 'La nueva contraseña debe tener al menos 6 caracteres']); exit;
            }

            // Guardar nueva
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        // Actualización de perfil estándar
        $name     = trim($data['name']     ?? '');
        $whatsapp = trim($data['whatsapp'] ?? '');
        $role     = trim($data['role']     ?? '');

        $sets   = [];
        $params = [];
        if ($name !== '')     { $sets[] = "name = ?";     $params[] = $name;     $_SESSION['user_name'] = $name; }
        if ($whatsapp !== '') { $sets[] = "whatsapp = ?"; $params[] = $whatsapp; }
        if ($role !== '' && in_array($role, ['admin','subscriber']) && ($_SESSION['user_role'] ?? '') === 'admin') {
            $sets[] = "role = ?"; $params[] = $role;
        }
        if (!empty($sets)) {
            $params[] = $user_id;
            $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Rama B: Multipart → subida de foto (XHR) ──
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo json_encode(['error' => 'Formato no permitido']); exit;
        }
        $fn   = 'profile_' . $user_id . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $fn;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$fn, $user_id]);
            $avatarUrl = CRM_URL . '/uploads/' . $fn;
            echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
        } else {
            echo json_encode(['error' => 'No se pudo mover el archivo a: ' . $dest]);
        }
        exit;
    }

    echo json_encode(['error' => 'Sin datos recibidos. Content-Type: ' . $contentType]);
}

echo json_encode(['error' => 'Método no permitido']);
?>
