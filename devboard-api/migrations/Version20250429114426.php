<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250429114426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD is_email_verified BOOLEAN NOT NULL DEFAULT false
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD email_verification_token VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD email_verification_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP is_email_verified
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP email_verification_token
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP email_verification_token_expires_at
        SQL);
    }
}
