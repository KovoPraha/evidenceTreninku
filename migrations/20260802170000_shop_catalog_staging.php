<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
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
    throw new RuntimeException('Unsupported database driver for shop catalog migration.');
};

return [
    'id' => '20260802170000_shop_catalog_staging',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported database driver for shop catalog migration.');
        }

        if (!$tableExists($pdo, 'shop_catalog_import_runs')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_catalog_import_runs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_sha256 CHAR(64) NOT NULL,
                    source_filename VARCHAR(255) NOT NULL,
                    contract_version VARCHAR(80) NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    product_count INT UNSIGNED NOT NULL,
                    variant_count INT UNSIGNED NOT NULL,
                    warning_count INT UNSIGNED NOT NULL,
                    manual_review_count INT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_catalog_run_source (source_sha256, contract_version),
                    INDEX idx_shop_catalog_run_status (status, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_import_runs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source_sha256 TEXT NOT NULL,
                    source_filename TEXT NOT NULL,
                    contract_version TEXT NOT NULL,
                    status TEXT NOT NULL,
                    product_count INTEGER NOT NULL,
                    variant_count INTEGER NOT NULL,
                    warning_count INTEGER NOT NULL,
                    manual_review_count INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (source_sha256, contract_version)
                )
                SQL);
        }

        if (!$tableExists($pdo, 'shop_catalog_product_candidates')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_catalog_product_candidates (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_id BIGINT UNSIGNED NOT NULL,
                    external_product_key VARCHAR(191) NOT NULL,
                    source_pair_code VARCHAR(64) NULL,
                    name VARCHAR(255) NOT NULL,
                    offer_type VARCHAR(32) NOT NULL,
                    classification_confidence VARCHAR(16) NOT NULL,
                    needs_manual_review TINYINT(1) NOT NULL DEFAULT 0,
                    payload_json LONGTEXT NOT NULL,
                    UNIQUE KEY uq_shop_catalog_product (run_id, external_product_key),
                    INDEX idx_shop_catalog_product_review (run_id, needs_manual_review),
                    CONSTRAINT fk_shop_catalog_product_run FOREIGN KEY (run_id)
                        REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_product_candidates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER NOT NULL,
                    external_product_key TEXT NOT NULL,
                    source_pair_code TEXT,
                    name TEXT NOT NULL,
                    offer_type TEXT NOT NULL,
                    classification_confidence TEXT NOT NULL,
                    needs_manual_review INTEGER NOT NULL DEFAULT 0,
                    payload_json TEXT NOT NULL,
                    UNIQUE (run_id, external_product_key),
                    FOREIGN KEY (run_id) REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE
                )
                SQL);
        }

        if (!$tableExists($pdo, 'shop_catalog_variant_candidates')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_catalog_variant_candidates (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_id BIGINT UNSIGNED NOT NULL,
                    product_candidate_id BIGINT UNSIGNED NOT NULL,
                    sku VARCHAR(64) NOT NULL,
                    price_mode VARCHAR(16) NOT NULL,
                    amount_minor BIGINT NULL,
                    currency CHAR(3) NULL,
                    stock_quantity_decimal DECIMAL(18,6) NULL,
                    payload_json LONGTEXT NOT NULL,
                    UNIQUE KEY uq_shop_catalog_variant (run_id, sku),
                    INDEX idx_shop_catalog_variant_product (product_candidate_id),
                    CONSTRAINT fk_shop_catalog_variant_run FOREIGN KEY (run_id)
                        REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE,
                    CONSTRAINT fk_shop_catalog_variant_product FOREIGN KEY (product_candidate_id)
                        REFERENCES shop_catalog_product_candidates (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_variant_candidates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER NOT NULL,
                    product_candidate_id INTEGER NOT NULL,
                    sku TEXT NOT NULL,
                    price_mode TEXT NOT NULL,
                    amount_minor INTEGER,
                    currency TEXT,
                    stock_quantity_decimal TEXT,
                    payload_json TEXT NOT NULL,
                    UNIQUE (run_id, sku),
                    FOREIGN KEY (run_id) REFERENCES shop_catalog_import_runs (id) ON DELETE CASCADE,
                    FOREIGN KEY (product_candidate_id) REFERENCES shop_catalog_product_candidates (id) ON DELETE CASCADE
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        foreach ([
            'shop_catalog_import_runs',
            'shop_catalog_product_candidates',
            'shop_catalog_variant_candidates',
        ] as $table) {
            if (!$tableExists($pdo, $table)) {
                return false;
            }
        }
        return true;
    },
];
