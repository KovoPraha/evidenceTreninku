<?php
declare(strict_types=1);

$shopOrderColumnExists = static function (PDO $pdo, string $column): bool {
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

return [
    'id' => '20260804010000_shop_order_fulfillment',
    'up' => static function (PDO $pdo) use ($shopOrderColumnExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['cancelled_at','ready_at','completed_at'] as $column) {
            if ($shopOrderColumnExists($pdo, $column)) continue;
            $pdo->exec(
                'ALTER TABLE shop_orders ADD COLUMN ' . $column
                . ($mysql ? ' DATETIME NULL' : ' TEXT NULL')
            );
        }
    },
    'verify' => static function (PDO $pdo) use ($shopOrderColumnExists): bool {
        foreach (['cancelled_at','ready_at','completed_at'] as $column) {
            if (!$shopOrderColumnExists($pdo, $column)) return false;
        }
        return true;
    },
];
