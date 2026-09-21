<?php

declare(strict_types=1);

namespace StagasBites\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921000001_InitialSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, auth tokens, catalogue, orders, coupons, inquiries, settings, Stripe event log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (
            id VARCHAR(36) NOT NULL,
            email VARCHAR(180) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'customer',
            failed_logins INT NOT NULL DEFAULT 0,
            locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');

        $this->addSql('CREATE TABLE auth_tokens (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            type VARCHAR(20) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_auth_token_hash ON auth_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_auth_token_user ON auth_tokens (user_id)');
        $this->addSql('ALTER TABLE auth_tokens ADD CONSTRAINT fk_auth_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE categories (
            id VARCHAR(36) NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            description TEXT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            meta_title VARCHAR(160) DEFAULT NULL,
            meta_description VARCHAR(320) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_slug ON categories (slug)');

        $this->addSql("CREATE TABLE products (
            id VARCHAR(36) NOT NULL,
            category_id VARCHAR(36) DEFAULT NULL,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            short_description VARCHAR(320) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            gallery JSON NOT NULL DEFAULT '[]',
            tags JSON NOT NULL DEFAULT '[]',
            spice_level SMALLINT NOT NULL DEFAULT 0,
            min_quantity INT NOT NULL DEFAULT 1,
            lead_time_hours INT NOT NULL DEFAULT 48,
            is_featured BOOLEAN NOT NULL DEFAULT FALSE,
            is_available BOOLEAN NOT NULL DEFAULT TRUE,
            sort_order INT NOT NULL DEFAULT 0,
            meta_title VARCHAR(160) DEFAULT NULL,
            meta_description VARCHAR(320) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_product_slug ON products (slug)');
        $this->addSql('CREATE INDEX idx_product_category ON products (category_id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE product_options (
            id VARCHAR(36) NOT NULL,
            product_id VARCHAR(36) NOT NULL,
            label VARCHAR(120) NOT NULL,
            price INT NOT NULL,
            serves VARCHAR(80) DEFAULT NULL,
            is_default BOOLEAN NOT NULL DEFAULT FALSE,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_option_product ON product_options (product_id)');
        $this->addSql('ALTER TABLE product_options ADD CONSTRAINT fk_option_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE orders (
            id VARCHAR(36) NOT NULL,
            order_number VARCHAR(20) NOT NULL,
            user_id VARCHAR(36) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending_payment',
            customer_first_name VARCHAR(80) NOT NULL,
            customer_last_name VARCHAR(80) NOT NULL,
            customer_email VARCHAR(180) NOT NULL,
            customer_phone VARCHAR(30) NOT NULL,
            fulfilment_method VARCHAR(10) NOT NULL,
            fulfilment_date DATE NOT NULL,
            fulfilment_time_slot VARCHAR(40) NOT NULL,
            delivery_address VARCHAR(400) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            subtotal INT NOT NULL,
            discount INT NOT NULL DEFAULT 0,
            delivery_fee INT NOT NULL DEFAULT 0,
            tax INT NOT NULL DEFAULT 0,
            total INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'CAD',
            coupon_code VARCHAR(40) DEFAULT NULL,
            stripe_session_id VARCHAR(255) DEFAULT NULL,
            stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
            paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_order_number ON orders (order_number)');
        $this->addSql('CREATE INDEX idx_order_user ON orders (user_id)');
        $this->addSql('CREATE INDEX idx_order_status ON orders (status)');
        $this->addSql('CREATE INDEX idx_order_fulfilment_date ON orders (fulfilment_date)');
        $this->addSql('CREATE INDEX idx_order_stripe_session ON orders (stripe_session_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE order_items (
            id VARCHAR(36) NOT NULL,
            order_id VARCHAR(36) NOT NULL,
            product_id VARCHAR(36) DEFAULT NULL,
            name VARCHAR(160) NOT NULL,
            option_label VARCHAR(120) NOT NULL,
            unit_price INT NOT NULL,
            quantity INT NOT NULL,
            line_total INT NOT NULL,
            PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_order_item_order ON order_items (order_id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE coupons (
            id VARCHAR(36) NOT NULL,
            code VARCHAR(40) NOT NULL,
            type VARCHAR(10) NOT NULL DEFAULT 'percent',
            value INT NOT NULL,
            min_subtotal INT NOT NULL DEFAULT 0,
            max_redemptions INT DEFAULT NULL,
            times_redeemed INT NOT NULL DEFAULT 0,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_coupon_code ON coupons (code)');

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

        $this->addSql('CREATE TABLE settings (
            key VARCHAR(80) NOT NULL,
            value JSON NOT NULL,
            PRIMARY KEY (key))');

        // Webhook idempotency log, written with INSERT ... ON CONFLICT DO NOTHING (no entity).
        $this->addSql('CREATE TABLE stripe_events (
            id VARCHAR(255) NOT NULL,
            type VARCHAR(120) NOT NULL,
            received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        foreach (['stripe_events', 'settings', 'inquiries', 'coupons', 'order_items', 'orders', 'product_options', 'products', 'categories', 'auth_tokens', 'users'] as $table) {
            $this->addSql("DROP TABLE IF EXISTS {$table} CASCADE");
        }
    }
}
