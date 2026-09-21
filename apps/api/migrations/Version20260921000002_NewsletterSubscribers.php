<?php

declare(strict_types=1);

namespace StagasBites\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921000002_NewsletterSubscribers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Newsletter subscribers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscribers (
            id VARCHAR(36) NOT NULL,
            email VARCHAR(180) NOT NULL,
            unsubscribe_token VARCHAR(64) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscriber_email ON subscribers (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscriber_token ON subscribers (unsubscribe_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS subscribers');
    }
}
