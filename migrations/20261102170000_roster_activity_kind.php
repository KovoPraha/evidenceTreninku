<?php

declare(strict_types=1);

$hasColumn = static function (PDO $pdo): bool {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $columns = $pdo->query($mysql ? 'SHOW COLUMNS FROM club_teams' : 'PRAGMA table_info(club_teams)')->fetchAll(PDO::FETCH_ASSOC);
    return in_array('activity_kind', array_column($columns, $mysql ? 'Field' : 'name'), true);
};

return [
    'id' => '20261102170000_roster_activity_kind',
    'up' => static function (PDO $pdo) use ($hasColumn): void {
        if (!$hasColumn($pdo)) {
            $pdo->exec("ALTER TABLE club_teams ADD COLUMN activity_kind VARCHAR(24) NOT NULL DEFAULT 'unclassified'");
        }
        // Preserve the existing explicit classification for legacy rosters. Never infer it from a name.
        $pdo->exec("UPDATE club_teams SET activity_kind='competitive' WHERE activity_kind='unclassified' "
            . "AND season_id IN (SELECT id FROM club_seasons WHERE season_type='calendar_year')");
        $pdo->exec("UPDATE club_teams SET activity_kind='circle' WHERE activity_kind='unclassified' "
            . "AND season_id IN (SELECT id FROM club_seasons WHERE season_type='school_year')");
    },
    'verify' => static function (PDO $pdo) use ($hasColumn): bool {
        return $hasColumn($pdo);
    },
];
