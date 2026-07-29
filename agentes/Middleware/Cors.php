<?php
class AgentCors
{
    public static function handlePreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
            http_response_code(204);
            exit;
        }
    }

    public static function validatePublicEndpoint(string $agentId, PDO $db): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = parse_url($origin, PHP_URL_HOST) ?? '';

        if ($host === '') {
            // No origin = direct API call, allow if local dev
            if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
                return;
            }
            // Allow if no origin (server-side calls)
            return;
        }

        // Check whitelist
        $stmt = $db->prepare("SELECT COUNT(*) FROM agent_domains WHERE agent_id = ? AND domain = ?");
        $stmt->execute([$agentId, $host]);
        if ((int)$stmt->fetchColumn() > 0) return;

        // Check subdomain match
        $stmt = $db->prepare("SELECT domain FROM agent_domains WHERE agent_id = ?");
        $stmt->execute([$agentId]);
        $domains = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($domains as $allowed) {
            if (str_ends_with($host, '.' . $allowed)) return;
        }

        throw new RuntimeException('Dominio no autorizado para este agente', 403);
    }

    public static function applyAdminCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (str_contains($origin, $_SERVER['HTTP_HOST'] ?? '')) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}
