<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Services/AvailabilityService.php';
require_once __DIR__ . '/../agenda/Services/BookingService.php';
require_once __DIR__ . '/../agenda/Services/BookingIntegrations.php';

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

/**
 * Rate limiting propio sobre la tabla `rate_limits` ya existente en el
 * proyecto. No se reusa agentes/Services/RateLimiter.php porque esa clase
 * concatena su $key con el hash de IP dentro de la columna `endpoint`
 * (VARCHAR(50)), lo que desborda la columna con cualquier key no trivial —
 * es un bug pre-existente ajeno a este módulo, no se toca acá.
 */
function agendaRateLimitCheck(PDO $pdo, string $endpoint, int $maxRequests, int $windowSeconds): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $ipHash = hash('sha256', $ip . '|' . $fwd);

    $pdo->prepare("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)")->execute([$windowSeconds]);

    $stmt = $pdo->prepare("SELECT id, request_count FROM rate_limits WHERE ip_hash = ? AND endpoint = ? ORDER BY window_start DESC LIMIT 1");
    $stmt->execute([$ipHash, $endpoint]);
    $row = $stmt->fetch();

    if ($row) {
        if ((int)$row['request_count'] >= $maxRequests) {
            throw new \RuntimeException('Límite de solicitudes alcanzado. Probá de nuevo en unos minutos.');
        }
        $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?")->execute([$row['id']]);
    } else {
        $pdo->prepare("INSERT INTO rate_limits (ip_hash, endpoint) VALUES (?, ?)")->execute([$ipHash, $endpoint]);
    }
}

function bookingErrorCode(string $errorCode): int {
    return match ($errorCode) {
        'not_found', 'disabled' => 404,
        'slot_taken', 'expired', 'invalid_state' => 409,
        'too_soon', 'invalid' => 400,
        default => 500,
    };
}

