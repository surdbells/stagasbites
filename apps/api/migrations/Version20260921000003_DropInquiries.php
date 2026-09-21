<?php

declare(strict_types=1);

namespace StagasBites\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921000003_DropInquiries extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove contact/catering form submissions: customers now reach us by phone, email, WhatsApp and social';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS inquiries');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("CREATE TABLE inquiries (
            id VARCHAR(36) NOT NULL,
            type VARCHAR(20) NOT NULL,
            name VARCHAR(160) NOT NULL,
            email VARCHAR(180) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            event_date DATE DEFAULT NULL,
            guest_count INT DEFAULT NULL,
            event_type VARCHAR(120) DEFAULT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))");
        $this->addSql('CREATE INDEX idx_inquiry_status ON inquiries (status)');
    }
}
