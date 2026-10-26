<?php
declare(strict_types=1);
return [
    'id'=>'20261025230000_race_result_revisions',
    'up'=>static function(PDO $pdo): void {
        $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
        $pdo->exec("CREATE TABLE IF NOT EXISTS race_result_states (race_id INTEGER PRIMARY KEY, version INTEGER NOT NULL DEFAULT 0, latest_revision_id BIGINT NULL, published_revision_id BIGINT NULL)$tail");
        $pdo->exec("CREATE TABLE IF NOT EXISTS race_participants (id $id, race_id INTEGER NOT NULL, source_key VARCHAR(100) NOT NULL, sportovec_id INTEGER NULL, name_snapshot VARCHAR(200) NOT NULL, club_snapshot VARCHAR(200) NOT NULL, UNIQUE(race_id,source_key))$tail");
        $pdo->exec("CREATE TABLE IF NOT EXISTS race_result_revisions (id $id, race_id INTEGER NOT NULL, version INTEGER NOT NULL, kind VARCHAR(16) NOT NULL, actor_id INTEGER NOT NULL, reason VARCHAR(500) NOT NULL, request_key VARCHAR(64) NOT NULL UNIQUE, payload_hash VARCHAR(64) NOT NULL, rows_json ".($mysql?'LONGTEXT':'TEXT')." NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(race_id,version))$tail");
    },
    'verify'=>static function(PDO $pdo): bool {
        foreach(['race_result_states','race_participants','race_result_revisions'] as $table) $pdo->query("SELECT * FROM $table WHERE 1=0");
        return true;
    },
];
