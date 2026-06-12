<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107134923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_qr_scan (id SERIAL NOT NULL, team_id INT NOT NULL, qr_sequence_id INT NOT NULL, scanned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scanned_by_user_agent VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3154EDB4296CD8AE ON team_qr_scan (team_id)');
        $this->addSql('CREATE INDEX IDX_3154EDB4C1E08F04 ON team_qr_scan (qr_sequence_id)');
        $this->addSql('COMMENT ON COLUMN team_qr_scan.scanned_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE team_qr_scan ADD CONSTRAINT FK_3154EDB4296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_qr_scan ADD CONSTRAINT FK_3154EDB4C1E08F04 FOREIGN KEY (qr_sequence_id) REFERENCES team_qr_sequence (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE team_qr_scan DROP CONSTRAINT FK_3154EDB4296CD8AE');
        $this->addSql('ALTER TABLE team_qr_scan DROP CONSTRAINT FK_3154EDB4C1E08F04');
        $this->addSql('DROP TABLE team_qr_scan');
    }
}
