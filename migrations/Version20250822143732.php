<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822143732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE grand_lot_draw ADD winner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE grand_lot_draw ADD CONSTRAINT FK_A74DDE335DFCD4B8 FOREIGN KEY (winner_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A74DDE335DFCD4B8 ON grand_lot_draw (winner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE grand_lot_draw DROP FOREIGN KEY FK_A74DDE335DFCD4B8');
        $this->addSql('DROP INDEX UNIQ_A74DDE335DFCD4B8 ON grand_lot_draw');
        $this->addSql('ALTER TABLE grand_lot_draw DROP winner_id');
    }
}
