<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade campo photo a la tabla client';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD photo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP COLUMN photo');
    }
}
