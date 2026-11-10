<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/CatalogParityComparison.php';
$base = rtrim($argv[1] ?? '', '/');
$target = $argv[2] ?? '';
$reference = isset($argv[3]) ? json_decode((string) file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR) : null;
if ($base !== 'https://kis.kovopraha.cz' || $target === '') {
    throw new RuntimeException('Explicit public production origin and output path required. GET only, no cookies.');
}
$cases = ['catalog' => '/booking/eshop.php', 'price' => '/booking/eshop.php?razeni=price',
    'page2' => '/booking/eshop.php?strana=2', 'search' => '/booking/eshop.php?q=dres',
    'empty' => '/booking/eshop.php?q=LOCALHOST_NOTFOUND_942718'];
$report = ['captured_utc' => gmdate('c'), 'method' => 'anonymous GET; full HTML and per-card hashes, explicit nonce/CSRF/five asset versions only',
    'complete' => false, 'parity' => $reference === null ? 'BASELINE_ONLY' : 'PASS', 'cases' => []];
foreach ($cases as $id => $path) {
    $curl = curl_init($base . $path);
    $headers = [];
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_ENCODING => '',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        }]);
    $body = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    if (!is_string($body) || $info['http_code'] !== 200 || preg_match('/Fatal error|Warning:/', $body)) {
        throw new RuntimeException('Public GET failed: ' . $id);
    }
    $sections = catalogParitySections(catalogParityHtml($body, $headers));
    $equal = $reference === null ? null : ($sections === ($reference['cases'][$id]['sections'] ?? null));
    $report['cases'][$id] = ['http' => 200, 'total_ms' => $info['total_time'] * 1000, 'sections' => $sections, 'equal' => $equal];
    if ($equal === false) {
        $report['parity'] = 'FAIL';
    }
}
if ($report['cases']['catalog']['sections']['cards'] === $report['cases']['page2']['sections']['cards']) {
    throw new RuntimeException('Actual catalog page 2 repeated page 1.');
}
$report['complete'] = $report['parity'] !== 'FAIL';
file_put_contents($target, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo 'Catalog probe: ' . $report['parity'] . "\n";
exit($report['complete'] ? 0 : 1);
