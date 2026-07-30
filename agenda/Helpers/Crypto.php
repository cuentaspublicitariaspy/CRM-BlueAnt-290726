<?php
/**
 * agenda/Helpers/Crypto.php
 * Encriptación simétrica AES-256-GCM para secretos guardados en BD
 * (auth_token de Twilio, password de SMTP). La clave sale de la env var
 * AGENDA_ENCRYPTION_KEY (base64 de 32 bytes), nunca de la base de datos.
 */
class Crypto {

    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private static function getKey(): string {
        $keyEnv = env('AGENDA_ENCRYPTION_KEY', '');
        if (empty($keyEnv)) {
            throw new \RuntimeException('AGENDA_ENCRYPTION_KEY no configurada. Agregala al .env antes de guardar credenciales del módulo Agenda.');
        }
        $key = base64_decode($keyEnv, true);
        if ($key === false || strlen($key) !== 32) {
            // Si no es un base64 de 32 bytes válido, derivar una clave de 32 bytes
            // a partir del valor tal cual (permite usar una passphrase simple).
            $key = hash('sha256', $keyEnv, true);
        }
        return $key;
    }

    public static function encrypt(string $plaintext): string {
        $key = self::getKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new \RuntimeException('No se pudo encriptar el valor.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string {
        $key = self::getKey();
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Valor encriptado inválido.');
        }
        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('No se pudo desencriptar el valor (clave incorrecta o dato corrupto).');
        }
        return $plaintext;
    }
}
