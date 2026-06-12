<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612100131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE information_sheet (id SERIAL NOT NULL, title VARCHAR(180) NOT NULL, subtitle VARCHAR(220) DEFAULT NULL, category VARCHAR(64) NOT NULL, thematic VARCHAR(140) NOT NULL, content_markdown TEXT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, image_alt VARCHAR(220) DEFAULT NULL, slug VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E41B5532989D9B62 ON information_sheet (slug)');
        $this->addSql('CREATE INDEX idx_information_sheet_category ON information_sheet (category)');
        $this->addSql('CREATE INDEX idx_information_sheet_slug ON information_sheet (slug)');
        $this->addSql('COMMENT ON COLUMN information_sheet.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN information_sheet.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE information_sheet');
    }
}
