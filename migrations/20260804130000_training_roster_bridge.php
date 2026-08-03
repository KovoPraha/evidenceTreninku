<?php
declare(strict_types=1);

$trainingRosterTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804130000_training_roster_bridge',
    'up' => static function (PDO $pdo) use ($trainingRosterTableExists): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $requiredTables = ['club_teams', 'club_roster_members', 'sportovci', 'treneri'];
        // SQLite permits references to tables created later; the isolated migration-catalog test
        // intentionally omits the legacy training baseline. MariaDB deployment must fail closed.
        if ($mysql) $requiredTables = [...$requiredTables, 'planovane_treninky', 'treninky'];
        foreach ($requiredTables as $required) {
            if (!$trainingRosterTableExists($pdo, $required)) {
                throw new RuntimeException('Required training roster bridge table is missing: ' . $required);
            }
        }

        if (!$trainingRosterTableExists($pdo, 'training_roster_links')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE training_roster_links (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    plan_id INT NULL,
                    trenink_id INT NULL,
                    team_id BIGINT UNSIGNED NOT NULL,
                    target_date DATE NOT NULL,
                    team_code_snapshot VARCHAR(48) NOT NULL,
                    team_name_snapshot VARCHAR(160) NOT NULL,
                    created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_training_roster_plan_team (plan_id, team_id),
                    UNIQUE KEY uq_training_roster_training_team (trenink_id, team_id),
                    KEY idx_training_roster_team_date (team_id, target_date),
                    CONSTRAINT fk_training_roster_plan FOREIGN KEY (plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
                    CONSTRAINT fk_training_roster_training FOREIGN KEY (trenink_id) REFERENCES treninky(id) ON DELETE CASCADE,
                    CONSTRAINT fk_training_roster_team FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_training_roster_actor FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    CONSTRAINT chk_training_roster_owner CHECK ((plan_id IS NULL) <> (trenink_id IS NULL))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE training_roster_links (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    plan_id INTEGER NULL,
                    trenink_id INTEGER NULL,
                    team_id INTEGER NOT NULL,
                    target_date TEXT NOT NULL,
                    team_code_snapshot TEXT NOT NULL,
                    team_name_snapshot TEXT NOT NULL,
                    created_by_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(plan_id, team_id),
                    UNIQUE(trenink_id, team_id),
                    CHECK ((plan_id IS NULL) <> (trenink_id IS NULL)),
                    FOREIGN KEY(plan_id) REFERENCES planovane_treninky(id) ON DELETE CASCADE,
                    FOREIGN KEY(trenink_id) REFERENCES treninky(id) ON DELETE CASCADE,
                    FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_training_roster_team_date ON training_roster_links(team_id, target_date)');
            }
        }

        if (!$trainingRosterTableExists($pdo, 'training_roster_expected')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE training_roster_expected (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    link_id BIGINT UNSIGNED NOT NULL,
                    sportovec_id INT NOT NULL,
                    roster_member_id BIGINT UNSIGNED NULL,
                    member_valid_from_snapshot DATE NOT NULL,
                    member_valid_to_snapshot DATE NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_training_roster_expected_person (link_id, sportovec_id),
                    KEY idx_training_roster_expected_person (sportovec_id, link_id),
                    CONSTRAINT fk_training_roster_expected_link FOREIGN KEY (link_id) REFERENCES training_roster_links(id) ON DELETE CASCADE,
                    CONSTRAINT fk_training_roster_expected_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_training_roster_expected_member FOREIGN KEY (roster_member_id) REFERENCES club_roster_members(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE training_roster_expected (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    link_id INTEGER NOT NULL,
                    sportovec_id INTEGER NOT NULL,
                    roster_member_id INTEGER NULL,
                    member_valid_from_snapshot TEXT NOT NULL,
                    member_valid_to_snapshot TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(link_id, sportovec_id),
                    FOREIGN KEY(link_id) REFERENCES training_roster_links(id) ON DELETE CASCADE,
                    FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY(roster_member_id) REFERENCES club_roster_members(id) ON DELETE SET NULL
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_training_roster_expected_person ON training_roster_expected(sportovec_id, link_id)');
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($trainingRosterTableExists): bool {
        return $trainingRosterTableExists($pdo, 'training_roster_links')
            && $trainingRosterTableExists($pdo, 'training_roster_expected');
    },
];
