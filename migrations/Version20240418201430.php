<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240418201430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE level_theme (level_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_FDACE2015FB14BA7 (level_id), INDEX IDX_FDACE20159027487 (theme_id), PRIMARY KEY(level_id, theme_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE level_theme ADD CONSTRAINT FK_FDACE2015FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE level_theme ADD CONSTRAINT FK_FDACE20159027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE type DROP symbol');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE level_theme DROP FOREIGN KEY FK_FDACE2015FB14BA7');
        $this->addSql('ALTER TABLE level_theme DROP FOREIGN KEY FK_FDACE20159027487');
        $this->addSql('DROP TABLE level_theme');
        $this->addSql('ALTER TABLE type ADD symbol VARCHAR(255) NOT NULL');
    }
}
