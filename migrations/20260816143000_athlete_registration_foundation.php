<?php
declare(strict_types=1);

$athleteRegistrationTableExists = static function (PDO $pdo, string $table): bool {
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

$athleteRegistrationColumnExists = static function (PDO $pdo, string $table, string $column): bool {
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1'
        );
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)($row['name'] ?? '') === $column) return true;
    }
    return false;
};

$athleteRegistrationIndexExists = static function (PDO $pdo, string $index): bool {
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
    'id' => '20260816143000_athlete_registration_foundation',
    'up' => static function (PDO $pdo) use (
        $athleteRegistrationTableExists,
        $athleteRegistrationColumnExists,
        $athleteRegistrationIndexExists
    ): void {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        if (!$athleteRegistrationColumnExists($pdo, 'account_person_claim_requests', 'request_kind')) {
            $pdo->exec($mysql
                ? "ALTER TABLE account_person_claim_requests ADD COLUMN request_kind VARCHAR(32) NOT NULL DEFAULT 'person_link' AFTER requested_role"
                : "ALTER TABLE account_person_claim_requests ADD COLUMN request_kind TEXT NOT NULL DEFAULT 'person_link'");
        }
        if (!$athleteRegistrationColumnExists($pdo, 'account_person_claim_requests', 'contract_version')) {
            $pdo->exec($mysql
                ? 'ALTER TABLE account_person_claim_requests ADD COLUMN contract_version VARCHAR(64) NULL AFTER request_kind'
                : 'ALTER TABLE account_person_claim_requests ADD COLUMN contract_version TEXT NULL');
        }
        if (!$athleteRegistrationIndexExists($pdo, 'idx_account_claim_kind_status')) {
            $pdo->exec('CREATE INDEX idx_account_claim_kind_status ON account_person_claim_requests(request_kind,status,created_at)');
        }

        if (!$athleteRegistrationTableExists($pdo, 'athlete_registration_request_details')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE athlete_registration_request_details (
                    request_id BIGINT UNSIGNED NOT NULL,
                    submitted_related_sportovec_id INT NULL,
                    has_czech_birth_number TINYINT(1) NOT NULL DEFAULT 1,
                    contact_email_snapshot VARCHAR(255) NOT NULL,
                    contact_phone VARCHAR(50) NOT NULL,
                    citizenship_country_code CHAR(2) NOT NULL DEFAULT 'CZ',
                    address_street VARCHAR(200) NOT NULL,
                    address_house_number VARCHAR(20) NOT NULL,
                    address_orientation_number VARCHAR(20) NULL,
                    address_city VARCHAR(100) NOT NULL,
                    address_postcode VARCHAR(10) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (request_id),
                    KEY idx_athlete_registration_related_person (submitted_related_sportovec_id),
                    CONSTRAINT fk_athlete_registration_detail_request FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_athlete_registration_detail_person FOREIGN KEY (submitted_related_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE athlete_registration_request_details (
                    request_id INTEGER NOT NULL PRIMARY KEY,
                    submitted_related_sportovec_id INTEGER NULL,
                    has_czech_birth_number INTEGER NOT NULL DEFAULT 1,
                    contact_email_snapshot TEXT NOT NULL,
                    contact_phone TEXT NOT NULL,
                    citizenship_country_code TEXT NOT NULL DEFAULT 'CZ',
                    address_street TEXT NOT NULL,
                    address_house_number TEXT NOT NULL,
                    address_orientation_number TEXT NULL,
                    address_city TEXT NOT NULL,
                    address_postcode TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    FOREIGN KEY (submitted_related_sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_athlete_registration_related_person ON athlete_registration_request_details(submitted_related_sportovec_id)');
            }
        }

        if (!$athleteRegistrationTableExists($pdo, 'athlete_registration_consent_snapshots')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE athlete_registration_consent_snapshots (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    request_id BIGINT UNSIGNED NOT NULL,
                    purpose VARCHAR(64) NOT NULL,
                    term_version_id BIGINT UNSIGNED NULL,
                    terms_version VARCHAR(64) NOT NULL,
                    text_snapshot TEXT NOT NULL,
                    accepted TINYINT(1) NOT NULL,
                    accepted_by_account_id INT NOT NULL,
                    accepted_at DATETIME NOT NULL,
                    withdrawn_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_athlete_registration_consent (request_id,purpose),
                    KEY idx_athlete_registration_consent_term (term_version_id),
                    KEY idx_athlete_registration_consent_account (accepted_by_account_id),
                    CONSTRAINT fk_athlete_registration_consent_request FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_athlete_registration_consent_term FOREIGN KEY (term_version_id) REFERENCES club_event_term_versions(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_athlete_registration_consent_account FOREIGN KEY (accepted_by_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE athlete_registration_consent_snapshots (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_id INTEGER NOT NULL,
                    purpose TEXT NOT NULL,
                    term_version_id INTEGER NULL,
                    terms_version TEXT NOT NULL,
                    text_snapshot TEXT NOT NULL,
                    accepted INTEGER NOT NULL,
                    accepted_by_account_id INTEGER NOT NULL,
                    accepted_at TEXT NOT NULL,
                    withdrawn_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE (request_id,purpose),
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    FOREIGN KEY (term_version_id) REFERENCES club_event_term_versions(id) ON DELETE RESTRICT,
                    FOREIGN KEY (accepted_by_account_id) REFERENCES verejni_uzivatele(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_athlete_registration_consent_term ON athlete_registration_consent_snapshots(term_version_id)');
                $pdo->exec('CREATE INDEX idx_athlete_registration_consent_account ON athlete_registration_consent_snapshots(accepted_by_account_id)');
            }
        }

        if (!$athleteRegistrationTableExists($pdo, 'osoba_citlive_udaje')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE osoba_citlive_udaje (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    record_token CHAR(32) NOT NULL,
                    request_id BIGINT UNSIGNED NOT NULL,
                    sportovec_id INT NULL,
                    rc_ciphertext VARBINARY(255) NOT NULL,
                    rc_nonce BINARY(24) NOT NULL,
                    rc_key_version VARCHAR(32) NOT NULL,
                    rc_blind_index BINARY(32) NOT NULL,
                    contract_version VARCHAR(64) NOT NULL DEFAULT 'person-sensitive-v1',
                    status VARCHAR(24) NOT NULL DEFAULT 'pending',
                    retention_reason VARCHAR(255) NULL,
                    retention_until DATETIME NULL,
                    erased_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_osoba_citlive_token (record_token),
                    UNIQUE KEY uq_osoba_citlive_request (request_id),
                    UNIQUE KEY uq_osoba_citlive_person (sportovec_id),
                    UNIQUE KEY uq_osoba_citlive_blind_index (rc_blind_index),
                    CONSTRAINT fk_osoba_citlive_request FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_osoba_citlive_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE osoba_citlive_udaje (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    record_token TEXT NOT NULL UNIQUE,
                    request_id INTEGER NOT NULL UNIQUE,
                    sportovec_id INTEGER NULL UNIQUE,
                    rc_ciphertext BLOB NOT NULL,
                    rc_nonce BLOB NOT NULL,
                    rc_key_version TEXT NOT NULL,
                    rc_blind_index BLOB NOT NULL UNIQUE,
                    contract_version TEXT NOT NULL DEFAULT 'person-sensitive-v1',
                    status TEXT NOT NULL DEFAULT 'pending',
                    retention_reason TEXT NULL,
                    retention_until TEXT NULL,
                    erased_at TEXT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT
                )
                SQL);
        }

        if (!$athleteRegistrationTableExists($pdo, 'athlete_private_files')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE athlete_private_files (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    request_id BIGINT UNSIGNED NOT NULL,
                    sportovec_id INT NULL,
                    file_kind VARCHAR(32) NOT NULL,
                    storage_key VARCHAR(128) NOT NULL,
                    sha256 BINARY(32) NOT NULL,
                    byte_size BIGINT UNSIGNED NOT NULL,
                    mime_type VARCHAR(64) NOT NULL,
                    width_px INT UNSIGNED NOT NULL,
                    height_px INT UNSIGNED NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'active',
                    consent_snapshot_id BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    replaced_at DATETIME NULL,
                    erased_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_athlete_private_storage_key (storage_key),
                    KEY idx_athlete_private_request_status (request_id,status,id),
                    KEY idx_athlete_private_person_status (sportovec_id,status,id),
                    KEY idx_athlete_private_consent (consent_snapshot_id),
                    CONSTRAINT fk_athlete_private_request FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_athlete_private_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_athlete_private_consent FOREIGN KEY (consent_snapshot_id) REFERENCES athlete_registration_consent_snapshots(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE athlete_private_files (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_id INTEGER NOT NULL,
                    sportovec_id INTEGER NULL,
                    file_kind TEXT NOT NULL,
                    storage_key TEXT NOT NULL UNIQUE,
                    sha256 BLOB NOT NULL,
                    byte_size INTEGER NOT NULL,
                    mime_type TEXT NOT NULL,
                    width_px INTEGER NOT NULL,
                    height_px INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    consent_snapshot_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    replaced_at TEXT NULL,
                    erased_at TEXT NULL,
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY (consent_snapshot_id) REFERENCES athlete_registration_consent_snapshots(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_athlete_private_request_status ON athlete_private_files(request_id,status,id)');
                $pdo->exec('CREATE INDEX idx_athlete_private_person_status ON athlete_private_files(sportovec_id,status,id)');
                $pdo->exec('CREATE INDEX idx_athlete_private_consent ON athlete_private_files(consent_snapshot_id)');
            }
        }

        if (!$athleteRegistrationTableExists($pdo, 'osoba_citlive_pristupy')) {
            $pdo->exec($mysql ? <<<'SQL'
                CREATE TABLE osoba_citlive_pristupy (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    sensitive_record_id BIGINT UNSIGNED NULL,
                    private_file_id BIGINT UNSIGNED NULL,
                    sportovec_id INT NULL,
                    request_id BIGINT UNSIGNED NULL,
                    actor_trainer_id INT NOT NULL,
                    action VARCHAR(32) NOT NULL,
                    reason VARCHAR(1000) NOT NULL DEFAULT '',
                    ip_address VARCHAR(45) NOT NULL DEFAULT '',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_osoba_citlive_access_record (sensitive_record_id,id),
                    KEY idx_osoba_citlive_access_file (private_file_id,id),
                    KEY idx_osoba_citlive_access_person (sportovec_id,id),
                    KEY idx_osoba_citlive_access_request (request_id,id),
                    KEY idx_osoba_citlive_access_actor (actor_trainer_id,id),
                    CONSTRAINT fk_osoba_citlive_access_record FOREIGN KEY (sensitive_record_id) REFERENCES osoba_citlive_udaje(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_osoba_citlive_access_file FOREIGN KEY (private_file_id) REFERENCES athlete_private_files(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_osoba_citlive_access_person FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_osoba_citlive_access_request FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_osoba_citlive_access_actor FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL : <<<'SQL'
                CREATE TABLE osoba_citlive_pristupy (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sensitive_record_id INTEGER NULL,
                    private_file_id INTEGER NULL,
                    sportovec_id INTEGER NULL,
                    request_id INTEGER NULL,
                    actor_trainer_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    reason TEXT NOT NULL DEFAULT '',
                    ip_address TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sensitive_record_id) REFERENCES osoba_citlive_udaje(id) ON DELETE RESTRICT,
                    FOREIGN KEY (private_file_id) REFERENCES athlete_private_files(id) ON DELETE RESTRICT,
                    FOREIGN KEY (sportovec_id) REFERENCES sportovci(id) ON DELETE RESTRICT,
                    FOREIGN KEY (request_id) REFERENCES account_person_claim_requests(id) ON DELETE RESTRICT,
                    FOREIGN KEY (actor_trainer_id) REFERENCES treneri(id) ON DELETE RESTRICT
                )
                SQL);
            if (!$mysql) {
                $pdo->exec('CREATE INDEX idx_osoba_citlive_access_record ON osoba_citlive_pristupy(sensitive_record_id,id)');
                $pdo->exec('CREATE INDEX idx_osoba_citlive_access_file ON osoba_citlive_pristupy(private_file_id,id)');
                $pdo->exec('CREATE INDEX idx_osoba_citlive_access_person ON osoba_citlive_pristupy(sportovec_id,id)');
                $pdo->exec('CREATE INDEX idx_osoba_citlive_access_request ON osoba_citlive_pristupy(request_id,id)');
                $pdo->exec('CREATE INDEX idx_osoba_citlive_access_actor ON osoba_citlive_pristupy(actor_trainer_id,id)');
            }
        }
    },
    'verify' => static function (PDO $pdo) use (
        $athleteRegistrationTableExists,
        $athleteRegistrationColumnExists,
        $athleteRegistrationIndexExists
    ): bool {
        foreach ([
            'athlete_registration_request_details',
            'athlete_registration_consent_snapshots',
            'athlete_private_files',
            'osoba_citlive_udaje',
            'osoba_citlive_pristupy',
        ] as $table) {
            if (!$athleteRegistrationTableExists($pdo, $table)) return false;
        }
        foreach ([
            ['account_person_claim_requests', 'request_kind'],
            ['account_person_claim_requests', 'contract_version'],
            ['athlete_registration_request_details', 'has_czech_birth_number'],
            ['athlete_registration_consent_snapshots', 'purpose'],
            ['athlete_private_files', 'storage_key'],
            ['osoba_citlive_udaje', 'rc_ciphertext'],
            ['osoba_citlive_udaje', 'rc_blind_index'],
            ['osoba_citlive_pristupy', 'actor_trainer_id'],
        ] as [$table, $column]) {
            if (!$athleteRegistrationColumnExists($pdo, $table, $column)) return false;
        }
        return $athleteRegistrationIndexExists($pdo, 'idx_account_claim_kind_status');
    },
];
