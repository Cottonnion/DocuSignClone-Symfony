<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250427091713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add failed_login_attempts and lockout_until columns to wp_users table';
    }

    public function up(Schema $schema): void
    {
        // First add the column as nullable
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD failed_login_attempts INT DEFAULT NULL
        SQL);
        
        // Update existing records to have a value of 0
        $this->addSql(<<<'SQL'
            UPDATE wp_users SET failed_login_attempts = 0 WHERE failed_login_attempts IS NULL
        SQL);
        
        // Now make the column NOT NULL
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ALTER COLUMN failed_login_attempts SET NOT NULL
        SQL);
        
        // Add the lockout_until column
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD lockout_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP failed_login_attempts
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP lockout_until
        SQL);
    }
}
