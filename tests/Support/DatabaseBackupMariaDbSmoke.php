<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

$database = getenv('EVIDENCE_BACKUP_SMOKE_DB') ?: 'evidence_backup_smoke_test';
if (preg_match('/\Aevidence_backup_smoke_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_backup_smoke_test'
) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (preg_match('/\A[1-9][0-9]{0,4}\z/', $port) !== 1 || (int)$port > 65535) {
    throw new RuntimeException('Invalid MariaDB smoke port.');
}
$hostAndPort = $host . ';port=' . $port;

$server = new PDO('mysql:host=' . $hostAndPort . ';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$restoreDatabase = $database . '_restore';
$quotedRestoreDatabase = '`' . str_replace('`', '``', $restoreDatabase) . '`';
$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'evidence-backup-smoke-' . bin2hex(random_bytes(6));
$appRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'app';
$backupRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'backups';

$removeTree = static function (string $path) use (&$removeTree, $temporaryRoot): void {
    if (!str_starts_with($path, $temporaryRoot)) throw new RuntimeException('Unsafe smoke cleanup path.');
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        if (!unlink($path)) throw new RuntimeException('Cannot remove smoke file.');
        return;
    }
    foreach (new FilesystemIterator($path) as $item) $removeTree($item->getPathname());
    if (!rmdir($path)) throw new RuntimeException('Cannot remove smoke directory.');
};

$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = new PDO('mysql:host=' . $hostAndPort . ';dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE TABLE nastaveni(klic VARCHAR(100) PRIMARY KEY,hodnota TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    // AUTO_INCREMENT odpovídá skutečnému legacy schématu; migrace veřejného
    // velodromu vkládá systémového trenéra bez explicitního id a v CI běží
    // MariaDB se strict mode (na rozdíl od výchozího XAMPP).
    $pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(255) UNIQUE,heslo VARCHAR(255),role VARCHAR(30),aktivni TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovist(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE individualni_lekce(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE NOT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'planovany') ENGINE=InnoDB");
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE mereni_zaznamy(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INT NOT NULL,sportovec_id INT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,aktivni TINYINT NOT NULL DEFAULT 1,verifikacni_token VARCHAR(255) NULL,registrovan DATETIME NOT NULL) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE verejne_rezervace(id INT PRIMARY KEY,lekce_id INT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'ceka',slot_cas_od TIME NULL,potvrzovaci_token VARCHAR(255) NULL,cas_rezervace DATETIME NOT NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE kis_import_runs(id INT AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_by INT NULL,status ENUM('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview',source_users VARCHAR(255),source_payments VARCHAR(255),source_rosters VARCHAR(255),stats_json LONGTEXT,warnings_json LONGTEXT,applied_at DATETIME,note TEXT) ENGINE=InnoDB");
    $pdo->exec('CREATE TABLE kis_import_rows(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,person_key VARCHAR(180) NOT NULL,jmeno VARCHAR(100) NOT NULL,prijmeni VARCHAR(160) NOT NULL,narozeni DATE,email VARCHAR(255),uciid VARCHAR(80),oddil VARCHAR(160),kis_aktivni TINYINT NOT NULL DEFAULT 0,kis_platebne_aktivni TINYINT NOT NULL DEFAULT 0,kis_neuhrazeno DECIMAL(10,2) NOT NULL DEFAULT 0,kis_posledni_uhrada DATE,kis_soupisky TEXT,raw_json LONGTEXT) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE kis_import_matches(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,row_id INT NOT NULL,sportovec_id INT,match_status ENUM('new','matched','ambiguous','conflict','ignored') NOT NULL,confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,reason VARCHAR(255),candidate_json LONGTEXT,resolved_by INT,resolved_at DATETIME,resolved_action ENUM('create','update','link','ignore','manual')) ENGINE=InnoDB");

    $catalog = EvidenceMigrationCatalog::load(dirname(__DIR__, 2) . '/migrations');
    $runner = new EvidenceMigrationRunner($pdo, $catalog);
    $result = $runner->apply();
    if (!$result['current']) throw new RuntimeException('MariaDB migration catalog is not current before backup smoke.');

    if (!mkdir($appRoot, 0700, true) || !mkdir($backupRoot, 0700, true)) {
        throw new RuntimeException('Cannot create backup smoke directories.');
    }
    $config = "<?php\n"
        . "define('DB_HOST', " . var_export($hostAndPort, true) . ");\n"
        . "define('DB_NAME', " . var_export($database, true) . ");\n"
        . "define('DB_USER', 'root');\n"
        . "define('DB_PASS', '');\n";
    if (file_put_contents($appRoot . DIRECTORY_SEPARATOR . 'config.php', $config, LOCK_EX) === false) {
        throw new RuntimeException('Cannot create backup smoke config.');
    }

    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/db-backup.php')
        . ' --app-root=' . escapeshellarg($appRoot)
        . ' --backup-dir=' . escapeshellarg($backupRoot)
        . ' --keep=1 --json';
    $pipes = [];
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start database backup smoke process.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) throw new RuntimeException('Database backup smoke failed: ' . trim((string)$stderr . ' ' . (string)$stdout));
    $payload = json_decode((string)$stdout, true, 512, JSON_THROW_ON_ERROR);
    if (($payload['ok'] ?? false) !== true) throw new RuntimeException('Database backup smoke did not return success.');

    $manifestPath = $backupRoot . DIRECTORY_SEPARATOR . basename((string)$payload['manifest']);
    $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['ownership_contract'] ?? '') !== '2026-08-24.1') {
        throw new RuntimeException('Database backup smoke used an unexpected ownership contract.');
    }
    $expectedColumnContract = [
        'club_event_term_versions' => ['status', 'archived_at', 'archived_by_trainer_id'],
        'club_program_enrollments' => ['terms_snapshot_json', 'terms_accepted_at', 'terms_accepted_by_account_id'],
        'shop_order_items' => [
            'program_terms_snapshot_json', 'program_terms_accepted_at', 'program_terms_accepted_by_account_id',
        ],
        'shop_products' => [
            'source_candidate_id', 'source_run_id', 'origin', 'created_by_trainer_id',
        ],
        'shop_variants' => ['source_candidate_id', 'origin', 'created_by_trainer_id'],
        'shop_coupons' => ['archived_at'],
        'staff_user_positions' => ['position_code', 'is_default', 'assigned_by_trainer_id'],
        'club_events' => ['activity_kind', 'planning_status', 'visibility', 'participant_fee_minor', 'legacy_race_id'],
    ];
    if (($manifest['owned_column_contract'] ?? null) !== $expectedColumnContract) {
        throw new RuntimeException('Database backup smoke used an unexpected owned column contract.');
    }
    // Katalog je tu úplný, takže snímek musí obsahovat všechny sloupce kontraktu.
    // Manifest zároveň nesmí hlásit sloupec, který v zálohované tabulce není.
    if (($manifest['owned_columns_present'] ?? null) !== $expectedColumnContract) {
        throw new RuntimeException('Database backup smoke did not report the contract columns present in the snapshot.');
    }
    foreach ($manifest['owned_columns_present'] as $contractTable => $contractColumns) {
        $actualColumns = $pdo->query(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='
            . $pdo->quote((string)$contractTable)
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($contractColumns as $contractColumn) {
            if (!in_array($contractColumn, $actualColumns, true)) {
                throw new RuntimeException('Manifest reported a column absent from the snapshot: ' . $contractTable . '.' . $contractColumn);
            }
        }
    }
    $required = [
        'shop_bank_settings', 'shop_bank_settings_events',
        'club_program_events',
        'shop_catalog_admin_events',
        'club_member_charge_events', 'club_member_charges',
        'family_calendar_feed_events', 'family_calendar_feeds',
        'family_weekly_summaries', 'family_weekly_summary_events', 'family_weekly_summary_preferences',
        'member_charge_reminder_events', 'member_charge_reminder_preferences', 'member_charge_reminders',
        'kis_import_charge_promotion_events', 'kis_import_charge_promotion_items', 'kis_import_charge_promotions',
        'kis_import_payment_rows', 'kis_import_sandbox_events', 'kis_import_sandbox_items', 'kis_import_sandbox_promotions',
        'shop_member_category_rules', 'shop_member_price_events', 'shop_member_product_prices',
        'athlete_private_files', 'athlete_registration_consent_snapshots',
        'athlete_registration_request_details', 'osoba_citlive_pristupy', 'osoba_citlive_udaje',
        'club_event_links', 'club_event_people', 'club_event_planned_participants',
        'club_event_vehicle_reservations',
    ];
    foreach ($required as $table) {
        if (!array_key_exists($table, $manifest['tables'] ?? [])) {
            throw new RuntimeException('Database backup smoke omitted table: ' . $table);
        }
    }

    $compressedSql = file_get_contents($backupRoot . DIRECTORY_SEPARATOR . basename((string)$payload['backup']));
    $sql = is_string($compressedSql) ? gzdecode($compressedSql) : false;
    if (!is_string($sql) || !str_contains($sql, '-- EVIDENCE BACKUP COMPLETE')) {
        throw new RuntimeException('Database backup smoke could not decompress a complete SQL dump.');
    }

    $server->exec('DROP DATABASE IF EXISTS ' . $quotedRestoreDatabase);
    $server->exec('CREATE DATABASE ' . $quotedRestoreDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $restore = new PDO('mysql:host=' . $hostAndPort . ';dbname=' . $restoreDatabase . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $delimiterParts = explode("DELIMITER $$\n", $sql, 2);
    $restore->exec($delimiterParts[0]);
    if (isset($delimiterParts[1])) {
        $triggerParts = explode("DELIMITER ;\n", $delimiterParts[1], 2);
        foreach (explode("$$\n", $triggerParts[0]) as $triggerStatement) {
            if (trim($triggerStatement) !== '') $restore->exec($triggerStatement);
        }
        if (isset($triggerParts[1]) && trim($triggerParts[1]) !== '') $restore->exec($triggerParts[1]);
    }

    foreach ($manifest['tables'] as $table => $expectedRows) {
        $restoredRows = (int)$restore->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', (string)$table) . '`')->fetchColumn();
        if ($restoredRows !== (int)$expectedRows) {
            throw new RuntimeException('Database restore row count mismatch for table: ' . $table);
        }
    }
    $restoredTriggers = $restore->query(
        'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME'
    )->fetchAll(PDO::FETCH_COLUMN);
    $expectedTriggers = $manifest['triggers'] ?? [];
    sort($expectedTriggers);
    if ($restoredTriggers !== $expectedTriggers) {
        throw new RuntimeException('Database restore trigger list does not match the backup manifest.');
    }
    echo 'MariaDB database backup and restore smoke OK (' . count($manifest['tables']) . " tables)\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedRestoreDatabase);
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    if (file_exists($temporaryRoot)) $removeTree($temporaryRoot);
}
