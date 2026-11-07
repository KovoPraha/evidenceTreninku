<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('APP_BASE_URL', 'http://localhost');
require_once __DIR__ . '/CatalogPageFixture.php';

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

function catalogPageMeasure(PDO $pdo, Tests\Support\QueryProfilePdo $instrument, ?int $account, array $input): array
{
    $calls = ['before' => 'beforeShopCatalogPage', 'after' => 'shopCatalogPageRead'];
    $expected = beforeShopCatalogPage($pdo, $account, $input);
    if ($expected !== shopCatalogPageRead($pdo, $account, $input)) {
        throw new RuntimeException('Complete catalog page state differs.');
    }
    $hash = hash('sha256', serialize($expected));
    unset($expected);
    $output = [];
    foreach ($calls as $mode => $call) {
        $output[$mode] = ['samples_ms' => [], 'peak_increment_bytes' => 0, 'output_sha256' => $hash];
        for ($i = 0; $i < 5; $i++) {
            $call($pdo, $account, $input);
        }
    }
    for ($i = 0; $i < 30; $i++) {
        foreach ($i % 2 === 0 ? $calls : array_reverse($calls, true) as $mode => $call) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            $result = $call($pdo, $account, $input);
            $output[$mode]['samples_ms'][] = (hrtime(true) - $start) / 1e6;
            $output[$mode]['peak_increment_bytes'] = max($output[$mode]['peak_increment_bytes'], memory_get_peak_usage() - $memory);
            if (hash('sha256', serialize($result)) !== $hash) {
                throw new RuntimeException('Page changed during paired samples.');
            }
            unset($result);
        }
    }
    foreach ($calls as $mode => $call) {
        $call($instrument, $account, $input);
        $instrument->profile = [];
        $instrument->profiling = true;
        $result = $call($instrument, $account, $input);
        $instrument->profiling = false;
        if (hash('sha256', serialize($result)) !== $hash) {
            throw new RuntimeException('Instrumented state differs.');
        }
        $groups = [];
        $fullRows = $imageRows = 0;
        foreach ($instrument->profile as $q) {
            $key = preg_replace('/\s+/', ' ', trim($q['sql']));
            if (!isset($groups[$key])) {
                $groups[$key] = ['count' => 0, 'rows' => 0, 'parameter_count' => count($q['parameters'])];
                if ($instrument->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                    $statement = $instrument->prepare('EXPLAIN ' . $q['sql']);
                    $statement->execute($q['parameters']);
                    $groups[$key]['explain'] = $statement->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            $groups[$key]['count']++;
            $groups[$key]['rows'] += $q['fetched_rows'];
            if (str_contains($q['sql'], 'v.attributes_json')) {
                $fullRows += $q['fetched_rows'];
            }
            if (str_contains($q['sql'], 'FROM shop_product_images')) {
                $imageRows += $q['fetched_rows'];
            }
        }
        $times = $output[$mode]['samples_ms'];
        sort($times);
        $output[$mode] += ['sql' => count($instrument->profile),
            'fetched_rows' => array_sum(array_column($instrument->profile, 'fetched_rows')),
            'full_variant_rows' => $fullRows, 'image_rows' => $imageRows,
            'queries' => $groups, 'median_ms' => ($times[14] + $times[15]) / 2, 'p95_ms' => $times[28]];
        if ($mode === 'after' && ($fullRows > 12 || $imageRows > 36)) {
            throw new RuntimeException('Full presentation exceeded selected single-variant page.');
        }
    }
    return $output + ['parity' => true, 'speedup' => $output['before']['median_ms'] / $output['after']['median_ms']];
}

$cases = [];
$cleanup = [];
foreach ([12, 1000, 5000, 15] as $size) {
    $schema = 'catalog_page_' . bin2hex(random_bytes(8));
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
        $source = catalogPageFixture($size, $size === 15);
        catalogPageCopyFixture($source, $pdo);
        if (!$mysql) {
            catalogPageCopyFixture($source, $instrument);
        }
        $source = null;
        foreach ($size === 5000 ? [[null, []], [1, ['razeni' => 'price', 'strana' => 2]],
            [null, ['q' => 'suffix_%', 'strana' => 400]], [null, ['kategorie' => 'missing']]]
            : [[null, []]] as $case => [$account, $input]) {
            $cases[$size . '_' . $case] = catalogPageMeasure($pdo, $instrument, $account, $input);
        }
        if ($size === 15) {
            foreach (["UPDATE club_program_offers SET capacity=0 WHERE id=1",
                "UPDATE club_event_term_versions SET status='withdrawn' WHERE scope_key='program:2'",
                "UPDATE account_person_roles SET status='revoked'"] as $sql) {
                $pdo->exec($sql);
                if (beforeShopCatalogPage($pdo, 1, ['razeni' => 'price']) !== shopCatalogPageRead($pdo, 1, ['razeni' => 'price'])) {
                    throw new RuntimeException('Fresh program/eligibility state differs.');
                }
            }
            $cases['fresh_program_changes'] = 'PASS';
        }
    } finally {
        $pdo = $instrument = null;
        if ($mysql) {
            $admin->exec("DROP DATABASE IF EXISTS `$schema`");
            $cleanup[] = $schema;
        }
    }
}
$hashes = [];
foreach (['booking/eshop.php', 'includes/shop_catalog_page_read.php', 'includes/shop_storefront_read.php',
    'includes/shop_storefront.php', 'includes/shop_catalog_cards.php', 'includes/shop_public_navigation.php',
    'tests/Support/CatalogPageBaseline.php', 'tests/Support/CatalogPageFixture.php', 'tests/Support/CatalogPageProfile.php'] as $file) {
    $hashes[$file] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $file);
}
echo json_encode(['php' => PHP_VERSION, 'server' => $server,
    'method' => '5 warmups, 30 interleaved plain PDO samples, strict full page state parity; separate warmed SQL/EXPLAIN. Synthetic contract fixtures; not full HTTP or production latency.',
    'source_sha256' => $hashes, 'cases' => $cases, 'dropped_owned_schemas' => $cleanup], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
