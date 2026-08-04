<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804234975_kis_member_charge_promotion',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'kis_import_charge_promotions')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE kis_import_charge_promotions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    import_run_id INT NOT NULL,
                    source_fingerprint CHAR(64) NOT NULL,
                    contract_version VARCHAR(32) NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    item_count INT UNSIGNED NOT NULL,
                    payment_count INT UNSIGNED NOT NULL,
                    apply_count INT UNSIGNED NOT NULL DEFAULT 1,
                    applied_by INT NOT NULL,
                    apply_reason VARCHAR(1000) NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rolled_back_by INT NULL,
                    rollback_reason VARCHAR(1000) NULL,
                    rolled_back_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_charge_promotion_run(import_run_id),
                    KEY idx_kis_charge_promotion_status(status,id),
                    CONSTRAINT fk_kis_charge_promotion_run FOREIGN KEY(import_run_id) REFERENCES kis_import_runs(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_charge_promotions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    import_run_id INTEGER NOT NULL UNIQUE,
                    source_fingerprint TEXT NOT NULL,
                    contract_version TEXT NOT NULL,
                    status TEXT NOT NULL,
                    item_count INTEGER NOT NULL,
                    payment_count INTEGER NOT NULL,
                    apply_count INTEGER NOT NULL DEFAULT 1,
                    applied_by INTEGER NOT NULL,
                    apply_reason TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rolled_back_by INTEGER NULL,
                    rollback_reason TEXT NULL,
                    rolled_back_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(import_run_id) REFERENCES kis_import_runs(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'kis_import_charge_promotion_items')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE kis_import_charge_promotion_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    promotion_id BIGINT UNSIGNED NOT NULL,
                    staged_payment_row_id BIGINT UNSIGNED NOT NULL,
                    source_ref VARCHAR(32) NOT NULL,
                    public_code VARCHAR(32) NOT NULL,
                    variable_symbol VARCHAR(10) NULL,
                    snapshot_fingerprint CHAR(64) NOT NULL,
                    charge_id BIGINT UNSIGNED NULL,
                    payment_id BIGINT UNSIGNED NULL,
                    status VARCHAR(16) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_charge_promotion_item(promotion_id,staged_payment_row_id),
                    UNIQUE KEY uq_kis_charge_promotion_ref(promotion_id,source_ref),
                    KEY idx_kis_charge_promotion_item_status(promotion_id,status,id),
                    CONSTRAINT fk_kis_charge_promotion_item_promotion FOREIGN KEY(promotion_id) REFERENCES kis_import_charge_promotions(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_kis_charge_promotion_item_staging FOREIGN KEY(staged_payment_row_id) REFERENCES kis_import_payment_rows(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_charge_promotion_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    promotion_id INTEGER NOT NULL,
                    staged_payment_row_id INTEGER NOT NULL,
                    source_ref TEXT NOT NULL,
                    public_code TEXT NOT NULL,
                    variable_symbol TEXT NULL,
                    snapshot_fingerprint TEXT NOT NULL,
                    charge_id INTEGER NULL,
                    payment_id INTEGER NULL,
                    status TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(promotion_id,staged_payment_row_id),
                    UNIQUE(promotion_id,source_ref),
                    FOREIGN KEY(promotion_id) REFERENCES kis_import_charge_promotions(id) ON DELETE RESTRICT,
                    FOREIGN KEY(staged_payment_row_id) REFERENCES kis_import_payment_rows(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'kis_import_charge_promotion_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE kis_import_charge_promotion_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    promotion_id BIGINT UNSIGNED NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    actor_id INT NOT NULL,
                    reason VARCHAR(1000) NOT NULL,
                    snapshot_json LONGTEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_kis_charge_promotion_event(promotion_id,id),
                    CONSTRAINT fk_kis_charge_promotion_event_promotion FOREIGN KEY(promotion_id) REFERENCES kis_import_charge_promotions(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_charge_promotion_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    promotion_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    reason TEXT NOT NULL,
                    snapshot_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(promotion_id) REFERENCES kis_import_charge_promotions(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        foreach (['kis_import_charge_promotions', 'kis_import_charge_promotion_items', 'kis_import_charge_promotion_events'] as $table) {
            if (!$tableExists($pdo, $table)) return false;
        }
        return true;
    },
];
