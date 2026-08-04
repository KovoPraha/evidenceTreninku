<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804234700_kis_import_sandbox_promotion',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$tableExists($pdo, 'kis_import_sandbox_promotions')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE kis_import_sandbox_promotions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    import_run_id INT NOT NULL,
                    preview_fingerprint CHAR(64) NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    item_count INT UNSIGNED NOT NULL,
                    apply_count INT UNSIGNED NOT NULL DEFAULT 1,
                    applied_by INT NOT NULL,
                    apply_reason TEXT NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rolled_back_by INT NULL,
                    rollback_reason TEXT NULL,
                    rolled_back_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_sandbox_preview (import_run_id,preview_fingerprint),
                    INDEX idx_kis_sandbox_status (status),
                    INDEX idx_kis_sandbox_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_sandbox_promotions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    import_run_id INTEGER NOT NULL,
                    preview_fingerprint TEXT NOT NULL,
                    status TEXT NOT NULL,
                    item_count INTEGER NOT NULL,
                    apply_count INTEGER NOT NULL DEFAULT 1,
                    applied_by INTEGER NOT NULL,
                    apply_reason TEXT NOT NULL,
                    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    rolled_back_by INTEGER NULL,
                    rollback_reason TEXT NULL,
                    rolled_back_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(import_run_id,preview_fingerprint)
                )
                SQL);
        }
        if (!$tableExists($pdo, 'kis_import_sandbox_items')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE kis_import_sandbox_items (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    promotion_id BIGINT UNSIGNED NOT NULL,
                    source_ref VARCHAR(128) NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    target_ref VARCHAR(128) NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_kis_sandbox_item (promotion_id,source_ref),
                    INDEX idx_kis_sandbox_item_active (promotion_id,active),
                    CONSTRAINT fk_kis_sandbox_item_promotion FOREIGN KEY (promotion_id)
                        REFERENCES kis_import_sandbox_promotions(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_sandbox_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    promotion_id INTEGER NOT NULL,
                    source_ref TEXT NOT NULL,
                    action TEXT NOT NULL,
                    target_ref TEXT NULL,
                    active INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(promotion_id,source_ref),
                    FOREIGN KEY(promotion_id) REFERENCES kis_import_sandbox_promotions(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'kis_import_sandbox_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE kis_import_sandbox_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    promotion_id BIGINT UNSIGNED NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    actor_id INT NOT NULL,
                    reason TEXT NOT NULL,
                    snapshot_json LONGTEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_kis_sandbox_event_promotion (promotion_id,created_at),
                    CONSTRAINT fk_kis_sandbox_event_promotion FOREIGN KEY (promotion_id)
                        REFERENCES kis_import_sandbox_promotions(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE kis_import_sandbox_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    promotion_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    actor_id INTEGER NOT NULL,
                    reason TEXT NOT NULL,
                    snapshot_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(promotion_id) REFERENCES kis_import_sandbox_promotions(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists): bool {
        foreach (['kis_import_sandbox_promotions', 'kis_import_sandbox_items', 'kis_import_sandbox_events'] as $table) {
            if (!$tableExists($pdo, $table)) {
                return false;
            }
        }
        return true;
    },
];
