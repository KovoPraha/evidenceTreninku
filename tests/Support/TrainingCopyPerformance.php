<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    throw new RuntimeException('Owned synthetic CLI benchmark required.');
}
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/TrainingCopyFixture.php';
require_once dirname(__DIR__, 2) . '/includes/training_copy_read.php';
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
$results = [];
$median = static function (array $values): float {
    sort($values);
    return $values[intdiv(count($values), 2)];
};
foreach ([100, 1000, 10000] as $size) {
    $schema = 'p11_copy_' . bin2hex(random_bytes(8));
    $admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec('SET SESSION query_cache_type=OFF');
        trainingCopyFixture($pdo, $size);
        foreach (['tied', 'unique'] as $dateLayout) {
            if ($dateLayout === 'unique') {
                $pdo->exec("UPDATE treninky SET datum=DATE_SUB(CURRENT_DATE, INTERVAL id DAY)");
            }
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $checksum = static function () use ($pdo, $tables): array {
                $values = [];
                foreach ($tables as $table) {
                    $values[$table] = $pdo->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
                }
                return $values;
            };
            $beforeChecksum = $checksum();
            $pdo->exec('START TRANSACTION READ ONLY');
            foreach ([
                'all' => ['1=1', [], null, false, 'all'],
                'root' => ['1=1', [], 1, false, 'all'],
                'child' => ['1=1', [], 2, false, 'all'],
                'group' => ['1=1', [1], null, true, 'all'],
                'group-root' => ['1=1', [1], 1, true, 'all'],
                'zero-team' => ['1=1', [], 0, false, 'all'],
                'empty-team' => ['1=1', [], 999, false, 'all'],
                'empty-group' => ['1=1', [999], null, true, 'all'],
                'recent' => ['1=1', [], null, false, '30'],
                'coach-scope' => ['EXISTS (SELECT 1 FROM trenink_trener tt WHERE tt.trenink_id=t.id AND tt.trener_id=?)', [1], 1, false, 'all'],
            ] as $name => [$scope, $params, $team, $group, $period]) {
                $sql = trainingCopyFixtureSql($scope, $group, $period);
                $expected = trainingCopyBaseline($pdo, $sql, $params, $team);
                $samples = ['before' => [], 'after' => []];
                $peaks = ['before' => [], 'after' => []];
                for ($round = -4; $round < 17; $round++) {
                    foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
                        memory_reset_peak_usage();
                        $memory = memory_get_usage();
                        $start = hrtime(true);
                        $rows = $variant === 'before' ? trainingCopyBaseline($pdo, $sql, $params, $team)
                            : TrainingCopyRead::rows($pdo, $sql, $params, $team, $group);
                        $ms = (hrtime(true) - $start) / 1e6;
                        $peak = memory_get_peak_usage() - $memory;
                        if ($rows !== $expected) {
                            throw new RuntimeException('Copy-list parity failed: ' . $size . '/' . $name . '/' . $variant
                                . ' expected=' . json_encode(array_column($expected, 'id'))
                                . ' actual=' . json_encode(array_column($rows, 'id')));
                        }
                        if ($round >= 0) {
                            $samples[$variant][] = $ms;
                            $peaks[$variant][] = $peak;
                        }
                        unset($rows);
                    }
                }
                if ($dateLayout === 'unique' && $size >= 1000 && $name === 'all'
                    && $median($samples['after']) >= $median($samples['before'])
                ) {
                    throw new RuntimeException('Bounded copy-list aggregation did not improve the full-history case.');
                }
                $results[] = ['size' => $size, 'date_layout' => $dateLayout, 'case' => $name, 'rows' => count($expected), 'parity' => true,
                    'samples_ms' => $samples, 'median_ms' => array_map($median, $samples),
                    'median_peak_bytes' => array_map($median, $peaks)];
                unset($expected);
            }
            $pdo->rollBack();
            if ($checksum() !== $beforeChecksum) {
                throw new RuntimeException('Read-only copy-list changed fixture data.');
            }
        }
    } finally {
        $pdo = null;
        $admin->exec('DROP DATABASE `' . $schema . '`');
    }
}
echo json_encode(['complete' => true, 'data_checksums_unchanged' => true,
    'server' => $identity['version'], 'cases' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
