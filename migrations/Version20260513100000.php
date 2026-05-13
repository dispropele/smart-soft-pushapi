<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop merchant_id from pledged_items (Merchant entity removed)';
    }

    public function up(Schema $schema): void
    {
        // Drop FK if it exists
        $this->addSql('ALTER TABLE pledged_items DROP CONSTRAINT IF EXISTS fk_pi_merchant');
        $this->addSql('DROP INDEX IF EXISTS idx_pledged_items_merchant');
        $this->addSql('ALTER TABLE pledged_items DROP COLUMN IF EXISTS merchant_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pledged_items ADD merchant_id INT DEFAULT NULL');
        // FK not restored — Merchant entity no longer exists
    }
}