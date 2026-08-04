<?php
declare(strict_types=1);

/**
 * Fail-closed database backup for the Evidence application.
 *
 * This file is deliberately self-contained: the deploy workflow uploads it to
 * ~/.evidence-deploy before changing the application directory.
 *
 * Usage:
 *   APP_HOST=data.kovopraha.cz php db-backup.php \
 *     --app-root=/path/to/evidence --backup-dir=/path/outside/webroot --json
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0077);
set_time_limit(0);

const EVIDENCE_BACKUP_FORMAT_VERSION = 1;
const EVIDENCE_OWNERSHIP_CONTRACT_VERSION = '2026-08-04.7';

/**
 * This is the ownership boundary in the shared database. A new Evidence table
 * must be added here in the same change that creates it. In particular,
 * jidlo_*, bar_*, results_* and legacy/archive tables are not owned here.
 */
const EVIDENCE_TABLES = [
    'account_person_claim_events',
    'account_person_claim_requests',
    'account_person_role_events',
    'account_person_roles',
    'auth_login_limits',
    'club_event_admin_events',
    'club_event_cart_items',
    'club_event_notification_events',
    'club_event_notifications',
    'club_event_order_items',
    'club_event_registration_events',
    'club_event_registrations',
    'club_event_roster_targets',
    'club_event_sessions',
    'club_event_term_versions',
    'club_events',
    'club_program_enrollment_events',
    'club_program_enrollments',
    'club_program_offers',
    'club_programs',
    'club_roster_events',
    'club_roster_members',
    'club_roster_rollover_exception_events',
    'club_roster_rollover_exceptions',
    'club_roster_rollover_run_items',
    'club_roster_rollover_runs',
    'club_seasons',
    'club_team_series',
    'club_teams',
    'child_access_accounts',
    'child_access_events',
    'cviky',
    'dalsi_cinnosti',
    'email_log',
    'evidence_schema_migrations',
    'fio_account_movements',
    'fio_import_runs',
    'fotky',
    'gs_kategorie',
    'gs_link_targets',
    'gs_linky',
    'individualni_lekce',
    'kis_import_matches',
    'kis_import_rows',
    'kis_import_runs',
    'kis_import_source_artifacts',
    'mereni',
    'mereni_zaznamy',
    'nastaveni',
    'opravneni',
    'oznameni',
    'oznameni_targets',
    'payments',
    'planovane_treninky',
    'planovane_treninky_podskupiny',
    'podskupiny',
    'push_subscriptions',
    'public_profile_events',
    'public_profile_settings',
    'public_self_profiles',
    'public_velodrome_cart_items',
    'public_velodrome_order_items',
    'public_velodrome_reservation_events',
    'rezervace_sportovist',
    'segmenty',
    'shop_cart_items',
    'shop_carts',
    'shop_catalog_import_runs',
    'shop_catalog_product_candidates',
    'shop_catalog_promotions',
    'shop_catalog_review_events',
    'shop_catalog_variant_candidates',
    'shop_coupon_events',
    'shop_coupon_redemptions',
    'shop_coupons',
    'shop_inventory_movements',
    'shop_order_events',
    'shop_order_items',
    'shop_orders',
    'shop_product_categories',
    'shop_product_event_links',
    'shop_product_images',
    'shop_product_publication_events',
    'shop_product_publications',
    'shop_products',
    'shop_variants',
    'skupiny',
    'soupiska_mapping',
    'sportovci',
    'sportovec_history',
    'sportovec_interni_poznamka',
    'sportovec_obdobi',
    'sportovec_podskupina',
    'sportovec_poznamka',
    'sportovec_skupina',
    'sportovist',
    'story_nastaveni',
    'story_vygenerovane',
    'tagy',
    'treneri',
    'treninky',
    'trenink_mereni',
    'trenink_podskupina',
    'trenink_skupina',
    'trenink_sportovec',
    'trenink_tag',
    'trenink_trener',
    'training_roster_expected',
    'training_roster_links',
    'ucto_audit_log',
    'ucto_dokumenty',
    'ucto_gs_kategorie',
    'ucto_gs_link_targets',
    'ucto_gs_linky',
    'ucto_jizdy',
    'ucto_servis',
    'ucto_tankovani',
    'ucto_uctenky',
    'ucto_udalosti',
    'ucto_vozidla',
    'verejne_rezervace',
    'verejni_uzivatele',
    'zatezove_testy',
    'zatezove_testy_soubory',
    'zavody',
    'zavod_fotka',
    'zavod_import',
    'zavod_mereni',
    'zavod_podskupina',
    'zavod_skupina',
    'zavod_sportovec',
    'zavod_trener',
];

