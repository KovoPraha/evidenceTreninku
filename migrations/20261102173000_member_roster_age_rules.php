<?php

declare(strict_types=1);

return [
    'id' => '20261102173000_member_roster_age_rules',
    'up' => static function (PDO $pdo): void {
        $pdo->exec($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_roster_age_rules (
                team_id BIGINT UNSIGNED PRIMARY KEY,
                age_min INT NOT NULL,
                age_max INT NOT NULL,
                reference_mode VARCHAR(24) NOT NULL,
                next_team_id BIGINT UNSIGNED NULL,
                FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                FOREIGN KEY(next_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE IF NOT EXISTS member_roster_age_rules (
                team_id INTEGER PRIMARY KEY REFERENCES club_teams(id) ON DELETE RESTRICT,
                age_min INTEGER NOT NULL,
                age_max INTEGER NOT NULL,
                reference_mode TEXT NOT NULL,
                next_team_id INTEGER NULL REFERENCES club_teams(id) ON DELETE RESTRICT
            )
            SQL);
    },
    'verify' => static function (PDO $pdo): bool {
        $pdo->query('SELECT team_id,age_min,age_max,reference_mode,next_team_id FROM member_roster_age_rules WHERE 1=0');
        return true;
    },
];
