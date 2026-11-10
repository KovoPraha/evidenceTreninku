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
require_once __DIR__ . '/WorkspaceAssetsComparison.php';
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
        $cookie = $output . '/cookie-workspace-parent-' . $variant . '-' . $role . '.txt';
        if (is_file($cookie)) {
            unlink($cookie);
        }
        fullHttpLogin($rt, $output, 'workspace-parent-' . $variant . '-' . $role, $credentials[$role]);
    }
}
$checksums = static function () use ($admin): array {
    $result = [];
    foreach (['treninky', 'trenink_sportovec', 'trenink_trener', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka',
        'training_closures', 'training_closure_revisions', 'sports_event_participation',
        'sports_event_revisions', 'sports_activity_statements', 'sportovec_obdobi'] as $table) {
        $result[$table] = $admin->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $result;
};
$domainBefore = $checksums();
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? $variants : array_reverse($variants) as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
        $paths = ['workspace' => '/pracovni_pozice.php', 'mine' => '/moje_treninky.php',
            'training' => '/sprava_treninku.php', 'mine_ajax' => '/ajax_treninky.php?rok=' . date('Y') . '&mesic=' . date('n')];
        foreach ($paths as $case => $path) {
            $role = $case === 'training' ? 'sports_lead' : 'coach';
            $response = fullHttpRequest($rt, $output, 'workspace-parent-' . $variant . '-' . $role, $path);
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:/', $response['body'])) {
                throw new RuntimeException('Asset parent unavailable: ' . $case . ':' . $response['status']);
            }
            $raw = $response['body'];
            $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[0-9]+"~', $raw);
            $cdn = substr_count($raw, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
            $fragment = $case === 'mine_ajax';
            if ($local !== ($fragment ? 0 : 1) || $cdn !== 0) {
                throw new RuntimeException('Expected exact Bootstrap resource difference.');
            }
            $html = workspaceAssetsComparisonHtml(fullHttpNormalizedHtml($response));
            $csrf = [];
            preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $csrfFields);
            $csrf = $csrfFields[1];
            if (preg_match('/const CSRF_TOKEN\s*=\s*"([a-f0-9]{64})"/i', $html, $csrfConstant)) {
                $csrf[] = $csrfConstant[1];
            }
            $csrf = array_values(array_unique($csrf));
            if (count($csrf) > 1) {
                throw new RuntimeException('Inconsistent CSRF values within parent.');
            }
            if ($csrf !== []) {
                // The same per-session token is also embedded in push JSON.
                $html = str_replace($csrf[0], 'SYNTHETIC_CSRF', $html);
            }
            $html = preg_replace('/(const CSRF_TOKEN\s*=\s*)"[^"]*"/', '$1"SYNTHETIC_CSRF"', $html);
            $html = preg_replace('/(name="csrf_token"\s+value=")[^"]*(")/', '$1SYNTHETIC_CSRF$2', $html);
            $normalizedBodies[$case][$variant] = $html;
            $signature = ['status' => 200, 'bytes' => strlen($html), 'sha256' => hash('sha256', $html)];
            $report['signatures'][$case][$variant] ??= $signature;
            if ($signature !== $report['signatures'][$case][$variant]) {
                throw new RuntimeException('Unstable normalized parent HTML: ' . $case);
            }
            $report['raw_signatures'][$case][$variant] ??= [
                'bytes' => strlen($raw), 'sha256' => hash('sha256', $raw), 'local_bootstrap' => $local,
                'cdn_bootstrap' => $cdn, 'gzip_bytes' => strlen(gzencode($raw, 6)),
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
        $denied = fullHttpRequest($rt, $output, 'workspace-parent-' . $variant . '-' . $role, $path);
        if (!in_array($denied['status'], $mustDeny ? [302,303,401,403] : [200,302,303,401,403], true)) {
            throw new RuntimeException('Workspace boundary admitted a forbidden route: ' . $role . ':' . $denied['status']);
        }
        $report['workspace_boundaries'][$variant][$role . ':' . $path] = ['status' => $denied['status'], 'location' => $denied['headers']['location'] ?? null];
    }
}
file_put_contents($output . '/workspace-boundary-boundaries.json', json_encode($report['workspace_boundaries'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
if ($report['workspace_boundaries']['before'] !== $report['workspace_boundaries']['after']) {
    throw new RuntimeException('Workspace denial semantics changed.');
}
if ($report['raw_signatures']['workspace']['before']['bytes'] <= $report['raw_signatures']['workspace']['after']['bytes']) {
    throw new RuntimeException('Workspace duplicate CSS was not removed.');
}
if ($checksums() !== $domainBefore) {
    throw new RuntimeException('Read-only header comparison changed canonical data.');
}
$report['domain_checksums_unchanged'] = true;
$report['complete'] = true;
file_put_contents($output . '/workspace-parents-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Shared header parent HTML comparison complete: ' . $size . PHP_EOL;
