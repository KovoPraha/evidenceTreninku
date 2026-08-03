<?php
declare(strict_types=1);

$clubProgramTableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804140000_club_programs',
    'up' => static function (PDO $pdo) use ($clubProgramTableExists): void {
        foreach (['treneri','sportovci','verejni_uzivatele','shop_products','shop_variants','shop_order_items','club_seasons','club_teams','club_roster_members','club_roster_events'] as $required) {
            if (!$clubProgramTableExists($pdo, $required)) throw new RuntimeException('Required club program table is missing: ' . $required);
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$clubProgramTableExists($pdo, 'club_programs')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_programs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(48) NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_program_code (code),
                KEY idx_club_program_status_name (status,name),
                CONSTRAINT fk_club_program_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_programs(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,description TEXT NULL,status TEXT NOT NULL DEFAULT 'active',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if (!$clubProgramTableExists($pdo, 'club_program_offers')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_program_offers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                program_id BIGINT UNSIGNED NOT NULL,
                season_id BIGINT UNSIGNED NOT NULL,
                team_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                variant_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(180) NOT NULL,
                starts_on DATE NOT NULL,
                ends_on DATE NOT NULL,
                sales_open_at DATETIME NULL,
                sales_close_at DATETIME NULL,
                capacity INT UNSIGNED NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                created_by_trainer_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_program_offer_code (code),
                UNIQUE KEY uq_club_program_offer_variant (variant_id),
                KEY idx_club_program_offer_program_dates (program_id,starts_on,ends_on),
                KEY idx_club_program_offer_status_sales (status,sales_open_at,sales_close_at),
                CONSTRAINT fk_club_program_offer_program FOREIGN KEY (program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_offer_season FOREIGN KEY (season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_offer_team FOREIGN KEY (team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_offer_product FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_offer_variant FOREIGN KEY (variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_offer_creator FOREIGN KEY (created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER NOT NULL,season_id INTEGER NOT NULL,team_id INTEGER NOT NULL,product_id INTEGER NOT NULL,variant_id INTEGER NOT NULL,code TEXT NOT NULL UNIQUE,name TEXT NOT NULL,starts_on TEXT NOT NULL,ends_on TEXT NOT NULL,sales_open_at TEXT NULL,sales_close_at TEXT NULL,capacity INTEGER NULL,status TEXT NOT NULL DEFAULT 'draft',created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(variant_id),FOREIGN KEY(program_id) REFERENCES club_programs(id) ON DELETE RESTRICT,FOREIGN KEY(season_id) REFERENCES club_seasons(id) ON DELETE RESTRICT,FOREIGN KEY(team_id) REFERENCES club_teams(id) ON DELETE RESTRICT,FOREIGN KEY(product_id) REFERENCES shop_products(id) ON DELETE RESTRICT,FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT,FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT)
            SQL);
        if (!$clubProgramTableExists($pdo, 'club_program_enrollments')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_program_enrollments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                offer_id BIGINT UNSIGNED NOT NULL,
                sportovec_id INT NOT NULL,
                account_id INT NOT NULL,
                source_order_item_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                valid_from DATE NOT NULL,
                valid_to DATE NOT NULL,
                activated_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_club_program_enrollment_offer_person (offer_id,sportovec_id),
                UNIQUE KEY uq_club_program_enrollment_order_item (source_order_item_id),
                KEY idx_club_program_enrollment_person (sportovec_id,status,valid_to),
                CONSTRAINT fk_club_program_enrollment_offer FOREIGN KEY (offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_enrollment_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_enrollment_account FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_enrollment_order_item FOREIGN KEY (source_order_item_id) REFERENCES shop_order_items(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,account_id INTEGER NOT NULL,source_order_item_id INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'active',valid_from TEXT NOT NULL,valid_to TEXT NOT NULL,activated_at TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(offer_id,sportovec_id),UNIQUE(source_order_item_id),FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,FOREIGN KEY(source_order_item_id) REFERENCES shop_order_items(id) ON DELETE RESTRICT)
            SQL);
        if (!$clubProgramTableExists($pdo, 'club_program_enrollment_events')) $pdo->exec($mysql ? <<<'SQL'
            CREATE TABLE club_program_enrollment_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                offer_id BIGINT UNSIGNED NOT NULL,
                enrollment_id BIGINT UNSIGNED NULL,
                actor_type VARCHAR(24) NOT NULL,
                actor_id INT NULL,
                action VARCHAR(48) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_club_program_event_offer (offer_id,id),
                KEY idx_club_program_event_enrollment (enrollment_id,id),
                CONSTRAINT fk_club_program_event_offer FOREIGN KEY (offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,
                CONSTRAINT fk_club_program_event_enrollment FOREIGN KEY (enrollment_id) REFERENCES club_program_enrollments(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL : <<<'SQL'
            CREATE TABLE club_program_enrollment_events(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_id INTEGER NOT NULL,enrollment_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,action TEXT NOT NULL,payload_json TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(offer_id) REFERENCES club_program_offers(id) ON DELETE RESTRICT,FOREIGN KEY(enrollment_id) REFERENCES club_program_enrollments(id) ON DELETE RESTRICT)
            SQL);
    },
    'verify' => static function (PDO $pdo) use ($clubProgramTableExists): bool {
        foreach (['club_programs','club_program_offers','club_program_enrollments','club_program_enrollment_events'] as $table) if (!$clubProgramTableExists($pdo, $table)) return false;
        return true;
    },
];
