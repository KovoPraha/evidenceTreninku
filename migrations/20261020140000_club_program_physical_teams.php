<?php
declare(strict_types=1);

return [
    'id' => '20261020140000_club_program_physical_teams',
    'up' => static function (PDO $pdo): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS club_program_physical_teams (
                program_id BIGINT UNSIGNED NOT NULL,
                team_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY(program_id, team_id),
                KEY idx_club_program_physical_team (team_id, program_id),
                CONSTRAINT fk_club_program_physical_program FOREIGN KEY (program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_physical_team FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS club_program_physical_teams (
                program_id INTEGER NOT NULL,
                team_id INTEGER NOT NULL,
                PRIMARY KEY(program_id, team_id),
                FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
                FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT
            )');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_physical_team ON club_program_physical_teams(team_id, program_id)');
        }
    },
    'verify' => static function (PDO $pdo): bool {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            return (bool)$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='club_program_physical_teams' LIMIT 1")->fetchColumn();
        }
        return (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='club_program_physical_teams' LIMIT 1")->fetchColumn();
    },
];
