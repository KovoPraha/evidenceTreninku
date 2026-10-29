<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/ProgramCapacityFixture.php';

$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
    throw new RuntimeException('Invalid isolated localhost port.');
}
$dsn = 'mysql:host=127.0.0.1;port=' . $port . ';charset=utf8mb4';
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'w6_profile_' . bin2hex(random_bytes(8));
$admin->exec('CREATE DATABASE `' . $database . '`');
$results = [];
$median = static function (array $values): float {
    sort($values, SORT_NUMERIC);
    $n = count($values);
    return ($values[(int) floor(($n - 1) / 2)] + $values[(int) floor($n / 2)]) / 2;
};
try {
    $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $database, 'root', '');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    Tests\Support\programCapacityFixture($pdo);
    // Representative lookup indexes, deliberately not claimed to be the migrated production schema.
    foreach (['club_program_offers' => '(team_id,starts_on,ends_on)',
        'club_program_enrollments' => '(offer_id,status)', 'shop_order_items' => '(club_program_offer_id)'] as $table => $columns) {
        $pdo->exec('CREATE INDEX profile_lookup ON ' . $table . $columns);
    }
    $pdo->exec('CREATE INDEX profile_source ON club_program_enrollments(source_order_item_id,status)');
    $pdo->exec('CREATE INDEX profile_physical ON club_program_physical_teams(team_id,program_id)');
    $now = new DateTimeImmutable('2026-10-29 12:00:00 Europe/Prague');
    foreach ([['empty', 0, 1], ['small', 10, 1], ['dense', 1000, 1], ['fanout', 1000, 2]] as [$name, $size, $teams]) {
        foreach (['club_program_enrollments', 'shop_order_items', 'shop_orders', 'club_program_physical_teams'] as $table) {
            $pdo->exec('DELETE FROM ' . $table);
        }
        $pdo->beginTransaction();
        $enrollment = $pdo->prepare("INSERT INTO club_program_enrollments VALUES(?,?,?,'active',?)");
        for ($i = 1; $i <= $size; $i++) {
            $offerId = $i % 2 === 0 ? 2 : 4;
            $enrollment->execute([$i, $offerId, $i, 100000 + $i]);
            Tests\Support\programCapacityHold($pdo, $i, $offerId, 10000 + $i, null);
        }
        if ($teams === 2) {
            $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,10),(1,20)');
        }
        $pdo->commit();
        $offer = Tests\Support\programCapacityOffer($pdo);
        $samples = ['canonical' => [], 'context' => []];
        $details = [];
        $reference = null;
        // Alternate the existing canonical and operation-local context paths; neither is a new implementation.
        for ($iteration = -5; $iteration < 30; $iteration++) {
            foreach ($iteration % 2 === 0 ? ['canonical', 'context'] : ['context', 'canonical'] as $mode) {
                $pdo->profile = [];
                $pdo->profiling = true;
                memory_reset_peak_usage();
                $memoryStart = memory_get_usage();
                $start = hrtime(true);
                $context = $mode === 'context' ? new ClubProgramReadContext($pdo, $now) : null;
                $state = clubProgramOfferCapacityState($pdo, $offer, $now, false, 0, $context);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $peak = memory_get_peak_usage();
                $pdo->profiling = false;
                $reference ??= $state;
                if ($reference !== $state || $state['occupied_count'] !== $size) {
                    throw new RuntimeException('Profile semantic invariant failed.');
                }
                if ($iteration >= 0) {
                    $samples[$mode][] = ['service_ms' => $elapsed, 'sql_count' => count($pdo->profile),
                        'sql_execute_ms' => array_sum(array_column($pdo->profile, 'execute_ms')),
                        'sql_fetch_ms' => array_sum(array_column($pdo->profile, 'fetch_ms')),
                        'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                        'peak_bytes' => $peak, 'incremental_peak_bytes' => max(0, $peak - $memoryStart)];
                    $details[$mode] = $pdo->profile;
                }
                unset($context);
            }
        }
        $plans = [];
        foreach ($details['context'] as $entry) {
            if (str_starts_with($entry['sql'], 'SELECT e.sportovec_id') || str_starts_with($entry['sql'], 'SELECT oi.id')) {
                $q = $pdo->prepare('EXPLAIN ' . $entry['sql']);
                $q->execute($entry['parameters']);
                $plans[] = ['sql' => $entry['sql'], 'parameters' => $entry['parameters'], 'plan' => $q->fetchAll()];
            }
        }
        $results[] = ['scenario' => $name, 'enrollments' => $size, 'holds' => $size, 'physical_teams' => $teams,
            'state' => $reference, 'result_bytes' => strlen(json_encode($reference, JSON_THROW_ON_ERROR)),
            'samples' => $samples, 'last_query_profiles' => $details, 'explain_reduced_schema' => $plans,
            'median_ms' => array_map(static fn (array $rows): float => $median(array_column($rows, 'service_ms')), $samples)];
    }
    echo json_encode(['synthetic_only' => true, 'schema' => 'reduced-indexed-fixture; not production schema or load',
        'database_version' => $admin->getAttribute(PDO::ATTR_SERVER_VERSION), 'php_version' => PHP_VERSION,
        'warmups_per_mode' => 5, 'samples_per_mode' => 30, 'instrumentation' => 'successful execute/fetch only; fetched rows are not rows examined',
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $admin->exec('DROP DATABASE `' . $database . '`');
}
