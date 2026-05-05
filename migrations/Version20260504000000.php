<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260504000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for loan system: clients, loan_tickets, loaned_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE clients_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE clients (id INT NOT NULL DEFAULT nextval(\'clients_id_seq\'), full_name VARCHAR(255) NOT NULL, passport_number VARCHAR(20) NOT NULL, passport_series VARCHAR(20) DEFAULT NULL, address TEXT DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, created_at TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_passport ON clients (passport_number)');
        $this->addSql('CREATE SEQUENCE loan_tickets_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE loan_tickets (id INT NOT NULL DEFAULT nextval(\'loan_tickets_id_seq\'), client_id INT NOT NULL, ticket_number VARCHAR(50) NOT NULL, loan_amount NUMERIC(12, 2) NOT NULL, interest_rate NUMERIC(12, 2) DEFAULT NULL, issued_at TIMESTAMP NOT NULL, return_date TIMESTAMP NOT NULL, status VARCHAR(50) NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP NOT NULL, updated_at TIMESTAMP DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_number ON loan_tickets (ticket_number)');
        $this->addSql('CREATE INDEX idx_client_status ON loan_tickets (client_id, status)');
        $this->addSql('CREATE SEQUENCE loaned_items_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE loaned_items (id INT NOT NULL DEFAULT nextval(\'loaned_items_id_seq\'), loan_ticket_id INT NOT NULL, metal_id INT DEFAULT NULL, metal_standard_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, jewelry_type VARCHAR(50) DEFAULT NULL, weight NUMERIC(10, 2) DEFAULT NULL, estimated_value NUMERIC(12, 2) NOT NULL, condition TEXT DEFAULT NULL, created_at TIMESTAMP NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_loan_ticket ON loaned_items (loan_ticket_id)');
        $this->addSql('ALTER TABLE loan_tickets ADD CONSTRAINT FK_LOAN_TICKETS_CLIENT FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_LOAN_TICKET FOREIGN KEY (loan_ticket_id) REFERENCES loan_tickets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_METAL FOREIGN KEY (metal_id) REFERENCES metals (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE loaned_items ADD CONSTRAINT FK_LOANED_ITEMS_METAL_STANDARD FOREIGN KEY (metal_standard_id) REFERENCES metal_standards (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_LOAN_TICKET');
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_METAL');
        $this->addSql('ALTER TABLE loaned_items DROP CONSTRAINT FK_LOANED_ITEMS_METAL_STANDARD');
        $this->addSql('ALTER TABLE loan_tickets DROP CONSTRAINT FK_LOAN_TICKETS_CLIENT');
        $this->addSql('DROP TABLE loaned_items');
        $this->addSql('DROP TABLE loan_tickets');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP SEQUENCE clients_id_seq');
        $this->addSql('DROP SEQUENCE loan_tickets_id_seq');
        $this->addSql('DROP SEQUENCE loaned_items_id_seq');
    }
}
