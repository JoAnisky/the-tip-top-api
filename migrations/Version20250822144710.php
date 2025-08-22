<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250822144710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE social_account ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE social_account ADD CONSTRAINT FK_F24D8339A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_F24D8339A76ED395 ON social_account (user_id)');
        $this->addSql('ALTER TABLE ticket ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3A76ED395 ON ticket (user_id)');
        $this->addSql('ALTER TABLE user ADD grand_lot_participation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649BD404C8B FOREIGN KEY (grand_lot_participation_id) REFERENCES grand_lot_participation (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649BD404C8B ON user (grand_lot_participation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649BD404C8B');
        $this->addSql('DROP INDEX UNIQ_8D93D649BD404C8B ON user');
        $this->addSql('ALTER TABLE user DROP grand_lot_participation_id');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A76ED395');
        $this->addSql('DROP INDEX IDX_97A0ADA3A76ED395 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP user_id');
        $this->addSql('ALTER TABLE social_account DROP FOREIGN KEY FK_F24D8339A76ED395');
        $this->addSql('DROP INDEX IDX_F24D8339A76ED395 ON social_account');
        $this->addSql('ALTER TABLE social_account DROP user_id');
    }
}
