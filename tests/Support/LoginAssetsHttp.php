<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('An owned fixture is required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';

[$runtime] = fullHttpEnvironment($output);
$variants = ['before' => $runtime, 'after' => ['base_url' => 'http://127.0.0.1:18100'] + $runtime];
$signatures = [];
$samples = [];
$signature = static function (array $response, string $variant, int $status): array {
    $raw = $response['body'];
    if ($response['status'] !== $status || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $raw)) {
        throw new RuntimeException('Unexpected login response status: ' . $variant . '/' . $response['status']);
    }
    $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $raw);
    $cdn = substr_count($raw, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    if ($local !== 1 || $cdn !== ($variant === 'before' ? 1 : 0)) {
        throw new RuntimeException('Unexpected login CSS links: ' . $variant);
    }
    $html = fullHttpNormalizedHtml($response);
    $csrf = '/(name="csrf_token"\s+value=")[a-f0-9]{64}(")/';
    $html = preg_replace($csrf, '$1SYNTHETIC_CSRF$2', $html, -1, $count);
    if ($count !== 1) {
        throw new RuntimeException('Expected one real CSRF token.');
    }
    $headers = array_intersect_key($response['headers'], array_flip([
        'content-type', 'cache-control', 'content-security-policy', 'x-frame-options', 'x-content-type-options',
    ]));
    $headers['content-security-policy'] = preg_replace("/nonce-[^']+/", 'nonce-SYNTHETIC_NONCE', $headers['content-security-policy']);
    if (!str_contains($headers['cache-control'] ?? '', 'no-store')) {
        throw new RuntimeException('Login response must remain no-store.');
    }
    return [hash('sha256', $html), $headers];
};
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? $variants : array_reverse($variants, true) as $variant => $rt) {
        $response = fullHttpRequest($rt, $output, 'signin-anonymous-' . $variant, '/login.php');
        $hash = $signature($response, $variant, 200);
        $signatures['form'] ??= $hash;
        if ($signatures['form'] !== $hash) {
            throw new RuntimeException('Login form or security headers differ.');
        }
        if ($round >= 0) {
            $samples['form'][$variant][] = array_intersect_key($response, array_flip(['status', 'bytes', 'ttfb_ms', 'total_ms']));
        }
    }
}

$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR)['coach'];
$controls = [];
foreach ($variants as $variant => $rt) {
    $role = 'signin-control-' . $variant;
    $bad = fullHttpRequest($rt, $output, $role, '/login.php', ['csrf_token' => 'invalid']);
    $signatures['csrf'] ??= $signature($bad, $variant, 400);
    if ($signatures['csrf'] !== $signature($bad, $variant, 400) || !str_contains($bad['body'], 'Přihlašovací formulář vypršel.')) {
        throw new RuntimeException('CSRF rejection differs.');
    }
    $get = fullHttpRequest($rt, $output, $role, '/login.php');
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $get['body'], $token)) {
        throw new RuntimeException('Login CSRF field missing.');
    }
    $empty = fullHttpRequest($rt, $output, $role, '/login.php', ['csrf_token' => $token[1], 'jmeno' => '', 'heslo' => '']);
    $signatures['empty'] ??= $signature($empty, $variant, 200);
    if ($signatures['empty'] !== $signature($empty, $variant, 200) || !str_contains($empty['body'], 'Vyplňte přihlašovací údaje')) {
        throw new RuntimeException('Empty form rejection differs.');
    }
    $authenticated = fullHttpRequest($rt, $output, $role, '/login.php', [
        'csrf_token' => $token[1], 'jmeno' => $credentials['login'], 'heslo' => $credentials['password'],
    ]);
    $redirect = fullHttpRequest($rt, $output, $role, '/login.php');
    $target = $authenticated['headers']['location'] ?? null;
    if ($authenticated['status'] !== 303 || $redirect['status'] !== 302 || $target === null || $target !== ($redirect['headers']['location'] ?? null)) {
        throw new RuntimeException('Authenticated return target differs.');
    }
    $controls[$variant] = ['valid_login' => 303, 'already_authenticated' => 302, 'location' => $target, 'csrf_rejected' => 400, 'empty_form' => 200];
}
if ($controls['before'] !== $controls['after']) {
    throw new RuntimeException('Login outcomes differ.');
}
file_put_contents($output . '/http.json', json_encode([
    'cases' => 5, 'signatures' => $signatures, 'samples' => $samples, 'controls' => $controls,
], JSON_THROW_ON_ERROR));
