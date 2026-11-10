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
require_once __DIR__ . '/SharedHeaderScriptComparison.php';
require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($size === 1000) {
    $issued = public_profile_access_issue($admin, 1001, 7);
    file_put_contents($output . '/bootstrap-browser-private.json', json_encode(['token' => $issued['token']], JSON_THROW_ON_ERROR));
}
$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
$variants = ['before','after'];
$normalizedBodies = [];
$report = ['size' => $size, 'complete' => false, 'samples' => [], 'signatures' => [], 'raw_signatures' => []];
foreach ($variants as $variant) {
    $rt = $runtime;
    $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    foreach (['coach','sports_lead'] as $role) {
        $cookie = $output . '/cookie-header-parent-' . $variant . '-' . $role . '.txt';
        if (is_file($cookie)) {
            unlink($cookie);
        }
        fullHttpLogin($rt, $output, 'header-parent-' . $variant . '-' . $role, $credentials[$role]);
    }
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected owned fixture table.');
        }
        $result[$table] = $admin->query('CHECKSUM TABLE `' . $table . '` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$domainBefore = $checksums();
$metadata = json_decode((string) file_get_contents(__DIR__ . '/SharedHeaderScriptReference.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        $paths = ['workspace' => '/pracovni_pozice.php', 'mine' => '/moje_treninky.php',
            'training' => '/sprava_treninku.php', 'mine_ajax' => '/ajax_treninky.php?rok=' . date('Y') . '&mesic=' . date('n')];
        foreach ($paths as $case => $path) {
            $role = $case === 'training' ? 'sports_lead' : 'coach';
            $response = fullHttpRequest($rt, $output, 'header-parent-' . $variant . '-' . $role, $path);
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $response['body'])) {
                throw new RuntimeException('Asset parent unavailable: ' . $case . ':' . $response['status']);
            }
            $raw = $response['body'];
            $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $raw);
            $cdn = substr_count($raw, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
            $fragment = $case === 'mine_ajax';
            if ($local !== ($fragment ? 0 : 1) || $cdn !== 0) {
                throw new RuntimeException('Expected exact Bootstrap resource difference.');
            }
            $bundles = preg_match_all('~src="/(?!/)[^"]*' . preg_quote($metadata['asset_path'], '~') . '"~', $raw);
            if ($bundles !== (!$fragment && $variant === 'after' ? 1 : 0)) {
                throw new RuntimeException('Unexpected staff bundle inclusion.');
            }
            $html = sharedHeaderScriptResponseHtml($response);
            $securityHeaders = [];
            foreach (['cache-control', 'content-type', 'content-security-policy', 'x-frame-options', 'x-content-type-options'] as $name) {
                $value = $response['headers'][$name] ?? null;
                $securityHeaders[$name] = is_string($value) ? preg_replace("/nonce-[^']+/", 'nonce-SYNTHETIC_NONCE', $value) : null;
            }
            $normalizedBodies[$case][$variant] = $html;
            $signature = ['status' => 200, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html), 'security_headers' => $securityHeaders];
            $report['signatures'][$case][$variant] ??= $signature;
            if ($signature !== $report['signatures'][$case][$variant]) {
                throw new RuntimeException('Unstable normalized parent HTML: ' . $case);
            }
            $report['raw_signatures'][$case][$variant] ??= [
                'bytes' => strlen($raw), 'sha256' => hash('sha256', $raw), 'local_bootstrap' => $local,
                'cdn_bootstrap' => $cdn, 'staff_bundles' => $bundles, 'gzip_bytes' => strlen(gzencode($raw, 6)),
            ];
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'], 'ttfb_ms' => $response['ttfb_ms']];
            }
        }
    }
}
foreach ($report['signatures'] as $case => $signatures) {
    if ($signatures['before'] !== $signatures['after']) {
        $beforeHtml = $normalizedBodies[$case]['before'];
        $afterHtml = $normalizedBodies[$case]['after'];
        $offset = 0;
        while ($offset < min(strlen($beforeHtml), strlen($afterHtml)) && $beforeHtml[$offset] === $afterHtml[$offset]) {
            $offset++;
        }
        $difference = ['case' => $case, 'offset' => $offset];
        foreach (['before' => $beforeHtml, 'after' => $afterHtml] as $variant => $body) {
            $snippet = substr($body, max(0, $offset - 80), 240);
            $snippet = preg_replace('/[a-f0-9]{32,}/i', '[OPAQUE]', $snippet);
            foreach ($credentials as $credential) {
                $snippet = str_replace([$credential['login'], $credential['password']], '[SYNTHETIC_ACCOUNT]', $snippet);
            }
            $difference[$variant] = $snippet;
        }
        file_put_contents($output . '/parent-difference.json', json_encode($difference, JSON_THROW_ON_ERROR));
        throw new RuntimeException('Parent HTML differs beyond allowed CSS link: ' . $case);
    }
}
foreach ($variants as $variant) {
    $rt = $runtime;
    $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    foreach ([['coach','/sprava_treninku.php',true],['sports_lead','/moje_treninky.php',true],
        ['anonymous','/moje_treninky.php',true],['anonymous','/sprava_treninku.php',true]] as [$role,$path,$mustDeny]) {
        $denied = fullHttpRequest($rt, $output, 'header-parent-' . $variant . '-' . $role, $path);
        if (!in_array($denied['status'], $mustDeny ? [302,303,401,403] : [200,302,303,401,403], true)) {
            throw new RuntimeException('Workspace boundary admitted a forbidden route: ' . $role . ':' . $denied['status']);
        }
        $report['workspace_boundaries'][$variant][$role . ':' . $path] = ['status' => $denied['status'], 'location' => $denied['headers']['location'] ?? null];
    }
}
file_put_contents($output . '/header-workspace-boundaries.json', json_encode($report['workspace_boundaries'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($report['workspace_boundaries']['before'] !== $report['workspace_boundaries']['after']) {
    throw new RuntimeException('Workspace denial semantics changed.');
}
foreach (['mine', 'training', 'workspace'] as $case) {
    if ($report['raw_signatures'][$case]['before']['bytes'] - $report['raw_signatures'][$case]['after']['bytes'] < 12000) {
        throw new RuntimeException('Required 12kB HTML reduction missing: ' . $case);
    }
}
if ($checksums() !== $domainBefore) {
    throw new RuntimeException('Read-only header comparison changed canonical data.');
}
$report['domain_checksums_unchanged'] = true;
$report['domain_table_count'] = count($domainBefore);
$report['complete'] = true;
file_put_contents($output . '/header-parents-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Shared header parent HTML comparison complete: ' . $size . PHP_EOL;
