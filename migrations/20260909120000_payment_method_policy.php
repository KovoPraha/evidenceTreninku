<?php
declare(strict_types=1);

$paymentPolicyTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$paymentPolicyColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if (($definition['name'] ?? null) === $column) return true;
    }
    return false;
};

return [
    'id' => '20260909120000_payment_method_policy',
    'up' => static function (PDO $pdo) use ($paymentPolicyTableExists,$paymentPolicyColumnExists): void {
        foreach (['shop_products','payments','individualni_lekce'] as $required) {
            if (!$paymentPolicyTableExists($pdo,$required)) throw new RuntimeException('Required payment policy table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$paymentPolicyColumnExists($pdo,'shop_products','payment_method_policy')) {
            $pdo->exec($mysql
                ? "ALTER TABLE shop_products ADD COLUMN payment_method_policy VARCHAR(32) NOT NULL DEFAULT 'bank_transfer' AFTER catalog_status"
                : "ALTER TABLE shop_products ADD COLUMN payment_method_policy TEXT NOT NULL DEFAULT 'bank_transfer'");
        }
        if (!$paymentPolicyColumnExists($pdo,'individualni_lekce','payment_method_policy')) {
            $pdo->exec($mysql
                ? "ALTER TABLE individualni_lekce ADD COLUMN payment_method_policy VARCHAR(32) NOT NULL DEFAULT 'bank_transfer' AFTER public_exclusive_booking"
                : "ALTER TABLE individualni_lekce ADD COLUMN payment_method_policy TEXT NOT NULL DEFAULT 'bank_transfer'");
        }
        if (!$paymentPolicyColumnExists($pdo,'payments','accepted_payment_methods')) {
            $pdo->exec($mysql
                ? "ALTER TABLE payments ADD COLUMN accepted_payment_methods VARCHAR(32) NOT NULL DEFAULT 'bank_transfer' AFTER method"
                : "ALTER TABLE payments ADD COLUMN accepted_payment_methods TEXT NOT NULL DEFAULT 'bank_transfer'");
        }
    },
    'verify' => static function (PDO $pdo) use ($paymentPolicyColumnExists): bool {
        foreach ([
            ['shop_products','payment_method_policy'],
            ['individualni_lekce','payment_method_policy'],
            ['payments','accepted_payment_methods'],
        ] as [$table,$column]) {
            if (!$paymentPolicyColumnExists($pdo,$table,$column)) return false;
            $invalid = (int)$pdo->query(
                "SELECT COUNT(*) FROM $table WHERE $column NOT IN ('bank_transfer','bank_transfer_sumup')"
            )->fetchColumn();
            if ($invalid !== 0) return false;
        }
        return true;
    },
];
