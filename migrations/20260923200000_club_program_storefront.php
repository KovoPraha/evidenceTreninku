<?php
declare(strict_types=1);

$tableExists=static function(PDO$pdo,string$table):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);return(bool)$statement->fetchColumn();
    }
    $statement=$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
    $statement->execute([$table]);return(bool)$statement->fetchColumn();
};
$columnExists=static function(PDO$pdo,string$table,string$column):bool{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table,$column]);return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']===$column)return true;
    return false;
};

return[
    'id'=>'20260923200000_club_program_storefront',
    'up'=>static function(PDO$pdo)use($tableExists,$columnExists):void{
        foreach(['club_programs','club_program_offers','club_seasons']as$table)if(!$tableExists($pdo,$table))throw new RuntimeException('Required club storefront table is missing: '.$table);
        $mysql=(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        if(!$columnExists($pdo,'club_program_offers','purchase_option')){
            $pdo->exec($mysql
                ? "ALTER TABLE club_program_offers ADD COLUMN purchase_option VARCHAR(24) NOT NULL DEFAULT 'custom' AFTER birth_year_to"
                : "ALTER TABLE club_program_offers ADD COLUMN purchase_option TEXT NOT NULL DEFAULT 'custom'");
        }
        if(!$columnExists($pdo,'club_program_offers','is_featured')){
            $pdo->exec($mysql
                ? 'ALTER TABLE club_program_offers ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER purchase_option'
                : 'ALTER TABLE club_program_offers ADD COLUMN is_featured INTEGER NOT NULL DEFAULT 0');
        }
        if(!$tableExists($pdo,'club_program_presentations'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_program_presentations (
                program_id BIGINT UNSIGNED PRIMARY KEY,
                public_name VARCHAR(160) NOT NULL,
                public_summary TEXT NULL,
                location_name VARCHAR(160) NULL,
                age_label VARCHAR(80) NULL,
                listing_status VARCHAR(24) NOT NULL DEFAULT 'draft',
                sort_order INT NOT NULL DEFAULT 0,
                interest_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_club_program_presentation_listing (listing_status,sort_order,program_id),
                CONSTRAINT fk_club_program_presentation_program FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_program_presentations(program_id INTEGER PRIMARY KEY,public_name TEXT NOT NULL,public_summary TEXT NULL,location_name TEXT NULL,age_label TEXT NULL,listing_status TEXT NOT NULL DEFAULT 'draft',sort_order INTEGER NOT NULL DEFAULT 0,interest_enabled INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE)
            SQL);
        if(!$tableExists($pdo,'club_program_schedule_slots'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_program_schedule_slots (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                program_id BIGINT UNSIGNED NOT NULL,
                season_id BIGINT UNSIGNED NOT NULL,
                weekday TINYINT UNSIGNED NOT NULL,
                starts_at TIME NOT NULL,
                ends_at TIME NOT NULL,
                location_name VARCHAR(160) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_program_schedule_slot(program_id,season_id,weekday,starts_at,ends_at,location_name),
                KEY idx_club_program_schedule_program(program_id,season_id,sort_order,weekday,starts_at),
                CONSTRAINT fk_club_program_schedule_program FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE,
                CONSTRAINT fk_club_program_schedule_season FOREIGN KEY(season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_program_schedule_slots(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER NOT NULL,season_id INTEGER NOT NULL,weekday INTEGER NOT NULL,starts_at TEXT NOT NULL,ends_at TEXT NOT NULL,location_name TEXT NULL,sort_order INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(program_id,season_id,weekday,starts_at,ends_at,location_name),FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE,FOREIGN KEY(season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT)
            SQL);
        if(!$tableExists($pdo,'club_program_images'))$pdo->exec($mysql?<<<'SQL'
            CREATE TABLE club_program_images (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                program_id BIGINT UNSIGNED NOT NULL,
                image_url VARCHAR(2048) NOT NULL,
                alt_text VARCHAR(255) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_program_image(program_id,image_url(191)),
                KEY idx_club_program_image_order(program_id,sort_order,id),
                CONSTRAINT fk_club_program_image_program FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL:<<<'SQL'
            CREATE TABLE club_program_images(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER NOT NULL,image_url TEXT NOT NULL,alt_text TEXT NOT NULL,sort_order INTEGER NOT NULL DEFAULT 0,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(program_id,image_url),FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE CASCADE)
            SQL);
        if(!$mysql){
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_presentation_listing ON club_program_presentations(listing_status,sort_order,program_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_schedule_program ON club_program_schedule_slots(program_id,season_id,sort_order,weekday,starts_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_club_program_image_order ON club_program_images(program_id,sort_order,id)');
        }
    },
    'verify'=>static function(PDO$pdo)use($tableExists,$columnExists):bool{
        return$columnExists($pdo,'club_program_offers','purchase_option')
            &&$columnExists($pdo,'club_program_offers','is_featured')
            &&$tableExists($pdo,'club_program_presentations')
            &&$tableExists($pdo,'club_program_schedule_slots')
            &&$tableExists($pdo,'club_program_images');
    },
];
