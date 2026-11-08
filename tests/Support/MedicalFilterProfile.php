<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || !ctype_digit((string) getenv('EVIDENCE_MARIADB_SMOKE_PORT'))
    || trim((string) getenv('EVIDENCE_PERFORMANCE_DATADIR')) === '') {
    throw new RuntimeException('Explicit owned synthetic server required.');
}
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/MedicalStateFixture.php';
require_once __DIR__ . '/MedicalFilterBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/medical_clearance.php';
$indexMigration = require dirname(__DIR__, 2) . '/migrations/20261108070000_medical_read_covering_index.php';

$dsn = 'mysql:host=127.0.0.1;port=' . getenv('EVIDENCE_MARIADB_SMOKE_PORT') . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$normalize = static fn (string $s): string => rtrim(str_replace('\\', '/', $s), '/');
if ($normalize($identity['directory']) !== $normalize((string) getenv('EVIDENCE_PERFORMANCE_DATADIR'))
    || (int) $identity['port'] !== (int) getenv('EVIDENCE_MARIADB_SMOKE_PORT')) {
    throw new RuntimeException('Wrong owned database server.');
}
$admin->exec('SET GLOBAL innodb_buffer_pool_size=536870912');
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'], 'php' => PHP_VERSION,
    'method' => 'Two data-identical owned schemas: original indexes vs covering index. '
        . 'Four warmups and 17 rotating timed triplets: original code/original indexes, '
        . 'original code/covering index, optimized code/covering index. Separate diagnostics.',
    'baseline' => '35fdad4483ec4326e5da9b6ffbb908f77a4df85f', 'query_cache_disabled' => true,
    'buffer_pool_bytes' => (int) $admin->query('SELECT @@innodb_buffer_pool_size')->fetchColumn(),
    'source_sha256' => [], 'cases' => [], 'indexes' => []];
