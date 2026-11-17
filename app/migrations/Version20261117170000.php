<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade la columna client_number a la tabla client para desacoplar el ID de cliente de gestión
 * del ID autoincremental técnico de la base de datos, y migra todos los registros existentes.
 */
final class Version20261117170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir columna client_number con valor autoincremental desacoplado y actualizar registros existentes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD client_number INT DEFAULT NULL');
        $this->addSql('UPDATE client SET client_number = id WHERE client_number IS NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLIENT_NUMBER ON client (client_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CLIENT_NUMBER ON client');
        $this->addSql('ALTER TABLE client DROP client_number');
    }
}
