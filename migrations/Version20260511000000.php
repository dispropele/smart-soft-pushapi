<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add jewelry types, stone types, metal colors and update goods/loaned_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE stone_types_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE stone_types (id INT NOT NULL DEFAULT nextval(\'stone_types_id_seq\'), name VARCHAR(100) NOT NULL, code VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id))');
        
        $this->addSql('CREATE SEQUENCE metal_colors_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE metal_colors (id INT NOT NULL DEFAULT nextval(\'metal_colors_id_seq\'), name VARCHAR(100) NOT NULL, code VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id))');
        
        $this->addSql('CREATE SEQUENCE good_types_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE good_types (id INT NOT NULL DEFAULT nextval(\'good_types_id_seq\'), category_id INT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_GOOD_TYPES_CATEGORY ON good_types (category_id)');
        $this->addSql('ALTER TABLE good_types ADD CONSTRAINT FK_GOOD_TYPES_CATEGORY FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        $this->addSql('ALTER TABLE goods ADD good_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE goods ADD has_stone BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE goods ADD stone_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE goods ADD metal_color_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_GOODS_GOOD_TYPE ON goods (good_type_id)');
        $this->addSql('CREATE INDEX IDX_GOODS_STONE_TYPE ON goods (stone_type_id)');
        $this->addSql('CREATE INDEX IDX_GOODS_METAL_COLOR ON goods (metal_color_id)');
        $this->addSql('ALTER TABLE goods ADD CONSTRAINT FK_GOODS_GOOD_TYPE FOREIGN KEY (good_type_id) REFERENCES good_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE goods ADD CONSTRAINT FK_GOODS_STONE_TYPE FOREIGN KEY (stone_type_id) REFERENCES stone_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE goods ADD CONSTRAINT FK_GOODS_METAL_COLOR FOREIGN KEY (metal_color_id) REFERENCES metal_colors (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        $this->addSql('ALTER TABLE loaned_items ADD good_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE loaned_items ADD has_stone BOOLEAN DEFAULT false');
        $this->addSql('ALTER TABLE loaned_items ADD stone_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE loaned_items ADD metal_color_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_LOANED_ITEMS_GOOD_TYPE ON loaned_items (good_type_id)');
        $this->addSql('CREATE INDEX IDX_LOANED_ITEMS_STONE_TYPE ON loaned_items (stone_type_id)');
        $this->addSql('CREATE INDEX IDX_LOANED_ITEMS_METAL_COLOR ON loaned_items (metal_color_id)');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_GOOD_TYPE FOREIGN KEY (good_type_id) REFERENCES good_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_STONE_TYPE FOREIGN KEY (stone_type_id) REFERENCES stone_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_METAL_COLOR FOREIGN KEY (metal_color_id) REFERENCES metal_colors (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_STONE_TYPE');
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_METAL_COLOR');
        $this->addSql('ALTER TABLE goods DROP CONSTRAINT FK_GOODS_STONE_TYPE');
        $this->addSql('ALTER TABLE goods DROP CONSTRAINT FK_GOODS_METAL_COLOR');
        $this->addSql('ALTER TABLE good_types DROP CONSTRAINT FK_GOOD_TYPES_CATEGORY');
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_GOOD_TYPE');
        $this->addSql('ALTER TABLE goods DROP CONSTRAINT FK_GOODS_GOOD_TYPE');
        $this->addSql('DROP TABLE stone_types');
        $this->addSql('DROP TABLE metal_colors');
        $this->addSql('DROP TABLE good_types');
        $this->addSql('DROP SEQUENCE stone_types_id_seq');
        $this->addSql('DROP SEQUENCE metal_colors_id_seq');
        $this->addSql('DROP SEQUENCE good_types_id_seq');
        $this->addSql('ALTER TABLE goods DROP COLUMN good_type_id');
        $this->addSql('ALTER TABLE goods DROP COLUMN has_stone');
        $this->addSql('ALTER TABLE goods DROP COLUMN stone_type_id');
        $this->addSql('ALTER TABLE goods DROP COLUMN metal_color_id');
        $this->addSql('ALTER TABLE loaned_items DROP COLUMN good_type_id');
        $this->addSql('ALTER TABLE loaned_items DROP COLUMN has_stone');
        $this->addSql('ALTER TABLE loaned_items DROP COLUMN stone_type_id');
        $this->addSql('ALTER TABLE loaned_items DROP COLUMN metal_color_id');
    }
}
