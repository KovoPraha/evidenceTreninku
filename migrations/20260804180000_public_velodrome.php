<?php
declare(strict_types=1);

$publicTableExists = static function (PDO $pdo, string $table): bool {
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

$publicColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $definition) {
        if ((string)$definition['name'] === $column) {
            return true;
        }
    }
    return false;
};

$publicIndexExists = static function (PDO $pdo, string $index): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME=? LIMIT 1'
        );
        $statement->execute([$index]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='index' AND name=? LIMIT 1");
    $statement->execute([$index]);
    return (bool)$statement->fetchColumn();
};

$publicTriggerExists = static function (PDO $pdo, string $trigger): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TRIGGERS '
            . 'WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=? LIMIT 1'
        );
        $statement->execute([$trigger]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='trigger' AND name=? LIMIT 1");
    $statement->execute([$trigger]);
    return (bool)$statement->fetchColumn();
};

return [
    'id' => '20260804180000_public_velodrome',
    'up' => static function (PDO $pdo) use (
        $publicTableExists,
        $publicColumnExists,
        $publicIndexExists,
        $publicTriggerExists
    ): void {
        foreach ([
            'sportovci', 'verejni_uzivatele', 'sportovist', 'individualni_lekce', 'verejne_rezervace',
            'treneri', 'account_person_roles', 'account_person_role_events',
        ] as $required) {
            if (!$publicTableExists($pdo, $required)) {
                throw new RuntimeException('Required public velodrome table is missing: ' . $required);
            }
        }
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if (!$publicTableExists($pdo, 'public_profile_settings')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_profile_settings (
                    singleton_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                    system_trainer_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_public_profile_system_trainer FOREIGN KEY (system_trainer_id)
                        REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_profile_settings (
                    singleton_id INTEGER NOT NULL PRIMARY KEY,
                    system_trainer_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (system_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
        }
        $systemTrainer = $pdo->prepare('SELECT id,jmeno,role,aktivni FROM treneri WHERE email=? ORDER BY id LIMIT 1');
        $systemTrainer->execute(['system.public-profile@localhost.invalid']);
        $systemTrainerRow = $systemTrainer->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($systemTrainerRow !== null
            && ((string)$systemTrainerRow['jmeno'] !== 'Automat veřejných profilů'
                || (string)$systemTrainerRow['role'] !== 'trener'
                || (int)$systemTrainerRow['aktivni'] !== 0)
        ) {
            throw new RuntimeException('Public profile system trainer identity conflict.');
        }
        $systemTrainerId = (int)($systemTrainerRow['id'] ?? 0);
        if ($systemTrainerId < 1) {
            $insertSystemTrainer = $pdo->prepare(
                "INSERT INTO treneri(jmeno,email,heslo,role,aktivni) VALUES (?,?,?,'trener',0)"
            );
            $insertSystemTrainer->execute([
                'Automat veřejných profilů',
                'system.public-profile@localhost.invalid',
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            ]);
            $systemTrainerId = (int)$pdo->lastInsertId();
        }
        $settings = $pdo->prepare('SELECT system_trainer_id FROM public_profile_settings WHERE singleton_id=1');
        $settings->execute();
        $storedSystemTrainerId = (int)$settings->fetchColumn();
        if ($storedSystemTrainerId < 1) {
            $pdo->prepare(
                'INSERT INTO public_profile_settings(singleton_id,system_trainer_id) VALUES (1,?)'
            )->execute([$systemTrainerId]);
        } elseif ($storedSystemTrainerId !== $systemTrainerId) {
            throw new RuntimeException('Public profile system trainer identity conflict.');
        }
        if (!$publicTableExists($pdo, 'public_self_profiles')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_self_profiles (
                    account_id INT NOT NULL PRIMARY KEY,
                    sportovec_id INT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_public_self_sportovec (sportovec_id),
                    CONSTRAINT fk_public_self_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_self_sportovec FOREIGN KEY (sportovec_id)
                        REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_self_profiles (
                    account_id INTEGER NOT NULL PRIMARY KEY,
                    sportovec_id INTEGER NOT NULL UNIQUE,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$publicTableExists($pdo, 'public_profile_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_profile_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    sportovec_id INT NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    payload_json LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_public_profile_event (account_id,id),
                    CONSTRAINT fk_public_profile_event_account FOREIGN KEY (account_id)
                        REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_public_profile_event_person FOREIGN KEY (sportovec_id)
                        REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_profile_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER NOT NULL,
                    sportovec_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$publicIndexExists($pdo, 'idx_public_profile_event')) {
            $pdo->exec('CREATE INDEX idx_public_profile_event ON public_profile_events(account_id,id)');
        }
        foreach ([
            ['individualni_lekce', 'public_exclusive_booking', $mysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0'],
            ['verejne_rezervace', 'sportovec_id', $mysql ? 'INT NULL' : 'INTEGER NULL REFERENCES sportovci(id) ON DELETE RESTRICT'],
            ['verejne_rezervace', 'active_token', $mysql ? 'VARCHAR(16) NULL' : 'TEXT NULL'],
        ] as [$table, $column, $definition]) {
            if (!$publicColumnExists($pdo, $table, $column)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            }
        }
        if ($mysql) {
            $foreignKey = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
                . "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='verejne_rezervace' "
                . "AND CONSTRAINT_NAME='fk_public_booking_person' LIMIT 1"
            );
            $foreignKey->execute();
            if (!$foreignKey->fetchColumn()) {
                $pdo->exec(
                    'ALTER TABLE verejne_rezervace ADD CONSTRAINT fk_public_booking_person '
                    . 'FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT'
                );
            }
        }
        foreach ([
            'uq_public_booking_active_person' => '(lekce_id,sportovec_id,active_token)',
            'idx_public_booking_capacity' => '(lekce_id,slot_cas_od,stav,id)',
        ] as $index => $columns) {
            if (!$publicIndexExists($pdo, $index)) {
                $unique = $index === 'uq_public_booking_active_person' ? 'UNIQUE ' : '';
                $pdo->exec("CREATE {$unique}INDEX $index ON verejne_rezervace$columns");
            }
        }
        if (!$publicTableExists($pdo, 'public_velodrome_reservation_events')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE public_velodrome_reservation_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    reservation_id INT NOT NULL,
                    actor_type VARCHAR(16) NOT NULL,
                    actor_id INT NULL,
                    action VARCHAR(32) NOT NULL,
                    from_status VARCHAR(24) NULL,
                    to_status VARCHAR(24) NOT NULL,
                    note VARCHAR(1000) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_public_velodrome_event (reservation_id,id),
                    CONSTRAINT fk_public_velodrome_event_reservation FOREIGN KEY (reservation_id)
                        REFERENCES verejne_rezervace(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE public_velodrome_reservation_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    reservation_id INTEGER NOT NULL,
                    actor_type TEXT NOT NULL,
                    actor_id INTEGER NULL,
                    action TEXT NOT NULL,
                    from_status TEXT NULL,
                    to_status TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (reservation_id) REFERENCES verejne_rezervace(id) ON DELETE RESTRICT
                )
                SQL);
        }
        if (!$publicIndexExists($pdo, 'idx_public_velodrome_event')) {
            $pdo->exec(
                'CREATE INDEX idx_public_velodrome_event '
                . 'ON public_velodrome_reservation_events(reservation_id,id)'
            );
        }
        if (!$publicTriggerExists($pdo, 'trg_public_velodrome_legacy_close')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TRIGGER trg_public_velodrome_legacy_close
                BEFORE UPDATE ON verejne_rezervace
                FOR EACH ROW
                BEGIN
                    IF OLD.sportovec_id IS NOT NULL
                       AND OLD.active_token='active'
                       AND NEW.active_token='active'
                       AND NEW.stav IN ('zrusena','zamitnuta') THEN
                        SET NEW.active_token=NULL;
                        INSERT INTO public_velodrome_reservation_events
                            (reservation_id,actor_type,actor_id,action,from_status,to_status,note)
                        VALUES
                            (OLD.id,'legacy',NULL,'legacy_close',OLD.stav,NEW.stav,
                             'Stav změněn starším rezervačním průchodem; aktivní token byl bezpečně uvolněn.');
                    END IF;
                END
                SQL : <<<'SQL'
                CREATE TRIGGER trg_public_velodrome_legacy_close
                AFTER UPDATE ON verejne_rezervace
                FOR EACH ROW
                WHEN OLD.sportovec_id IS NOT NULL
                 AND OLD.active_token='active'
                 AND NEW.active_token='active'
                 AND NEW.stav IN ('zrusena','zamitnuta')
                BEGIN
                    UPDATE verejne_rezervace SET active_token=NULL WHERE id=NEW.id;
                    INSERT INTO public_velodrome_reservation_events
                        (reservation_id,actor_type,actor_id,action,from_status,to_status,note)
                    VALUES
                        (OLD.id,'legacy',NULL,'legacy_close',OLD.stav,NEW.stav,
                         'Stav změněn starším rezervačním průchodem; aktivní token byl bezpečně uvolněn.');
                END
                SQL);
        }
    },
    'verify' => static function (PDO $pdo) use (
        $publicTableExists,
        $publicColumnExists,
        $publicIndexExists,
        $publicTriggerExists
    ): bool {
        return $publicTableExists($pdo, 'public_self_profiles')
            && $publicTableExists($pdo, 'public_profile_settings')
            && $publicTableExists($pdo, 'public_profile_events')
            && $publicTableExists($pdo, 'public_velodrome_reservation_events')
            && $publicColumnExists($pdo, 'individualni_lekce', 'public_exclusive_booking')
            && $publicColumnExists($pdo, 'verejne_rezervace', 'sportovec_id')
            && $publicColumnExists($pdo, 'verejne_rezervace', 'active_token')
            && $publicIndexExists($pdo, 'uq_public_booking_active_person')
            && $publicIndexExists($pdo, 'idx_public_booking_capacity')
            && $publicIndexExists($pdo, 'idx_public_velodrome_event')
            && $publicTriggerExists($pdo, 'trg_public_velodrome_legacy_close');
    },
];
