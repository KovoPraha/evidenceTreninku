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
$oldCases = array_filter($before['cases'], static fn (array $case): bool => $case['route'] === 'R13');
$newCases = array_filter($after['cases'], static fn (array $case): bool => $case['route'] === 'R13');
$caseIndexes = [1 => array_keys($oldCases), 2 => array_keys($newCases)];
$oldCases = array_values($oldCases);
$newCases = array_values($newCases);
// The automatic makeup migration adds four unrelated tables and one ledger row.
$makeupSchemaOnly = $after['fixture_tables'] === $before['fixture_tables'] + 4
    && $after['migration_ledger_count'] === $before['migration_ledger_count'] + 1;
if (!$before['complete'] || !$after['complete'] || count($oldCases) !== 9 || count($newCases) !== 9
    || $before['server'] !== $after['server'] || $before['php'] !== $after['php']
    || (!$makeupSchemaOnly && ($before['fixture_tables'] !== $after['fixture_tables']
        || $before['migration_ledger_count'] !== $after['migration_ledger_count']))
    || $before['warmups'] !== 5 || $after['warmups'] !== 5 || $before['timing_samples'] !== 30 || $after['timing_samples'] !== 30) {
    throw new RuntimeException('Incomplete or incomparable measurement environments.');
}
foreach ([1, 2] as $phase) {
    $cleanup = json_decode((string)file_get_contents($argv[$phase] . '/cleanup.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!$cleanup['removed'] || count($reports[$phase - 1]['negative_checks']) !== 4
        || array_filter($reports[$phase - 1]['negative_checks'], static fn (string $state): bool => $state !== 'denied') !== []) {
        throw new RuntimeException('Missing schema cleanup or authorization checks.');
    }
}
$result = ['complete' => false, 'before_source_sha' => $before['source_sha'], 'after_source_sha' => $after['source_sha'],
    'server' => $before['server'], 'php' => $before['php'], 'cases' => [],
    'domain_results_equal' => true, 'html_change' => 'bounded searchable person picker and template whitespace',
    'timing_samples_per_phase' => 270, 'sql_samples_per_phase' => 9];
foreach ($oldCases as $index => $old) {
    $new = $newCases[$index];
    foreach (['route', 'role', 'mode', 'people'] as $key) {
        if ($old[$key] !== $new[$key]) {
            throw new RuntimeException('Different case matrix.');
        }
    }
    $oldData = $old['result']['data'];
    $newData = $new['result']['data'];
    unset($oldData['available_people'], $newData['available_people']);
    if ($oldData !== $newData || $new['result']['data']['available_people'] > 26) {
        throw new RuntimeException('Changed domain result or unbounded person picker.');
    }
    foreach ([1, 2] as $phase) {
        $file = $argv[$phase] . '/' . sprintf('%02d-R13.json', $caseIndexes[$phase][$index] + 1);
        $detail = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        if (count($detail['timing']['samples']) !== 30 || count($detail['sql_detail']['samples']) !== 1
            || !$detail['timing']['read_only_session'] || !$detail['sql_detail']['read_only_session']
            || $detail['timing']['result']['result_sha256'] !== $detail['sql_detail']['result']['result_sha256']) {
            throw new RuntimeException('Missing samples, read-only session or instrumentation parity.');
        }
    }
    $metrics = static fn (array $case): array => ['sql' => $case['sql']['count'], 'rows' => $case['sql']['fetched_rows'],
        'median_ms' => $case['median_ms'], 'p95_ms' => $case['p95_ms'], 'peak_increment_bytes' => $case['peak_increment_bytes'],
        'html_bytes' => $case['result']['html_bytes'], 'person_choices' => $case['result']['data']['available_people']];
    $result['cases'][] = ['people' => $old['people'], 'mode' => $old['mode'], 'before' => $metrics($old), 'after' => $metrics($new),
        'row_reduction_percent' => round(100 * (1 - $new['sql']['fetched_rows'] / $old['sql']['fetched_rows']), 3),
        'median_speedup' => round($old['median_ms'] / $new['median_ms'], 2)];
}
$result['complete'] = true;
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
