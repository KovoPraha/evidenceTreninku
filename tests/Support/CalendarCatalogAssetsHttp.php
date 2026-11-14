<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}

$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('An owned fixture is required.');
}
$verifyOnly = ($argv[2] ?? '') === '--verify';
$emptyOnly = ($argv[2] ?? '') === '--empty';
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';

[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$admin->exec('SET GLOBAL query_cache_type=OFF');
if (!in_array(strtoupper((string)$admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('The owned HTTP benchmark requires the SQL result cache to be off.');
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected fixture table name.');
        }
        $value = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
        if ($value === null) {
            throw new RuntimeException('Unavailable fixture checksum: ' . $table);
        }
        $result[$table] = $value;
    }
    return $result;
};
if ($verifyOnly) {
    $expected = json_decode((string)file_get_contents($output . '/checksums.json'), true, 512, JSON_THROW_ON_ERROR);
    if ($checksums() !== $expected) {
        throw new RuntimeException('A read-only request changed the fixture.');
    }
    echo json_encode(['tables' => count($expected), 'unchanged' => true], JSON_THROW_ON_ERROR), "\n";
    exit;
}

$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$events = json_decode((string)file_get_contents($output . '/calendar-events.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($credentials as $role => $credential) {
    fullHttpLogin($runtime, $output, $role, $credential);
    $calendar = in_array($role, ['coach', 'other_coach', 'sports_lead', 'program_coordinator', 'finance_manager'], true);
    $cases[] = ['id' => 'calendar-' . $role, 'role' => $role, 'path' => '/club_calendar.php?month=2026-06',
        'status' => $calendar ? 200 : 403];
    $cases[] = ['id' => 'catalog-' . $role, 'role' => $role, 'path' => '/eshop_catalog_admin.php',
        'status' => $role === 'catalog_manager' ? 200 : 403];
}
foreach (['calendar' => '/club_calendar.php', 'catalog' => '/eshop_catalog_admin.php'] as $id => $path) {
    $cases[] = ['id' => $id . '-anonymous', 'role' => 'anonymous', 'path' => $path, 'status' => 302];
}
foreach ([
    ['calendar-detail', 'coach', '/club_calendar.php?month=2026-06&event_id=' . $events[0]],
    ['calendar-empty', 'coach', '/club_calendar.php?month=2001-01'],
    ['calendar-missing', 'coach', '/club_calendar.php?month=2026-06&event_id=999999'],
    ['calendar-search', 'coach', '/club_calendar.php?month=2026-06&event_id=' . $events[0] . '&athlete_q=1001'],
    ['catalog-filter', 'catalog_manager', '/eshop_catalog_admin.php?status=active&sort=name'],
    ['catalog-empty', 'catalog_manager', '/eshop_catalog_admin.php?q=LOCALHOST-NO-MATCH-777777'],
    ['catalog-invalid', 'catalog_manager', '/eshop_catalog_admin.php?sort=INVALID'],
] as [$id, $role, $path]) {
    $cases[] = compact('id', 'role', 'path') + ['status' => 200];
}
$initial = $checksums();
file_put_contents($output . '/checksums.json', json_encode($initial, JSON_THROW_ON_ERROR));
$readInitial = $checksums();
$variants = ['before' => $runtime, 'after' => ['base_url' => 'http://127.0.0.1:18100'] + $runtime];
$signatures = [];
$samples = [];
$removedBootstrap = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" '
    . 'integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
try {
    foreach ($cases as $case) {
        $id = $case['id'];
        $positive = $case['status'] === 200;
        for ($round = -4; $round < ($positive ? 17 : -3); $round++) {
            foreach ($round % 2 === 0 ? $variants : array_reverse($variants, true) as $variant => $rt) {
                $response = fullHttpRequest($rt, $output, $case['role'], $case['path']);
                $raw = $response['body'];
                if ($response['status'] !== $case['status'] || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $raw)) {
                    throw new RuntimeException('Page HTTP status or error: ' . $id . '/' . $variant . '/' . $response['status']);
                }
                $cdn = substr_count($raw, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
                $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $raw);
                if ($positive && ($local !== 1 || $cdn !== ($variant === 'before' ? 1 : 0))) {
                    throw new RuntimeException('Unexpected CSS links: ' . $id . '/' . $variant);
                }
                $headers = array_intersect_key($response['headers'], array_flip([
                    'content-type', 'cache-control', 'content-security-policy', 'location', 'x-frame-options', 'x-content-type-options',
                ]));
                $html = $raw;
                // P11-ASSET13 compatibility: remove only the exact former CDN tag.
                $html = str_replace($removedBootstrap, '', $html, $removed);
                if ($removed !== ($positive && $variant === 'before' ? 1 : 0)) {
                    throw new RuntimeException('Unexpected Bootstrap normalization: ' . $id . '/' . $variant);
                }
                if (preg_match("/nonce-([^']+)/", $headers['content-security-policy'] ?? '', $nonce)) {
                    $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
                    $headers['content-security-policy'] = str_replace($nonce[1], 'SYNTHETIC_NONCE', $headers['content-security-policy']);
                }
                $signature = [hash('sha256', $html), $headers];
                $signatures[$id] ??= $signature;
                if ($signature !== $signatures[$id]) {
                    throw new RuntimeException('Whole HTML or security headers differ: ' . $id . '/' . $variant);
                }
                if ($round >= 0 || !$positive) {
                    $samples[$id][$variant][] = [
                        'status' => $response['status'],
                        'html_bytes' => strlen($raw),
                        'ttfb_ms' => $response['ttfb_ms'],
                        'total_ms' => $response['total_ms'],
                        'local_css' => $local,
                        'cdn_css' => $cdn,
                    ];
                }
            }
        }
    }
    if ($checksums() !== $readInitial) {
        throw new RuntimeException('Read-only requests changed the fixture.');
    }
} finally {
    if ($checksums() !== $initial) {
        throw new RuntimeException('Read-only matrix changed the fixture.');
    }
}
file_put_contents($output . '/http.json', json_encode([
    'cases' => count($cases), 'database_query_cache_off' => true,
    'removed_bootstrap_bytes' => strlen($removedBootstrap),
    'fixture_people' => (int)$admin->query('SELECT COUNT(*) FROM sportovci')->fetchColumn(),
    'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
