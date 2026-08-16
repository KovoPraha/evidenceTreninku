<?php
declare(strict_types=1);

$registrationTermsColumnExists = static function (PDO $pdo, string $table, string $column): bool {
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

$registrationTermsIndexExists = static function (PDO $pdo, string $index): bool {
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

$registrationTermsSeeds = [
    'member_data_notice' => [
        'member-data-notice-2026-08-16-v1',
        'Beru na vědomí, že Cyklistický klub KOVO Praha zpracuje údaje uvedené v žádosti za účelem posouzení registrace, evidence členství a komunikace s členem nebo jeho zákonným zástupcem. Údaje nejsou zveřejňovány. Předběžné retenční lhůty jsou uvedeny v informaci klubu a budou před produkční aktivací potvrzeny podle konkrétního právního titulu.',
    ],
    'birth_number_legal_notice' => [
        'birth-number-legal-notice-2026-08-16-v1',
        'Beru na vědomí zpracování českého rodného čísla pro zákonem vyžadovanou evidenci klubu. Rodné číslo je přístupné pouze administrátorům, není součástí exportů a je uloženo šifrovaně. U cizince, kterému české rodné číslo nebylo přiděleno, se žádné náhradní číslo nevytváří. Konkrétní právní zdroj a konečná retenční lhůta budou doplněny před produkční aktivací.',
    ],
    'photo_internal' => [
        'photo-internal-2026-08-16-v1',
        'Souhlasím s uložením fotografie v neveřejné evidenci klubu pro interní identifikaci sportovce. Fotografie je volitelná, přístupná pouze administrátorům a není tímto souhlasem určena ke zveřejnění.',
    ],
    'photo_public' => [
        'photo-public-2026-08-16-v1',
        'Souhlasím se zveřejněním fotografie sportovce v komunikačních a prezentačních kanálech klubu. Tento volitelný souhlas není podmínkou členství a lze jej odvolat; interní fotografie se tím automaticky nezveřejňuje.',
    ],
];

return [
    'id' => '20260816180000_registration_terms_scope',
    'up' => static function (PDO $pdo) use (
        $registrationTermsColumnExists,
        $registrationTermsIndexExists,
        $registrationTermsSeeds
    ): void {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            foreach ([
                'scope_type' => "VARCHAR(32) NULL AFTER id",
                'scope_key' => "VARCHAR(128) NULL AFTER scope_type",
                'consent_purpose' => "VARCHAR(64) NULL AFTER scope_key",
                'actor_type' => "VARCHAR(24) NOT NULL DEFAULT 'trainer' AFTER actor_trainer_id",
                'actor_id' => 'INT NULL AFTER actor_type',
            ] as $column => $definition) {
                if (!$registrationTermsColumnExists($pdo, 'club_event_term_versions', $column)) {
                    $pdo->exec('ALTER TABLE club_event_term_versions ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
            $pdo->exec(
                "UPDATE club_event_term_versions SET scope_type='club_event',"
                . "scope_key=CONCAT('event:',event_id),consent_purpose='club_event_registration',"
                . "actor_type='trainer',actor_id=actor_trainer_id "
                . 'WHERE scope_type IS NULL OR scope_key IS NULL OR consent_purpose IS NULL OR actor_id IS NULL'
            );
            $pdo->exec(<<<'SQL'
                ALTER TABLE club_event_term_versions
                    MODIFY event_id BIGINT UNSIGNED NULL,
                    MODIFY cancellation_policy_plain TEXT NULL,
                    MODIFY cancellation_deadline_at DATETIME NULL,
                    MODIFY actor_trainer_id INT NULL,
                    MODIFY scope_type VARCHAR(32) NOT NULL,
                    MODIFY scope_key VARCHAR(128) NOT NULL,
                    MODIFY consent_purpose VARCHAR(64) NOT NULL
                SQL);
            if (!$registrationTermsIndexExists($pdo, 'uq_terms_scope_version')) {
                $pdo->exec(
                    'CREATE UNIQUE INDEX uq_terms_scope_version ON club_event_term_versions'
                    . '(scope_type,scope_key,consent_purpose,terms_version)'
                );
            }
        } elseif ($driver === 'sqlite') {
            $columns = [];
            foreach ($pdo->query('PRAGMA table_info(club_event_term_versions)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[(string)$row['name']] = $row;
            }
            $needsRebuild = !isset($columns['scope_type'], $columns['scope_key'], $columns['consent_purpose'], $columns['actor_type'], $columns['actor_id'])
                || (int)($columns['event_id']['notnull'] ?? 1) !== 0
                || (int)($columns['actor_trainer_id']['notnull'] ?? 1) !== 0;
            if ($needsRebuild) {
                $foreignKeys = (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn();
                $pdo->exec('PRAGMA foreign_keys=OFF');
                try {
                    $pdo->exec(<<<'SQL'
                        CREATE TABLE club_event_term_versions_next (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            scope_type TEXT NOT NULL,
                            scope_key TEXT NOT NULL,
                            consent_purpose TEXT NOT NULL,
                            event_id INTEGER NULL,
                            terms_version TEXT NOT NULL,
                            consent_text_plain TEXT NOT NULL,
                            cancellation_policy_plain TEXT NULL,
                            cancellation_deadline_at TEXT NULL,
                            actor_trainer_id INTEGER NULL,
                            actor_type TEXT NOT NULL,
                            actor_id INTEGER NULL,
                            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE (event_id, terms_version),
                            FOREIGN KEY (event_id) REFERENCES club_events (id) ON DELETE RESTRICT,
                            FOREIGN KEY (actor_trainer_id) REFERENCES treneri (id) ON DELETE RESTRICT
                        )
                        SQL);
                    $pdo->exec(<<<'SQL'
                        INSERT INTO club_event_term_versions_next
                            (id,scope_type,scope_key,consent_purpose,event_id,terms_version,
                             consent_text_plain,cancellation_policy_plain,cancellation_deadline_at,
                             actor_trainer_id,actor_type,actor_id,created_at)
                        SELECT id,'club_event','event:' || event_id,'club_event_registration',event_id,
                               terms_version,consent_text_plain,cancellation_policy_plain,
                               cancellation_deadline_at,actor_trainer_id,'trainer',actor_trainer_id,created_at
                        FROM club_event_term_versions
                        SQL);
                    $pdo->exec('DROP TABLE club_event_term_versions');
                    $pdo->exec('ALTER TABLE club_event_term_versions_next RENAME TO club_event_term_versions');
                } finally {
                    if ($foreignKeys === 1) $pdo->exec('PRAGMA foreign_keys=ON');
                }
            }
            if (!$registrationTermsIndexExists($pdo, 'uq_terms_scope_version')) {
                $pdo->exec(
                    'CREATE UNIQUE INDEX uq_terms_scope_version ON club_event_term_versions'
                    . '(scope_type,scope_key,consent_purpose,terms_version)'
                );
            }
        } else {
            throw new RuntimeException('Unsupported database driver for scoped terms migration.');
        }

        $find = $pdo->prepare(
            'SELECT id FROM club_event_term_versions '
            . 'WHERE scope_type=? AND scope_key=? AND consent_purpose=? AND terms_version=? LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO club_event_term_versions '
            . '(scope_type,scope_key,consent_purpose,event_id,terms_version,consent_text_plain,'
            . 'cancellation_policy_plain,cancellation_deadline_at,actor_trainer_id,actor_type,actor_id) '
            . "VALUES ('athlete_registration','athlete-registration-v1',?,NULL,?,?,NULL,NULL,NULL,'system',0)"
        );
        foreach ($registrationTermsSeeds as $purpose => [$version, $text]) {
            $find->execute(['athlete_registration', 'athlete-registration-v1', $purpose, $version]);
            if ($find->fetchColumn() === false) $insert->execute([$purpose, $version, $text]);
        }
    },
    'verify' => static function (PDO $pdo) use ($registrationTermsColumnExists, $registrationTermsSeeds): bool {
        foreach (['scope_type', 'scope_key', 'consent_purpose', 'actor_type', 'actor_id'] as $column) {
            if (!$registrationTermsColumnExists($pdo, 'club_event_term_versions', $column)) return false;
        }
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_term_versions WHERE scope_type='athlete_registration' "
            . "AND scope_key='athlete-registration-v1' AND consent_purpose=? AND terms_version=? "
            . "AND event_id IS NULL AND actor_type='system' AND actor_id=0"
        );
        foreach ($registrationTermsSeeds as $purpose => [$version]) {
            $statement->execute([$purpose, $version]);
            if ((int)$statement->fetchColumn() !== 1) return false;
        }
        return true;
    },
];
