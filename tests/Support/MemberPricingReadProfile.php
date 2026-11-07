<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/MemberPricingReadFixture.php';

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

function memberPricingMeasure(PDO $pdo, Tests\Support\QueryProfilePdo $instrument, array $catalog, int $account): array
{
    $calls = ['before' => 'beforeMemberPricingCatalog', 'after' => 'shopMemberPricesApplyToCatalog'];
    $output = [];
    foreach ($calls as $mode => $call) {
        $result = $call($pdo, $account, $catalog, '2026-11-07');
        if ($mode === 'before') {
            $expected = $result;
        } elseif ($result !== $expected) {
            throw new RuntimeException('Complete pricing output differs.');
        }
        $output[$mode] = ['samples_ms' => [], 'peak_increment_bytes' => 0,
            'output_sha256' => hash('sha256', json_encode($result, JSON_THROW_ON_ERROR))];
        unset($result);
        for ($i = 0; $i < 5; $i++) {
            $call($pdo, $account, $catalog, '2026-11-07');
        }
    }
    unset($expected);
    // Alternate order to avoid always assigning warmed/later machine load to the new implementation.
    for ($i = 0; $i < 30; $i++) {
        foreach ($i % 2 === 0 ? $calls : array_reverse($calls, true) as $mode => $call) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $result = $call($pdo, $account, $catalog, '2026-11-07');
            $output[$mode]['samples_ms'][] = (hrtime(true) - $start) / 1e6;
            $output[$mode]['peak_increment_bytes'] = max($output[$mode]['peak_increment_bytes'], memory_get_peak_usage() - $memory);
            unset($result);
        }
    }
    foreach ($calls as $mode => $call) {
        $call($instrument, $account, $catalog, '2026-11-07');
        $instrument->profile = [];
        $instrument->profiling = true;
        $result = $call($instrument, $account, $catalog, '2026-11-07');
        $instrument->profiling = false;
        if (hash('sha256', json_encode($result, JSON_THROW_ON_ERROR)) !== $output[$mode]['output_sha256']) {
            throw new RuntimeException('Instrumented output differs.');
        }
        $groups = [];
        foreach ($instrument->profile as $q) {
            $key = preg_replace('/\s+/', ' ', trim($q['sql']));
            if (!isset($groups[$key])) {
                $groups[$key] = ['count' => 0, 'rows' => 0, 'parameter_count' => count($q['parameters'])];
                if ($instrument->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $explain = $instrument->prepare('EXPLAIN ' . $q['sql']);
                    $explain->execute($q['parameters']);
                    $groups[$key]['explain'] = $explain->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            $groups[$key]['count']++;
            $groups[$key]['rows'] += $q['fetched_rows'];
        }
        $output[$mode]['sql'] = count($instrument->profile);
        $output[$mode]['fetched_rows'] = array_sum(array_column($instrument->profile, 'fetched_rows'));
        $output[$mode]['queries'] = $groups;
        $times = $output[$mode]['samples_ms'];
        sort($times);
        $output[$mode]['median_ms'] = ($times[14] + $times[15]) / 2;
        $output[$mode]['p95_ms'] = $times[28];
    }
    $output['parity'] = true;
    $output['speedup'] = $output['before']['median_ms'] / $output['after']['median_ms'];
    if ($account === 1 && $output['after']['sql'] > 4) {
        throw new RuntimeException('500-product pricing read budget exceeded.');
    }
    return $output;
}

$cases = [];
$cleanup = [];
foreach ([[12, false, 1], [100, false, 1], [500, false, 1], [12, true, 1], [100, true, 1], [500, true, 1],
    [500, true, 2], [500, true, 3], [500, true, 0]] as [$size, $distinct, $account]) {
    $schema = 'pricing_read_' . bin2hex(random_bytes(8));
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
        memberPricingReadSeed($pdo, $distinct ? $size : 1);
        if (!$mysql) {
            memberPricingReadSeed($instrument, $distinct ? $size : 1);
        } else {
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $instrument->exec('SET SESSION TRANSACTION READ ONLY');
        }
        $key = ($distinct ? 'products' : 'variants') . '_' . $size . '_account_' . $account;
        $cases[$key] = memberPricingMeasure($pdo, $instrument, memberPricingReadCatalog($size, $distinct), $account);
    } finally {
        $pdo = $instrument = null;
        if ($mysql) {
            $admin->exec("DROP DATABASE IF EXISTS `$schema`");
            $cleanup[] = $schema;
        }
    }
}
$hashes = [];
foreach (['includes/shop_member_pricing.php', 'includes/shop_member_pricing_read.php', 'booking/eshop.php', 'booking/produkt.php',
    'tests/Support/MemberPricingReadBaseline.php', 'tests/Support/MemberPricingReadFixture.php', 'tests/Support/MemberPricingReadProfile.php'] as $file) {
    $hashes[$file] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $file);
}
echo json_encode(
    ['php' => PHP_VERSION, 'server' => $server, 'method' => '5 warmups, 30 interleaved plain PDO samples per phase, '
    . 'same process and fixtures; separate warmed instrumentation; complete strict data parity. Presentation prices only, not full HTTP.',
    'source_sha256' => $hashes, 'cases' => $cases, 'dropped_owned_schemas' => $cleanup],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
), PHP_EOL;
