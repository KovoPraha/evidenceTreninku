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
require_once __DIR__ . '/PlannerHierarchyFixture.php';
require_once __DIR__ . '/PlannerHierarchyBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

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
    'baseline' => '69f49033', 'source_sha256' => [
        'includes/training_planner_pending_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/training_planner_pending_read.php'),
        'includes/training_planner_read.php' => hash_file('sha256', dirname(__DIR__, 2) . '/includes/training_planner_read.php'),
    ], 'cases' => []];
$percentile = static function (array $samples, float $p): float {
    sort($samples, SORT_NUMERIC);
    return $samples[(int) ceil(count($samples) * $p) - 1];
};
foreach ([40, 720, 7200, 36000] as $plans) {
    $schema = 'p11_hierarchy_' . bin2hex(random_bytes(8));
    $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        Tests\Support\PlannerHierarchyFixture::seed($pdo, $plans);
        $pdo->query('ANALYZE TABLE planovane_treninky,training_roster_links,club_team_edges')->fetchAll();
        $checksum = static fn (): array => $pdo->query(
            'CHECKSUM TABLE planovane_treninky,training_roster_links,club_team_edges EXTENDED'
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $before = $checksum();
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        foreach ([false, true] as $mine) {
            foreach ([1, 2, 10] as $team) {
                $args = [$pdo, '2027-01-01', $mine, 7, 0, 0, $team];
                $expected = Tests\Support\plannerHierarchyPendingBaseline(...$args);
                $samples = ['before' => [], 'after' => []];
                $diagnostics = [];
                for ($iteration = -4; $iteration < 17; $iteration++) {
                    $order = $iteration % 2 === 0 ? ['before', 'after'] : ['after', 'before'];
                    foreach ($order as $variant) {
                        $start = hrtime(true);
                        $value = $variant === 'before'
                            ? Tests\Support\plannerHierarchyPendingBaseline(...$args) : trainingPlannerPendingSummary(...$args);
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
                    $variant === 'before' ? Tests\Support\plannerHierarchyPendingBaseline(...$args) : trainingPlannerPendingSummary(...$args);
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
                $report['cases'][] = ['plans' => $plans, 'mine' => $mine, 'team' => $team, 'result' => $expected,
                    'parity' => true, 'samples_ms' => $samples, 'diagnostics' => $diagnostics,
                    'before_median_ms' => $percentile($samples['before'], .5), 'after_median_ms' => $percentile($samples['after'], .5),
                    'before_p95_ms' => $percentile($samples['before'], .95), 'after_p95_ms' => $percentile($samples['after'], .95)];
            }
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
