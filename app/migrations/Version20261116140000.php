<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir columna welcome_message a la tabla client';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD welcome_message LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP welcome_message');
    }
}
