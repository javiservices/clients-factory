<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251026195339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE billing_record (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, subscription_id INT NOT NULL, invoice_id INT DEFAULT NULL, billing_date DATETIME NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, amount NUMERIC(10, 2) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_billed TINYINT(1) NOT NULL, INDEX IDX_DEEEBCC119EB6921 (client_id), INDEX IDX_DEEEBCC19A1887DC (subscription_id), INDEX IDX_DEEEBCC12989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE billing_record ADD CONSTRAINT FK_DEEEBCC119EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE billing_record ADD CONSTRAINT FK_DEEEBCC19A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id)');
        $this->addSql('ALTER TABLE billing_record ADD CONSTRAINT FK_DEEEBCC12989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE invoice_line DROP FOREIGN KEY FK_D3D1D6932989F1FD');
        $this->addSql('DROP TABLE invoice_line');
        $this->addSql('ALTER TABLE company DROP legal_notice, DROP footer_text, DROP created_at, DROP updated_at, CHANGE city city VARCHAR(255) DEFAULT NULL, CHANGE country country VARCHAR(255) DEFAULT NULL, CHANGE website province VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD total_amount NUMERIC(10, 2) NOT NULL, ADD net_amount NUMERIC(10, 2) NOT NULL, ADD description VARCHAR(255) DEFAULT NULL, ADD is_electronic TINYINT(1) NOT NULL, DROP subtotal, DROP total, DROP currency, DROP status, DROP created_at, DROP updated_at, CHANGE issue_date issue_date DATETIME NOT NULL, CHANGE due_date due_date DATETIME DEFAULT NULL, CHANGE tax_rate tax_rate VARCHAR(10) NOT NULL, CHANGE notes xml_content LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice_line (id INT AUTO_INCREMENT NOT NULL, invoice_id INT NOT NULL, description VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, line_total NUMERIC(10, 2) NOT NULL, tax_rate NUMERIC(5, 2) DEFAULT NULL, period_start DATETIME DEFAULT NULL, period_end DATETIME DEFAULT NULL, INDEX IDX_D3D1D6932989F1FD (invoice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE invoice_line ADD CONSTRAINT FK_D3D1D6932989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE billing_record DROP FOREIGN KEY FK_DEEEBCC119EB6921');
        $this->addSql('ALTER TABLE billing_record DROP FOREIGN KEY FK_DEEEBCC19A1887DC');
        $this->addSql('ALTER TABLE billing_record DROP FOREIGN KEY FK_DEEEBCC12989F1FD');
        $this->addSql('DROP TABLE billing_record');
        $this->addSql('ALTER TABLE company ADD legal_notice LONGTEXT DEFAULT NULL, ADD footer_text LONGTEXT DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE city city VARCHAR(100) DEFAULT NULL, CHANGE country country VARCHAR(100) DEFAULT NULL, CHANGE province website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD subtotal NUMERIC(10, 2) NOT NULL, ADD total NUMERIC(10, 2) NOT NULL, ADD currency VARCHAR(3) NOT NULL, ADD status VARCHAR(20) NOT NULL, ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME DEFAULT NULL, DROP total_amount, DROP net_amount, DROP description, DROP is_electronic, CHANGE issue_date issue_date DATE NOT NULL, CHANGE due_date due_date DATE DEFAULT NULL, CHANGE tax_rate tax_rate NUMERIC(5, 2) NOT NULL, CHANGE xml_content notes LONGTEXT DEFAULT NULL');
    }
}
