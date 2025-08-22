<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822145438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket ADD store_id INT NOT NULL, ADD code_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA327DAFE17 FOREIGN KEY (code_id) REFERENCES code (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3B092A811 ON ticket (store_id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA327DAFE17 ON ticket (code_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B092A811');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA327DAFE17');
        $this->addSql('DROP INDEX IDX_97A0ADA3B092A811 ON ticket');
        $this->addSql('DROP INDEX IDX_97A0ADA327DAFE17 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP store_id, DROP code_id');
    }
}
