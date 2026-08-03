<?php
declare(strict_types=1);

$kisRosterTableExists=static function(PDO $pdo,string $table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$s->execute([$table]);return(bool)$s->fetchColumn();}
    $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$s->execute([$table]);return(bool)$s->fetchColumn();
};

return[
    'id'=>'20260804090000_kis_teams_rosters',
    'up'=>static function(PDO $pdo)use($kisRosterTableExists):void{
        foreach(['treneri','sportovci']as$required)if(!$kisRosterTableExists($pdo,$required))throw new RuntimeException('Required KIS roster table is missing: '.$required);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$kisRosterTableExists($pdo,'club_seasons'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_seasons (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(120) NOT NULL,
                starts_on DATE NOT NULL,
                ends_on DATE NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_season_code (code),
                KEY idx_club_season_status_dates (status,starts_on,ends_on),
                CONSTRAINT fk_club_season_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,starts_on TEXT NOT NULL,ends_on TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'draft',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(created_by_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRosterTableExists($pdo,'club_teams'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_teams (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                season_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(48) NOT NULL,
                name VARCHAR(160) NOT NULL,
                discipline VARCHAR(120) NOT NULL,
                age_label VARCHAR(120) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_team_season_code (season_id,code),
                KEY idx_club_team_season_status (season_id,status,name),
                CONSTRAINT fk_club_team_season FOREIGN KEY (season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_team_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER NOT NULL,code TEXT NOT NULL,name TEXT NOT NULL,discipline TEXT NOT NULL,age_label TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'active',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(season_id,code),FOREIGN KEY(season_id)REFERENCES club_seasons(id)ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRosterTableExists($pdo,'club_roster_members'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_members (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                source VARCHAR(24) NOT NULL,
                kis_external_id_snapshot VARCHAR(80) NULL,
                valid_from DATE NOT NULL,
                valid_to DATE NULL,
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_roster_team_person (team_id,sportovec_id),
                KEY idx_club_roster_team_status (team_id,status,id),
                KEY idx_club_roster_person_status (sportovec_id,status,id),
                CONSTRAINT fk_club_roster_team FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_roster_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_roster_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'active',source TEXT NOT NULL,kis_external_id_snapshot TEXT NULL,valid_from TEXT NOT NULL,valid_to TEXT NULL,created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(team_id,sportovec_id),FOREIGN KEY(team_id)REFERENCES club_teams(id)ON DELETE RESTRICT,FOREIGN KEY(sportovec_id)REFERENCES sportovci(id)ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisRosterTableExists($pdo,'club_roster_events'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_roster_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id BIGINT UNSIGNED NOT NULL,
                roster_member_id BIGINT UNSIGNED NULL,
                actor_trainer_id INT NOT NULL,
                action VARCHAR(32) NOT NULL,
                before_json LONGTEXT NULL,
                after_json LONGTEXT NOT NULL,
                note VARCHAR(1000) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_club_roster_event_team (team_id,id),
                CONSTRAINT fk_club_roster_event_team FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_roster_event_member FOREIGN KEY (roster_member_id) REFERENCES club_roster_members(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_roster_event_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER NOT NULL,roster_member_id INTEGER NULL,actor_trainer_id INTEGER NOT NULL,action TEXT NOT NULL,before_json TEXT NULL,after_json TEXT NOT NULL,note TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(team_id)REFERENCES club_teams(id)ON DELETE RESTRICT,FOREIGN KEY(roster_member_id)REFERENCES club_roster_members(id)ON DELETE RESTRICT,FOREIGN KEY(actor_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
    },
    'verify'=>static function(PDO $pdo)use($kisRosterTableExists):bool{foreach(['club_seasons','club_teams','club_roster_members','club_roster_events']as$table)if(!$kisRosterTableExists($pdo,$table))return false;return true;},
];
