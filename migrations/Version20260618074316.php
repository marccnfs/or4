<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618074316 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team DROP CONSTRAINT FK_C4E0A61F535D9AB');
        $this->addSql('ALTER TABLE team ALTER escape_game_id DROP NOT NULL');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F535D9AB FOREIGN KEY (escape_game_id) REFERENCES escape_game (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE team DROP CONSTRAINT fk_c4e0a61f535d9ab');
        $this->addSql('ALTER TABLE team ALTER escape_game_id SET NOT NULL');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT fk_c4e0a61f535d9ab FOREIGN KEY (escape_game_id) REFERENCES escape_game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
