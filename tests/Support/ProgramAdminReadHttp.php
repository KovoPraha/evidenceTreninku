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

// Only owned synthetic configuration: enable the real legacy gate for this route.
$admin->exec("INSERT INTO opravneni(klic,min_role) VALUES('sync_evidence','hlavni')
    ON DUPLICATE KEY UPDATE min_role=VALUES(min_role)");
require_once __DIR__ . '/ProgramAdminReadFixture.php';
if (!$emptyOnly) {
    programAdminReadPopulate($admin, 100);
}
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($emptyOnly ? [] : $credentials as $role => $credential) {
    fullHttpLogin($runtime, $output, $role, $credential);
    $cases[] = ['id' => $role, 'role' => $role, 'path' => '/club_programs_admin.php',
        'status' => $role === 'program_coordinator' ? 200 : 403];
}
$cases[] = ['id' => 'anonymous', 'role' => 'anonymous', 'path' => '/club_programs_admin.php', 'status' => 302];
$cases[] = ['id' => 'program-terms', 'role' => 'program_coordinator',
    'path' => '/club_programs_admin.php?terms_program_id=1', 'status' => 200];
$cases[] = ['id' => 'offer-terms', 'role' => 'program_coordinator',
    'path' => '/club_programs_admin.php?terms_program_id=1&terms_offer_id=1', 'status' => 200];
$cases[] = ['id' => 'missing-program', 'role' => 'program_coordinator',
    'path' => '/club_programs_admin.php?terms_program_id=999999', 'status' => 200];
$initial = $checksums();
$saved = [];
if ($emptyOnly) {
    // This setup transition only touches the owned synthetic fixture. Preserve exact rows
    // and restore them before comparing the original all-table checksums again.
    foreach (['club_program_offers', 'club_programs'] as $table) {
        $saved[$table] = $admin->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    }
    $admin->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        $admin->exec('DELETE FROM club_program_offers; DELETE FROM club_programs');
    } finally {
        $admin->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $cases = [['id' => 'empty-overview', 'role' => 'program_coordinator',
        'path' => '/club_programs_admin.php', 'status' => 200]];
} else {
    file_put_contents($output . '/checksums.json', json_encode($initial, JSON_THROW_ON_ERROR));
}
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
    if ($checksums() !== $readInitial) {
        throw new RuntimeException('Read-only requests changed the fixture.');
    }
} finally {
    if ($emptyOnly) {
        $admin->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_reverse($saved, true) as $table => $rows) {
                foreach ($rows as $row) {
                    $columns = '`' . implode('`,`', array_keys($row)) . '`';
                    $insert = $admin->prepare('INSERT INTO ' . $table . '(' . $columns . ') VALUES('
                        . implode(',', array_fill(0, count($row), '?')) . ')');
                    $insert->execute(array_values($row));
                }
            }
        } finally {
            $admin->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        if ($checksums() !== $initial) {
            throw new RuntimeException('Empty fixture restoration changed values.');
        }
    }
}
file_put_contents($output . ($emptyOnly ? '/empty-http.json' : '/http.json'), json_encode([
    'cases' => count($cases), 'database_query_cache_off' => true,
    'fixture_offers' => (int)$admin->query('SELECT COUNT(*) FROM club_program_offers')->fetchColumn(),
    'fixture_people' => (int)$admin->query('SELECT COUNT(*) FROM sportovci')->fetchColumn(),
    'signatures' => $signatures, 'samples' => $samples,
], JSON_THROW_ON_ERROR));
