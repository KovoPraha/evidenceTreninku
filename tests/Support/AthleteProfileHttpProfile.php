<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$phase = $argv[2] ?? '';
if ($output === false || !in_array($phase, ['prepare', 'before', 'after'], true)) {
    throw new RuntimeException('Owned output and phase required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($phase === 'prepare') {
    $admin->exec('SET GLOBAL query_cache_type=OFF');
    $admin->exec("INSERT INTO coach_scope_grants(trainer_id,scope_kind,scope_id,valid_from,reason,created_by_trainer_id)
        VALUES(7,'team',2,'2020-01-01','LOCALHOST synthetic athlete profile scope',7)");
    $admin->exec('CREATE TABLE IF NOT EXISTS trenink_mereni(trenink_id INT,mereni_id INT,poradi INT);
        CREATE TABLE IF NOT EXISTS segmenty(id INT PRIMARY KEY,nazev VARCHAR(100));
        CREATE TABLE IF NOT EXISTS sportovec_poznamka(trenink_id INT,sportovec_id INT,poznamka TEXT)');
    $fields = ['sportovec_id' => 'INT', 'typ' => 'VARCHAR(50)', 'vzdalenost' => 'VARCHAR(30)',
        'distance_unit' => 'VARCHAR(10)', 'cas' => 'VARCHAR(50)', 'prevod' => 'VARCHAR(20)',
        'cvik_id' => 'INT', 'segment_id' => 'INT', 'vaha' => 'VARCHAR(30)', 'opakovani' => 'INT',
        'rpe' => 'VARCHAR(30)', 'poznamka' => 'TEXT'];
    $columns = array_column($admin->query('SHOW COLUMNS FROM mereni_zaznamy')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    foreach ($fields as $name => $type) {
        if (!in_array($name, $columns, true)) {
            $admin->exec('ALTER TABLE mereni_zaznamy ADD ' . $name . ' ' . $type . ' NULL');
        }
    }
    $columns = array_column($admin->query('SHOW COLUMNS FROM trenink_mereni')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('poradi', $columns, true)) {
        $admin->exec('ALTER TABLE trenink_mereni ADD poradi INT');
    }
    $admin->beginTransaction();
    $measurement = $admin->prepare('INSERT INTO mereni_zaznamy(id,sportovec_id,typ,vzdalenost,distance_unit,cas,prevod,poznamka) VALUES(?,?,?,?,?,?,?,?)');
    $link = $admin->prepare('INSERT INTO trenink_mereni(trenink_id,mereni_id,poradi) VALUES(?,?,?)');
    $note = $admin->prepare('INSERT INTO sportovec_poznamka(trenink_id,sportovec_id,poznamka) VALUES(?,?,?)');
    $types = ['kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb','KOLO','kolo ','unknown'];
    foreach (range(1, 1000) as $i) {
        $tid = 9000000 + $i;
        $note->execute([$tid, 1001, 'Synthetic own note ' . $i]);
        $note->execute([$tid, 1002, 'Foreign hidden note ' . $i]);
        foreach (range(0, 9) as $n) {
            $mid = 9000000 + $i * 10 + $n;
            $measurement->execute([$mid, $n === 9 ? 1002 : 1001, $types[($i + $n) % 9], '10.25', 'km',
                '00:30:00', '50/14', str_repeat('Synthetic measurement <note> ', 8)]);
            $link->execute([$tid, $mid, $n]);
        }
    }
    $admin->commit();
    exit;
}
if (!in_array(strtoupper((string) $admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('HTTP result cache must be OFF.');
}
$checksums = static function (PDO $pdo): array {
    $out = [];
    foreach (['treninky','trenink_sportovec','trenink_mereni','mereni_zaznamy','sportovec_poznamka'] as $table) {
        $out[$table] = $pdo->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $out;
};
$checksum = $checksums($admin);
$profile = fullHttpRequest($runtime, $output, 'coach', '/sportovec_treninky.php?id=1001');
if ($profile['status'] !== 200) {
    throw new RuntimeException('Scoped staff profile unavailable: ' . $profile['status']);
}
$issued = public_profile_access_issue($admin, 1001, 7);
$exchange = fullHttpRequest($runtime, $output, 'bearer', '/sportovec_treninky.php?hash=' . $issued['token']);
if ($exchange['status'] !== 303 || ($exchange['headers']['location'] ?? '') !== 'sportovec_treninky.php') {
    throw new RuntimeException('Real bearer exchange must redirect to clean URL.');
}
$cases = [['id' => 'parent', 'kind' => 'parent', 'role' => 'coach', 'params' => []]];
foreach (['all' => ['rok' => 0], 'year' => ['rok' => date('Y')], 'page2' => ['rok' => 0, 'profile_page' => 2],
    'last' => ['rok' => 0, 'profile_page' => 1000000], 'empty' => ['rok' => 2099]] as $id => $params) {
    $cases[] = ['id' => 'history_' . $id, 'kind' => 'history', 'role' => 'coach', 'params' => $params];
}
foreach (['all' => '', 'exact_case' => 'KOLO', 'exact_space' => 'kolo ', 'unknown' => 'unknown', 'empty' => 'absent'] as $id => $type) {
    $cases[] = ['id' => 'measurements_' . $id, 'kind' => 'measurements', 'role' => 'coach', 'params' => ['view' => 'measurements', 'typ' => $type]];
}
$cases[] = ['id' => 'measurements_page2', 'kind' => 'measurements', 'role' => 'coach',
    'params' => ['view' => 'measurements', 'typ' => 'kolo', 'profile_page' => 2]];
$cases[] = ['id' => 'bearer_history', 'kind' => 'history', 'role' => 'bearer', 'params' => ['rok' => 0, 'id' => 1002, 'sid' => 1002]];
$cases[] = ['id' => 'bearer_measurements', 'kind' => 'measurements', 'role' => 'bearer', 'params' => ['view' => 'measurements', 'id' => 1002, 'sid' => 1002]];
$previous = $phase === 'after' ? json_decode(file_get_contents($output . '/profile-http-before.json'), true, 512, JSON_THROW_ON_ERROR) : null;
$domain = static function (array $response, string $kind, string $type = ''): array {
    $doc = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . fullHttpNormalizedHtml($response), LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $xp = new DOMXPath($doc);
    $rows = [];
    if ($kind === 'parent') {
        foreach ($xp->query('//script | //*[@id="pane-mereni"]') as $node) {
            $node->parentNode->removeChild($node);
        }
    }
    foreach ($xp->query('//text()') as $text) {
        $text->nodeValue = preg_replace('/\s+/u', ' ', trim($text->nodeValue));
    }
    if ($kind === 'parent') {
        return ['hash' => hash('sha256', $doc->saveHTML())];
    }
    $query = $kind === 'history' ? '//div[contains(concat(" ",normalize-space(@class)," ")," trenink-card ")]'
        : '//*[@id="mereniTable"]/tbody/tr';
    foreach ($xp->query($query) as $row) {
        if ($kind === 'measurements' && $type !== '' && $row->getAttribute('data-typ') !== $type) {
            continue;
        }
        $rows[] = hash('sha256', $doc->saveHTML($row));
    }
    $summary = [];
    $query = $kind === 'history' ? '/html/body/div[1]//strong' : '//*[contains(concat(" ",normalize-space(@class)," ")," mereni-filter-btn ")]';
    foreach ($xp->query($query) as $node) {
        $summary[] = trim($node->textContent);
    }
    return ['rows' => $rows, 'summary' => $summary];
};
$report = ['complete' => false, 'phase' => $phase, 'checksums_before' => $checksum, 'cases' => []];
foreach ($cases as $i => $case) {
    $kind = $case['kind'];
    $type = $case['params']['typ'] ?? '';
    $parent = $kind === 'parent' || ($kind === 'measurements' && $phase === 'before');
    $path = $parent ? '/sportovec_treninky.php' : '/ajax_sportovec_treninky.php?' . http_build_query($case['params']);
    $samples = [];
    $signature = null;
    for ($n = 0; $n < 22; $n++) {
        $r = fullHttpRequest($runtime, $output, $case['role'], $path);
        if ($r['status'] !== 200 || preg_match('/Fatal error|Warning:/', $r['body'])) {
            file_put_contents($output . '/case-calibration.html', $r['body']);
            throw new RuntimeException('Athlete profile HTTP failed: ' . $case['id'] . ', status ' . $r['status']);
        }
        if (($r['headers']['referrer-policy'] ?? '') !== 'no-referrer' || !str_contains($r['headers']['cache-control'] ?? '', 'no-store')) {
            throw new RuntimeException('Profile privacy headers changed.');
        }
        if (str_contains($r['body'], 'Foreign hidden note') || str_contains($r['body'], 'save-note-btn')) {
            throw new RuntimeException('Read-only session leaked foreign notes or a write control.');
        }
        $value = $domain($r, $kind, $type);
        if ($n === 0) {
            $signature = $value;
            if ($previous) {
                $wanted = $previous['cases'][$i]['domain'];
                if ($kind !== 'parent') {
                    $page = max(1, min((int) ($case['params']['profile_page'] ?? 1), max(1, (int) ceil(count($wanted['rows']) / 25))));
                    $wanted['rows'] = array_slice($wanted['rows'], ($page - 1) * 25, 25);
                }
                if ($value !== $wanted) {
                    throw new RuntimeException('Changed rendered values/order/summaries: ' . $case['id']);
                }
            }
        } elseif ($value !== $signature) {
            throw new RuntimeException('Unstable HTTP domain: ' . $case['id']);
        }
        if ($n >= 5) {
            $samples[] = array_intersect_key($r, array_flip(['status','ttfb_ms','total_ms','bytes']));
        }
    }
    $report['cases'][] = $case + ['status' => 200, 'path_kind' => $parent ? 'full_profile_GET' : 'profile_AJAX', 'domain' => $signature, 'samples' => $samples];
}
$report['denials'] = [];
foreach (['', '?view=measurements', '?view=measurements&id=1001&sid=1001'] as $params) {
    $r = fullHttpRequest($runtime, $output, 'guest', '/ajax_sportovec_treninky.php' . $params);
    if ($r['status'] !== 403) {
        throw new RuntimeException('Anonymous athlete access allowed.');
    }
    $report['denials'][] = ['status' => 403];
}
$admin->exec('UPDATE public_profile_access_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE sportovec_id=1001');
foreach (['', '?view=measurements'] as $params) {
    if (fullHttpRequest($runtime, $output, 'bearer', '/ajax_sportovec_treninky.php' . $params)['status'] !== 403) {
        throw new RuntimeException('Revoked bearer retained access.');
    }
}
$report['bearer_revocation'] = true;
$admin->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
try {
    if (fullHttpRequest($runtime, $output, 'coach', '/ajax_sportovec_treninky.php?view=measurements')['status'] !== 403) {
        throw new RuntimeException('Revoked coach retained access.');
    }
    $report['staff_revocation'] = true;
} finally {
    $admin->exec('UPDATE treneri SET aktivni=1 WHERE id=7');
    $credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
    fullHttpLogin($runtime, $output, 'coach', $credentials['coach']);
}
if ($checksums($admin) !== $checksum || ($previous && $previous['checksums_before'] !== $checksum)) {
    throw new RuntimeException('Profile read changed domain data.');
}
$report['complete'] = true;
$report['data_unchanged'] = true;
file_put_contents($output . '/profile-http-' . $phase . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Athlete profile ' . $phase . ' HTTP complete' . PHP_EOL;
