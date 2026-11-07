<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/TrainingDayChoicesFixture.php';

$mysql = in_array('--mariadb', $argv, true);
$admin = null;
$server = ['driver' => 'sqlite', 'version' => (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn()];
if ($mysql) {
    $port = (string) getenv('EVIDENCE_MARIADB_SMOKE_PORT');
    $directory = (string) getenv('EVIDENCE_PERFORMANCE_DATADIR');
    if (getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY' || !ctype_digit($port) || $directory === '') {
        throw new RuntimeException('Explicit owned localhost port, datadir and SYNTHETIC-ONLY confirmation required.');
    }
    $dsn = "mysql:host=127.0.0.1;port=$port;charset=utf8mb4";
    $admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
    $normalize = static fn (string $path): string => rtrim(strtolower(str_replace('\\', '/', $path)), '/');
    if ((int) $server['port'] !== (int) $port || $normalize($directory) !== $normalize($server['directory'])) {
        throw new RuntimeException('Wrong database server; refusing fixture operations.');
    }
    $server['driver'] = 'mysql';
    unset($server['directory']);
}

function lessonChoiceMeasure(PDO $pdo, Tests\Support\QueryProfilePdo $instrument, int $actor, string $search, string $on): array
{
    $calls = ['before' => 'beforeTrainingDayChoices', 'after' => 'trainingDayChoices'];
    $expected = beforeTrainingDayChoices($pdo, $actor, $search, $on);
    if ($expected !== trainingDayChoices($pdo, $actor, $search, $on)) {
        throw new RuntimeException('Complete ordered lesson rows differ.');
    }
    $hash = hash('sha256', serialize($expected));
    $out = [];
    foreach ($calls as $mode => $call) {
        $out[$mode] = ['samples_ms' => [], 'peak_increment_bytes' => 0, 'output_sha256' => $hash];
        for ($i = 0; $i < 5; $i++) {
            $call($pdo, $actor, $search, $on);
        }
    }
    for ($i = 0; $i < 30; $i++) {
        foreach ($i % 2 === 0 ? $calls : array_reverse($calls, true) as $mode => $call) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $result = $call($pdo, $actor, $search, $on);
            $out[$mode]['samples_ms'][] = (hrtime(true) - $start) / 1e6;
            $out[$mode]['peak_increment_bytes'] = max($out[$mode]['peak_increment_bytes'], memory_get_peak_usage() - $memory);
            if ($result !== $expected) {
                throw new RuntimeException('Rows changed in paired samples.');
            }
        }
    }
    foreach ($calls as $mode => $call) {
        $call($instrument, $actor, $search, $on);
        $instrument->profile = [];
        $instrument->profiling = true;
        $result = $call($instrument, $actor, $search, $on);
        $instrument->profiling = false;
        if ($result !== $expected) {
            throw new RuntimeException('Instrumented rows differ.');
        }
        $groups = [];
        foreach ($instrument->profile as $q) {
            $sql = $q['sql'];
            if (!isset($groups[$sql])) {
                $groups[$sql] = ['count' => 0, 'rows' => 0, 'parameter_count' => count($q['parameters'])];
                if ($instrument->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $explain = $instrument->prepare('EXPLAIN ' . $sql);
                    $explain->execute($q['parameters']);
                    $groups[$sql]['explain'] = $explain->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            $groups[$sql]['count']++;
            $groups[$sql]['rows'] += $q['fetched_rows'];
        }
        $times = $out[$mode]['samples_ms'];
        sort($times);
        $out[$mode] += ['sql' => count($instrument->profile),
            'fetched_rows' => array_sum(array_column($instrument->profile, 'fetched_rows')),
            'queries' => $groups, 'median_ms' => ($times[14] + $times[15]) / 2, 'p95_ms' => $times[28]];
    }
    if ($out['after']['sql'] > ($actor === 4 ? 9 : 6)) {
        throw new RuntimeException('Bounded current lesson read exceeded its query budget.');
    }
    $pdo->beginTransaction();
    try {
        if (beforeTrainingDayChoices($pdo, $actor, $search, $on) !== trainingDayChoices($pdo, $actor, $search, $on)) {
            throw new RuntimeException('Transaction fallback rows differ.');
        }
    } finally {
        $pdo->rollBack();
    }
    return $out + ['parity' => true, 'transaction_parity' => true];
}

$cases = [];
$cleanup = [];
$specs = [[0, 1, 'coach', '', ''], [1, 1, 'coach', '', ''], [50, 1, 'coach', '', ''],
    [1000, 1, 'coach', '', ''], [50, 2, 'sports_lead', '', ''], [50, 3, 'coach', '', ''],
    [50, 4, 'coach', '', ''], [50, 4, 'sports_lead', '', ''], [100, 1, 'coach', 'kurz_%', ''],
    [100, 1, 'coach', 'ŽLUTÝ', '2026-11-07'], [50, 1, 'coach', 'NOTFOUND', ''], [50, 1, 'coach', '', 'invalid']];
foreach ($specs as $index => [$size, $actor, $position, $search, $on]) {
    $schema = 'lesson_read_' . bin2hex(random_bytes(8));
    try {
        if ($mysql) {
            $admin->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $connectionDsn = $dsn . ';dbname=' . $schema;
        } else {
            $connectionDsn = 'sqlite::memory:';
        }
        $pdo = new PDO(
            $connectionDsn,
            $mysql ? 'root' : null,
            $mysql ? '' : null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $instrument = new Tests\Support\QueryProfilePdo($connectionDsn, $mysql ? 'root' : null, $mysql ? '' : null);
        if ($mysql) {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $instrument->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        trainingDayChoicesSeed($pdo, $size);
        if (!$mysql) {
            trainingDayChoicesSeed($instrument, $size);
        }
        trainingDayChoicesSession($actor, $position);
        $cases[(string) $index] = ['lesson_count' => $size, 'actor' => $actor, 'position' => $position, 'search' => $search, 'on' => $on,
            'measurement' => lessonChoiceMeasure($pdo, $instrument, $actor, $search, $on)];
    } finally {
        $pdo = $instrument = null;
        if ($mysql) {
            $admin->exec("DROP DATABASE IF EXISTS `$schema`");
            $cleanup[] = $schema;
        }
    }
}
$hashes = [];
foreach (['includes/training_day.php', 'includes/coach_scope.php', 'includes/coach_training_access.php',
    'tests/Support/TrainingDayChoicesBaseline.php', 'tests/Support/TrainingDayChoicesFixture.php', 'tests/Support/TrainingDayChoicesProfile.php'] as $file) {
    $hashes[$file] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $file);
}
echo json_encode(['php' => PHP_VERSION, 'server' => $server,
    'method' => '5 warmups, 30 interleaved plain PDO pairs, exact ordered row and transaction parity; separate warmed SQL/EXPLAIN. Synthetic contract fixtures; not full HTTP latency.',
    'source_sha256' => $hashes, 'cases' => $cases, 'dropped_owned_schemas' => $cleanup], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
