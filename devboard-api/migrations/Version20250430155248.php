<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430155248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ALTER updated_at SET DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE token_blacklist ALTER blacklisted_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE token_blacklist ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE token_blacklist ALTER blacklisted_at TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE token_blacklist ALTER expires_at TYPE TIMESTAMP(0) WITH TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ALTER updated_at DROP NOT NULL
        SQL);
    }
}
