<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804230000_club_event_shop',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        foreach (['shop_carts','shop_orders','shop_variants','club_events','club_event_registrations','sportovci'] as $required) {
            if (!$tableExists($pdo, $required)) throw new RuntimeException('Required table is missing: '.$required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'club_event_cart_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_cart_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cart_id BIGINT UNSIGNED NOT NULL,
                    event_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    beneficiary_sportovec_id INT NOT NULL,
                    consent_version VARCHAR(64) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_cart_person (cart_id,event_id,beneficiary_sportovec_id),
                    CONSTRAINT fk_event_cart_cart FOREIGN KEY (cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_cart_event FOREIGN KEY (event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_cart_variant FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_cart_person FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_cart_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cart_id INTEGER NOT NULL,
                    event_id INTEGER NOT NULL,
                    variant_id INTEGER NOT NULL,
                    beneficiary_sportovec_id INTEGER NOT NULL,
                    consent_version TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(cart_id,event_id,beneficiary_sportovec_id),
                    FOREIGN KEY (cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT,
                    FOREIGN KEY (event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'club_event_order_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_order_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id BIGINT UNSIGNED NOT NULL,
                    registration_id BIGINT UNSIGNED NOT NULL,
                    event_id BIGINT UNSIGNED NOT NULL,
                    variant_id BIGINT UNSIGNED NOT NULL,
                    beneficiary_sportovec_id INT NOT NULL,
                    event_name_snapshot VARCHAR(255) NOT NULL,
                    sku_snapshot VARCHAR(191) NOT NULL,
                    consent_version_snapshot VARCHAR(64) NOT NULL,
                    consent_text_snapshot TEXT NOT NULL,
                    cancellation_policy_snapshot TEXT NOT NULL,
                    cancellation_deadline_snapshot DATETIME NOT NULL,
                    eligibility_team_ids_snapshot LONGTEXT NOT NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    unit_amount_minor BIGINT UNSIGNED NOT NULL,
                    line_amount_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_event_order_registration (registration_id),
                    UNIQUE KEY uq_event_order_person (order_id,event_id,beneficiary_sportovec_id),
                    CONSTRAINT fk_event_order_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_order_registration FOREIGN KEY (registration_id) REFERENCES club_event_registrations(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_order_event FOREIGN KEY (event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_order_variant FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_event_order_person FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    registration_id INTEGER NOT NULL UNIQUE,
                    event_id INTEGER NOT NULL,
                    variant_id INTEGER NOT NULL,
                    beneficiary_sportovec_id INTEGER NOT NULL,
                    event_name_snapshot TEXT NOT NULL,
                    sku_snapshot TEXT NOT NULL,
                    consent_version_snapshot TEXT NOT NULL,
                    consent_text_snapshot TEXT NOT NULL,
                    cancellation_policy_snapshot TEXT NOT NULL,
                    cancellation_deadline_snapshot TEXT NOT NULL,
                    eligibility_team_ids_snapshot TEXT NOT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    unit_amount_minor INTEGER NOT NULL,
                    line_amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(order_id,event_id,beneficiary_sportovec_id),
                    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    FOREIGN KEY (registration_id) REFERENCES club_event_registrations(id) ON DELETE RESTRICT,
                    FOREIGN KEY (event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                    FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static fn(PDO $pdo): bool => $tableExists($pdo, 'club_event_cart_items')
        && $tableExists($pdo, 'club_event_order_items'),
];
