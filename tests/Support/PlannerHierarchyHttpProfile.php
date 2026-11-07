<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$variant = $argv[2] ?? '';
if ($output === false || !in_array($variant, ['before', 'after', 'expand'], true)) {
    throw new RuntimeException('Owned output and before/after/expand required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
if ($variant === 'expand') {
    $admin->exec('USE `' . $runtime['schema'] . '`');
    $plan = $admin->prepare("INSERT INTO planovane_treninky(id,datum,nazev,cas_od,cas_do,trener_id,skupina_id,kategorie)
        VALUES(?,?,'Synthetic historical plan','16:00','17:00',?,1,'mtb')");
    $link = $admin->prepare("INSERT INTO training_roster_links(plan_id,team_id,target_date,team_code_snapshot,team_name_snapshot,created_by_trainer_id)
        VALUES(?,?,?,'SYN','Synthetic historical roster',7)");
    $start = new DateTimeImmutable('monday this week');
    $admin->beginTransaction();
    for ($i = 0; $i < 20000; $i++) {
        $date = $start->modify('-' . (8 + $i % 350) . ' days')->format('Y-m-d');
        $plan->execute([700000 + $i, $date, 7 + $i % 2]);
        $link->execute([700000 + $i, 1 + $i % 2, $date]);
    }
    $admin->commit();
    $admin->query('ANALYZE TABLE planovane_treninky,training_roster_links')->fetchAll();
    exit;
}
$credentials = json_decode((string) file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$matrix = array_values(array_filter(fullHttpMatrix($credentials), static fn (array $c): bool => str_starts_with($c['id'], 'planner-')));
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
            throw new RuntimeException('Planner HTTP failure: ' . $case['id']);
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
