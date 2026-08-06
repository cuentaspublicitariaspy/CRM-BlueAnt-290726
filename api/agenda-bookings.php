<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../agenda/Helpers/Auth.php';
require_once __DIR__ . '/../agenda/Services/AvailabilityService.php';
require_once __DIR__ . '/../agenda/Services/BookingService.php';
require_once __DIR__ . '/../agenda/Services/BookingIntegrations.php';

$session = AgendaAuth::requireSession();
$ownerUserId = AgendaAuth::resolveOwnerUserId($session, $_GET['user_id'] ?? null);
$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) { http_response_code($code); echo json_encode($data); exit; }

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

if ($method === 'GET' && ($_GET['action'] ?? '') === 'availability') {
    $resourceId = (int)($_GET['resource_id'] ?? 0);
    $serviceId = (int)($_GET['service_id'] ?? 0);
    $from = $_GET['from'] ?? date('Y-m-d');
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+14 days'));
    if (!$resourceId || !$serviceId) respond(['error' => 'Parámetros incompletos'], 400);

    $stmt = $pdo->prepare("SELECT id FROM agenda_resources WHERE id = ? AND user_id = ?");
    $stmt->execute([$resourceId, $ownerUserId]);
    if (!$stmt->fetch()) respond(['error' => 'Recurso inválido'], 400);

    $availability = new AvailabilityService($pdo);
    respond(['slots' => $availability->getSlots($resourceId, $serviceId, $from, $to)]);
}

if ($method === 'GET') {
    $where = ['bk.user_id = ?'];
    $params = [$ownerUserId];

    if (!empty($_GET['resource_id'])) { $where[] = 'bk.resource_id = ?'; $params[] = (int)$_GET['resource_id']; }
    if (!empty($_GET['status'])) { $where[] = 'bk.status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['from'])) { $where[] = 'bk.starts_at >= ?'; $params[] = $_GET['from'] . ' 00:00:00'; }
    if (!empty($_GET['to'])) { $where[] = 'bk.starts_at <= ?'; $params[] = $_GET['to'] . ' 23:59:59'; }

    $sql = "
        SELECT bk.*, b.name AS branch_name, r.name AS resource_name, s.name AS service_name
        FROM agenda_bookings bk
        JOIN agenda_branches b ON b.id = bk.branch_id
        JOIN agenda_resources r ON r.id = bk.resource_id
        JOIN agenda_services s ON s.id = bk.service_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY bk.starts_at DESC
        LIMIT 500
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond($stmt->fetchAll());
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) $data = $_POST;
    $action = $data['action'] ?? null;

    $availability = new AvailabilityService($pdo);
    $bookingService = new BookingService($pdo, $availability);

    if ($action === 'cancel' && !empty($data['id'])) {
        try {
            $booking = $bookingService->cancelByOwner($ownerUserId, (int)$data['id']);
            $booking = agendaSyncIntegrations($pdo, $booking, 'cancelled');
            respond(['success' => true, 'booking' => $booking]);
        } catch (AgendaBookingException $e) {
            respond(['error' => $e->getMessage()], $e->errorCode === 'not_found' ? 404 : 400);
        }
    }

    if ($action === 'create_manual') {
        $resourceId = (int)($data['resource_id'] ?? 0);
        $serviceId = (int)($data['service_id'] ?? 0);
        $startsAt = trim($data['starts_at'] ?? '');
        if (!$resourceId || !$serviceId || $startsAt === '') respond(['error' => 'Recurso, servicio y horario son obligatorios'], 400);

        $contact = [
            'name' => $data['contact_name'] ?? ($data['contact']['name'] ?? ''),
            'phone' => $data['contact_phone'] ?? ($data['contact']['phone'] ?? ''),
            'email' => $data['contact_email'] ?? ($data['contact']['email'] ?? ''),
        ];
        $contactId = !empty($data['contact_id']) ? (int)$data['contact_id'] : null;
        $notes = $data['notes'] ?? null;
        $externalAgentId = !empty($data['external_agent_id']) ? (int)$data['external_agent_id'] : null;

        try {
            $booking = $bookingService->createManual($ownerUserId, $resourceId, $serviceId, $startsAt, $contact, $contactId, $notes, $externalAgentId);
            $booking = agendaSyncIntegrations($pdo, $booking, 'confirmed');
            notifyBookingEvent($pdo, $booking, 'confirmed');
            respond(['success' => true, 'booking' => $booking]);
        } catch (AgendaBookingException $e) {
            $code = $e->errorCode === 'slot_taken' ? 409 : ($e->errorCode === 'not_found' ? 404 : 400);
            respond(['error' => $e->getMessage()], $code);
        }
    }

    respond(['error' => 'Acción no soportada'], 400);
}

respond(['error' => 'Método no soportado'], 405);
