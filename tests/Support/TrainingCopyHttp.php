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
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once __DIR__ . '/BootstrapAssetComparison.php';

[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
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
foreach ($credentials as $role => $credential) {
    fullHttpLogin($runtime, $output, $role, $credential);
}
$cases = [];
foreach ([
    'all' => 'obdobi=all',
    'default' => '',
    'month' => 'obdobi=30',
    'year' => 'obdobi=365',
    'group' => 'obdobi=all&skupina_id=1',
    'root' => 'obdobi=all&team_id=1',
    'child' => 'obdobi=all&team_id=2',
    'empty' => 'obdobi=all&team_id=999999',
    'zero' => 'obdobi=all&team_id=0',
    'group-root' => 'obdobi=all&team_id=1&skupina_id=1',
] as $id => $query) {
    $cases[] = ['id' => $id, 'role' => 'coach',
        'path' => '/duplikovat_trenink.php' . ($query === '' ? '' : '?' . $query), 'status' => 200];
}
$cases[] = ['id' => 'lead-ties', 'role' => 'sports_lead', 'path' => '/duplikovat_trenink.php?obdobi=all', 'status' => 200];
$cases[] = ['id' => 'anonymous', 'role' => 'anonymous', 'path' => '/duplikovat_trenink.php', 'status' => 302];
$initial = $checksums();
file_put_contents($output . '/checksums.json', json_encode($initial, JSON_THROW_ON_ERROR));
$variants = ['before' => $runtime, 'after' => ['base_url' => 'http://127.0.0.1:18100'] + $runtime];
$signatures = [];
$samples = [];
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
            if ($positive && ($local !== 1 || $cdn !== 0)) {
                throw new RuntimeException('Unexpected CSS links: ' . $id . '/' . $variant);
            }
            $headers = array_intersect_key($response['headers'], array_flip([
                'content-type', 'cache-control', 'content-security-policy', 'location', 'x-frame-options', 'x-content-type-options',
            ]));
            $html = $raw;
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
file_put_contents($output . '/http.json', json_encode([
    'cases' => count($cases), 'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
