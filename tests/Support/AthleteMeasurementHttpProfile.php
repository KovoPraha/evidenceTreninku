<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '';
$size = (int) ($argv[3] ?? 1000);
if ($output === false || !in_array($mode, ['measure', 'diagnose', 'security'], true) || !in_array($size, [1000, 10000, 30000], true)) {
    throw new RuntimeException('Owned output, mode and size required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if (!in_array(strtoupper((string) $admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('SQL result cache must be OFF.');
}
$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$variants = getenv('ATHLETE_MEASUREMENT_COMPARE') === '1' ? ['before', 'after'] : ['before'];

function parentRuntime(array $runtime, string $variant): array
{
    $runtime['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    return $runtime;
}

function parentSignature(array $response): array
{
    if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $response['body'])) {
        throw new RuntimeException('Parent HTTP failed: ' . $response['status']);
    }
    $html = fullHttpNormalizedHtml($response);
    $html = preg_replace('/(const CSRF_TOKEN\s*=\s*)"[^"]*"/', '$1"SYNTHETIC_CSRF"', $html);
    return ['status' => 200, 'bytes' => strlen($html), 'normalized_sha256' => hash('sha256', $html)];
}

foreach ($variants as $variant) {
    $cookie = $output . '/cookie-parent-' . $variant . '.txt';
    if (is_file($cookie)) {
        unlink($cookie);
    }
    fullHttpLogin(parentRuntime($runtime, $variant), $output, 'parent-' . $variant, $credentials['coach']);
}
$checksums = static function () use ($admin): array {
    $out = [];
    foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka',
        'training_closures', 'training_closure_revisions', 'sports_event_participation',
        'sports_event_revisions', 'sports_activity_statements', 'sportovec_obdobi'] as $table) {
        $out[$table] = $admin->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $out;
};
$before = $checksums();
if ($mode === 'diagnose') {
    foreach ($variants as $variant) {
        $rt = parentRuntime($runtime, $variant);
        parentSignature(fullHttpRequest($rt, $output, 'parent-' . $variant, '/sportovec_treninky.php?id=1001'));
        parentSignature(fullHttpRequest(
            $rt,
            $output,
            'parent-' . $variant,
            '/ajax_sportovec_treninky.php?view=measurements',
            null,
            ['X-P11-Measure: measurement-' . $variant]
        ));
    }
    exit;
}
if ($mode === 'security') {
    require __DIR__ . '/AthleteMeasurementSecurity.php';
    exit;
}
$report = ['complete' => false, 'size' => $size, 'samples' => [], 'signatures' => [], 'controls' => [], 'control_signatures' => []];
$cases = ['primary' => [1001, 'view=measurements'], 'exact_case' => [1001, 'view=measurements&typ=KOLO'],
    'exact_space' => [1001, 'view=measurements&typ=kolo%20'], 'page2' => [1001, 'view=measurements&profile_page=2'],
    'last' => [1001, 'view=measurements&profile_page=1000000'], 'absent' => [1001, 'view=measurements&typ=absent'],
    'small' => [1012, 'view=measurements'], 'empty' => [1020, 'view=measurements']];
for ($i = -4; $i < 17; $i++) {
    foreach ($i % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $rt = parentRuntime($runtime, $variant);
        foreach ($cases as $case => [$person, $query]) {
            parentSignature(fullHttpRequest($rt, $output, 'parent-' . $variant, '/sportovec_treninky.php?id=' . $person));
            $r = fullHttpRequest($rt, $output, 'parent-' . $variant, '/ajax_sportovec_treninky.php?' . $query);
            $signature = parentSignature($r);
            $report['control_signatures'][$case][$variant] ??= $signature;
            if ($report['control_signatures'][$case][$variant] !== $signature) {
                throw new RuntimeException('Unstable whole measurement fragment.');
            }
            if ($i >= 0) {
                $sample = ['ttfb_ms' => $r['ttfb_ms'], 'total_ms' => $r['total_ms'], 'bytes' => $r['bytes']];
                $report['controls'][$case][$variant][] = $sample;
                if ($case === 'primary') {
                    $report['samples'][$variant][] = $sample;
                    $report['signatures'][$variant] = $signature;
                }
            }
        }
    }
}
if (count($variants) === 2) {
    foreach ($report['control_signatures'] as $signatures) {
        if ($signatures['before'] !== $signatures['after']) {
            throw new RuntimeException('Whole measurement fragment differs.');
        }
    }
}
if ($checksums() !== $before) {
    throw new RuntimeException('Measurement changed domain data.');
}
$report['complete'] = true;
$report['domain_checksums_unchanged'] = true;
file_put_contents($output . '/measurement-http-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Measurement whole HTTP complete: ' . $size . PHP_EOL;
