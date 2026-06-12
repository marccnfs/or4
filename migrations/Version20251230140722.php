<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230140722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE escape_game (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, options JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN escape_game.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN escape_game.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE step (id SERIAL NOT NULL, escape_game_id INT NOT NULL, type VARCHAR(1) NOT NULL, solution VARCHAR(255) NOT NULL, letter VARCHAR(1) NOT NULL, order_number INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_43B9FE3C535D9AB ON step (escape_game_id)');
        $this->addSql('CREATE TABLE team (id SERIAL NOT NULL, escape_game_id INT NOT NULL, name VARCHAR(255) NOT NULL, registration_code VARCHAR(100) NOT NULL, qr_token VARCHAR(255) NOT NULL, state VARCHAR(50) NOT NULL, score INT NOT NULL, letter_order JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C4E0A61F535D9AB ON team (escape_game_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_registration_code ON team (registration_code)');
        $this->addSql('CREATE TABLE team_qr_sequence (id SERIAL NOT NULL, team_id INT NOT NULL, order_number INT NOT NULL, qr_code VARCHAR(255) NOT NULL, hint VARCHAR(255) DEFAULT NULL, validated BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D7E800D2296CD8AE ON team_qr_sequence (team_id)');
        $this->addSql('CREATE TABLE team_step_progress (id SERIAL NOT NULL, team_id INT NOT NULL, step_id INT NOT NULL, state VARCHAR(50) NOT NULL, validated_letter VARCHAR(1) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6766D5B8296CD8AE ON team_step_progress (team_id)');
        $this->addSql('CREATE INDEX IDX_6766D5B873B21E9C ON team_step_progress (step_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_step_progress_team_step ON team_step_progress (team_id, step_id)');
        $this->addSql('COMMENT ON COLUMN team_step_progress.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN team_step_progress.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE step ADD CONSTRAINT FK_43B9FE3C535D9AB FOREIGN KEY (escape_game_id) REFERENCES escape_game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F535D9AB FOREIGN KEY (escape_game_id) REFERENCES escape_game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_qr_sequence ADD CONSTRAINT FK_D7E800D2296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_step_progress ADD CONSTRAINT FK_6766D5B8296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_step_progress ADD CONSTRAINT FK_6766D5B873B21E9C FOREIGN KEY (step_id) REFERENCES step (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE step DROP CONSTRAINT FK_43B9FE3C535D9AB');
        $this->addSql('ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F535D9AB');
        $this->addSql('ALTER TABLE team_qr_sequence DROP CONSTRAINT FK_D7E800D2296CD8AE');
        $this->addSql('ALTER TABLE team_step_progress DROP CONSTRAINT FK_6766D5B8296CD8AE');
        $this->addSql('ALTER TABLE team_step_progress DROP CONSTRAINT FK_6766D5B873B21E9C');
        $this->addSql('DROP TABLE escape_game');
        $this->addSql('DROP TABLE step');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_qr_sequence');
        $this->addSql('DROP TABLE team_step_progress');
    }
}
