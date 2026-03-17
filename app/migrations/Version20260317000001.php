<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migración de cifrado de datos personales – LOPD-GDD (LO 3/2018) / RGPD (UE 2016/679)
 *
 * Amplía los campos de datos personales de la tabla `client` a TEXT para
 * almacenar los valores cifrados con AES-256-GCM.
 *
 * ⚠️  DESPUÉS de ejecutar esta migración es OBLIGATORIO correr:
 *       php bin/console app:encrypt-existing-data
 *    para cifrar los datos ya existentes en la base de datos.
 */
final class Version20260317000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LOPD/RGPD: convierte campos de datos personales del cliente a TEXT cifrado (AES-256-GCM)';
    }

    public function up(Schema $schema): void
    {
        // Nombre y apellidos
        $this->addSql('ALTER TABLE client MODIFY COLUMN name TEXT NOT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN surname TEXT NOT NULL');

        // Dirección
        $this->addSql('ALTER TABLE client MODIFY COLUMN address TEXT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN locality TEXT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN zip_code TEXT NULL');

        // Contacto
        $this->addSql('ALTER TABLE client MODIFY COLUMN phone TEXT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN email TEXT NULL');

        // Identificación (DNI/NIF – especialmente protegido por LOPD)
        $this->addSql('ALTER TABLE client MODIFY COLUMN id_number TEXT NULL');

        // Fecha de nacimiento: de DATE a TEXT para almacenar valor cifrado
        $this->addSql('ALTER TABLE client MODIFY COLUMN birth_date TEXT NULL COMMENT \'(DC2Type:encrypted_date)\'');

        // Código de tarjeta de acceso
        $this->addSql('ALTER TABLE client MODIFY COLUMN card_code TEXT NULL');

        $this->write('');
        $this->write('<comment>⚠️  Recuerda cifrar los datos existentes ejecutando:</comment>');
        $this->write('<info>    php bin/console app:encrypt-existing-data</info>');
        $this->write('');
    }

    public function down(Schema $schema): void
    {
        // IMPORTANTE: el down solo revierte tipos; los datos cifrados quedan ilegibles
        // tras revertir. Hacer backup antes de ejecutar el down.
        $this->addSql('ALTER TABLE client MODIFY COLUMN name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN surname VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN address VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN locality VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN zip_code VARCHAR(10) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN phone VARCHAR(20) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN email VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN id_number VARCHAR(15) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN birth_date DATE NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN card_code VARCHAR(255) NULL');
    }
}
