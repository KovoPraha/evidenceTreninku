<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/includes/bar/reversals.php';
require_once dirname(__DIR__, 2) . '/includes/bar/invoices.php';
require_once dirname(__DIR__, 2) . '/includes/bar/accounting.php';
$port = (int)(getenv('BAR_SMOKE_DB_PORT') ?: 3306);
if ($port < 1024 || $port > 65535) {
    throw new RuntimeException('Invalid loopback test port.');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4', 'root', '', $options);
$database = 'evidence_bar_test_documents_' . bin2hex(random_bytes(6));
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
$oldDsn = getenv('BAR_TARGET_DSN');
$oldUser = getenv('BAR_TARGET_USER');
$oldPassword = getenv('BAR_TARGET_PASSWORD');
putenv('BAR_TARGET_DSN=' . $dsn);
putenv('BAR_TARGET_USER=root');
putenv('BAR_TARGET_PASSWORD=');
$workers = [];
$connect = static fn (): PDO => new PDO($dsn, 'root', '', $options);
$login = static function (int $id): void {
    $_SESSION = ['trener_id' => $id, 'trener_session_version' => 1, 'staff_active_position' => $id === 3 ? 'bar_admin' : 'bar_operator'];
};
$start = static function (array $input) use (&$workers): int {
    $process = proc_open([PHP_BINARY, __DIR__ . '/BarDocumentsRaceWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated checkout race worker.');
    }
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 10);
    $ready = json_decode((string)fgets($pipes[1]), true, 8, JSON_THROW_ON_ERROR);
    if (!($ready['ready'] ?? false)) {
        throw new RuntimeException('Checkout worker did not become ready.');
    }
    $workers[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2], 'connection_id' => $ready['connection_id']];
    return array_key_last($workers);
};
$waitBlocked = static function (array $indexes) use (&$workers, $server): void {
    $ids = array_map(static fn (int $index): int => $workers[$index]['connection_id'], $indexes);
    $deadline = microtime(true) + 8;
    do {
        $rows = $server->query('SELECT ID,INFO FROM information_schema.PROCESSLIST WHERE ID IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
        $waiting = array_filter($rows, static fn (array $row): bool => str_contains((string)$row['INFO'], 'kis_bar_state'));
        if (count($waiting) === count($ids)) {
            return;
        }
        usleep(20000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Workers did not reach the real database lock barrier.');
};
$finish = static function (int $index) use (&$workers): array {
    $worker = $workers[$index];
    $output = trim((string)stream_get_contents($worker['out']));
    $error = trim((string)stream_get_contents($worker['err']));
    fclose($worker['out']);
    fclose($worker['err']);
    $code = proc_close($worker['process']);
    unset($workers[$index]);
    if ($code !== 0 || $error !== '') {
        throw new RuntimeException('Isolated worker failed: ' . $error);
    }
    return json_decode($output, true, 16, JSON_THROW_ON_ERROR);
};
try {
    $pdo = $connect();
    $writer = $connect();
    $barrier = $connect();
    $pdo->exec('CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),role VARCHAR(20),aktivni INT,session_version INT,email VARCHAR(255),heslo VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO treneri(id,jmeno,role,aktivni,session_version) VALUES(2,'LOCALHOST A','trener',1,1),(3,'LOCALHOST B','trener',1,1)");
    $migrationIds = ['20260821150000_staff_workspaces', '20261105150000_bar_foundation', '20261105160000_bar_transfer',
        '20261105170000_bar_stock', '20261105180000_bar_catalog_management', '20261105190000_bar_checkout', '20261105200000_bar_documents', '20261105210000_bar_recovery'];
    foreach ($migrationIds as $migrationId) {
        $migration = require dirname(__DIR__, 2) . '/migrations/' . $migrationId . '.php';
        $migration['up']($pdo);
        $migration['up']($pdo);
        if (!$migration['verify']($pdo)) {
            throw new RuntimeException('Repeated isolated migration failed.');
        }
    }
    $pdo->exec("INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(2,'bar_operator',1),(3,'bar_admin',1)");
    $pdo->exec("INSERT INTO kis_bar_categories(id,name) VALUES(1,'LOCALHOST Drinks')");
    $pdo->exec("INSERT INTO kis_bar_products(id,category_id,name,description,price_minor,purchase_minor,stock_mode,stock_stall,package_name) "
        . "VALUES(1,1,'LOCALHOST Tea','',3500,1101,'sledovat',1,'')");

    $storage = sys_get_temp_dir() . '/kis-bar-native-documents-' . bin2hex(random_bytes(8));
    $oldStorage = getenv('APP_PRIVATE_STORAGE_ROOT');
    putenv('APP_PRIVATE_STORAGE_ROOT=' . $storage);
    $login(3);
    $token = static fn (): string => bin2hex(random_bytes(32));
    $id = barUploadInvoice($pdo, dirname(__DIR__) . '/fixtures/bar-localhost-invoice.pdf', $token(), false)['invoice_id'];
    $draft = ['supplier' => 'LOCALHOST Supplier', 'supplier_id' => '12345678', 'number' => 'LOCAL-001', 'date' => '2020-01-01', 'currency' => 'CZK',
        'net_minor' => 10000, 'vat_minor' => 2100, 'gross_minor' => 12100, 'rounding_minor' => 0, 'payable_minor' => 12100, 'verified' => 1,
        'rows' => [['code' => 'TEA001', 'description' => 'LOCALHOST Tea', 'quantity' => 2, 'unit' => 'bal', 'net_minor' => 10000,
            'gross_minor' => 12100, 'kind' => 'stock', 'product_id' => 1, 'multiplier' => 3, 'confirmed' => 1, 'remember' => 1,
            'cost_choice' => 'apply', 'purchase_version' => 1]]];
    barSaveInvoice($pdo, $id, 1, $draft, 'LOCALHOST checked invoice', $token());
    $input = ['action' => 'receive', 'actor_id' => 3, 'position' => 'bar_admin', 'invoice_id' => $id, 'version' => 2, 'token' => $token(), 'storage' => $storage];
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($input);
    $b = $start($input);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $race = [$finish($a), $finish($b)];
    if (!$race[0]['ok'] || !$race[1]['ok'] || $race[0]['result']['receipt_id'] !== $race[1]['result']['receipt_id']
        || (int)$pdo->query('SELECT stock_office FROM kis_bar_products WHERE id=1')->fetchColumn() !== 6
        || (int)$pdo->query('SELECT purchase_minor FROM kis_bar_products WHERE id=1')->fetchColumn() !== 2017) {
        throw new RuntimeException('Concurrent receipt replay changed stock or cost twice.');
    }
    $sources = barAccountingSources($pdo);
    $selection = array_map(static fn (array $row): string => $row['sha256'], $sources);
    $exportInput = ['action' => 'export', 'actor_id' => 3, 'position' => 'bar_admin', 'selection' => $selection, 'token' => $token()];
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($exportInput);
    $exportInput['token'] = $token();
    $b = $start($exportInput);
    $waitBlocked([$a, $b]);
    $barrier->commit();
    $race = [$finish($a), $finish($b)];
    if (count(array_filter($race, static fn (array $row): bool => $row['ok'])) !== 1
        || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_accounting_export_items')->fetchColumn() !== 1) {
        throw new RuntimeException('Two accounting exports claimed the same immutable source.');
    }
    $frozen = barAccountingExport($pdo, 1);
    barReverseInvoice($pdo, $id, 3, 'LOCALHOST later reversal', $token());
    $sources = barAccountingSources($pdo);
    if (count($sources) !== 1 || reset($sources)['event']['kind'] !== 'supplier_receipt_reversal'
        || barAccountingExport($pdo, 1)['manifest_json'] !== $frozen['manifest_json']) {
        throw new RuntimeException('Late reversal rewrote exported evidence.');
    }
    $draft['rows'][0]['purchase_version'] = 2;
    barSaveInvoice($pdo, $id, 4, $draft, 'LOCALHOST approve again', $token());
    $input['version'] = 5;
    $input['token'] = $token();
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($input);
    $waitBlocked([$a]);
    $writer->exec('UPDATE kis_bar_products SET purchase_version=purchase_version+2 WHERE id=1');
    $barrier->commit();
    if ($finish($a)['ok']) {
        throw new RuntimeException('Receipt used stale cost approval after real lock wait.');
    }
    $draft['rows'][0]['purchase_version'] = 4;
    barSaveInvoice($pdo, $id, 5, $draft, 'LOCALHOST current price', $token());
    $input['version'] = 6;
    $input['token'] = $token();
    $barrier->beginTransaction();
    barLockState($barrier);
    $a = $start($input);
    $waitBlocked([$a]);
    $writer->exec("DELETE FROM staff_user_positions WHERE trainer_id=3 AND position_code='bar_admin'");
    $barrier->commit();
    if ($finish($a)['ok'] || (int)$pdo->query('SELECT COUNT(*) FROM kis_bar_invoice_receipts')->fetchColumn() !== 1) {
        throw new RuntimeException('Revoked administrator received goods after real lock wait.');
    }
    echo json_encode(['ok' => true, 'mariadb' => $server->query('SELECT VERSION()')->fetchColumn(),
        'checks' => ['repeat_migrations', 'concurrent_receipt_replay', 'competing_accounting_exports', 'late_reversal_immutable_export',
            'cost_aba_during_real_wait', 'position_revoked_during_real_wait']], JSON_THROW_ON_ERROR) . "\n";
} finally {
    if (isset($barrier) && $barrier->inTransaction()) {
        $barrier->rollBack();
    }
    foreach ($workers as $worker) {
        proc_terminate($worker['process']);
        fclose($worker['out']);
        fclose($worker['err']);
        proc_close($worker['process']);
    }
    $pdo = $writer = $barrier = null;
    $server->exec("DROP DATABASE `$database`");
    foreach (['BAR_TARGET_DSN' => $oldDsn, 'BAR_TARGET_USER' => $oldUser, 'BAR_TARGET_PASSWORD' => $oldPassword] as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
    if (isset($storage)) {
        foreach (glob($storage . '/bar-invoices/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($storage . '/bar-invoices')) {
            rmdir($storage . '/bar-invoices');
            rmdir($storage);
        }
        putenv($oldStorage === false ? 'APP_PRIVATE_STORAGE_ROOT' : 'APP_PRIVATE_STORAGE_ROOT=' . $oldStorage);
    }
    $_SESSION = [];
}
