<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/CatalogParityComparison.php';
$systemCurl = in_array('--system-curl', $argv, true);
$argv = array_values(array_filter($argv, static fn (string $value): bool => $value !== '--system-curl'));
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
    'transport' => $systemCurl ? 'system curl; same HTTPS GET and comparison' : 'PHP libcurl',
    'complete' => false, 'parity' => $reference === null ? 'BASELINE_ONLY' : 'PASS', 'cases' => []];
foreach ($cases as $id => $path) {
    if ($systemCurl) {
        // Explicit opt-in for an incompatible local libcurl transport. Keep the
        // same status/content checks, verified HTTPS and no cookies/redirects.
        $private = tempnam(sys_get_temp_dir(), 'kis-public-probe-');
        if ($private === false) {
            throw new RuntimeException('Cannot create private probe transport files.');
        }
        $headerFile = $private . '.headers';
        try {
            $process = proc_open(
                [PHP_OS_FAMILY === 'Windows' ? 'curl.exe' : 'curl', '--silent', '--show-error',
                '--compressed', '--connect-timeout', '10', '--max-time', '60', '--proto', '=https',
                '--dump-header', $headerFile, '--output', $private, '--write-out', '%{http_code} %{time_total}', $base . $path],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start system curl.');
            }
            $metadata = trim((string) stream_get_contents($pipes[1]));
            $error = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || !preg_match('/^([0-9]{3}) ([0-9.]+)$/D', $metadata, $match)) {
                throw new RuntimeException('System HTTPS transport failed: ' . $error);
            }
            $body = (string) file_get_contents($private);
            $info = ['http_code' => (int) $match[1], 'total_time' => (float) $match[2]];
            $headers = [];
            foreach (explode("\n", (string) file_get_contents($headerFile)) as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
            }
        } finally {
            unlink($private);
            if (is_file($headerFile)) {
                unlink($headerFile);
            }
        }
    } else {
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
    }
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
