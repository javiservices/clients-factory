<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Permite suscripciones ilimitadas (sin fecha de fin) haciendo nullable
 * subscription.end_date y billing_record.period_end.
 */
final class Version20261119093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permitir valores NULL en subscription.end_date y billing_record.period_end para suscripciones ilimitadas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription MODIFY end_date DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE billing_record MODIFY period_end DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription MODIFY end_date DATETIME NOT NULL');
        $this->addSql('ALTER TABLE billing_record MODIFY period_end DATE NOT NULL');
    }
}
