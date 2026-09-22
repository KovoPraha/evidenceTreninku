<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    $sql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?";
    $statement = $pdo->prepare($sql);
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};
$columnInfo = static function (PDO $pdo, string $table, string $column): ?array {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT IS_NULLABLE AS nullable FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $statement->execute([$table, $column]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) {
            return ['nullable' => (int)$row['notnull'] === 0 ? 'YES' : 'NO'];
        }
    }
    return null;
};
$constraintExists = static function (PDO $pdo, string $table, string $constraint): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return false;
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
        . 'WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?'
    );
    $statement->execute([$table, $constraint]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260922140000_low_friction_storefront',
    'up' => static function (PDO $pdo) use ($tableExists, $columnInfo, $constraintExists): void {
        foreach (['shop_orders', 'shop_products', 'shop_variants', 'treneri'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Required storefront table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            if (($columnInfo($pdo, 'shop_orders', 'account_id')['nullable'] ?? '') !== 'YES') {
                if ($constraintExists($pdo, 'shop_orders', 'fk_shop_order_account')) {
                    $pdo->exec('ALTER TABLE shop_orders DROP FOREIGN KEY fk_shop_order_account');
                }
                $pdo->exec('ALTER TABLE shop_orders MODIFY account_id INT NULL');
                $pdo->exec('ALTER TABLE shop_orders ADD CONSTRAINT fk_shop_order_account FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT');
            }
            if (($columnInfo($pdo, 'shop_orders', 'source_cart_id')['nullable'] ?? '') !== 'YES') {
                if ($constraintExists($pdo, 'shop_orders', 'fk_shop_order_cart')) {
                    $pdo->exec('ALTER TABLE shop_orders DROP FOREIGN KEY fk_shop_order_cart');
                }
                $pdo->exec('ALTER TABLE shop_orders MODIFY source_cart_id BIGINT UNSIGNED NULL');
                $pdo->exec('ALTER TABLE shop_orders ADD CONSTRAINT fk_shop_order_cart FOREIGN KEY(source_cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT');
            }
            $columns = [
                'checkout_mode' => "VARCHAR(24) NOT NULL DEFAULT 'account' AFTER source_cart_id",
                'guest_access_token_hash' => 'CHAR(64) NULL AFTER checkout_mode',
                'customer_phone_snapshot' => 'VARCHAR(50) NULL AFTER customer_email_snapshot',
                'address_street_snapshot' => 'VARCHAR(200) NULL AFTER customer_phone_snapshot',
                'address_city_snapshot' => 'VARCHAR(100) NULL AFTER address_street_snapshot',
                'address_postcode_snapshot' => 'VARCHAR(20) NULL AFTER address_city_snapshot',
            ];
            foreach ($columns as $column => $definition) {
                if ($columnInfo($pdo, 'shop_orders', $column) === null) {
                    $pdo->exec('ALTER TABLE shop_orders ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
            $hasIndex = $pdo->query("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='shop_orders' AND INDEX_NAME='uq_shop_order_guest_token' LIMIT 1")->fetchColumn();
            if (!$hasIndex) $pdo->exec('ALTER TABLE shop_orders ADD UNIQUE KEY uq_shop_order_guest_token(guest_access_token_hash)');
        } else {
            if (($columnInfo($pdo, 'shop_orders', 'account_id')['nullable'] ?? '') !== 'YES'
                || ($columnInfo($pdo, 'shop_orders', 'source_cart_id')['nullable'] ?? '') !== 'YES'
            ) {
                if ($pdo->inTransaction()) throw new RuntimeException('SQLite změna objednávek vyžaduje stav mimo transakci.');
                $createSql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='shop_orders'")->fetchColumn();
                $nextSql = preg_replace('/^CREATE TABLE\s+shop_orders/i', 'CREATE TABLE shop_orders_guest_next', $createSql, 1);
                $nextSql = preg_replace('/account_id\s+INTEGER\s+NOT NULL/i', 'account_id INTEGER NULL', (string)$nextSql, 1);
                $nextSql = preg_replace('/source_cart_id\s+INTEGER\s+NOT NULL/i', 'source_cart_id INTEGER NULL', (string)$nextSql, 1);
                if ($nextSql === '' || !str_contains($nextSql, 'account_id INTEGER NULL') || !str_contains($nextSql, 'source_cart_id INTEGER NULL')) {
                    throw new RuntimeException('SQLite definici objednávek nelze bezpečně převést.');
                }
                $columns = array_map(static fn(array $row): string => (string)$row['name'], $pdo->query('PRAGMA table_info(shop_orders)')->fetchAll(PDO::FETCH_ASSOC));
                $quoted = implode(',', array_map(static fn(string $name): string => '"' . str_replace('"', '""', $name) . '"', $columns));
                $pdo->exec('PRAGMA foreign_keys=OFF');
                try {
                    $pdo->exec($nextSql);
                    $pdo->exec('INSERT INTO shop_orders_guest_next(' . $quoted . ') SELECT ' . $quoted . ' FROM shop_orders');
                    $pdo->exec('DROP TABLE shop_orders');
                    $pdo->exec('ALTER TABLE shop_orders_guest_next RENAME TO shop_orders');
                    if (in_array('payment_expires_at', $columns, true)) {
                        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_order_expiration ON shop_orders(status,payment_status,payment_expires_at,id)');
                    }
                } finally {
                    $pdo->exec('PRAGMA foreign_keys=ON');
                }
            }
            $columns = [
                'checkout_mode' => "TEXT NOT NULL DEFAULT 'account'",
                'guest_access_token_hash' => 'TEXT NULL',
                'customer_phone_snapshot' => 'TEXT NULL',
                'address_street_snapshot' => 'TEXT NULL',
                'address_city_snapshot' => 'TEXT NULL',
                'address_postcode_snapshot' => 'TEXT NULL',
            ];
            foreach ($columns as $column => $definition) {
                if ($columnInfo($pdo, 'shop_orders', $column) === null) {
                    $pdo->exec('ALTER TABLE shop_orders ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_shop_order_guest_token ON shop_orders(guest_access_token_hash)');
        }

        if (!$tableExists($pdo, 'shop_product_interests')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE shop_product_interests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                variant_id BIGINT UNSIGNED NULL,
                email VARCHAR(254) NOT NULL,
                email_normalized VARCHAR(254) NOT NULL,
                source_path VARCHAR(255) NOT NULL,
                consent_text_snapshot VARCHAR(500) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'new',
                contacted_at DATETIME NULL,
                closed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_shop_interest_queue(status,created_at,id),
                KEY idx_shop_interest_identity(product_id,email_normalized,status),
                CONSTRAINT fk_shop_interest_product FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                CONSTRAINT fk_shop_interest_variant FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE shop_product_interests(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                variant_id INTEGER NULL,
                email TEXT NOT NULL,
                email_normalized TEXT NOT NULL,
                source_path TEXT NOT NULL,
                consent_text_snapshot TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'new',
                contacted_at TEXT NULL,
                closed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
            )
            SQL);
        if (!$mysql) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_interest_queue ON shop_product_interests(status,created_at,id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_interest_identity ON shop_product_interests(product_id,email_normalized,status)');
        }
        if (!$tableExists($pdo, 'shop_product_interest_events')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE shop_product_interest_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                interest_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id INT NULL,
                action VARCHAR(32) NOT NULL,
                from_status VARCHAR(24) NULL,
                to_status VARCHAR(24) NOT NULL,
                note VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_shop_interest_event(interest_id,id),
                CONSTRAINT fk_shop_interest_event_interest FOREIGN KEY(interest_id) REFERENCES shop_product_interests(id) ON DELETE RESTRICT,
                CONSTRAINT fk_shop_interest_event_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE shop_product_interest_events(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                interest_id INTEGER NOT NULL,
                actor_trainer_id INTEGER NULL,
                action TEXT NOT NULL,
                from_status TEXT NULL,
                to_status TEXT NOT NULL,
                note TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(interest_id) REFERENCES shop_product_interests(id) ON DELETE RESTRICT,
                FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        if (!$mysql) $pdo->exec('CREATE INDEX IF NOT EXISTS idx_shop_interest_event ON shop_product_interest_events(interest_id,id)');
    },
    'verify' => static function (PDO $pdo) use ($tableExists, $columnInfo): bool {
        foreach (['checkout_mode','guest_access_token_hash','customer_phone_snapshot','address_street_snapshot','address_city_snapshot','address_postcode_snapshot'] as $column) {
            if ($columnInfo($pdo, 'shop_orders', $column) === null) return false;
        }
        return ($columnInfo($pdo, 'shop_orders', 'account_id')['nullable'] ?? '') === 'YES'
            && ($columnInfo($pdo, 'shop_orders', 'source_cart_id')['nullable'] ?? '') === 'YES'
            && $tableExists($pdo, 'shop_product_interests')
            && $tableExists($pdo, 'shop_product_interest_events');
    },
];
