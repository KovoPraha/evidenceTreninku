<?php

declare(strict_types=1);

return [
    'id' => '20261122120000_password_reset_request_audit',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_request_audit (
                request_id CHAR(32) PRIMARY KEY,
                audience VARCHAR(16) NOT NULL,
                outcome VARCHAR(16) NOT NULL,
                reason_code VARCHAR(32) NOT NULL,
                outbox_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_password_reset_request_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_request_audit (
                request_id TEXT PRIMARY KEY,
                audience TEXT NOT NULL,
                outcome TEXT NOT NULL,
                reason_code TEXT NOT NULL,
                outbox_id INTEGER NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        if (!$mysql) {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_request_created ON password_reset_request_audit(created_at)');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $sql = $mysql
            ? "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reset_request_audit'"
            : "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='password_reset_request_audit'";
        return (int)$pdo->query($sql)->fetchColumn() === 1;
    },
];
