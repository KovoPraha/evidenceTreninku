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
    throw new RuntimeException('Unsupported database driver for weekly summary migration.');
};

return [
    'id' => '20260805040000_family_weekly_summaries',
    'up' => static function (PDO $pdo) use ($tableExists): void {
        if (!$tableExists($pdo, 'verejni_uzivatele')) {
            throw new RuntimeException('Weekly summary prerequisite is missing: verejni_uzivatele.');
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$tableExists($pdo, 'family_weekly_summary_preferences')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE family_weekly_summary_preferences (
                    account_id INT PRIMARY KEY,
                    enabled TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_weekly_summary_preference_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE family_weekly_summary_preferences (
                    account_id INTEGER PRIMARY KEY,
                    enabled INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$tableExists($pdo, 'family_weekly_summaries')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE family_weekly_summaries (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    period_from DATE NOT NULL,
                    period_to DATE NOT NULL,
                    recipient_email VARCHAR(254) NOT NULL,
                    recipient_name VARCHAR(255) NOT NULL,
                    subject_plain VARCHAR(255) NOT NULL,
                    body_plain TEXT NOT NULL,
                    item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
                    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    claimed_at DATETIME NULL,
                    claim_token CHAR(32) NULL,
                    sent_at DATETIME NULL,
                    cancelled_at DATETIME NULL,
                    last_error VARCHAR(500) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_family_weekly_summary(account_id,period_from),
                    KEY idx_family_weekly_summary_queue(status,available_at,id),
                    CONSTRAINT fk_family_weekly_summary_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE family_weekly_summaries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    period_from TEXT NOT NULL,
                    period_to TEXT NOT NULL,
                    recipient_email TEXT NOT NULL,
                    recipient_name TEXT NOT NULL,
                    subject_plain TEXT NOT NULL,
                    body_plain TEXT NOT NULL,
                    item_count INTEGER NOT NULL DEFAULT 0,
                    status TEXT NOT NULL DEFAULT 'pending',
                    attempts INTEGER NOT NULL DEFAULT 0,
                    available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    claimed_at TEXT NULL,
                    claim_token TEXT NULL,
                    sent_at TEXT NULL,
                    cancelled_at TEXT NULL,
                    last_error TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(account_id,period_from),
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) $pdo->exec('CREATE INDEX idx_family_weekly_summary_queue ON family_weekly_summaries(status,available_at,id)');
        }
        if (!$tableExists($pdo, 'family_weekly_summary_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE family_weekly_summary_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    summary_id BIGINT UNSIGNED NULL,
                    account_id INT NOT NULL,
                    actor_type VARCHAR(24) NOT NULL DEFAULT 'system',
                    actor_id BIGINT NULL,
                    action VARCHAR(32) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NULL,
                    note VARCHAR(500) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_family_weekly_summary_event(summary_id,id),
                    KEY idx_family_weekly_account_event(account_id,id),
                    CONSTRAINT fk_family_weekly_event_summary FOREIGN KEY(summary_id)
                        REFERENCES family_weekly_summaries(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_family_weekly_event_account FOREIGN KEY(account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE family_weekly_summary_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    summary_id INTEGER NULL,
                    account_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL DEFAULT 'system',
                    actor_id INTEGER NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(summary_id) REFERENCES family_weekly_summaries(id) ON DELETE RESTRICT,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_family_weekly_summary_event ON family_weekly_summary_events(summary_id,id)');
                $pdo->exec('CREATE INDEX idx_family_weekly_account_event ON family_weekly_summary_events(account_id,id)');
            }
        }
    },
    'verify' => static fn (PDO $pdo): bool => $tableExists($pdo, 'family_weekly_summary_preferences')
        && $tableExists($pdo, 'family_weekly_summaries')
        && $tableExists($pdo, 'family_weekly_summary_events'),
];
