<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function fullHttpEnvironment(string $output): array
{
    $runtime = json_decode((string)file_get_contents($output . '/runtime.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($runtime['synthetic_only'] ?? false) !== true || ($runtime['external_sends_disabled'] ?? false) !== true
        || !preg_match('/^p11_read_[a-f0-9]{16}$/D', $runtime['schema'])
        || $runtime['base_url'] !== 'http://127.0.0.1:' . $runtime['http_port']
        || hash_file('sha256', dirname(__DIR__, 2) . '/config.php') !== $runtime['config_sha256']) {
        throw new RuntimeException('Owned synthetic HTTP environment required.');
    }
    $admin = new PDO('mysql:host=127.0.0.1;port=' . $runtime['port'] . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $admin->query('SELECT @@port port,@@datadir directory,VERSION() version')->fetch(PDO::FETCH_ASSOC);
    if ((int)$identity['port'] !== $runtime['port'] || strtolower(rtrim(str_replace('\\', '/', $identity['directory']), '/')) !== strtolower($runtime['datadir'])) {
        throw new RuntimeException('Wrong owned database server.');
    }
    return [$runtime, $admin, $identity];
}

function fullHttpRequest(array $runtime, string $output, string $role, string $path, ?array $post = null, array $headers = []): array
{
    if (!preg_match('~^/[a-z0-9_./?=&%:+-]+$~iD', $path) || str_contains($path, '..')) {
        throw new RuntimeException('Invalid local request path.');
    }
    $cookie = $output . '/cookie-' . $role . '.txt';
    $curl = curl_init($runtime['base_url'] . $path);
    $responseHeaders = [];
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 60, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        }]);
    if ($post !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $body = curl_exec($curl);
    $info = curl_getinfo($curl);
    $error = curl_error($curl);
    curl_close($curl);
    if (!is_string($body)) {
        throw new RuntimeException('Local HTTP transport failed: ' . $error);
    }
    return ['status' => (int)$info['http_code'], 'ttfb_ms' => $info['starttransfer_time'] * 1000,
        'total_ms' => $info['total_time'] * 1000, 'bytes' => strlen($body), 'headers' => $responseHeaders, 'body' => $body];
}

function fullHttpLogin(array $runtime, string $output, string $role, array $credential): array
{
    $get = fullHttpRequest($runtime, $output, $role, '/login.php');
    if ($get['status'] !== 200 || !preg_match('/name="csrf_token"\s+value="([^"]+)"/', $get['body'], $token)) {
        file_put_contents($output . '/login-calibration.html', $get['body']);
        throw new RuntimeException('Login form unavailable for ' . $role . ': ' . $get['status']);
    }
    $post = fullHttpRequest($runtime, $output, $role, '/login.php', ['csrf_token' => $token[1],
        'jmeno' => $credential['login'], 'heslo' => $credential['password']]);
    if ($post['status'] !== 303) {
        file_put_contents($output . '/login-calibration.html', $post['body']);
        throw new RuntimeException('Standard login failed for ' . $role . ': ' . $post['status']);
    }
    return ['form_status' => $get['status'], 'post_status' => $post['status'], 'form_ms' => $get['total_ms'], 'post_ms' => $post['total_ms']];
}

function fullHttpNormalizedHtml(array $response): string
{
    $html = str_replace("\r\n", "\n", $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
    }
    require_once __DIR__ . '/BootstrapAssetComparison.php';
    return bootstrapAssetComparisonHtml($html);
}

function fullHttpMatrix(array $credentials): array
{
    $cases = [];
    $add = static function (string $id, string $role, string $path, int $status = 200) use (&$cases): void {
        $cases[] = compact('id', 'role', 'path', 'status');
    };
    $add('planner-coach-own', 'coach', '/planovac.php');
    $add('planner-coach-all', 'coach', '/planovac.php?jen_moje=0');
    $add('planner-coach-root', 'coach', '/planovac.php?team_id=1');
    $add('planner-coach-child', 'coach', '/planovac.php?team_id=2');
    $add('planner-coach-trainer', 'coach', '/planovac.php?jen_moje=0&trener_id=8');
    $add('planner-coach-group', 'coach', '/planovac.php?jen_moje=0&skupina_id=1');
    $add('planner-coach-empty', 'coach', '/planovac.php?team_id=999999');
    $add('planner-coach-previous', 'coach', '/planovac.php?datum=' . date('Y-m-d', strtotime('-7 days')));
    $add('planner-lead-own', 'sports_lead', '/planovac.php');
    $add('planner-lead-all', 'sports_lead', '/planovac.php?jen_moje=0');
    $add('planner-lead-root', 'sports_lead', '/planovac.php?jen_moje=0&team_id=1');
    $add('closure-coach-training', 'coach', '/training_closure_admin.php?id=101');
    $add('closure-coach-plan', 'coach', '/training_closure_admin.php?plan_id=50010');
    $add('closure-coach-search', 'coach', '/training_closure_admin.php?id=101&lesson_on=&lesson_q=Synthetic');
    $add('closure-coach-empty-search', 'coach', '/training_closure_admin.php?id=101&lesson_on=&lesson_q=NOTFOUND');
    $add('closure-coach-list', 'coach', '/training_closure_admin.php');
    $add('closure-lead-training', 'sports_lead', '/training_closure_admin.php?id=101');
    $add('closure-lead-foreign', 'sports_lead', '/training_closure_admin.php?id=102');
    $add('closure-lead-plan', 'sports_lead', '/training_closure_admin.php?plan_id=50011');
    $add('closure-other-own', 'other_coach', '/training_closure_admin.php?id=102');
    $add('closure-coach-foreign-denied', 'coach', '/training_closure_admin.php?id=102', 403);
    $add('closure-coach-foreign-plan-denied', 'coach', '/training_closure_admin.php?plan_id=50011', 403);
    foreach (['all' => '', 'valid' => '?medical_state=valid', 'pending' => '?medical_state=pending', 'revoked' => '?medical_state=revoked',
        'empty' => '?medical_state=expired', 'person' => '?person_id=10001', 'unknown-person' => '?person_id=999999',
        'page2' => '?medical_page=2', 'last-page' => '?medical_page=400', 'search' => '?medical_q=Synthetic',
        'picker' => '?medical_person_q=Synthetic&medical_person_page=2'] as $mode => $query) {
        $add('medical-registrar-' . $mode, 'registrar', '/medical_clearance_admin.php' . $query);
    }
    foreach (array_keys($credentials) as $role) {
        foreach (['planner' => '/planovac.php', 'closure' => '/training_closure_admin.php?id=101',
            'medical' => '/medical_clearance_admin.php'] as $route => $path) {
            $allowed = $route === 'medical' ? $role === 'registrar' : in_array($role, ['coach', 'sports_lead', 'other_coach'], true);
            if (!$allowed) {
                $add($route . '-' . $role . '-denied', $role, $path, 403);
            }
        }
    }
    foreach (['planner' => '/planovac.php', 'closure' => '/training_closure_admin.php?id=101',
        'medical' => '/medical_clearance_admin.php'] as $route => $path) {
        $add($route . '-anonymous', 'anonymous', $path, $route === 'medical' ? 403 : 302);
    }
    return $cases;
}

