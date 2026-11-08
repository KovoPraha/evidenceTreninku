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

$dsn = 'mysql:host=127.0.0.1;port=' . getenv('EVIDENCE_MARIADB_SMOKE_PORT') . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$normalize = static fn (string $s): string => rtrim(str_replace('\\', '/', $s), '/');
if ($normalize($identity['directory']) !== $normalize((string) getenv('EVIDENCE_PERFORMANCE_DATADIR'))
    || (int) $identity['port'] !== (int) getenv('EVIDENCE_MARIADB_SMOKE_PORT')) {
    throw new RuntimeException('Wrong owned database server.');
}
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'], 'php' => PHP_VERSION,
    'method' => 'One seed per size, read-only session, 4 warmups and 17 alternating timed pairs; separate SQL/fetch diagnostics.',
    'baseline' => '35fdad4483ec4326e5da9b6ffbb908f77a4df85f', 'source_sha256' => [
        'includes/medical_clearance_filtered_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/medical_clearance_filtered_read.php'),
        'includes/medical_clearance_state_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/medical_clearance_state_read.php'),
        'includes/medical_clearance_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/medical_clearance_read.php'),
    ], 'cases' => []];
$percentile = static function (array $samples, float $p): float {
    sort($samples, SORT_NUMERIC);
    return $samples[(int) ceil(count($samples) * $p) - 1];
};
foreach ([100, 1000, 10000, 100000] as $watches) {
    $schema = 'p11_medfilter_' . bin2hex(random_bytes(8));
    $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec('SET SESSION query_cache_type=OFF');
        if (!in_array(strtoupper((string) $pdo->query('SELECT @@session.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
            throw new RuntimeException('SQL result cache must be disabled for timing.');
        }
        Tests\Support\MedicalStateFixture::seed($pdo, $watches);
        // A bounded number of future chains amid a growing ordinary history.
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL WHERE watch_id>100');
        $pdo->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
        $checksum = static fn (): array => $pdo->query(
            'CHECKSUM TABLE medical_clearance_watches,medical_clearance_records,sportovci EXTENDED'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $report['indexes'][(string) $watches] = [];
        foreach (['medical_clearance_watches', 'medical_clearance_records', 'sportovci'] as $table) {
            $report['indexes'][(string) $watches][$table] = $pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        }
        $before = $checksum();
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
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
            $expected = Tests\Support\medicalFilterBaselineList($pdo, $input);
            $samples = ['before' => [], 'after' => []];
            $diagnostics = [];
            for ($iteration = -4; $iteration < 17; $iteration++) {
                $order = $iteration % 2 === 0 ? ['before', 'after'] : ['after', 'before'];
                foreach ($order as $variant) {
                    $start = hrtime(true);
                    $value = $variant === 'before'
                        ? Tests\Support\medicalFilterBaselineList($pdo, $input) : medicalClearanceList($pdo, $input);
                    $ms = (hrtime(true) - $start) / 1e6;
                    if ($value !== $expected) {
                        throw new RuntimeException('Frozen-main parity failed.');
                    }
                    if ($iteration >= 0) {
                        $samples[$variant][] = $ms;
                    }
                }
            }
            foreach (['before', 'after'] as $variant) {
                $pdo->profile = [];
                $pdo->profiling = true;
                $variant === 'before' ? Tests\Support\medicalFilterBaselineList($pdo, $input) : medicalClearanceList($pdo, $input);
                $pdo->profiling = false;
                foreach ($pdo->profile as $query) {
                    if (!str_starts_with($query['sql'], 'SELECT')) {
                        throw new RuntimeException('Read projection wrote data.');
                    }
                }
                $diagnostics[$variant] = ['sql_count' => count($pdo->profile),
                    'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                    'max_bindings' => max(array_map(static fn (array $q): int => count($q['parameters']), $pdo->profile)),
                    'queries' => []];
                foreach ($pdo->profile as $query) {
                    $explain = $pdo->prepare('EXPLAIN ' . $query['sql']);
                    $explain->execute($query['parameters']);
                    $diagnostics[$variant]['queries'][] = ['sql' => $query['sql'], 'explain_estimates' => $explain->fetchAll(PDO::FETCH_ASSOC)];
                    $explain->closeCursor();
                }
            }
            $report['cases'][] = ['watches' => $watches, 'case' => $name, 'input' => $input,
                'result_sha256' => hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
                'total' => $expected['total'], 'parity' => true, 'samples_ms' => $samples, 'diagnostics' => $diagnostics,
                'before_median_ms' => $percentile($samples['before'], .5), 'after_median_ms' => $percentile($samples['after'], .5),
                'before_p95_ms' => $percentile($samples['before'], .95), 'after_p95_ms' => $percentile($samples['after'], .95)];
        }
        if ($before !== $checksum()) {
            throw new RuntimeException('Synthetic source checksums changed.');
        }
    } finally {
        $admin->exec('DROP DATABASE `' . $schema . '`');
    }
}
$report['complete'] = true;
$report['source_checksums_unchanged'] = true;
$report['query_cache_disabled'] = true;
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
