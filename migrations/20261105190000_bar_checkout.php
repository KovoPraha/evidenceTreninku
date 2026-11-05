<?php

declare(strict_types=1);

return [
    'id' => '20261105190000_bar_checkout',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigint = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_settings (
            id INT NOT NULL PRIMARY KEY, cup_deposit_minor BIGINT NOT NULL DEFAULT 0, lock_version INT NOT NULL DEFAULT 1
        )$suffix");
        $insert = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
        $pdo->exec("$insert INTO kis_bar_settings(id) VALUES(1)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_requests (
            token VARCHAR(64) NOT NULL PRIMARY KEY, actor_id INT NOT NULL, operation VARCHAR(40) NOT NULL,
            request_sha256 VARCHAR(64) NOT NULL, result_json TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_cashboxes (
            id $id, owner_id INT NOT NULL, active_owner_id INT NULL UNIQUE, opening_minor BIGINT NOT NULL,
            opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, closed_at DATETIME NULL, lock_version INT NOT NULL DEFAULT 1,
            expected_minor BIGINT NULL, counted_minor BIGINT NULL, difference_minor BIGINT NULL,
            opening_reason TEXT NOT NULL, closing_reason TEXT NULL, closing_snapshot_json TEXT NULL,
            FOREIGN KEY(owner_id) REFERENCES treneri(id) ON DELETE RESTRICT,
            FOREIGN KEY(active_owner_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_cash_movements (
            id $id, cashbox_id $bigint NOT NULL, actor_id INT NOT NULL, kind VARCHAR(30) NOT NULL, amount_minor BIGINT NOT NULL,
            source_type VARCHAR(30) NOT NULL, source_id $bigint NULL, reason TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(source_type,source_id),
            FOREIGN KEY(cashbox_id) REFERENCES kis_bar_cashboxes(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_sales (
            id $id, cashbox_id $bigint NULL, actor_id INT NOT NULL, kind VARCHAR(20) NOT NULL, currency VARCHAR(3) NOT NULL,
            goods_gross_minor BIGINT NOT NULL, discount_percent INT NOT NULL, discount_minor BIGINT NOT NULL,
            goods_net_minor BIGINT NOT NULL, cup_quantity INT NOT NULL, cup_unit_minor BIGINT NOT NULL,
            deposit_minor BIGINT NOT NULL, total_minor BIGINT NOT NULL, reason TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(cashbox_id) REFERENCES kis_bar_cashboxes(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_sale_lines (
            id $id, sale_id $bigint NOT NULL, product_id $bigint NOT NULL, product_name VARCHAR(255) NOT NULL, description TEXT NOT NULL,
            quantity INT NOT NULL, unit_price_minor BIGINT NOT NULL, gross_minor BIGINT NOT NULL, net_minor BIGINT NOT NULL,
            purchase_unit_minor BIGINT NULL,
            FOREIGN KEY(sale_id) REFERENCES kis_bar_sales(id) ON DELETE RESTRICT,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_sale_components (
            id $id, sale_id $bigint NOT NULL, product_id $bigint NOT NULL, product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL, location_code VARCHAR(10) NOT NULL, purchase_unit_minor BIGINT NULL,
            stock_operation_id $bigint NOT NULL, UNIQUE(sale_id,product_id),
            FOREIGN KEY(sale_id) REFERENCES kis_bar_sales(id) ON DELETE RESTRICT,
            FOREIGN KEY(product_id) REFERENCES kis_bar_products(id) ON DELETE RESTRICT,
            FOREIGN KEY(stock_operation_id) REFERENCES kis_bar_stock_operations(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_sale_payments (
            sale_id $bigint NOT NULL PRIMARY KEY, method VARCHAR(10) NOT NULL, status VARCHAR(15) NOT NULL,
            amount_minor BIGINT NOT NULL, verification_kind VARCHAR(20) NULL, confirmation_reference VARCHAR(255) NULL,
            confirmed_by INT NULL, confirmed_at DATETIME NULL, UNIQUE(method,confirmation_reference),
            FOREIGN KEY(sale_id) REFERENCES kis_bar_sales(id) ON DELETE RESTRICT,
            FOREIGN KEY(confirmed_by) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_cup_returns (
            id $id, sale_id $bigint NOT NULL, cashbox_id $bigint NOT NULL, actor_id INT NOT NULL,
            quantity INT NOT NULL, unit_minor BIGINT NOT NULL, total_minor BIGINT NOT NULL, reason TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(sale_id) REFERENCES kis_bar_sales(id) ON DELETE RESTRICT,
            FOREIGN KEY(cashbox_id) REFERENCES kis_bar_cashboxes(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT
        )$suffix");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kis_bar_voids (
            id $id, sale_id $bigint NOT NULL UNIQUE, actor_id INT NOT NULL, refund_cashbox_id $bigint NULL,
            goods_minor BIGINT NOT NULL, deposit_minor BIGINT NOT NULL, cup_quantity INT NOT NULL,
            reversed_minor BIGINT NOT NULL, refunded_minor BIGINT NOT NULL, refund_method VARCHAR(15) NOT NULL,
            refund_reference VARCHAR(255) NULL, reason TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(refund_method,refund_reference),
            FOREIGN KEY(sale_id) REFERENCES kis_bar_sales(id) ON DELETE RESTRICT,
            FOREIGN KEY(actor_id) REFERENCES treneri(id) ON DELETE RESTRICT,
            FOREIGN KEY(refund_cashbox_id) REFERENCES kis_bar_cashboxes(id) ON DELETE RESTRICT
        )$suffix");
    },
    'verify' => static function (PDO $pdo): bool {
        $columns = [
            'requests' => 'token,actor_id,operation,request_sha256,result_json',
            'cashboxes' => 'id,owner_id,active_owner_id,opening_minor,closed_at,lock_version,closing_snapshot_json',
            'cash_movements' => 'id,cashbox_id,actor_id,kind,amount_minor,source_type,source_id,reason',
            'sales' => 'id,cashbox_id,actor_id,kind,currency,goods_gross_minor,goods_net_minor,deposit_minor,total_minor',
            'sale_lines' => 'id,sale_id,product_id,product_name,quantity,unit_price_minor,net_minor,purchase_unit_minor',
            'sale_components' => 'id,sale_id,product_id,quantity,location_code,purchase_unit_minor,stock_operation_id',
            'sale_payments' => 'sale_id,method,status,amount_minor,verification_kind,confirmation_reference,confirmed_by',
            'cup_returns' => 'id,sale_id,cashbox_id,actor_id,quantity,unit_minor,total_minor',
            'voids' => 'id,sale_id,actor_id,refund_cashbox_id,reversed_minor,refunded_minor,refund_method',
        ];
        foreach ($columns as $table => $fields) {
            $pdo->query('SELECT ' . $fields . ' FROM kis_bar_' . $table . ' WHERE 1=0');
        }
        return (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_settings WHERE id=1 AND lock_version>=1 AND cup_deposit_minor>=0')->fetchColumn() === 1;
    },
];
