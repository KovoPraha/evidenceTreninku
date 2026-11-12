<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    throw new RuntimeException('Owned synthetic CLI benchmark required.');
}
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/AdminDashboardFixture.php';
require_once dirname(__DIR__, 2) . '/includes/admin_dashboard_read.php';

$port = (string)getenv('EVIDENCE_MARIADB_SMOKE_PORT');
$datadir = (string)getenv('EVIDENCE_PERFORMANCE_DATADIR');
if (!ctype_digit($port) || $datadir === '') {
    throw new RuntimeException('Explicit localhost port and datadir required.');
}
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
if ((int)$identity['port'] !== (int)$port
    || rtrim(str_replace('\\', '/', $identity['directory']), '/') !== rtrim(str_replace('\\', '/', $datadir), '/')
) {
    throw new RuntimeException('Wrong owned database server.');
}
$median = static function (array $values): float {
    sort($values);
    return $values[intdiv(count($values), 2)];
};
$results = [];
foreach ([0, 100, 10000, 100000] as $size) {
    foreach (['mixed', 'grouped', 'ungrouped'] as $layout) {
        $schema = 'p11_adm_' . bin2hex(random_bytes(8));
        $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->exec("SET SESSION query_cache_type=OFF; SET time_zone='+00:00'; SET timestamp=1794484800");
            adminDashboardSeed($pdo, $size, $layout);
            $checksum = static function () use ($pdo): array {
                return $pdo->query('CHECKSUM TABLE sportovci,sportovec_skupina EXTENDED')->fetchAll(PDO::FETCH_ASSOC);
            };
            $beforeChecksum = $checksum();
            $pdo->exec('START TRANSACTION READ ONLY');
            $expected = adminDashboardBaseline($pdo);
            $known = [
                'without_group' => $layout === 'grouped' ? 0 : ($layout === 'mixed' ? $size - intdiv($size, 2) : $size),
                'debt' => intdiv($size, 5) * 2,
                'stale' => intdiv($size, 6) + intdiv($size + 5, 6),
                'manual_active' => intdiv($size, 20) * 2,
            ];
            if ($expected !== $known) {
                throw new RuntimeException('Fixture boundary conditions are incorrect.');
            }
            $samples = ['before' => [], 'after' => []];
            $peaks = ['before' => [], 'after' => []];
            for ($round = -4; $round < 17; $round++) {
                foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
                    memory_reset_peak_usage();
                    $memory = memory_get_usage();
                    $start = hrtime(true);
                    $counts = $variant === 'before' ? adminDashboardBaseline($pdo) : adminDashboardMemberCounts($pdo);
                    $ms = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage() - $memory;
                    if ($counts !== $expected) {
                        throw new RuntimeException('Dashboard counts differ: ' . $size . '/' . $layout . '/' . $variant);
                    }
                    if ($round >= 0) {
                        $samples[$variant][] = $ms;
                        $peaks[$variant][] = $peak;
                    }
                }
            }
            $diagnostics = [];
            foreach (['before', 'after'] as $variant) {
                $pdo->profile = [];
                $pdo->profiling = true;
                $variant === 'before' ? adminDashboardBaseline($pdo) : adminDashboardMemberCounts($pdo);
                $pdo->profiling = false;
                $diagnostics[$variant] = ['sql' => count($pdo->profile),
                    'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))];
            }
            if ($diagnostics['before']['sql'] !== 4 || $diagnostics['after']['sql'] !== 2) {
                throw new RuntimeException('Unexpected dashboard query budget.');
            }
            $pdo->rollBack();
            if ($checksum() !== $beforeChecksum) {
                throw new RuntimeException('Read-only dashboard changed fixture data.');
            }
            $results[] = ['size' => $size, 'layout' => $layout, 'counts' => $expected, 'parity' => true,
                'samples_ms' => $samples, 'median_ms' => array_map($median, $samples),
                'median_peak_bytes' => array_map($median, $peaks), 'diagnostics' => $diagnostics];
        } finally {
            $pdo = null;
            $admin->exec('DROP DATABASE `' . $schema . '`');
        }
    }
}
echo json_encode(['complete' => true, 'data_checksums_unchanged' => true,
    'server' => $identity['version'], 'cases' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
