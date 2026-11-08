<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$variant = $argv[2] ?? '';
if ($output === false || !in_array($variant, ['before', 'after', 'prepare', 'expand'], true)) {
    throw new RuntimeException('Owned output and before/after/prepare/expand required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$identity['query_cache_type'] = (string) $admin->query('SELECT @@global.query_cache_type')->fetchColumn();
if (!in_array(strtoupper($identity['query_cache_type']), ['OFF', '0'], true)) {
    throw new RuntimeException('Owned HTTP server must have SQL result caching disabled.');
}
if ($variant === 'expand') {
    $admin->exec('USE `' . $runtime['schema'] . '`');
    if ((int) $admin->query('SELECT COUNT(*) FROM medical_clearance_watches')->fetchColumn() !== 10000
        || (int) $admin->query('SELECT MAX(id) FROM sportovci')->fetchColumn() !== 20000) {
        throw new RuntimeException('Expected exactly the owned 10k fixture before expansion.');
    }
    $policy = $admin->query('SELECT policy_id FROM medical_clearance_watches ORDER BY id LIMIT 1')->fetchColumn();
    $person = $admin->prepare("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(?,'Synthetic medical','Synthetic','2017-01-01')");
    $watch = $admin->prepare("INSERT INTO medical_clearance_watches(sportovec_id,purpose,policy_id,next_review_on,created_by) VALUES(?,'synthetic-purpose',?,CURRENT_DATE,7)");
    $record = $admin->prepare("INSERT INTO medical_clearance_records(watch_id,issued_on,valid_until,original_location,state,revision,recorded_by,verified_by,verified_at) "
        . "VALUES(?,DATE_SUB(CURRENT_DATE,INTERVAL 30 DAY),DATE_ADD(CURRENT_DATE,INTERVAL 30 DAY),'Synthetic fixture, no document',?,1,7,"
        . "IF(?='pending',NULL,7),IF(?='pending',NULL,CURRENT_TIMESTAMP))");
    $admin->beginTransaction();
    for ($id = 20001; $id <= 110000; $id++) {
        $person->execute([$id]);
        $watch->execute([$id, $policy]);
        $watchId = (int) $admin->lastInsertId();
        foreach (['superseded', 'pending', 'verified'] as $state) {
            $record->execute([$watchId, $state, $state, $state]);
        }
    }
    $admin->commit();
    $admin->exec('UPDATE medical_clearance_watches SET next_review_on=DATE_ADD(CURRENT_DATE,INTERVAL MOD(id,4) DAY)');
    $admin->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
    exit;
}
if ($variant === 'prepare') {
    $admin->exec('USE `' . $runtime['schema'] . '`');
    $ids = $admin->query('SELECT id FROM medical_clearance_watches ORDER BY id LIMIT 50')->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) !== 50) {
        throw new RuntimeException('Expected the owned large medical fixture.');
    }
    // Calibrate every visible state once, before either timed variant; no private documents.
    $admin->beginTransaction();
    $update = $admin->prepare('UPDATE medical_clearance_records SET state=? WHERE watch_id=?');
    foreach (array_slice($ids, 0, 5) as $id) {
        $update->execute(['rejected', $id]);
    }
    foreach (array_slice($ids, 5, 5) as $id) {
        $update->execute(['pending', $id]);
    }
    foreach (array_slice($ids, 10, 5) as $id) {
        $admin->prepare("UPDATE medical_clearance_records SET issued_on=DATE_ADD(CURRENT_DATE,INTERVAL 1 DAY),
            state='verified' WHERE id=(SELECT current_id FROM (SELECT MAX(id) current_id FROM medical_clearance_records WHERE watch_id=?) x)")
            ->execute([$id]);
    }
    foreach (array_slice($ids, 15, 5) as $id) {
        $admin->prepare("UPDATE medical_clearance_records SET valid_until=DATE_SUB(CURRENT_DATE,INTERVAL 1 DAY),
            state='verified' WHERE id=(SELECT current_id FROM (SELECT MAX(id) current_id FROM medical_clearance_records WHERE watch_id=?) x)")
            ->execute([$id]);
    }
    foreach (array_slice($ids, 20, 5) as $id) {
        $admin->prepare("UPDATE medical_clearance_records SET issued_on=DATE_ADD(CURRENT_DATE,INTERVAL 1 DAY),
            state='verified',replaces_id=(SELECT previous_id FROM (SELECT MIN(id) previous_id FROM medical_clearance_records WHERE watch_id=?) x)
            WHERE id=(SELECT current_id FROM (SELECT MAX(id) current_id FROM medical_clearance_records WHERE watch_id=?) y)")
            ->execute([$id, $id]);
    }
    $admin->commit();
    $admin->exec('UPDATE medical_clearance_watches SET next_review_on=DATE_ADD(CURRENT_DATE,INTERVAL MOD(id,4) DAY)');
    $admin->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
    exit;
}
$credentials = json_decode((string) file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$matrix = array_values(array_filter(fullHttpMatrix($credentials), static fn (array $c): bool => str_starts_with($c['id'], 'medical-')
    && ($c['status'] !== 200 || in_array($c['id'], ['medical-registrar-all', 'medical-registrar-person'], true))));
$day = static fn (int $n): string => date('Y-m-d', strtotime(($n < 0 ? '' : '+') . $n . ' day'));
$inputs = [
    'broad-search' => ['medical_q' => 'Synthetic', 'medical_state' => 'valid'],
    'broad-pending' => ['medical_q' => 'Synthetic', 'medical_state' => 'pending'],
    'half-pending' => ['medical_q' => 'Synthetic', 'medical_state' => 'pending', 'medical_to' => $day(1)],
    'half-search' => ['medical_q' => 'Synthetic', 'medical_state' => 'valid', 'medical_to' => $day(1)],
    'rare-search' => ['medical_q' => '10026', 'medical_state' => 'valid'],
    'empty-search' => ['medical_q' => '__no_such_person__', 'medical_state' => 'valid'],
    'date-day' => ['medical_from' => $day(1), 'medical_to' => $day(1), 'medical_state' => 'valid'],
    'date-from' => ['medical_from' => $day(1), 'medical_state' => 'valid'],
    'date-to' => ['medical_to' => $day(2), 'medical_state' => 'pending'],
    'date-empty' => ['medical_to' => $day(-1), 'medical_state' => 'valid'],
    'combined' => ['medical_q' => 'Synthetic', 'medical_from' => $day(1), 'medical_to' => $day(2), 'medical_state' => 'valid'],
    'plain-search' => ['medical_q' => 'Synthetic'],
    'plain-date' => ['medical_from' => $day(1), 'medical_to' => $day(2)],
    'plain-empty' => ['medical_q' => '__no_such_person__'],
    'control-state' => ['medical_state' => 'valid'],
];
foreach ($inputs as $name => $input) {
    $matrix[] = ['id' => 'medical-filter-' . $name, 'role' => 'registrar',
        'path' => '/medical_clearance_admin.php?' . http_build_query($input), 'status' => 200];
}
$indexes = [];
$admin->exec('USE `' . $runtime['schema'] . '`');
foreach (['medical_clearance_watches', 'medical_clearance_records', 'sportovci'] as $table) {
    $indexes[$table] = $admin->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
}
file_put_contents($output . '/fixture-indexes.json', json_encode($indexes, JSON_THROW_ON_ERROR));
file_put_contents($output . '/matrix.json', json_encode($matrix, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$report = ['complete' => false, 'server' => $identity, 'method' => 'plain complete HTTP; five warmups and thirty timed samples per positive case',
    'cases' => []];
$previous = $variant === 'after'
    ? json_decode((string) file_get_contents($output . '/http-before.json'), true, 512, JSON_THROW_ON_ERROR) : null;
foreach ($matrix as $index => $case) {
    $hash = null;
    $samples = [];
    $repeat = $case['status'] === 200 ? 35 : 1;
    for ($i = 0; $i < $repeat; $i++) {
        $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if ($response['status'] !== $case['status'] || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body'])) {
            throw new RuntimeException('Medical HTTP failure: ' . $case['id']);
        }
        $current = hash('sha256', fullHttpNormalizedHtml($response));
        if (($hash !== null && $hash !== $current)
            || ($previous !== null && $previous['cases'][$index]['html_sha256'] !== $current)) {
            throw new RuntimeException('Complete normalized HTML differs: ' . $case['id']);
        }
        $hash = $current;
        if ($i >= 5 || $repeat === 1) {
            $samples[] = array_intersect_key($response, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
        }
    }
    $report['cases'][] = $case + ['html_sha256' => $hash, 'samples' => $samples];
}
$report['complete'] = true;
file_put_contents($output . '/http-' . $variant . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