function notifyBookingEvent(PDO $pdo, array $booking, string $eventType): void {
    try {
        $file = __DIR__ . '/../agenda/Services/NotificationService.php';
        if (file_exists($file)) {
            require_once $file;
            (new AgendaNotificationService($pdo))->notifyBookingEvent($booking, $eventType);
        }
    } catch (\Throwable $e) {
        error_log('Agenda notify error: ' . $e->getMessage());
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Lectura pública: más permisiva. Escritura pública: más agresiva.
$readActions = ['link-config', 'availability', 'detail', 'reschedule-availability'];
try {
    if (in_array($action, $readActions, true)) {
        agendaRateLimitCheck($pdo, 'agenda_public_read', 90, 60);
    } else {
        agendaRateLimitCheck($pdo, 'agenda_public_write', 15, 60);
    }
} catch (\RuntimeException $e) {
    respond(['error' => $e->getMessage()], 429);
}

function loadActiveLink(PDO $pdo, string $token): array {
    $stmt = $pdo->prepare("SELECT * FROM agenda_booking_links WHERE token = ?");
    $stmt->execute([$token]);
    $link = $stmt->fetch();
    if (!$link) respond(['error' => 'Enlace no encontrado'], 404);
    if ($link['status'] === 'revoked') respond(['error' => 'Este enlace fue revocado'], 404);
    if ($link['expires_at'] && strtotime($link['expires_at']) < time()) respond(['error' => 'Este enlace venció'], 404);

    $settingsStmt = $pdo->prepare("SELECT enabled FROM agenda_settings WHERE user_id = ?");
    $settingsStmt->execute([$link['user_id']]);
    $enabled = $settingsStmt->fetchColumn();
    if ($enabled !== false && !(int)$enabled) respond(['error' => 'La agenda no está disponible'], 404);

    return $link;
}

if ($action === 'link-config') {
    $token = $_GET['token'] ?? '';
    if ($token === '') respond(['error' => 'Token requerido'], 400);
    $link = loadActiveLink($pdo, $token);
    $ownerUserId = (int)$link['user_id'];

    $branches = $pdo->prepare("SELECT id, name, city, timezone FROM agenda_branches WHERE user_id = ? AND active = 1" . ($link['branch_id'] ? " AND id = ?" : "") . " ORDER BY name");
    $branches->execute($link['branch_id'] ? [$ownerUserId, $link['branch_id']] : [$ownerUserId]);

    $resourcesSql = "
        SELECT r.id, r.name, r.description, r.branch_id, r.color, r.photo,
               (SELECT GROUP_CONCAT(service_id) FROM agenda_resource_services WHERE resource_id = r.id) AS service_ids_raw
        FROM agenda_resources r
        WHERE r.user_id = ? AND r.active = 1
    ";
    $params = [$ownerUserId];
    if ($link['resource_id']) { $resourcesSql .= " AND r.id = ?"; $params[] = $link['resource_id']; }
    if ($link['branch_id']) { $resourcesSql .= " AND r.branch_id = ?"; $params[] = $link['branch_id']; }
    $resourcesStmt = $pdo->prepare($resourcesSql);
    $resourcesStmt->execute($params);
    $resources = $resourcesStmt->fetchAll();
    foreach ($resources as &$r) {
        $r['service_ids'] = $r['service_ids_raw'] ? array_map('intval', explode(',', $r['service_ids_raw'])) : [];
        unset($r['service_ids_raw']);
        $r['photo_url'] = $r['photo'] ? CRM_URL . '/uploads/' . basename($r['photo']) : null;
        unset($r['photo']);
    }
    if ($link['service_id']) {
        $resources = array_values(array_filter($resources, fn($r) => in_array((int)$link['service_id'], $r['service_ids'], true)));
    }

    $servicesSql = "SELECT id, name, duration_min, price, currency FROM agenda_services WHERE user_id = ? AND active = 1" . ($link['service_id'] ? " AND id = ?" : "");
    $servicesStmt = $pdo->prepare($servicesSql);
    $servicesStmt->execute($link['service_id'] ? [$ownerUserId, $link['service_id']] : [$ownerUserId]);

    respond([
        'branches' => $branches->fetchAll(),
        'resources' => $resources,
        'services' => $servicesStmt->fetchAll(),
        'preselected' => [
            'branch_id' => $link['branch_id'] ? (int)$link['branch_id'] : null,
            'resource_id' => $link['resource_id'] ? (int)$link['resource_id'] : null,
            'service_id' => $link['service_id'] ? (int)$link['service_id'] : null,
        ],
    ]);
}

if ($action === 'availability') {
    $token = $_GET['token'] ?? '';
    $resourceId = (int)($_GET['resource_id'] ?? 0);
    $serviceId = (int)($_GET['service_id'] ?? 0);
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+14 days'));
    if ($token === '' || !$resourceId || !$serviceId) respond(['error' => 'Parámetros incompletos'], 400);

    $link = loadActiveLink($pdo, $token);
    $stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $link['user_id']]);
    if (!$stmt->fetch()) respond(['error' => 'Recurso inválido'], 400);

    $availability = new AvailabilityService($pdo);
    respond(['slots' => $availability->getSlots($resourceId, $serviceId, $from, $to)]);
}

if ($action === 'hold' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = $data['token'] ?? '';
    $resourceId = (int)($data['resource_id'] ?? 0);
    $serviceId = (int)($data['service_id'] ?? 0);
    $startsAt = trim($data['starts_at'] ?? '');
    if ($token === '' || !$resourceId || !$serviceId || $startsAt === '') respond(['error' => 'Parámetros incompletos'], 400);

    $link = loadActiveLink($pdo, $token);
    $stmt = $pdo->prepare("SELECT rs.resource_id FROM agenda_resource_services rs JOIN agenda_resources r ON r.id = rs.resource_id WHERE rs.resource_id = ? AND rs.service_id = ? AND r.user_id = ?");
    $stmt->execute([$resourceId, $serviceId, $link['user_id']]);
    if (!$stmt->fetch()) respond(['error' => 'El recurso no ofrece ese servicio'], 400);

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);
    try {
        $booking = $bookingService->createHold($resourceId, $serviceId, $startsAt, 'client');
        if ($link['status'] === 'active') {
            $pdo->prepare("UPDATE agenda_booking_links SET status = 'used' WHERE id = ?")->execute([$link['id']]);
        }
        respond(['success' => true, 'booking' => $booking]);
    } catch (AgendaBookingException $e) {
        respond(['error' => $e->getMessage(), 'code' => $e->errorCode], bookingErrorCode($e->errorCode));
    }
}

