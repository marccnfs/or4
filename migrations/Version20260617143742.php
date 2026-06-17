<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617143742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_invitation (id SERIAL NOT NULL, team_id INT NOT NULL, invited_by_id INT NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CFC41367296CD8AE ON team_invitation (team_id)');
        $this->addSql('CREATE INDEX IDX_CFC41367A7B4A7E3 ON team_invitation (invited_by_id)');
        $this->addSql('CREATE INDEX idx_team_invitation_email ON team_invitation (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_invitation_token ON team_invitation (token)');
        $this->addSql('COMMENT ON COLUMN team_invitation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN team_invitation.accepted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE team_membership (id SERIAL NOT NULL, team_id INT NOT NULL, user_id INT NOT NULL, role VARCHAR(30) NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B826A040296CD8AE ON team_membership (team_id)');
        $this->addSql('CREATE INDEX IDX_B826A040A76ED395 ON team_membership (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_team_member ON team_membership (team_id, user_id)');
        $this->addSql('COMMENT ON COLUMN team_membership.joined_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE team_message (id SERIAL NOT NULL, team_id INT NOT NULL, author_id INT NOT NULL, content TEXT DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, image_original_name VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_49C44148296CD8AE ON team_message (team_id)');
        $this->addSql('CREATE INDEX IDX_49C44148F675F31B ON team_message (author_id)');
        $this->addSql('CREATE INDEX idx_team_message_created_at ON team_message (created_at)');
        $this->addSql('COMMENT ON COLUMN team_message.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE team_invitation ADD CONSTRAINT FK_CFC41367296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_invitation ADD CONSTRAINT FK_CFC41367A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_membership ADD CONSTRAINT FK_B826A040296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_membership ADD CONSTRAINT FK_B826A040A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE team_message ADD CONSTRAINT FK_49C44148F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE team_invitation DROP CONSTRAINT FK_CFC41367296CD8AE');
        $this->addSql('ALTER TABLE team_invitation DROP CONSTRAINT FK_CFC41367A7B4A7E3');
        $this->addSql('ALTER TABLE team_membership DROP CONSTRAINT FK_B826A040296CD8AE');
        $this->addSql('ALTER TABLE team_membership DROP CONSTRAINT FK_B826A040A76ED395');
        $this->addSql('ALTER TABLE team_message DROP CONSTRAINT FK_49C44148296CD8AE');
        $this->addSql('ALTER TABLE team_message DROP CONSTRAINT FK_49C44148F675F31B');
        $this->addSql('DROP TABLE team_invitation');
        $this->addSql('DROP TABLE team_membership');
        $this->addSql('DROP TABLE team_message');
    }
}
