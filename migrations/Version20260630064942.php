<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630064942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE manual_reference_row (id SERIAL NOT NULL, reference_table_id INT NOT NULL, data JSON NOT NULL, position INT NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EAE3865D34F57323 ON manual_reference_row (reference_table_id)');
        $this->addSql('CREATE INDEX idx_manual_reference_row_position ON manual_reference_row (position)');
        $this->addSql('CREATE INDEX idx_manual_reference_row_active ON manual_reference_row (is_active)');
        $this->addSql('COMMENT ON COLUMN manual_reference_row.data IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN manual_reference_row.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN manual_reference_row.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE manual_reference_table (id SERIAL NOT NULL, page_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, slug VARCHAR(190) NOT NULL, description TEXT DEFAULT NULL, columns_definition JSON NOT NULL, status VARCHAR(30) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CBF5AF64C4663E4 ON manual_reference_table (page_id)');
        $this->addSql('CREATE INDEX idx_manual_reference_table_status ON manual_reference_table (status)');
        $this->addSql('CREATE INDEX idx_manual_reference_table_position ON manual_reference_table (position)');
        $this->addSql('CREATE UNIQUE INDEX uniq_manual_reference_table_slug ON manual_reference_table (slug)');
        $this->addSql('COMMENT ON COLUMN manual_reference_table.columns_definition IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN manual_reference_table.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN manual_reference_table.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE manual_reference_row ADD CONSTRAINT FK_EAE3865D34F57323 FOREIGN KEY (reference_table_id) REFERENCES manual_reference_table (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manual_reference_table ADD CONSTRAINT FK_CBF5AF64C4663E4 FOREIGN KEY (page_id) REFERENCES manual_page (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE manual_reference_row DROP CONSTRAINT FK_EAE3865D34F57323');
        $this->addSql('ALTER TABLE manual_reference_table DROP CONSTRAINT FK_CBF5AF64C4663E4');
        $this->addSql('DROP TABLE manual_reference_row');
        $this->addSql('DROP TABLE manual_reference_table');
    }
}
