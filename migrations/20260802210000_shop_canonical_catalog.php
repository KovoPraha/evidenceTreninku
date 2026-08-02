<?php
declare(strict_types=1);

$shopCanonicalTableExists = static function (PDO $pdo, string $table): bool {
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
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1"
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for canonical shop migration.');
};

$shopCanonicalColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Invalid table name in canonical shop migration.');
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Unsupported database driver for canonical shop migration.');
};

return [
    'id' => '20260802210000_shop_canonical_catalog',
    'up' => static function (PDO $pdo) use ($shopCanonicalTableExists, $shopCanonicalColumnExists): void {
        if (!$shopCanonicalTableExists($pdo, 'shop_catalog_import_runs')
            || !$shopCanonicalTableExists($pdo, 'shop_catalog_product_candidates')
        ) {
            throw new RuntimeException('Shop staging and review migrations must run first.');
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ([
            'promoted_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            'promoted_by' => $driver === 'mysql' ? 'INT NULL' : 'INTEGER NULL',
        ] as $column => $definition) {
            if (!$shopCanonicalColumnExists($pdo, 'shop_catalog_import_runs', $column)) {
                $pdo->exec('ALTER TABLE shop_catalog_import_runs ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        if (!$shopCanonicalTableExists($pdo, 'shop_catalog_promotions')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_catalog_promotions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    run_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    status VARCHAR(24) NOT NULL,
                    product_count INT UNSIGNED NOT NULL DEFAULT 0,
                    variant_count INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME NULL,
                    UNIQUE KEY uq_shop_catalog_promotion_run (run_id),
                    CONSTRAINT fk_shop_catalog_promotion_run FOREIGN KEY (run_id)
                        REFERENCES shop_catalog_import_runs (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_catalog_promotions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id INTEGER NOT NULL UNIQUE,
                    actor_trainer_id INTEGER NOT NULL,
                    status TEXT NOT NULL,
                    product_count INTEGER NOT NULL DEFAULT 0,
                    variant_count INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    completed_at TEXT,
                    FOREIGN KEY (run_id) REFERENCES shop_catalog_import_runs (id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$shopCanonicalTableExists($pdo, 'shop_products')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_products (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_candidate_id BIGINT UNSIGNED NOT NULL,
                    source_run_id BIGINT UNSIGNED NOT NULL,
                    external_product_key VARCHAR(191) NOT NULL,
                    source_pair_code VARCHAR(64) NULL,
                    name VARCHAR(255) NOT NULL,
                    short_description TEXT NULL,
                    description_html_untrusted LONGTEXT NULL,
                    offer_type VARCHAR(32) NOT NULL,
                    visibility VARCHAR(32) NULL,
                    item_type VARCHAR(32) NULL,
                    catalog_status VARCHAR(24) NOT NULL DEFAULT 'draft',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_product_candidate (source_candidate_id),
                    UNIQUE KEY uq_shop_product_external (external_product_key),
                    INDEX idx_shop_product_type_status (offer_type, catalog_status),
                    CONSTRAINT fk_shop_product_candidate FOREIGN KEY (source_candidate_id)
                        REFERENCES shop_catalog_product_candidates (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_product_run FOREIGN KEY (source_run_id)
                        REFERENCES shop_catalog_import_runs (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    source_candidate_id INTEGER NOT NULL UNIQUE,
                    source_run_id INTEGER NOT NULL,
                    external_product_key TEXT NOT NULL UNIQUE,
                    source_pair_code TEXT,
                    name TEXT NOT NULL,
                    short_description TEXT,
                    description_html_untrusted TEXT,
                    offer_type TEXT NOT NULL,
                    visibility TEXT,
                    item_type TEXT,
                    catalog_status TEXT NOT NULL DEFAULT 'draft',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (source_candidate_id) REFERENCES shop_catalog_product_candidates (id) ON DELETE RESTRICT,
                    FOREIGN KEY (source_run_id) REFERENCES shop_catalog_import_runs (id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$shopCanonicalTableExists($pdo, 'shop_variants')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_variants (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    source_candidate_id BIGINT UNSIGNED NOT NULL,
                    sku VARCHAR(64) NOT NULL,
                    ean VARCHAR(32) NULL,
                    attributes_json LONGTEXT NOT NULL,
                    price_mode VARCHAR(16) NOT NULL,
                    amount_minor BIGINT NULL,
                    compare_at_amount_minor BIGINT NULL,
                    currency CHAR(3) NULL,
                    includes_vat TINYINT(1) NULL,
                    vat_rate_basis_points INT NULL,
                    stock_quantity_decimal DECIMAL(18,6) NULL,
                    unit_code VARCHAR(32) NULL,
                    availability_in_stock VARCHAR(120) NULL,
                    availability_out_of_stock VARCHAR(120) NULL,
                    free_shipping TINYINT(1) NULL,
                    free_billing TINYINT(1) NULL,
                    visible TINYINT(1) NULL,
                    catalog_status VARCHAR(24) NOT NULL DEFAULT 'draft',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_shop_variant_candidate (source_candidate_id),
                    UNIQUE KEY uq_shop_variant_sku (sku),
                    INDEX idx_shop_variant_product (product_id),
                    CONSTRAINT fk_shop_variant_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_shop_variant_candidate FOREIGN KEY (source_candidate_id)
                        REFERENCES shop_catalog_variant_candidates (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_variants (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    source_candidate_id INTEGER NOT NULL UNIQUE,
                    sku TEXT NOT NULL UNIQUE,
                    ean TEXT,
                    attributes_json TEXT NOT NULL,
                    price_mode TEXT NOT NULL,
                    amount_minor INTEGER,
                    compare_at_amount_minor INTEGER,
                    currency TEXT,
                    includes_vat INTEGER,
                    vat_rate_basis_points INTEGER,
                    stock_quantity_decimal TEXT,
                    unit_code TEXT,
                    availability_in_stock TEXT,
                    availability_out_of_stock TEXT,
                    free_shipping INTEGER,
                    free_billing INTEGER,
                    visible INTEGER,
                    catalog_status TEXT NOT NULL DEFAULT 'draft',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE RESTRICT,
                    FOREIGN KEY (source_candidate_id) REFERENCES shop_catalog_variant_candidates (id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$shopCanonicalTableExists($pdo, 'shop_product_categories')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_product_categories (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    category_path VARCHAR(500) NOT NULL,
                    is_default TINYINT(1) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_shop_product_category (product_id, category_path),
                    CONSTRAINT fk_shop_category_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_product_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    category_path TEXT NOT NULL,
                    is_default INTEGER NOT NULL DEFAULT 0,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    UNIQUE (product_id, category_path),
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE CASCADE
                )
                SQL);
        }

        if (!$shopCanonicalTableExists($pdo, 'shop_product_images')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE shop_product_images (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    image_url VARCHAR(2048) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_shop_product_image (product_id, image_url(191)),
                    CONSTRAINT fk_shop_image_product FOREIGN KEY (product_id)
                        REFERENCES shop_products (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE shop_product_images (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    image_url TEXT NOT NULL,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    UNIQUE (product_id, image_url),
                    FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE CASCADE
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($shopCanonicalTableExists, $shopCanonicalColumnExists): bool {
        foreach ([
            'shop_catalog_promotions', 'shop_products', 'shop_variants',
            'shop_product_categories', 'shop_product_images',
        ] as $table) {
            if (!$shopCanonicalTableExists($pdo, $table)) {
                return false;
            }
        }
        return $shopCanonicalColumnExists($pdo, 'shop_catalog_import_runs', 'promoted_at')
            && $shopCanonicalColumnExists($pdo, 'shop_catalog_import_runs', 'promoted_by');
    },
];
