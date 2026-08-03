<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for public velodrome shop migration.');
};

return [
    'id' => '20260804200000_public_velodrome_shop',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        foreach (['shop_carts', 'shop_orders', 'individualni_lekce', 'sportovci', 'verejne_rezervace'] as $required) {
            if (!$tableExists($pdo, $required)) {
                throw new RuntimeException('Required table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'public_velodrome_cart_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_velodrome_cart_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    cart_id BIGINT UNSIGNED NOT NULL,
                    lesson_id INT NOT NULL,
                    beneficiary_sportovec_id INT NOT NULL,
                    note VARCHAR(1000) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_public_velo_cart_slot_person (cart_id,lesson_id,beneficiary_sportovec_id),
                    KEY idx_public_velo_cart_lesson (lesson_id,id),
                    CONSTRAINT fk_public_velo_cart_cart FOREIGN KEY (cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_velo_cart_lesson FOREIGN KEY (lesson_id) REFERENCES individualni_lekce(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_velo_cart_person FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_velodrome_cart_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cart_id INTEGER NOT NULL,
                    lesson_id INTEGER NOT NULL,
                    beneficiary_sportovec_id INTEGER NOT NULL,
                    note TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(cart_id,lesson_id,beneficiary_sportovec_id),
                    FOREIGN KEY (cart_id) REFERENCES shop_carts(id) ON DELETE RESTRICT,
                    FOREIGN KEY (lesson_id) REFERENCES individualni_lekce(id) ON DELETE RESTRICT,
                    FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'public_velodrome_order_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_velodrome_order_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    order_id BIGINT UNSIGNED NOT NULL,
                    reservation_id INT NOT NULL,
                    lesson_id INT NOT NULL,
                    beneficiary_sportovec_id INT NOT NULL,
                    lesson_name_snapshot VARCHAR(255) NOT NULL,
                    lesson_date_snapshot DATE NOT NULL,
                    starts_at_snapshot TIME NOT NULL,
                    ends_at_snapshot TIME NOT NULL,
                    exclusive_snapshot TINYINT(1) NOT NULL,
                    note_snapshot VARCHAR(1000) NULL,
                    quantity INT UNSIGNED NOT NULL DEFAULT 1,
                    unit_amount_minor BIGINT UNSIGNED NOT NULL,
                    line_amount_minor BIGINT UNSIGNED NOT NULL,
                    currency CHAR(3) NOT NULL DEFAULT 'CZK',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_public_velo_order_slot_person (order_id,lesson_id,beneficiary_sportovec_id),
                    UNIQUE KEY uq_public_velo_order_reservation (reservation_id),
                    KEY idx_public_velo_order_lesson (lesson_id,id),
                    CONSTRAINT fk_public_velo_order_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_velo_order_reservation FOREIGN KEY (reservation_id) REFERENCES verejne_rezervace(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_velo_order_lesson FOREIGN KEY (lesson_id) REFERENCES individualni_lekce(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_velo_order_person FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_velodrome_order_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id INTEGER NOT NULL,
                    reservation_id INTEGER NOT NULL UNIQUE,
                    lesson_id INTEGER NOT NULL,
                    beneficiary_sportovec_id INTEGER NOT NULL,
                    lesson_name_snapshot TEXT NOT NULL,
                    lesson_date_snapshot TEXT NOT NULL,
                    starts_at_snapshot TEXT NOT NULL,
                    ends_at_snapshot TEXT NOT NULL,
                    exclusive_snapshot INTEGER NOT NULL,
                    note_snapshot TEXT NULL,
                    quantity INTEGER NOT NULL DEFAULT 1,
                    unit_amount_minor INTEGER NOT NULL,
                    line_amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL DEFAULT 'CZK',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(order_id,lesson_id,beneficiary_sportovec_id),
                    FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT,
                    FOREIGN KEY (reservation_id) REFERENCES verejne_rezervace(id) ON DELETE RESTRICT,
                    FOREIGN KEY (lesson_id) REFERENCES individualni_lekce(id) ON DELETE RESTRICT,
                    FOREIGN KEY (beneficiary_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static fn(PDO $pdo): bool => $tableExists($pdo, 'public_velodrome_cart_items')
        && $tableExists($pdo, 'public_velodrome_order_items'),
];
