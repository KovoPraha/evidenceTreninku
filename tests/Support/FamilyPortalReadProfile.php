<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/ProgramCapacityMigratedFixture.php';
require_once __DIR__ . '/FamilyPortalReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/family_portal.php';

$before = in_array('--before', $argv, true);
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Invalid isolated localhost port.');
}
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$admin = new PDO($dsn, 'root', '', $options);
$database = 'w71_profile_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$median = static function (array $values): float {
    sort($values, SORT_NUMERIC);
    return $values[intdiv(count($values), 2)];
};
$results = [];
try {
    $pdo = new PDO($dsn . ';dbname=' . $database, 'root', '', $options);
    Tests\Support\programCapacityMigratedSchema($pdo);
    // Checkout's minimal legacy bootstrap omits these existing legacy membership fields.
    $columns = $pdo->query('SHOW COLUMNS FROM sportovci')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['stav_clenstvi' => "VARCHAR(20) NOT NULL DEFAULT 'cekajici'", 'stav_manualni' => 'TINYINT NOT NULL DEFAULT 0',
        'stav_duvod' => 'TEXT NULL', 'stav_aktualizovan' => 'DATETIME NULL'] as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec('ALTER TABLE sportovci ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
    $pdo->exec("INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni) VALUES(7,'Synthetic','synthetic@example.test','not-a-password','admin',1)");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,aktivni,email_overeno,registrovan) VALUES(1,1,1,CURRENT_TIMESTAMP),(2,1,1,CURRENT_TIMESTAMP)");
    $person = $pdo->prepare("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni,stav_clenstvi,stav_manualni,stav_duvod) VALUES(?,'Synthetic',?,'2010-01-01','archiv',1,?)");
    $relation = $pdo->prepare("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(?,?,'guardian','approved','2020-01-01',7,'LOCALHOST NEPLATIT')");
    for ($i = 1; $i <= 120; $i++) {
        $person->execute([$i, sprintf('Person %03d', $i), str_repeat('Synthetic membership. ', 40)]);
        if ($i > 20) {
            $relation->execute([2, $i]);
        }
    }
    $profile = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $database, 'root', '');
    $profile->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $schema = [];
    foreach (['sportovci', 'verejni_uzivatele', 'account_person_roles'] as $table) {
        $schema[$table] = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    }
    $read = static fn (PDO $connection, string $mode, int $target): bool => $mode === 'baseline'
        ? familyReadBaselineCanViewPerson($connection, 1, $target) : familyPortalCanViewPerson($connection, 1, $target);
    foreach ([0, 1, 5, 20] as $size) {
        $pdo->exec('DELETE FROM account_person_roles WHERE account_id=1');
        for ($i = 1; $i <= $size; $i++) {
            $relation->execute([1, $i]);
        }
        foreach (array_keys($schema) as $table) {
            $pdo->query('ANALYZE TABLE ' . $table)->fetchAll();
        }
        if (familyReadBaselineAuthorizedPeople($pdo, 1) !== familyPortalAuthorizedPeople($pdo, 1)) {
            throw new RuntimeException('Family list changed.');
        }
        foreach ([1, 120] as $target) {
            $expected = $size > 0 && $target === 1;
            $samples = ['baseline' => [], 'current' => []];
            for ($iteration = -3; $iteration < 15; $iteration++) {
                foreach ($iteration % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                    memory_reset_peak_usage();
                    $memoryStart = memory_get_usage();
                    $start = hrtime(true);
                    $result = $read($pdo, $mode, $target);
                    $elapsed = (hrtime(true) - $start) / 1e6;
                    $peak = max(0, memory_get_peak_usage() - $memoryStart);
                    if ($result !== $expected) {
                        throw new RuntimeException('Authorization changed.');
                    }
                    if ($iteration >= 0) {
                        $samples[$mode][] = ['service_ms' => $elapsed, 'incremental_peak_bytes' => $peak];
                    }
                }
            }
            $queries = [];
            foreach (['baseline', 'current'] as $mode) {
                $profile->profile = [];
                $profile->profiling = true;
                if ($read($profile, $mode, $target) !== $expected) {
                    throw new RuntimeException('Instrumented authorization changed.');
                }
                $profile->profiling = false;
                $queries[$mode] = $profile->profile;
            }
            if (!$before && (count($queries['current']) !== 4 || array_sum(array_column($queries['current'], 'fetched_rows')) !== 3 + (int) $expected)) {
                throw new RuntimeException('Targeted authorization exceeded the query/row budget.');
            }
            $plans = [];
            foreach ($queries as $mode => $entries) {
                $entry = $entries[3];
                $q = $pdo->prepare('EXPLAIN ' . $entry['sql']);
                $q->execute($entry['parameters']);
                $plans[$mode] = $q->fetchAll();
            }
            $results[] = ['family_size' => $size, 'target' => $target === 1 ? 'own' : 'foreign', 'allowed' => $expected,
                'samples' => $samples, 'median_ms' => array_map(static fn (array $rows): float => $median(array_column($rows, 'service_ms')), $samples),
                'median_incremental_peak_bytes' => array_map(static fn (array $rows): float => $median(array_column($rows, 'incremental_peak_bytes')), $samples),
                'queries' => $queries, 'explain' => $plans];
        }
    }
    // A separate committed connection revokes between calls; no cached account/role authority.
    $writer = new PDO($dsn . ';dbname=' . $database, 'root', '', $options);
    foreach (["UPDATE account_person_roles SET status='revoked' WHERE account_id=1 AND sportovec_id=1",
        'UPDATE verejni_uzivatele SET aktivni=0 WHERE id=1', 'UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=1'] as $change) {
        $writer->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=1 AND sportovec_id=1");
        $writer->exec('UPDATE verejni_uzivatele SET aktivni=1,email_overeno=1 WHERE id=1');
        if (!$read($pdo, 'baseline', 1) || !$read($pdo, 'current', 1)) {
            throw new RuntimeException('Missing initial authorization.');
        }
        $writer->beginTransaction();
        $writer->exec($change);
        $writer->commit();
        if ($read($pdo, 'baseline', 1) || $read($pdo, 'current', 1)) {
            throw new RuntimeException('Committed revocation was not observed.');
        }
    }
    echo json_encode(['synthetic_only' => true, 'phase' => $before ? 'before implementation' : 'enforced budget',
        'schema' => 'checkout legacy bootstrap plus all migrations and four legacy membership columns; FK enabled',
        'migration_count' => count(glob(dirname(__DIR__, 2) . '/migrations/*.php')), 'table_definitions' => $schema,
        'database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION), 'php_version' => PHP_VERSION,
        'measurement' => 'plain PDO; 3 warmups + 15 alternating samples; ANALYZE TABLE; separate SQL instrumentation',
        'revocation' => 'three committed changes from another connection observed by subsequent autocommit reads; no writer lock race claim',
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($writer) && $writer->inTransaction()) {
        $writer->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
}
