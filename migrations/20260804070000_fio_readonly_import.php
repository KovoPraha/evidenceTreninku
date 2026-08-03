<?php
declare(strict_types=1);

$fioTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804070000_fio_readonly_import',
    'up' => static function (PDO $pdo) use ($fioTableExists): void {
        foreach (['payments', 'shop_orders'] as $required) {
            if (!$fioTableExists($pdo, $required)) throw new RuntimeException('Required Fio import table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$fioTableExists($pdo, 'fio_import_runs')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE fio_import_runs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    period_from DATE NOT NULL,
                    period_to DATE NOT NULL,
                    source_account_iban VARCHAR(34) NULL,
                    status VARCHAR(16) NOT NULL,
                    fetched_count INT UNSIGNED NOT NULL DEFAULT 0,
                    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
                    duplicate_count INT UNSIGNED NOT NULL DEFAULT 0,
                    proposed_count INT UNSIGNED NOT NULL DEFAULT 0,
                    review_count INT UNSIGNED NOT NULL DEFAULT 0,
                    ignored_count INT UNSIGNED NOT NULL DEFAULT 0,
                    error_code VARCHAR(64) NULL,
                    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    finished_at DATETIME NULL,
                    KEY idx_fio_runs_started (started_at,id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE fio_import_runs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    period_from TEXT NOT NULL,
                    period_to TEXT NOT NULL,
                    source_account_iban TEXT NULL,
                    status TEXT NOT NULL,
                    fetched_count INTEGER NOT NULL DEFAULT 0,
                    inserted_count INTEGER NOT NULL DEFAULT 0,
                    duplicate_count INTEGER NOT NULL DEFAULT 0,
                    proposed_count INTEGER NOT NULL DEFAULT 0,
                    review_count INTEGER NOT NULL DEFAULT 0,
                    ignored_count INTEGER NOT NULL DEFAULT 0,
                    error_code TEXT NULL,
                    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    finished_at TEXT NULL
                )
                SQL);
        }
        if (!$fioTableExists($pdo, 'fio_account_movements')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE fio_account_movements (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    fio_movement_id VARCHAR(80) NOT NULL,
                    booked_on DATE NOT NULL,
                    amount_minor BIGINT NOT NULL,
                    currency CHAR(3) NOT NULL,
                    variable_symbol VARCHAR(10) NULL,
                    movement_type VARCHAR(64) NOT NULL,
                    raw_sha256 CHAR(64) NOT NULL,
                    match_status VARCHAR(32) NOT NULL,
                    candidate_payment_id BIGINT UNSIGNED NULL,
                    candidate_order_id BIGINT UNSIGNED NULL,
                    match_reason VARCHAR(255) NOT NULL,
                    import_run_id BIGINT UNSIGNED NOT NULL,
                    first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_fio_movement_id (fio_movement_id),
                    KEY idx_fio_movement_match (match_status,booked_on,id),
                    KEY idx_fio_movement_vs (variable_symbol,booked_on,id),
                    CONSTRAINT fk_fio_movement_run FOREIGN KEY (import_run_id) REFERENCES fio_import_runs(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_fio_movement_payment FOREIGN KEY (candidate_payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_fio_movement_order FOREIGN KEY (candidate_order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE fio_account_movements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    fio_movement_id TEXT NOT NULL UNIQUE,
                    booked_on TEXT NOT NULL,
                    amount_minor INTEGER NOT NULL,
                    currency TEXT NOT NULL,
                    variable_symbol TEXT NULL,
                    movement_type TEXT NOT NULL,
                    raw_sha256 TEXT NOT NULL,
                    match_status TEXT NOT NULL,
                    candidate_payment_id INTEGER NULL,
                    candidate_order_id INTEGER NULL,
                    match_reason TEXT NOT NULL,
                    import_run_id INTEGER NOT NULL,
                    first_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (import_run_id) REFERENCES fio_import_runs(id) ON DELETE RESTRICT,
                    FOREIGN KEY (candidate_payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
                    FOREIGN KEY (candidate_order_id) REFERENCES shop_orders(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($fioTableExists): bool {
        return $fioTableExists($pdo, 'fio_import_runs') && $fioTableExists($pdo, 'fio_account_movements');
    },
];