const EVIDENCE_REQUIRED_TABLES = ['nastaveni', 'sportovci', 'treneri', 'treninky'];

function outputResult(array $payload, bool $json): void
{
    if ($json) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        return;
    }

    foreach ($payload as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_bool($value)) {
            $value = $value ? 'ano' : 'ne';
        }
        echo $key . ': ' . (string)$value . PHP_EOL;
    }
}

function failBackup(string $message, bool $json, array $cleanupFiles = []): never
{
    foreach ($cleanupFiles as $file) {
        $name = is_string($file) ? basename($file) : '';
        if (
            is_string($file)
            && preg_match('/^evidence_[0-9A-Za-z_-]+\.(?:sql\.gz|sha256|manifest\.json)(?:\.partial)?$/', $name) === 1
            && is_file($file)
        ) {
            @unlink($file);
        }
    }
    outputResult(['ok' => false, 'error' => $message], $json);
    exit(1);
}

function quoteIdentifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Nepovolený SQL identifikátor.');
    }
    return '`' . $identifier . '`';
}

/** @param resource $gz */
function gzWriteAll($gz, string $data): void
{
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        $written = gzwrite($gz, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Zápis komprimované zálohy selhal.');
        }
        $offset += $written;
    }
}

function sqlValue(PDO $pdo, mixed $value, bool $binary): string
{
    if ($value === null) {
        return 'NULL';
    }
    if ($binary) {
        return '0x' . bin2hex((string)$value);
    }
    $quoted = $pdo->quote((string)$value);
    if ($quoted === false) {
        throw new RuntimeException('Databázový ovladač nedokázal bezpečně zapsat hodnotu.');
    }
    return $quoted;
}

function atomicTextFile(string $partial, string $final, string $contents): void
{
    if (file_put_contents($partial, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Nelze zapsat doprovodný soubor zálohy.');
    }
    if (!chmod($partial, 0600)) {
        throw new RuntimeException('Nelze nastavit bezpečná práva doprovodného souboru.');
    }
    if (!rename($partial, $final)) {
        throw new RuntimeException('Nelze atomicky dokončit doprovodný soubor zálohy.');
    }
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/')
        || str_starts_with($path, '\\\\')
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

$options = getopt('', ['app-root:', 'backup-dir:', 'keep::', 'json']);
$json = array_key_exists('json', $options);
$appRoot = isset($options['app-root']) ? realpath((string)$options['app-root']) : false;
$backupDirRaw = isset($options['backup-dir']) ? (string)$options['backup-dir'] : '';
$keep = isset($options['keep']) ? filter_var($options['keep'], FILTER_VALIDATE_INT) : 20;

if ($appRoot === false || !is_dir($appRoot)) {
    failBackup('Parametr --app-root musí ukazovat na existující adresář aplikace.', $json);
}
if ($backupDirRaw === '' || !isAbsolutePath($backupDirRaw)) {
    failBackup('Parametr --backup-dir musí být absolutní cesta mimo webový adresář.', $json);
}
if ($keep === false || $keep < 1 || $keep > 200) {
    failBackup('Parametr --keep musí být celé číslo od 1 do 200.', $json);
}

$configPath = $appRoot . DIRECTORY_SEPARATOR . 'config.php';
if (!is_file($configPath)) {
    failBackup('V aplikaci chybí config.php.', $json);
}

if (!is_dir($backupDirRaw) && !mkdir($backupDirRaw, 0700, true) && !is_dir($backupDirRaw)) {
    failBackup('Nelze vytvořit adresář záloh.', $json);
}
$backupDir = realpath($backupDirRaw);
if ($backupDir === false || !is_dir($backupDir) || !is_writable($backupDir)) {
    failBackup('Adresář záloh není dostupný pro zápis.', $json);
}
if ($backupDir === $appRoot || str_starts_with($backupDir . DIRECTORY_SEPARATOR, $appRoot . DIRECTORY_SEPARATOR)) {
    failBackup('Adresář záloh musí ležet mimo webový adresář aplikace.', $json);
}
if (!chmod($backupDir, 0700)) {
    failBackup('Nelze nastavit práva 0700 na adresáři záloh.', $json);
}

$appHost = trim((string)getenv('APP_HOST'));
if ($appHost !== '') {
    $_SERVER['HTTP_HOST'] = $appHost;
    $_SERVER['SERVER_NAME'] = preg_replace('/:\d+$/', '', $appHost) ?: $appHost;
}

require $configPath;
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        failBackup("V config.php chybí konstanta {$constant}.", $json);
    }
}

