<?php
/**
 * Database schema. Works for both MySQL/MariaDB and SQLite.
 * {PK} and {ENGINE} are replaced depending on the database type.
 */
function shifa_schema(string $driver): array
{
    $pk = $driver === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $tables = [
        "CREATE TABLE settings (
            k VARCHAR(100) NOT NULL PRIMARY KEY,
            v TEXT
        ){ENGINE}",

        "CREATE TABLE admins (
            id {PK},
            name VARCHAR(150) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE categories (
            id {PK},
            parent_id INT NULL,
            name VARCHAR(150) NOT NULL,
            name_bn VARCHAR(150) NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            description TEXT NULL,
            image VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active INT NOT NULL DEFAULT 1,
            coming_soon INT NOT NULL DEFAULT 0,
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE products (
            id {PK},
            category_id INT NOT NULL,
            name VARCHAR(200) NOT NULL,
            name_bn VARCHAR(200) NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            sku VARCHAR(64) NULL,
            description TEXT NULL,
            description_bn TEXT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            sale_price DECIMAL(10,2) NULL,
            quantity INT NOT NULL DEFAULT 0,
            weight DECIMAL(8,3) NOT NULL DEFAULT 0,
            image VARCHAR(255) NULL,
            is_featured INT NOT NULL DEFAULT 0,
            is_active INT NOT NULL DEFAULT 1,
            created_at VARCHAR(19) NOT NULL,
            updated_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE product_images (
            id {PK},
            product_id INT NOT NULL,
            path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0
        ){ENGINE}",

        "CREATE TABLE customers (
            id {PK},
            name VARCHAR(150) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            phone VARCHAR(30) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            zone VARCHAR(20) NULL,
            marketing_opt_in INT NOT NULL DEFAULT 1,
            created_at VARCHAR(19) NOT NULL,
            last_order_at VARCHAR(19) NULL
        ){ENGINE}",

        "CREATE TABLE password_resets (
            id {PK},
            user_type VARCHAR(10) NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at VARCHAR(19) NOT NULL,
            used INT NOT NULL DEFAULT 0
        ){ENGINE}",

        "CREATE TABLE orders (
            id {PK},
            serial VARCHAR(20) NOT NULL UNIQUE,
            customer_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(191) NOT NULL,
            address TEXT NOT NULL,
            city VARCHAR(100) NULL,
            zone VARCHAR(20) NOT NULL,
            note TEXT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            delivery_charge DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            total_weight DECIMAL(8,3) NOT NULL DEFAULT 0,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'en',
            created_at VARCHAR(19) NOT NULL,
            updated_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE order_items (
            id {PK},
            order_id INT NOT NULL,
            product_id INT NULL,
            name VARCHAR(200) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            weight DECIMAL(8,3) NOT NULL DEFAULT 0,
            line_total DECIMAL(10,2) NOT NULL
        ){ENGINE}",

        "CREATE TABLE order_status_history (
            id {PK},
            order_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            note TEXT NULL,
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE delivery_rates (
            id {PK},
            zone VARCHAR(20) NOT NULL,
            min_kg DECIMAL(8,3) NOT NULL,
            max_kg DECIMAL(8,3) NOT NULL,
            charge DECIMAL(10,2) NOT NULL
        ){ENGINE}",

        "CREATE TABLE campaigns (
            id {PK},
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            product_ids TEXT NULL,
            audience VARCHAR(20) NOT NULL DEFAULT 'opted_in',
            total INT NOT NULL DEFAULT 0,
            sent INT NOT NULL DEFAULT 0,
            failed INT NOT NULL DEFAULT 0,
            last_customer_id INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE stock_log (
            id {PK},
            product_id INT NOT NULL,
            change_qty INT NOT NULL,
            qty_after INT NOT NULL,
            reason VARCHAR(100) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",

        "CREATE TABLE login_attempts (
            id {PK},
            ip VARCHAR(64) NOT NULL,
            kind VARCHAR(20) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        ){ENGINE}",
    ];

    $indexes = [
        "CREATE INDEX idx_cat_parent ON categories (parent_id)",
        "CREATE INDEX idx_prod_cat ON products (category_id)",
        "CREATE INDEX idx_img_prod ON product_images (product_id)",
        "CREATE INDEX idx_order_customer ON orders (customer_id)",
        "CREATE INDEX idx_order_status ON orders (status)",
        "CREATE INDEX idx_order_created ON orders (created_at)",
        "CREATE INDEX idx_item_order ON order_items (order_id)",
        "CREATE INDEX idx_hist_order ON order_status_history (order_id)",
        "CREATE INDEX idx_stock_prod ON stock_log (product_id)",
        "CREATE INDEX idx_login_ip ON login_attempts (ip, kind)",
    ];

    $out = [];
    foreach ($tables as $sql) {
        $out[] = str_replace(['{PK}', '{ENGINE}'], [$pk, $engine], $sql);
    }
    return array_merge($out, $indexes);
}
