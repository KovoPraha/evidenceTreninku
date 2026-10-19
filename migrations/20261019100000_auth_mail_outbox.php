<?php
declare(strict_types=1);

return [
    'id' => '20261019100000_auth_mail_outbox',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS auth_mail_outbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(16) NOT NULL,
                target_type VARCHAR(16) NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                recipient_email VARCHAR(254) NOT NULL,
                subject_plain VARCHAR(255) NOT NULL,
                body_ciphertext TEXT NULL,
                key_version VARCHAR(32) NOT NULL,
                status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at DATETIME NULL,
                claim_token CHAR(32) NULL,
                sent_at DATETIME NULL,
                last_error VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_auth_mail_queue (status,available_at,id),
                KEY idx_auth_mail_target (kind,target_type,target_id,status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS auth_mail_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kind TEXT NOT NULL CHECK(kind IN ('verify','reset')),
                target_type TEXT NOT NULL CHECK(target_type IN ('public','child')),
                target_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                recipient_email TEXT NOT NULL,
                subject_plain TEXT NOT NULL,
                body_ciphertext TEXT NULL,
                key_version TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','processing','sent','failed','cancelled')),
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                claimed_at TEXT NULL,
                claim_token TEXT NULL,
                sent_at TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        if (!$mysql) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_mail_queue ON auth_mail_outbox(status,available_at,id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_mail_target ON auth_mail_outbox(kind,target_type,target_id,status)');
        }
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS auth_mail_outbox_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                outbox_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id BIGINT UNSIGNED NOT NULL,
                action VARCHAR(32) NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_auth_mail_event_outbox (outbox_id,id),
                CONSTRAINT fk_auth_mail_event_outbox FOREIGN KEY (outbox_id) REFERENCES auth_mail_outbox(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS auth_mail_outbox_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                outbox_id INTEGER NOT NULL,
                actor_trainer_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (outbox_id) REFERENCES auth_mail_outbox(id) ON DELETE RESTRICT
            )
            SQL);
        if (!$mysql) $pdo->exec('CREATE INDEX IF NOT EXISTS idx_auth_mail_event_outbox ON auth_mail_outbox_events(outbox_id,id)');
    },
    'verify' => static function (PDO $pdo): bool {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('auth_mail_outbox','auth_mail_outbox_events')")->fetchColumn();
            return $count === 2;
        }
        $count = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('auth_mail_outbox','auth_mail_outbox_events')")->fetchColumn();
        return $count === 2;
    },
];
