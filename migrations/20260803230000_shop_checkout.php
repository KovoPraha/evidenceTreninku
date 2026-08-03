<?php
declare(strict_types=1);

$shopCheckoutTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
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

return [
    'id' => '20260803230000_shop_checkout',
    'up' => static function (PDO $pdo) use ($shopCheckoutTableExists): void {
        foreach (['shop_products','shop_variants','shop_product_publications','verejni_uzivatele','treneri'] as $required) {
            if (!$shopCheckoutTableExists($pdo, $required)) {
                throw new RuntimeException('Required checkout table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$shopCheckoutTableExists($pdo, 'shop_carts')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_carts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cart_key CHAR(32) NOT NULL,
                    account_id INT NOT NULL,
                    active_account_id INT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'active',
                    currency CHAR(3) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    converted_at DATETIME NULL,
                    UNIQUE KEY uq_shop_cart_key (cart_key),
                    UNIQUE KEY uq_shop_cart_active_account (active_account_id),
                    KEY idx_shop_cart_account (account_id,status,id),
                    CONSTRAINT fk_shop_cart_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_carts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cart_key TEXT NOT NULL UNIQUE,
                    account_id INTEGER NOT NULL,
                    active_account_id INTEGER NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'active',
                    currency TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    converted_at TEXT NULL,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'shop_cart_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_cart_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cart_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    quantity INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_cart_variant (cart_id,variant_id),
                    CONSTRAINT fk_shop_cart_item_cart FOREIGN KEY (cart_id)
                        REFERENCES shop_carts(id) ON DELETE CASCADE,
                    CONSTRAINT fk_shop_cart_item_variant FOREIGN KEY (variant_id)
                        REFERENCES shop_variants(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_cart_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cart_id INTEGER NOT NULL,
                    variant_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(cart_id,variant_id),
                    FOREIGN KEY (cart_id) REFERENCES shop_carts(id) ON DELETE CASCADE,
                    FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'shop_orders')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_orders (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    public_code CHAR(18) NOT NULL,
                    account_id INT NOT NULL,
                    source_cart_id BIGINT UNSIGNED NOT NULL,
                    idempotency_key_hash CHAR(64) NOT NULL,
                    status VARCHAR(24) NOT NULL,
                    payment_status VARCHAR(24) NOT NULL,
                    fulfillment_method VARCHAR(32) NOT NULL,
                    customer_name_snapshot VARCHAR(255) NOT NULL,
                    customer_email_snapshot VARCHAR(254) NOT NULL,
                    subtotal_minor BIGINT UNSIGNED NOT NULL,
                    discount_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    total_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    placed_at DATETIME NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_order_public_code (public_code),
                    UNIQUE KEY uq_shop_order_idempotency (idempotency_key_hash),
                    KEY idx_shop_order_account (account_id,created_at,id),
                    CONSTRAINT fk_shop_order_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_order_cart FOREIGN KEY (source_cart_id)
                        REFERENCES shop_carts(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    public_code TEXT NOT NULL UNIQUE,
                    account_id INTEGER NOT NULL,
                    source_cart_id INTEGER NOT NULL,
                    idempotency_key_hash TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL,
                    payment_status TEXT NOT NULL,
                    fulfillment_method TEXT NOT NULL,
                    customer_name_snapshot TEXT NOT NULL,
                    customer_email_snapshot TEXT NOT NULL,
                    subtotal_minor INTEGER NOT NULL,
                    discount_minor INTEGER NOT NULL DEFAULT 0,
                    total_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    placed_at TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY (source_cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'shop_order_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_order_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id BIGINT UNSIGNED NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    product_name_snapshot VARCHAR(255) NOT NULL,
                    sku_snapshot VARCHAR(64) NOT NULL,
                    attributes_json_snapshot LONGTEXT NOT NULL,
                    quantity INT UNSIGNED NOT NULL,
                    unit_amount_minor BIGINT UNSIGNED NOT NULL,
                    line_amount_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    includes_vat_snapshot TINYINT(1) NULL,
                    vat_rate_basis_points_snapshot INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_order_variant (order_id,variant_id),
                    CONSTRAINT fk_shop_order_item_order FOREIGN KEY (order_id)
                        REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_order_item_product FOREIGN KEY (product_id)
                        REFERENCES shop_products(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_order_item_variant FOREIGN KEY (variant_id)
                        REFERENCES shop_variants(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    variant_id INTEGER NOT NULL,
                    product_name_snapshot TEXT NOT NULL,
                    sku_snapshot TEXT NOT NULL,
                    attributes_json_snapshot TEXT NOT NULL,
                    quantity INTEGER NOT NULL,
                    unit_amount_minor INTEGER NOT NULL,
                    line_amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    includes_vat_snapshot INTEGER NULL,
                    vat_rate_basis_points_snapshot INTEGER NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(order_id,variant_id),
                    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                    FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'shop_order_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_order_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id BIGINT UNSIGNED NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id INT NULL,
                    action VARCHAR(64) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    note VARCHAR(1000) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_shop_order_event_order (order_id,id),
                    CONSTRAINT fk_shop_order_event_order FOREIGN KEY (order_id)
                        REFERENCES shop_orders(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_order_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'shop_inventory_movements')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE shop_inventory_movements (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    order_id BIGINT UNSIGNED NOT NULL,
                    order_item_id BIGINT UNSIGNED NOT NULL,
                    movement_type VARCHAR(32) NOT NULL,
                    quantity_delta_decimal DECIMAL(18,6) NOT NULL,
                    stock_after_decimal DECIMAL(18,6) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_inventory_order_item_type (order_item_id,movement_type),
                    KEY idx_shop_inventory_variant (variant_id,id),
                    CONSTRAINT fk_shop_inventory_variant FOREIGN KEY (variant_id)
                        REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_inventory_order FOREIGN KEY (order_id)
                        REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_inventory_order_item FOREIGN KEY (order_item_id)
                        REFERENCES shop_order_items(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_inventory_movements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    variant_id INTEGER NOT NULL,
                    order_id INTEGER NOT NULL,
                    order_item_id INTEGER NOT NULL,
                    movement_type TEXT NOT NULL,
                    quantity_delta_decimal TEXT NOT NULL,
                    stock_after_decimal TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(order_item_id,movement_type),
                    FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    FOREIGN KEY (order_item_id) REFERENCES shop_order_items(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$shopCheckoutTableExists($pdo, 'payments')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE payments (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    payable_type VARCHAR(32) NOT NULL,
                    payable_id BIGINT UNSIGNED NOT NULL,
                    method VARCHAR(32) NOT NULL,
                    status VARCHAR(24) NOT NULL,
                    amount_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    variable_symbol VARCHAR(10) NOT NULL,
                    iban_snapshot VARCHAR(34) NOT NULL,
                    bic_snapshot VARCHAR(11) NULL,
                    account_label_snapshot VARCHAR(255) NOT NULL,
                    spd_payload TEXT NOT NULL,
                    due_at DATETIME NOT NULL,
                    paid_at DATETIME NULL,
                    confirmed_by_trainer_id INT NULL,
                    confirmation_note VARCHAR(1000) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_payment_payable (payable_type,payable_id),
                    UNIQUE KEY uq_payment_variable_symbol (variable_symbol),
                    KEY idx_payment_status_due (status,due_at,id),
                    CONSTRAINT fk_payment_confirmed_by FOREIGN KEY (confirmed_by_trainer_id)
                        REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    payable_type TEXT NOT NULL,
                    payable_id INTEGER NOT NULL,
                    method TEXT NOT NULL,
                    status TEXT NOT NULL,
                    amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    variable_symbol TEXT NOT NULL UNIQUE,
                    iban_snapshot TEXT NOT NULL,
                    bic_snapshot TEXT NULL,
                    account_label_snapshot TEXT NOT NULL,
                    spd_payload TEXT NOT NULL,
                    due_at TEXT NOT NULL,
                    paid_at TEXT NULL,
                    confirmed_by_trainer_id INTEGER NULL,
                    confirmation_note TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(payable_type,payable_id),
                    FOREIGN KEY (confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($shopCheckoutTableExists): bool {
        foreach (['shop_carts','shop_cart_items','shop_orders','shop_order_items','shop_order_events','shop_inventory_movements','payments'] as $table) {
            if (!$shopCheckoutTableExists($pdo,$table)) return false;
        }
        return true;
    },
];
