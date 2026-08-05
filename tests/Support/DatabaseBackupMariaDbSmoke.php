<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

$database = getenv('EVIDENCE_BACKUP_SMOKE_DB') ?: 'evidence_backup_smoke_test';
if (preg_match('/\Aevidence_backup_smoke_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_backup_smoke_test'
) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
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
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE TABLE nastaveni(klic VARCHAR(100) PRIMARY KEY,hodnota TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    $pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(255) UNIQUE,heslo VARCHAR(255),role VARCHAR(30),aktivni TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovist(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE individualni_lekce(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec("CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE NOT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'planovany') ENGINE=InnoDB");
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY) ENGINE=InnoDB');
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
        . "define('DB_HOST', '127.0.0.1');\n"
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
    if (($manifest['ownership_contract'] ?? '') !== '2026-08-05.3') {
        throw new RuntimeException('Database backup smoke used an unexpected ownership contract.');
    }
    $required = [
        'club_member_charge_events', 'club_member_charges',
        'family_calendar_feed_events', 'family_calendar_feeds',
        'family_weekly_summaries', 'family_weekly_summary_events', 'family_weekly_summary_preferences',
        'member_charge_reminder_events', 'member_charge_reminder_preferences', 'member_charge_reminders',
        'kis_import_charge_promotion_events', 'kis_import_charge_promotion_items', 'kis_import_charge_promotions',
        'kis_import_payment_rows', 'kis_import_sandbox_events', 'kis_import_sandbox_items', 'kis_import_sandbox_promotions',
        'shop_member_category_rules', 'shop_member_price_events', 'shop_member_product_prices',
    ];
    foreach ($required as $table) {
        if (!array_key_exists($table, $manifest['tables'] ?? [])) {
            throw new RuntimeException('Database backup smoke omitted table: ' . $table);
        }
    }
    echo 'MariaDB database backup smoke OK (' . count($manifest['tables']) . " tables)\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
    if (file_exists($temporaryRoot)) $removeTree($temporaryRoot);
}
