<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$phase = $argv[2] ?? '';
if ($output === false || !in_array($phase, ['prepare','before','after'], true)) {
    throw new RuntimeException('Owned output and phase required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime,$admin,$identity] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($phase === 'prepare') {
    $admin->exec('SET GLOBAL query_cache_type=OFF');
    $columns = array_column($admin->query('SHOW COLUMNS FROM skupiny')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('poradi', $columns, true)) {
        $admin->exec('ALTER TABLE skupiny ADD poradi INT DEFAULT 0');
    }
    $trainingColumns = array_column($admin->query('SHOW COLUMNS FROM treninky')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    foreach (['poznamka', 'obrazky'] as $column) {
        if (!in_array($column, $trainingColumns, true)) {
            $admin->exec('ALTER TABLE treninky ADD ' . $column . ' TEXT NULL');
        }
    }
    $admin->exec('CREATE TABLE IF NOT EXISTS trenink_skupina(trenink_id INT,skupina_id INT);
        CREATE TABLE IF NOT EXISTS trenink_podskupina(trenink_id INT,podskupina_id INT)');
    $admin->exec("INSERT INTO opravneni(klic,min_role) VALUES('sprava_treninku','hlavni')
        ON DUPLICATE KEY UPDATE min_role=VALUES(min_role)");
    $admin->beginTransaction();
    $training = $admin->prepare('INSERT INTO treninky(id,datum,napln,poznamka,obrazky,kategorie,delka) VALUES(?,?,?,?,?,?,?)');
    $coach = $admin->prepare('INSERT INTO trenink_trener(trenink_id,trener_id) VALUES(?,?)');
    $person = $admin->prepare('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) VALUES(?,?)');
    $group = $admin->prepare('INSERT INTO trenink_skupina(trenink_id,skupina_id) VALUES(?,1)');
    $link = $admin->prepare("INSERT INTO training_roster_links(trenink_id,team_id,target_date,team_code_snapshot,
        team_name_snapshot,created_by_trainer_id) VALUES(?,2,?,'HTTP-CHILD','LOCALHOST child roster',7)");
    for ($i = 1; $i <= 1000; $i++) {
        $id = 9000000 + $i;
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $training->execute([$id,$date,'Synthetic history ' . $i,str_repeat('Synthetic history note ', 100),'',$i % 2 ? 'mtb' : 'silnice',1.25]);
        $coach->execute([$id,7]);
        $coach->execute([$id,8]);
        $group->execute([$id]);
        $link->execute([$id,$date]);
        foreach (range(1001, 1012) as $sportovec) {
            $person->execute([$id,$sportovec]);
        }
    }
    $admin->commit();
    exit;
}
if (!in_array(strtoupper((string)$admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF','0'], true)) {
    throw new RuntimeException('HTTP SQL result cache must be OFF.');
}
$checksums = static function (PDO $pdo): array {
    $r = [];
    foreach (['treninky','trenink_trener','trenink_sportovec','training_roster_links','club_team_edges'] as $table) {
        $row = $pdo->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC);
        $r[$table] = $row['Checksum'];
    }
    return $r;
};
$checksum = $checksums($admin);
$cases = [];
foreach (['sprava_treninku.php','sprava_vsech_treninku.php'] as $route) {
    foreach (['all' => [], 'group' => ['skupina_id' => 1], 'team' => ['team_id' => 1],
        'trainer' => ['trenere' => [7]], 'page2' => ['history_page' => 2]] as $name => $params) {
        $cases[] = ['id' => $route . '-' . $name,'path' => '/' . $route . ($params ? '?' . http_build_query($params) : ''),
            'role' => 'sports_lead','status' => 200,'page' => $name === 'page2' ? 2 : 1];
    }
}
foreach (['rare' => 'Synthetic history 995','empty' => 'NoSuchHistoryNeedle'] as $name => $q) {
    $cases[] = ['id' => 'rich-' . $name,'path' => '/sprava_vsech_treninku.php?' . http_build_query(['q' => $q]),
        'role' => 'sports_lead','status' => 200,'page' => 1];
}
foreach (['coach','registrar','guest'] as $role) {
    foreach (['sprava_treninku.php','sprava_vsech_treninku.php'] as $route) {
        $cases[] = ['id' => $route . '-denied-' . $role,'path' => '/' . $route,'role' => $role,'status' => 403,'page' => 1];
    }
}
file_put_contents($output . '/matrix.json', json_encode($cases, JSON_THROW_ON_ERROR));
$previous = $phase === 'after' ? json_decode(file_get_contents($output . '/history-http-before.json'), true, 512, JSON_THROW_ON_ERROR) : null;
$report = ['complete' => false,'phase' => $phase,'checksums_before' => $checksum,'cases' => []];
$domain = static function (string $html): array {
    $doc = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($old);
    $xp = new DOMXPath($doc);
    foreach ($xp->query('//input[@name="csrf_token"]') as $input) {
        $input->setAttribute('value', 'SYNTHETIC_CSRF');
    }
    $rows = [];
    foreach ($xp->query('//tr[starts-with(@id,"detail")]') as $detail) {
        $id = (int) substr($detail->getAttribute('id'), 6);
        $row = $detail->previousSibling;
        while ($row && $row->nodeType !== XML_ELEMENT_NODE) {
            $row = $row->previousSibling;
        }
        $parts = [];
        foreach ([$row,$detail] as $node) {
            if (!$node) {
                throw new RuntimeException('Missing history row.');
            }
            foreach ($xp->query('.//text()', $node) as $text) {
                $text->nodeValue = preg_replace('/\s+/u', ' ', trim($text->nodeValue));
            }
            $parts[] = $doc->saveHTML($node);
        }
        $rows[$id] = hash('sha256', implode('', $parts));
    }
    $stats = $xp->query('//div[contains(@class,"row g-3 mb-3")]')->item(0);
    return ['rows' => $rows,'stats' => $stats ? preg_replace('/\s+/u', ' ', trim($stats->textContent)) : null,
        'csrf_count' => $xp->query('//input[@name="csrf_token"]')->length,
        'pager' => $xp->query('//nav[@aria-label="Stránkování tréninků"]')->length];
};
foreach ($cases as $i => $case) {
    $samples = [];
    $signature = null;
    for ($n = 0; $n < ($case['status'] === 200 ? 22 : 1); $n++) {
        $r = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if ($case['status'] !== 200) {
            if (!in_array($r['status'], [302,303,401,403], true)) {
                throw new RuntimeException('Unauthorized history response.');
            }
        } elseif ($r['status'] !== 200 || preg_match('/Fatal error|Warning:|Nepodařilo se načíst tréninky|Chyba při načítání tréninků/', $r['body'])) {
            throw new RuntimeException('History HTTP failed: ' . $case['id']);
        }
        if ($n === 0 && $case['status'] === 200) {
            $signature = $domain($r['body']);
            if ($phase === 'after') {
                $old = $previous['cases'][$i]['domain'];

                // Original same-day ordering was unspecified. Compare identities/content, unit tests enforce stable new order.
                foreach ($signature['rows'] as $id => $hash) {
                    if (($old['rows'][$id] ?? null) !== $hash) {
                        throw new RuntimeException('History row content differs: ' . $case['id'] . ':' . $id);
                    }
                }
                if (count($signature['rows']) !== min(25, max(0, count($old['rows']) - ($case['page'] - 1) * 25))
                    || $signature['stats'] !== $old['stats'] || $signature['pager'] !== 1) {
                    throw new RuntimeException('History count/stats/pager differs: ' . $case['id']);
                }
            }
        }
        if ($case['status'] === 200 && $n > 0 && $domain($r['body']) !== $signature) {
            throw new RuntimeException('Unstable history domain within phase.');
        }
        if ($case['status'] !== 200 || $n >= 5) {
            $samples[] = array_intersect_key($r, array_flip(['status','ttfb_ms','total_ms','bytes']));
        }
    }
    $report['cases'][] = $case + ['domain' => $signature,'samples' => $samples];
}
if ($checksums($admin) !== $checksum || ($previous && $previous['checksums_before'] !== $checksum)) {
    throw new RuntimeException('HTTP changed owned domain data.');
}
$report['complete'] = true;
$report['data_unchanged'] = true;
file_put_contents($output . '/history-http-' . $phase . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'History ' . $phase . ' HTTP complete' . PHP_EOL;
