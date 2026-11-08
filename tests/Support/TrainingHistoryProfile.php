<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/TrainingHistoryFixture.php';
require_once __DIR__ . '/TrainingHistoryBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_history_read.php';

$native = getenv('EVIDENCE_PERFORMANCE_CONFIRM') === 'SYNTHETIC-ONLY';
$admin = null;
$schemas = [];
$server = 'SQLite';
$dsn = 'sqlite::memory:';
if ($native) {
    $port = (string) getenv('EVIDENCE_MARIADB_SMOKE_PORT');
    $directory = (string) getenv('EVIDENCE_PERFORMANCE_DATADIR');
    if (!ctype_digit($port) || $directory === '') {
        throw new RuntimeException('Explicit owned localhost server required.');
    }
    $dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
    $admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
    if ((int) $identity['port'] !== (int) $port || rtrim($identity['directory'], '/') !== rtrim($directory, '/')) {
        throw new RuntimeException('Wrong owned database server.');
    }
    $server = $identity['version'];
}
$report = ['complete' => false, 'synthetic_only' => true, 'server' => $server, 'php' => PHP_VERSION,
    'baseline' => 'cc80556131f855cb29ec75c335873574930577af', 'source_sha256' => [],
    'method' => 'Same data, 4 warmups and 17 alternating pairs; entire original history vs first25 with exact total statistics. Separate SQL diagnostics.',
    'cases' => []];
foreach (['includes/training_history_read.php','sprava_treninku.php','sprava_vsech_treninku.php'] as $f) {
    $report['source_sha256'][$f] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $f);
}
$percentile = static function (array $values, float $p): float {
    sort($values);
    return $values[(int) ceil(count($values) * $p) - 1];
};
try {
    foreach ([100, 1000, 10000] as $size) {
        if ($native) {
            $schema = 'p11_history_' . bin2hex(random_bytes(8));
            $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $schemas[] = $schema;
            $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->exec('SET SESSION query_cache_type=OFF');
            if (!in_array(strtoupper((string) $pdo->query('SELECT @@session.query_cache_type')->fetchColumn()), ['OFF','0'], true)) {
                throw new RuntimeException('SQL result cache must be OFF.');
            }
        } else {
            $pdo = new Tests\Support\QueryProfilePdo($dsn);
        }
        Tests\Support\TrainingHistoryFixture::seed($pdo, $size);
        $checksums = static function (PDO $p): array {
            $out = [];
            foreach (['treninky','trenink_trener','trenink_sportovec','training_roster_links','club_team_edges'] as $table) {
                $out[$table] = hash('sha256', json_encode($p->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR));
            }
            return $out;
        };
        $beforeChecksum = $checksums($pdo);
        if ($native) {
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        } else {
            $pdo->exec('PRAGMA query_only=ON');
        }
        foreach (['all' => ['1=1',[],0], 'trainer' => ['tt.trener_id=?',[2],0],
            'category' => ['t.kategorie=?',['mtb'],0], 'rare' => ['t.id=?',[$size],0],
            'empty' => ['t.id<0',[],0], 'historical_root' => ['1=1',[],1]] as $name => [$where,$params,$team]) {
            $sql = Tests\Support\TrainingHistoryFixture::sql($pdo, true, $where);
            $expected = Tests\Support\TrainingHistoryBaseline::read($pdo, $sql, $params, $team);
            ksort($expected['labels'], SORT_NUMERIC);
            $expected['participants'] = Tests\Support\TrainingHistoryFixture::participantGroups($expected['participants']);
            $ids = array_column(array_slice($expected['rows'], 0, 25), 'id');
            $wanted = ['rows' => array_slice($expected['rows'], 0, 25),
                'labels' => array_intersect_key($expected['labels'], array_flip($ids)),
                'participants' => array_values(array_filter($expected['participants'], static fn (array $r): bool => in_array($r['trenink_id'], $ids)))];
            $read = static function (PDO $p) use ($sql, $params, $team): array {
                $page = TrainingHistoryRead::page($p, $sql, $params, $team, 1);
                return ['rows' => $page['rows'], 'labels' => trainingRosterBridgeTrainingLabels($p, $page['rows']),
                    'participants' => Tests\Support\TrainingHistoryFixture::participants($p, array_column($page['rows'], 'id'))];
            };
            $samples = ['before' => [],'after' => []];
            $memory = ['before' => [],'after' => []];
            for ($i = -4; $i < 17; $i++) {
                foreach ($i % 2 === 0 ? ['before','after'] : ['after','before'] as $variant) {
                    memory_reset_peak_usage();
                    $usage = memory_get_usage(false);
                    $start = hrtime(true);
                    $value = $variant === 'before' ? Tests\Support\TrainingHistoryBaseline::read($pdo, $sql, $params, $team) : $read($pdo);
                    $ms = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage(false) - $usage;
                    // Labels are a lookup map; cross-training map insertion order is not rendered.
                    ksort($value['labels'], SORT_NUMERIC);
                    $value['participants'] = Tests\Support\TrainingHistoryFixture::participantGroups($value['participants']);
                    if ($value !== ($variant === 'before' ? $expected : $wanted)) {
                        throw new RuntimeException('Changed history rows, labels or participants.');
                    }
                    if ($i >= 0) {
                        $samples[$variant][] = $ms;
                        $memory[$variant][] = $peak;
                    }
                    unset($value);
                }
            }
            $diagnostics = [];
            foreach (['before','after'] as $variant) {
                $pdo->profile = [];
                $pdo->profiling = true;
                $variant === 'before' ? Tests\Support\TrainingHistoryBaseline::read($pdo, $sql, $params, $team) : $read($pdo);
                $pdo->profiling = false;
                foreach ($pdo->profile as $q) {
                    if (!preg_match('/^(SELECT|SHOW|PRAGMA)/', $q['sql'])) {
                        throw new RuntimeException('Unexpected write.');
                    }
                }
                $diagnostics[$variant] = ['sql_count' => count($pdo->profile),
                    'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                    'max_bindings' => max(array_map(static fn (array $q): int => count($q['parameters']), $pdo->profile))];
            }
            $report['cases'][] = ['trainings' => $size,'case' => $name,'total' => count($expected['rows']),
                'page_rows' => count($wanted['rows']),'parity' => true,'samples_ms' => $samples,
                'before_median_ms' => $percentile($samples['before'], .5),'after_median_ms' => $percentile($samples['after'], .5),
                'before_p95_ms' => $percentile($samples['before'], .95),'after_p95_ms' => $percentile($samples['after'], .95),
                'before_peak_bytes' => max($memory['before']),'after_peak_bytes' => max($memory['after']), 'diagnostics' => $diagnostics];
            unset($expected,$wanted);
        }
        if ($checksums($pdo) !== $beforeChecksum) {
            throw new RuntimeException('Read-only history changed.');
        }
        $pdo = null;
    }
} finally {
    $pdo = null;
    foreach ($schemas as $schema) {
        $admin->exec('DROP DATABASE `' . $schema . '`');
    }
}
$report['complete'] = true;
$report['data_checksums_unchanged'] = true;
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
