<?php
declare(strict_types=1);

$manualCatalogTableExists = static function (PDO $pdo, string $table): bool {
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
    throw new RuntimeException('Unsupported database driver for manual catalog origin migration.');
};

/** @return array<string,mixed>|null */
$manualCatalogColumn = static function (PDO $pdo, string $table, string $column): ?array {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Invalid table name in manual catalog origin migration.');
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? null) === $column) {
                return $row;
            }
        }
        return null;
    }
    throw new RuntimeException('Unsupported database driver for manual catalog origin migration.');
};

$manualCatalogForeignKeyExists = static function (
    PDO $pdo,
    string $table,
    string $column,
    string $referencedTable
): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? '
            . 'AND REFERENCED_TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column, $referencedTable]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Invalid table name in manual catalog origin migration.');
        }
        foreach ($pdo->query('PRAGMA foreign_key_list(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['from'] ?? null) === $column && ($row['table'] ?? null) === $referencedTable) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Unsupported database driver for manual catalog origin migration.');
};

$manualCatalogNullable = static function (PDO $pdo, string $table, string $column) use (
    $manualCatalogColumn
): bool {
    $definition = $manualCatalogColumn($pdo, $table, $column);
    if ($definition === null) {
        return false;
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return ($definition['IS_NULLABLE'] ?? null) === 'YES';
    }
    return (int)($definition['notnull'] ?? 1) === 0;
};

$manualCatalogOriginDefault = static function (PDO $pdo, string $table) use ($manualCatalogColumn): bool {
    $definition = $manualCatalogColumn($pdo, $table, 'origin');
    if ($definition === null) {
        return false;
    }
    $default = $definition['COLUMN_DEFAULT'] ?? $definition['dflt_value'] ?? null;
    return trim((string)$default, "'\"") === 'import';
};

