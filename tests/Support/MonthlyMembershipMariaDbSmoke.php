<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2).'/vendor/autoload.php';
require_once __DIR__.'/MonthlyMembershipFixture.php';

$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
$database = getenv('EVIDENCE_MONTHLY_SMOKE_DB') ?: 'evidence_monthly_smoke_test_'.bin2hex(random_bytes(5));
if (!preg_match('/^evidence_monthly_smoke_test_[a-z0-9_]+$/D', $database) || !preg_match('/^[1-9][0-9]{0,4}$/D', $port) || (int)$port > 65535) {
    throw new RuntimeException('Only an explicitly isolated smoke schema is allowed.');
}
$server = new PDO('mysql:host=127.0.0.1;port='.$port.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tick = chr(96);
$schema = $tick.$database.$tick;
// Child processes share only this isolated schema, never application configuration.
if (($argv[1] ?? '') === '--race-worker') {
    $pdo = new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $id = (int)($argv[3] ?? 0);
    if (($argv[2] ?? '') === 'movement') {
        $result = monthlyMembershipImportMovement($pdo, $id, 'CZ6508000000192000145399');
    } elseif (($argv[2] ?? '') === 'period') {
        $result = monthlyMembershipTransaction($pdo, static fn () => monthlyMembershipGenerateLocked($pdo, $id, '2026-10-01'));
    } else {
        throw new RuntimeException('Invalid race operation.');
    }
    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

function monthlySmokeRace(PDO $pdo, string $database, string $operation, int $id): array
{
    putenv('EVIDENCE_MONTHLY_SMOKE_DB='.$database);
    $processes = [];
    $pdo->beginTransaction();
    monthlyMembershipLock($pdo);
    try {
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open(
                [PHP_BINARY, __FILE__, '--race-worker', $operation, (string)$id],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start concurrency worker.');
            }
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        // Both workers start behind the same held finance lock.
        usleep(200000);
    } finally {
        $pdo->commit();
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Concurrent worker failed: '.$error);
        }
        $results[] = (int)json_decode($output, true, 512, JSON_THROW_ON_ERROR)['result'];
    }
    sort($results);
    return $results;
}

$private = sys_get_temp_dir().'/monthly-mariadb-'.bin2hex(random_bytes(8));
mkdir($private);
$previous = getenv('APP_PRIVATE_STORAGE_ROOT');
putenv('APP_PRIVATE_STORAGE_ROOT='.$private);
$server->exec('CREATE DATABASE '.$schema.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$database.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE
    => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $fixture = \Tests\Support\MonthlyMembershipFixture::database();
    // Synthetic business fixture only; actual new tables use the real MariaDB migration.
    $tables = $fixture->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    ->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (str_starts_with($table, 'monthly_membership')) {
            continue;
        }
        $columns = $fixture->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC);
        $definitions = [];
        foreach ($columns as $column) {
            $name = $tick.$column['name'].$tick;
            if ($column['pk'] && $column['name'] === 'id') {
                $definitions[] = $name.' BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
            } elseif (str_contains(strtoupper($column['type']), 'INT')) {
                $definitions[] = $name.' BIGINT NULL'.($column['dflt_value'] !== null ? ' DEFAULT '.$column['dflt_value'] : '');
            } elseif (str_contains($column['name'], '_at')) {
                $definitions[] = $name.' DATETIME NULL'.($column['dflt_value'] !== null ? ' DEFAULT '.$column['dflt_value'] : '');
            } else {
                $definitions[] = $name.' TEXT NULL';
            }
        }
        $pdo->exec('CREATE TABLE '.$tick.$table.$tick.'('.implode(',', $definitions).') ENGINE=InnoDB');
        foreach ($fixture->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $names = array_keys($row);
            $pdo->prepare('INSERT INTO '.$tick.$table.$tick.' ('.$tick.implode($tick.','.$tick, $names).$tick.') VALUES('
    .implode(',', array_fill(0, count($names), '?')).')')->execute(array_values($row));
        }
    }
    $migration = require dirname(__DIR__, 2).'/migrations/20261105150000_monthly_memberships.php';
    $migration['up']($pdo);
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('Migration verification failed.');
    }
    $tariff = \Tests\Support\MonthlyMembershipFixture::tariff($pdo);
    $one = \Tests\Support\MonthlyMembershipFixture::subscribe($pdo, $tariff, 1);
    \Tests\Support\MonthlyMembershipFixture::subscribe($pdo, $tariff, 2);
    $movement = \Tests\Support\MonthlyMembershipFixture::movement($pdo, 'LOCALHOST-MARIA-PART', '1800');
    if (monthlySmokeRace($pdo, $database, 'movement', $movement) !== [0, 1]
        || monthlyMembershipRemaining($pdo, 1) !== 0 || monthlyMembershipRemaining($pdo, 2) !== 60000) {
        throw new RuntimeException('Partial family allocation mismatch.');
    }
    if (monthlyMembershipImportMovement($pdo, $movement, 'CZ6508000000192000145399')) {
        throw new RuntimeException('Duplicate movement was applied.');
    }
    if (monthlySmokeRace($pdo, $database, 'period', $one) !== [0, 1]) {
        throw new RuntimeException('Concurrent period generation duplicated a charge.');
    }
    $document = monthlyMembershipIssueDocument($pdo, 1);
    if (!is_file(monthlyMembershipDocumentForAccount($pdo, 20, $document))) {
        throw new RuntimeException('Missing private PDF.');
    }
    monthlyMembershipCancel($pdo, 20, $one, true, new DateTimeImmutable('2026-10-20'));
    if (monthlyMembershipTransaction($pdo, static fn () => monthlyMembershipGenerateLocked($pdo, $one, '2026-12-01')) !== 0) {
        throw new RuntimeException('Cancelled membership generated a future charge.');
    }
    echo json_encode(['ok' => true,'database' => $database,'server' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
    ,'checks' => ['migration_idempotent','two_children','partial_allocation','movement_replay','next_month','private_pdf'
    ,'termination','concurrent_receipt','concurrent_period']], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $server->exec('DROP DATABASE '.$schema);
    $previous === false ? putenv('APP_PRIVATE_STORAGE_ROOT') : putenv('APP_PRIVATE_STORAGE_ROOT='.$previous);
    // This exact random test directory is the only filesystem cleanup target.
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($private);
}
