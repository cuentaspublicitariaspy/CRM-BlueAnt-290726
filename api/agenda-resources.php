<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';

$session = AgendaAuth::requireSession();
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT r.*, b.name AS branch_name,
               (SELECT GROUP_CONCAT(service_id) FROM agenda_resource_services WHERE resource_id = r.id) AS service_ids_raw,
               (SELECT COUNT(*) FROM agenda_schedules WHERE resource_id = r.id) AS schedule_count
        FROM agenda_resources r
        JOIN agenda_branches b ON b.id = r.branch_id
        WHERE r.user_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$ownerUserId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['service_ids'] = $row['service_ids_raw'] ? array_map('intval', explode(',', $row['service_ids_raw'])) : [];
        unset($row['service_ids_raw']);
        $row['photo_url'] = $row['photo'] ? CRM_URL . '/uploads/' . basename($row['photo']) : null;
    }
    respond($rows);
}

if ($method === 'POST') {
    // Crear/editar/borrar recursos es configuración: solo admin. La lectura
    // (GET arriba) sigue abierta porque el subscriber la necesita para armar
    // el modal de "Nueva reserva" con lo que el admin ya dejó configurado.
    AgendaAuth::requireAdmin($session);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    if ($action === 'delete' && !empty($data['id'])) {
        $pdo->prepare("DELETE FROM agenda_resources WHERE id = ? AND user_id = ?")->execute([(int)$data['id'], $ownerUserId]);
        respond(['success' => true]);
    }

    if (!empty($data['id'])) {
        $resourceId = (int)$data['id'];
        // Merge parcial: cualquier campo no enviado explícitamente conserva
        // su valor actual — el panel guarda "datos básicos", "buffers" y
        // "servicios" por separado, y ninguno de esos guardados debe pisar
        // los campos de los otros.
        $stmt = $pdo->prepare("SELECT branch_id, name, description, capacity, color, photo, active, buffer_before_min, buffer_after_min FROM agenda_resources WHERE id = ? AND user_id = ?");
        $stmt->execute([$resourceId, $ownerUserId]);
        $current = $stmt->fetch();
        if (!$current) respond(['error' => 'Recurso no encontrado'], 404);

        $branchId = array_key_exists('branch_id', $data) ? (int)$data['branch_id'] : (int)$current['branch_id'];
        $name = array_key_exists('name', $data) ? trim($data['name']) : $current['name'];
        if ($name === '') respond(['error' => 'El nombre es obligatorio'], 400);
        $description = array_key_exists('description', $data) ? $data['description'] : $current['description'];
        $capacity = array_key_exists('capacity', $data) ? max(1, (int)$data['capacity']) : (int)$current['capacity'];
        $color = array_key_exists('color', $data) ? $data['color'] : $current['color'];
        $active = array_key_exists('active', $data) ? (int)!!$data['active'] : (int)$current['active'];
        $bufferBefore = array_key_exists('buffer_before_min', $data) ? max(0, (int)$data['buffer_before_min']) : (int)$current['buffer_before_min'];
        $bufferAfter = array_key_exists('buffer_after_min', $data) ? max(0, (int)$data['buffer_after_min']) : (int)$current['buffer_after_min'];

        // Foto: solo se toca si vino un archivo nuevo en este request (subida
        // dedicada desde el detalle del recurso) — el resto de los guardados
        // parciales (datos básicos, buffers, servicios) no la tocan.
        $photo = $current['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) respond(['error' => 'Formato de imagen no permitido'], 400);
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fn = 'resource_' . $resourceId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fn)) respond(['error' => 'No se pudo guardar la foto'], 500);
            $photo = $fn;
        }

        if ($branchId !== (int)$current['branch_id']) {
            $stmtB = $pdo->prepare("SELECT id FROM agenda_branches WHERE id = ? AND user_id = ?");
            $stmtB->execute([$branchId, $ownerUserId]);
            if (!$stmtB->fetch()) respond(['error' => 'Sucursal inválida'], 400);
        }

        $pdo->prepare("UPDATE agenda_resources SET branch_id=?, name=?, description=?, capacity=?, color=?, photo=?, active=?, buffer_before_min=?, buffer_after_min=? WHERE id=? AND user_id=?")
            ->execute([$branchId, $name, $description, $capacity, $color, $photo, $active, $bufferBefore, $bufferAfter, $resourceId, $ownerUserId]);
    } else {
        $name = trim($data['name'] ?? '');
        $branchId = (int)($data['branch_id'] ?? 0);
        if ($name === '' || !$branchId) respond(['error' => 'Nombre y sucursal son obligatorios'], 400);

        $stmtB = $pdo->prepare("SELECT id FROM agenda_branches WHERE id = ? AND user_id = ?");
        $stmtB->execute([$branchId, $ownerUserId]);
        if (!$stmtB->fetch()) respond(['error' => 'Sucursal inválida'], 400);

        $description = $data['description'] ?? null;
        $capacity = max(1, (int)($data['capacity'] ?? 1));
        $color = $data['color'] ?? null;
        $active = isset($data['active']) ? (int)!!$data['active'] : 1;
        $bufferBefore = max(0, (int)($data['buffer_before_min'] ?? 0));
        $bufferAfter = max(0, (int)($data['buffer_after_min'] ?? 0));

        $stmt = $pdo->prepare("INSERT INTO agenda_resources (user_id, branch_id, name, description, capacity, color, active, buffer_before_min, buffer_after_min) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ownerUserId, $branchId, $name, $description, $capacity, $color, $active, $bufferBefore, $bufferAfter]);
        $resourceId = (int)$pdo->lastInsertId();
    }

    // Reasignar servicios ofrecidos solo si vino la clave (permite guardar
    // disponibilidad/buffers sin pisar la lista de servicios ya asignada).
    if (array_key_exists('service_ids', $data)) {
        $serviceIds = array_map('intval', (array)$data['service_ids']);
        $pdo->prepare("DELETE FROM agenda_resource_services WHERE resource_id = ?")->execute([$resourceId]);
        if (!empty($serviceIds)) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $stmtS = $pdo->prepare("SELECT id FROM agenda_services WHERE user_id = ? AND id IN ($placeholders)");
            $stmtS->execute(array_merge([$ownerUserId], $serviceIds));
            $validIds = $stmtS->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare("INSERT IGNORE INTO agenda_resource_services (resource_id, service_id) VALUES (?, ?)");
            foreach ($validIds as $sid) $ins->execute([$resourceId, $sid]);
        }
    }

    $photoUrl = isset($photo) && $photo ? CRM_URL . '/uploads/' . basename($photo) : null;
    respond(['success' => true, 'id' => $resourceId, 'photo_url' => $photoUrl]);
}

respond(['error' => 'Método no soportado'], 405);
