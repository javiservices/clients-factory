<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change zip_code and company_phone columns from INT to VARCHAR in user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY COLUMN zip_code VARCHAR(10) NULL');
        $this->addSql('ALTER TABLE user MODIFY COLUMN company_phone VARCHAR(20) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user MODIFY COLUMN zip_code INT NULL');
        $this->addSql('ALTER TABLE user MODIFY COLUMN company_phone INT NULL');
    }
}
