<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250429120054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER created_at TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER last_login_at TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER lockout_until TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER is_email_verified TYPE BOOLEAN
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER is_email_verified DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER email_verification_token_expires_at TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER last_login_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER lockout_until TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER is_email_verified TYPE BOOLEAN
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER is_email_verified SET DEFAULT false
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER email_verification_token_expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
    }
}
