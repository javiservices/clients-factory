<?php

namespace App\Doctrine\Type;

use App\Service\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Tipo Doctrine personalizado para campos de fecha cifrados en base de datos.
 *
 * Almacena la fecha como una cadena cifrada (formato Y-m-d) en un campo TEXT.
 * En el lado PHP la entidad trabaja con \DateTimeInterface de forma transparente.
 *
 * Protege datos de fecha de nacimiento en cumplimiento con la LOPD-GDD
 * (LO 3/2018) y el RGPD (Reglamento UE 2016/679).
 */
class EncryptedDateType extends Type
{
    public const NAME        = 'encrypted_date';
    private const DATE_FORMAT = 'Y-m-d';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT';
    }

    /**
     * PHP → Base de datos: formatear la fecha y cifrarla.
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $dateString = $value->format(self::DATE_FORMAT);

            // Evitar doble cifrado
            if (EncryptionService::isEncrypted($dateString)) {
                return $dateString;
            }

            return EncryptionService::encryptValue($dateString);
        }

        // Si es string y ya está cifrado, devolver tal cual
        if (is_string($value) && EncryptionService::isEncrypted($value)) {
            return $value;
        }

        // Cadena en texto plano → cifrar
        return EncryptionService::encryptValue((string) $value);
    }

    /**
     * Base de datos → PHP: descifrar y convertir a \DateTime.
     *
     * Compatibilidad con datos en texto plano (fechas MySQL sin cifrar)
     * durante el período de transición.
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Descifrar (devuelve el valor original si no está cifrado)
        $dateString = EncryptionService::decryptValue((string) $value);

        if ($dateString === null || $dateString === '') {
            return null;
        }

        try {
            return new \DateTime($dateString);
        } catch (\Throwable) {
            return null;
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
