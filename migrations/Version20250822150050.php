<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822150050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code ADD gain_id INT NOT NULL');
        $this->addSql('ALTER TABLE code ADD CONSTRAINT FK_77153098C60EF8C4 FOREIGN KEY (gain_id) REFERENCES gain (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_77153098C60EF8C4 ON code (gain_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code DROP FOREIGN KEY FK_77153098C60EF8C4');
        $this->addSql('DROP INDEX UNIQ_77153098C60EF8C4 ON code');
        $this->addSql('ALTER TABLE code DROP gain_id');
    }
}
