<?php
declare(strict_types=1);

$clubTermsColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
            throw new RuntimeException('Invalid club terms table name.');
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }
    throw new RuntimeException('Unsupported database driver for club event terms migration.');
};

$clubTermsTableExists = static function (PDO $pdo, string $table): bool {
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new RuntimeException('Unsupported database driver for club event terms migration.');
};

return [
    'id' => '20260803150000_club_event_terms',
    'up' => static function (PDO $pdo) use ($clubTermsColumnExists, $clubTermsTableExists): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $definitions = [
            'club_events' => [
                'terms_version' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
                'consent_text_plain' => $driver === 'mysql' ? 'TEXT NULL' : 'TEXT NULL',
                'cancellation_policy_plain' => $driver === 'mysql' ? 'TEXT NULL' : 'TEXT NULL',
                'cancellation_deadline_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
                'terms_configured_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
                'terms_configured_by_trainer_id' => $driver === 'mysql' ? 'INT NULL' : 'INTEGER NULL',
            ],
            'club_event_registrations' => [
                'consent_version_snapshot' => $driver === 'mysql' ? 'VARCHAR(64) NULL' : 'TEXT NULL',
                'consent_text_snapshot' => $driver === 'mysql' ? 'TEXT NULL' : 'TEXT NULL',
                'consented_at' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
                'cancellation_policy_snapshot' => $driver === 'mysql' ? 'TEXT NULL' : 'TEXT NULL',
                'cancellation_deadline_snapshot' => $driver === 'mysql' ? 'DATETIME NULL' : 'TEXT NULL',
            ],
        ];
        foreach ($definitions as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (!$clubTermsColumnExists($pdo, $table, $column)) {
                    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
        }
        if (!$clubTermsTableExists($pdo, 'club_event_term_versions')) {
            $pdo->exec($driver === 'mysql' ? <<<'SQL'
                CREATE TABLE club_event_term_versions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    terms_version VARCHAR(64) NOT NULL,
                    consent_text_plain TEXT NOT NULL,
                    cancellation_policy_plain TEXT NOT NULL,
                    cancellation_deadline_at DATETIME NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_club_event_terms_version (event_id, terms_version),
                    CONSTRAINT fk_club_terms_event FOREIGN KEY (event_id)
                        REFERENCES club_events (id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_terms_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri (id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_term_versions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id INTEGER NOT NULL,
                    terms_version TEXT NOT NULL,
                    consent_text_plain TEXT NOT NULL,
                    cancellation_policy_plain TEXT NOT NULL,
                    cancellation_deadline_at TEXT NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (event_id, terms_version),
                    FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                )
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use ($clubTermsColumnExists, $clubTermsTableExists): bool {
        if (!$clubTermsTableExists($pdo, 'club_event_term_versions')) {
            return false;
        }
        foreach ([
            'club_events' => [
                'terms_version', 'consent_text_plain', 'cancellation_policy_plain',
                'cancellation_deadline_at', 'terms_configured_at', 'terms_configured_by_trainer_id',
            ],
            'club_event_registrations' => [
                'consent_version_snapshot', 'consent_text_snapshot', 'consented_at',
                'cancellation_policy_snapshot', 'cancellation_deadline_snapshot',
            ],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (!$clubTermsColumnExists($pdo, $table, $column)) {
                    return false;
                }
            }
        }
        return true;
    },
];
