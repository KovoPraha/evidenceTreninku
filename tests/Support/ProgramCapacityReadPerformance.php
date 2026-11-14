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
require_once __DIR__ . '/ProgramAdminReadFixture.php';
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/ProgramCapacityReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/club_program_admin_read.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$admin->exec('SET GLOBAL query_cache_type=OFF');
$dsn = 'mysql:host=127.0.0.1;port=' . $runtime['port'] . ';dbname=' . $runtime['schema'] . ';charset=utf8mb4';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$profile = new Tests\Support\QueryProfilePdo($dsn, 'root', '');
$profile->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->exec('SET SESSION query_cache_type=OFF');
$profile->exec('SET SESSION query_cache_type=OFF');
$checksum = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW FULL TABLES WHERE Table_type="BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
        if ($result[$table] === null) {
            throw new RuntimeException('Unavailable table checksum.');
        }
    }
    return $result;
};
$initial = $checksum();
$median = static function (array $values): float {
    sort($values);
    return $values[intdiv(count($values), 2)];
};
$results = [];
$saved = [];
foreach (['club_programs', 'club_program_offers'] as $table) {
    $saved[$table] = $admin->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
}
$empty = false;
$emptyFixture = static function (bool $enable) use ($admin, $saved, &$empty): void {
    $admin->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        if ($enable) {
            $admin->exec('DELETE FROM club_program_offers; DELETE FROM club_programs');
        } else {
            foreach ($saved as $table => $rows) {
                foreach ($rows as $row) {
                    $admin->prepare('INSERT INTO ' . $table . '(`' . implode('`,`', array_keys($row)) . '`) VALUES('
                        . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
                }
            }
        }
        $empty = $enable;
    } finally {
        $admin->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
try {
    foreach ([0, 2, 12, 100, 401] as $size) {
        if ($size === 0) {
            $emptyFixture(true);
        } else {
            if ($empty) {
                $emptyFixture(false);
            }
            programAdminReadPopulate($admin, $size);
        }
        $before = $checksum();
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $expected = clubProgramAdminOffers($pdo);
            if (count($expected) !== $size) {
                throw new RuntimeException('Unexpected fixture size.');
            }
            $samples = $peaks = ['before' => [], 'after' => []];
            for ($round = -4; $round < 17; $round++) {
                foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
                    memory_reset_peak_usage();
                    $memory = memory_get_usage();
                    $start = hrtime(true);
                    $rows = $variant === 'before' ? programCapacityReadBaseline($pdo) : clubProgramAdminOffersRead($pdo);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    $peak = memory_get_peak_usage() - $memory;
                    if ($rows !== $expected) {
                        throw new RuntimeException('Strict native row parity: ' . $size . '/' . $variant);
                    }
                    if ($round >= 0) {
                        $samples[$variant][] = $elapsed;
                        $peaks[$variant][] = $peak;
                    }
                    unset($rows);
                }
            }
        } finally {
            $pdo->rollBack();
        }
        $instrumented = [];
        foreach (['before', 'after'] as $variant) {
            $profile->profile = [];
            $profile->profiling = true;
            $rows = $variant === 'before' ? programCapacityReadBaseline($profile) : clubProgramAdminOffersRead($profile);
            $profile->profiling = false;
            if ($rows !== $expected) {
                throw new RuntimeException('Instrumentation changed results.');
            }
            $instrumented[$variant] = ['queries' => count($profile->profile),
                'fetched_rows' => array_sum(array_column($profile->profile, 'fetched_rows')), 'trace' => $profile->profile];
        }
        if ($size >= 100 && ($median($samples['after']) >= $median($samples['before'])
            || $instrumented['after']['queries'] >= $instrumented['before']['queries'] / 3)) {
            throw new RuntimeException('No material native benefit.');
        }
        if ($before !== $checksum()) {
            throw new RuntimeException('Read-only native requests changed data.');
        }
        $results[] = ['offers' => $size, 'strict_parity' => true, 'samples_ms' => $samples,
            'median_ms' => array_map($median, $samples), 'median_peak_bytes' => array_map($median, $peaks),
            'instrumented' => $instrumented];
        unset($expected, $rows);
    }
} finally {
    if ($empty) {
        $emptyFixture(false);
    }
    programAdminReadRemoveAdditional($admin);
    if ($initial !== $checksum()) {
        throw new RuntimeException('Owned fixture restoration failed.');
    }
}
file_put_contents($output . '/native.json', json_encode(['server' => $identity['version'],
    'query_cache_off' => true, 'uninstrumented_timing' => true, 'checksums_unchanged' => true,
    'fixture_restored' => true, 'cases' => $results], JSON_THROW_ON_ERROR));
