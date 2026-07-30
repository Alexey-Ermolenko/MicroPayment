<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds the ROLE_ADMIN account from ADMIN_EMAIL / ADMIN_PASSWORD: the role cannot be
 * self-assigned on register anymore.
 */
final class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the initial ROLE_ADMIN user';
    }

    /**
     * @throws \JsonException
     */
    public function up(Schema $schema): void
    {
        $email = ($_SERVER['ADMIN_EMAIL'] ?? '');
        $password = ($_SERVER['ADMIN_PASSWORD'] ?? '');

        if ($email === '' || $password === '') {
            $this->write('ADMIN_EMAIL / ADMIN_PASSWORD are not set, skipping the admin seed.');

            return;
        }

        if (false !== $this->connection->fetchOne('SELECT 1 FROM users WHERE email = ?', [$email])) {
            $this->write(sprintf('Admin "%s" already exists, nothing to seed.', $email));

            return;
        }

        $this->addSql(
            'INSERT INTO users (id, email, password, roles, created_at)
             VALUES (gen_random_uuid(), ?, ?, CAST(? AS JSON), CAST(NOW() AS TIMESTAMP(0)))',
            [
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                json_encode(['ROLE_ADMIN'], JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $email = ($_SERVER['ADMIN_EMAIL'] ?? '');

        if ($email !== '') {
            $this->addSql('DELETE FROM users WHERE email = ?', [$email]);
        }
    }
}
