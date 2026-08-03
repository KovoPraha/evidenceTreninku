<?php
declare(strict_types=1);

$childAccessTableExists = static function (PDO $pdo, string $table): bool {
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
    throw new RuntimeException('Unsupported database driver for child access migration.');
};

return [
    'id' => '20260804190000_child_access_accounts',
    'up' => static function (PDO $pdo) use ($childAccessTableExists): void {
        foreach (['sportovci', 'treneri'] as $required) {
            if (!$childAccessTableExists($pdo, $required)) {
                throw new RuntimeException('Required child access table is missing: ' . $required);
            }
        }

        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$childAccessTableExists($pdo, 'child_access_accounts')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE child_access_accounts (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sportovec_id INT NOT NULL,
                    login_name VARCHAR(120) NOT NULL,
                    login_key VARCHAR(120) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    session_version INT UNSIGNED NOT NULL DEFAULT 1,
                    last_login_at DATETIME NULL,
                    password_changed_at DATETIME NOT NULL,
                    created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_child_access_sportovec (sportovec_id),
                    UNIQUE KEY uq_child_access_login_key (login_key),
                    INDEX idx_child_access_active (active, id),
                    CONSTRAINT fk_child_access_sportovec FOREIGN KEY (sportovec_id)
                        REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_child_access_creator FOREIGN KEY (created_by_trainer_id)
                        REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE child_access_accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sportovec_id INTEGER NOT NULL UNIQUE,
                    login_name TEXT NOT NULL,
                    login_key TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    active INTEGER NOT NULL DEFAULT 1,
                    session_version INTEGER NOT NULL DEFAULT 1,
                    last_login_at TEXT NULL,
                    password_changed_at TEXT NOT NULL,
                    created_by_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$childAccessTableExists($pdo, 'child_access_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE child_access_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    access_account_id BIGINT UNSIGNED NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id BIGINT NULL,
                    action VARCHAR(32) NOT NULL,
                    note VARCHAR(1000) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_child_access_event_account (access_account_id, id),
                    CONSTRAINT fk_child_access_event_account FOREIGN KEY (access_account_id)
                        REFERENCES child_access_accounts(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE child_access_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    access_account_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NULL,
                    action TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (access_account_id) REFERENCES child_access_accounts(id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($childAccessTableExists): bool {
        return $childAccessTableExists($pdo, 'child_access_accounts')
            && $childAccessTableExists($pdo, 'child_access_events');
    },
];
