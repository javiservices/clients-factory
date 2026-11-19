<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade la columna daily_access_limit a la tabla client (por defecto 1).
 */
final class Version20261119020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir columna daily_access_limit a client con valor por defecto 1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD daily_access_limit INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE client SET daily_access_limit = 1 WHERE daily_access_limit IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP daily_access_limit');
    }
}
