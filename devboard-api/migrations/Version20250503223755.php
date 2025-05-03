<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250503223755 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename signatory order column and fix profile timestamps';
    }

    public function up(Schema $schema): void
    {
        // Rename order column to signing_order
        $this->addSql('ALTER TABLE signatory RENAME COLUMN "order" TO signing_order');

        // Update existing profile records with current timestamp
        $this->addSql('UPDATE profile SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('UPDATE profile SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL');

        // Make timestamps NOT NULL
        $this->addSql('ALTER TABLE profile ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE profile ALTER COLUMN updated_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert signing_order to order
        $this->addSql('ALTER TABLE signatory RENAME COLUMN signing_order TO "order"');

        // Make timestamps nullable again
        $this->addSql('ALTER TABLE profile ALTER COLUMN created_at DROP NOT NULL');
        $this->addSql('ALTER TABLE profile ALTER COLUMN updated_at DROP NOT NULL');
    }
}
