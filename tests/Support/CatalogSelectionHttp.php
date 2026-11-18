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
require_once __DIR__ . '/CategoryMenuComparison.php';

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

$variants = ['before' => $runtime, 'after' => ['base_url' => 'http://127.0.0.1:18100'] + $runtime];
if ($label === 'matrix') {
    $credential = json_decode((string)file_get_contents($output . '/checkout-credential-private.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($variants as $variant => $rt) {
        $role = 'customer-' . $variant;
        $form = fullHttpRequest($rt, $output, $role, '/booking/prihlaseni.php');
        if ($form['status'] !== 200 || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $form['body'], $token)) {
            throw new RuntimeException('Customer login unavailable.');
        }
        $post = fullHttpRequest($rt, $output, $role, '/booking/prihlaseni.php', [
            'csrf_token' => $token[1], 'email' => $credential['email'], 'heslo' => $credential['password'],
        ]);
        if ($post['status'] !== 302) {
            throw new RuntimeException('Customer login failed.');
        }
    }
}
$volume = json_decode((string)file_get_contents($output . '/catsel-volume-private.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach (['anonymous', 'customer'] as $role) {
    foreach ([
        'overview' => '', 'category' => '?kategorie=LOCALHOST-0',
        'leaf' => '?kategorie=LOCALHOST-' . ($volume['nodes'] - 1), 'missing' => '?kategorie=LOCALHOST-absent',
        'invalid' => '?kategorie=%3E', 'search' => '?q=LOCALHOST', 'empty' => '?q=LOCALHOST-NO-MATCH-999',
        'sort-price' => '?razeni=price', 'sort-name' => '?razeni=name', 'page' => '?strana=999',
    ] as $id => $query) {
        if ($label !== 'matrix' && !in_array($id, ['overview', 'category', 'leaf'], true)) {
            continue;
        }
        $cases[] = ['id' => $id . '-' . $role, 'role' => $role, 'path' => '/booking/eshop.php' . $query, 'status' => 200];
    }
    if ($label === 'matrix') {
        $cases[] = ['id' => 'csrf-' . $role, 'role' => $role, 'path' => '/booking/eshop.php',
            'status' => $role === 'anonymous' ? 303 : 200,
            'post' => ['csrf_token' => 'LOCALHOST-INVALID', 'action' => 'add', 'variant_id' => 300001, 'quantity' => 1]];
    }
}
// Establish sessions/CSRF outside both checksums and measurements.
foreach ($variants as $variant => $rt) {
    foreach (['anonymous', 'customer'] as $role) {
        fullHttpRequest($rt, $output, $role . '-' . $variant, '/booking/eshop.php');
    }
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
                $response = fullHttpRequest($rt, $output, $case['role'] . '-' . $variant, $case['path'], $case['post'] ?? null);
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
                    $headers['content-security-policy'] = categoryMenuComparisonCsp(
                        $raw,
                        $headers['content-security-policy'],
                        $nonce[1],
                        $rt['base_url']
                    );
                    $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
                    $headers['content-security-policy'] = str_replace($nonce[1], 'SYNTHETIC_NONCE', $headers['content-security-policy']);
                }
                $html = preg_replace('/(name="csrf_token"\s+value=")[a-f0-9]{64}(")/', '$1SYNTHETIC_CSRF$2', $html);
                $html = preg_replace('/(name="checkout_key"\s+value=")[a-f0-9]{32}(")/', '$1SYNTHETIC_CHECKOUT$2', $html);
                // Each physical server advertises its own origin, including images
                // under CSP self. Compare every path/attribute with only that origin normalized.
                $html = str_replace($rt['base_url'], 'http://synthetic.local', $html);
                foreach ($headers as &$value) {
                    $value = str_replace($rt['base_url'], 'http://synthetic.local', $value);
                }
                unset($value);
                $signature = [hash('sha256', $html), $headers];
                $signatures[$id] ??= $signature;
                if ($signature !== $signatures[$id]) {
                    file_put_contents($output . '/comparison-failure-private.html', $html);
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
    'removed_bootstrap_bytes' => 0, 'label' => $label, 'fixture_volume' => $volume,
    'fixture_people' => (int)$admin->query('SELECT COUNT(*) FROM sportovci')->fetchColumn(),
    'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
