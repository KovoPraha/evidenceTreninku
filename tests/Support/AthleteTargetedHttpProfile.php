<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '';
$size = (int) ($argv[3] ?? 1000);
if ($output === false || !in_array($mode, ['prepare', 'measure', 'diagnose', 'security'], true) || !in_array($size, [1000, 10000, 30000], true)) {
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
if ($mode === 'prepare') {
    if ((int) $admin->query('SELECT COUNT(*) FROM mereni_zaznamy WHERE sportovec_id=1012')->fetchColumn() !== 0) {
        throw new RuntimeException('Small measurement fixture must start empty.');
    }
    $measurement = $admin->prepare("INSERT INTO mereni_zaznamy(id,sportovec_id,typ,vzdalenost,distance_unit,cas,poznamka)
        VALUES(?,1012,?,1,'km','00:01:00','Synthetic small measurement')");
    $link = $admin->prepare('INSERT INTO trenink_mereni(trenink_id,mereni_id,poradi) VALUES(?,?,0)');
    $admin->beginTransaction();
    foreach (range(1, 12) as $number) {
        $measurement->execute([83000000 + $number, ['kolo', 'KOLO', 'kolo '][$number % 3]]);
        $link->execute([9000000 + $number, 83000000 + $number]);
    }
    $admin->exec("INSERT INTO treninky(id,datum,napln,poznamka,obrazky,kategorie,delka)
        VALUES(84000000,'1990-01-01','Synthetic old profile','','','mtb',1)");
    $oldMeasurement = $admin->prepare("INSERT INTO mereni_zaznamy(id,sportovec_id,typ,vzdalenost,distance_unit,cas,poznamka)
        VALUES(?,1013,'kolo',1,'km','00:01:00','Synthetic old measurement')");
    foreach (range(1, 900) as $number) {
        $oldMeasurement->execute([84000000 + $number]);
        $link->execute([84000000, 84000000 + $number]);
    }
    $admin->exec('DELETE FROM trenink_sportovec WHERE sportovec_id IN (1012,1020)');
    $admin->commit();
    echo 'Owned small/old measurement fixture: 12/900'  . PHP_EOL;
    exit;
}
// Bulk inserts into the minimal migrated fixture leave cardinalities at 1–2.
// Refresh statistics once for the shared owned dataset before either variant;
// application requests never ANALYZE or change indexes/settings.
$analysis = $admin->query('ANALYZE TABLE treninky,trenink_sportovec,trenink_mereni,mereni_zaznamy,sportovec_poznamka')->fetchAll(PDO::FETCH_ASSOC);
$statistics = ['shared_before_after_dataset' => true, 'analysis' => $analysis, 'tables' => []];
foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'] as $table) {
    $statistics['tables'][$table] = [
        'rows' => (int) $admin->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(),
        'indexes' => $admin->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC),
    ];
}
$statistics['profile_counts'] = [];
foreach ([1001, 1012, 1013, 1020] as $person) {
    $count = $admin->prepare('SELECT COUNT(*) FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
        JOIN treninky t ON t.id=tm.trenink_id WHERE mz.sportovec_id=?');
    $count->execute([$person]);
    $statistics['profile_counts'][$person] = (int) $count->fetchColumn();
}
if ($statistics['profile_counts'][1012] !== 12 || $statistics['profile_counts'][1013] !== 900 || $statistics['profile_counts'][1020] !== 0) {
    throw new RuntimeException('Expected a nonempty small measurement profile and an empty control.');
}
$statistics['history_profile_counts'] = [];
foreach ([1001, 1012, 1013, 1020] as $person) {
    $count = $admin->prepare('SELECT COUNT(*) FROM treninky t WHERE EXISTS
        (SELECT 1 FROM trenink_sportovec ts WHERE ts.trenink_id=t.id AND ts.sportovec_id=?)
        OR EXISTS (SELECT 1 FROM trenink_mereni tm JOIN mereni_zaznamy mz ON mz.id=tm.mereni_id
            WHERE tm.trenink_id=t.id AND mz.sportovec_id=?)');
    $count->execute([$person, $person]);
    $statistics['history_profile_counts'][$person] = (int) $count->fetchColumn();
}
if ($statistics['history_profile_counts'][1012] !== 12 || $statistics['history_profile_counts'][1020] !== 0) {
    throw new RuntimeException('Actual small/empty history controls required.');
}
file_put_contents($output . '/measurement-statistics-' . $size . '.json', json_encode($statistics, JSON_THROW_ON_ERROR));
$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$variants = getenv('ATHLETE_TARGETED_COMPARE') === '1' ? ['before', 'after'] : ['before'];

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
        foreach ([1001, 1012, 1013, 1020] as $person) {
            parentSignature(fullHttpRequest(
                $rt,
                $output,
                'parent-' . $variant,
                '/sportovec_treninky.php?id=' . $person,
                null,
                ['X-P11-Measure: targeted-' . $variant . '-parent-' . $person]
            ));
            foreach (['history' => 'rok=0', 'measurements' => 'view=measurements'] as $view => $query) {
                parentSignature(fullHttpRequest(
                    $rt,
                    $output,
                    'parent-' . $variant,
                    '/ajax_sportovec_treninky.php?' . $query,
                    null,
                    ['X-P11-Measure: targeted-' . $variant . '-' . $view . '-' . $person]
                ));
            }
        }
    }
    if ($checksums() !== $before) {
        throw new RuntimeException('Diagnosis changed canonical records.');
    }
    exit;
}
if ($mode === 'security') {
    require __DIR__ . '/AthleteHistorySecurity.php';
    exit;
}
$report = ['complete' => false, 'size' => $size, 'samples' => [], 'signatures' => [], 'controls' => [], 'control_signatures' => []];
$currentYear = (string) date('Y');
$cases = ['primary' => [1001, 'rok=' . $currentYear], 'all_history' => [1001, 'rok=0'],
    'year_page2' => [1001, 'rok=' . $currentYear . '&profile_page=2'],
    'year_last' => [1001, 'rok=' . $currentYear . '&profile_page=1000000'],
    'page2' => [1001, 'rok=0&profile_page=2'], 'last' => [1001, 'rok=0&profile_page=1000000'],
    'empty_year' => [1001, 'rok=2099'], 'small' => [1012, 'rok=' . $currentYear],
    'old' => [1013, 'rok=0'], 'empty' => [1020, 'rok=' . $currentYear],
    'mz_large' => [1001, 'view=measurements'], 'mz_small' => [1012, 'view=measurements'],
    'mz_old' => [1013, 'view=measurements'], 'mz_empty' => [1020, 'view=measurements']];
for ($i = -4; $i < 17; $i++) {
    foreach ($i % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $rt = parentRuntime($runtime, $variant);
        foreach ($cases as $case => [$person, $query]) {
            parentSignature(fullHttpRequest($rt, $output, 'parent-' . $variant, '/sportovec_treninky.php?id=' . $person));
            $r = fullHttpRequest($rt, $output, 'parent-' . $variant, '/ajax_sportovec_treninky.php?' . $query);
            $signature = parentSignature($r);
            $report['control_signatures'][$case][$variant] ??= $signature;
            if ($report['control_signatures'][$case][$variant] !== $signature) {
                throw new RuntimeException('Unstable whole targeted fragment.');
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
file_put_contents($output . '/targeted-http-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Targeted whole HTTP complete: ' . $size . PHP_EOL;
