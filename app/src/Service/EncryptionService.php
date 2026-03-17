<?php

namespace App\Service;

/**
 * Servicio de cifrado AES-256-GCM para datos personales de clientes.
 *
 * Cumplimiento LOPD-GDD (LO 3/2018) y RGPD (Reglamento UE 2016/679).
 * Cifra los datos personales almacenados en base de datos con cifrado
 * simétrico autenticado que garantiza confidencialidad e integridad.
 *
 * La clave debe configurarse en la variable de entorno APP_ENCRYPTION_KEY
 * como un valor base64 de exactamente 32 bytes (256 bits).
 */
class EncryptionService
{
    private const CIPHER     = 'aes-256-gcm';
    private const IV_LENGTH  = 12;   // 96 bits – recomendado para GCM
    private const TAG_LENGTH = 16;   // 128 bits de tag de autenticación

    /** Caché estática de la clave decodificada para evitar múltiples llamadas */
    private static ?string $cachedKey = null;

    // ──────────────────────────────────────────────────────────────────────
    // API estática (para uso desde tipos Doctrine custom)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Cifra un valor de texto plano.
     * Devuelve NULL si el valor de entrada es NULL.
     */
    public static function encryptValue(?string $plaintext): ?string
    {
        if ($plaintext === null) {
            return null;
        }

        $key = self::getKey();
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('El cifrado AES-256-GCM ha fallado.');
        }

        // Formato almacenado: base64( IV [12 bytes] + TAG [16 bytes] + ciphertext )
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Descifra un valor almacenado en base de datos.
     *
     * Si el valor no está cifrado (datos anteriores a la migración) devuelve
     * el valor original tal cual para garantizar compatibilidad durante la
     * transición. Ejecutar app:encrypt-existing-data para cifrar datos legados.
     */
    public static function decryptValue(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return $ciphertext;
        }

        // Verificar si es un valor cifrado por nosotros (base64 estricto ≥ 29 bytes decodificados)
        $decoded = base64_decode($ciphertext, strict: true);
        if ($decoded === false || strlen($decoded) < self::IV_LENGTH + self::TAG_LENGTH + 1) {
            // Dato en texto plano (período de transición) – devolver tal cual
            return $ciphertext;
        }

        try {
            $key        = self::getKey();
            $iv         = substr($decoded, 0, self::IV_LENGTH);
            $tag        = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
            $encrypted  = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

            $result = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            // Si openssl devuelve false, el tag de autenticación no es válido
            // → dato corrupto o en texto plano; devolver original
            return $result !== false ? $result : $ciphertext;
        } catch (\Throwable) {
            return $ciphertext;
        }
    }

    /**
     * Comprueba si un valor ya está cifrado en el formato propio.
     */
    public static function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, strict: true);

        return $decoded !== false
            && strlen($decoded) >= self::IV_LENGTH + self::TAG_LENGTH + 1;
    }

    // ──────────────────────────────────────────────────────────────────────
    // API de instancia (inyectable como servicio Symfony)
    // ──────────────────────────────────────────────────────────────────────

    public function encrypt(?string $plaintext): ?string
    {
        return self::encryptValue($plaintext);
    }

    public function decrypt(?string $ciphertext): ?string
    {
        return self::decryptValue($ciphertext);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Gestión de clave
    // ──────────────────────────────────────────────────────────────────────

    private static function getKey(): string
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }

        $keyBase64 = $_ENV['APP_ENCRYPTION_KEY']
            ?? getenv('APP_ENCRYPTION_KEY')
            ?? null;

        if (!$keyBase64) {
            throw new \RuntimeException(
                'La variable de entorno APP_ENCRYPTION_KEY no está configurada. '
                . 'Es obligatoria para el cifrado de datos personales (LOPD/RGPD).'
            );
        }

        $key = base64_decode($keyBase64, strict: true);

        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException(
                'APP_ENCRYPTION_KEY debe ser una clave base64 válida de exactamente 32 bytes (256 bits). '
                . 'Genera una nueva con: php -r "echo base64_encode(random_bytes(32));"'
            );
        }

        self::$cachedKey = $key;

        return self::$cachedKey;
    }
}
