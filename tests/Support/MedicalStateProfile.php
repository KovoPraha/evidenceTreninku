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
require_once __DIR__ . '/MedicalStateBaseline.php';
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
    'baseline' => '6b1860d5', 'source_sha256' => [
        'includes/medical_clearance_state_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/medical_clearance_state_read.php'),
        'includes/medical_clearance_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/medical_clearance_read.php'),
    ], 'cases' => []];
$percentile = static function (array $samples, float $p): float {
    sort($samples, SORT_NUMERIC);
    return $samples[(int) ceil(count($samples) * $p) - 1];
};
foreach ([100, 1000, 10000, 100000] as $watches) {
    $schema = 'p11_medstate_' . bin2hex(random_bytes(8));
    $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        Tests\Support\MedicalStateFixture::seed($pdo, $watches);
        // A bounded number of future chains amid a growing ordinary history.
        $pdo->exec('UPDATE medical_clearance_records SET replaces_id=NULL WHERE watch_id>100');
        $pdo->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
        $checksum = static fn (): array => $pdo->query(
            'CHECKSUM TABLE medical_clearance_watches,medical_clearance_records,sportovci EXTENDED'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $before = $checksum();
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $cases = ['valid' => ['medical_state' => 'valid'], 'pending' => ['medical_state' => 'pending'],
            'missing' => ['medical_state' => 'missing'], 'expired' => ['medical_state' => 'expired'],
            'person' => ['medical_state' => 'valid', 'person_id' => 1],
            'search' => ['medical_state' => 'valid', 'medical_q' => 'Synthetic']];
        if ($watches === 100000) {
            $cases = array_intersect_key($cases, array_flip(['valid', 'pending', 'person']));
        }
        foreach ($cases as $name => $input) {
            $expected = Tests\Support\medicalStateBaselineList($pdo, $input);
            $samples = ['before' => [], 'after' => []];
            $diagnostics = [];
            for ($iteration = -4; $iteration < 17; $iteration++) {
                $order = $iteration % 2 === 0 ? ['before', 'after'] : ['after', 'before'];
                foreach ($order as $variant) {
                    $start = hrtime(true);
                    $value = $variant === 'before'
                        ? Tests\Support\medicalStateBaselineList($pdo, $input) : medicalClearanceList($pdo, $input);
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
                $variant === 'before' ? Tests\Support\medicalStateBaselineList($pdo, $input) : medicalClearanceList($pdo, $input);
                $pdo->profiling = false;
                foreach ($pdo->profile as $query) {
                    if (!str_starts_with($query['sql'], 'SELECT')) {
                        throw new RuntimeException('Read projection wrote data.');
                    }
                }
                $diagnostics[$variant] = ['sql_count' => count($pdo->profile),
                    'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                    'max_bindings' => max(array_map(static fn (array $q): int => count($q['parameters']), $pdo->profile))];
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
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
