<?php
declare(strict_types=1);

$catalogAdminTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

/** @return array<string,mixed>|null */
$catalogAdminColumn = static function (PDO $pdo, string $table, string $column): ?array {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table,$column]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return $row;
    }
    return null;
};

$catalogAdminInventoryReady = static function (PDO $pdo) use ($catalogAdminColumn): bool {
    foreach (['actor_type','actor_id','reason'] as $column) {
        if ($catalogAdminColumn($pdo, 'shop_inventory_movements', $column) === null) return false;
    }
    foreach (['order_id','order_item_id'] as $column) {
        $definition = $catalogAdminColumn($pdo, 'shop_inventory_movements', $column);
        if ($definition === null) return false;
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            if (($definition['IS_NULLABLE'] ?? null) !== 'YES') return false;
        } elseif ((int)($definition['notnull'] ?? 1) !== 0) return false;
    }
    return true;
};

return [
    'id' => '20260817110000_shop_catalog_admin',
    'up' => static function (PDO $pdo) use (
        $catalogAdminTableExists,
        $catalogAdminColumn,
        $catalogAdminInventoryReady
    ): void {
        foreach (['shop_products','shop_variants','shop_inventory_movements','treneri'] as $required) {
            if (!$catalogAdminTableExists($pdo, $required)) {
                throw new RuntimeException('Required catalog admin table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$catalogAdminInventoryReady($pdo)) {
            if ($mysql) {
                $clauses = [];
                if ($catalogAdminColumn($pdo, 'shop_inventory_movements', 'actor_type') === null) {
                    $clauses[] = 'ADD COLUMN actor_type VARCHAR(24) NULL AFTER movement_type';
                }
                if ($catalogAdminColumn($pdo, 'shop_inventory_movements', 'actor_id') === null) {
                    $clauses[] = 'ADD COLUMN actor_id BIGINT NULL AFTER actor_type';
                }
                if ($catalogAdminColumn($pdo, 'shop_inventory_movements', 'reason') === null) {
                    $clauses[] = 'ADD COLUMN reason TEXT NULL AFTER actor_id';
                }
                $order = $catalogAdminColumn($pdo, 'shop_inventory_movements', 'order_id');
                if (($order['IS_NULLABLE'] ?? null) !== 'YES') {
                    $clauses[] = 'MODIFY COLUMN order_id BIGINT UNSIGNED NULL';
                }
                $item = $catalogAdminColumn($pdo, 'shop_inventory_movements', 'order_item_id');
                if (($item['IS_NULLABLE'] ?? null) !== 'YES') {
                    $clauses[] = 'MODIFY COLUMN order_item_id BIGINT UNSIGNED NULL';
                }
                if ($clauses !== []) $pdo->exec('ALTER TABLE shop_inventory_movements ' . implode(', ', $clauses));
            } else {
                $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1;
                $pdo->exec('PRAGMA foreign_keys=OFF');
                $pdo->beginTransaction();
                try {
                    $pdo->exec('DROP TABLE IF EXISTS shop_inventory_movements_r4_next');
                    $pdo->exec(<<<'SQL'
                        CREATE TABLE shop_inventory_movements_r4_next (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            variant_id INTEGER NOT NULL,
                            order_id INTEGER NULL,
                            order_item_id INTEGER NULL,
                            movement_type TEXT NOT NULL,
                            actor_type TEXT NULL,
                            actor_id INTEGER NULL,
                            reason TEXT NULL,
                            quantity_delta_decimal TEXT NOT NULL,
                            stock_after_decimal TEXT NOT NULL,
                            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE(order_item_id,movement_type),
                            FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                            FOREIGN KEY(order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                            FOREIGN KEY(order_item_id) REFERENCES shop_order_items(id) ON DELETE RESTRICT
                        )
                        SQL);
                    $actorType = $catalogAdminColumn($pdo, 'shop_inventory_movements', 'actor_type') === null ? 'NULL' : 'actor_type';
                    $actorId = $catalogAdminColumn($pdo, 'shop_inventory_movements', 'actor_id') === null ? 'NULL' : 'actor_id';
                    $reason = $catalogAdminColumn($pdo, 'shop_inventory_movements', 'reason') === null ? 'NULL' : 'reason';
                    $pdo->exec(
                        'INSERT INTO shop_inventory_movements_r4_next '
                        . '(id,variant_id,order_id,order_item_id,movement_type,actor_type,actor_id,reason,'
                        . 'quantity_delta_decimal,stock_after_decimal,created_at) '
                        . 'SELECT id,variant_id,order_id,order_item_id,movement_type,' . $actorType . ','
                        . $actorId . ',' . $reason . ',quantity_delta_decimal,stock_after_decimal,created_at '
                        . 'FROM shop_inventory_movements'
                    );
                    $pdo->exec('DROP TABLE shop_inventory_movements');
                    $pdo->exec('ALTER TABLE shop_inventory_movements_r4_next RENAME TO shop_inventory_movements');
                    $pdo->exec('CREATE INDEX idx_shop_inventory_variant ON shop_inventory_movements(variant_id,id)');
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $exception;
                } finally {
                    $pdo->exec('PRAGMA foreign_keys=' . ($foreignKeys ? 'ON' : 'OFF'));
                }
            }
        }
        if (!$catalogAdminTableExists($pdo, 'shop_catalog_admin_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_catalog_admin_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id BIGINT NOT NULL,
                    action VARCHAR(48) NOT NULL,
                    before_json LONGTEXT NULL,
                    after_json LONGTEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_shop_catalog_admin_product (product_id,id),
                    KEY idx_shop_catalog_admin_variant (variant_id,id),
                    CONSTRAINT fk_shop_catalog_admin_product FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_catalog_admin_variant FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_admin_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    variant_id INTEGER NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    before_json TEXT NULL,
                    after_json TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                    FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use (
        $catalogAdminTableExists,
        $catalogAdminColumn,
        $catalogAdminInventoryReady
    ): bool {
        if (!$catalogAdminTableExists($pdo, 'shop_catalog_admin_events') || !$catalogAdminInventoryReady($pdo)) {
            return false;
        }
        foreach (['product_id','variant_id','actor_type','actor_id','action','before_json','after_json','reason','created_at'] as $column) {
            if ($catalogAdminColumn($pdo, 'shop_catalog_admin_events', $column) === null) return false;
        }
        return true;
    },
];