$manualCatalogRebuildSqlite = static function (PDO $pdo) use ($manualCatalogColumn): void {
    $foreignKeysEnabled = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn() === 1;
    $productOrigin = $manualCatalogColumn($pdo, 'shop_products', 'origin') === null
        ? "'import'"
        : 'origin';
    $productActor = $manualCatalogColumn($pdo, 'shop_products', 'created_by_trainer_id') === null
        ? 'NULL'
        : 'created_by_trainer_id';
    $variantOrigin = $manualCatalogColumn($pdo, 'shop_variants', 'origin') === null
        ? "'import'"
        : 'origin';
    $variantActor = $manualCatalogColumn($pdo, 'shop_variants', 'created_by_trainer_id') === null
        ? 'NULL'
        : 'created_by_trainer_id';

    if ($pdo->inTransaction()) {
        throw new RuntimeException('SQLite manual catalog migration requires its own transaction.');
    }
    $pdo->exec('PRAGMA foreign_keys=OFF');
    $pdo->beginTransaction();
    try {
        $pdo->exec('DROP TABLE IF EXISTS shop_products_r1_next');
        $pdo->exec(<<<'SQL'
            CREATE TABLE shop_products_r1_next (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_candidate_id INTEGER NULL UNIQUE,
                source_run_id INTEGER NULL,
                origin TEXT NOT NULL DEFAULT 'import',
                created_by_trainer_id INTEGER NULL,
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
                FOREIGN KEY (source_candidate_id) REFERENCES shop_catalog_product_candidates(id) ON DELETE RESTRICT,
                FOREIGN KEY (source_run_id) REFERENCES shop_catalog_import_runs(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        $pdo->exec(
            'INSERT INTO shop_products_r1_next '
            . '(id,source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,'
            . 'source_pair_code,name,short_description,description_html_untrusted,offer_type,visibility,'
            . 'item_type,catalog_status,created_at,updated_at) '
            . 'SELECT id,source_candidate_id,source_run_id,' . $productOrigin . ',' . $productActor . ','
            . 'external_product_key,source_pair_code,name,short_description,description_html_untrusted,'
            . 'offer_type,visibility,item_type,catalog_status,created_at,updated_at FROM shop_products'
        );
        $pdo->exec('DROP TABLE shop_products');
        $pdo->exec('ALTER TABLE shop_products_r1_next RENAME TO shop_products');

        $pdo->exec('DROP TABLE IF EXISTS shop_variants_r1_next');
        $pdo->exec(<<<'SQL'
            CREATE TABLE shop_variants_r1_next (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                source_candidate_id INTEGER NULL UNIQUE,
                origin TEXT NOT NULL DEFAULT 'import',
                created_by_trainer_id INTEGER NULL,
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
                FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                FOREIGN KEY (source_candidate_id) REFERENCES shop_catalog_variant_candidates(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            )
            SQL);
        $pdo->exec(
            'INSERT INTO shop_variants_r1_next '
            . '(id,product_id,source_candidate_id,origin,created_by_trainer_id,sku,ean,attributes_json,'
            . 'price_mode,amount_minor,compare_at_amount_minor,currency,includes_vat,vat_rate_basis_points,'
            . 'stock_quantity_decimal,unit_code,availability_in_stock,availability_out_of_stock,free_shipping,'
            . 'free_billing,visible,catalog_status,created_at,updated_at) '
            . 'SELECT id,product_id,source_candidate_id,' . $variantOrigin . ',' . $variantActor . ','
            . 'sku,ean,attributes_json,price_mode,amount_minor,compare_at_amount_minor,currency,includes_vat,'
            . 'vat_rate_basis_points,stock_quantity_decimal,unit_code,availability_in_stock,'
            . 'availability_out_of_stock,free_shipping,free_billing,visible,catalog_status,created_at,'
            . 'updated_at FROM shop_variants'
        );
        $pdo->exec('DROP TABLE shop_variants');
        $pdo->exec('ALTER TABLE shop_variants_r1_next RENAME TO shop_variants');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->exec('PRAGMA foreign_keys=' . ($foreignKeysEnabled ? 'ON' : 'OFF'));
        throw $exception;
    }
    $pdo->exec('PRAGMA foreign_keys=' . ($foreignKeysEnabled ? 'ON' : 'OFF'));
    if ($pdo->query('PRAGMA foreign_key_check')->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('SQLite foreign key check failed after manual catalog migration.');
    }
};

return [
    'id' => '20260816200000_shop_manual_catalog_origin',
    'up' => static function (PDO $pdo) use (
        $manualCatalogTableExists,
        $manualCatalogColumn,
        $manualCatalogNullable,
        $manualCatalogForeignKeyExists,
        $manualCatalogRebuildSqlite
    ): void {
        foreach (['shop_products', 'shop_variants', 'treneri'] as $required) {
            if (!$manualCatalogTableExists($pdo, $required)) {
                throw new RuntimeException('Required manual catalog table is missing: ' . $required);
            }
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $ready = $manualCatalogColumn($pdo, 'shop_products', 'origin') !== null
                && $manualCatalogColumn($pdo, 'shop_products', 'created_by_trainer_id') !== null
                && $manualCatalogNullable($pdo, 'shop_products', 'source_candidate_id')
                && $manualCatalogNullable($pdo, 'shop_products', 'source_run_id')
                && $manualCatalogColumn($pdo, 'shop_variants', 'origin') !== null
                && $manualCatalogColumn($pdo, 'shop_variants', 'created_by_trainer_id') !== null
                && $manualCatalogNullable($pdo, 'shop_variants', 'source_candidate_id');
            if (!$ready) {
                $manualCatalogRebuildSqlite($pdo);
            }
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Unsupported database driver for manual catalog origin migration.');
        }

        if ($manualCatalogColumn($pdo, 'shop_products', 'origin') === null) {
            $pdo->exec(
                "ALTER TABLE shop_products ADD COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'import' "
                . 'AFTER source_run_id'
            );
        }
        if ($manualCatalogColumn($pdo, 'shop_products', 'created_by_trainer_id') === null) {
            $pdo->exec(
                'ALTER TABLE shop_products ADD COLUMN created_by_trainer_id INT NULL AFTER origin'
            );
        }
        if ($manualCatalogColumn($pdo, 'shop_variants', 'origin') === null) {
            $pdo->exec(
                "ALTER TABLE shop_variants ADD COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'import' "
                . 'AFTER source_candidate_id'
            );
        }
        if ($manualCatalogColumn($pdo, 'shop_variants', 'created_by_trainer_id') === null) {
            $pdo->exec(
                'ALTER TABLE shop_variants ADD COLUMN created_by_trainer_id INT NULL AFTER origin'
            );
        }
        $pdo->exec(
            'ALTER TABLE shop_products '
            . 'MODIFY source_candidate_id BIGINT UNSIGNED NULL, '
            . 'MODIFY source_run_id BIGINT UNSIGNED NULL'
        );
        $pdo->exec(
            'ALTER TABLE shop_variants MODIFY source_candidate_id BIGINT UNSIGNED NULL'
        );
        if (!$manualCatalogForeignKeyExists(
            $pdo,
            'shop_products',
            'created_by_trainer_id',
            'treneri'
        )) {
            $pdo->exec(
                'ALTER TABLE shop_products ADD CONSTRAINT fk_shop_product_creator '
                . 'FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT'
            );
        }
        if (!$manualCatalogForeignKeyExists(
            $pdo,
            'shop_variants',
            'created_by_trainer_id',
            'treneri'
        )) {
            $pdo->exec(
                'ALTER TABLE shop_variants ADD CONSTRAINT fk_shop_variant_creator '
                . 'FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT'
            );
        }
    },
    'verify' => static function (PDO $pdo) use (
        $manualCatalogColumn,
        $manualCatalogNullable,
        $manualCatalogOriginDefault,
        $manualCatalogForeignKeyExists
    ): bool {
        foreach (['shop_products', 'shop_variants'] as $table) {
            if ($manualCatalogColumn($pdo, $table, 'origin') === null
                || $manualCatalogColumn($pdo, $table, 'created_by_trainer_id') === null
                || !$manualCatalogOriginDefault($pdo, $table)
                || !$manualCatalogForeignKeyExists($pdo, $table, 'created_by_trainer_id', 'treneri')
            ) {
                return false;
            }
        }
        return $manualCatalogNullable($pdo, 'shop_products', 'source_candidate_id')
            && $manualCatalogNullable($pdo, 'shop_products', 'source_run_id')
            && $manualCatalogNullable($pdo, 'shop_variants', 'source_candidate_id');
    },
];
