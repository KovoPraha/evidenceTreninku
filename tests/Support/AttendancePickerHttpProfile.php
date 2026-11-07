<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$variant = $argv[2] ?? '';
$verifyExisting = ($argv[3] ?? '') === '--verify-existing';
if ($output === false || !in_array($variant, ['before', 'after'], true)) {
    throw new RuntimeException('Owned output and before/after required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$matrix = array_values(array_filter(fullHttpMatrix($credentials), static fn (array $c): bool => str_starts_with($c['path'], '/training_closure_admin.php')));
file_put_contents($output . '/matrix.json', json_encode($matrix, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$core = ['planovane_treninky', 'training_roster_links', 'club_roster_members', 'club_team_edges', 'treninky',
    'trenink_sportovec', 'training_closures', 'training_closure_revisions', 'medical_clearance_watches',
    'medical_clearance_records', 'medical_clearance_policies', 'treneri', 'staff_user_positions'];
$checksum = static function () use ($admin, $runtime, $core): array {
    $tables = array_map(static fn (string $t): string => '`' . $runtime['schema'] . '`.`' . $t . '`', $core);
    return $admin->query('CHECKSUM TABLE ' . implode(',', $tables) . ' EXTENDED')->fetchAll(PDO::FETCH_KEY_PAIR);
};
$report = ['complete' => false, 'variant' => $variant, 'server' => $identity, 'php' => PHP_VERSION,
    'warmups' => 5, 'samples_per_positive_case' => 30, 'core_checksums_before' => $checksum(), 'cases' => []];
if ($verifyExisting) {
    $report = json_decode((string)file_get_contents($output . '/http-' . $variant . '.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($report['server'] !== $identity || count($report['cases']) !== count($matrix)) {
        throw new RuntimeException('Cannot verify a different or incomplete matrix.');
    }
    foreach ($report['cases'] as $i => $case) {
        $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if (array_intersect_key($case, $matrix[$i]) !== $matrix[$i]
            || count($case['samples']) !== ($case['status'] === 200 ? 30 : 1)
            || $response['status'] !== $case['status']
            || hash('sha256', fullHttpNormalizedHtml($response)) !== $case['html_sha256']) {
            throw new RuntimeException('Cannot verify altered measured output.');
        }
    }
    $report['verification_only_resume'] = true;
}
foreach ($matrix as $case) {
    if ($verifyExisting) {
        continue;
    }
    $samples = [];
    $hash = null;
    for ($i = 0; $i < ($case['status'] === 200 ? 35 : 1); $i++) {
        $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if ($response['status'] !== $case['status'] || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body'])) {
            file_put_contents($output . '/case-calibration.html', $response['body']);
            throw new RuntimeException('Unexpected HTTP status/body: ' . $case['id']);
        }
        $currentHash = hash('sha256', fullHttpNormalizedHtml($response));
        if ($hash !== null && $hash !== $currentHash) {
            throw new RuntimeException('Unstable HTML: ' . $case['id']);
        }
        $hash = $currentHash;
        if ($case['status'] !== 200 || $i >= 5) {
            $samples[] = array_intersect_key($response, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
        }
    }
    $case += ['html_sha256' => $hash, 'samples' => $samples];
    if ($case['status'] === 200) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $response['body'], LIBXML_NONET);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $values = static fn (string $query): array => array_map(static fn (DOMNode $n): string => $n->nodeValue, iterator_to_array($x->query($query)));
        $case['form'] = [
            'participant_options' => $x->query('//select[@name="participant_ids[]"]/option')->length,
            'participant_ids' => $values('//select[@name="participant_ids[]"]/option[@selected]/@value'),
            'coach_ids' => $values('//select[@name="coach_ids[]"]/option[@selected]/@value'),
            'record_source' => $values('//select[@name="record_source"]/option[@selected]/@value'),
            'version' => $values('//input[@name="version"]/@value'),
            'times' => $values('//input[@type="datetime-local"]/@value'),
            'excuse_options' => $x->query('//select[@name="person_id"]/option')->length,
            'lesson_choices' => $x->query('//*[@id="lesson-work"]//ul/li')->length,
            'history' => $values('//*[@id="actual-attendance"]//ul/li/text()'),
        ];
    }
    $report['cases'][] = $case;
    file_put_contents($output . '/http-' . $variant . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    echo $case['id'] . ': ' . $case['status'] . "\n";
}
$report['core_checksums_after'] = $checksum();
$report['core_unchanged'] = $report['core_checksums_before'] === $report['core_checksums_after'];
if (!$report['core_unchanged'] || in_array(null, $report['core_checksums_after'], true)) {
    throw new RuntimeException('Core data changed.');
}
if ($variant === 'after') {
    $before = json_decode((string)file_get_contents($output . '/http-before.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($before['core_checksums_before'] !== $report['core_checksums_after']) {
        throw new RuntimeException('Before/after fixture changed.');
    }
    foreach ($report['cases'] as $i => $case) {
        if ($case['status'] !== 200) {
            continue;
        }
        $original = $before['cases'][$i]['form'];
        $current = $case['form'];
        unset($original['participant_options'], $original['excuse_options'], $current['participant_options'], $current['excuse_options']);
        // Selection is a set; the old SQL had unspecified order for identical names.
        // The paginated picker deliberately adds ID as a stable tie-breaker.
        foreach (['participant_ids', 'coach_ids'] as $key) {
            sort($original[$key], SORT_STRING);
            sort($current[$key], SORT_STRING);
        }
        if ($original !== $current) {
            throw new RuntimeException('Selected people/source/version/times/history changed: ' . $case['id']);
        }
    }
    $report['form_state_parity'] = true;
}
$report['complete'] = true;
file_put_contents($output . '/http-' . $variant . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
