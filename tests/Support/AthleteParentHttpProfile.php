<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '';
$size = (int) ($argv[3] ?? 1000);
if ($output === false || !in_array($mode, ['measure', 'diagnose'], true) || !in_array($size, [1000, 10000, 30000], true)) {
    throw new RuntimeException('Owned output, mode and size required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if (!in_array(strtoupper((string) $admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('SQL result cache must be OFF.');
}
$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$variants = getenv('ATHLETE_PARENT_COMPARE') === '1' ? ['before', 'after'] : ['before'];

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
    foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'] as $table) {
        $out[$table] = $admin->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $out;
};
$before = $checksums();
if ($mode === 'diagnose') {
    foreach ($variants as $variant) {
        parentSignature(fullHttpRequest(
            parentRuntime($runtime, $variant),
            $output,
            'parent-' . $variant,
            '/sportovec_treninky.php?id=1001',
            null,
            ['X-P11-Measure: parent-' . $variant]
        ));
    }
    exit;
}
$report = ['complete' => false, 'size' => $size, 'samples' => [], 'signatures' => []];
for ($i = -4; $i < 17; $i++) {
    foreach ($i % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $r = fullHttpRequest(parentRuntime($runtime, $variant), $output, 'parent-' . $variant, '/sportovec_treninky.php?id=1001');
        $signature = parentSignature($r);
        $report['signatures'][$variant] ??= $signature;
        if ($report['signatures'][$variant] !== $signature) {
            throw new RuntimeException('Unstable whole parent HTML.');
        }
        if ($i >= 0) {
            $report['samples'][$variant][] = ['ttfb_ms' => $r['ttfb_ms'], 'total_ms' => $r['total_ms'], 'bytes' => $r['bytes']];
        }
    }
}
if (count($variants) === 2 && $report['signatures']['before'] !== $report['signatures']['after']) {
    throw new RuntimeException('Whole parent HTML differs.');
}
if ($checksums() !== $before) {
    throw new RuntimeException('Parent changed domain data.');
}
$report['complete'] = true;
$report['domain_checksums_unchanged'] = true;
file_put_contents($output . '/parent-http-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Parent whole HTTP complete: ' . $size . PHP_EOL;
