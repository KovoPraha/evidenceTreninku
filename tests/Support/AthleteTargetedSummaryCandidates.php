<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$diagnostic = json_decode(file_get_contents(dirname(__DIR__, 2)
    . '/docs/verification/athlete-profile-targeted-read-20261109/diagnostic-c9/native-10.3/targeted-before-measurements-1001.json'), true, 512, JSON_THROW_ON_ERROR);
$baseline = null;
foreach ($diagnostic['queries'] as $query) {
    if (str_contains($query['sql'], 'AS first_position')) {
        $baseline = $query['sql'];
    }
}
if ($baseline === null) {
    throw new RuntimeException('Frozen current summary required.');
}
$source = 'FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id JOIN treninky t ON t.id=tm.trenink_id';
$templates = ['baseline' => $baseline];
foreach ([
    'links_first' => 'FROM trenink_mereni tm STRAIGHT_JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id STRAIGHT_JOIN treninky t ON t.id=tm.trenink_id',
    'owner_first' => 'FROM mereni_zaznamy mz STRAIGHT_JOIN trenink_mereni tm ON tm.mereni_id=mz.id STRAIGHT_JOIN treninky t ON t.id=tm.trenink_id',
] as $label => $replacement) {
    $templates[$label] = str_replace($source, $replacement, $baseline, $replacements);
    if ($replacements !== 1) {
        throw new RuntimeException('Frozen grouped source mismatch.');
    }
}
$templates['narrow_owner'] = str_replace(
    $source . ' WHERE mz.sportovec_id=?',
    'FROM (SELECT id,typ FROM mereni_zaznamy WHERE sportovec_id=? LIMIT 18446744073709551615) mz'
    . ' STRAIGHT_JOIN trenink_mereni tm ON tm.mereni_id=mz.id STRAIGHT_JOIN treninky t ON t.id=tm.trenink_id',
    $baseline,
    $replacements
);
if ($replacements !== 1) {
    throw new RuntimeException('Frozen grouped filter mismatch.');
}
$templates['hex_group'] = str_replace(
    ['SELECT BINARY mz.typ AS typ,COUNT(*)', 'GROUP BY BINARY mz.typ'],
    ['SELECT UNHEX(HEX(mz.typ)) AS typ,COUNT(*)', 'GROUP BY HEX(mz.typ)'],
    $baseline
);
$report = ['synthetic_only'  => true, 'summary_only' => true, 'pairs' => 7, 'warmups' => 2, 'cases' => []];
foreach ([1001, 1012, 1013, 1020] as $person) {
    $expected = null;
    $case = ['sid' => $person, 'parity' => true, 'samples_ms' => [], 'explain' => []];
    foreach ($templates as $name => $sql) {
        $explain = $admin->prepare('EXPLAIN FORMAT=JSON ' . $sql);
        $explain->execute([$person, $person]);
        $case['explain'][$name] = $explain->fetchColumn();
    }
    for ($round = -2; $round < 7; ++$round) {
        foreach ($round % 2 === 0 ? array_keys($templates) : array_reverse(array_keys($templates)) as $name) {
            $diskBefore = (int) $admin->query("SHOW SESSION STATUS LIKE 'Created_tmp_disk_tables'")->fetch(PDO::FETCH_NUM)[1];
            $start = hrtime(true);
            $statement = $admin->prepare($templates[$name]);
            $statement->execute([$person, $person]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $diskAfter = (int) $admin->query("SHOW SESSION STATUS LIKE 'Created_tmp_disk_tables'")->fetch(PDO::FETCH_NUM)[1];
            usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['typ'], (string) $b['typ']));
            $expected ??= $rows;
            if ($rows !== $expected) {
                throw new RuntimeException('Candidate complete summary parity failed.');
            }
            if ($round >= 0) {
                $case['samples_ms'][$name][] = $elapsed;
                $case['disk_temporary_tables'][$name][] = $diskAfter - $diskBefore;
            }
        }
    }
    $case['types'] = count($expected);
    $report['cases'][] = $case;
}
file_put_contents($output . '/candidate-summaries.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Owned complete summary comparison complete.' . PHP_EOL;
