<?php
session_start();
require_once __DIR__ . '/api/config.php';

// Asegurar que la tabla users existe
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
    can_create_agents TINYINT(1) DEFAULT 0,
    slug VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$hasUsers = $count > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if ($pass !== $confirm) { $error = 'Las contraseñas no coinciden'; }
    elseif (strlen($pass) < 6) { $error = 'Mínimo 6 caracteres'; }
    else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        if (!$hasUsers && !empty($_POST['name']) && !empty($_POST['email'])) {
            // Crear primer admin
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([trim($_POST['name']), trim($_POST['email']), $hash]);
            $ok = "Admin <b>" . htmlspecialchars($_POST['name']) . "</b> creado. <a href='login.php'>Iniciar sesión</a>";
            $hasUsers = true;
        } else {
            // Resetear password de usuario existente
            $email = trim($_POST['email'] ?? '');
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            if ($stmt->rowCount()) {
                $ok = "Contraseña actualizada para <b>" . htmlspecialchars($email) . "</b>. <a href='login.php'>Iniciar sesión</a>";
            } else {
                $error = "Email no encontrado";
            }
        }
    }
}

$emails = $hasUsers ? $pdo->query("SELECT id, email, name, role FROM users ORDER BY id")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reset Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:#f1f5f9;font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}</style>
</head>
<body>
<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
<?php if (isset($ok)): ?>
  <div class="text-emerald-700 bg-emerald-50 rounded-xl p-4 mb-4"><?= $ok ?></div>
<?php else: ?>
  <h2 class="text-2xl font-bold mb-1"><?= $hasUsers ? 'Reset Password' : 'Crear Admin' ?></h2>
  <p class="text-slate-500 text-sm mb-6">
    <?= $hasUsers ? 'Usuarios en la DB — seleccioná uno:' : 'No hay usuarios. Creá el primer administrador:' ?>
  </p>

  <?php if ($hasUsers): ?>
    <ul class="mb-6 space-y-2">
    <?php foreach ($emails as $u): ?>
      <li class="text-xs bg-slate-50 rounded-lg p-3 flex justify-between">
        <span><b><?= htmlspecialchars($u['name']) ?></b> <span class="text-slate-400">(<?= htmlspecialchars($u['role']) ?>)</span></span>
        <span class="text-slate-500"><?= htmlspecialchars($u['email']) ?></span>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (isset($error)): ?><div class="text-red-600 bg-red-50 rounded-xl p-3 mb-4 text-sm"><?= $error ?></div><?php endif; ?>

  <form method="POST" class="space-y-4">
    <?php if (!$hasUsers): ?>
      <input type="text" name="name" placeholder="Nombre completo" required class="w-full border rounded-xl p-3 text-sm">
      <input type="email" name="email" placeholder="Email" required class="w-full border rounded-xl p-3 text-sm">
    <?php else: ?>
      <select name="email" class="w-full border rounded-xl p-3 text-sm" required>
        <option value="">Seleccioná tu email</option>
        <?php foreach ($emails as $u): ?>
          <option value="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['email']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input type="password" name="password" placeholder="Nueva contraseña" required class="w-full border rounded-xl p-3 text-sm">
    <input type="password" name="confirm" placeholder="Confirmar contraseña" required class="w-full border rounded-xl p-3 text-sm">
    <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700">
      <?= $hasUsers ? 'Cambiar contraseña' : 'Crear administrador' ?>
    </button>
  </form>
  <p class="text-xs text-slate-400 mt-4 text-center">Recordá <b>borrar este archivo</b> después de usarlo.</p>
<?php endif; ?>
</div>
</body>
</html>
