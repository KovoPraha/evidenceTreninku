<?php
declare(strict_types=1);

$publicationTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for shop publication migration.');
};

return [
    'id' => '20260803090000_shop_product_publication',
    'up' => static function (PDO $pdo) use ($publicationTableExists): void {
        foreach (['shop_products', 'shop_variants', 'treneri'] as $required) {
            if (!$publicationTableExists($pdo, $required)) {
                throw new RuntimeException('Required publication table is missing: ' . $required);
            }
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$publicationTableExists($pdo, 'shop_product_publications')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_product_publications (
                    product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    status VARCHAR(24) NOT NULL DEFAULT 'draft',
                    public_name VARCHAR(255) NOT NULL,
                    public_summary TEXT NOT NULL,
                    decision_note TEXT NOT NULL,
                    activated_by_trainer_id INT NULL,
                    activated_at DATETIME NULL,
                    deactivated_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_shop_publication_status (status, activated_at),
                    CONSTRAINT fk_shop_publication_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_publication_actor FOREIGN KEY (activated_by_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_product_publications (
                    product_id INTEGER NOT NULL PRIMARY KEY,
                    status TEXT NOT NULL DEFAULT 'draft',
                    public_name TEXT NOT NULL,
                    public_summary TEXT NOT NULL,
                    decision_note TEXT NOT NULL,
                    activated_by_trainer_id INTEGER NULL,
                    activated_at TEXT NULL,
                    deactivated_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE RESTRICT,
                    FOREIGN KEY (activated_by_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$publicationTableExists($pdo, 'shop_product_publication_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_product_publication_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    public_name VARCHAR(255) NOT NULL,
                    public_summary TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_shop_publication_event_product (product_id, created_at),
                    CONSTRAINT fk_shop_publication_event_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_publication_event_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_product_publication_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NOT NULL,
                    public_name TEXT NOT NULL,
                    public_summary TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($publicationTableExists): bool {
        return $publicationTableExists($pdo, 'shop_product_publications')
            && $publicationTableExists($pdo, 'shop_product_publication_events');
    },
];
