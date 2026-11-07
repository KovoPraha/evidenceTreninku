<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!isset($argv[1])) {
    throw new RuntimeException('CLI OWN_OUTPUT_DIRECTORY required.');
}
$output = realpath($argv[1]);
if ($output === false) {
    throw new RuntimeException('Missing output.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$matrix = json_decode((string)file_get_contents($output . '/matrix.json'), true, 512, JSON_THROW_ON_ERROR);
$timings = json_decode((string)file_get_contents($output . '/http-timings.json'), true, 512, JSON_THROW_ON_ERROR);
if (!$timings['complete'] || count($timings['cases']) !== count($matrix)) {
    throw new RuntimeException('Complete plain timing matrix required first.');
}
$hashes = array_column($timings['cases'], 'html_sha256', 'id');
$core = ['planovane_treninky', 'training_roster_links', 'club_roster_members', 'club_team_edges', 'treninky',
    'trenink_sportovec', 'training_closures', 'training_closure_revisions', 'medical_clearance_watches',
    'medical_clearance_records', 'medical_clearance_policies', 'treneri', 'staff_user_positions'];
$checksum = static function () use ($admin, $runtime, $core): array {
    $tables = array_map(static fn (string $t): string => '`' . $runtime['schema'] . '`.`' . $t . '`', $core);
    return $admin->query('CHECKSUM TABLE ' . implode(',', $tables) . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
};
$before = $checksum();
$report = ['complete' => false, 'server' => $identity, 'method' => 'separate instrumented HTTP; MariaDB general log on owned server only',
    'fetched_rows_measured' => false, 'sql_duration_measured' => false, 'cases' => []];
$admin->exec("SET GLOBAL general_log=OFF; SET GLOBAL log_output='TABLE'");
try {
    foreach ($matrix as $case) {
        $admin->exec('TRUNCATE TABLE mysql.general_log');
        $admin->exec('SET GLOBAL general_log=ON');
        try {
            $response = fullHttpRequest($runtime, $output, $case['role'], $case['path'], headers: ['X-P11-Measure: ' . $case['id']]);
        } finally {
            $admin->exec('SET GLOBAL general_log=OFF');
        }
        $metricFile = $runtime['runtime'] . '/metrics/' . $case['id'] . '.json';
        if (!is_file($metricFile) || $response['status'] !== $case['status']
            || hash('sha256', fullHttpNormalizedHtml($response)) !== $hashes[$case['id']]) {
            throw new RuntimeException('Missing PHP metric or instrumented HTTP parity failed: ' . $case['id']);
        }
        $metric = json_decode((string)file_get_contents($metricFile), true, 512, JSON_THROW_ON_ERROR);
        if ($metric['fatal'] || ($case['status'] === 200 && (!$metric['db_bootstrap_included'] || !$metric['navigation_included']))) {
            throw new RuntimeException('Incomplete bootstrap/navigation: ' . $case['id']);
        }
        $q = $admin->prepare("SELECT argument FROM mysql.general_log WHERE command_type IN ('Query','Execute') AND user_host LIKE ? ORDER BY event_time");
        $q->execute(['%' . $runtime['web_user'] . '%']);
        $queries = $q->fetchAll(PDO::FETCH_COLUMN);
        $unique = [];
        $writes = [];
        foreach ($queries as $sql) {
            $normalized = trim((string)preg_replace('/\s+/', ' ', $sql));
            $key = hash('sha256', $normalized);
            $unique[$key] ??= ['sql' => $normalized, 'count' => 0];
            $unique[$key]['count']++;
            if (preg_match('/^(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $normalized)) {
                $writes[] = $normalized;
            }
            if (!isset($unique[$key]['explain']) && preg_match('/^SELECT\b/i', $normalized)) {
                try {
                    $admin->exec('USE `' . $runtime['schema'] . '`');
                    $unique[$key]['explain'] = $admin->query('EXPLAIN ' . $normalized)->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $unique[$key]['explain_error_code'] = $e->getCode();
                }
            }
        }
        $case += ['html_parity' => true, 'php' => $metric, 'sql_count' => count($queries),
            'metadata_sql_count' => count(array_filter($queries, static fn (string $sql): bool => stripos($sql, 'information_schema') !== false || preg_match('/^SHOW /i', $sql) === 1)),
            'write_commands' => $writes, 'queries' => array_values($unique)];
        $report['cases'][] = $case;
        file_put_contents($output . '/http-diagnostics.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        echo $case['id'] . ': ' . $case['sql_count'] . ' SQL' . "\n";
    }
} finally {
    $admin->exec('SET GLOBAL general_log=OFF');
}
$after = $checksum();
$report['core_checksums_before'] = $before;
$report['core_checksums_after'] = $after;
$report['core_unchanged'] = $before === $after && !in_array(null, $before, true);
$report['complete'] = true;
if (!$report['core_unchanged']) {
    throw new RuntimeException('Core synthetic data changed during diagnostic GET matrix.');
}
file_put_contents($output . '/http-diagnostics.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
