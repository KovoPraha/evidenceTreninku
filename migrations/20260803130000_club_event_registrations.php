<?php
declare(strict_types=1);

$registrationTableExists = static function (PDO $pdo, string $table): bool {
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
    throw new RuntimeException('Unsupported database driver for club event registrations migration.');
};

return [
    'id' => '20260803130000_club_event_registrations',
    'up' => static function (PDO $pdo) use ($registrationTableExists): void {
        foreach (['club_events', 'verejni_uzivatele', 'sportovci', 'account_person_roles'] as $required) {
            if (!$registrationTableExists($pdo, $required)) {
                throw new RuntimeException('Required club registration table is missing: ' . $required);
            }
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!$registrationTableExists($pdo, 'club_event_registrations')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_event_registrations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    account_id INT NOT NULL,
                    sportovec_id INT NOT NULL,
                    relation_role_snapshot VARCHAR(24) NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'confirmed',
                    registered_at DATETIME NOT NULL,
                    cancelled_at DATETIME NULL,
                    cancellation_note TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_club_registration_person (event_id, sportovec_id),
                    INDEX idx_club_registration_capacity (event_id, status),
                    INDEX idx_club_registration_account (account_id, status),
                    CONSTRAINT fk_club_registration_event FOREIGN KEY (event_id)
                        REFERENCES club_events (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_registration_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_registration_person FOREIGN KEY (sportovec_id)
                        REFERENCES sportovci (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_registrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INTEGER NOT NULL,
                    account_id INTEGER NOT NULL,
                    sportovec_id INTEGER NOT NULL,
                    relation_role_snapshot TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'confirmed',
                    registered_at TEXT NOT NULL,
                    cancelled_at TEXT NULL,
                    cancellation_note TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (event_id, sportovec_id),
                    FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele (id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci (id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$registrationTableExists($pdo, 'club_event_registration_events')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_event_registration_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    registration_id BIGINT UNSIGNED NOT NULL,
                    actor_type VARCHAR(24) NOT NULL,
                    actor_id INT NULL,
                    action VARCHAR(32) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    note TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_club_registration_event_history (registration_id, created_at),
                    CONSTRAINT fk_club_registration_history FOREIGN KEY (registration_id)
                        REFERENCES club_event_registrations (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_registration_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    registration_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (registration_id) REFERENCES club_event_registrations (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($registrationTableExists): bool {
        return $registrationTableExists($pdo, 'club_event_registrations')
            && $registrationTableExists($pdo, 'club_event_registration_events');
    },
];
