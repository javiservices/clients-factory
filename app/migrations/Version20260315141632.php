<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315141632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change phone and zip_code columns from INT to VARCHAR in client table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY COLUMN phone VARCHAR(20) NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN zip_code VARCHAR(10) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client MODIFY COLUMN phone INT NULL');
        $this->addSql('ALTER TABLE client MODIFY COLUMN zip_code INT NULL');
    }
}
