<?php
declare(strict_types=1);

$tableExists = static function (PDO $pdo, string $table): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if ((string)($definition['name'] ?? '') === $column) return true;
    }
    return false;
};

$indexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=? LIMIT 1'
        );
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260824120000_club_calendar_planning',
    'up' => static function (PDO $pdo) use ($tableExists, $columnExists, $indexExists): void {
        foreach (['club_events', 'club_event_sessions', 'club_event_registrations', 'club_event_roster_targets',
            'treneri', 'sportovci', 'verejni_uzivatele', 'club_teams',
            'club_member_charges', 'payments'] as $required) {
            if (!$tableExists($pdo, $required)) {
                throw new RuntimeException('Required club calendar table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $hadVisibility = $columnExists($pdo, 'club_events', 'visibility');
        $columns = [
            'activity_kind' => $mysql ? "VARCHAR(24) NOT NULL DEFAULT 'other'" : "TEXT NOT NULL DEFAULT 'other'",
            'planning_status' => $mysql ? "VARCHAR(24) NOT NULL DEFAULT 'confirmed'" : "TEXT NOT NULL DEFAULT 'confirmed'",
            'visibility' => $mysql ? "VARCHAR(24) NOT NULL DEFAULT 'staff'" : "TEXT NOT NULL DEFAULT 'staff'",
            'public_description_plain' => $mysql ? 'TEXT NULL' : 'TEXT NULL',
            'internal_note' => $mysql ? 'TEXT NULL' : 'TEXT NULL',
            'participant_fee_minor' => $mysql ? 'BIGINT UNSIGNED NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
            'fee_due_days' => $mysql ? 'SMALLINT UNSIGNED NOT NULL DEFAULT 14' : 'INTEGER NOT NULL DEFAULT 14',
            'legacy_race_id' => $mysql ? 'INT NULL' : 'INTEGER NULL',
            'public_published_at' => $mysql ? 'DATETIME NULL' : 'TEXT NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$columnExists($pdo, 'club_events', $column)) {
                $pdo->exec('ALTER TABLE club_events ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        $pdo->exec("UPDATE club_events SET activity_kind=CASE WHEN event_type='camp' THEN 'camp' ELSE activity_kind END");
        $pdo->exec("UPDATE club_events SET public_description_plain=description_plain WHERE public_description_plain IS NULL");
        // Existing open offers were public before visibility existed. Preserve that contract;
        // only newly created calendar plans default to staff-only.
        if (!$hadVisibility) {
            $pdo->exec("UPDATE club_events SET visibility='public',public_published_at=COALESCE(public_published_at,CURRENT_TIMESTAMP) WHERE status='open'");
        }
        if (!$indexExists($pdo, 'uq_club_event_legacy_race')) {
            $pdo->exec('CREATE UNIQUE INDEX uq_club_event_legacy_race ON club_events(legacy_race_id)');
        }
        if (!$indexExists($pdo, 'idx_club_event_planning_visibility')) {
            $pdo->exec('CREATE INDEX idx_club_event_planning_visibility ON club_events(planning_status,visibility,status)');
        }

        if (!$tableExists($pdo, 'club_event_people')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_people (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    event_id BIGINT UNSIGNED NOT NULL,
                    person_role VARCHAR(24) NOT NULL,
                    trainer_id INT NULL,
                    external_name VARCHAR(255) NULL,
                    external_contact VARCHAR(255) NULL,
                    visible_to_members TINYINT(1) NOT NULL DEFAULT 0,
                    note VARCHAR(1000) NOT NULL DEFAULT '',
                    created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_club_event_people_event (event_id,id),
                    CONSTRAINT fk_club_event_people_event FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_event_people_trainer FOREIGN KEY(trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_event_people_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_people (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER NOT NULL,person_role TEXT NOT NULL,
                    trainer_id INTEGER NULL,external_name TEXT NULL,external_contact TEXT NULL,
                    visible_to_members INTEGER NOT NULL DEFAULT 0,note TEXT NOT NULL DEFAULT '',
                    created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY(trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            $pdo->exec('CREATE INDEX idx_club_event_people_event ON club_event_people(event_id,id)');
        }

        if (!$tableExists($pdo, 'club_event_links')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_links (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,
                    link_type VARCHAR(24) NOT NULL,label VARCHAR(255) NOT NULL,url VARCHAR(2048) NOT NULL,
                    sort_order INT NOT NULL DEFAULT 0,created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_club_event_links_event (event_id,sort_order,id),
                    CONSTRAINT fk_club_event_links_event FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_event_links_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_links (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER NOT NULL,link_type TEXT NOT NULL,
                    label TEXT NOT NULL,url TEXT NOT NULL,sort_order INTEGER NOT NULL DEFAULT 0,
                    created_by_trainer_id INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            $pdo->exec('CREATE INDEX idx_club_event_links_event ON club_event_links(event_id,sort_order,id)');
        }

        if (!$tableExists($pdo, 'club_event_vehicle_reservations')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_vehicle_reservations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,
                    vehicle_id INT NOT NULL,starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,
                    driver_trainer_id INT NULL,driver_name VARCHAR(255) NULL,note VARCHAR(1000) NOT NULL DEFAULT '',
                    conflict_acknowledged TINYINT(1) NOT NULL DEFAULT 0,conflict_note VARCHAR(1000) NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'active',created_by_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_club_vehicle_time (vehicle_id,status,starts_at,ends_at),
                    KEY idx_club_vehicle_event (event_id,status,id),
                    CONSTRAINT fk_club_vehicle_event FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_vehicle_driver FOREIGN KEY(driver_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_vehicle_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_vehicle_reservations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER NOT NULL,vehicle_id INTEGER NOT NULL,
                    starts_at TEXT NOT NULL,ends_at TEXT NOT NULL,driver_trainer_id INTEGER NULL,driver_name TEXT NULL,
                    note TEXT NOT NULL DEFAULT '',conflict_acknowledged INTEGER NOT NULL DEFAULT 0,
                    conflict_note TEXT NULL,status TEXT NOT NULL DEFAULT 'active',created_by_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY(vehicle_id) REFERENCES ucto_vozidla(id) ON DELETE RESTRICT,
                    FOREIGN KEY(driver_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            $pdo->exec('CREATE INDEX idx_club_vehicle_time ON club_event_vehicle_reservations(vehicle_id,status,starts_at,ends_at)');
            $pdo->exec('CREATE INDEX idx_club_vehicle_event ON club_event_vehicle_reservations(event_id,status,id)');
            if ($mysql && $tableExists($pdo, 'ucto_vozidla')) {
                $pdo->exec('ALTER TABLE club_event_vehicle_reservations ADD CONSTRAINT fk_club_vehicle_vehicle FOREIGN KEY(vehicle_id) REFERENCES ucto_vozidla(id) ON DELETE RESTRICT');
            }
        }

        if (!$tableExists($pdo, 'club_event_planned_participants')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE club_event_planned_participants (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_id BIGINT UNSIGNED NOT NULL,
                    sportovec_id INT NOT NULL,account_id INT NULL,status VARCHAR(24) NOT NULL DEFAULT 'planned',
                    registration_id BIGINT UNSIGNED NULL,charge_id BIGINT UNSIGNED NULL,
                    created_by_trainer_id INT NULL,confirmed_by_trainer_id INT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,confirmed_at DATETIME NULL,cancelled_at DATETIME NULL,
                    UNIQUE KEY uq_club_planned_participant (event_id,sportovec_id),
                    KEY idx_club_planned_status (event_id,status,id),
                    CONSTRAINT fk_club_planned_event FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_person FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_account FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_registration FOREIGN KEY(registration_id) REFERENCES club_event_registrations(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_charge FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_creator FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_club_planned_confirmer FOREIGN KEY(confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE club_event_planned_participants (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,event_id INTEGER NOT NULL,sportovec_id INTEGER NOT NULL,
                    account_id INTEGER NULL,status TEXT NOT NULL DEFAULT 'planned',registration_id INTEGER NULL,
                    charge_id INTEGER NULL,created_by_trainer_id INTEGER NULL,confirmed_by_trainer_id INTEGER NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,confirmed_at TEXT NULL,cancelled_at TEXT NULL,
                    UNIQUE(event_id,sportovec_id),FOREIGN KEY(event_id) REFERENCES club_events(id) ON DELETE RESTRICT,
                    FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY(account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY(registration_id) REFERENCES club_event_registrations(id) ON DELETE RESTRICT,
                    FOREIGN KEY(charge_id) REFERENCES club_member_charges(id) ON DELETE RESTRICT,
                    FOREIGN KEY(created_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT,
                    FOREIGN KEY(confirmed_by_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            $pdo->exec('CREATE INDEX idx_club_planned_status ON club_event_planned_participants(event_id,status,id)');
        }

        // One-time, idempotent bridge: future result records become staff-only confirmed plans.
        if ($tableExists($pdo, 'zavody') && $columnExists($pdo, 'zavody', 'datum')
            && $columnExists($pdo, 'zavody', 'popis') && $columnExists($pdo, 'zavody', 'trener_id')) {
            $races = $pdo->query('SELECT id,datum,popis,trener_id FROM zavody WHERE datum>=CURRENT_DATE ORDER BY id')
                ->fetchAll(PDO::FETCH_ASSOC);
            $exists = $pdo->prepare('SELECT id FROM club_events WHERE legacy_race_id=?');
            $insertEvent = $pdo->prepare(
                "INSERT INTO club_events(code,event_type,name,description_plain,audience_label,min_age,max_age,capacity,"
                . "pricing_policy,currency,registration_starts_at,registration_ends_at,status,created_by_trainer_id,"
                . "activity_kind,planning_status,visibility,public_description_plain,internal_note,participant_fee_minor,"
                . "fee_due_days,legacy_race_id) VALUES(?, 'club_event', ?, ?, 'Kluboví sportovci', NULL, NULL, 10000,"
                . "'free','CZK',NULL,NULL,'draft',?,'race','confirmed','staff',?,NULL,0,14,?)"
            );
            $insertSession = $pdo->prepare(
                "INSERT INTO club_event_sessions(event_id,starts_at,ends_at,location,capacity_override,status) "
                . "VALUES(?,?,?,'Místo bude doplněno',NULL,'scheduled')"
            );
            foreach ($races as $race) {
                $exists->execute([(int)$race['id']]);
                if ($exists->fetchColumn()) continue;
                $name = trim((string)$race['popis']);
                if ($name === '') $name = 'Závod #' . (int)$race['id'];
                $code = 'LEGACY-RACE-' . (int)$race['id'];
                $insertEvent->execute([$code, mb_substr($name, 0, 255, 'UTF-8'), $name,
                    (int)$race['trener_id'], $name, (int)$race['id']]);
                $eventId = (int)$pdo->lastInsertId();
                $date = (string)$race['datum'];
                $insertSession->execute([$eventId, $date . ' 00:00:00', $date . ' 23:59:59']);
            }
        }
    },
    'verify' => static function (PDO $pdo) use ($tableExists, $columnExists, $indexExists): bool {
        foreach (['club_event_people', 'club_event_links', 'club_event_vehicle_reservations',
            'club_event_planned_participants'] as $table) {
            if (!$tableExists($pdo, $table)) return false;
        }
        foreach (['activity_kind', 'planning_status', 'visibility', 'public_description_plain', 'internal_note',
            'participant_fee_minor', 'fee_due_days', 'legacy_race_id', 'public_published_at'] as $column) {
            if (!$columnExists($pdo, 'club_events', $column)) return false;
        }
        return $indexExists($pdo, 'uq_club_event_legacy_race')
            && $indexExists($pdo, 'idx_club_event_planning_visibility');
    },
];
