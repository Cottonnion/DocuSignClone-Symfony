<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250503100016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C74F2195F9D83E2 ON refresh_token (expires_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX unique_active_token_per_user ON refresh_token (user_id, is_active) WHERE (is_active = true)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users ADD last_password_change TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C74F2195F9D83E2
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX unique_active_token_per_user
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE wp_users DROP last_password_change
        SQL);
    }
}
