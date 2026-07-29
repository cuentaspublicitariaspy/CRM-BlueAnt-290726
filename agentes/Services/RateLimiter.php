<?php
class AgentRateLimiter
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function getIpHash(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        return hash('sha256', $ip . '|' . $fwd);
    }

    public function check(string $key, int $maxRequests, int $windowSeconds, string $endpoint = 'generic'): void
    {
        $ipHash = self::getIpHash();
        $storageKey = $key . ':' . $ipHash;

        // Clean old
        $this->db->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL $windowSeconds SECOND)");

        $stmt = $this->db->prepare(
            "SELECT request_count FROM rate_limits WHERE ip_hash = ? AND endpoint = ? ORDER BY window_start DESC LIMIT 1"
        );
        $stmt->execute([$ipHash, $storageKey]);
        $row = $stmt->fetch();

        if ($row) {
            $count = (int)$row['request_count'];
            if ($count >= $maxRequests) {
                throw new RuntimeException("Limite de uso alcanzado. Intenta de nuevo en unos minutos.");
            }
            $this->db->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip_hash = ? AND endpoint = ?")
                     ->execute([$ipHash, $storageKey]);
        } else {
            $this->db->prepare("INSERT INTO rate_limits (ip_hash, endpoint) VALUES (?, ?)")
                     ->execute([$ipHash, $storageKey]);
        }
    }
}
