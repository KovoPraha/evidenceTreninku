<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/TrainingRosterFilterBaseline.php';
require_once __DIR__ . '/PlannerCopyCountBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

$schema = 'w82_filter_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    w72LegacyColumns($pdo, 'planovane_treninky', ['skupina_id' => 'INT NULL']);
    for ($team = 3; $team <= 20; $team++) {
        $pdo->prepare('INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id)'
            . " VALUES(?,1,?,'Synthetic','all','synthetic','active',7)")->execute([$team, 'W82-' . $team]);
    }
    $pdo->exec("INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,valid_to,actor_trainer_id,reason)"
        . " VALUES(1,2,'2026-01-01','2026-06-30',7,'Synthetic'),(1,3,'2026-04-01',NULL,7,'Synthetic'),"
        . "(2,3,'2026-02-01','2026-03-31',7,'Synthetic'),(3,1,'2026-02-01','2026-03-31',7,'Synthetic')");
    $plan = $pdo->prepare('INSERT INTO planovane_treninky(id,datum,stav,trener_id,skupina_id) VALUES(?,?,?,7,?)');
    $actual = $pdo->prepare('INSERT INTO treninky(id,datum,napln) VALUES(?,?,?)');
    $link = $pdo->prepare('INSERT INTO training_roster_links(plan_id,trenink_id,team_id,target_date,team_code_snapshot,'
        . "team_name_snapshot,created_by_trainer_id) VALUES(?,?,?,?,'SYN',?,7)");
    $inserted = 0;
    $results = [];
    foreach ([0, 1, 12, 52] as $weeks) {
        $pdo->beginTransaction();
        for ($week = $inserted; $week < $weeks; $week++) {
            $date = (new DateTimeImmutable('2026-01-01'))->modify('+' . ($week * 7) . ' days')->format('Y-m-d');
            for ($team = 1; $team <= 20; $team++) {
                $id = 1000 + $week * 20 + $team;
                $actual->execute([$id, $date, 'Synthetic actual ' . $id]);
                $plan->execute([$id, $date, ['planovany', 'evidovany', 'zruseny', 'PLANOVANY'][$id % 4], $id % 2 + 1]);
                if ($team % 7 !== 0) {
                    $link->execute([$id, null, $team, $date, 'Team ' . $team]);
                    $link->execute([null, $id, $team, $date, 'Team ' . $team]);
                }
            }
        }
        $pdo->commit();
        $inserted = $weeks;
        $rows = $pdo->query('SELECT id,datum FROM planovane_treninky WHERE id>=1000 ORDER BY datum,id')->fetchAll();
        foreach (['Plan', 'Training', 'CopyUnfiltered', 'CopyTeam'] as $kind) {
            $call = static function (PDO $db, string $mode) use ($kind, $rows): array|int {
                if (str_starts_with($kind, 'Copy')) {
                    $function = $mode === 'baseline' ? 'w82CopyCountBaseline' : 'trainingPlannerCopyCount';
                    return $function($db, '2026-01-01', '2026-12-31', true, 7, 0, 0, $kind === 'CopyTeam' ? 1 : 0);
                }
                $function = $mode === 'baseline' ? 'w82Filter' . $kind . 'Baseline' : 'trainingRosterBridgeFilter' . $kind . 'Rows';
                return $function($db, $rows, 1);
            };
            $counts = [];
            $hash = null;
            foreach (['baseline', 'current'] as $mode) {
                $pdo->profile = [];
                $pdo->profiling = true;
                $value = $call($pdo, $mode);
                $pdo->profiling = false;
                $valueHash = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
                $hash ??= $valueHash;
                if ($hash !== $valueHash) {
                    throw new RuntimeException('Filter or copy count changed.');
                }
                $edges = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_team_edges'));
                $counts[$mode] = ['sql' => count($pdo->profile), 'edge_sql' => count($edges),
                    'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))];
            }
            if ($counts['current']['edge_sql'] > 1 || $counts['current']['sql'] > $counts['baseline']['sql']) {
                throw new RuntimeException('Filter query budget failed.');
            }
            $returned = is_array($value) ? count($value) : $value;
            unset($value);
            $pdo->profile = [];
            $plain = w72Connection($schema);
            $samples = ['baseline' => [], 'current' => []];
            for ($iteration = -1; $iteration < 5; $iteration++) {
                foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                    memory_reset_peak_usage();
                    $memory = memory_get_usage();
                    $start = hrtime(true);
                    $value = $call($plain, $mode);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage() - $memory;
                    if ($hash !== hash('sha256', json_encode($value, JSON_THROW_ON_ERROR))) {
                        throw new RuntimeException('Timed filter changed.');
                    }
                    unset($value);
                    if ($iteration >= 0) {
                        $samples[$mode][] = ['ms' => $elapsed, 'incremental_peak_bytes' => $peak];
                    }
                }
            }
            $median = static function (array $values): float {
                sort($values);
                return $values[intdiv(count($values), 2)];
            };
            $peaks = array_map(static fn (array $values): float => $median(array_column($values, 'incremental_peak_bytes')), $samples);
            if ($peaks['current'] > $peaks['baseline'] + 65536) {
                throw new RuntimeException('Filter memory overhead exceeded 64 KiB.');
            }
            $results[] = ['weeks' => $weeks, 'teams' => 20, 'kind' => $kind, 'counts' => $counts, 'same_data' => true,
                'sha256' => $hash, 'returned' => $returned, 'samples' => $samples, 'median_peak_bytes' => $peaks,
                'median_ms' => array_map(static fn (array $values): float => $median(array_column($values, 'ms')), $samples)];
        }
    }
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(), 'production_mutations' => false,
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
