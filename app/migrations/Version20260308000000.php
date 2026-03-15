<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea el usuario administrador del sistema';
    }

    public function up(Schema $schema): void
    {
        $email = getenv('ADMIN_EMAIL') ?: 'admin@clientsfactory.com';
        $plainPassword = getenv('ADMIN_PASSWORD') ?: 'Admin2026!';
        $password = password_hash($plainPassword, PASSWORD_BCRYPT);

        $this->addSql(
            'INSERT INTO user (email, roles, password) SELECT :email, :roles, :password WHERE NOT EXISTS (SELECT 1 FROM user WHERE email = :email)',
            [
                'email' => $email,
                'roles' => json_encode(['ROLE_ADMIN']),
                'password' => $password,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $email = getenv('ADMIN_EMAIL') ?: 'admin@clientsfactory.com';
        $this->addSql('DELETE FROM user WHERE email = :email', ['email' => $email]);
    }
}