foreach (['includes/medical_clearance_read.php', 'includes/medical_clearance_state_read.php',
    'includes/medical_clearance_filtered_read.php', 'includes/medical_clearance_read_index.php',
    'migrations/20261108070000_medical_read_covering_index.php'] as $file) {
    $report['source_sha256'][$file] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $file);
}
$percentile = static function (array $values, float $p): float {
    sort($values, SORT_NUMERIC);
    return $values[(int) ceil(count($values) * $p) - 1];
};
$checksum = static function (PDO $connection): array {
    $values = [];
    foreach ($connection->query('CHECKSUM TABLE medical_clearance_watches,medical_clearance_records,sportovci EXTENDED')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $values[substr($row['Table'], strrpos($row['Table'], '.') + 1)] = $row['Checksum'];
    }
    return $values;
};
foreach ([100, 1000, 10000, 100000] as $watches) {
    $schemas = [];
    $connections = [];
    $originalChecksums = [];
    try {
        foreach (['before', 'after'] as $schemaVariant) {
            $schema = 'p11_medfilter_' . bin2hex(random_bytes(8));
            $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $schemas[] = $schema;
            $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->exec('SET SESSION query_cache_type=OFF');
            if (!in_array(strtoupper((string) $pdo->query('SELECT @@session.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
                throw new RuntimeException('SQL result caching must be disabled.');
            }
            Tests\Support\MedicalStateFixture::seed($pdo, $watches);
            $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL WHERE watch_id>100');
            if ($schemaVariant === 'after') {
                $indexMigration['up']($pdo);
                $indexMigration['up']($pdo);
            }
            if ($indexMigration['verify']($pdo) !== ($schemaVariant === 'after')) {
                throw new RuntimeException('Unexpected before/after index shape.');
            }
            $pdo->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
            $report['indexes'][(string) $watches][$schemaVariant] = MedicalClearanceReadIndex::definitions($pdo);
            $originalChecksums[$schemaVariant] = $checksum($pdo);
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $connections[$schemaVariant] = $pdo;
        }
        if ($originalChecksums['before'] !== $originalChecksums['after']) {
            throw new RuntimeException('Owned paired schemas must contain identical data.');
        }
        $day = static fn (int $n): string => date('Y-m-d', strtotime(($n < 0 ? '' : '+') . $n . ' day'));
        $cases = [
            'broad_search' => ['medical_q' => 'Synthetic', 'medical_state' => 'valid'],
            'half_search' => ['medical_q' => 'Žáková', 'medical_state' => 'valid'],
            'broad_pending' => ['medical_q' => 'Synthetic', 'medical_state' => 'pending'],
            'half_pending' => ['medical_q' => 'Žáková', 'medical_state' => 'pending'],
            'rare_search' => ['medical_q' => 'purpose ' . $watches, 'medical_state' => 'pending'],
            'empty_search' => ['medical_q' => '__no_such_person__', 'medical_state' => 'valid'],
            'date_day' => ['medical_from' => $day(1), 'medical_to' => $day(1), 'medical_state' => 'valid'],
            'date_from' => ['medical_from' => $day(1), 'medical_state' => 'valid'],
            'date_to' => ['medical_to' => $day(2), 'medical_state' => 'pending'],
            'date_empty' => ['medical_to' => $day(-1), 'medical_state' => 'valid'],
            'combined' => ['medical_q' => 'Synthetic', 'medical_from' => $day(1), 'medical_to' => $day(2), 'medical_state' => 'valid'],
            'plain_search' => ['medical_q' => 'Synthetic'],
            'plain_date' => ['medical_from' => $day(1), 'medical_to' => $day(2)],
            'plain_rare' => ['medical_q' => 'purpose ' . $watches],
            'plain_empty' => ['medical_q' => '__no_such_person__'],
            'control_state' => ['medical_state' => 'valid'],
        ];
        foreach ($cases as $name => $input) {
            $expected = Tests\Support\medicalFilterBaselineList($connections['before'], $input);
            $samples = ['before' => [], 'index_only' => [], 'after' => []];
            $diagnostics = [];
            for ($iteration = -4; $iteration < 17; $iteration++) {
                $order = ['before', 'index_only', 'after'];
                $shift = ($iteration + 6) % 3;
                $order = [...array_slice($order, $shift), ...array_slice($order, 0, $shift)];
                foreach ($order as $variant) {
                    $pdo = $connections[$variant === 'before' ? 'before' : 'after'];
                    $start = hrtime(true);
                    $value = $variant === 'after' ? medicalClearanceList($pdo, $input)
                        : Tests\Support\medicalFilterBaselineList($pdo, $input);
                    $ms = (hrtime(true) - $start) / 1e6;
                    if ($value !== $expected) {
                        throw new RuntimeException('Original, index-only and optimized results must match exactly.');
                    }
                    if ($iteration >= 0) {
                        $samples[$variant][] = $ms;
                    }
                }
            }
            foreach (['before', 'index_only', 'after'] as $variant) {
                $pdo = $connections[$variant === 'before' ? 'before' : 'after'];
                $pdo->profile = [];
                $pdo->profiling = true;
                $variant === 'after' ? medicalClearanceList($pdo, $input) : Tests\Support\medicalFilterBaselineList($pdo, $input);
                $pdo->profiling = false;
                $queries = $pdo->profile;
                $diagnostics[$variant] = ['sql_count' => count($queries),
                    'fetched_rows' => array_sum(array_column($queries, 'fetched_rows')),
                    'max_bindings' => max(array_map(static fn (array $q): int => count($q['parameters']), $queries)), 'queries' => []];
                foreach ($queries as $query) {
                    if (!str_starts_with($query['sql'], 'SELECT')) {
                        throw new RuntimeException('Timed reader wrote to the fixture.');
                    }
                    $explain = $pdo->prepare('EXPLAIN ' . $query['sql']);
                    $explain->execute($query['parameters']);
                    $diagnostics[$variant]['queries'][] = array_intersect_key($query, array_flip(['sql', 'execute_ms', 'fetch_ms', 'fetched_rows']))
                        + ['explain_estimates' => $explain->fetchAll(PDO::FETCH_ASSOC)];
                    $explain->closeCursor();
                }
            }
            $report['cases'][] = ['watches' => $watches, 'case' => $name, 'input' => $input,
                'result_sha256' => hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)), 'total' => $expected['total'], 'parity' => true,
                'samples_ms' => $samples, 'diagnostics' => $diagnostics,
                'before_median_ms' => $percentile($samples['before'], .5), 'index_only_median_ms' => $percentile($samples['index_only'], .5),
                'after_median_ms' => $percentile($samples['after'], .5),
                'before_p95_ms' => $percentile($samples['before'], .95), 'index_only_p95_ms' => $percentile($samples['index_only'], .95),
                'after_p95_ms' => $percentile($samples['after'], .95)];
        }
        foreach ($connections as $variant => $connection) {
            if ($originalChecksums[$variant] !== $checksum($connection)) {
                throw new RuntimeException('Owned data changed during read-only comparisons.');
            }
        }
    } finally {
        $connections = [];
        foreach ($schemas as $schema) {
            $admin->exec('DROP DATABASE `' . $schema . '`');
        }
    }
}
$report['complete'] = true;
$report['paired_data_checksums_equal'] = true;
$report['source_checksums_unchanged'] = true;
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