if (($argv[1] ?? '') === '--library') {
    return;
}
$mode = $argv[1] ?? '';
$output = realpath($argv[2] ?? '');
if (!in_array($mode, ['--login', '--probe', '--measure'], true) || $output === false) {
    throw new RuntimeException('--login/--probe/--measure OWN_OUTPUT_DIRECTORY required.');
}
[$runtime, $admin, $identity] = fullHttpEnvironment($output);
$credentials = json_decode((string)file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
if ($mode === '--login') {
    $logins = [];
    foreach ($credentials as $role => $credential) {
        $logins[$role] = fullHttpLogin($runtime, $output, $role, $credential);
    }
    file_put_contents($output . '/logins.json', json_encode($logins, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    echo 'Standard form logins complete: ' . count($logins) . "\n";
    exit;
}
$matrix = fullHttpMatrix($credentials);
file_put_contents($output . '/matrix.json', json_encode($matrix, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$report = ['complete' => false, 'server' => $identity, 'php' => PHP_VERSION, 'method' => 'curl full HTTP, no redirects, no SQL/PHP instrumentation',
    'warmups' => $mode === '--measure' ? 5 : 0, 'samples_per_positive_case' => $mode === '--measure' ? 30 : 1, 'cases' => []];
if ($mode === '--measure' && ($argv[3] ?? '') === '--resume') {
    $previous = json_decode((string)file_get_contents($output . '/http-timings.json'), true, 512, JSON_THROW_ON_ERROR);
    $byId = array_column($matrix, null, 'id');
    if ($previous['server'] !== $identity || $previous['php'] !== PHP_VERSION || $previous['samples_per_positive_case'] !== 30) {
        throw new RuntimeException('Cannot resume a different timing environment.');
    }
    foreach ($previous['cases'] as $old) {
        foreach (['id', 'role', 'path', 'status'] as $key) {
            if (($byId[$old['id']][$key] ?? null) !== $old[$key]) {
                throw new RuntimeException('Cannot resume altered cases.');
            }
        }
    }
    $report['cases'] = $previous['cases'];
}
$finished = array_column($report['cases'], 'id');
foreach ($matrix as $case) {
    if (in_array($case['id'], $finished, true)) {
        continue;
    }
    $samples = [];
    $hash = null;
    $repeat = $mode === '--measure' && $case['status'] === 200 ? 35 : 1;
    for ($i = 0; $i < $repeat; $i++) {
        $response = fullHttpRequest($runtime, $output, $case['role'], $case['path']);
        if ($response['status'] !== $case['status'] || preg_match('/Fatal error|Warning:|Uncaught .*Exception/', $response['body'])) {
            file_put_contents($output . '/case-calibration.html', $response['body']);
            throw new RuntimeException($case['id'] . ': expected ' . $case['status'] . ', got ' . $response['status']);
        }
        $normalized = fullHttpNormalizedHtml($response);
        $currentHash = hash('sha256', $normalized);
        if ($hash !== null && $currentHash !== $hash) {
            throw new RuntimeException('Unstable HTML: ' . $case['id']);
        }
        $hash = $currentHash;
        if ($case['status'] === 200 && (!str_contains($response['body'], 'app-staff-topbar') || !str_contains($response['body'], '/assets/vendor/bootstrap/'))) {
            throw new RuntimeException('Full authenticated navigation/assets missing: ' . $case['id']);
        }
        if ($mode !== '--measure' || $case['status'] !== 200 || $i >= 5) {
            $samples[] = array_intersect_key($response, array_flip(['status', 'ttfb_ms', 'total_ms', 'bytes']));
        }
    }
    $case['html_sha256'] = $hash;
    $case['samples'] = $samples;
    $report['cases'][] = $case;
    echo $case['id'] . ': ' . $case['status'] . "\n";
    file_put_contents($output . '/http-' . ($mode === '--probe' ? 'probe' : 'timings') . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}
$report['complete'] = true;
file_put_contents($output . '/http-' . ($mode === '--probe' ? 'probe' : 'timings') . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
