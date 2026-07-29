<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Crear tabla de usuarios si no existe
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20),
    avatar VARCHAR(255),
    role VARCHAR(20) DEFAULT 'subscriber',
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

if ($action === 'register') {
    $name = trim($data['name'] ?? '');
    $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $passwordRaw = trim($data['password'] ?? '');

    if (!$name || !$email || !$passwordRaw || strlen($passwordRaw) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nombre, email y contraseña válidos son requeridos (mínimo 6 caracteres)']);
        exit;
    }

    $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'El email ya está registrado']);
    }
}

elseif ($action === 'login') {
    $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $data['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email y contraseña son requeridos']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Verificar si el usuario está activo
        if (isset($user['active']) && (int)$user['active'] === 0) {
            echo json_encode(['success' => false, 'error' => 'Tu usuario ha sido desactivado. Contactá con el Administrador.']);
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'] ?? 'subscriber';
        echo json_encode(['success' => true, 'role' => $_SESSION['user_role']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas']);
    }
}

elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
}

elseif ($action === 'forgot_password') {
    // En producción, no confirmar la existencia del email para evitar enumeración de cuentas
    $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email inválido']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        // Generar token de recuperación en un sistema real y enviar por email
    }
    echo json_encode(['success' => true, 'message' => 'Si existe el email, se ha enviado un enlace de recuperación']);
}
?>
