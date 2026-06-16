<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616070428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE information_sheet_read (id SERIAL NOT NULL, agent_id INT NOT NULL, sheet_id INT NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C8215F733414710B ON information_sheet_read (agent_id)');
        $this->addSql('CREATE INDEX IDX_C8215F738B1206A5 ON information_sheet_read (sheet_id)');
        $this->addSql('CREATE INDEX idx_information_sheet_read_read_at ON information_sheet_read (read_at)');
        $this->addSql('COMMENT ON COLUMN information_sheet_read.read_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE information_sheet_workspace (id SERIAL NOT NULL, agent_id INT NOT NULL, sheet_id INT NOT NULL, title VARCHAR(180) NOT NULL, thematic_snapshot VARCHAR(140) NOT NULL, personal_notes TEXT DEFAULT NULL, questions TEXT DEFAULT NULL, additional_elements TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42F558413414710B ON information_sheet_workspace (agent_id)');
        $this->addSql('CREATE INDEX IDX_42F558418B1206A5 ON information_sheet_workspace (sheet_id)');
        $this->addSql('CREATE INDEX idx_information_sheet_workspace_created_at ON information_sheet_workspace (created_at)');
        $this->addSql('COMMENT ON COLUMN information_sheet_workspace.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN information_sheet_workspace.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE information_sheet_workspace_attachment (id SERIAL NOT NULL, workspace_id INT NOT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(180) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_10C86A1582D40A1F ON information_sheet_workspace_attachment (workspace_id)');
        $this->addSql('COMMENT ON COLUMN information_sheet_workspace_attachment.uploaded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE information_sheet_workspace_message (id SERIAL NOT NULL, workspace_id INT NOT NULL, author_id INT NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5094372282D40A1F ON information_sheet_workspace_message (workspace_id)');
        $this->addSql('CREATE INDEX IDX_50943722F675F31B ON information_sheet_workspace_message (author_id)');
        $this->addSql('CREATE INDEX idx_information_sheet_workspace_message_created_at ON information_sheet_workspace_message (created_at)');
        $this->addSql('COMMENT ON COLUMN information_sheet_workspace_message.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE information_sheet_read ADD CONSTRAINT FK_C8215F733414710B FOREIGN KEY (agent_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_read ADD CONSTRAINT FK_C8215F738B1206A5 FOREIGN KEY (sheet_id) REFERENCES information_sheet (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_workspace ADD CONSTRAINT FK_42F558413414710B FOREIGN KEY (agent_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_workspace ADD CONSTRAINT FK_42F558418B1206A5 FOREIGN KEY (sheet_id) REFERENCES information_sheet (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_workspace_attachment ADD CONSTRAINT FK_10C86A1582D40A1F FOREIGN KEY (workspace_id) REFERENCES information_sheet_workspace (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_workspace_message ADD CONSTRAINT FK_5094372282D40A1F FOREIGN KEY (workspace_id) REFERENCES information_sheet_workspace (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE information_sheet_workspace_message ADD CONSTRAINT FK_50943722F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD display_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE information_sheet_read DROP CONSTRAINT FK_C8215F733414710B');
        $this->addSql('ALTER TABLE information_sheet_read DROP CONSTRAINT FK_C8215F738B1206A5');
        $this->addSql('ALTER TABLE information_sheet_workspace DROP CONSTRAINT FK_42F558413414710B');
        $this->addSql('ALTER TABLE information_sheet_workspace DROP CONSTRAINT FK_42F558418B1206A5');
        $this->addSql('ALTER TABLE information_sheet_workspace_attachment DROP CONSTRAINT FK_10C86A1582D40A1F');
        $this->addSql('ALTER TABLE information_sheet_workspace_message DROP CONSTRAINT FK_5094372282D40A1F');
        $this->addSql('ALTER TABLE information_sheet_workspace_message DROP CONSTRAINT FK_50943722F675F31B');
        $this->addSql('DROP TABLE information_sheet_read');
        $this->addSql('DROP TABLE information_sheet_workspace');
        $this->addSql('DROP TABLE information_sheet_workspace_attachment');
        $this->addSql('DROP TABLE information_sheet_workspace_message');
        $this->addSql('ALTER TABLE "user" DROP display_name');
    }
}
