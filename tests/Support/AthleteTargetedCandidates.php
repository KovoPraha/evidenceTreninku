<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$size = (int) ($argv[2] ?? 0);
if (!in_array($size, [1000,10000,30000], true)) {
    throw new RuntimeException('Owned size required.');
}
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$analysis = $admin->query('ANALYZE TABLE treninky,trenink_sportovec,trenink_mereni,mereni_zaznamy,sportovec_poznamka')->fetchAll(PDO::FETCH_ASSOC);
$report = ['size' => $size, 'shared_analysis' => $analysis, 'synthetic_only' => true, 'selector_only' => true, 'pairs' => 7, 'warmups' => 2, 'cases' => []];
$order = ' ORDER BY t.datum DESC,t.id DESC';
$owned = '(SELECT trenink_id FROM trenink_sportovec WHERE sportovec_id=:attendance_sid
    UNION SELECT tm.trenink_id FROM trenink_mereni tm
    JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id WHERE mz.sportovec_id=:measurement_sid)';
foreach ([1001, 1002, 1012, 1013, 1020] as $person) {
    foreach ([0, (int) date('Y'), 2099] as $year) {
        $filter = $year > 0 ? 'YEAR(t.datum)=:year AND ' : '';
        $params = [':attendance_sid' => $person, ':measurement_sid' => $person];
        if ($year > 0) {
            $params[':year'] = $year;
        }
        $templates = [
            'exists' => 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . '(
                EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)
                OR EXISTS (SELECT 1 FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                    WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid))' . $order,
            'owned_join' => 'SELECT t.id,t.delka FROM treninky t JOIN ' . $owned . ' owned ON owned.trenink_id=t.id
                WHERE ' . $filter . '1=1' . $order,
            'owned_in' => 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . 't.id IN ' . $owned . $order,
        ];
        $templates = ['exists' => $templates['exists']];
        if ($year > 0) {
            $templates['filtered_year'] = str_replace(
                'WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid',
                'JOIN treninky selected_t ON selected_t.id=tm.trenink_id'
                    . ' WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid AND YEAR(selected_t.datum)=:ownership_year',
                $templates['exists']
            );
            $templates['anti_not_exists'] = $templates['filtered_year'] . '';
            $templates['anti_not_exists'] = str_replace(
                ' AND YEAR(selected_t.datum)=:ownership_year',
                ' AND YEAR(selected_t.datum)=:ownership_year AND NOT EXISTS (SELECT 1 FROM trenink_sportovec present'
                    . ' WHERE present.trenink_id=selected_t.id AND present.sportovec_id=:present_sid)',
                $templates['filtered_year']
            );
            $templates['anti_left'] = str_replace(
                'JOIN treninky selected_t ON selected_t.id=tm.trenink_id',
                'JOIN treninky selected_t ON selected_t.id=tm.trenink_id LEFT JOIN trenink_sportovec present'
                    . ' ON present.trenink_id=selected_t.id AND present.sportovec_id=:present_sid',
                $templates['filtered_year']
            );
            $templates['anti_left'] = str_replace(
                ' AND YEAR(selected_t.datum)=:ownership_year',
                ' AND YEAR(selected_t.datum)=:ownership_year AND present.trenink_id IS NULL',
                $templates['anti_left']
            );
            $templates['case'] = 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . 'CASE WHEN
                EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)
                THEN 1 ELSE EXISTS (SELECT 1 FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                    WHERE tm.trenink_id=t.id AND mz.sportovec_id=:measurement_sid) END' . $order;

            $casePrefix = 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . 'CASE WHEN
                EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)
                THEN 1 ELSE t.id IN (';
            $caseSource = 'SELECT tm.trenink_id FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                JOIN treninky selected_t ON selected_t.id=tm.trenink_id';
            $caseWhere = ' WHERE mz.sportovec_id=:measurement_sid AND YEAR(selected_t.datum)=:ownership_year';
            $templates['case_in_year'] = $casePrefix . $caseSource . $caseWhere . ') END' . $order;
            $templates['case_in_anti'] = $casePrefix . $caseSource
                . ' LEFT JOIN trenink_sportovec present ON present.trenink_id=selected_t.id AND present.sportovec_id=:present_sid'
                . $caseWhere . ' AND present.trenink_id IS NULL) END' . $order;
        }
        if ($year > 0) {
            $prefix = 'SELECT t.id,t.delka FROM treninky t WHERE ' . $filter . 'CASE WHEN ';
            $attendanceExists = 'EXISTS (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=:attendance_sid)';
            $attendanceIn = 't.id IN (SELECT ts.trenink_id FROM trenink_sportovec ts WHERE ts.sportovec_id=:attendance_sid)';
            $lazyMeasurement = ' THEN 1 ELSE EXISTS (SELECT 1 FROM (
                SELECT DISTINCT tm.trenink_id FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
                JOIN treninky selected_t ON selected_t.id=tm.trenink_id
                WHERE mz.sportovec_id=:measurement_sid AND YEAR(selected_t.datum)=:ownership_year
                ) owned_keys WHERE owned_keys.trenink_id=t.id) END' . $order;
            $templates = ['exists' => $templates['exists'],
                'lazy_exists' => $prefix . $attendanceExists . $lazyMeasurement,
                'lazy_in' => $prefix . $attendanceIn . $lazyMeasurement];
        }
        $expected = null;
        $case = ['sid' => $person, 'year' => $year, 'parity' => true, 'samples_ms' => [], 'explain' => []];
        $caseBindings = [];
        foreach ($templates as $name => $sql) {
            $bindings = $params;
            if (str_contains($sql, ':ownership_year')) {
                $bindings[':ownership_year'] = $year;
            }
            if (str_contains($sql, ':present_sid')) {
                $bindings[':present_sid'] = $person;
            }
            if (str_contains($sql, ':ownership_start')) {
                $bindings[':ownership_start'] = sprintf('%04d-00-00', $year);
                $bindings[':ownership_end'] = sprintf('%04d-12-31', $year);
            }
            $caseBindings[$name] = $bindings;
            $explain = $admin->prepare('EXPLAIN FORMAT=JSON ' . $sql);
            $explain->execute($bindings);
            $case['explain'][$name] = $explain->fetchColumn();
        }
        for ($round = -2; $round < 7; ++$round) {
            foreach ($round % 2 === 0 ? array_keys($templates) : array_reverse(array_keys($templates)) as $name) {
                $start = hrtime(true);
                $statement = $admin->prepare($templates[$name]);
                $statement->execute($caseBindings[$name]);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $expected ??= $rows;
                if ($rows !== $expected) {
                    throw new RuntimeException('Candidate ordered key/type parity failed.');
                }
                if ($round >= 0) {
                    $case['samples_ms'][$name][] = $elapsed;
                }
            }
        }
        $case['rows'] = count($expected);
        $report['cases'][] = $case;
    }
}
file_put_contents($output . '/candidate-selectors-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Owned SQL candidate comparison complete.' . PHP_EOL;
