<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: users, wallets, transactions, notifications, audit_logs.
 */
final class Version20260702000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial MicroPayment schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL DEFAULT '[]', created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');

        $this->addSql('CREATE TABLE wallets (id UUID NOT NULL, user_id UUID NOT NULL, currency VARCHAR(3) NOT NULL, balance BIGINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_wallet_user ON wallets (user_id)');

        $this->addSql('CREATE TABLE transactions (id UUID NOT NULL, idempotency_key VARCHAR(128) DEFAULT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, sender_wallet_id UUID DEFAULT NULL, recipient_wallet_id UUID DEFAULT NULL, refunded_transaction_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C57B8F0F5 ON transactions (idempotency_key)');
        $this->addSql('CREATE INDEX idx_transaction_sender ON transactions (sender_wallet_id)');
        $this->addSql('CREATE INDEX idx_transaction_recipient ON transactions (recipient_wallet_id)');
        $this->addSql('CREATE INDEX idx_transaction_refunded ON transactions (refunded_transaction_id)');
        $this->addSql('CREATE INDEX idx_transaction_status ON transactions (status)');
        $this->addSql('CREATE INDEX idx_transaction_created_at ON transactions (created_at)');
        $this->addSql('CREATE INDEX idx_transaction_type ON transactions (type)');

        $this->addSql('CREATE TABLE notifications (id UUID NOT NULL, user_id UUID NOT NULL, type VARCHAR(64) NOT NULL, message TEXT NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_notification_user ON notifications (user_id)');

        $this->addSql('CREATE TABLE logs (id UUID NOT NULL, actor VARCHAR(128) NOT NULL, action VARCHAR(64) NOT NULL, entity_type VARCHAR(64) NOT NULL, entity_id VARCHAR(64) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_log_entity ON logs (entity_type, entity_id)');

        $this->addSql('ALTER TABLE wallets ADD CONSTRAINT FK_wallet_user FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_transaction_sender FOREIGN KEY (sender_wallet_id) REFERENCES wallets (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_transaction_recipient FOREIGN KEY (recipient_wallet_id) REFERENCES wallets (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_transaction_refunded FOREIGN KEY (refunded_transaction_id) REFERENCES transactions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_notification_user FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_notification_user');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_transaction_refunded');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_transaction_recipient');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_transaction_sender');
        $this->addSql('ALTER TABLE wallets DROP CONSTRAINT FK_wallet_user');
        $this->addSql('DROP TABLE logs');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE wallets');
        $this->addSql('DROP TABLE users');
    }
}
