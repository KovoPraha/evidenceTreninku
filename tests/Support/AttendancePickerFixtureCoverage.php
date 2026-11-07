<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/training_closure_people.php';
[$runtime, $pdo] = fullHttpEnvironment($output);
$pdo->exec('USE `' . $runtime['schema'] . '`');
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$report = ['complete' => false, 'synthetic_only' => true, 'method' => 'direct helper-level PDO coverage on the measured fixture; HTTP authentication verified separately',
    'read_only_session' => true, 'cases' => []];
foreach (['coach', 'sports_lead'] as $role) {
    $_SESSION = ['trener_id' => $credentials[$role]['id'], 'staff_positions' => [$role], 'staff_active_position' => $role];
    $expected = $pdo->query('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE ' . coachTrainingPersonPredicate($pdo)
        . ' ORDER BY CASE WHEN (' . coachTrainingPreferredPersonPredicate($pdo) . ') THEN 0 ELSE 1 END,s.prijmeni,s.jmeno,s.id')->fetchAll(PDO::FETCH_ASSOC);
    $actual = [];
    for ($page = 1; $page < 1000; $page++) {
        $result = trainingClosurePersonPage($pdo, ['page' => (string)$page]);
        if (count($result['rows']) > 50) {
            throw new RuntimeException('Unbounded selection page.');
        }
        array_push($actual, ...$result['rows']);
        if (!$result['has_more']) {
            break;
        }
    }
    if (count($expected) !== 10221 || $expected !== $actual || count(array_unique(array_column($actual, 'id'))) !== 10221) {
        throw new RuntimeException('Measured fixture lost/repeated a person or preference.');
    }
    $report['cases'][] = ['role' => $role, 'people' => count($actual), 'pages' => $page,
        'every_person_reachable_once' => true, 'preference_and_stable_name_id_order_equal' => true,
        'data_sha256' => hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR))];
}
$report['complete'] = true;
file_put_contents($output . '/full-fixture-person-coverage.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "All 10221 people reachable once across 205 pages for both roles.\n";
