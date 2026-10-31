<?php

declare(strict_types=1);

return [
    'id' => '20261031090000_trainer_password_reset',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS trainer_password_reset_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trainer_id INT NOT NULL,
                recipient_email VARCHAR(254) NOT NULL,
                session_version INT NOT NULL,
                public_account_id INT NULL,
                public_session_version INT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_trainer_reset_hash (token_hash),
                KEY idx_trainer_reset_target (trainer_id,consumed_at,expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS trainer_password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                trainer_id INTEGER NOT NULL,
                recipient_email TEXT NOT NULL,
                session_version INTEGER NOT NULL,
                public_account_id INTEGER NULL,
                public_session_version INTEGER NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                consumed_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        if ($mysql) {
            return; // Existing production outbox uses VARCHAR, without a target-type CHECK.
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_trainer_reset_target ON trainer_password_reset_tokens(trainer_id,consumed_at,expires_at)');
        $original = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='auth_mail_outbox'")->fetchColumn();
        if (str_contains($original, "'trainer'")) {
            return;
        }
        if (!str_contains($original, "target_type IN ('public','child')") || $pdo->inTransaction()) {
            throw new RuntimeException('Unexpected SQLite outbox schema or active transaction.');
        }
        // SQLite requires a table rebuild to widen a CHECK. Preserve rows, indexes and inbound FKs.
        $replacement = str_replace("target_type IN ('public','child')", "target_type IN ('public','child','trainer')", $original);
        $replacement = str_replace('CREATE TABLE auth_mail_outbox', 'CREATE TABLE auth_mail_outbox_trainer_next', $replacement);
        $indexes = $pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='auth_mail_outbox' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $pdo->exec('PRAGMA foreign_keys=OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec($replacement);
            $pdo->exec('INSERT INTO auth_mail_outbox_trainer_next SELECT * FROM auth_mail_outbox');
            $pdo->exec('DROP TABLE auth_mail_outbox');
            $pdo->exec('ALTER TABLE auth_mail_outbox_trainer_next RENAME TO auth_mail_outbox');
            foreach ($indexes as $sql) {
                $pdo->exec($sql);
            }
            if ($pdo->query('PRAGMA foreign_key_check')->fetch() !== false) {
                throw new RuntimeException('Outbox migration foreign key check failed.');
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            $pdo->exec('PRAGMA foreign_keys=' . $foreignKeys);
        }
    },
    'verify' => static function (PDO $pdo): bool {
        try {
            $pdo->query('SELECT trainer_id,recipient_email,session_version,public_account_id,public_session_version,token_hash,expires_at,consumed_at FROM trainer_password_reset_tokens WHERE 1=0');
            if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='auth_mail_outbox'")->fetchColumn();
                return str_contains($sql, "'trainer'");
            }
            return true;
        } catch (PDOException) {
            return false;
        }
    },
];
