<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629133722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix manual nullable date columns generated as strings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manual_section ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER reviewed_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER published_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_section ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING NULLIF(updated_at, \'\')::TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE manual_page ALTER reviewed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING NULLIF(reviewed_at, \'\')::TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE manual_page ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING NULLIF(updated_at, \'\')::TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE manual_page ALTER published_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING NULLIF(published_at, \'\')::TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN manual_section.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN manual_page.reviewed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN manual_page.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN manual_page.published_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN manual_section.updated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN manual_page.reviewed_at IS NULL');
        $this->addSql('COMMENT ON COLUMN manual_page.updated_at IS NULL');
        $this->addSql('COMMENT ON COLUMN manual_page.published_at IS NULL');
        $this->addSql('ALTER TABLE manual_section ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER reviewed_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_page ALTER published_at DROP DEFAULT');
        $this->addSql('ALTER TABLE manual_section ALTER updated_at TYPE VARCHAR(255) USING updated_at::VARCHAR(255)');
        $this->addSql('ALTER TABLE manual_page ALTER reviewed_at TYPE VARCHAR(255) USING reviewed_at::VARCHAR(255)');
        $this->addSql('ALTER TABLE manual_page ALTER updated_at TYPE VARCHAR(255) USING updated_at::VARCHAR(255)');
        $this->addSql('ALTER TABLE manual_page ALTER published_at TYPE VARCHAR(255) USING published_at::VARCHAR(255)');
    }
}
