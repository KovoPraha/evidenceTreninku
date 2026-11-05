<?php

declare(strict_types=1);

return [
    'id' => '20261105170000_bar_stock',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigint = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_stock_operations (
            id $id, token VARCHAR(64) NOT NULL UNIQUE, request_sha256 VARCHAR(64) NOT NULL,
            product_id $bigint NOT NULL, actor_id INT NOT NULL, kind VARCHAR(40) NOT NULL,
            reason VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_stock_movements (
            id $id, operation_id $bigint NOT NULL, product_id $bigint NOT NULL, location_code VARCHAR(10) NOT NULL,
            quantity_delta INT NOT NULL, quantity_after INT NOT NULL, purchase_minor BIGINT NULL,
            product_name VARCHAR(255) NOT NULL,
            FOREIGN KEY(operation_id) REFERENCES kis_bar_stock_operations(id) ON DELETE RESTRICT,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT
        )$suffix");
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT id,token,request_sha256,product_id,actor_id,kind,reason FROM kis_bar_stock_operations WHERE 1=0');
        $pdo->query('SELECT id,operation_id,product_id,location_code,quantity_delta,quantity_after,purchase_minor,product_name FROM kis_bar_stock_movements WHERE 1=0');
        return true;
    },
];
