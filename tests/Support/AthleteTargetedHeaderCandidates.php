<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$size = (int) ($argv[2] ?? 0);
if ($output === false || !in_array($size, [1000,10000,30000], true)) {
    throw new RuntimeException('Owned output and size required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$path = dirname(__DIR__, 2)
    . '/docs/verification/athlete-profile-targeted-read-20261109/diagnostic-c9/native-10.3/targeted-before-parent-1001.json.gz';
$diagnostic = json_decode(gzdecode(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
$baseline = null;
foreach ($diagnostic['queries'] as $query) {
    if (str_contains($query['sql'], 'SUM(owned.measurements)')) {
        $baseline = $query['sql'];
    }
}
if ($baseline === null) {
    throw new RuntimeException('Frozen header required.');
}
$source = 'FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id';
$templates = ['before' => $baseline,
    'force_owner' => str_replace('mereni_zaznamy mz ON', 'mereni_zaznamy mz FORCE INDEX (idx_sportovec) ON', $baseline),
    'links_first' => str_replace($source, 'FROM trenink_mereni tm STRAIGHT_JOIN mereni_zaznamy mz FORCE INDEX (idx_sportovec) ON mz.id=tm.mereni_id', $baseline),
    'owner_first' => str_replace($source, 'FROM mereni_zaznamy mz FORCE INDEX (idx_sportovec) STRAIGHT_JOIN trenink_mereni tm ON tm.mereni_id=mz.id', $baseline),
];
$templates['semijoin'] = str_replace(
    $source . ' WHERE mz.sportovec_id=:measurement_sid',
    'FROM trenink_mereni tm WHERE tm.mereni_id IN (SELECT mz.id FROM mereni_zaznamy mz WHERE mz.sportovec_id=:measurement_sid)',
    $baseline,
    $replacements
);
if ($replacements !== 1) {
    throw new RuntimeException('Frozen header branch mismatch.');
}
$analysis = $admin->query('ANALYZE TABLE treninky,trenink_sportovec,trenink_mereni,mereni_zaznamy,sportovec_poznamka')->fetchAll(PDO::FETCH_ASSOC);
$report = ['size' => $size, 'synthetic_only' => true, 'shared_analysis' => $analysis,
    'warmups' => 2, 'samples' => 7, 'cases' => [], 'complete' => false];
foreach ([1001,1002,1012,1013,1020] as $person) {
    $expected = null;
    $case = ['sid' => $person, 'parity' => true, 'samples_ms' => [], 'explain' => []];
    $params = [':attendance_sid' => $person, ':measurement_sid' => $person];
    foreach ($templates as $name => $sql) {
        $plan = $admin->prepare('EXPLAIN FORMAT=JSON ' . $sql);
        $plan->execute($params);
        $case['explain'][$name] = $plan->fetchColumn();
    }
    for ($round = -2; $round < 7; ++$round) {
        foreach ($round % 2 === 0 ? array_keys($templates) : array_reverse(array_keys($templates)) as $name) {
            $start = hrtime(true);
            $statement = $admin->prepare($templates[$name]);
            $statement->execute($params);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $expected ??= $rows;
            if ($rows !== $expected) {
                throw new RuntimeException('Complete header rows/type parity failed.');
            }
            if ($round >= 0) {
                $case['samples_ms'][$name][] = $elapsed;
            }
        }
    }
    $report['cases'][] = $case;
}
$report['complete'] = true;
file_put_contents($output . '/header-candidates-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Owned header comparison complete: ' . $size . PHP_EOL;
