<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/CoachHistoryFixture.php';

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
    'baseline' => 'ab60bfdb827d7daee143541baaec222d99cf8ba3', 'source_sha256' => [],
    'method' => 'Same data, 4 warmups and 17 alternating pairs; entire original history vs first25 with exact total count; PDO emulated prepares match db.php. Separate SQL diagnostics.',
    'cases' => []];
foreach (['includes/coach_history_read.php','includes/training_history_read.php','ajax_treninky.php','moje_treninky.php'] as $f) {
    $report['source_sha256'][$f] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $f);
}
$percentile = static function (array $values, float $p): float {
    sort($values);
    return $values[(int) ceil(count($values) * $p) - 1];
};
try {
    foreach ([100, 1000, 10000] as $size) {
        if ($native) {
            $schema = 'p11_coach_history_' . bin2hex(random_bytes(8));
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
        Tests\Support\CoachHistoryFixture::seed($pdo, $size);
        $checksums = static function (PDO $p): array {
            $out = [];
            foreach (['treninky','trenink_trener','trenink_sportovec','training_roster_links','club_team_edges','trenink_skupina','trenink_podskupina','trenink_tag'] as $table) {
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
        $cases = Tests\Support\CoachHistoryFixture::cases($pdo);
        foreach (['all','group','month_without_year','rare','empty','historical_root'] as $name) {
            [$from,$params] = $cases[$name === 'historical_root' ? 'all' : $name];
            $team = $name === 'historical_root' ? 1 : 0;
            $expected = Tests\Support\CoachHistoryFixture::normalize(Tests\Support\CoachHistoryFixture::original($pdo, $from, $params, $team));
            $wanted = Tests\Support\CoachHistoryFixture::subset($expected, array_slice($expected['rows'], 0, 25));
            $total = count($expected['rows']);
            $read = static function (PDO $p) use ($from, $params, $team, $total): array {
                $page = CoachHistoryRead::page($p, $from, $params, $team, 1);
                if ($page['total'] !== $total) {
                    throw new RuntimeException('Whole-history total changed.');
                }
                return Tests\Support\CoachHistoryFixture::related($p, $page['rows']);
            };
            $samples = ['before' => [],'after' => []];
            $memory = ['before' => [],'after' => []];
            for ($i = -4; $i < 17; $i++) {
                foreach ($i % 2 === 0 ? ['before','after'] : ['after','before'] as $variant) {
                    memory_reset_peak_usage();
                    $usage = memory_get_usage(false);
                    $start = hrtime(true);
                    $value = $variant === 'before' ? Tests\Support\CoachHistoryFixture::original($pdo, $from, $params, $team) : $read($pdo);
                    $ms = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage(false) - $usage;
                    $value = Tests\Support\CoachHistoryFixture::normalize($value);
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
                $variant === 'before' ? Tests\Support\CoachHistoryFixture::original($pdo, $from, $params, $team) : $read($pdo);
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
