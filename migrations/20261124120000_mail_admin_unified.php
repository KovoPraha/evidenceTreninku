<?php

declare(strict_types=1);

return [
    'id' => '20261124120000_mail_admin_unified',
    'up' => static function (PDO $pdo): void {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_request_audit (
                request_id CHAR(32) PRIMARY KEY,
                kind VARCHAR(24) NOT NULL,
                identifier_hash CHAR(64) NULL,
                outcome VARCHAR(16) NOT NULL,
                reason_code VARCHAR(40) NOT NULL,
                outbox_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_mail_request_created (created_at,request_id),
                KEY idx_mail_request_identifier (identifier_hash,created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_request_audit (
                request_id TEXT PRIMARY KEY,
                kind TEXT NOT NULL,
                identifier_hash TEXT NULL,
                outcome TEXT NOT NULL,
                reason_code TEXT NOT NULL,
                outbox_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_bcc_settings (
                id TINYINT UNSIGNED PRIMARY KEY,
                enabled_until DATETIME NULL,
                changed_by_trainer_id INT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_bcc_settings (
                id INTEGER PRIMARY KEY,
                enabled_until TEXT NULL,
                changed_by_trainer_id INTEGER NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_bcc (
                source_type VARCHAR(40) NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                bcc_email VARCHAR(254) NOT NULL,
                actor_trainer_id INT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                cancelled_at DATETIME NULL,
                accepted_at DATETIME NULL,
                accepted_count INT UNSIGNED NOT NULL DEFAULT 0,
                skipped_at DATETIME NULL,
                PRIMARY KEY (source_type,source_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_bcc (
                source_type TEXT NOT NULL,
                source_id INTEGER NOT NULL,
                bcc_email TEXT NOT NULL,
                actor_trainer_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                cancelled_at TEXT NULL,
                accepted_at TEXT NULL,
                accepted_count INTEGER NOT NULL DEFAULT 0,
                skipped_at TEXT NULL,
                PRIMARY KEY (source_type,source_id)
            )
            SQL);
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_type VARCHAR(40) NULL,
                source_id BIGINT UNSIGNED NULL,
                actor_trainer_id INT NOT NULL,
                action VARCHAR(40) NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_mail_admin_event_source (source_type,source_id,id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_type TEXT NULL,
                source_id INTEGER NULL,
                actor_trainer_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_reissues (
                original_outbox_id BIGINT UNSIGNED PRIMARY KEY,
                new_outbox_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS mail_admin_reissues (
                original_outbox_id INTEGER PRIMARY KEY,
                new_outbox_id INTEGER NOT NULL,
                actor_trainer_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        if (!$mysql) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_request_created ON mail_request_audit(created_at,request_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_request_identifier ON mail_request_audit(identifier_hash,created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mail_admin_event_source ON mail_admin_events(source_type,source_id,id)');
        }
        $columns = $mysql
            ? $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_request_audit'")->fetchAll(PDO::FETCH_COLUMN)
            : array_column($pdo->query('PRAGMA table_info(password_reset_request_audit)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('identifier_hash', $columns, true)) {
            $pdo->exec('ALTER TABLE password_reset_request_audit ADD COLUMN identifier_hash CHAR(64) NULL');
        }
        if ($mysql) {
            $index = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() "
                . "AND TABLE_NAME='password_reset_request_audit' AND INDEX_NAME='idx_password_reset_identifier'")->fetchColumn();
            if (!(int)$index) {
                $pdo->exec('CREATE INDEX idx_password_reset_identifier ON password_reset_request_audit(identifier_hash,created_at)');
            }
            $pdo->exec('INSERT IGNORE INTO mail_admin_bcc_settings(id,enabled_until) VALUES(1,NULL)');
        } else {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_identifier ON password_reset_request_audit(identifier_hash,created_at)');
            $pdo->exec('INSERT OR IGNORE INTO mail_admin_bcc_settings(id,enabled_until) VALUES(1,NULL)');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $tables = $mysql
            ? (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
                . "AND TABLE_NAME IN ('mail_request_audit','mail_admin_bcc_settings','mail_admin_bcc','mail_admin_events','mail_admin_reissues')")->fetchColumn()
            : (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' "
                . "AND name IN ('mail_request_audit','mail_admin_bcc_settings','mail_admin_bcc','mail_admin_events','mail_admin_reissues')")->fetchColumn();
        $pdo->query('SELECT identifier_hash FROM password_reset_request_audit WHERE 1=0');
        return $tables === 5;
    },
];
