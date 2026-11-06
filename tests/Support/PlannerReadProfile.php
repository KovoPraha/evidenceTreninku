<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY'
    || !ctype_digit((string)getenv('EVIDENCE_MARIADB_SMOKE_PORT'))
    || trim((string)getenv('EVIDENCE_PERFORMANCE_DATADIR')) === '') {
    throw new RuntimeException('Explicit owned synthetic server required.');
}
require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/PlannerReadFixture.php';
require_once __DIR__ . '/PlannerReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

$admin = w72Connection();
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
$directory = rtrim(str_replace('\\', '/', $identity['directory']), '/');
$expected = rtrim(str_replace('\\', '/', (string)getenv('EVIDENCE_PERFORMANCE_DATADIR')), '/');
if (PHP_OS_FAMILY === 'Windows') {
    $directory = strtolower($directory);
    $expected = strtolower($expected);
}
if ($directory !== $expected || (int)$identity['port'] !== (int)getenv('EVIDENCE_MARIADB_SMOKE_PORT')) {
    throw new RuntimeException('Wrong owned database server.');
}
$schema = 'p11_planner_' . bin2hex(random_bytes(8));
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $identity['version'],
    'summary_parity_cases' => 0, 'pending_parity_cases' => 0, 'fresh_data' => false, 'transaction_locks_preserved' => false];
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    Tests\Support\PlannerReadFixture::seed($pdo);
    $pdo->beginTransaction();
    $pdo->profiling = true;
    $old = trainingRsvpPlanSummaries($pdo, [101, 103]);
    $canonicalSql = array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile);
    $pdo->profile = [];
    $new = trainingPlannerRsvpSummaries($pdo, [101, 103]);
    $newSql = array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile);
    $pdo->profiling = false;
    if ($old !== $new || $canonicalSql !== $newSql || !str_contains(json_encode($newSql, JSON_THROW_ON_ERROR), 'FOR UPDATE')) {
        throw new RuntimeException('Transaction read/locking contract changed.');
    }
    $report['transaction_locks_preserved'] = true;
    $pdo->rollBack();
    trainingPlannerRsvpSummaries($pdo, [101, 102]);
    $pdo->exec("UPDATE training_rsvps SET response='not_going'");
    if (trainingRsvpPlanSummaries($pdo, [101, 102]) !== trainingPlannerRsvpSummaries($pdo, [101, 102])) {
        throw new RuntimeException('Responses were cached between projections.');
    }
    $report['fresh_data'] = true;
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    foreach (Tests\Support\PlannerReadFixture::summaryInputs() as $ids) {
        if (trainingRsvpPlanSummaries($pdo, $ids) !== trainingPlannerRsvpSummaries($pdo, $ids)) {
            throw new RuntimeException('Summary parity failed.');
        }
        $report['summary_parity_cases']++;
    }
    foreach (['2026-01-01', '2026-03-01', '2027-01-01'] as $date) {
        foreach ([false, true] as $mine) {
            foreach ([0, 7, 8] as $trainer) {
                foreach ([0, 1, 2] as $group) {
                    foreach ([0, 1, 2, 3] as $team) {
                        $args = [$pdo, $date, $mine, 7, $trainer, $group, $team];
                        if (Tests\Support\PlannerReadBaseline::pending(...$args) !== trainingPlannerPendingSummary(...$args)) {
                            throw new RuntimeException('Pending warning parity failed.');
                        }
                        $report['pending_parity_cases']++;
                    }
                }
            }
        }
    }
    $pdo->profile = [];
    $pdo->profiling = true;
    trainingPlannerRsvpSummaries($pdo, [101, 102, 103, 104, 105, 106]);
    $report['summary_sql'] = $pdo->profile;
    $pdo->profile = [];
    trainingPlannerPendingSummary($pdo, '2027-01-01', false, 7, 0, 0, 0);
    $report['warning_sql'] = $pdo->profile;
    if (count($report['summary_sql']) > 11 || count($report['warning_sql']) !== 1 || $report['warning_sql'][0]['fetched_rows'] !== 1) {
        throw new RuntimeException('Planner read budget changed.');
    }
    $report['read_only_parity_session'] = true;
    $report['complete'] = true;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
    $report['schema_removed'] = true;
}
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
