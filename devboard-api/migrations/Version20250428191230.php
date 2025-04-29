<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250428191230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD first_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD last_name VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD bio VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD avatar VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD phone_number VARCHAR(20) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD location VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD website VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD user_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP username
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES wp_users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8157AA0FA76ED395 ON profile (user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP CONSTRAINT FK_8157AA0FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_8157AA0FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD email VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile ADD username VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP first_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP last_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP bio
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP avatar
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP phone_number
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP location
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP website
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE profile DROP user_id
        SQL);
    }
}
