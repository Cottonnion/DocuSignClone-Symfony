<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250503220929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add timestamps to profile and signed status to signatory with proper defaults';
    }

    public function up(Schema $schema): void
    {
        // Add timestamps to profile with current timestamp default
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
        SQL);

        // Add signed status to signatory with false default
        $this->addSql(<<<'SQL'
            ALTER TABLE signatory ADD signed BOOLEAN DEFAULT FALSE
        SQL);

        // Update existing records
        $this->addSql(<<<'SQL'
            UPDATE signatory SET signed = FALSE WHERE signed IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE signatory DROP signed
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP created_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP updated_at
        SQL);
    }
}
