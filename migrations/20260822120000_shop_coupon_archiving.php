<?php
declare(strict_types=1);

$couponArchiveTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$couponArchiveColumnExists = static function (PDO $pdo, string $table, string $column) use ($couponArchiveTableExists): bool {
    if (!$couponArchiveTableExists($pdo, $table)) return false;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if ((string)$definition['name'] === $column) return true;
        }
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
    );
    $statement->execute([$table, $column]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260822120000_shop_coupon_archiving',
    'up' => static function (PDO $pdo) use ($couponArchiveTableExists, $couponArchiveColumnExists): void {
        if (!$couponArchiveTableExists($pdo, 'shop_coupons')) {
            throw new RuntimeException('Coupon archiving requires the K4 coupon table.');
        }
        if (!$couponArchiveColumnExists($pdo, 'shop_coupons', 'archived_at')) {
            $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
            $pdo->exec('ALTER TABLE shop_coupons ADD COLUMN archived_at '
                . ($mysql ? 'DATETIME NULL AFTER active' : 'TEXT NULL'));
        }
    },
    'verify' => static function (PDO $pdo) use ($couponArchiveColumnExists): bool {
        return $couponArchiveColumnExists($pdo, 'shop_coupons', 'archived_at');
    },
];
