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
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected synthetic table name.');
        }
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$dataBefore = $checksums();
$cases = [
    'login' => '/booking/prihlaseni.php',
    'expired' => '/booking/prihlaseni.php?session=expired',
    'return' => '/booking/prihlaseni.php?redirect=eshop.php',
    'registration-family' => '/booking/registrace.php',
    'registration-shop' => '/booking/registrace.php?purpose=nakup',
    'registration-sport' => '/booking/registrace.php?purpose=sport',
    'athlete' => '/booking/sportovec_prihlaseni.php',
    'reset' => '/booking/zapomenute_heslo.php',
    'trainer-reset' => '/booking/zapomenute_heslo.php?pro=trener',
    'control' => '/booking/znovu_overit_email.php',
];
$normalize = static function (array $response): string {
    return (string) preg_replace('/(name="csrf_token"\s+value=")[^"]*(")/', '$1SYNTHETIC_CSRF$2', fullHttpNormalizedHtml($response));
};
$report = ['complete' => false, 'samples' => [], 'signatures' => [], 'csrf_denials' => [], 'raw' => []];
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        foreach ($cases as $case => $path) {
            $response = fullHttpRequest($rt, $output, 'account-' . $variant, $path);
            $body = $response['body'];
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $body)) {
                throw new RuntimeException('Account route failed: ' . $case . ':' . $response['status']);
            }
            $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=(?:[0-9]+|[a-f0-9]{64})"~', $body);
            $cdn = substr_count($body, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
            if ($local !== 1 || $cdn !== ($variant === 'before' && $case !== 'control' ? 1 : 0)) {
                throw new RuntimeException('Unexpected CSS resources: ' . $case . ':' . $variant);
            }
            $signature = hash('sha256', $normalize($response));
            $report['signatures'][$case][$variant] ??= $signature;
            if ($report['signatures'][$case][$variant] !== $signature) {
                throw new RuntimeException('Unstable whole HTML: ' . $case);
            }
            $report['raw'][$case][$variant] ??= ['bytes' => strlen($body), 'local_css' => $local, 'cdn_css' => $cdn];
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
            if ($round === -4 && in_array($case, ['login', 'registration-family', 'athlete', 'reset', 'trainer-reset'], true)) {
                $denied = fullHttpRequest($rt, $output, 'account-' . $variant, $path, ['csrf_token' => 'invalid']);
                if ($denied['status'] !== 200 || !str_contains($denied['body'], 'Neplatn')) {
                    throw new RuntimeException('Missing CSRF denial: ' . $case);
                }
                $report['csrf_denials'][$case][$variant] = ['status' => $denied['status'], 'html_sha256' => hash('sha256', $normalize($denied))];
            }
        }
    }
}
foreach (['signatures', 'csrf_denials'] as $key) {
    foreach ($report[$key] as $case => $pair) {
        if ($pair['before'] !== $pair['after']) {
            throw new RuntimeException('Whole response differs beyond exact CSS/nonce/CSRF allowance: ' . $case . ':' . $key);
        }
    }
}
if ($checksums() !== $dataBefore) {
    throw new RuntimeException('Account comparison changed canonical data.');
}
$report['tables_unchanged'] = count($dataBefore);
$report['complete'] = true;
file_put_contents($output . '/account-http.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Account whole HTTP, CSRF denial and table checksums PASS.\n";
