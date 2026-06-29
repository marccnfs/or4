<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629113314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE manual_page (id SERIAL NOT NULL, section_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(190) NOT NULL, summary VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, content_markdown TEXT NOT NULL, tags JSON DEFAULT NULL, position INT NOT NULL, status VARCHAR(30) NOT NULL, reviewed_at VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at VARCHAR(255) DEFAULT NULL, published_at VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_63097909D823E37A ON manual_page (section_id)');
        $this->addSql('CREATE INDEX IDX_63097909B03A8386 ON manual_page (created_by_id)');
        $this->addSql('CREATE INDEX IDX_63097909896DBBDE ON manual_page (updated_by_id)');
        $this->addSql('CREATE INDEX idx_manual_page_position ON manual_page (position)');
        $this->addSql('CREATE INDEX idx_manual_page_status ON manual_page (status)');
        $this->addSql('CREATE INDEX idx_manual_page_type ON manual_page (type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_manual_page_section_slug ON manual_page (section_id, slug)');
        $this->addSql('COMMENT ON COLUMN manual_page.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE manual_page_version (id SERIAL NOT NULL, page_id INT NOT NULL, created_by_id INT DEFAULT NULL, title_snapshot VARCHAR(180) NOT NULL, content_markdown_snapshot TEXT NOT NULL, version_number INT NOT NULL, change_summary VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5476E038C4663E4 ON manual_page_version (page_id)');
        $this->addSql('CREATE INDEX IDX_5476E038B03A8386 ON manual_page_version (created_by_id)');
        $this->addSql('CREATE INDEX idx_manual_page_version_created_at ON manual_page_version (created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_manual_page_version_number ON manual_page_version (page_id, version_number)');
        $this->addSql('COMMENT ON COLUMN manual_page_version.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE manual_section (id SERIAL NOT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(190) NOT NULL, description TEXT DEFAULT NULL, icon VARCHAR(80) DEFAULT NULL, position INT NOT NULL, is_published BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CF849DCC989D9B62 ON manual_section (slug)');
        $this->addSql('CREATE INDEX idx_manual_section_position ON manual_section (position)');
        $this->addSql('COMMENT ON COLUMN manual_section.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE manual_page ADD CONSTRAINT FK_63097909D823E37A FOREIGN KEY (section_id) REFERENCES manual_section (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manual_page ADD CONSTRAINT FK_63097909B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manual_page ADD CONSTRAINT FK_63097909896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manual_page_version ADD CONSTRAINT FK_5476E038C4663E4 FOREIGN KEY (page_id) REFERENCES manual_page (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manual_page_version ADD CONSTRAINT FK_5476E038B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE manual_page DROP CONSTRAINT FK_63097909D823E37A');
        $this->addSql('ALTER TABLE manual_page DROP CONSTRAINT FK_63097909B03A8386');
        $this->addSql('ALTER TABLE manual_page DROP CONSTRAINT FK_63097909896DBBDE');
        $this->addSql('ALTER TABLE manual_page_version DROP CONSTRAINT FK_5476E038C4663E4');
        $this->addSql('ALTER TABLE manual_page_version DROP CONSTRAINT FK_5476E038B03A8386');
        $this->addSql('DROP TABLE manual_page');
        $this->addSql('DROP TABLE manual_page_version');
        $this->addSql('DROP TABLE manual_section');
    }
}
