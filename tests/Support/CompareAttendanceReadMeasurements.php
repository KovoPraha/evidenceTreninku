<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (count($argv) !== 3) {
    throw new RuntimeException('Usage: BEFORE_DIRECTORY AFTER_DIRECTORY');
}
$reports = [];
foreach (array_slice($argv, 1) as $directory) {
    $reports[] = json_decode((string)file_get_contents($directory . '/summary.json'), true, 512, JSON_THROW_ON_ERROR);
}
[$before, $after] = $reports;
$oldCases = array_filter($before['cases'], static fn (array $case): bool => $case['route'] === 'R06');
$newCases = array_filter($after['cases'], static fn (array $case): bool => $case['route'] === 'R06');
$indexes = [1 => array_keys($oldCases), 2 => array_keys($newCases)];
$oldCases = array_values($oldCases);
$newCases = array_values($newCases);
if (!$before['complete'] || !$after['complete'] || count($oldCases) !== 9 || count($newCases) !== 9
    || $before['server'] !== $after['server'] || $before['php'] !== $after['php']
    || $before['fixture_tables'] !== $after['fixture_tables'] || $before['migration_ledger_count'] !== $after['migration_ledger_count']
    || $before['warmups'] !== 5 || $after['warmups'] !== 5 || $before['timing_samples'] !== 30 || $after['timing_samples'] !== 30) {
    throw new RuntimeException('Incomparable or incomplete attendance measurements.');
}
foreach ([1, 2] as $phase) {
    $cleanup = json_decode((string)file_get_contents($argv[$phase] . '/cleanup.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!$cleanup['removed'] || count($reports[$phase - 1]['negative_checks']) !== 4
        || array_filter($reports[$phase - 1]['negative_checks'], static fn (string $value): bool => $value !== 'denied') !== []) {
        throw new RuntimeException('Missing cleanup or authorization checks.');
    }
}
$report = ['complete' => false, 'before_source_sha' => $before['source_sha'], 'after_source_sha' => $after['source_sha'],
    'server' => $before['server'], 'php' => $before['php'], 'domain_results_equal' => true, 'html_lf_equal' => true,
    'timing_samples_per_phase' => 270, 'sql_samples_per_phase' => 9, 'cases' => []];
foreach ($oldCases as $i => $old) {
    $new = $newCases[$i];
    foreach (['role', 'participants', 'revisions', 'mode'] as $key) {
        if ($old[$key] !== $new[$key]) {
            throw new RuntimeException('Different attendance fixture matrix.');
        }
    }
    if ($old['result']['data'] !== $new['result']['data'] || $old['result']['html_lf_sha256'] !== $new['result']['html_lf_sha256']) {
        throw new RuntimeException('Attendance data or rendered HTML changed.');
    }
    // Current shared UI: original 45 + 14 bounded-picker reads - 3 batched lesson reads.
    // Canonical validator budgets (3/200 and 5/1200) stay in AttendanceReadProfile.
    // Full HTML/data parity, row cap and relative improvement remain mandatory.
    if ($new['sql']['count'] > 56 || $new['sql']['fetched_rows'] > 1000
        || $new['sql']['count'] >= $old['sql']['count'] || $new['sql']['fetched_rows'] > $old['sql']['fetched_rows']) {
        throw new RuntimeException('Attendance query/row budget or measured improvement regressed.');
    }
    foreach ([1, 2] as $phase) {
        $path = $argv[$phase] . '/' . sprintf('%02d-R06.json', $indexes[$phase][$i] + 1);
        $detail = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (count($detail['timing']['samples']) !== 30 || count($detail['sql_detail']['samples']) !== 1
            || !$detail['timing']['read_only_session'] || !$detail['sql_detail']['read_only_session']
            || $detail['timing']['result']['result_sha256'] !== $detail['sql_detail']['result']['result_sha256']) {
            throw new RuntimeException('Missing samples, read-only sessions or instrumentation parity.');
        }
    }
    $metrics = static fn (array $case): array => ['sql' => $case['sql']['count'], 'rows' => $case['sql']['fetched_rows'],
        'median_ms' => $case['median_ms'], 'p95_ms' => $case['p95_ms'], 'peak_increment_bytes' => $case['peak_increment_bytes']];
    $report['cases'][] = ['participants' => $old['participants'], 'revisions' => $old['revisions'], 'role' => $old['role'], 'before' => $metrics($old), 'after' => $metrics($new),
        'sql_reduction_percent' => round(100 * (1 - $new['sql']['count'] / $old['sql']['count']), 2),
        'row_reduction_percent' => round(100 * (1 - $new['sql']['fetched_rows'] / $old['sql']['fetched_rows']), 2),
        'median_speedup' => round($old['median_ms'] / $new['median_ms'], 2)];
}
$report['complete'] = true;
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