if ($action === 'confirm' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $manageToken = $data['manage_token'] ?? '';
    if ($manageToken === '') respond(['error' => 'Token de gestión requerido'], 400);

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);
    try {
        $booking = $bookingService->confirmByManageToken($manageToken, [
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
        ]);
        $booking = agendaSyncIntegrations($pdo, $booking, 'confirmed');
        notifyBookingEvent($pdo, $booking, 'confirmed');
        respond(['success' => true, 'booking' => $booking]);
    } catch (AgendaBookingException $e) {
        respond(['error' => $e->getMessage(), 'code' => $e->errorCode], bookingErrorCode($e->errorCode));
    }
}

if ($action === 'detail') {
    $manageToken = $_GET['manage_token'] ?? '';
    if ($manageToken === '') respond(['error' => 'Token de gestión requerido'], 400);

    $stmt = $pdo->prepare("
        SELECT bk.*, br.name AS branch_name, br.timezone, r.name AS resource_name, s.name AS service_name, s.duration_min
        FROM agenda_bookings bk
        JOIN agenda_branches br ON br.id = bk.branch_id
        JOIN agenda_resources r ON r.id = bk.resource_id
        JOIN agenda_services s ON s.id = bk.service_id
        WHERE bk.manage_token = ?
    ");
    $stmt->execute([$manageToken]);
    $booking = $stmt->fetch();
    if (!$booking) respond(['error' => 'Reserva no encontrada'], 404);
    respond($booking);
}

if ($action === 'reschedule-availability') {
    $manageToken = $_GET['manage_token'] ?? '';
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+14 days'));
    if ($manageToken === '') respond(['error' => 'Token de gestión requerido'], 400);

    $stmt = $pdo->prepare("SELECT resource_id, service_id FROM agenda_bookings WHERE manage_token = ?");
    $stmt->execute([$manageToken]);
    $booking = $stmt->fetch();
    if (!$booking) respond(['error' => 'Reserva no encontrada'], 404);

    $availability = new AvailabilityService($pdo);
    respond(['slots' => $availability->getSlots((int)$booking['resource_id'], (int)$booking['service_id'], $from, $to)]);
}

if ($action === 'reschedule' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $manageToken = $data['manage_token'] ?? '';
    $startsAt = trim($data['starts_at'] ?? '');
    if ($manageToken === '' || $startsAt === '') respond(['error' => 'Parámetros incompletos'], 400);

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);
    try {
        $booking = $bookingService->rescheduleByManageToken($manageToken, $startsAt);
        $booking = agendaSyncIntegrations($pdo, $booking, 'rescheduled');
        notifyBookingEvent($pdo, $booking, 'rescheduled');
        respond(['success' => true, 'booking' => $booking]);
    } catch (AgendaBookingException $e) {
        respond(['error' => $e->getMessage(), 'code' => $e->errorCode], bookingErrorCode($e->errorCode));
    }
}

if ($action === 'cancel' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $manageToken = $data['manage_token'] ?? '';
    if ($manageToken === '') respond(['error' => 'Token de gestión requerido'], 400);

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);
    try {
        $booking = $bookingService->cancelByManageToken($manageToken, $data['reason'] ?? null, 'client');
        $booking = agendaSyncIntegrations($pdo, $booking, 'cancelled');
        notifyBookingEvent($pdo, $booking, 'cancelled');
        respond(['success' => true, 'booking' => $booking]);
    } catch (AgendaBookingException $e) {
        respond(['error' => $e->getMessage(), 'code' => $e->errorCode], bookingErrorCode($e->errorCode));
    }
}

if ($action === 'confirm-attendance' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $manageToken = $data['manage_token'] ?? '';
    if ($manageToken === '') respond(['error' => 'Token de gestión requerido'], 400);

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);
    try {
        $booking = $bookingService->confirmAttendanceByManageToken($manageToken);
        respond(['success' => true, 'booking' => $booking]);
    } catch (AgendaBookingException $e) {
        respond(['error' => $e->getMessage(), 'code' => $e->errorCode], bookingErrorCode($e->errorCode));
    }
}

respond(['error' => 'Acción no soportada'], 400);
