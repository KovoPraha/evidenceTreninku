<?php
declare(strict_types=1);

/**
 * Localhost/CI-only MariaDB smoke test for the M3.5d sports import review.
 *
 * SQLite fixtures (tests/Integration/SportsImportReviewTest.php) cannot
 * represent every real MariaDB quirk. M3.5d's live browser pass found one the
 * hard way: legacy `zavod_sportovec` has no surrogate primary key, and a
 * query referencing `zs.id` only failed once it ran against real MySQL/MariaDB.
 * This script rebuilds a minimal but real MariaDB schema on an isolated test
 * database — including that missing-PK table — seeds synthetic LOCALHOST
 * data, runs sportsImportReview() and sportsDataQualityInventory() against it
 * and asserts key counts plus the absence of sportovec_id/name leakage.
 *
 * It never connects to the `evidence` database or any production host: the
 * target database name is restricted to a fixed test pattern and the host is
 * hardcoded to 127.0.0.1, matching tests/Support/*MariaDbSmoke.php.
 */

$database = getenv('EVIDENCE_SPORTS_REVIEW_SMOKE_DB') ?: 'evidence_sports_review_smoke_test';
if (preg_match('/\Aevidence_sports_review_smoke_test_[a-z0-9_]+\z/', $database) !== 1
    && $database !== 'evidence_sports_review_smoke_test'
) {
    throw new RuntimeException('Refusing to use a non-test MariaDB database name.');
}

