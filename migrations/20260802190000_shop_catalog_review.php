<?php
declare(strict_types=1);

$shopReviewTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1"
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for shop review migration.');
};

$shopReviewColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Invalid table name in shop review migration.');
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Unsupported database driver for shop review migration.');
};

return [
    'id' => '20260802190000_shop_catalog_review',
    'up' => static function (PDO $pdo) use ($shopReviewTableExists, $shopReviewColumnExists): void {
        if (!$shopReviewTableExists($pdo, 'shop_catalog_product_candidates')) {
            throw new RuntimeException('Shop catalog staging migration must run first.');
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [
            'review_status' => $driver === 'mysql'
                ? "VARCHAR(24) NOT NULL DEFAULT 'auto_classified'"
                : "TEXT NOT NULL DEFAULT 'auto_classified'",
            'reviewed_offer_type' => $driver === 'mysql' ? 'VARCHAR(32) NULL' : 'TEXT NULL',
            'review_note' => $driver === 'mysql' ? 'TEXT NULL' : 'TEXT NULL',
            'reviewed_by' => $driver === 'mysql' ? 'INT NULL' : 'INTEGER NULL',
            'reviewed_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$shopReviewColumnExists($pdo, 'shop_catalog_product_candidates', $column)) {
                $pdo->exec(
                    'ALTER TABLE shop_catalog_product_candidates ADD COLUMN '
                    . $column . ' ' . $definition
                );
            }
        }

        $pdo->exec(
            "UPDATE shop_catalog_product_candidates SET review_status = "
            . "CASE WHEN needs_manual_review = 1 THEN 'pending' ELSE 'auto_classified' END "
            . "WHERE reviewed_at IS NULL"
        );

        if (!$shopReviewTableExists($pdo, 'shop_catalog_review_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_catalog_review_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_id BIGINT UNSIGNED NOT NULL,
                    product_candidate_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    from_offer_type VARCHAR(32) NULL,
                    to_offer_type VARCHAR(32) NULL,
                    note TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_shop_review_run (run_id, created_at),
                    INDEX idx_shop_review_product (product_candidate_id, created_at),
                    CONSTRAINT fk_shop_review_run FOREIGN KEY (run_id)
                        REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE,
                    CONSTRAINT fk_shop_review_product FOREIGN KEY (product_candidate_id)
                        REFERENCES shop_catalog_product_candidates (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_review_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER NOT NULL,
                    product_candidate_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_offer_type TEXT,
                    to_offer_type TEXT,
                    note TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (run_id) REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE,
                    FOREIGN KEY (product_candidate_id) REFERENCES shop_catalog_product_candidates (id) ON DELETE CASCADE
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($shopReviewTableExists, $shopReviewColumnExists): bool {
        if (!$shopReviewTableExists($pdo, 'shop_catalog_review_events')) {
            return false;
        }
        foreach ([
            'review_status', 'reviewed_offer_type', 'review_note', 'reviewed_by', 'reviewed_at',
        ] as $column) {
            if (!$shopReviewColumnExists($pdo, 'shop_catalog_product_candidates', $column)) {
                return false;
            }
        }
        return true;
    },
];
