<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717075852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE person DROP CONSTRAINT fk_34dcd1763fd73900');
        $this->addSql('ALTER TABLE person DROP CONSTRAINT fk_34dcd17639dec40e');
        $this->addSql('DROP SEQUENCE person_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE personne_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE personne (id INT NOT NULL, pere_id INT DEFAULT NULL, mere_id INT DEFAULT NULL, prenom VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, naissance DATE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FCEC9EF3FD73900 ON personne (pere_id)');
        $this->addSql('CREATE INDEX IDX_FCEC9EF39DEC40E ON personne (mere_id)');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EF3FD73900 FOREIGN KEY (pere_id) REFERENCES personne (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE personne ADD CONSTRAINT FK_FCEC9EF39DEC40E FOREIGN KEY (mere_id) REFERENCES personne (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE person');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE personne DROP CONSTRAINT FK_FCEC9EF3FD73900');
        $this->addSql('ALTER TABLE personne DROP CONSTRAINT FK_FCEC9EF39DEC40E');
        $this->addSql('DROP SEQUENCE personne_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE person_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE person (id INT NOT NULL, pere_id INT DEFAULT NULL, mere_id INT DEFAULT NULL, prenom VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, naissance DATE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_34dcd1763fd73900 ON person (pere_id)');
        $this->addSql('CREATE INDEX idx_34dcd17639dec40e ON person (mere_id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT fk_34dcd1763fd73900 FOREIGN KEY (pere_id) REFERENCES person (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT fk_34dcd17639dec40e FOREIGN KEY (mere_id) REFERENCES person (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE personne');
    }
}
