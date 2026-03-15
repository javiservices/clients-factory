<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade campo frozen_at a la tabla subscription';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD frozen_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN frozen_at');
    }
}
