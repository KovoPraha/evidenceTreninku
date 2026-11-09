<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$size = (int) ($argv[2] ?? 0);
if ($output === false || !in_array($size, [1000,10000,30000], true)) {
    throw new RuntimeException('Owned output and size required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($size === 1000) {
    $issued = public_profile_access_issue($admin, 1001, 7);
    file_put_contents($output . '/bootstrap-browser-private.json', json_encode(['token' => $issued['token']], JSON_THROW_ON_ERROR));
}
$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$variants = ['before','after'];
$report = ['size' => $size, 'complete' => false, 'samples' => [], 'signatures' => [], 'raw_signatures' => []];
foreach ($variants as $variant) {
    $rt = $runtime;
    $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    fullHttpLogin($rt, $output, 'asset-parent-' . $variant, $credentials['coach']);
}
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        foreach (['profile' => '/sportovec_treninky.php?id=1001', 'mine' => '/moje_treninky.php', 'training' => '/sprava_treninku.php'] as $case => $path) {
            $response = fullHttpRequest($rt, $output, 'asset-parent-' . $variant, $path);
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $response['body'])) {
                throw new RuntimeException('Asset parent unavailable: ' . $case . ':' . $response['status']);
            }
            $raw = $response['body'];
            $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[0-9]+"~', $raw);
            $cdn = substr_count($raw, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
            if ($local !== 1 || $cdn !== ($variant === 'before' ? 1 : 0)) {
                throw new RuntimeException('Expected exact Bootstrap resource difference.');
            }
            $html = fullHttpNormalizedHtml($response);
            $html = preg_replace('/(const CSRF_TOKEN\s*=\s*)"[^"]*"/', '$1"SYNTHETIC_CSRF"', $html);
            $html = preg_replace('/(name="csrf_token"\s+value=")[^"]*(")/', '$1SYNTHETIC_CSRF$2', $html);
            $signature = ['status' => 200, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html)];
            $report['signatures'][$case][$variant] ??= $signature;
            if ($signature !== $report['signatures'][$case][$variant]) {
                throw new RuntimeException('Unstable normalized parent HTML: ' . $case);
            }
            $report['raw_signatures'][$case][$variant] ??= ['bytes' => strlen($raw), 'sha256' => hash('sha256', $raw), 'local_bootstrap' => $local, 'cdn_bootstrap' => $cdn];
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
        }
    }
}
foreach ($report['signatures'] as $case => $signatures) {
    if ($signatures['before'] !== $signatures['after']) {
        throw new RuntimeException('Parent HTML differs beyond allowed CSS link: ' . $case);
    }
}
$report['complete'] = true;
file_put_contents($output . '/asset-parents-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Exact asset parent HTML comparison complete: ' . $size . PHP_EOL;
