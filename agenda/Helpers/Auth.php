<?php
/**
 * agenda/Helpers/Auth.php
 * Centraliza el patrón de sesión + permisos admin/subscriber que en el
 * resto del CRM está duplicado en cada api/*.php (ver api/prospects.php,
 * api/clients.php).
 *
 * A diferencia de prospects/services (donde cada cuenta tiene su propio
 * negocio vía user_id), este CRM es de UN solo negocio: admin y subscriber
 * comparten la MISMA sucursal/recursos/servicios/reservas. El rol solo
 * distingue permisos (admin configura, subscriber solo agenda), no de
 * quién es "dueño" de los datos — por eso todos resuelven al mismo
 * BUSINESS_OWNER_USER_ID en vez del user_id de la sesión activa.
 */
class AgendaAuth {

    /** Cuenta bajo la cual viven todos los datos del negocio (sucursal,
     * recursos, servicios, reservas). Este CRM es de un único negocio (Blue
     * Ant Wealth) — no hay selector de negocio en la UI. El user_id real
     * difiere entre entornos (dev local vs producción tienen tablas users
     * distintas), por eso se resuelve desde AGENDA_BUSINESS_OWNER_USER_ID en
     * .env en vez de quedar fijo en el código; 2 es el fallback de dev local. */
    const BUSINESS_OWNER_USER_ID_FALLBACK = 2;

    private static function businessOwnerUserId(): int {
        $value = getenv('AGENDA_BUSINESS_OWNER_USER_ID');
        return $value !== false && $value !== '' ? (int)$value : self::BUSINESS_OWNER_USER_ID_FALLBACK;
    }

    /**
     * Exige sesión activa. Corta el request con 401 si no hay sesión.
     * Devuelve ['user_id' => int, 'is_admin' => bool].
     */
    public static function requireSession(): array {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        return [
            'user_id'  => (int)$_SESSION['user_id'],
            'is_admin' => ($_SESSION['user_role'] ?? '') === 'admin',
        ];
    }

    /**
     * Resuelve sobre qué negocio (user_id) opera el request. Un solo
     * negocio compartido por todas las cuentas — admin y subscriber
     * siempre ven y operan sobre los mismos datos, el rol solo determina
     * si pueden mutar configuración o no (ver requireAdmin()).
     */
    public static function resolveOwnerUserId(array $session, $requestedUserId = null): int {
        return self::businessOwnerUserId();
    }

    /**
     * Corta el request con 403 si la sesión actual no es admin.
     */
    public static function requireAdmin(array $session): void {
        if (!$session['is_admin']) {
            http_response_code(403);
            echo json_encode(['error' => 'Solo administradores']);
            exit;
        }
    }
}
