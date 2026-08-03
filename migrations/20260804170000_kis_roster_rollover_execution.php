<?php
declare(strict_types=1);

$kisRolloverTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return(bool)$s->fetchColumn();
    }
    $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return(bool)$s->fetchColumn();
};

return [
    'id'=>'20260804170000_kis_roster_rollover_execution',
    'up'=>static function(PDO $pdo)use($kisRolloverTableExists):void{
        foreach(['club_teams','club_seasons','club_roster_members','treneri','sportovci']as$required)if(!$kisRolloverTableExists($pdo,$required))throw new RuntimeException('Required KIS rollover table is missing: '.$required);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$kisRolloverTableExists($pdo,'club_roster_rollover_exceptions'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_rollover_exceptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_team_id BIGINT UNSIGNED NOT NULL,
                target_season_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                exception_action VARCHAR(24) NOT NULL,
                target_team_id BIGINT UNSIGNED NULL,
                reason VARCHAR(1000) NOT NULL,
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_roster_rollover_exception (source_team_id,target_season_id,sportovec_id),
                CONSTRAINT fk_rollover_exception_source FOREIGN KEY(source_team_id) REFERENCES club_teams(id) ON DELETE CASCADE,
                CONSTRAINT fk_rollover_exception_season FOREIGN KEY(target_season_id) REFERENCES club_seasons(id) ON DELETE CASCADE,
                CONSTRAINT fk_rollover_exception_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_exception_target FOREIGN KEY(target_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_exception_actor FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_rollover_exceptions(id INTEGER PRIMARY KEY AUTOINCREMENT,source_team_id INTEGER NOT NULL,target_season_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,exception_action TEXT NOT NULL,target_team_id INTEGER NULL,reason TEXT NOT NULL,created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_team_id,target_season_id,sportovec_id),FOREIGN KEY(source_team_id)REFERENCES club_teams(id)ON DELETE CASCADE,FOREIGN KEY(target_season_id)REFERENCES club_seasons(id)ON DELETE CASCADE,FOREIGN KEY(sportovec_id)REFERENCES sportovci(id)ON DELETE RESTRICT,FOREIGN KEY(target_team_id)REFERENCES club_teams(id)ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRolloverTableExists($pdo,'club_roster_rollover_exception_events'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_rollover_exception_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exception_id BIGINT UNSIGNED NOT NULL,
                actor_trainer_id INT NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_rollover_exception_event FOREIGN KEY(exception_id) REFERENCES club_roster_rollover_exceptions(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_exception_event_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_rollover_exception_events(id INTEGER PRIMARY KEY AUTOINCREMENT,exception_id INTEGER NOT NULL,actor_trainer_id INTEGER NOT NULL,before_json TEXT NULL,after_json TEXT NOT NULL,reason TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(exception_id)REFERENCES club_roster_rollover_exceptions(id)ON DELETE RESTRICT,FOREIGN KEY(actor_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRolloverTableExists($pdo,'club_roster_rollover_runs'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_rollover_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_team_id BIGINT UNSIGNED NOT NULL,
                target_season_id BIGINT UNSIGNED NOT NULL,
                preview_fingerprint CHAR(64) NOT NULL,
                actor_trainer_id INT NOT NULL,
                reason VARCHAR(1000) NOT NULL,
                moved_count INT UNSIGNED NOT NULL,
                skipped_count INT UNSIGNED NOT NULL,
                result_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_roster_rollover_fingerprint (source_team_id,target_season_id,preview_fingerprint),
                CONSTRAINT fk_rollover_run_source FOREIGN KEY(source_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_run_season FOREIGN KEY(target_season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_run_actor FOREIGN KEY(actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_rollover_runs(id INTEGER PRIMARY KEY AUTOINCREMENT,source_team_id INTEGER NOT NULL,target_season_id INTEGER NOT NULL,preview_fingerprint TEXT NOT NULL,actor_trainer_id INTEGER NOT NULL,reason TEXT NOT NULL,moved_count INTEGER NOT NULL,skipped_count INTEGER NOT NULL,result_json TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(source_team_id,target_season_id,preview_fingerprint),FOREIGN KEY(source_team_id)REFERENCES club_teams(id)ON DELETE RESTRICT,FOREIGN KEY(target_season_id)REFERENCES club_seasons(id)ON DELETE RESTRICT,FOREIGN KEY(actor_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRolloverTableExists($pdo,'club_roster_rollover_run_items'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_rollover_run_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                source_member_id BIGINT UNSIGNED NOT NULL,
                target_team_id BIGINT UNSIGNED NULL,
                target_member_id BIGINT UNSIGNED NULL,
                action VARCHAR(32) NOT NULL,
                before_json LONGTEXT NOT NULL,
                after_json LONGTEXT NOT NULL,
                UNIQUE KEY uq_roster_rollover_run_person (run_id,sportovec_id),
                CONSTRAINT fk_rollover_item_run FOREIGN KEY(run_id) REFERENCES club_roster_rollover_runs(id) ON DELETE CASCADE,
                CONSTRAINT fk_rollover_item_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_item_source_member FOREIGN KEY(source_member_id) REFERENCES club_roster_members(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_item_target FOREIGN KEY(target_team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_rollover_item_target_member FOREIGN KEY(target_member_id) REFERENCES club_roster_members(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_rollover_run_items(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,source_member_id INTEGER NOT NULL,target_team_id INTEGER NULL,target_member_id INTEGER NULL,action TEXT NOT NULL,before_json TEXT NOT NULL,after_json TEXT NOT NULL,UNIQUE(run_id,sportovec_id),FOREIGN KEY(run_id)REFERENCES club_roster_rollover_runs(id)ON DELETE CASCADE,FOREIGN KEY(sportovec_id)REFERENCES sportovci(id)ON DELETE RESTRICT,FOREIGN KEY(source_member_id)REFERENCES club_roster_members(id)ON DELETE RESTRICT,FOREIGN KEY(target_team_id)REFERENCES club_teams(id)ON DELETE RESTRICT,FOREIGN KEY(target_member_id)REFERENCES club_roster_members(id)ON DELETE RESTRICT)
            SQL);
    },
    'verify'=>static function(PDO$pdo)use($kisRolloverTableExists):bool{foreach(['club_roster_rollover_exceptions','club_roster_rollover_exception_events','club_roster_rollover_runs','club_roster_rollover_run_items']as$table)if(!$kisRolloverTableExists($pdo,$table))return false;return true;},
];
