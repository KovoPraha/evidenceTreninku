<?php
declare(strict_types=1);

$accountRoleTableExists = static function (PDO $pdo, string $table): bool {
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
        $statement = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1"
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for account person migration.');
};

return [
    'id' => '20260802230000_account_person_roles',
    'up' => static function (PDO $pdo) use ($accountRoleTableExists): void {
        if (!$accountRoleTableExists($pdo, 'verejni_uzivatele')
            || !$accountRoleTableExists($pdo, 'sportovci')
        ) {
            throw new RuntimeException('Public accounts and sportovci tables must exist first.');
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$accountRoleTableExists($pdo, 'account_person_roles')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE account_person_roles (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    sportovec_id INT NOT NULL,
                    relation_role VARCHAR(24) NOT NULL,
                    status VARCHAR(24) NOT NULL,
                    source VARCHAR(24) NOT NULL DEFAULT 'admin',
                    valid_from DATETIME NOT NULL,
                    valid_to DATETIME NULL,
                    created_by_trainer_id INT NOT NULL,
                    approved_by_trainer_id INT NULL,
                    decision_note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_account_person (account_id, sportovec_id),
                    INDEX idx_account_person_active (account_id, status, valid_to),
                    INDEX idx_person_account_active (sportovec_id, status, valid_to),
                    CONSTRAINT fk_account_person_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_person_sportovec FOREIGN KEY (sportovec_id)
                        REFERENCES sportovci (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_person_created_by FOREIGN KEY (created_by_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_person_approved_by FOREIGN KEY (approved_by_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE account_person_roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    sportovec_id INTEGER NOT NULL,
                    relation_role TEXT NOT NULL,
                    status TEXT NOT NULL,
                    source TEXT NOT NULL DEFAULT 'admin',
                    valid_from TEXT NOT NULL,
                    valid_to TEXT,
                    created_by_trainer_id INTEGER NOT NULL,
                    approved_by_trainer_id INTEGER,
                    decision_note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (account_id, sportovec_id),
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci (id) ON DELETE RESTRICT,
                    FOREIGN KEY (created_by_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT,
                    FOREIGN KEY (approved_by_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$accountRoleTableExists($pdo, 'account_person_role_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE account_person_role_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    relation_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(24) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    relation_role VARCHAR(24) NOT NULL,
                    note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_account_role_event_relation (relation_id, created_at),
                    CONSTRAINT fk_account_role_event_relation FOREIGN KEY (relation_id)
                        REFERENCES account_person_roles (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_account_role_event_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE account_person_role_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    relation_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    from_status TEXT,
                    to_status TEXT NOT NULL,
                    relation_role TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (relation_id) REFERENCES account_person_roles (id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($accountRoleTableExists): bool {
        return $accountRoleTableExists($pdo, 'account_person_roles')
            && $accountRoleTableExists($pdo, 'account_person_role_events');
    },
];
