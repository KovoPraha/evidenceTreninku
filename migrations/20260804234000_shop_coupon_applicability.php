<?php
declare(strict_types=1);

$couponScopeTableExists = static function (PDO $pdo, string $table): bool {
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

$couponScopeColumnExists = static function (PDO $pdo, string $table, string $column) use ($couponScopeTableExists): bool {
    if (!$couponScopeTableExists($pdo, $table)) return false;
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
    'id' => '20260804234000_shop_coupon_applicability',
    'up' => static function (PDO $pdo) use ($couponScopeTableExists, $couponScopeColumnExists): void {
        if (!$couponScopeTableExists($pdo, 'shop_coupons') || !$couponScopeTableExists($pdo, 'shop_coupon_redemptions')) {
            throw new RuntimeException('Coupon applicability requires the K4 coupon tables.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$couponScopeColumnExists($pdo, 'shop_coupons', 'applicability_mask')) {
            $pdo->exec('ALTER TABLE shop_coupons ADD COLUMN applicability_mask '
                . ($mysql ? 'SMALLINT UNSIGNED NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1'));
        }
        if (!$couponScopeColumnExists($pdo, 'shop_coupon_redemptions', 'eligible_subtotal_minor')) {
            $pdo->exec('ALTER TABLE shop_coupon_redemptions ADD COLUMN eligible_subtotal_minor '
                . ($mysql ? 'BIGINT UNSIGNED NULL' : 'INTEGER NULL'));
        }
        if (!$couponScopeColumnExists($pdo, 'shop_coupon_redemptions', 'applicability_mask_snapshot')) {
            $pdo->exec('ALTER TABLE shop_coupon_redemptions ADD COLUMN applicability_mask_snapshot '
                . ($mysql ? 'SMALLINT UNSIGNED NULL' : 'INTEGER NULL'));
        }
    },
    'verify' => static function (PDO $pdo) use ($couponScopeColumnExists): bool {
        return $couponScopeColumnExists($pdo, 'shop_coupons', 'applicability_mask')
            && $couponScopeColumnExists($pdo, 'shop_coupon_redemptions', 'eligible_subtotal_minor')
            && $couponScopeColumnExists($pdo, 'shop_coupon_redemptions', 'applicability_mask_snapshot');
    },
];
