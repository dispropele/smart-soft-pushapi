<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add has_stones column to good_types table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE good_types ADD has_stones BOOLEAN DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE good_types DROP COLUMN has_stones');
    }
}
