<?php

namespace App\Doctrine\Type;

use App\Service\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Tipo Doctrine personalizado para campos de texto cifrados en base de datos.
 *
 * Utiliza AES-256-GCM (EncryptionService) para cifrar de forma transparente
 * los datos personales de los clientes en cumplimiento con la LOPD-GDD
 * (LO 3/2018) y el RGPD (Reglamento UE 2016/679).
 *
 * Los valores se almacenan en la BD como TEXT (base64 del bloque cifrado).
 * En el lado PHP la entidad siempre trabaja con el texto plano original.
 */
class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // TEXT cubre hasta 65 535 bytes, suficiente para cualquier campo cifrado
        return 'TEXT';
    }

    /**
     * PHP → Base de datos: cifrar el valor antes de persistirlo.
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Evitar doble cifrado si ya está cifrado
        if (EncryptionService::isEncrypted((string) $value)) {
            return $value;
        }

        return EncryptionService::encryptValue((string) $value);
    }

    /**
     * Base de datos → PHP: descifrar el valor al leerlo.
     *
     * Durante el período de transición los valores en texto plano se devuelven
     * tal cual (compatibilidad hacia atrás). Ejecutar app:encrypt-existing-data
     * para completar la migración.
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return EncryptionService::decryptValue((string) $value);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
