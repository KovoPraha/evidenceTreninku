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
$index = 'idx_rix_measurement_owner_cover';
$existing = array_column($admin->query('SHOW INDEX FROM mereni_zaznamy')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
if (in_array($index, $existing, true)) {
    throw new RuntimeException('Probe index must start absent.');
}
$checksum = $admin->query('CHECKSUM TABLE mereni_zaznamy EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
$report = ['synthetic_only' => true, 'production_ddl' => false, 'summary_only' => true,
    'warmups' => 2, 'samples' => 7, 'blocks' => [], 'complete' => false];
$expected = [];
$added = false;
try {
    foreach ([false, true, false, true] as $block => $indexed) {
        if ($indexed) {
            $start = hrtime(true);
            $admin->exec('CREATE INDEX idx_rix_measurement_owner_cover ON mereni_zaznamy(sportovec_id,id,typ)');
            $added = true;
            $buildMs = (hrtime(true) - $start) / 1e6;
        } elseif ($added) {
            $admin->exec('DROP INDEX idx_rix_measurement_owner_cover ON mereni_zaznamy');
            $added = false;
        }
        $admin->query('ANALYZE TABLE treninky,trenink_mereni,mereni_zaznamy')->fetchAll();
        $current = ['indexed' => $indexed, 'build_ms' => $indexed ? $buildMs : null, 'cases' => [], 'write_ms' => []];
        $templates = ['automatic' => $baseline];
        if ($indexed) {
            $templates['force_cover'] = str_replace(
                'mereni_zaznamy mz ON',
                'mereni_zaznamy mz FORCE INDEX (idx_rix_measurement_owner_cover) ON',
                $baseline,
                $replacements
            );
            if ($replacements !== 2) {
                throw new RuntimeException('Expected two measurement joins.');
            }
            $source = 'FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id JOIN treninky t ON t.id=tm.trenink_id';
            $replacement = 'FROM mereni_zaznamy mz FORCE INDEX (idx_rix_measurement_owner_cover)'
                . ' STRAIGHT_JOIN trenink_mereni tm ON tm.mereni_id=mz.id STRAIGHT_JOIN treninky t ON t.id=tm.trenink_id';
            $templates['owner_cover'] = str_replace($source, $replacement, $baseline);
        }
        foreach ($templates as $variant => $sql) {
            foreach ([1001, 1012, 1013, 1020] as $person) {
                $case = ['sid' => $person, 'variant' => $variant, 'samples_ms' => [], 'parity' => true];
                $plan = $admin->prepare('EXPLAIN FORMAT=JSON ' . $sql);
                $plan->execute([$person, $person]);
                $case['explain_raw'] = $plan->fetchColumn();
                for ($round = -2; $round < 7; ++$round) {
                    $start = hrtime(true);
                    $statement = $admin->prepare($sql);
                    $statement->execute([$person, $person]);
                    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['typ'], (string) $b['typ']));
                    $expected[$person] ??= $rows;
                    if ($rows !== $expected[$person]) {
                        throw new RuntimeException('Indexed complete summary parity failed.');
                    }
                    if ($round >= 0) {
                        $case['samples_ms'][] = $elapsed;
                    }
                }
                $current['cases'][] = $case;
            }
        }
        $writeRows = $admin->query('SELECT id,sportovec_id,typ,poznamka FROM mereni_zaznamy
            WHERE sportovec_id=1001 ORDER BY id LIMIT 1000')->fetchAll(PDO::FETCH_NUM);
        $writeParams = [];
        foreach ($writeRows as $row) {
            $writeParams = [...$writeParams, 100000000 + (int) $row[0], $row[1], $row[2], $row[3]];
        }
        $insert = $admin->prepare('INSERT INTO mereni_zaznamy(id,sportovec_id,typ,poznamka) VALUES '
            . implode(',', array_fill(0, count($writeRows), '(?,?,?,?)')));
        $current['write_rows'] = count($writeRows);
        $current['write_method'] = 'Prepared INSERT VALUES only; row preparation, transaction start and rollback excluded';
        for ($round = -2; $round < 7; ++$round) {
            $admin->beginTransaction();
            try {
                $start = hrtime(true);
                $insert->execute($writeParams);
                $elapsed = (hrtime(true) - $start) / 1e6;
                if ($round >= 0) {
                    $current['write_ms'][] = $elapsed;
                }
            } finally {
                $admin->rollBack();
            }
        }
        $current['indexes'] = $admin->query('SHOW INDEX FROM mereni_zaznamy')->fetchAll(PDO::FETCH_ASSOC);
        $status = $admin->query("SHOW TABLE STATUS LIKE 'mereni_zaznamy'")->fetch(PDO::FETCH_ASSOC);
        $current['table_bytes_estimate'] = ['data' => (int) $status['Data_length'], 'indexes' => (int) $status['Index_length']];
        $report['blocks'][] = $current;
    }
    $report['complete'] = true;
} finally {
    if ($added) {
        $admin->exec('DROP INDEX idx_rix_measurement_owner_cover ON mereni_zaznamy');
    }
    $report['probe_index_removed'] = true;
    $report['domain_checksum_unchanged'] = $checksum === $admin->query('CHECKSUM TABLE mereni_zaznamy EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    file_put_contents($output . '/candidate-index.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}
echo 'Owned index comparison complete.' . PHP_EOL;
