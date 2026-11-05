<?php

declare(strict_types=1);

return [
    'id' => '20261105150000_bar_foundation',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigint = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_categories (
            id $id, source_id INT NULL UNIQUE, name VARCHAR(255) NOT NULL, sort_order INT NOT NULL DEFAULT 0, color_index INT NOT NULL DEFAULT 0
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_products (
            id $id, source_id INT NULL UNIQUE, category_id $bigint NOT NULL,
            name VARCHAR(255) NOT NULL, description TEXT NOT NULL,
            price_minor BIGINT NOT NULL, purchase_minor BIGINT NULL,
            image_name VARCHAR(255) NULL, sort_order INT NOT NULL DEFAULT 0,
            stock_mode VARCHAR(20) NOT NULL, stock_stall INT NOT NULL DEFAULT 0, stock_office INT NOT NULL DEFAULT 0,
            minimum_stock INT NOT NULL DEFAULT 0, package_name VARCHAR(100) NOT NULL, package_yield INT NOT NULL DEFAULT 1,
            on_terminal INT NOT NULL DEFAULT 1, sold_out INT NOT NULL DEFAULT 0,
            favorite INT NOT NULL DEFAULT 0, archived INT NOT NULL DEFAULT 0,
            lock_version INT NOT NULL DEFAULT 1,
            FOREIGN KEY(category_id) REFERENCES kis_bar_categories(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_recipes (
            product_id $bigint NOT NULL, ingredient_id $bigint NOT NULL, quantity INT NOT NULL,
            PRIMARY KEY(product_id,ingredient_id),
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT,
            FOREIGN KEY(ingredient_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_permissions (
            trainer_id INT NOT NULL, permission_code VARCHAR(40) NOT NULL,
            granted_by INT NOT NULL, reason VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(trainer_id,permission_code),
            FOREIGN KEY(trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
            FOREIGN KEY(granted_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_events (
            id $id, actor_id INT NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_id $bigint NOT NULL,
            action VARCHAR(40) NOT NULL, detail_json TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $insert = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $pdo->exec("$insert INTO staff_positions(code,label,sort_order,active) VALUES
            ('bar_operator','Obsluha baru',76,1),('bar_admin','Administrátor baru',77,1)");
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT id,source_id,name,sort_order,color_index FROM kis_bar_categories WHERE 1=0');
        $pdo->query('SELECT id,source_id,category_id,name,description,price_minor,purchase_minor,image_name,sort_order,'
            . 'stock_mode,stock_stall,stock_office,minimum_stock,package_name,package_yield,on_terminal,sold_out,favorite,archived,lock_version '
            . 'FROM kis_bar_products WHERE 1=0');
        $pdo->query('SELECT product_id,ingredient_id,quantity FROM kis_bar_recipes WHERE 1=0');
        $pdo->query('SELECT trainer_id,permission_code,granted_by,reason FROM kis_bar_permissions WHERE 1=0');
        $pdo->query('SELECT id,actor_id,entity_type,entity_id,action,detail_json FROM kis_bar_events WHERE 1=0');
        return (int)$pdo->query("SELECT COUNT(*) FROM staff_positions WHERE code IN ('bar_operator','bar_admin') AND active=1")->fetchColumn() === 2;
    },
];
