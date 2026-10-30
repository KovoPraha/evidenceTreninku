<?php

declare(strict_types=1);

require_once __DIR__ . '/RefactoringFixtureRuntime.php';
require_once dirname(__DIR__, 2) . '/includes/accounting_export.php';

$runtime = new RefactoringFixtureRuntime();
$oldPrivate = getenv('APP_PRIVATE_STORAGE_ROOT');
try {
    $pdo = $runtime->create();
    w72Seed($pdo);
    $pdo->exec("INSERT IGNORE INTO staff_user_positions(trainer_id,position_code,is_default,assigned_by_trainer_id) VALUES(7,'finance_manager',0,7)");
    $pdo->exec('INSERT INTO accounting_accounts(id,code,name,kind,currency,opened_on,opening_minor,evidence,reason,actor_trainer_id,recorded_at) '
        . "VALUES(1,'W12','Synthetic','cash','CZK','2026-01-01',0,'Synthetic','Synthetic',7,'2026-01-01')");
    $movement = $pdo->prepare('INSERT INTO accounting_movements(account_id,kind,amount_minor,currency,effective_on,recorded_at,'
        . 'source_context,source_reference,request_key,fingerprint,evidence,reason,actor_trainer_id) '
        . "VALUES(1,'income',100,'CZK','2026-01-10','2026-01-10','synthetic',?,?,?,'Synthetic','Synthetic',7)");
    $charge = $pdo->prepare('INSERT INTO club_member_charges(sportovec_id,public_code,charge_type,title_snapshot,amount_minor,currency,status,source_system,created_at) '
        . "VALUES(1,?,'member_fee','Synthetic',100,'CZK','unpaid','synthetic','2026-01-10')");
    $pdo->beginTransaction();
    for ($i = 1; $i <= 10000; $i++) {
        $key = 'w12-' . $i;
        $movement->execute([$key, $key, hash('sha256', $key)]);
        if ($i <= 100) {
            $charge->execute([$key]);
        }
    }
    $pdo->commit();
    $private = $runtime->directory . '/private';
    putenv('APP_PRIVATE_STORAGE_ROOT=' . $private);
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $start = hrtime(true);
    $batch = accountingExportCreate($pdo, '2026-01-01', '2026-01-31', 'w12-full-export', 7, 'Synthetic complete archive and restore', true);
    $exportMs = (hrtime(true) - $start) / 1e6;
    $exportPeak = memory_get_peak_usage() - $memory;
    $exportPath = privateStorageResolve($batch['storage_key']);
    $zip = new ZipArchive();
    if (!$exportPath || $zip->open($exportPath) !== true) {
        throw new RuntimeException('Complete export ZIP missing.');
    }
    $snapshot = json_decode($zip->getFromName('data.json'), true, 512, JSON_THROW_ON_ERROR);
    $pdf = $zip->getFromName('klubove-vyporadani.pdf');
    if (count($snapshot['movements']) !== 10000 || count($snapshot['club_receivables']) !== 100
        || !is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
        throw new RuntimeException('Complete export cardinality or PDF contract failed.');
    }
    $zip->close();
    unset($snapshot, $pdf);
    $app = $runtime->directory . '/app';
    $backups = $runtime->directory . '/backups';
    mkdir($app, 0700);
    mkdir($backups, 0700);
    $port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
    file_put_contents($app . '/config.php', "<?php\n"
        . "define('DB_HOST'," . var_export('127.0.0.1;port=' . $port, true) . ");\n"
        . "define('DB_NAME'," . var_export($runtime->schema, true) . ");\n"
        . "define('DB_USER','root');\ndefine('DB_PASS','');\n");
    $start = hrtime(true);
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/db-backup.php', '--app-root=' . $app,
        '--backup-dir=' . $backups, '--keep=1', '--json'], [0 => ['pipe', 'r'],
        1 => ['file', $runtime->directory . '/backup.out', 'w'], 2 => ['file', $runtime->directory . '/backup.err', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated backup CLI.');
    }
    fclose($pipes[0]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Isolated backup failed: ' . file_get_contents($runtime->directory . '/backup.err'));
    }
    $backupMs = (hrtime(true) - $start) / 1e6;
    $backup = json_decode(file_get_contents($runtime->directory . '/backup.out'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($backups . '/' . basename($backup['manifest'])), true, 512, JSON_THROW_ON_ERROR);
    $sqlPath = $backups . '/' . basename($backup['backup']);
    if (!$backup['ok'] || !hash_equals($manifest['sha256'], hash_file('sha256', $sqlPath))) {
        throw new RuntimeException('Backup checksum failed.');
    }
    $sql = gzdecode(file_get_contents($sqlPath));
    if (!is_string($sql) || !str_ends_with($sql, "-- EVIDENCE BACKUP COMPLETE\n")
        || preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)/im', $sql)) {
        throw new RuntimeException('Incomplete or unexpected backup SQL.');
    }
    $restore = $runtime->create('_restored');
    $restore->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $start = hrtime(true);
    $parts = explode("DELIMITER $$\n", $sql, 2);
    $restore->exec($parts[0]);
    if (isset($parts[1])) {
        $triggers = explode("DELIMITER ;\n", $parts[1], 2);
        foreach (explode("$$\n", $triggers[0]) as $statement) {
            if (trim($statement) !== '') {
                $restore->exec($statement);
            }
        }
        if (isset($triggers[1]) && trim($triggers[1]) !== '') {
            $restore->exec($triggers[1]);
        }
    }
    $restoreMs = (hrtime(true) - $start) / 1e6;
    unset($sql, $parts, $triggers);
    $digest = static function (PDO $db, string $table): string {
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $rows = [];
        foreach ($db->query('SELECT * FROM `' . $table . '`') as $row) {
            $rows[] = hash('sha256', serialize($row));
        }
        sort($rows, SORT_STRING);
        return hash('sha256', implode('', $rows));
    };
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($tables, array_keys($manifest['tables'])) || count($tables) !== count($manifest['tables'])) {
        throw new RuntimeException('Backup omitted a migrated table.');
    }
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table) || $digest($pdo, $table) !== $digest($restore, $table)) {
            throw new RuntimeException('Restored table contents differ: ' . $table);
        }
    }
    $foreignKeys = 'SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME '
        . 'FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL '
        . 'ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION';
    if ($pdo->query($foreignKeys)->fetchAll() !== $restore->query($foreignKeys)->fetchAll()
        || (int) $restore->query('SELECT @@foreign_key_checks')->fetchColumn() !== 1) {
        throw new RuntimeException('Restored FK definitions or enforcement differ.');
    }
    $triggerNames = $restore->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_COLUMN);
    sort($triggerNames);
    $expectedTriggers = $manifest['triggers'];
    sort($expectedTriggers);
    if ($triggerNames !== $expectedTriggers) {
        throw new RuntimeException('Restored trigger list differs.');
    }
    $restoredPrivate = $runtime->directory . '/restored-private';
    $privateFiles = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($private) + 1);
        $target = $restoredPrivate . '/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        copy($file->getPathname(), $target);
        if (!hash_equals(hash_file('sha256', $file->getPathname()), hash_file('sha256', $target))) {
            throw new RuntimeException('Restored private file checksum differs.');
        }
        $privateFiles++;
    }
    putenv('APP_PRIVATE_STORAGE_ROOT=' . $restoredPrivate);
    $download = accountingExportDownload($restore, (int) $batch['id'], 7);
    if (!hash_equals($batch['archive_sha256'], hash_file('sha256', $download['path']))) {
        throw new RuntimeException('Restored export reference is not intact.');
    }
    echo json_encode(['status' => 'PASS', 'rows' => 10000, 'club_pdf_rows' => 100, 'tables' => count($tables),
        'triggers' => count($triggerNames), 'foreign_keys_identical_and_enabled' => true, 'whole_table_digests_equal' => true,
        'private_files_restored' => $privateFiles, 'complete_export_ms' => $exportMs, 'export_extra_peak_bytes' => $exportPeak,
        'export_zip_bytes' => filesize($exportPath), 'backup_ms' => $backupMs, 'backup_compressed_bytes' => filesize($sqlPath),
        'restore_ms' => $restoreMs, 'backup_sha256' => $manifest['sha256'], 'production_mutations' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    putenv($oldPrivate === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $oldPrivate);
    $runtime->close();
}
