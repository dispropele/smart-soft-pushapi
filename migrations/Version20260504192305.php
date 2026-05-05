<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504192305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop subcategory from goods';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE goods DROP CONSTRAINT fk_563b92d5dc6fe57');
        $this->addSql('DROP INDEX idx_563b92d5dc6fe57');
        $this->addSql('ALTER TABLE goods DROP subcategory_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE goods ADD subcategory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE goods ADD CONSTRAINT fk_563b92d5dc6fe57 FOREIGN KEY (subcategory_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_563b92d5dc6fe57 ON goods (subcategory_id)');
    }
}
