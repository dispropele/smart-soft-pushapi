<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make good_types.code nullable so it can be auto-generated.
 * Also add missing sequences for goods/merchants if seeding requires.
 */
final class Version20260511200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make good_types.code nullable for auto-generation';
    }

    public function up(Schema $schema): void
    {
        // Разрешаем NULL в поле code у good_types — код генерируется автоматически
        $this->addSql('ALTER TABLE good_types ALTER COLUMN code DROP NOT NULL');
        $this->addSql('ALTER TABLE good_types ALTER COLUMN code SET DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Обратно делаем NOT NULL (может упасть если есть NULL значения)
        $this->addSql("UPDATE good_types SET code = 'auto_' || id WHERE code IS NULL");
        $this->addSql('ALTER TABLE good_types ALTER COLUMN code SET NOT NULL');
    }
}
