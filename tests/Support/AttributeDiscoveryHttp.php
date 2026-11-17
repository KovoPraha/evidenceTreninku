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
$label = $argv[2] ?? 'matrix';
$verifyOnly = $label === '--verify';
if (!$verifyOnly && !preg_match('/^[a-z0-9-]+$/D', $label)) {
    throw new RuntimeException('Owned case label required.');
}
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
$ids = json_decode((string)file_get_contents($output . '/category-attribute-ids.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($credentials as $role => $credential) {
    if ($label === 'matrix') {
        fullHttpLogin($runtime, $output, $role, $credential);
    }
    foreach (['categories', 'attributes'] as $page) {
        $cases[] = ['id' => $page . '-' . $role, 'role' => $role, 'path' => '/eshop_' . $page . '_admin.php',
            'status' => $role === 'catalog_manager' ? 200 : 403];
    }
}
foreach (['categories', 'attributes'] as $page) {
    $cases[] = ['id' => $page . '-anonymous', 'role' => 'anonymous', 'path' => '/eshop_' . $page . '_admin.php', 'status' => 302];
}
foreach ([
    ['category-detail', '/eshop_categories_admin.php?cesta=' . rawurlencode($ids['category'])],
    ['category-search', '/eshop_categories_admin.php?q=Equipment'],
    ['category-empty', '/eshop_categories_admin.php?q=LOCALHOST-NO-MATCH-777777'],
    ['category-invalid', '/eshop_categories_admin.php?cesta=%3E'],
    ['category-product', '/eshop_categories_admin.php?produkt_id=' . $ids['product']],
    ['category-missing-product', '/eshop_categories_admin.php?produkt_id=999999'],
    ['attribute-text-inactive', '/eshop_attributes_admin.php?id=' . $ids['attributes']['text']],
    ['attribute-number', '/eshop_attributes_admin.php?id=' . $ids['attributes']['number']],
    ['attribute-choice', '/eshop_attributes_admin.php?id=' . $ids['attributes']['choice']],
    ['attribute-missing', '/eshop_attributes_admin.php?id=999999'],
    ['attribute-key', '/eshop_attributes_admin.php?klic=LOCALHOST_NEW'],
] as [$id, $path]) {
    $cases[] = ['id' => $id, 'role' => 'catalog_manager', 'path' => $path, 'status' => 200];
}
foreach (['categories', 'attributes'] as $page) {
    $cases[] = ['id' => $page . '-csrf-rejected', 'role' => 'catalog_manager', 'path' => '/eshop_' . $page . '_admin.php',
        'status' => 200, 'post' => ['csrf_token' => 'LOCALHOST-INVALID-CSRF', 'action' => 'save_meta']];
}
if ($label !== 'matrix') {
    $cases = array_values(array_filter($cases, static fn (array $case): bool =>
        in_array($case['id'], ['attributes-catalog_manager', 'attribute-choice', 'categories-catalog_manager'], true)));
}
$initial = $checksums();
file_put_contents($output . '/checksums.json', json_encode($initial, JSON_THROW_ON_ERROR));
$readInitial = $checksums();
$variants = ['before' => $runtime, 'after' => ['base_url' => 'http://127.0.0.1:18100'] + $runtime];
$signatures = [];
$samples = [];
try {
    foreach ($cases as $case) {
        $id = $case['id'];
        $positive = $case['status'] === 200;
        for ($round = -4; $round < ($positive ? 17 : -3); $round++) {
            foreach ($round % 2 === 0 ? $variants : array_reverse($variants, true) as $variant => $rt) {
                $response = fullHttpRequest($rt, $output, $case['role'], $case['path'], $case['post'] ?? null);
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
    'removed_bootstrap_bytes' => 0, 'label' => $label,
    'fixture_people' => (int)$admin->query('SELECT COUNT(*) FROM sportovci')->fetchColumn(),
    'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
