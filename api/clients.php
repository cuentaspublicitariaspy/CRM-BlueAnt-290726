<?php
// api/clients.php - Endpoint para gestionar clientes, conversión y servicios contratados
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── GET: listar clientes ──
if ($method === 'GET') {
    try {
        if ($is_admin) {
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       GROUP_CONCAT(s.id) AS service_ids,
                       GROUP_CONCAT(s.name SEPARATOR '||') AS service_names
                FROM prospects p
                LEFT JOIN prospect_services ps ON p.id = ps.prospect_id
                LEFT JOIN services s ON ps.service_id = s.id
                WHERE p.status IN ('cliente_activo', 'cliente_inactivo')
                GROUP BY p.id
                ORDER BY p.name ASC
            ");
            $stmt->execute([]);
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       GROUP_CONCAT(s.id) AS service_ids,
                       GROUP_CONCAT(s.name SEPARATOR '||') AS service_names
                FROM prospects p
                LEFT JOIN prospect_services ps ON p.id = ps.prospect_id
                LEFT JOIN services s ON ps.service_id = s.id
                WHERE p.user_id = ? AND p.status IN ('cliente_activo', 'cliente_inactivo')
                GROUP BY p.id
                ORDER BY p.name ASC
            ");
            $stmt->execute([$user_id]);
        }
        $clients = $stmt->fetchAll();

        // Formatear servicios en array
        foreach ($clients as &$c) {
            $c['services'] = [];
            if ($c['service_ids'] && $c['service_names']) {
                $ids = explode(',', $c['service_ids']);
                $names = explode('||', $c['service_names']);
                for ($i = 0; $i < count($ids); $i++) {
                    $c['services'][] = [
                        'id' => (int)$ids[$i],
                        'name' => $names[$i] ?? ''
                    ];
                }
            }
            unset($c['service_ids']);
            unset($c['service_names']);
        }

        respond($clients);
    } catch (Exception $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── POST: conversión, actualización, cambio de estado y agregado directo ──
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }

    $action = $data['action'] ?? null;

    // 1. CONVERTIR PROSPECTO A CLIENTE / ACTUALIZAR SERVICIOS DESDE DETALLE DE PROSPECTO
    if ($action === 'convert' && !empty($data['prospect_id'])) {
        $prospect_id = (int)$data['prospect_id'];
        $services = isset($data['services']) ? (array)$data['services'] : []; // Array de IDs de servicios

        try {
            $pdo->beginTransaction();

            // Verificar pertenencia del prospecto
            if ($is_admin) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE id = ?");
                $chk->execute([$prospect_id]);
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM prospects WHERE id = ? AND user_id = ?");
                $chk->execute([$prospect_id, $user_id]);
            }
            if (!$chk->fetchColumn()) {
                throw new Exception('Prospecto no encontrado o sin acceso');
            }

            // Campos avanzados
            $language = $data['language'] ?? 'es';
            $address = $data['address'] ?? null;
            $city = $data['city'] ?? null;
            $state = $data['state'] ?? null;
            $zip_code = $data['zip_code'] ?? null;
            $has_business = isset($data['has_business']) ? (int)$data['has_business'] : 0;
            
            $card_number = $data['card_number'] ?? null;
            $card_expiry = $data['card_expiry'] ?? null;
            $card_cvv = $data['card_cvv'] ?? null;

            // Actualizar prospecto a cliente_activo
            if ($is_admin) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE prospects 
                    SET status = 'cliente_activo',
                        language = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        zip_code = ?,
                        has_business = ?,
                        card_number = ?,
                        card_expiry = ?,
                        card_cvv = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $language, $address, $city, $state, $zip_code, 
                    $has_business, $card_number, $card_expiry, $card_cvv,
                    $prospect_id
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE prospects 
                    SET status = 'cliente_activo',
                        language = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        zip_code = ?,
                        has_business = ?,
                        card_number = ?,
                        card_expiry = ?,
                        card_cvv = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmtUpdate->execute([
                    $language, $address, $city, $state, $zip_code, 
                    $has_business, $card_number, $card_expiry, $card_cvv,
                    $prospect_id, $user_id
                ]);
            }

            // Actualizar servicios contratados
            $pdo->prepare("DELETE FROM prospect_services WHERE prospect_id = ?")->execute([$prospect_id]);
            
            $serviceNames = [];
            if (!empty($services)) {
                $stmtInsertService = $pdo->prepare("INSERT INTO prospect_services (prospect_id, service_id) VALUES (?, ?)");
                $stmtGetName = $pdo->prepare("SELECT name FROM services WHERE id = ?");
                
                foreach ($services as $srvId) {
                    $srvId = (int)$srvId;
                    $stmtInsertService->execute([$prospect_id, $srvId]);
                    
                    $stmtGetName->execute([$srvId]);
                    $nameSrv = $stmtGetName->fetchColumn();
                    if ($nameSrv) {
                        $serviceNames[] = $nameSrv;
                    }
                }
            }

            // Registrar actividad
            $srvText = empty($serviceNames) ? 'ninguno' : implode(', ', $serviceNames);
            $note = "Convertido a Cliente Activo. Servicios contratados: " . $srvText;
            if ($has_business) {
                $note .= " | Con negocio registrado.";
            }

            $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, ?, 'nota')")
                ->execute([$prospect_id, $note]);

            $pdo->commit();
            respond(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // 2. CAMBIAR ESTADO (ACTIVO/INACTIVO)
    if ($action === 'toggle_status' && !empty($data['client_id'])) {
        $client_id = (int)$data['client_id'];
        $new_status = $data['status'] ?? null; // 'cliente_activo' o 'cliente_inactivo'

        if (!in_array($new_status, ['cliente_activo', 'cliente_inactivo', 'prospecto'])) {
            respond(['error' => 'Estado inválido'], 400);
        }

        try {
            $pdo->beginTransaction();

            // Verificar pertenencia
            if ($is_admin) {
                $chk = $pdo->prepare("SELECT name FROM prospects WHERE id = ?");
                $chk->execute([$client_id]);
            } else {
                $chk = $pdo->prepare("SELECT name FROM prospects WHERE id = ? AND user_id = ?");
                $chk->execute([$client_id, $user_id]);
            }
            $client_name = $chk->fetchColumn();
            if (!$client_name) {
                throw new Exception('Cliente no encontrado');
            }

            if ($is_admin) {
                $stmt = $pdo->prepare("UPDATE prospects SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $client_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE prospects SET status = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$new_status, $client_id, $user_id]);
            }

            // Si pasa a prospecto, eliminar sus servicios contratados para coherencia
            if ($new_status === 'prospecto') {
                $pdo->prepare("DELETE FROM prospect_services WHERE prospect_id = ?")->execute([$client_id]);
            }

            // Loguear actividad
            $statusLabel = $new_status === 'cliente_activo' ? 'Activo' : ($new_status === 'cliente_inactivo' ? 'Inactivo' : 'Prospecto');
            $note = "Estado del contacto cambiado a: " . $statusLabel;
            $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, ?, 'nota')")
                ->execute([$client_id, $note]);

            $pdo->commit();
            respond(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // 3. EDITAR INFORMACIÓN DE CLIENTE (INCLUIDO SERVICIOS Y TARJETA)
    if ($action === 'update_client_info' && !empty($data['client_id'])) {
        $client_id = (int)$data['client_id'];
        $services = isset($data['services']) ? (array)$data['services'] : [];

        try {
            $pdo->beginTransaction();

            // Verificar pertenencia
            if ($is_admin) {
                $chk = $pdo->prepare("SELECT name, status FROM prospects WHERE id = ?");
                $chk->execute([$client_id]);
            } else {
                $chk = $pdo->prepare("SELECT name, status FROM prospects WHERE id = ? AND user_id = ?");
                $chk->execute([$client_id, $user_id]);
            }
            $currentData = $chk->fetch();
            if (!$currentData) {
                throw new Exception('Cliente no encontrado');
            }

            $name = $data['name'] ?? $currentData['name'];
            $email = $data['email'] ?? '';
            $whatsapp = $data['whatsapp'] ?? '';
            $status = $data['status'] ?? $currentData['status'];
            $language = $data['language'] ?? 'es';
            $address = $data['address'] ?? null;
            $city = $data['city'] ?? null;
            $state = $data['state'] ?? null;
            $zip_code = $data['zip_code'] ?? null;
            $has_business = isset($data['has_business']) ? (int)$data['has_business'] : 0;
            
            $card_number = $data['card_number'] ?? null;
            $card_expiry = $data['card_expiry'] ?? null;
            $card_cvv = $data['card_cvv'] ?? null;
            $annual_income = isset($data['annual_income']) && $data['annual_income'] !== '' ? (float)$data['annual_income'] : null;
            $marital_status = $data['marital_status'] ?? null;
            $spouse_income = isset($data['spouse_income']) && $data['spouse_income'] !== '' ? (float)$data['spouse_income'] : null;
            $owns_house = isset($data['owns_house']) ? (int)$data['owns_house'] : 0;

            // Calcular interest_score
            $interestScore = 0;
            if ($has_business) $interestScore++;
            if ($annual_income !== null && $annual_income >= 100000) $interestScore++;
            if (!empty($marital_status) && $marital_status === 'married') $interestScore++;
            if ($spouse_income !== null && $spouse_income > 0) $interestScore++;
            if ($owns_house) $interestScore++;
            if ($interestScore <= 1) $interestLevel = 'bajo';
            elseif ($interestScore <= 3) $interestLevel = 'medio';
            else $interestLevel = 'alto';

            // Si es inactivo o no tiene negocio, podemos limpiar los datos de tarjeta por seguridad
            if ($status === 'cliente_inactivo' || !$has_business) {
                $card_number = null;
                $card_expiry = null;
                $card_cvv = null;
            }

            // Actualizar tabla prospects
            if ($is_admin) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE prospects 
                    SET name = ?,
                        email = ?,
                        whatsapp = ?,
                        status = ?,
                        language = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        zip_code = ?,
                        has_business = ?,
                        card_number = ?,
                        card_expiry = ?,
                        card_cvv = ?,
                        annual_income = ?,
                        marital_status = ?,
                        spouse_income = ?,
                        owns_house = ?,
                        interest_score = ?,
                        interest_level = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $name, $email, $whatsapp, $status, $language, $address, $city, $state, $zip_code,
                    $has_business, $card_number, $card_expiry, $card_cvv,
                    $annual_income, $marital_status, $spouse_income, $owns_house,
                    $interestScore, $interestLevel,
                    $client_id
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE prospects 
                    SET name = ?,
                        email = ?,
                        whatsapp = ?,
                        status = ?,
                        language = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        zip_code = ?,
                        has_business = ?,
                        card_number = ?,
                        card_expiry = ?,
                        card_cvv = ?,
                        annual_income = ?,
                        marital_status = ?,
                        spouse_income = ?,
                        owns_house = ?,
                        interest_score = ?,
                        interest_level = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmtUpdate->execute([
                    $name, $email, $whatsapp, $status, $language, $address, $city, $state, $zip_code,
                    $has_business, $card_number, $card_expiry, $card_cvv,
                    $annual_income, $marital_status, $spouse_income, $owns_house,
                    $interestScore, $interestLevel,
                    $client_id, $user_id
                ]);
            }

            // Actualizar servicios contratados
            $pdo->prepare("DELETE FROM prospect_services WHERE prospect_id = ?")->execute([$client_id]);
            if (!empty($services)) {
                $stmtInsertService = $pdo->prepare("INSERT INTO prospect_services (prospect_id, service_id) VALUES (?, ?)");
                foreach ($services as $srvId) {
                    $stmtInsertService->execute([$client_id, (int)$srvId]);
                }
            }

            // Loguear actividad
            $note = "Información de cliente y servicios contratados actualizada.";
            $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, ?, 'nota')")
                ->execute([$client_id, $note]);

            $pdo->commit();
            respond(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // 4. AGREGAR CLIENTE DIRECTAMENTE
    if ($action === 'add_client') {
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? '';
        $whatsapp = $data['whatsapp'] ?? '';
        $status = $data['status'] ?? 'cliente_activo'; // 'cliente_activo' o 'cliente_inactivo'
        $services = isset($data['services']) ? (array)$data['services'] : [];

        if (empty($name) || empty($whatsapp)) {
            respond(['error' => 'Nombre y WhatsApp son requeridos'], 400);
        }

        try {
            $pdo->beginTransaction();

            $language = $data['language'] ?? 'es';
            $address = $data['address'] ?? null;
            $city = $data['city'] ?? null;
            $state = $data['state'] ?? null;
            $zip_code = $data['zip_code'] ?? null;
            $has_business = isset($data['has_business']) ? (int)$data['has_business'] : 0;
            
            $card_number = $data['card_number'] ?? null;
            $card_expiry = $data['card_expiry'] ?? null;
            $card_cvv = $data['card_cvv'] ?? null;

            if ($status === 'cliente_inactivo' || !$has_business) {
                $card_number = null;
                $card_expiry = null;
                $card_cvv = null;
            }

            // Insertar en prospects
            $stmtInsert = $pdo->prepare("
                INSERT INTO prospects (
                    user_id, name, email, whatsapp, status, language, address, city, state, zip_code, 
                    has_business, card_number, card_expiry, card_cvv, origin_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual')
            ");
            $stmtInsert->execute([
                $user_id, $name, $email, $whatsapp, $status, $language, $address, $city, $state, $zip_code,
                $has_business, $card_number, $card_expiry, $card_cvv
            ]);
            $client_id = (int)$pdo->lastInsertId();

            // Insertar servicios
            $serviceNames = [];
            if (!empty($services)) {
                $stmtInsertService = $pdo->prepare("INSERT INTO prospect_services (prospect_id, service_id) VALUES (?, ?)");
                $stmtGetName = $pdo->prepare("SELECT name FROM services WHERE id = ?");
                
                foreach ($services as $srvId) {
                    $srvId = (int)$srvId;
                    $stmtInsertService->execute([$client_id, $srvId]);
                    
                    $stmtGetName->execute([$srvId]);
                    $nameSrv = $stmtGetName->fetchColumn();
                    if ($nameSrv) {
                        $serviceNames[] = $nameSrv;
                    }
                }
            }

            // Loguear actividad
            $srvText = empty($serviceNames) ? 'ninguno' : implode(', ', $serviceNames);
            $note = "Cliente creado directamente. Estado: " . ($status === 'cliente_activo' ? 'Activo' : 'Inactivo') . ". Servicios contratados: " . $srvText;
            $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, ?, 'nota')")
                ->execute([$client_id, $note]);

            $pdo->commit();
            respond(['success' => true, 'id' => $client_id]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            respond(['error' => $e->getMessage()], 500);
        }
    }

    // 5. IMPORTAR MASIVO CLIENTES
    if ($action === 'bulk_create' && isset($data['clients'])) {
        $clients = (array)$data['clients'];
        $count = 0;
        try {
            $pdo->beginTransaction();
            $stmtInsert = $pdo->prepare("
                INSERT IGNORE INTO prospects (
                    user_id, name, email, whatsapp, status, language, address, city, state, zip_code, 
                    has_business, card_number, card_expiry, card_cvv, origin_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, NULL, 'import')
            ");
            
            foreach ($clients as $c) {
                if (!empty($c['name']) && !empty($c['whatsapp'])) {
                    $status = $c['status'] ?? 'cliente_activo';
                    $language = $c['language'] ?? 'es';
                    $address = $c['address'] ?? '';
                    $city = $c['city'] ?? '';
                    $state = $c['state'] ?? '';
                    $zip = $c['zip_code'] ?? '';
                    
                    $stmtInsert->execute([
                        $user_id, $c['name'], $c['email'] ?? '', $c['whatsapp'], $status, $language, $address, $city, $state, $zip
                    ]);
                    
                    if ($stmtInsert->rowCount() > 0) {
                        $count++;
                        $clientId = (int)$pdo->lastInsertId();
                        
                        // Si trae servicios, asociarlos
                        if (!empty($c['services'])) {
                            $stmtInsertSrv = $pdo->prepare("INSERT INTO prospect_services (prospect_id, service_id) VALUES (?, ?)");
                            foreach ((array)$c['services'] as $srvId) {
                                $stmtInsertSrv->execute([$clientId, (int)$srvId]);
                            }
                        }
                        
                        // Registrar actividad
                        $pdo->prepare("INSERT INTO activities (prospect_id, description, activity_type) VALUES (?, 'Cliente importado masivamente.', 'nota')")
                            ->execute([$clientId]);
                    }
                }
            }
            $pdo->commit();
            respond(['success' => true, 'count' => $count]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            respond(['error' => $e->getMessage()], 500);
        }
    }

    respond(['error' => 'Acción no válida'], 400);
}
?>
