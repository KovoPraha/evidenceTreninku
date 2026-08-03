<?php
declare(strict_types=1);

$shopBeneficiaryTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$shopBeneficiaryColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
};

$shopBeneficiaryIndexExists = static function (PDO $pdo, string $table, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $statement->execute([$table, $index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=?");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

$shopBeneficiaryForeignKeyExists = static function (PDO $pdo, string $table, string $constraint): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return true;
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE=\'FOREIGN KEY\'');
    $statement->execute([$table, $constraint]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804120000_shop_item_beneficiaries',
    'up' => static function (PDO $pdo) use ($shopBeneficiaryTableExists, $shopBeneficiaryColumnExists, $shopBeneficiaryIndexExists, $shopBeneficiaryForeignKeyExists): void {
        foreach (['sportovci', 'shop_cart_items', 'shop_order_items'] as $required) {
            if (!$shopBeneficiaryTableExists($pdo, $required)) throw new RuntimeException('Required shop beneficiary table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach ([
            ['shop_cart_items', 'idx_shop_cart_item_beneficiary', 'fk_shop_cart_item_beneficiary'],
            ['shop_order_items', 'idx_shop_order_item_beneficiary', 'fk_shop_order_item_beneficiary'],
        ] as [$table, $index, $constraint]) {
            if (!$shopBeneficiaryColumnExists($pdo, $table, 'beneficiary_sportovec_id')) {
                $pdo->exec($mysql
                    ? "ALTER TABLE {$table} ADD COLUMN beneficiary_sportovec_id INT NULL"
                    : "ALTER TABLE {$table} ADD COLUMN beneficiary_sportovec_id INTEGER NULL REFERENCES sportovci(id) ON DELETE RESTRICT");
            }
            if (!$shopBeneficiaryIndexExists($pdo, $table, $index)) {
                $pdo->exec($mysql
                    ? "ALTER TABLE {$table} ADD INDEX {$index} (beneficiary_sportovec_id)"
                    : "CREATE INDEX {$index} ON {$table}(beneficiary_sportovec_id)");
            }
            if ($mysql && !$shopBeneficiaryForeignKeyExists($pdo, $table, $constraint)) {
                $pdo->exec("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT");
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($shopBeneficiaryColumnExists, $shopBeneficiaryIndexExists, $shopBeneficiaryForeignKeyExists): bool {
        foreach ([
            ['shop_cart_items', 'idx_shop_cart_item_beneficiary', 'fk_shop_cart_item_beneficiary'],
            ['shop_order_items', 'idx_shop_order_item_beneficiary', 'fk_shop_order_item_beneficiary'],
        ] as [$table, $index, $constraint]) {
            if (!$shopBeneficiaryColumnExists($pdo, $table, 'beneficiary_sportovec_id')
                || !$shopBeneficiaryIndexExists($pdo, $table, $index)
                || !$shopBeneficiaryForeignKeyExists($pdo, $table, $constraint)
            ) return false;
        }
        return true;
    },
];
