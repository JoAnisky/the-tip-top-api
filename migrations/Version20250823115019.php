<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250823115019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE code (id INT AUTO_INCREMENT NOT NULL, gain_id INT NOT NULL, is_used TINYINT(1) NOT NULL, used_on DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_77153098C60EF8C4 (gain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE gain (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, value NUMERIC(10, 2) DEFAULT NULL, probability INT NOT NULL, allocation_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', claim_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE grand_lot_draw (id INT AUTO_INCREMENT NOT NULL, winner_id INT DEFAULT NULL, attribution_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', label VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_A74DDE335DFCD4B8 (winner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE grand_lot_participation (id INT AUTO_INCREMENT NOT NULL, participation_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE social_account (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, provider_name VARCHAR(255) NOT NULL, provider_id INT NOT NULL, INDEX IDX_F24D8339A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE store (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL, store_manager VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ticket (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, store_id INT NOT NULL, code_id INT DEFAULT NULL, issued_on DATETIME NOT NULL, receipt_amount NUMERIC(10, 2) NOT NULL, INDEX IDX_97A0ADA3A76ED395 (user_id), INDEX IDX_97A0ADA3B092A811 (store_id), INDEX IDX_97A0ADA327DAFE17 (code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, grand_lot_participation_id INT DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, birthdate DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', gender VARCHAR(10) DEFAULT NULL, city VARCHAR(150) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, country VARCHAR(180) DEFAULT NULL, phone_number VARCHAR(20) DEFAULT NULL, registered_in DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', roles JSON NOT NULL COMMENT \'(DC2Type:json)\', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649BD404C8B (grand_lot_participation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE code ADD CONSTRAINT FK_77153098C60EF8C4 FOREIGN KEY (gain_id) REFERENCES gain (id)');
        $this->addSql('ALTER TABLE grand_lot_draw ADD CONSTRAINT FK_A74DDE335DFCD4B8 FOREIGN KEY (winner_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE social_account ADD CONSTRAINT FK_F24D8339A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3B092A811 FOREIGN KEY (store_id) REFERENCES store (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA327DAFE17 FOREIGN KEY (code_id) REFERENCES code (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649BD404C8B FOREIGN KEY (grand_lot_participation_id) REFERENCES grand_lot_participation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE code DROP FOREIGN KEY FK_77153098C60EF8C4');
        $this->addSql('ALTER TABLE grand_lot_draw DROP FOREIGN KEY FK_A74DDE335DFCD4B8');
        $this->addSql('ALTER TABLE social_account DROP FOREIGN KEY FK_F24D8339A76ED395');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3A76ED395');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3B092A811');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA327DAFE17');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649BD404C8B');
        $this->addSql('DROP TABLE code');
        $this->addSql('DROP TABLE gain');
        $this->addSql('DROP TABLE grand_lot_draw');
        $this->addSql('DROP TABLE grand_lot_participation');
        $this->addSql('DROP TABLE social_account');
        $this->addSql('DROP TABLE store');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE user');
    }
}
