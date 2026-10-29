<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/ProgramCapacityMigratedFixture.php';
require_once __DIR__ . '/ProgramCapacityBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/club_program.php';

$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Invalid isolated localhost port.');
}
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$admin = new PDO($dsn, 'root', '', $options);
$database = 'w63_profile_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$median = static function (array $values): float {
    sort($values, SORT_NUMERIC);
    return $values[intdiv(count($values), 2)];
};
$results = [];
try {
    $pdo = new PDO($dsn . ';dbname=' . $database, 'root', '', $options);
    Tests\Support\programCapacityMigratedSchema($pdo);
    Tests\Support\programCapacityMigratedSeed($pdo);
    $profile = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $database, 'root', '');
    $profile->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $now = new DateTimeImmutable('2026-10-29 12:00:00 Europe/Prague');
    $schema = [];
    foreach (['club_program_offers', 'club_program_enrollments', 'club_program_physical_teams', 'shop_orders', 'shop_order_items', 'account_person_claim_requests'] as $table) {
        $schema[$table] = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    }
    $scenarios = [['empty', 0, false, false], ['small', 10, false, false],
        ['dense', 10000, false, false], ['duplicates', 10000, true, false], ['fanout', 1000, false, true]];
    foreach ($scenarios as [$name, $size, $duplicates, $fanout]) {
        Tests\Support\programCapacityMigratedLoad($pdo, $size, $duplicates, $fanout);
        foreach (array_keys($schema) as $table) {
            $pdo->query('ANALYZE TABLE ' . $table)->fetchAll();
        }
        $offer = $pdo->query('SELECT * FROM club_program_offers WHERE id=1')->fetch();
        $samples = ['baseline' => [], 'current' => []];
        $reference = null;
        $read = static function (PDO $connection, string $mode) use ($now, $offer): array {
            $context = new ClubProgramReadContext($connection, $now);
            return $mode === 'baseline'
                ? programCapacityProfileBaseline($connection, $offer, $now, false, 0, $context)
                : clubProgramOfferCapacityState($connection, $offer, $now, false, 0, $context);
        };
        // Plain PDO: per-row instrumentation would distort streaming timings.
        for ($iteration = -3; $iteration < 15; $iteration++) {
            foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                memory_reset_peak_usage();
                $memoryStart = memory_get_usage();
                $start = hrtime(true);
                $state = $read($pdo, $mode);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $incremental = max(0, memory_get_peak_usage() - $memoryStart);
                $reference ??= $state;
                if ($state !== $reference || $state['occupied_count'] !== ($duplicates ? 1 : $size)) {
                    throw new RuntimeException('Migrated profile capacity changed.');
                }
                if ($iteration >= 0) {
                    $samples[$mode][] = ['service_ms' => $elapsed, 'incremental_peak_bytes' => $incremental];
                }
            }
        }
        $queries = [];
        foreach (['baseline', 'current'] as $mode) {
            $profile->profile = [];
            $profile->profiling = true;
            if ($read($profile, $mode) !== $reference) {
                throw new RuntimeException('Instrumented state changed.');
            }
            $profile->profiling = false;
            $queries[$mode] = $profile->profile;
        }
        $projection = static fn (array $rows): array => array_map(static fn (array $row): array => [$row['sql'], $row['parameters'], $row['fetched_rows']], $rows);
        if ($projection($queries['baseline']) !== $projection($queries['current'])) {
            throw new RuntimeException('SQL/parameters/rows changed.');
        }
        $plans = [];
        foreach ($queries['current'] as $entry) {
            if (str_starts_with($entry['sql'], 'SELECT e.sportovec_id') || str_starts_with($entry['sql'], 'SELECT oi.id')) {
                $q = $pdo->prepare('EXPLAIN ' . $entry['sql']);
                $q->execute($entry['parameters']);
                $plans[] = ['sql' => $entry['sql'], 'parameters' => $entry['parameters'], 'plan' => $q->fetchAll()];
            }
        }
        $peaks = array_map(static fn (array $rows): float => $median(array_column($rows, 'incremental_peak_bytes')), $samples);
        if ($size === 10000 && ($peaks['current'] > 4 * 1024 * 1024 || $peaks['current'] > $peaks['baseline'] * 0.6)) {
            throw new RuntimeException('Capacity PHP peak exceeded 4 MiB or 60% of materialized baseline.');
        }
        $results[] = ['scenario' => $name, 'items' => $size, 'state' => $reference, 'samples' => $samples,
            'median_ms' => array_map(static fn (array $rows): float => $median(array_column($rows, 'service_ms')), $samples),
            'median_incremental_peak_bytes' => $peaks,
            'queries' => $queries, 'explain' => $plans];
    }
    echo json_encode(['synthetic_only' => true, 'schema' => 'minimal legacy bootstrap plus full repository migration catalog; FK enabled',
        'database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION), 'php_version' => PHP_VERSION,
        'migration_count' => count(glob(dirname(__DIR__, 2) . '/migrations/*.php')),
        'baseline' => 'frozen 9da70c97 pre-extraction reader; W6.2 proved identical SQL/results',
        'warmups_per_mode' => 3, 'samples_per_mode' => 15, 'pdo_mysql_buffered' => true,
        'memory_guard' => '10000 items: median incremental PHP peak <= 4 MiB and <= 60% of baseline',
        'optimizer_statistics' => 'ANALYZE TABLE on the six profiled tables after loading each scenario',
        'measurement' => 'plain PDO timings and PHP peak; separate query instrumentation; fetched rows are not rows examined',
        'table_definitions' => $schema, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
}
