<?php
declare(strict_types=1);

$couponTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);return (bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$statement->execute([$table]);return (bool)$statement->fetchColumn();
};
$couponColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
        $statement->execute([$table,$column]);return (bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $definition)if(($definition['name']??null)===$column)return true;
    return false;
};

return [
    'id'=>'20260804050000_shop_coupons',
    'up'=>static function(PDO $pdo)use($couponTableExists,$couponColumnExists):void{
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$couponTableExists($pdo,'shop_coupons'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE shop_coupons (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                discount_type VARCHAR(16) NOT NULL,
                value_minor_or_basis_points INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'CZK',
                minimum_order_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                maximum_discount_minor BIGINT UNSIGNED NULL,
                usage_limit_total INT UNSIGNED NULL,
                usage_count INT UNSIGNED NOT NULL DEFAULT 0,
                valid_from DATETIME NULL,
                valid_until DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_shop_coupon_code (code),
                KEY idx_shop_coupon_active_validity (active,valid_from,valid_until),
                CONSTRAINT fk_shop_coupon_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE shop_coupons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                discount_type TEXT NOT NULL,
                value_minor_or_basis_points INTEGER NOT NULL,
                currency TEXT NOT NULL DEFAULT 'CZK',
                minimum_order_minor INTEGER NOT NULL DEFAULT 0,
                maximum_discount_minor INTEGER NULL,
                usage_limit_total INTEGER NULL,
                usage_count INTEGER NOT NULL DEFAULT 0,
                valid_from TEXT NULL,
                valid_until TEXT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_by_trainer_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        if(!$couponTableExists($pdo,'shop_coupon_events'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE shop_coupon_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                coupon_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id INT NOT NULL,
                action VARCHAR(32) NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NOT NULL,
                note VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_shop_coupon_event (coupon_id,id),
                CONSTRAINT fk_shop_coupon_event_coupon FOREIGN KEY (coupon_id) REFERENCES shop_coupons(id) ON DELETE RESTRICT,
                CONSTRAINT fk_shop_coupon_event_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE shop_coupon_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                coupon_id INTEGER NOT NULL,
                actor_trainer_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                before_json TEXT NULL,
                after_json TEXT NOT NULL,
                note TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (coupon_id) REFERENCES shop_coupons(id) ON DELETE RESTRICT,
                FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        if(!$couponTableExists($pdo,'shop_coupon_redemptions'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE shop_coupon_redemptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                coupon_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                account_id INT NOT NULL,
                code_snapshot VARCHAR(32) NOT NULL,
                discount_type_snapshot VARCHAR(16) NOT NULL,
                value_snapshot INT UNSIGNED NOT NULL,
                discount_minor BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_shop_coupon_redemption_order (order_id),
                KEY idx_shop_coupon_redemption_coupon (coupon_id,id),
                CONSTRAINT fk_shop_coupon_redemption_coupon FOREIGN KEY (coupon_id) REFERENCES shop_coupons(id) ON DELETE RESTRICT,
                CONSTRAINT fk_shop_coupon_redemption_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                CONSTRAINT fk_shop_coupon_redemption_account FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE shop_coupon_redemptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                coupon_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL UNIQUE,
                account_id INTEGER NOT NULL,
                code_snapshot TEXT NOT NULL,
                discount_type_snapshot TEXT NOT NULL,
                value_snapshot INTEGER NOT NULL,
                discount_minor INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (coupon_id) REFERENCES shop_coupons(id) ON DELETE RESTRICT,
                FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
            )
            SQL);
        if(!$couponColumnExists($pdo,'shop_carts','coupon_id'))$pdo->exec('ALTER TABLE shop_carts ADD COLUMN coupon_id '.($mysql?'BIGINT UNSIGNED NULL':'INTEGER NULL'));
    },
    'verify'=>static function(PDO $pdo)use($couponTableExists,$couponColumnExists):bool{
        foreach(['shop_coupons','shop_coupon_events','shop_coupon_redemptions']as$table)if(!$couponTableExists($pdo,$table))return false;
        return $couponColumnExists($pdo,'shop_carts','coupon_id');
    },
];
