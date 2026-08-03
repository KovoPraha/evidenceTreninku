<?php
declare(strict_types=1);

$kisPolicyTableExists=static function(PDO $pdo,string $table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$s->execute([$table]);return(bool)$s->fetchColumn();}
    $s=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");$s->execute([$table]);return(bool)$s->fetchColumn();
};
$kisPolicyColumnExists=static function(PDO $pdo,string $table,string $column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');$s->execute([$table,$column]);return(bool)$s->fetchColumn();}
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;return false;
};

return[
    'id'=>'20260804110000_kis_roster_policies',
    'up'=>static function(PDO $pdo)use($kisPolicyTableExists,$kisPolicyColumnExists):void{
        foreach(['club_seasons','club_teams','treneri']as$required)if(!$kisPolicyTableExists($pdo,$required))throw new RuntimeException('Required KIS roster policy table is missing: '.$required);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$kisPolicyColumnExists($pdo,'club_seasons','season_type')){
            $pdo->exec($mysql?"ALTER TABLE club_seasons ADD COLUMN season_type VARCHAR(24) NOT NULL DEFAULT 'calendar_year' AFTER name":"ALTER TABLE club_seasons ADD COLUMN season_type TEXT NOT NULL DEFAULT 'calendar_year'");
            if($mysql)$pdo->exec("UPDATE club_seasons SET season_type=CASE WHEN YEAR(starts_on)<>YEAR(ends_on) THEN 'school_year' ELSE 'calendar_year' END");
            else$pdo->exec("UPDATE club_seasons SET season_type=CASE WHEN substr(starts_on,1,4)<>substr(ends_on,1,4) THEN 'school_year' ELSE 'calendar_year' END");
        }
        if(!$kisPolicyTableExists($pdo,'club_team_series'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_team_series (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(48) NOT NULL,
                name VARCHAR(160) NOT NULL,
                series_type VARCHAR(24) NOT NULL,
                season_type VARCHAR(24) NOT NULL,
                rollover_policy VARCHAR(32) NOT NULL,
                next_series_id BIGINT UNSIGNED NULL,
                age_from_years SMALLINT UNSIGNED NULL,
                age_to_years SMALLINT UNSIGNED NULL,
                birth_year_from SMALLINT UNSIGNED NULL,
                birth_year_to SMALLINT UNSIGNED NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_team_series_code (code),
                KEY idx_club_team_series_policy (season_type,rollover_policy,status),
                KEY idx_club_team_series_next (next_series_id),
                CONSTRAINT fk_club_team_series_next FOREIGN KEY (next_series_id) REFERENCES club_team_series(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_team_series_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_team_series(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,series_type TEXT NOT NULL,season_type TEXT NOT NULL,rollover_policy TEXT NOT NULL,next_series_id INTEGER NULL,age_from_years INTEGER NULL,age_to_years INTEGER NULL,birth_year_from INTEGER NULL,birth_year_to INTEGER NULL,status TEXT NOT NULL DEFAULT 'active',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(next_series_id)REFERENCES club_team_series(id)ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id)REFERENCES treneri(id)ON DELETE RESTRICT)
            SQL);
        if(!$kisPolicyColumnExists($pdo,'club_teams','series_id')){
            $pdo->exec($mysql?'ALTER TABLE club_teams ADD COLUMN series_id BIGINT UNSIGNED NULL AFTER season_id':'ALTER TABLE club_teams ADD COLUMN series_id INTEGER NULL REFERENCES club_team_series(id)');
            if($mysql){$pdo->exec('ALTER TABLE club_teams ADD KEY idx_club_team_series_season (series_id,season_id)');$pdo->exec('ALTER TABLE club_teams ADD CONSTRAINT fk_club_team_series FOREIGN KEY (series_id) REFERENCES club_team_series(id) ON DELETE RESTRICT');}
        }
    },
    'verify'=>static function(PDO $pdo)use($kisPolicyTableExists,$kisPolicyColumnExists):bool{
        return$kisPolicyTableExists($pdo,'club_team_series')&&$kisPolicyColumnExists($pdo,'club_seasons','season_type')&&$kisPolicyColumnExists($pdo,'club_teams','series_id');
    },
];
