<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/../agentes/Services/ElevenLabsService.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}
$is_admin = ($_SESSION['user_role'] ?? 'subscriber') === 'admin';
$user_id = (int)$_SESSION['user_id'];
$method = strtoupper($_GET['method'] ?? 'GET');
$path = $_GET['path'] ?? '/';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    $parts = array_values(array_filter(explode('/', $path)));
    $resource = $parts[0] ?? '';
    $resourceId = $parts[1] ?? '';
    $action = $parts[2] ?? '';
    $actionParam = $parts[3] ?? '';

    // ========== PUBLIC CONFIG ==========
    if ($resource === 'api' && $parts[1] === 'agents' && !empty($parts[2]) && ($parts[3] ?? '') === 'config' && $method === 'GET') {
        $agentId = $parts[2];
        $stmt = $pdo->prepare("SELECT id, name, primary_color, widget_style, voice_mode, model, avatar FROM agents WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        if (!$agent) { http_response_code(404); echo json_encode(['error' => 'Agente no encontrado']); exit; }
        echo json_encode([
            'agent_id' => $agent['id'], 'name' => $agent['name'],
            'primary_color' => $agent['primary_color'] ?? '#2563eb',
            'widget_style' => $agent['widget_style'] ?? 'bubble',
            'voice_mode' => $agent['voice_mode'] ?? 'none',
            'elevenlabs_agent_id' => $agent['elevenlabs_agent_id'] ?? null,
            'model' => $agent['model'],
            'avatar_url' => $agent['avatar'] ? ($agent['avatar']) : null,
        ]);
        exit;
    }

    // ========== ADMIN AGENTS ==========
    if ($resource === 'admin') {
        if ($parts[1] === 'users') {
            $userId = $parts[2] ?? '';
            $userAction = $parts[3] ?? '';

            if ($userId === '' && $method === 'GET') {
                // List users
                if (!$is_admin) respondError('Acceso no autorizado', 403);
                $stmt = $pdo->query('SELECT id, name, email, role, can_create_agents FROM users ORDER BY name ASC');
                echo json_encode(['users' => $stmt->fetchAll()]);
                exit;
            }

            if ($userId !== '' && $userAction === 'agents' && $method === 'GET') {
                if (!$is_admin) respondError('Acceso no autorizado', 403);
                $stmt = $pdo->prepare('SELECT a.id, a.name, a.model, a.is_active FROM agents a JOIN agent_subscriptions s ON a.id = s.agent_id WHERE s.user_id = ?');
                $stmt->execute([$userId]);
                echo json_encode(['agents' => $stmt->fetchAll()]);
                exit;
            }

            if ($userId !== '' && $userAction === 'agents' && $method === 'POST') {
                if (!$is_admin) respondError('Acceso no autorizado', 403);
                $agentId = $input['agent_id'] ?? '';
                if (!$agentId) respondError('agent_id requerido', 400);
                $stmt = $pdo->prepare("INSERT IGNORE INTO agent_subscriptions (agent_id, user_id) VALUES (?, ?)");
                $stmt->execute([$agentId, $userId]);
                echo json_encode(['success' => true]);
                exit;
            }

            if ($userId !== '' && $userAction === 'permissions' && $method === 'PATCH') {
                if (!$is_admin) respondError('Acceso no autorizado', 403);
                $allowed = $input['can_create_agents'] ?? null;
                if (!is_bool($allowed)) respondError('can_create_agents debe ser booleano', 400);
                $pdo->prepare('UPDATE users SET can_create_agents = ? WHERE id = ?')->execute([(int)$allowed, $userId]);
                echo json_encode(['success' => true, 'can_create_agents' => $allowed]);
                exit;
            }

            respondError('Ruta no encontrada', 404);
        }

        if ($parts[1] !== 'agents') respondError('Ruta no encontrada', 404);

        $agentId = $parts[2] ?? '';
        $subAction = $parts[3] ?? '';
        $subActionParam = $parts[4] ?? '';

        // GET /admin/agents ? list
        if ($agentId === '' && $method === 'GET') {
            $sql = "SELECT a.id, a.name, a.model, a.mode, a.widget_style, a.voice_mode,
                    a.is_active, a.daily_message_limit, a.created_at, a.owner_crm_user_id,
                    u.name as owner_name,
                    (SELECT COUNT(*) FROM knowledge_files kf WHERE kf.agent_id = a.id) as files_count
                    FROM agents a LEFT JOIN users u ON a.owner_crm_user_id = u.id";
            if ($is_admin) {
                $stmt = $pdo->query($sql . " ORDER BY a.created_at DESC");
            } else {
                $sql .= " WHERE a.is_active = 1 AND (a.owner_crm_user_id = ? OR a.id IN (SELECT agent_id FROM agent_subscriptions WHERE user_id = ?)) ORDER BY a.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $user_id]);
            }
            echo json_encode(['agents' => $stmt->fetchAll()]);
            exit;
        }

        // POST /admin/agents ? create
        if ($agentId === '' && $method === 'POST') {
            if (!$is_admin) {
                $perm = $pdo->prepare("SELECT can_create_agents FROM users WHERE id = ?");
                $perm->execute([$user_id]);
                if (!(int)$perm->fetchColumn()) respondError('No tienes permiso para crear agentes', 403);
            }
            $name = trim($input['name'] ?? '');
            if ($name === '') respondError('Nombre requerido', 400);
            $agentIdNew = 'ag_' . bin2hex(random_bytes(14));
            $stmt = $pdo->prepare("INSERT INTO agents (id, name, personality_prompt, model, mode, widget_style, voice_mode, primary_color, owner_crm_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $agentIdNew, $name,
                $input['personality_prompt'] ?? 'Eres un asistente util y amable.',
                $input['model'] ?? 'gpt-4o-mini',
                $input['mode'] ?? 'preciso',
                $input['widget_style'] ?? 'bubble',
                'none',
                $input['primary_color'] ?? '#2563eb',
                $is_admin ? null : $user_id
            ]);
            echo json_encode([
                'agent_id' => $agentIdNew,
                'name' => $name,
                'embed_script' => '<script src="/crm/agentes/widget/widget.js?v=' . time() . '" data-agent-id="' . $agentIdNew . '"></script>',
            ]);
            exit;
        }

        if ($agentId === '') respondError('ID de agente requerido', 400);
        if (!preg_match('/^ag_[a-f0-9]{28}$/', $agentId)) respondError('ID de agente invalido', 400);

        // Validate agent exists
        $stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch();
        if (!$agent) respondError('Agente no encontrado', 404);

        // Access control
        function checkAccess(array $agent, bool $isOwnerCheck = false): void {
            global $is_admin, $user_id, $pdo;
            if ($is_admin) return;
            $own = (int)($agent['owner_crm_user_id'] ?? 0) === $user_id;
            if ($isOwnerCheck && !$own) respondError('No tienes permiso para esta accion', 403);
            if (!$own) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM agent_subscriptions WHERE agent_id = ? AND user_id = ?");
                $stmt->execute([$agent['id'], $user_id]);
                if (!(int)$stmt->fetchColumn()) respondError('Acceso no autorizado a este agente', 403);
            }
        }

        // GET /admin/agents/{id}
        if ($subAction === '' && $method === 'GET') {
            checkAccess($agent);
            echo json_encode(['agent' => $agent]);
            exit;
        }

        // PUT /admin/agents/{id}
        if ($subAction === '' && $method === 'PUT') {
            checkAccess($agent, true);

            // Campos editables por el usuario (elevenlabs_agent_id es solo-lectura desde el front)
            $allowedFields = ['name','personality_prompt','model','mode','widget_style','voice_mode','primary_color','max_messages_per_session','max_tokens_response','max_message_length','context_messages','daily_message_limit','is_active'];
            $updates = []; $params = [];
            foreach ($allowedFields as $f) {
                if (!array_key_exists($f, $input)) continue;
                $v = $input[$f];
                if ($f === 'voice_mode' && !in_array($v, ['none', 'elevenlabs'], true)) $v = 'none';
                if ($f === 'is_active') $v = $v ? 1 : 0;
                if (in_array($f, ['max_messages_per_session','max_tokens_response','max_message_length','context_messages','daily_message_limit'])) $v = max(1, (int)$v);
                if ($f === 'primary_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) continue;
                $updates[] = "$f = ?"; $params[] = $v;
            }
            if (empty($updates)) respondError('No hay campos validos para actualizar', 400);

            // ?? Sincronización automática con ElevenLabs ??????????????????????
            $newVoiceMode     = $input['voice_mode'] ?? $agent['voice_mode'];
            $newName          = $input['name'] ?? $agent['name'];
            $newPrompt        = $input['personality_prompt'] ?? $agent['personality_prompt'] ?? '';
            $currentElId      = $agent['elevenlabs_agent_id'] ?? null;
            $elSyncError      = null;

            if ($newVoiceMode === 'elevenlabs') {
                try {
                    $el = ElevenLabsService::fromDatabase($pdo);
                    
                    // 1. Sincronizar automáticamente cualquier archivo que no se haya subido a ElevenLabs aún
                    $stFiles = $pdo->prepare("SELECT id, original_filename, stored_filename, mime_type, elevenlabs_doc_id FROM knowledge_files WHERE agent_id = ?");
                    $stFiles->execute([$agentId]);
                    $agentFiles = $stFiles->fetchAll();
                    
                    $docIds = [];
                    foreach ($agentFiles as $file) {
                        $elDocId = $file['elevenlabs_doc_id'];
                        if (empty($elDocId)) {
                            $filePath = __DIR__ . '/../storage/knowledge/' . $file['stored_filename'];
                            if (file_exists($filePath)) {
                                try {
                                    $elDocId = $el->uploadKnowledgeFile($filePath, $file['original_filename'], $file['mime_type']);
                                    $pdo->prepare("UPDATE knowledge_files SET elevenlabs_doc_id = ? WHERE id = ?")
                                        ->execute([$elDocId, $file['id']]);
                                } catch (\Exception $uploadEx) {
                                    // Continuar con los demás aunque uno falle temporalmente
                                }
                            }
                        }
                        if (!empty($elDocId)) {
                            $docIds[] = $elDocId;
                        }
                    }

                    // 2. Crear o actualizar el agente con la lista completa de documentos
                    if (empty($currentElId)) {
                        $newElId = $el->createAgent($newName, $newPrompt);
                        $updates[] = 'elevenlabs_agent_id = ?';
                        $params[]  = $newElId;
                        if (!empty($docIds)) {
                            $el->updateAgent($newElId, $newName, $newPrompt, $docIds);
                        }
                    } else {
                        $el->updateAgent($currentElId, $newName, $newPrompt, $docIds);
                    }
                } catch (\RuntimeException $e) {
                    $elSyncError = $e->getMessage();
                }
            } elseif ($newVoiceMode === 'none' && !empty($currentElId)) {
                // El admin desactivó la voz ? limpiar el agente de ElevenLabs
                try {
                    $el = ElevenLabsService::fromDatabase($pdo);
                    $el->deleteAgent($currentElId);
                } catch (\RuntimeException $e) { /* ignorar errores de limpieza */ }
                $updates[] = 'elevenlabs_agent_id = ?';
                $params[]  = null;
            }
            // ?????????????????????????????????????????????????????????????????

            $params[] = $agentId;
            $pdo->prepare("UPDATE agents SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

            $response = ['success' => true];
            if ($elSyncError) {
                $response['warning'] = 'Agente guardado, pero ocurrió un error al sincronizar con ElevenLabs: ' . $elSyncError;
            } else if ($newVoiceMode === 'elevenlabs') {
                $response['elevenlabs_synced'] = true;
            }
            echo json_encode($response);
            exit;
        }

        // DELETE /admin/agents/{id}
        if ($subAction === '' && $method === 'DELETE') {
            if (!$is_admin) respondError('Solo administradores pueden eliminar agentes', 403);
            checkAccess($agent, true);

            // Eliminar agente de ElevenLabs si existe
            if (!empty($agent['elevenlabs_agent_id'])) {
                try {
                    $el = ElevenLabsService::fromDatabase($pdo);
                    $el->deleteAgent($agent['elevenlabs_agent_id']);
                } catch (\RuntimeException $e) { /* ignorar si falla la limpieza */ }
            }

            $stmt = $pdo->prepare("SELECT stored_filename FROM knowledge_files WHERE agent_id = ?");
            $stmt->execute([$agentId]);
            $files = $stmt->fetchAll();
            $pdo->prepare("DELETE FROM agents WHERE id = ?")->execute([$agentId]);
            foreach ($files as $f) {
                $path = __DIR__ . '/../storage/knowledge/' . $f['stored_filename'];
                if (file_exists($path)) unlink($path);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        // ===== UPLOAD FILE =====
        if ($subAction === 'upload' && $method === 'POST') {
            checkAccess($agent, true);
            if (!isset($_FILES['file'])) respondError('No se envio ningun archivo', 400);
            require_once __DIR__ . '/../agentes/Services/KnowledgeBase.php';
            $kb = new AgentKnowledgeBase($pdo);
            try {
                $result = $kb->upload($agentId, $_FILES['file']);
                // Si el agente tiene ElevenLabs activo, subir también a la KB de ElevenLabs
                if (($agent['voice_mode'] ?? '') === 'elevenlabs' && !empty($agent['elevenlabs_agent_id'])) {
                    try {
                        $el = ElevenLabsService::fromDatabase($pdo);
                        // Obtener los datos del archivo desde la base de datos
                        $stFile = $pdo->prepare("SELECT stored_filename, mime_type FROM knowledge_files WHERE id = ?");
                        $stFile->execute([$result['id']]);
                        $fileData = $stFile->fetch();
                        if ($fileData) {
                            $filePath = __DIR__ . '/../storage/knowledge/' . $fileData['stored_filename'];
                            if (file_exists($filePath)) {
                                $elDocId = $el->uploadKnowledgeFile($filePath, $result['name'], $fileData['mime_type']);
                                // Guardar el doc ID en la BD
                                $pdo->prepare("UPDATE knowledge_files SET elevenlabs_doc_id = ? WHERE id = ?")
                                    ->execute([$elDocId, $result['id']]);
                                $result['elevenlabs_doc_id'] = $elDocId;
                                // Actualizar el agente con el prompt y la nueva lista de documentos en una sola llamada
                                $stDocs = $pdo->prepare("SELECT elevenlabs_doc_id FROM knowledge_files WHERE agent_id = ? AND elevenlabs_doc_id IS NOT NULL");
                                $stDocs->execute([$agentId]);
                                $docIds = $stDocs->fetchAll(\PDO::FETCH_COLUMN);
                                $el->updateAgent($agent['elevenlabs_agent_id'], $agent['name'], $agent['personality_prompt'] ?? '', $docIds);
                            }
                        }
                    } catch (\RuntimeException $e) { /* no bloquear la subida si ElevenLabs falla */ }
                }
                echo json_encode(['success' => true, 'file' => $result]);
            } catch (RuntimeException $e) {
                respondError($e->getMessage(), $e->getCode() ?: 400);
            }
            exit;
        }

        // ===== FILES =====
        if ($subAction === 'files' && $subActionParam === '' && $method === 'GET') {
            checkAccess($agent);
            require_once __DIR__ . '/../agentes/Services/KnowledgeBase.php';
            $kb = new AgentKnowledgeBase($pdo);
            echo json_encode(['files' => $kb->listFiles($agentId)]);
            exit;
        }

        if ($subAction === 'files' && $subActionParam !== '' && $method === 'DELETE') {
            checkAccess($agent, true);
            require_once __DIR__ . '/../agentes/Services/KnowledgeBase.php';
            $kb = new AgentKnowledgeBase($pdo);
            try { 
                // Obtener el elevenlabs_doc_id antes de borrar
                $stFile = $pdo->prepare("SELECT elevenlabs_doc_id FROM knowledge_files WHERE id = ? AND agent_id = ?");
                $stFile->execute([(int)$subActionParam, $agentId]);
                $elDocId = $stFile->fetchColumn();

                $kb->delete((int)$subActionParam, $agentId);

                // Eliminar el doc de la KB de ElevenLabs y actualizar el agente
                if (!empty($elDocId) && ($agent['voice_mode'] ?? '') === 'elevenlabs' && !empty($agent['elevenlabs_agent_id'])) {
                    try {
                        $el = ElevenLabsService::fromDatabase($pdo);
                        $el->deleteKnowledgeDoc($elDocId);
                        // Actualizar el agente con el prompt y la lista de documentos restantes en una sola llamada
                        $stDocs = $pdo->prepare("SELECT elevenlabs_doc_id FROM knowledge_files WHERE agent_id = ? AND elevenlabs_doc_id IS NOT NULL");
                        $stDocs->execute([$agentId]);
                        $docIds = $stDocs->fetchAll(\PDO::FETCH_COLUMN);
                        $el->updateAgent($agent['elevenlabs_agent_id'], $agent['name'], $agent['personality_prompt'] ?? '', $docIds);
                    } catch (\RuntimeException $e) { /* ignorar errores de limpieza */ }
                }
                echo json_encode(['success' => true]); 
            }
            catch (RuntimeException $e) { respondError($e->getMessage(), $e->getCode() ?: 400); }
            exit;
        }

        // ===== KB-SYNC: re-sincronizar archivos existentes con ElevenLabs KB =====
        if ($subAction === 'kb-sync' && $method === 'POST') {
            checkAccess($agent, true);
            if (($agent['voice_mode'] ?? '') !== 'elevenlabs' || empty($agent['elevenlabs_agent_id'])) {
                respondError('El agente no tiene ElevenLabs activo', 400);
            }
            try {
                $el = ElevenLabsService::fromDatabase($pdo);
                // Obtener archivos sin elevenlabs_doc_id
                $stFiles = $pdo->prepare("SELECT id, original_filename, stored_filename, mime_type FROM knowledge_files WHERE agent_id = ? AND (elevenlabs_doc_id IS NULL OR elevenlabs_doc_id = '')");
                $stFiles->execute([$agentId]);
                $pendingFiles = $stFiles->fetchAll();
                $synced = 0;
                foreach ($pendingFiles as $file) {
                    $filePath = __DIR__ . '/../storage/knowledge/' . $file['stored_filename'];
                    if (file_exists($filePath)) {
                        $elDocId = $el->uploadKnowledgeFile($filePath, $file['original_filename'], $file['mime_type']);
                        $pdo->prepare("UPDATE knowledge_files SET elevenlabs_doc_id = ? WHERE id = ?")
                            ->execute([$elDocId, $file['id']]);
                        $synced++;
                    }
                }
                // Actualizar el agente con el prompt y todos los documentos sincronizados en una sola llamada
                $stDocs = $pdo->prepare("SELECT elevenlabs_doc_id FROM knowledge_files WHERE agent_id = ? AND elevenlabs_doc_id IS NOT NULL");
                $stDocs->execute([$agentId]);
                $docIds = $stDocs->fetchAll(\PDO::FETCH_COLUMN);
                $el->updateAgent($agent['elevenlabs_agent_id'], $agent['name'], $agent['personality_prompt'] ?? '', $docIds);
                echo json_encode(['success' => true, 'synced' => $synced, 'total_docs' => count($docIds)]);
            } catch (\RuntimeException $e) {
                respondError('Error al sincronizar: ' . $e->getMessage(), 500);
            }
            exit;
        }

        // ===== DOMAINS =====
        if ($subAction === 'domains' && $subActionParam === '' && $method === 'GET') {
            checkAccess($agent);
            $stmt = $pdo->prepare("SELECT id, domain, created_at FROM agent_domains WHERE agent_id = ? ORDER BY created_at DESC");
            $stmt->execute([$agentId]);
            echo json_encode(['domains' => $stmt->fetchAll()]);
            exit;
        }

        if ($subAction === 'domains' && $subActionParam === '' && $method === 'POST') {
            checkAccess($agent, true);
            $domain = strtolower(trim($input['domain'] ?? ''));
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/', $domain))
                respondError('Dominio invalido', 400);
            try {
                $stmt = $pdo->prepare("INSERT INTO agent_domains (agent_id, domain) VALUES (?, ?)");
                $stmt->execute([$agentId, $domain]);
                echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') respondError('El dominio ya esta registrado', 409);
                throw $e;
            }
            exit;
        }

        if ($subAction === 'domains' && $subActionParam !== '' && $method === 'DELETE') {
            checkAccess($agent, true);
            $stmt = $pdo->prepare("DELETE FROM agent_domains WHERE id = ? AND agent_id = ?");
            $stmt->execute([(int)$subActionParam, $agentId]);
            if (!$stmt->rowCount()) respondError('Dominio no encontrado', 404);
            echo json_encode(['success' => true]);
            exit;
        }

        // ===== EMBED =====
        if ($subAction === 'embed' && $method === 'GET') {
            checkAccess($agent);
            $color = $agent['primary_color'] ?? '#2563eb';
            $style = $agent['widget_style'] ?? 'bubble';
            echo json_encode([
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'script' => '<script src="/crm/agentes/widget/widget.js?v=' . time() . '" data-agent-id="' . $agentId . '" data-primary-color="' . $color . '" data-style="' . $style . '"></script>',
            ]);
            exit;
        }

        // ===== AVATAR =====
        if ($subAction === 'avatar' && $method === 'POST') {
            checkAccess($agent, true);
            if (!isset($_FILES['avatar'])) respondError('No se envio ninguna imagen', 400);
            $file = $_FILES['avatar'];
            if ($file['error'] !== UPLOAD_ERR_OK) respondError('Error al subir el archivo', 400);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (!in_array($mime, $allowed, true)) respondError('Solo JPG, PNG, GIF o WebP', 400);
            $ext = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', default => 'jpg' };
            $stmt = $pdo->prepare("SELECT avatar FROM agents WHERE id = ?");
            $stmt->execute([$agentId]);
            $old = $stmt->fetchColumn();
            if ($old) { $oldPath = __DIR__ . '/..' . $old; if (file_exists($oldPath)) unlink($oldPath); }
            $stored = 'avatar_' . $agentId . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            if (!move_uploaded_file($file['tmp_name'], $destDir . $stored)) respondError('Error al guardar', 500);
            $avatarUrl = '/uploads/avatars/' . $stored;
            $pdo->prepare("UPDATE agents SET avatar = ? WHERE id = ?")->execute([$avatarUrl, $agentId]);
            echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
            exit;
        }

        if ($subAction === 'avatar' && $method === 'DELETE') {
            checkAccess($agent, true);
            $stmt = $pdo->prepare("SELECT avatar FROM agents WHERE id = ?");
            $stmt->execute([$agentId]);
            $old = $stmt->fetchColumn();
            if ($old) { $p = __DIR__ . '/..' . $old; if (file_exists($p)) unlink($p); }
            $pdo->prepare("UPDATE agents SET avatar = NULL WHERE id = ?")->execute([$agentId]);
            echo json_encode(['success' => true]);
            exit;
        }

        // ===== SESSIONS =====
        if ($subAction === 'sessions' && $subActionParam === '' && $method === 'GET') {
            checkAccess($agent);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(50, max(10, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['q'] ?? '');
            $where = 'WHERE s.agent_id = ?';
            $params = [$agentId];
            if ($search !== '') {
                $where .= ' AND (s.domain LIKE ? OR EXISTS (SELECT 1 FROM chat_messages m WHERE m.session_id = s.id AND m.content LIKE ?))';
                $like = '%' . $search . '%';
                $params[] = $like; $params[] = $like;
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_sessions s $where");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT s.id, s.session_token, s.message_count, s.domain, s.created_at, (SELECT content FROM chat_messages m WHERE m.session_id = s.id ORDER BY m.created_at DESC LIMIT 1) as last_message FROM chat_sessions s $where ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            echo json_encode(['sessions' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $limit))]);
            exit;
        }

        if ($subAction === 'sessions' && $subActionParam !== '') {
            $sessionId = (int)$subActionParam;
            $isExport = ($parts[4] ?? '') === 'export';
            checkAccess($agent);
            $stmt = $pdo->prepare("SELECT * FROM chat_sessions WHERE id = ? AND agent_id = ? LIMIT 1");
            $stmt->execute([$sessionId, $agentId]);
            $session = $stmt->fetch();
            if (!$session) respondError('Sesion no encontrada', 404);

            if ($isExport && $method === 'GET') {
                $stmt = $pdo->prepare("SELECT role, content, tokens_used, created_at FROM chat_messages WHERE session_id = ? ORDER BY id ASC");
                $stmt->execute([$sessionId]);
                $messages = $stmt->fetchAll();
                $format = $_GET['format'] ?? 'json';
                if ($format === 'txt') {
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Content-Disposition: attachment; filename="conversacion_' . $sessionId . '.txt"');
                    $lines = [str_repeat('=', 50), 'Agente: ' . $agent['name'], 'Inicio: ' . $session['created_at'], 'Mensajes: ' . $session['message_count'], str_repeat('=', 50), ''];
                    foreach ($messages as $m) { $role = $m['role'] === 'user' ? 'Usuario' : 'Asistente'; $lines[] = "[$role] " . $m['created_at']; $lines[] = str_repeat('-', 30); $lines[] = $m['content']; $lines[] = ''; }
                    echo implode("\n", $lines);
                } else {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="conversacion_' . $sessionId . '.json"');
                    echo json_encode(['agent' => $agent['name'], 'agent_id' => $agentId, 'session_id' => $session['id'], 'domain' => $session['domain'], 'started_at' => $session['created_at'], 'message_count' => $session['message_count'], 'messages' => array_map(fn($m) => ['role' => $m['role'], 'content' => $m['content'], 'time' => $m['created_at']], $messages)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                exit;
            }

            // GET session detail
            if ($method === 'GET') {
                $stmt = $pdo->prepare("SELECT id, role, content, tokens_used, created_at FROM chat_messages WHERE session_id = ? ORDER BY id ASC");
                $stmt->execute([$sessionId]);
                $session['messages'] = $stmt->fetchAll();
                echo json_encode(['session' => $session]);
                exit;
            }

            // DELETE session
            if ($method === 'DELETE') {
                $stmt = $pdo->prepare("DELETE FROM chat_sessions WHERE id = ? AND agent_id = ?");
                $stmt->execute([$sessionId, $agentId]);
                if (!$stmt->rowCount()) respondError('Sesion no encontrada', 404);
                echo json_encode(['success' => true]);
                exit;
            }
        }

        respondError('Ruta no encontrada', 404);
    }

    respondError('Ruta no encontrada', 404);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}

function respondError(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
