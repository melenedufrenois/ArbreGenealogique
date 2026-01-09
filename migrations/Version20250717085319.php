<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717085319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personne_personne (personne_source INT NOT NULL, personne_target INT NOT NULL, PRIMARY KEY(personne_source, personne_target))');
        $this->addSql('CREATE INDEX IDX_CC1CC8AA6BF0479E ON personne_personne (personne_source)');
        $this->addSql('CREATE INDEX IDX_CC1CC8AA72151711 ON personne_personne (personne_target)');
        $this->addSql('ALTER TABLE personne_personne ADD CONSTRAINT FK_CC1CC8AA6BF0479E FOREIGN KEY (personne_source) REFERENCES personne (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE personne_personne ADD CONSTRAINT FK_CC1CC8AA72151711 FOREIGN KEY (personne_target) REFERENCES personne (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE personne_personne');
    }
}
