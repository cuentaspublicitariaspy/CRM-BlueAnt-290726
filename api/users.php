<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
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
require_once 'db_config.php';
ob_clean();

if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'No autorizado']); exit; }
if (($_SESSION['user_role'] ?? '') !== 'admin') { echo json_encode(['error' => 'Solo administradores']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

// ── Migraciones ──
try { $pdo->exec("ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN can_create_agents TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE agents ADD COLUMN owner_crm_user_id INT NULL DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS landing_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    landing_id INT NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(32) UNIQUE NOT NULL,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lu (landing_id, user_id)
)"); } catch (Exception $e) {}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS agent_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_au (agent_id, user_id)
)"); } catch (Exception $e) {}

// ── GET: listar usuarios ──
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, u.role, u.active, u.whatsapp, u.avatar, u.slug,
                   u.created_at, u.can_create_agents,
            (SELECT COUNT(*) FROM prospects p WHERE p.user_id = u.id) as prospect_count
            FROM users u 
            ORDER BY u.role ASC, u.name ASC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── POST ──
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) { echo json_encode(['error' => 'JSON inválido']); exit; }

    $action = $data['action'] ?? '';
    $id     = (int)($data['id'] ?? 0);

    // ── Crear usuario ──
    if ($action === 'create_user') {
        $name     = trim($data['name']     ?? '');
        $email    = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = trim($data['password'] ?? '');
        $role     = in_array($data['role'] ?? '', ['admin','subscriber']) ? $data['role'] : 'subscriber';
        $slug     = trim($data['slug'] ?? '');

        if (!$name || !$email || !$password) {
            echo json_encode(['error' => 'Nombre, email y contraseña son requeridos']); exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']); exit;
        }
        if (!$slug) {
            $slug = strtolower(preg_replace('/[^a-z0-9_-]+/', '-', explode('@', $email)[0]));
        }

        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (name, email, password, role, active, slug) VALUES (?, ?, ?, ?, 1, ?)")
                ->execute([$name, $email, $hash, $role, $slug]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'El email ya está registrado']);
        }
        exit;
    }

    // ── Obtener landings del usuario con estado de asignación ──
    if ($action === 'get_user_landings') {
        $target = (int)($data['user_id'] ?? 0);
        if (!$target) { echo json_encode(['error' => 'user_id requerido']); exit; }
        try {
            $stmt = $pdo->prepare("
                SELECT l.id, l.title, l.description, l.color,
                    CASE WHEN ls.id IS NOT NULL THEN 1 ELSE 0 END AS assigned,
                    ls.token, ls.views
                FROM landings l
                LEFT JOIN landing_subscriptions ls ON l.id = ls.landing_id AND ls.user_id = ?
                ORDER BY l.title ASC
            ");
            $stmt->execute([$target]);
            $landings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener cantidad de prospectos sin landing (Importación Directa)
            $stmtD = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE user_id = ? AND landing_id IS NULL");
            $stmtD->execute([$target]);
            $directCount = (int)$stmtD->fetchColumn();

            if ($directCount > 0) {
                $landings[] = [
                    'id'          => 0,
                    'title'       => 'Importacion Directa',
                    'description' => 'Prospectos añadidos manualmente o importados',
                    'color'       => '#64748b',
                    'assigned'    => 1,
                    'token'       => null,
                    'views'       => $directCount
                ];
            }

            echo json_encode($landings);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Asignar o desasignar landing a usuario ──
    if ($action === 'assign_landing') {
        $target     = (int)($data['user_id']    ?? 0);
        $landing_id = (int)($data['landing_id'] ?? 0);
        $assign     = (bool)($data['assign']    ?? false);

        if (!$target || !$landing_id) { echo json_encode(['error' => 'Faltan datos']); exit; }

        try {
            if ($assign) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM landing_subscriptions WHERE landing_id = ? AND user_id = ?");
                $chk->execute([$landing_id, $target]);
                if (!(int)$chk->fetchColumn()) {
                    $token = bin2hex(random_bytes(12));
                    $pdo->prepare("INSERT INTO landing_subscriptions (landing_id, user_id, token) VALUES (?, ?, ?)")
                        ->execute([$landing_id, $target, $token]);
                }
            } else {
                $pdo->prepare("DELETE FROM landing_subscriptions WHERE landing_id = ? AND user_id = ?")
                    ->execute([$landing_id, $target]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Obtener agentes con estado de asignación para un usuario ──
    if ($action === 'get_user_agents') {
        $target = (int)($data['user_id'] ?? 0);
        if (!$target) { echo json_encode(['error' => 'user_id requerido']); exit; }

        try {
            $stmtAg = $pdo->query("
                SELECT id, name, model, is_active, primary_color, widget_style, owner_crm_user_id
                FROM agents
                ORDER BY name ASC
            ");
            $agents = $stmtAg->fetchAll(PDO::FETCH_ASSOC);

            // Traer asignaciones del usuario
            $stmtSub = $pdo->prepare("SELECT agent_id FROM agent_subscriptions WHERE user_id = ?");
            $stmtSub->execute([$target]);
            $assignedIds = array_column($stmtSub->fetchAll(PDO::FETCH_ASSOC), 'agent_id');

            // Traer nombre del dueño de cada agente (si tiene owner)
            $ownerIds = array_filter(array_column($agents, 'owner_crm_user_id'));
            $ownerNames = [];
            if (!empty($ownerIds)) {
                $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));
                $stmtOwners = $pdo->prepare("SELECT id, name FROM users WHERE id IN ($placeholders)");
                $stmtOwners->execute($ownerIds);
                foreach ($stmtOwners->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $ownerNames[(int)$row['id']] = $row['name'];
                }
            }

            foreach ($agents as &$ag) {
                $ownerId = (int)($ag['owner_crm_user_id'] ?? 0);
                // Asignado = está en agent_subscriptions O el dueño es el propio target
                $ag['assigned']   = (in_array($ag['id'], $assignedIds) || $ownerId === $target) ? 1 : 0;
                $ag['owner_name'] = $ownerId ? ($ownerNames[$ownerId] ?? 'Desconocido') : null;
                $ag['is_own']     = ($ownerId === $target) ? 1 : 0;
            }
            unset($ag);

            echo json_encode($agents);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Asignar o desasignar agente a usuario ──
    if ($action === 'assign_agent') {
        $target   = (int)($data['user_id']  ?? 0);
        $agent_id = trim($data['agent_id']  ?? '');
        $assign   = (bool)($data['assign']  ?? false);

        if (!$target || !$agent_id) { echo json_encode(['error' => 'Faltan datos']); exit; }

        try {
            if ($assign) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM agent_subscriptions WHERE agent_id = ? AND user_id = ?");
                $chk->execute([$agent_id, $target]);
                if (!(int)$chk->fetchColumn()) {
                    $pdo->prepare("INSERT INTO agent_subscriptions (agent_id, user_id) VALUES (?, ?)")
                        ->execute([$agent_id, $target]);
                }
            } else {
                $pdo->prepare("DELETE FROM agent_subscriptions WHERE agent_id = ? AND user_id = ?")
                    ->execute([$agent_id, $target]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Cambiar contraseña de usuario ──
    if ($action === 'change_password') {
        $target   = (int)($data['user_id'] ?? 0);
        $password = trim($data['password'] ?? '');
        
        if (!$target || !$password) {
            echo json_encode(['error' => 'Faltan datos']); exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']); exit;
        }
        
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$hash, $target]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Activar / desactivar permiso de crear agentes ──
    if ($action === 'toggle_can_create_agents') {
        $target = (int)($data['user_id'] ?? $id ?? 0);
        if (!$target) { echo json_encode(['error' => 'user_id requerido']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT can_create_agents FROM users WHERE id = ?");
            $stmt->execute([$target]);
            $current = (int)$stmt->fetchColumn();
            $new = $current === 1 ? 0 : 1;
            $pdo->prepare("UPDATE users SET can_create_agents = ? WHERE id = ?")->execute([$new, $target]);
            echo json_encode(['success' => true, 'can_create_agents' => $new]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Activar / desactivar agente (admin puede deshabilitar agentes de usuarios) ──
    if ($action === 'toggle_agent_active') {
        $agent_id = trim($data['agent_id'] ?? '');
        if (!$agent_id) { echo json_encode(['error' => 'Datos insuficientes']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT is_active FROM agents WHERE id = ?");
            $stmt->execute([$agent_id]);
            $current = (int)$stmt->fetchColumn();
            $new = $current === 1 ? 0 : 1;
            $pdo->prepare("UPDATE agents SET is_active = ? WHERE id = ?")->execute([$new, $agent_id]);
            echo json_encode(['success' => true, 'is_active' => $new]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Activar / desactivar usuario ──
    if ($action === 'toggle_active') {
        if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }
        try {
            $stmt = $pdo->prepare("SELECT active FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $new = (int)$stmt->fetchColumn() === 1 ? 0 : 1;
            $pdo->prepare("UPDATE users SET active = ? WHERE id = ?")->execute([$new, $id]);
            echo json_encode(['success' => true, 'active' => $new]);
        } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'set_role') {
        $role = in_array($data['role'] ?? '', ['admin','subscriber']) ? $data['role'] : 'subscriber';
        try {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
        exit;
    }

    // ── Actualizar usuario (nombre, email, rol, slug, contraseña opcional) ──
    if ($action === 'update_user') {
        $id       = (int)($data['id'] ?? 0);
        $name     = trim($data['name']     ?? '');
        $email    = trim($data['email']    ?? '');
        $role     = in_array($data['role'] ?? '', ['admin','subscriber']) ? $data['role'] : 'subscriber';
        $slug     = trim($data['slug'] ?? '');
        $password = trim($data['password'] ?? '');

        if (!$id || !$name || !$email) {
            echo json_encode(['error' => 'ID, nombre y email son requeridos']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Email inválido']); exit;
        }
        if ($password && strlen($password) < 6) {
            echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']); exit;
        }
        if (!$slug) {
            $slug = strtolower(explode('@', $email)[0]);
        }

        try {
            // Verificar email único (excluyendo este usuario)
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                echo json_encode(['error' => 'El email ya está registrado por otro usuario']); exit;
            }

            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, slug = ?, password = ? WHERE id = ?")
                    ->execute([$name, $email, $role, $slug, $hash, $id]);
            } else {
                $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, slug = ? WHERE id = ?")
                    ->execute([$name, $email, $role, $slug, $id]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Eliminar usuario ──
    if ($action === 'delete_user') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }

        // No permitir eliminarse a sí mismo
        if ($id === (int)($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['error' => 'No puedes eliminar tu propia cuenta']); exit;
        }

        try {
            $pdo->prepare("DELETE FROM agent_subscriptions WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM landing_subscriptions WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE prospects SET user_id = NULL WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida: ' . $action]);
}
?>
