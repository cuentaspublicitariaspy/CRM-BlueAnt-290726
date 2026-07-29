<?php
session_start();
header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'No autorizado']); exit; }
$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
$method  = $_SERVER['REQUEST_METHOD'];

// Migraciones seguras
try { $pdo->exec("CREATE TABLE IF NOT EXISTS prospects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    whatsapp VARCHAR(50),
    landing_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)"); } catch (Exception $e) {}

foreach ([
    "ALTER TABLE prospects ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id",
    "ALTER TABLE prospects ADD COLUMN landing_id INT AFTER whatsapp",
] as $sql) { try { $pdo->exec($sql); } catch (Exception $e) {} }

// Quitar restricción de email único para permitir re-importaciones o leads repetidos
try { $pdo->exec("ALTER TABLE prospects DROP INDEX email"); } catch (Exception $e) {}

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

// ── GET: listar prospectos del usuario ──
if ($method === 'GET') {
    try {
        // Prospectos del CRM (admin ve todos, otros usuarios solo los suyos)
        if ($is_admin) {
            $stmt = $pdo->query("
                SELECT p.*, l.title AS landing_title, l.color AS landing_color, p.agent_domain AS domain,
                       u.name AS user_name
                FROM prospects p
                LEFT JOIN landings l ON p.landing_id = l.id
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.status = 'prospecto' OR p.status IS NULL
                ORDER BY p.id DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, l.title AS landing_title, l.color AS landing_color, p.agent_domain AS domain 
                FROM prospects p
                LEFT JOIN landings l ON p.landing_id = l.id
                WHERE p.user_id = ? AND (p.status = 'prospecto' OR p.status IS NULL)
                ORDER BY p.id DESC
            ");
            $stmt->execute([$user_id]);
        }
        $prospects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Leads desde AgenteCRM (misma DB)
        try {
            if ($is_admin) {
                $stmtAg = $pdo->query("
                    SELECT lp.id, lp.name, lp.email, lp.phone AS whatsapp, 
                           lp.lead_stage AS status, lp.lead_score, lp.urgency, lp.estimated_budget,
                           lp.service_interest, lp.company, lp.main_problem, lp.conversation_summary, lp.created_at,
                           a.id AS agent_id, a.name AS agent_name, cs.domain AS agent_domain
                    FROM lead_profiles lp
                    JOIN agents a ON a.id = lp.agent_id
                    LEFT JOIN chat_sessions cs ON lp.session_id = cs.id
                    WHERE (lp.email IS NOT NULL AND lp.email != '')
                       OR (lp.phone IS NOT NULL AND lp.phone != '')
                    ORDER BY lp.created_at DESC
                    LIMIT 100
                ");
            } else {
                $stmtAg = $pdo->prepare("
                    SELECT lp.id, lp.name, lp.email, lp.phone AS whatsapp, 
                           lp.lead_stage AS status, lp.lead_score, lp.urgency, lp.estimated_budget,
                           lp.service_interest, lp.company, lp.main_problem, lp.conversation_summary, lp.created_at,
                           a.id AS agent_id, a.name AS agent_name, cs.domain AS agent_domain
                    FROM lead_profiles lp
                    JOIN agents a ON a.id = lp.agent_id
                    LEFT JOIN chat_sessions cs ON lp.session_id = cs.id
                    WHERE ((lp.email IS NOT NULL AND lp.email != '') OR (lp.phone IS NOT NULL AND lp.phone != ''))
                      AND (a.owner_crm_user_id = ? OR a.id IN (SELECT agent_id FROM agent_subscriptions WHERE user_id = ?))
                    ORDER BY lp.created_at DESC
                    LIMIT 100
                ");
                $stmtAg->execute([$user_id, $user_id]);
            }
            while ($row = $stmtAg->fetch()) {
                if (!$row['name']) $row['name'] = 'Lead de Agente #' . $row['id'];
                $row['landing_title'] = 'Agente: ' . ($row['agent_name'] ?? 'IA');
                $row['landing_color'] = '#6366f1';
                $row['agent_lead'] = true;
                $row['origin_type'] = 'agent';
                $row['agent_domain'] = $row['agent_domain'] ?? '';
                $row['domain'] = $row['agent_domain'] ?? '';
                $row['lead_score'] = (int)($row['lead_score'] ?? 0);
                unset($row['agent_name']);
                $prospects[] = $row;
            }
        } catch (\Throwable $e) {
            error_log("prospects.php: error querying agent leads: " . $e->getMessage());
        }

        // Ordenar por created_at DESC
        usort($prospects, function($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        respond($prospects);
    } catch (Exception $e) { respond(['error' => $e->getMessage()], 500); }
}

// ── POST ──
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = $_POST;

    $id         = $data['id']         ?? null;
    $action     = $data['action']     ?? null;
    $landing_id = $data['landing_id'] ?? null;

    // BORRAR (soporta masivo y agent leads)
    if ($action === 'delete' && (isset($data['id']) || isset($data['ids']))) {
        $ids = isset($data['ids']) ? (array)$data['ids'] : [$data['id']];
        if (empty($ids)) respond(['error' => 'IDs requeridos'], 400);

        try {
            if (!empty($data['agent_lead'])) {
                foreach ($ids as $lid) {
                    $pdo->prepare("DELETE FROM lead_profiles WHERE id = ?")->execute([(int)$lid]);
                }
            } else {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                if ($is_admin) {
                    $pdo->prepare("DELETE FROM activities WHERE prospect_id IN (SELECT id FROM prospects WHERE id IN ($placeholders))")->execute($ids);
                    $pdo->prepare("DELETE FROM prospects WHERE id IN ($placeholders)")
                        ->execute($ids);
                } else {
                    $pdo->prepare("DELETE FROM activities WHERE prospect_id IN (SELECT id FROM prospects WHERE id IN ($placeholders) AND user_id = ?)")->execute([...$ids, $user_id]);
                    $pdo->prepare("DELETE FROM prospects WHERE id IN ($placeholders) AND user_id = ?")
                        ->execute([...$ids, $user_id]);
                }
            }
            respond(['success' => true]);
        } catch (Exception $e) { respond(['error' => $e->getMessage()], 500); }
    }

    // ACTUALIZAR
    if (!empty($id) && !empty($data['name'])) {
        try {
            if ($is_admin) {
                $pdo->prepare("UPDATE prospects SET name = ?, email = ?, whatsapp = ? WHERE id = ?")
                    ->execute([$data['name'], $data['email'] ?? '', $data['whatsapp'] ?? '', $id]);
            } else {
                $pdo->prepare("UPDATE prospects SET name = ?, email = ?, whatsapp = ? WHERE id = ? AND user_id = ?")
                    ->execute([$data['name'], $data['email'] ?? '', $data['whatsapp'] ?? '', $id, $user_id]);
            }
            respond(['success' => true]);
        } catch (Exception $e) { respond(['error' => $e->getMessage()], 500); }
    }

    // CREAR MASIVO
    if ($action === 'bulk_create' && isset($data['prospects'])) {
        $prospects = (array)$data['prospects'];
        $count = 0;
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO prospects (user_id, name, email, whatsapp, origin_type) VALUES (?, ?, ?, ?, 'import')");
            foreach ($prospects as $p) {
                if (!empty($p['name']) && !empty($p['whatsapp'])) {
                    $stmt->execute([$user_id, $p['name'], $p['email'] ?? '', $p['whatsapp']]);
                    if ($stmt->rowCount() > 0) $count++;
                }
            }
            respond(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // CREAR (desde landing o desde CRM)
    if (empty($data['name']) || empty($data['whatsapp'])) {
        respond(['error' => 'Campos incompletos: name, whatsapp'], 400);
    }

    try {
        // Si viene de una landing, buscar el user_id dueño de esa landing
        $insert_user_id = $user_id;
        if (!empty($landing_id)) {
            $stmtL = $pdo->prepare("SELECT user_id FROM landings WHERE id = ?");
            $stmtL->execute([$landing_id]);
            $owner = $stmtL->fetchColumn();
            if ($owner) $insert_user_id = (int)$owner;
        }

        $origin_type = $data['origin_type'] ?? (empty($landing_id) ? 'manual' : 'landing');
        $agent_id = $data['agent_id'] ?? null;
        $agent_domain = $data['agent_domain'] ?? null;

        $pdo->prepare("INSERT INTO prospects (user_id, name, email, whatsapp, landing_id, origin_type, agent_id, agent_domain) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$insert_user_id, $data['name'], $data['email'], $data['whatsapp'], $landing_id ?: null, $origin_type, $agent_id, $agent_domain]);
        respond(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (Exception $e) { respond(['error' => $e->getMessage()], 500); }
}
?>
