<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$variant = $argv[2] ?? '';
if ($output === false || !in_array($variant, ['before', 'after', 'prepare'], true)) {
    throw new RuntimeException('Owned output and before/after/prepare required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
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
    $admin->query('ANALYZE TABLE medical_clearance_watches,medical_clearance_records,sportovci')->fetchAll();
    exit;
}
$credentials = json_decode((string) file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$matrix = array_values(array_filter(fullHttpMatrix($credentials), static fn (array $c): bool => str_starts_with($c['id'], 'medical-')));
foreach ($matrix as &$case) {
    if ($case['id'] === 'medical-registrar-empty') {
        $case['id'] = 'medical-registrar-expired';
    }
}
unset($case);
foreach (['missing' => '?medical_state=missing', 'not-yet-valid' => '?medical_state=not_yet_valid',
    'valid-page2' => '?medical_state=valid&medical_page=2',
    'valid-last-page' => '?medical_state=valid&medical_page=999999',
    'search-empty' => '?medical_state=valid&medical_q=__no_such_synthetic_person__',
    'state-search' => '?medical_state=valid&medical_q=Synthetic',
    'state-person' => '?medical_state=valid&person_id=10026'] as $name => $query) {
    $matrix[] = ['id' => 'medical-registrar-' . $name, 'role' => 'registrar',
        'path' => '/medical_clearance_admin.php' . $query, 'status' => 200];
}
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