$stamp = gmdate('Y-m-d_His') . '_' . bin2hex(random_bytes(4));
$base = $backupDir . DIRECTORY_SEPARATOR . 'evidence_' . $stamp;
$sqlFinal = $base . '.sql.gz';
$shaFinal = $base . '.sha256';
$manifestFinal = $base . '.manifest.json';
$sqlPartial = $sqlFinal . '.partial';
$shaPartial = $shaFinal . '.partial';
$manifestPartial = $manifestFinal . '.partial';
$partials = [$sqlPartial, $shaPartial, $manifestPartial];
$newFiles = array_merge($partials, [$sqlFinal, $shaFinal, $manifestFinal]);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $placeholders = implode(',', array_fill(0, count(EVIDENCE_TABLES), '?'));
    $tableQuery = $pdo->prepare(
        "SELECT TABLE_NAME, TABLE_TYPE, ENGINE
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders})
          ORDER BY TABLE_NAME"
    );
    $tableQuery->execute(array_merge([(string)DB_NAME], EVIDENCE_TABLES));
    $tableRows = $tableQuery->fetchAll();
    $tableMeta = [];
    foreach ($tableRows as $row) {
        $name = (string)$row['TABLE_NAME'];
        if ((string)$row['TABLE_TYPE'] !== 'BASE TABLE') {
            throw new RuntimeException("Objekt {$name} není základní tabulka; view se zálohovat nebude.");
        }
        if (strcasecmp((string)$row['ENGINE'], 'InnoDB') !== 0) {
            throw new RuntimeException("Tabulka {$name} nepoužívá InnoDB; konzistentní snapshot nelze zaručit.");
        }
        $tableMeta[$name] = $row;
    }
    foreach (EVIDENCE_REQUIRED_TABLES as $required) {
        if (!isset($tableMeta[$required])) {
            throw new RuntimeException("Chybí povinná tabulka {$required}; pravděpodobně je zvolena nesprávná databáze.");
        }
    }
    $tables = array_keys($tableMeta);
    sort($tables, SORT_STRING);

    $foreignKeyQuery = $pdo->prepare(
        "SELECT TABLE_NAME, REFERENCED_TABLE_NAME
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            AND ((TABLE_NAME IN ({$placeholders}) AND REFERENCED_TABLE_NAME NOT IN ({$placeholders}))
              OR (TABLE_NAME NOT IN ({$placeholders}) AND REFERENCED_TABLE_NAME IN ({$placeholders})))
          LIMIT 1"
    );
    $foreignKeyQuery->execute(array_merge(
        [(string)DB_NAME, (string)DB_NAME],
        EVIDENCE_TABLES,
        EVIDENCE_TABLES,
        EVIDENCE_TABLES,
        EVIDENCE_TABLES
    ));
    $crossBoundaryFk = $foreignKeyQuery->fetch();
    if ($crossBoundaryFk) {
        throw new RuntimeException(
            'Cizí klíč překračuje hranici Evidence: '
            . $crossBoundaryFk['TABLE_NAME'] . ' -> ' . $crossBoundaryFk['REFERENCED_TABLE_NAME'] . '.'
        );
    }

    $triggerQuery = $pdo->prepare(
        "SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE
           FROM information_schema.TRIGGERS
          WHERE TRIGGER_SCHEMA = ? AND EVENT_OBJECT_TABLE IN ({$placeholders})
          ORDER BY TRIGGER_NAME"
    );
    $triggerQuery->execute(array_merge([(string)DB_NAME], EVIDENCE_TABLES));
    $triggerRows = $triggerQuery->fetchAll();
    $triggers = [];
    foreach ($triggerRows as $triggerRow) {
        $triggerName = (string)$triggerRow['TRIGGER_NAME'];
        $show = $pdo->query('SHOW CREATE TRIGGER ' . quoteIdentifier($triggerName))->fetch();
        $createTrigger = (string)($show['SQL Original Statement'] ?? $show['Create Trigger'] ?? '');
        if ($createTrigger === '') {
            throw new RuntimeException("Nelze načíst definici triggeru {$triggerName}.");
        }
        $createTrigger = preg_replace('/^CREATE\s+DEFINER\s*=\s*[^ ]+\s+TRIGGER\s+/i', 'CREATE TRIGGER ', $createTrigger, 1) ?? '';
        if (!preg_match('/^CREATE\s+TRIGGER\s+/i', $createTrigger)) {
            throw new RuntimeException("Trigger {$triggerName} má nepodporovanou definici.");
        }
        $triggers[] = ['name' => $triggerName, 'sql' => $createTrigger];
    }

    $schemas = [];
    $columns = [];
    foreach ($tables as $table) {
        $createRow = $pdo->query('SHOW CREATE TABLE ' . quoteIdentifier($table))->fetch(PDO::FETCH_NUM);
        if (!is_array($createRow) || !isset($createRow[1])) {
            throw new RuntimeException("Nelze načíst schéma tabulky {$table}.");
        }
        $schemas[$table] = (string)$createRow[1];

        $columnRows = $pdo->query('SHOW FULL COLUMNS FROM ' . quoteIdentifier($table))->fetchAll();
        $columns[$table] = [];
        foreach ($columnRows as $column) {
            if (stripos((string)($column['Extra'] ?? ''), 'GENERATED') !== false) {
                continue;
            }
            $type = strtolower((string)$column['Type']);
            $columns[$table][] = [
                'name' => (string)$column['Field'],
                'binary' => preg_match('/(?:^|\b)(?:binary|varbinary|blob|tinyblob|mediumblob|longblob)(?:\b|\()/i', $type) === 1,
            ];
        }
    }

    $gz = gzopen($sqlPartial, 'wb6');
    if ($gz === false) {
        throw new RuntimeException('Nelze vytvořit soubor zálohy.');
    }
    if (!chmod($sqlPartial, 0600)) {
        gzclose($gz);
        throw new RuntimeException('Nelze nastavit bezpečná práva souboru zálohy.');
    }

    $rowCounts = [];
    try {
        gzWriteAll($gz, "-- Evidence database backup\n");
        gzWriteAll($gz, '-- Created UTC: ' . gmdate(DATE_ATOM) . "\n");
        gzWriteAll($gz, '-- Ownership contract: ' . EVIDENCE_OWNERSHIP_CONTRACT_VERSION . "\n\n");
        gzWriteAll($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        foreach ($tables as $table) {
            $quotedTable = quoteIdentifier($table);
            gzWriteAll($gz, "DROP TABLE IF EXISTS {$quotedTable};\n{$schemas[$table]};\n\n");

            $columnMeta = $columns[$table];
            if ($columnMeta === []) {
                $rowCounts[$table] = 0;
                continue;
            }
            $quotedColumns = array_map(static fn(array $column): string => quoteIdentifier($column['name']), $columnMeta);
            $columnSql = implode(',', $quotedColumns);
            $statement = $pdo->query("SELECT {$columnSql} FROM {$quotedTable}");
            $batch = [];
            $count = 0;
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                foreach ($columnMeta as $column) {
                    $values[] = sqlValue($pdo, $row[$column['name']] ?? null, (bool)$column['binary']);
                }
                $batch[] = '(' . implode(',', $values) . ')';
                $count++;
                if (count($batch) === 200) {
                    gzWriteAll($gz, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                gzWriteAll($gz, "INSERT INTO {$quotedTable} ({$columnSql}) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzWriteAll($gz, "\n");
            $rowCounts[$table] = $count;
        }
        $pdo->commit();

        if ($triggers !== []) {
            gzWriteAll($gz, "DELIMITER $$\n");
            foreach ($triggers as $trigger) {
                gzWriteAll($gz, 'DROP TRIGGER IF EXISTS ' . quoteIdentifier($trigger['name']) . "$$\n");
                gzWriteAll($gz, $trigger['sql'] . "$$\n");
            }
            gzWriteAll($gz, "DELIMITER ;\n\n");
        }
        gzWriteAll($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- EVIDENCE BACKUP COMPLETE\n");
    } catch (Throwable $writeError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        gzclose($gz);
        throw $writeError;
    }
    if (!gzclose($gz)) {
        throw new RuntimeException('Komprimovaný soubor se nepodařilo uzavřít.');
    }

    $check = gzopen($sqlPartial, 'rb');
    if ($check === false) {
        throw new RuntimeException('Vytvořenou zálohu nelze znovu otevřít.');
    }
    $tail = '';
    while (!gzeof($check)) {
        $chunk = gzread($check, 1024 * 1024);
        if ($chunk === false) {
            gzclose($check);
            throw new RuntimeException('Kontrola komprimované zálohy selhala.');
        }
        $tail = substr($tail . $chunk, -128);
    }
    gzclose($check);
    if (!str_contains($tail, '-- EVIDENCE BACKUP COMPLETE')) {
        throw new RuntimeException('Záloha nemá koncový kontrolní marker.');
    }

    $sha256 = hash_file('sha256', $sqlPartial);
    $size = filesize($sqlPartial);
    if ($sha256 === false || $size === false || $size < 1) {
        throw new RuntimeException('Nelze ověřit kontrolní součet nebo velikost zálohy.');
    }

    $manifest = [
        'format_version' => EVIDENCE_BACKUP_FORMAT_VERSION,
        'application' => 'evidence',
        'created_at_utc' => gmdate(DATE_ATOM),
        'ownership_contract' => EVIDENCE_OWNERSHIP_CONTRACT_VERSION,
        'database_name' => (string)DB_NAME,
        'sql_file' => basename($sqlFinal),
        'sha256' => $sha256,
        'compressed_bytes' => $size,
        'code_revision' => trim((string)getenv('DEPLOY_GIT_SHA')) ?: null,
        'deploy_run_id' => trim((string)getenv('DEPLOY_RUN_ID')) ?: null,
        'tables' => $rowCounts,
        'triggers' => array_column($triggers, 'name'),
    ];
    $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

    if (!rename($sqlPartial, $sqlFinal)) {
        throw new RuntimeException('Nelze atomicky dokončit SQL zálohu.');
    }
    atomicTextFile($shaPartial, $shaFinal, $sha256 . '  ' . basename($sqlFinal) . PHP_EOL);
    atomicTextFile($manifestPartial, $manifestFinal, $manifestJson);

    $oldBackups = glob($backupDir . DIRECTORY_SEPARATOR . 'evidence_*.sql.gz') ?: [];
    rsort($oldBackups, SORT_STRING);
    foreach (array_slice($oldBackups, $keep) as $oldSql) {
        $oldBase = substr($oldSql, 0, -strlen('.sql.gz'));
        @unlink($oldSql);
        @unlink($oldBase . '.sha256');
        @unlink($oldBase . '.manifest.json');
    }

    outputResult([
        'ok' => true,
        'backup' => basename($sqlFinal),
        'manifest' => basename($manifestFinal),
        'sha256' => $sha256,
        'tables' => count($tables),
        'triggers' => count($triggers),
        'compressed_bytes' => $size,
        'location' => $backupDir,
    ], $json);
} catch (Throwable $error) {
    failBackup($error->getMessage(), $json, $newFiles);
}