$server = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Pre-contract shape per docs/databazove-schema.md. zavod_sportovec has no
    // surrogate primary key on purpose — the real production table does not
    // either, and that is exactly what a SQLite fixture cannot reproduce.
    $pdo->exec('CREATE TABLE treninky(id INT NOT NULL PRIMARY KEY,datum DATE NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavody(id INT NOT NULL PRIMARY KEY,datum DATE NOT NULL,kategorie VARCHAR(20) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE mereni_zaznamy(id INT NOT NULL PRIMARY KEY,typ VARCHAR(20) NOT NULL,sportovec_id INT NULL,vzdalenost DECIMAL(10,2) NULL,cas VARCHAR(50) NULL,rpe VARCHAR(50) NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE trenink_mereni(trenink_id INT NOT NULL,mereni_id INT NOT NULL,poradi INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavod_mereni(zavod_id INT NOT NULL,mereni_id INT NOT NULL,poradi INT NOT NULL DEFAULT 0,PRIMARY KEY(zavod_id,mereni_id)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INT NOT NULL,sportovec_id INT NULL,poradi INT NULL,cas VARCHAR(50) NULL,body DECIMAL(10,2) NULL,jmeno_ext VARCHAR(200) NULL,klub VARCHAR(200) NULL,kategorie_start VARCHAR(100) NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE mereni(id INT NOT NULL PRIMARY KEY,trenink_id INT NULL,sportovec_id INT NULL,vzdalenost VARCHAR(100) NULL,cas VARCHAR(50) NULL,poznamka TEXT NULL) ENGINE=InnoDB');

    $pdo->exec("INSERT INTO treninky VALUES(1,'2026-08-01'),(2,'2026-08-02')");
    $pdo->exec("INSERT INTO zavody VALUES(1,'2026-07-15','silnice')");

    // Legacy rows inserted before the v1 contract columns exist, mirroring
    // real history: nothing here is backfilled or guessed. sportovec_id stays
    // NULL throughout this fixture — no synthetic person is created at all.
    $pdo->exec(
        "INSERT INTO mereni_zaznamy(id,typ,sportovec_id,vzdalenost,cas,rpe) VALUES"
        . "(2,'kolo',NULL,10.00,'30:00',NULL),"
        . "(3,'posilovna',NULL,NULL,'cca 2 min','LOCALHOST'),"
        . "(4,'beh',NULL,NULL,'05:30.250',NULL),"
        . "(5,'kolo',NULL,NULL,NULL,NULL)"
    );
    $pdo->exec('INSERT INTO trenink_mereni VALUES(1,2,1)');
    $pdo->exec(
        "INSERT INTO zavod_sportovec(zavod_id,sportovec_id,poradi,cas,body,jmeno_ext) VALUES"
        . "(1,NULL,NULL,'1h02m',NULL,'LOCALHOST externí závodník'),"
        . "(1,NULL,NULL,NULL,NULL,NULL)"
    );
    $pdo->exec(
        "INSERT INTO mereni(id,trenink_id,sportovec_id,vzdalenost,cas,poznamka) VALUES"
        . "(1,1,NULL,'10 km','30 min','LOCALHOST sports-review-smoke'),"
        . "(2,2,NULL,'500 m','01:45.500','LOCALHOST sports-review-smoke'),"
        . "(3,1,NULL,'200m','30s','LOCALHOST sports-review-smoke'),"
        . "(4,2,NULL,'10-15 km','45:00','LOCALHOST sports-review-smoke'),"
        . "(5,1,NULL,'cca 10 km',NULL,'LOCALHOST sports-review-smoke'),"
        . "(6,2,NULL,'10 km, 5 km','14.52','LOCALHOST sports-review-smoke'),"
        . "(7,1,NULL,NULL,NULL,'LOCALHOST sports-review-smoke')"
    );

    $migration = require dirname(__DIR__) . '/migrations/20260805050000_sports_measurement_contract.php';
    $migration['up']($pdo);
    if (!$migration['verify']($pdo)) {
        throw new RuntimeException('MariaDB sports measurement contract migration verification failed.');
    }

    // v1 rows created only after the contract migration ran, as in real use —
    // the migration itself never backfills the four legacy rows above.
    $pdo->exec(
        "INSERT INTO mereni_zaznamy(id,typ,sportovec_id,contract_version,distance_unit,distance_meters,duration_ms,rpe_value) VALUES"
        . "(1,'kolo',NULL,'sports-measurement-v1','km',10000.00,1800000,NULL)"
    );
    $pdo->exec(
        "INSERT INTO zavod_sportovec(zavod_id,sportovec_id,poradi,cas,result_contract_version,result_status,result_time_ms) VALUES"
        . "(1,NULL,1,'30:00','sports-measurement-v1','finished',1800000)"
    );

    require_once dirname(__DIR__) . '/includes/sports_import_review.php';

    $now = new DateTimeImmutable('2026-08-05 12:00:00');
    $review = sportsImportReview($pdo, 200, $now);
    $inventory = sportsDataQualityInventory($pdo, $now);

    $failures = [];
    $assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures): void {
        if ($expected !== $actual) {
            $failures[] = sprintf('%s: expected %s, got %s', $label, var_export($expected, true), var_export($actual, true));
        }
    };

    $assertSame(true, $review['available'], 'review.available');
    $assertSame(5, $review['measurements']['total'], 'measurements.total');
    $assertSame(1, $review['measurements']['v1_total'], 'measurements.v1_total');
    $assertSame(['m' => 0, 'km' => 1], $review['measurements']['v1_units'], 'measurements.v1_units');
    $assertSame(1, $review['measurements']['v1_with_duration'], 'measurements.v1_with_duration');
    $assertSame(0, $review['measurements']['v1_with_rpe'], 'measurements.v1_with_rpe');
    $assertSame(3, $review['measurements']['legacy_with_values'], 'measurements.legacy_with_values');
    $assertSame(1, $review['measurements']['legacy_without_values'], 'measurements.legacy_without_values');
    $assertSame(1, $review['measurements']['convertible_count'], 'measurements.convertible_count');
    $assertSame(2, $review['measurements']['ambiguous_count'], 'measurements.ambiguous_count');
    $assertSame(false, $review['measurements']['truncated'], 'measurements.truncated');
    $assertSame([2, 3], array_column($review['measurements']['ambiguous_rows'], 'id'), 'measurements.ambiguous_rows ids');

    $assertSame(true, $review['races']['available'], 'races.available');
    $assertSame(3, $review['races']['total'], 'races.total');
    $assertSame(1, $review['races']['v1_total'], 'races.v1_total');
    $assertSame(1, $review['races']['v1_statuses']['finished'], 'races.v1_statuses.finished');
    $assertSame(1, $review['races']['legacy_with_result'], 'races.legacy_with_result');
    $assertSame(1, $review['races']['legacy_without_result'], 'races.legacy_without_result');
    $assertSame(2, $review['races']['ambiguous_count'], 'races.ambiguous_count');
    $assertSame(1, $review['races']['ambiguous_rows'][0]['zavod_id'] ?? null, 'races.ambiguous_rows[0].zavod_id');

    $assertSame(true, $review['legacy_text_table']['available'], 'legacy_text_table.available');
    $assertSame(7, $review['legacy_text_table']['total'], 'legacy_text_table.total');
    $assertSame(2, $review['legacy_text_table']['recognized_count'], 'legacy_text_table.recognized_count');
    $assertSame(5, $review['legacy_text_table']['ambiguous_count'], 'legacy_text_table.ambiguous_count');
    $assertSame(false, $review['legacy_text_table']['truncated'], 'legacy_text_table.truncated');

    $assertSame(8, $inventory['total_records'], 'inventory.total_records');
    $assertSame(8, $inventory['finding_count'], 'inventory.finding_count');
    $assertSame(5, $inventory['sources']['structured_measurements']['record_count'], 'inventory.structured_measurements.record_count');
    $assertSame(true, $inventory['sources']['structured_measurements']['available'], 'inventory.structured_measurements.available');
    $assertSame(3, $inventory['sources']['race_results']['record_count'], 'inventory.race_results.record_count');
    $assertSame(true, $inventory['sources']['race_results']['available'], 'inventory.race_results.available');
    $assertSame(3, count($inventory['unavailable']), 'inventory.unavailable count');

    $encodedReview = json_encode($review, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $encodedInventory = json_encode($inventory, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    foreach (['sportovec_id', '"jmeno"', '"prijmeni"'] as $forbidden) {
        if (str_contains($encodedReview, $forbidden)) {
            $failures[] = "review output leaked forbidden field: $forbidden";
        }
        if (str_contains($encodedInventory, $forbidden)) {
            $failures[] = "inventory output leaked forbidden field: $forbidden";
        }
    }

    if ($failures !== []) {
        throw new RuntimeException("MariaDB sports review smoke failed:\n - " . implode("\n - ", $failures));
    }

    echo "MariaDB sports review smoke OK — "
        . $review['measurements']['total'] . ' measurements (' . $review['measurements']['v1_total'] . " v1), "
        . $review['races']['total'] . ' race results (' . $review['races']['v1_total'] . " v1), "
        . $review['legacy_text_table']['total'] . ' legacy text rows ('
        . $review['legacy_text_table']['recognized_count'] . ' recognized/'
        . $review['legacy_text_table']['ambiguous_count'] . " ambiguous), "
        . $inventory['total_records'] . ' inventory records, '
        . $inventory['finding_count'] . " findings\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
