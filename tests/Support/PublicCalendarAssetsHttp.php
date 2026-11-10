<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$mode = $argv[2] ?? '--compare';
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once __DIR__ . '/PublicCalendarAssetsComparison.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($mode === '--seed') {
    $add = static function (string $table, array $columns) use ($admin): void {
        $present = $admin->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($columns as $name => $type) {
            if (!in_array($name, $present, true)) {
                $admin->exec('ALTER TABLE ' . $table . ' ADD ' . $name . ' ' . $type);
            }
        }
    };
    $add('sportovist', ['kod' => 'VARCHAR(50) NULL', 'je_verejne' => 'INT DEFAULT 0', 'aktivni' => 'INT DEFAULT 1', 'poradi' => 'INT DEFAULT 0']);
    $add('individualni_lekce', ['trener_id' => 'INT NULL', 'typ' => "VARCHAR(30) DEFAULT 'zelena'", 'popis' => 'TEXT NULL',
        'slot_delka_min' => 'INT DEFAULT 60', 'max_osob' => 'INT DEFAULT 4', 'vyjimka_3_dny' => 'INT DEFAULT 0']);
    $admin->exec("INSERT INTO sportovist(id,nazev,kod,je_verejne,aktivni,poradi)
        VALUES(101,'LOCALHOST lesson','LOCALHOST-LESSON',1,1,1),(102,'LOCALHOST velodrome','velodrom',1,1,2)");
    $insert = $admin->prepare('INSERT INTO individualni_lekce(id,nazev,datum,cas_od,cas_do,cena_kc,sportoviste_id,trener_id,
        stav,booking_context,slot_delka_min,max_osob,typ,popis) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach (range(1, 4) as $i) {
        $insert->execute([900000 + $i, 'LOCALHOST slot ' . $i, date('Y-m-20'), sprintf('%02d:00:00', 9 + $i), sprintf('%02d:00:00', 10 + $i),
            $i % 2 ? 420 : 0, $i <= 2 ? 101 : 102, 7, 'aktivni', $i <= 2 ? 'individual_lesson' : 'public_velodrome', 30, 4, 'zelena', 'SYNTHETIC ONLY']);
    }
    exit;
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected owned table.');
        }
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$beforeData = $checksums();
$cases = ['calendar' => '/booking/kalendar.php', 'venue' => '/booking/kalendar.php?sportoviste_id=101',
    'empty-venue' => '/booking/kalendar.php?sportoviste_id=999999',
    'empty-month' => '/booking/kalendar.php?od=2099-01-01', 'invalid-month' => '/booking/kalendar.php?od=invalid',
    'velodrome' => '/booking/velodrom.php', 'control' => '/booking/klubovy_kalendar.php',
    'control-register' => '/booking/klubovy_kalendar.php?zobrazeni=register'];
$normalize = static function (array $response): string {
    $html = str_replace("\r\n", "\n", $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
    }
    return publicCalendarAssetsComparisonHtml($html);
};
$report = ['complete' => false, 'samples' => [], 'signatures' => [], 'anonymous_post_denials' => []];
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        foreach ($cases as $case => $path) {
            $response = fullHttpRequest($rt, $output, 'calendar-' . $variant, $path);
            $body = $response['body'];
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $body)) {
                file_put_contents($output . '/failure-private.html', $body);
                throw new RuntimeException('Calendar route failed: ' . $case . ':' . $response['status']);
            }
            $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $body);
            $cdn = substr_count($body, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
            if ($local !== 1 || $cdn !== ($variant === 'before' && !str_starts_with($case, 'control') ? 1 : 0)) {
                throw new RuntimeException('Unexpected CSS resources.');
            }
            $signature = hash('sha256', $normalize($response));
            $report['signatures'][$case] ??= $signature;
            if ($report['signatures'][$case] !== $signature) {
                file_put_contents($output . '/difference-' . $variant . '-private.html', $normalize($response));
                throw new RuntimeException('Whole HTML differs: ' . $case . ':' . $variant);
            }
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
        }
        if ($round === -4) {
            foreach (['reserve', 'add_paid_to_cart', 'cancel'] as $action) {
                $denied = fullHttpRequest(
                    $rt,
                    $output,
                    'calendar-' . $variant,
                    '/booking/velodrom.php',
                    ['action' => $action, 'lesson_id' => 900003, 'csrf_token' => 'invalid']
                );
                if ($denied['status'] !== 303 || $denied['headers']['location'] !== 'prihlaseni.php?redirect=velodrom.php') {
                    throw new RuntimeException('Anonymous reservation gate changed.');
                }
                $report['anonymous_post_denials'][$variant][$action] = $denied['status'];
            }
        }
    }
}
if ($checksums() !== $beforeData) {
    throw new RuntimeException('Calendar GET/denial changed canonical data.');
}
$report['tables_unchanged'] = count($beforeData);
$report['complete'] = true;
file_put_contents($output . '/calendar-http.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Calendar whole HTTP and anonymous denial parity PASS.\n";
