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
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($phase === 'prepare') {
    $columns = array_column($admin->query('SHOW COLUMNS FROM treninky')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('mereni', $columns, true)) {
        $admin->exec('ALTER TABLE treninky ADD mereni TEXT NULL');
    }
    $admin->exec('CREATE TABLE IF NOT EXISTS tagy(id INT PRIMARY KEY,nazev VARCHAR(100));
        CREATE TABLE IF NOT EXISTS trenink_tag(trenink_id INT,tag_id INT)');
    $admin->exec("INSERT IGNORE INTO tagy(id,nazev) VALUES(990001,'Synthetic bike'),(990002,'Synthetic road')");
    $admin->prepare('UPDATE treninky SET mereni=? WHERE id>=9000001 AND id<=9001000')
        ->execute([str_repeat('Synthetic measurement ', 30)]);
    $admin->beginTransaction();
    for ($i = 9000001; $i <= 9001000; $i++) {
        $admin->exec('INSERT INTO trenink_tag(trenink_id,tag_id) VALUES(' . $i . ',990001),(' . $i . ',990002)');
    }
    $admin->exec("INSERT INTO treninky(id,datum,napln,poznamka,delka,kategorie)
        VALUES(9100001,CURRENT_DATE,'ForeignOnlyNeedle','Synthetic foreign note',1.25,'mtb');
        INSERT INTO trenink_trener(trenink_id,trener_id) VALUES(9100001,8)");
    $admin->commit();
    exit;
}
if (!in_array(strtoupper((string)$admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('HTTP SQL result cache must be OFF.');
}
$checksums = static function (PDO $pdo): array {
    $r = [];
    foreach (['treninky', 'trenink_trener', 'trenink_sportovec', 'training_roster_links', 'club_team_edges',
        'trenink_skupina', 'trenink_podskupina', 'trenink_tag'] as $table) {
        $row = $pdo->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC);
        $r[$table] = $row['Checksum'];
    }
    return $r;
};
$checksum = $checksums($admin);
$cases = [];
foreach (['all' => [], 'group' => ['skupina_id' => 1], 'team' => ['team_id' => 1],
    'year' => ['rok' => date('Y')], 'month_without_year' => ['mesic' => date('n')],
    'year_month' => ['rok' => date('Y'), 'mesic' => date('n')],
    'search' => ['q' => 'Synthetic history 1'], 'wildcard' => ['q' => '_'],
    'rare' => ['q' => 'Synthetic history 995'], 'empty' => ['q' => 'NoSuchCoachNeedle'],
    'page2' => ['history_page' => 2], 'last' => ['history_page' => 1000000],
    'foreign' => ['q' => 'ForeignOnlyNeedle', 'trener_id' => 8]] as $name => $params) {
    $cases[] = ['id' => $name, 'path' => '/ajax_treninky.php' . ($params ? '?' . http_build_query($params) : ''),
        'role' => 'coach', 'status' => 200, 'page' => $params['history_page'] ?? 1,
        'expected_total' => in_array($name, ['empty', 'foreign'], true) ? 0 : ($name === 'rare' ? 1 : null)];
}
$cases[] = ['id' => 'other_coach', 'path' => '/ajax_treninky.php?q=ForeignOnlyNeedle', 'role' => 'other_coach',
    'status' => 200, 'page' => 1, 'expected_total' => 1];
$cases[] = ['id' => 'registrar_denied', 'path' => '/ajax_treninky.php', 'role' => 'registrar',
    'status' => 403, 'page' => 1, 'expected_total' => null];
$cases[] = ['id' => 'anonymous_denied', 'path' => '/ajax_treninky.php?history_page=2', 'role' => 'guest',
    'status' => 403, 'page' => 1, 'expected_total' => null];
file_put_contents($output . '/matrix.json', json_encode($cases, JSON_THROW_ON_ERROR));
$previous = $phase === 'after' ? json_decode(file_get_contents($output . '/history-http-before.json'), true, 512, JSON_THROW_ON_ERROR) : null;
$report = ['complete' => false, 'phase' => $phase, 'checksums_before' => $checksum, 'cases' => []];
$domain = static function (string $html): array {
    $doc = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $xp = new DOMXPath($doc);
    // The explicit revocation test rotates the session between phases. Compare the
    // same CSRF field/action contract while excluding the ephemeral token value.
    foreach ($xp->query('//input[@name="csrf_token"]') as $input) {
        if ($input->getAttribute('value') === '') {
            throw new RuntimeException('Missing synthetic CSRF value.');
        }
        $input->setAttribute('value', 'SYNTHETIC_CSRF');
    }
    $rows = $dates = $order = $parts = [];
    foreach ($xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," accordion-item ")]') as $card) {
        $button = $xp->query('.//button[@aria-controls]', $card)->item(0);
        if (!$button || !preg_match('/^c([0-9]+)$/D', $button->getAttribute('aria-controls'), $match)) {
            throw new RuntimeException('Missing training card identity.');
        }
        $id = (int)$match[1];
        $order[] = $id;
        $dates[$id] = trim($xp->evaluate('string(.//strong[1])', $button));
        foreach ($xp->query('.//text()', $card) as $text) {
            $text->nodeValue = preg_replace('/\s+/u', ' ', trim($text->nodeValue));
        }
        $rows[$id] = hash('sha256', $doc->saveHTML($card));
        $parts[$id] = [];
        foreach ($xp->query('.//div[contains(concat(" ",normalize-space(@class)," ")," kv ")]', $card) as $section) {
            $label = trim($xp->evaluate('string(./div[1])', $section));
            $parts[$id][$label] = hash('sha256', $doc->saveHTML($section));
        }
    }
    $meta = $xp->query('//*[@data-coach-history]')->item(0);
    return ['rows' => $rows, 'order' => $order, 'dates' => $dates, 'parts' => $parts,
        'total' => $meta ? (int)$meta->getAttribute('data-history-total') : count($order),
        'page' => $meta ? (int)$meta->getAttribute('data-history-page') : 1,
        'pager' => $xp->query('//nav[@aria-label="Stránkování tréninků"]')->length];
};
foreach ($cases as $i => $case) {
    $samples = [];
    $signature = null;
    $positive = $case['status'] === 200;
    for ($n = 0; $n < ($positive ? 22 : 1); $n++) {
        $r = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if ($r['status'] !== $case['status'] || preg_match('/Fatal error|Warning:/', $r['body'])) {
            throw new RuntimeException('Coach history HTTP failed: ' . $case['id'] . ', status ' . $r['status']);
        }
        if ($n === 0 && $positive) {
            $signature = $domain($r['body']);
            if ($case['expected_total'] !== null && $signature['total'] !== $case['expected_total']) {
                throw new RuntimeException('Wrong scoped result count: ' . $case['id'] . ':' . $signature['total']);
            }
            if ($phase === 'after') {
                $old = $previous['cases'][$i]['domain'];
                $expected = $old['order'];
                usort($expected, static fn (int $a, int $b): int => strcmp($old['dates'][$b], $old['dates'][$a]) ?: ($b <=> $a));
                $page = min(max(1, (int)$case['page']), max(1, (int)ceil(count($expected) / 25)));
                $expected = array_slice($expected, ($page - 1) * 25, 25);
                foreach ($signature['rows'] as $id => $hash) {
                    if (($old['rows'][$id] ?? null) !== $hash) {
                        throw new RuntimeException('Coach card content differs: ' . $case['id'] . ':' . $id . ', sections: '
                            . json_encode(array_keys(array_diff_assoc($signature['parts'][$id], $old['parts'][$id] ?? [])), JSON_THROW_ON_ERROR));
                    }
                }
                if ($signature['order'] !== $expected || $signature['total'] !== $old['total']
                    || $signature['page'] !== $page || $signature['pager'] !== 1) {
                    throw new RuntimeException('Coach history page, order, total or navigation differs: ' . $case['id']);
                }
            }
        }
        if ($positive && $n > 0 && $domain($r['body']) !== $signature) {
            throw new RuntimeException('Unstable coach history domain within phase.');
        }
        if (!$positive || $n >= 5) {
            $samples[] = array_intersect_key($r, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
        }
    }
    $report['cases'][] = $case + ['domain' => $signature, 'samples' => $samples];
}
// Same existing authenticated session must lose access immediately after revocation.
$admin->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
try {
    $r = fullHttpRequest($runtime, $output, 'coach', '/ajax_treninky.php');
    $rejected = in_array($r['status'], [302, 303, 401, 403], true)
        || ($r['status'] === 200 && $domain($r['body'])['rows'] === []);
    if (!$rejected) {
        throw new RuntimeException('Revoked coach retained history access.');
    }
    $report['revocation'] = ['status' => $r['status'], 'no_training_cards' => true];
} finally {
    $admin->exec('UPDATE treneri SET aktivni=1 WHERE id=7');
    $credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
    fullHttpLogin($runtime, $output, 'coach', $credentials['coach']);
}
if ($previous && $report['revocation'] !== $previous['revocation']) {
    throw new RuntimeException('Revocation response changed.');
}
if ($checksums($admin) !== $checksum || ($previous && $previous['checksums_before'] !== $checksum)) {
    throw new RuntimeException('HTTP changed owned domain data.');
}
$report['complete'] = true;
$report['data_unchanged'] = true;
file_put_contents($output . '/history-http-' . $phase . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Coach history ' . $phase . ' HTTP complete' . PHP_EOL;
