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

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
w72LegacyColumns($admin, 'sportovci', [
    'kis_neuhrazeno' => 'DECIMAL(10,2) NULL',
    'kis_last_seen_at' => 'TIMESTAMP NULL',
    'kis_aktivni' => 'TINYINT NULL',
]);
foreach ([
    'sportovec_skupina(sportovec_id INT NOT NULL,skupina_id INT NOT NULL,PRIMARY KEY(sportovec_id,skupina_id))',
    'sportovec_obdobi(id INT PRIMARY KEY,sportovec_id INT,datum_do DATE NULL)',
    'soupiska_mapping(id INT PRIMARY KEY,skupina_id INT,podskupina_id INT)',
] as $definition) {
    $admin->exec('CREATE TABLE IF NOT EXISTS ' . $definition . ' ENGINE=InnoDB');
}
$admin->exec("UPDATE sportovci SET
    kis_neuhrazeno=CASE WHEN id % 3 = 0 THEN 129.99 WHEN id % 3 = 1 THEN 0 ELSE -1 END,
    kis_last_seen_at=CASE WHEN id % 3 = 0 THEN NULL
        WHEN id % 3 = 1 THEN DATE_SUB(CURRENT_DATE, INTERVAL 180 DAY) ELSE CURRENT_DATE END,
    kis_aktivni=CASE WHEN id % 2 = 0 THEN 1 ELSE 0 END");
$admin->exec('INSERT IGNORE INTO sportovec_skupina(sportovec_id,skupina_id)
    SELECT id,1 FROM sportovci WHERE id % 2 = 0');
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($credentials as $role => $credential) {
    fullHttpLogin($runtime, $output, $role, $credential);
    $cases[] = ['id' => $role, 'role' => $role, 'path' => '/admin_dashboard.php',
        'status' => $role === 'system_admin' ? 200 : 403];
}
$cases[] = ['id' => 'anonymous', 'role' => 'anonymous', 'path' => '/admin_dashboard.php', 'status' => 302];
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
            if ($positive && ($local !== 1 || $cdn !== 1)) {
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
    'cases' => count($cases), 'database_query_cache_off' => true,
    'fixture_people' => (int)$admin->query('SELECT COUNT(*) FROM sportovci')->fetchColumn(),
    'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
