<?php
/**
 * agenda/Helpers/OAuthState.php
 * Estado firmado (HMAC-SHA256) para flujos OAuth (por ahora Google Calendar,
 * reusable para cualquier otro proveedor a futuro) — evita que alguien arme
 * un callback falso o reuse uno viejo. Reusa AGENDA_ENCRYPTION_KEY (no hace
 * falta otro secreto en .env); acá solo firma, no encripta: el payload va
 * en claro dentro del state, solo hace falta integridad + expiración, no
 * confidencialidad (no lleva ningún secreto, solo IDs).
 */
class OAuthState {

    private static function secret(): string {
        $key = env('AGENDA_ENCRYPTION_KEY', '');
        if (empty($key)) {
            throw new \RuntimeException('AGENDA_ENCRYPTION_KEY no configurada.');
        }
        return $key;
    }

    private static function b64url(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64url(string $encoded): string {
        return base64_decode(strtr($encoded, '-_', '+/'));
    }

    public static function encode(array $payload, int $ttlSeconds = 600): string {
        $payload['exp'] = time() + $ttlSeconds;
        $b64 = self::b64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $sig = self::b64url(hash_hmac('sha256', $b64, self::secret(), true));
        return $b64 . '.' . $sig;
    }

    /** @throws \RuntimeException si el estado es inválido, adulterado o venció. */
    public static function decode(string $state): array {
        $parts = explode('.', $state, 2);
        if (count($parts) !== 2) throw new \RuntimeException('Estado inválido.');
        [$b64, $sig] = $parts;
        $expectedSig = self::b64url(hash_hmac('sha256', $b64, self::secret(), true));
        if (!hash_equals($expectedSig, $sig)) throw new \RuntimeException('Estado inválido o adulterado.');

        $payload = json_decode(self::unb64url($b64), true);
        if (!is_array($payload)) throw new \RuntimeException('Estado inválido.');
        if (($payload['exp'] ?? 0) < time()) throw new \RuntimeException('El enlace de conexión venció, iniciá el proceso de nuevo.');
        return $payload;
    }
}
