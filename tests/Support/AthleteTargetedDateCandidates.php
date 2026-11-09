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
$indexes = array_column($admin->query('SHOW INDEX FROM treninky')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
if (in_array('idx_rix_history_date_cover', $indexes, true)) {
    throw new RuntimeException('Probe index must start absent.');
}
$checksum = $admin->query('CHECKSUM TABLE treninky EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
$report = ['size' => $size, 'synthetic_only' => true, 'production_ddl' => false,
    'warmups' => 2, 'samples' => 7, 'cases' => [], 'complete' => false];
$expected = [];
$added = false;
try {
    foreach ([false, true] as $indexed) {
        if ($indexed) {
            $start = hrtime(true);
            $admin->exec('CREATE INDEX idx_rix_history_date_cover ON treninky(datum,id,delka)');
            $report['index_build_ms'] = (hrtime(true) - $start) / 1e6;
            $added = true;
        }
        $admin->query('ANALYZE TABLE treninky,trenink_sportovec,trenink_mereni,mereni_zaznamy,sportovec_poznamka')->fetchAll();
        foreach ([1001,1002,1012,1013,1020] as $person) {
            foreach ([0,(int) date('Y'),2099] as $year) {
                $params = [':attendance_sid' => $person, ':measurement_sid' => $person];
                $filter = '';
                if ($year > 0) {
                    $filter = 'YEAR(t.datum)=:year AND ';
                    $params[':year'] = $year;
                }
                $attendance = 'EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)';
                $measurement = 'EXISTS (SELECT 1 FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                    WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid)';
                $order = ' ORDER BY t.datum DESC,t.id DESC';
                $original = 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . '(' . $attendance . ' OR ' . $measurement . ')' . $order;
                $templates = [$indexed ? 'indexed_original' : 'before' => $original];
                if ($indexed && $year > 0) {
                    $range = 't.datum>=:year_start AND t.datum<=:year_end AND ';
                    $templates['ranged'] = str_replace($filter, $filter . $range, $original);
                    $templates['ranged_case'] = 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . $range
                        . 'CASE WHEN ' . $attendance . ' THEN 1 ELSE ' . $measurement . ' END' . $order;
                    $templates['forced_range'] = str_replace(
                        'FROM treninky t WHERE',
                        'FROM treninky t FORCE INDEX (idx_rix_history_date_cover) WHERE',
                        $templates['ranged']
                    );
                }
                foreach ($templates as $name => $sql) {
                    $bindings = $params;
                    if (str_contains($sql, ':year_start')) {
                        $bindings[':year_start'] = sprintf('%04d-00-00', $year);
                        $bindings[':year_end'] = sprintf('%04d-12-31', $year);
                    }
                    $case = ['sid' => $person, 'year' => $year, 'indexed' => $indexed,
                        'variant' => $name, 'parity' => true, 'samples_ms' => []];
                    $plan = $admin->prepare('EXPLAIN FORMAT=JSON ' . $sql);
                    $plan->execute($bindings);
                    $case['explain_raw'] = $plan->fetchColumn();
                    for ($round = -2; $round < 7; ++$round) {
                        $start = hrtime(true);
                        $statement = $admin->prepare($sql);
                        $statement->execute($bindings);
                        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                        $elapsed = (hrtime(true) - $start) / 1e6;
                        $expected[$person][$year] ??= $rows;
                        if ($rows !== $expected[$person][$year]) {
                            throw new RuntimeException('Date index ordered key/type parity failed.');
                        }
                        if ($round >= 0) {
                            $case['samples_ms'][] = $elapsed;
                        }
                    }
                    $case['rows'] = count($rows);
                    $report['cases'][] = $case;
                }
            }
        }
        $status = $admin->query("SHOW TABLE STATUS LIKE 'treninky'")->fetch(PDO::FETCH_ASSOC);
        $report['table_bytes_estimate'][$indexed ? 'after' : 'before'] = [
            'data' => (int) $status['Data_length'], 'indexes' => (int) $status['Index_length']];
    }
    $report['complete'] = true;
} finally {
    if ($added) {
        $admin->exec('DROP INDEX idx_rix_history_date_cover ON treninky');
    }
    $report['probe_index_removed'] = true;
    $report['domain_checksum_unchanged'] = $checksum === $admin->query('CHECKSUM TABLE treninky EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    file_put_contents($output . '/date-selectors-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}
echo 'Owned date index comparison complete: ' . $size . PHP_EOL;
