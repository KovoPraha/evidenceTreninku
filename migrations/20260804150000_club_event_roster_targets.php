<?php
declare(strict_types=1);

$eventRosterTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$eventRosterColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if ((string)$definition['name'] === $column) {
            return true;
        }
    }
    return false;
};

$eventRosterIndexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=? LIMIT 1'
        );
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804150000_club_event_roster_targets',
    'up' => static function (PDO $pdo) use (
        $eventRosterTableExists,
        $eventRosterColumnExists,
        $eventRosterIndexExists
    ): void {
        foreach (['club_events', 'club_teams', 'club_event_registrations', 'treneri'] as $required) {
            if (!$eventRosterTableExists($pdo, $required)) {
                throw new RuntimeException('Required event roster target table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$eventRosterTableExists($pdo, 'club_event_roster_targets')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_roster_targets (
                    event_id BIGINT UNSIGNED NOT NULL,
                    team_id BIGINT UNSIGNED NOT NULL,
                    actor_trainer_id INT NOT NULL,
                    decision_note VARCHAR(1000) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (event_id,team_id),
                    KEY idx_club_event_roster_team (team_id,event_id),
                    CONSTRAINT fk_club_event_roster_event FOREIGN KEY (event_id)
                        REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_event_roster_team FOREIGN KEY (team_id)
                        REFERENCES club_teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_event_roster_actor FOREIGN KEY (actor_trainer_id)
                        REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_roster_targets (
                    event_id INTEGER NOT NULL,
                    team_id INTEGER NOT NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    decision_note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (event_id,team_id),
                    FOREIGN KEY (event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$eventRosterIndexExists($pdo, 'idx_club_event_roster_team')) {
            $pdo->exec('CREATE INDEX idx_club_event_roster_team ON club_event_roster_targets(team_id,event_id)');
        }
        foreach ([
            'eligibility_team_ids_snapshot' => $mysql ? 'LONGTEXT NULL' : 'TEXT NULL',
            'eligibility_reason_snapshot' => $mysql ? 'VARCHAR(1000) NULL' : 'TEXT NULL',
        ] as $column => $definition) {
            if (!$eventRosterColumnExists($pdo, 'club_event_registrations', $column)) {
                $pdo->exec('ALTER TABLE club_event_registrations ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    },
    'verify' => static function (PDO $pdo) use (
        $eventRosterTableExists,
        $eventRosterColumnExists,
        $eventRosterIndexExists
    ): bool {
        return $eventRosterTableExists($pdo, 'club_event_roster_targets')
            && $eventRosterIndexExists($pdo, 'idx_club_event_roster_team')
            && $eventRosterColumnExists($pdo, 'club_event_registrations', 'eligibility_team_ids_snapshot')
            && $eventRosterColumnExists($pdo, 'club_event_registrations', 'eligibility_reason_snapshot');
    },
];
