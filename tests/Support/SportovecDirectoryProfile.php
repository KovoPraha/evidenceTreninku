<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/SportovecDirectoryBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/sportovec_directory_read.php';

$schema = 'w75_directory_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    \Tests\Support\programCapacityMigratedSchema($pdo);
    w72LegacyColumns($pdo, 'sportovci', [
        'email' => 'VARCHAR(255) NULL', 'uciid' => 'VARCHAR(80) NULL', 'category' => 'VARCHAR(80) NULL',
        'stav_clenstvi' => 'VARCHAR(20) NULL', 'stav_manualni' => 'INT NULL', 'stav_duvod' => 'TEXT NULL',
        'kis_aktivni' => 'INT NULL', 'kis_neuhrazeno' => 'DECIMAL(10,2) NULL', 'kis_last_seen_at' => 'DATETIME NULL',
    ]);
    $pdo->exec('ALTER TABLE sportovci MODIFY jmeno VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,'
        . ' MODIFY prijmeni VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS skupiny(id INT PRIMARY KEY,nazev VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE IF NOT EXISTS sportovec_skupina(sportovec_id INT,skupina_id INT,'
        . 'PRIMARY KEY(sportovec_id,skupina_id),KEY idx_skupina(skupina_id)) ENGINE=InnoDB');
    // Preserve the legacy index/FKs documented in database/local-demo-schema.sql.
    $pdo->exec('CREATE TABLE IF NOT EXISTS trenink_sportovec(trenink_id INT NOT NULL,sportovec_id INT NOT NULL,'
        . 'PRIMARY KEY(trenink_id,sportovec_id),KEY fk_ts_sportovci(sportovec_id),'
        . 'CONSTRAINT fk_ts_sportovci FOREIGN KEY(sportovec_id) REFERENCES sportovci(id) ON DELETE CASCADE,'
        . 'CONSTRAINT fk_ts_treninky FOREIGN KEY(trenink_id) REFERENCES treninky(id) ON DELETE CASCADE) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO treninky(id) VALUES(1),(2)');
    $pdo->exec("INSERT INTO skupiny VALUES(1,'Alpha'),(2,'Beta')");
    $person = $pdo->prepare('INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,email,uciid,category,stav_clenstvi,'
        . 'stav_manualni,stav_duvod,kis_aktivni,kis_neuhrazeno,kis_last_seen_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $group = $pdo->prepare('INSERT INTO sportovec_skupina VALUES(?,?)');
    $training = $pdo->prepare('INSERT INTO trenink_sportovec VALUES(?,?)');
    $inserted = 0;
    $results = [];
    $measurements = [];
    $before = in_array('--before', $argv, true);
    foreach ([0, 100, 1000, 10000] as $size) {
        $pdo->beginTransaction();
        for ($id = $inserted + 1; $id <= $size; $id++) {
            $person->execute([$id, 'Synthetic ' . ($id % 11), sprintf('Person %05d', $id),
                $id % 3 ? '2000-01-01' : null, 'person' . $id . '@example.test', (string) $id,
                $id % 5 ? 'Youth' : null, ['aktivni', 'cekajici', 'neaktivni', 'archiv', null][$id % 5],
                $id % 2, str_repeat('LOCALHOST membership. ', 50), $id % 3 ? 1 : null,
                $id % 4 ? $id : null, $id % 2 ? '2020-01-01' : date('Y-m-d')]);
            if ($id % 2) {
                $group->execute([$id, 1]);
            }
            if ($id % 7 === 0) {
                $group->execute([$id, 2]);
            }
            for ($i = 1; $i <= $id % 3; $i++) {
                $training->execute([$i, $id]);
            }
        }
        $pdo->commit();
        $inserted = $size;
        if ($size === 100) {
            $pdo->exec("UPDATE sportovci SET jmeno='Žaneta',prijmeni='Šťastná' WHERE id=1");
            $pdo->exec("UPDATE sportovci SET jmeno='ZANETA',prijmeni='STASTNA' WHERE id=2");
        }
        $cases = [['pp' => 50], ['pp' => 100, 'p' => 999], ['q' => 'missing'], ['pp' => 0]];
        if ($size === 100) {
            $cases[] = ['pp' => 500];
            $cases[] = ['pp' => 37, 'p' => -2, 'sort' => 'invalid', 'dir' => 'invalid'];
            foreach (['prijmeni', 'jmeno', 'narozeni', 'category', 'uciid', 'treninku', 'kis'] as $sort) {
                foreach (['asc', 'desc'] as $dir) {
                    $cases[] = compact('sort', 'dir') + ['pp' => 50, 'p' => 2];
                }
            }
            foreach (['aktivni', 'cekajici', 'neaktivni', 'archiv', 'manualni', 'invalid'] as $stav) {
                $cases[] = compact('stav') + ['q' => 'Person'];
            }
            foreach (['kis_aktivni', 'dluh', 'bez_skupiny', 'mimo_import', 'invalid'] as $kis) {
                $cases[] = compact('kis') + ['pp' => 50];
            }
            foreach (['%01', '_', '@example.test', 'Synthetic', 'Žaneta', 'stastna', "' OR 1=1 --"] as $q) {
                $cases[] = compact('q');
            }
        }
        foreach ($cases as $input) {
            // The existing page uses PDO's default emulated prepares and repeats :q.
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $pdo->profile = [];
            $pdo->profiling = true;
            $expected = w75DirectoryBaseline($pdo, $input);
            $baselineProfile = $pdo->profile;
            $pdo->profile = [];
            $pdo->profiling = true;
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $before || in_array('--legacy-prepares', $argv, true));
            $actual = $before ? w75DirectoryBaseline($pdo, $input) : sportovecDirectoryRead($pdo, $input);
            $pdo->profiling = false;
            if ($actual !== $expected) {
                throw new RuntimeException('Directory result changed: size=' . $size . ' input=' . json_encode($input));
            }
            $rows = array_sum(array_column($pdo->profile, 'fetched_rows'));
            $limit = $actual['perPage'] ?: $size;
            if (!$before && $rows > min($size, $limit) + 3) {
                throw new RuntimeException('Directory materialized too many rows: ' . $rows . ' for page ' . $limit);
            }
            if (!$before && count($pdo->profile) !== 4) {
                throw new RuntimeException('Directory query budget failed.');
            }
            $results[] = ['size' => $size, 'input' => $input, 'same_data' => true,
                'baseline_sql' => count($baselineProfile), 'baseline_rows' => array_sum(array_column($baselineProfile, 'fetched_rows')),
                'sql' => count($pdo->profile), 'fetched_rows' => $rows, 'returned_people' => count($actual['sportovciPage'])];
        }
        unset($actual, $expected);
        $pdo->profile = [];
        $plain = w72Connection($schema);
        $samples = ['baseline' => [], 'current' => []];
        $referenceHash = null;
        for ($iteration = -1; $iteration < 5; $iteration++) {
            foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                $plain->setAttribute(PDO::ATTR_EMULATE_PREPARES, $mode === 'baseline' || $before);
                memory_reset_peak_usage();
                $memory = memory_get_usage();
                $start = hrtime(true);
                $value = $mode === 'baseline' || $before ? w75DirectoryBaseline($plain, ['pp' => 100])
                    : sportovecDirectoryRead($plain, ['pp' => 100]);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $peak = memory_get_peak_usage() - $memory;
                $hash = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
                $referenceHash ??= $hash;
                if ($hash !== $referenceHash) {
                    throw new RuntimeException('Timed directory result changed.');
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
        $measurements[] = ['size' => $size, 'per_page' => 100, 'samples' => $samples,
            'median_ms' => array_map(static fn (array $rows): float => $median(array_column($rows, 'ms')), $samples),
            'median_peak_bytes' => array_map(static fn (array $rows): float =>
                $median(array_column($rows, 'incremental_peak_bytes')), $samples)];
        if (!$before && $size >= 1000) {
            $peaks = $measurements[array_key_last($measurements)]['median_peak_bytes'];
            if ($peaks['current'] > 2 * 1024 * 1024 || $peaks['current'] > $peaks['baseline'] / 2) {
                throw new RuntimeException('Directory memory budget failed.');
            }
        }
    }
    $pdo->profile = [];
    $pdo->profiling = true;
    sportovecDirectoryRead($pdo, ['pp' => 100]);
    $pdo->profiling = false;
    $plans = [];
    foreach ($pdo->profile as $query) {
        $explain = $pdo->prepare('EXPLAIN ' . $query['sql']);
        $explain->execute($query['parameters']);
        $plans[] = ['sql' => $query['sql'], 'fetched_rows' => $query['fetched_rows'], 'explain' => $explain->fetchAll()];
    }
    echo json_encode(
        ['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'migrated_schema' => true, 'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(),
        'production_mutations' => false, 'results' => $results, 'measurements' => $measurements, 'plans_at_10000' => $plans],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
