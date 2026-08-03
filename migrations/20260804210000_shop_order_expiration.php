<?php
declare(strict_types=1);

$columnExists = static function (PDO $pdo, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME='shop_orders' AND COLUMN_NAME=? LIMIT 1"
        );
        $statement->execute([$column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(shop_orders)')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if (($definition['name'] ?? null) === $column) return true;
    }
    return false;
};

$indexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() '
            . "AND TABLE_NAME='shop_orders' AND INDEX_NAME=? LIMIT 1"
        );
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804210000_shop_order_expiration',
    'up' => static function (PDO $pdo) use ($columnExists, $indexExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['payment_expires_at', 'expired_at'] as $column) {
            if ($columnExists($pdo, $column)) continue;
            $pdo->exec('ALTER TABLE shop_orders ADD COLUMN ' . $column . ($mysql ? ' DATETIME NULL' : ' TEXT NULL'));
        }
        // Existing payment due dates are immutable snapshots suitable for the first backfill.
        $pdo->exec(
            "UPDATE shop_orders SET payment_expires_at=(SELECT p.due_at FROM payments p "
            . "WHERE p.payable_type='shop_order' AND p.payable_id=shop_orders.id LIMIT 1) "
            . 'WHERE payment_expires_at IS NULL'
        );
        if (!$indexExists($pdo, 'idx_shop_order_expiration')) {
            $pdo->exec('CREATE INDEX idx_shop_order_expiration ON shop_orders(status,payment_status,payment_expires_at,id)');
        }
    },
    'verify' => static function (PDO $pdo) use ($columnExists, $indexExists): bool {
        if (!$columnExists($pdo, 'payment_expires_at') || !$columnExists($pdo, 'expired_at')
            || !$indexExists($pdo, 'idx_shop_order_expiration')) return false;
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id "
            . 'WHERE o.payment_expires_at IS NULL AND p.due_at IS NOT NULL'
        )->fetchColumn() === 0;
    },
];
