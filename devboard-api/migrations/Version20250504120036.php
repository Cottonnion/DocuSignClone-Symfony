<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250504120036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update document_version table with new fields and make status non-nullable';
    }

    public function up(Schema $schema): void
    {
        // First add the new columns
        $this->addSql('ALTER TABLE document_version ADD status VARCHAR(20) DEFAULT \'draft\'');
        $this->addSql('ALTER TABLE document_version ADD tags JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE document_version ADD metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE document_version ADD is_major BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE document_version ADD file_size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document_version ADD file_type VARCHAR(255) DEFAULT NULL');

        // Update any existing NULL status records to 'draft'
        $this->addSql('UPDATE document_version SET status = \'draft\' WHERE status IS NULL');

        // Now make status non-nullable
        $this->addSql('ALTER TABLE document_version ALTER COLUMN status SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove the new columns
        $this->addSql('ALTER TABLE document_version DROP status');
        $this->addSql('ALTER TABLE document_version DROP tags');
        $this->addSql('ALTER TABLE document_version DROP metadata');
        $this->addSql('ALTER TABLE document_version DROP is_major');
        $this->addSql('ALTER TABLE document_version DROP file_size');
        $this->addSql('ALTER TABLE document_version DROP file_type');
    }
}
