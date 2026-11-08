<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
$mode = $argv[2] ?? '';
$size = (int) ($argv[3] ?? 1000);
if ($output === false || !in_array($mode, ['prepare', 'measure', 'diagnose'], true)
    || !in_array($size, [1000, 10000, 30000], true)) {
    throw new RuntimeException('Owned output, mode and size required.');
}
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once dirname(__DIR__, 2) . '/includes/public_profile_token.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($mode === 'prepare') {
    $admin->exec('CREATE TABLE IF NOT EXISTS p11_profile_numbers(n INT PRIMARY KEY)');
    $max = max(1000, (int) $admin->query('SELECT COALESCE(MAX(n),0) FROM p11_profile_numbers')->fetchColumn());
    $admin->beginTransaction();
    for ($start = $max + 1; $start <= $size; $start += 500) {
        $values = array_map(static fn (int $n): string => '(' . $n . ')', range($start, min($start + 499, $size)));
        $admin->exec('INSERT INTO p11_profile_numbers VALUES' . implode(',', $values));
    }
    $condition = 'n>' . $max . ' AND n<=' . $size;
    $admin->exec("INSERT INTO treninky(id,datum,napln,poznamka,obrazky,kategorie,delka)
        SELECT 20000000+n,DATE_SUB(CURRENT_DATE,INTERVAL MOD(n,900) DAY),CONCAT('Synthetic extended ',n),'','','mtb',1.25
        FROM p11_profile_numbers WHERE " . $condition);
    $admin->exec('INSERT INTO trenink_sportovec(trenink_id,sportovec_id) SELECT 20000000+n,1001 FROM p11_profile_numbers WHERE ' . $condition);
    $admin->exec("INSERT INTO sportovec_poznamka(trenink_id,sportovec_id,poznamka)
        SELECT 20000000+n,1001,CONCAT('Synthetic extended note ',n) FROM p11_profile_numbers WHERE " . $condition);
    $digits = '(SELECT 0 j UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) digits';
    $admin->exec("INSERT INTO mereni_zaznamy(id,sportovec_id,typ,vzdalenost,distance_unit,cas,prevod,poznamka)
        SELECT 20000000+n*10+j,IF(j=9,1002,1001),ELT(MOD(n+j,9)+1,
        'kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb','KOLO','kolo ','unknown'),
        '10.25','km','00:30:00','50/14',REPEAT('Synthetic measurement <note> ',8)
        FROM p11_profile_numbers CROSS JOIN " . $digits . ' WHERE ' . $condition);
    $admin->exec('INSERT INTO trenink_mereni(trenink_id,mereni_id,poradi)
        SELECT 20000000+n,20000000+n*10+j,j FROM p11_profile_numbers CROSS JOIN ' . $digits . ' WHERE ' . $condition);
    $admin->commit();
    echo 'Owned synthetic profile trainings: ' . $size . PHP_EOL;
    exit;
}
if (!in_array(strtoupper((string) $admin->query('SELECT @@global.query_cache_type')->fetchColumn()), ['OFF', '0'], true)) {
    throw new RuntimeException('SQL cache must be OFF.');
}

function profileRuntime(array $runtime, string $variant): array
{
    $runtime['base_url'] = 'http://127.0.0.1:' . ($variant === 'before' ? 18099 : 18100);
    return $runtime;
}

function profileGet(array $runtime, string $output, string $variant, string $role, string $query = '', array $headers = []): array
{
    return fullHttpRequest(
        profileRuntime($runtime, $variant),
        $output,
        $variant . '-' . $role,
        '/ajax_sportovec_treninky.php?' . $query,
        null,
        $headers
    );
}

function profileSignature(array $r): array
{
    if ($r['status'] !== 200 || preg_match('/Fatal error|Warning:/', $r['body'])
        || str_contains($r['body'], 'Foreign hidden note') || preg_match('/class="[^"]*\bsave-note-btn\b/', $r['body'])) {
        throw new RuntimeException('Profile status/content/read-only contract changed: ' . $r['status']);
    }
    return ['status' => 200, 'bytes' => strlen($r['body']), 'body_sha256' => hash('sha256', $r['body'])];
}

function profileLogin(array $runtime, string $output, string $variant, string $role, array $credential): void
{
    $cookie = $output . '/cookie-' . $variant . '-' . $role . '.txt';
    if (is_file($cookie)) {
        unlink($cookie);
    }
    fullHttpLogin(profileRuntime($runtime, $variant), $output, $variant . '-' . $role, $credential);
}

function profileGrant(array $runtime, string $output, string $variant, string $role): void
{
    $r = fullHttpRequest(profileRuntime($runtime, $variant), $output, $variant . '-' . $role, '/sportovec_treninky.php?id=1001');
    if ($r['status'] !== 200) {
        throw new RuntimeException('Actual scoped profile grant failed: ' . $r['status']);
    }
}

function profilePair(array $runtime, string $output, string $variant, string $control, ?string $csrf = null, ?callable $observe = null): array
{
    $handles = [];
    $multi = curl_multi_init();
    $make = static function (string $role, string $path, ?array $post = null) use ($runtime, $output, $variant): CurlHandle {
        $cookie = $output . '/cookie-' . $variant . '-' . $role . '.txt';
        $c = curl_init(profileRuntime($runtime, $variant)['base_url'] . $path);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 120, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie]);
        if ($post !== null) {
            curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        }
        return $c;
    };
    $handles['long'] = $make('coach', '/ajax_sportovec_treninky.php?view=measurements');
    curl_multi_add_handle($multi, $handles['long']);
    $start = hrtime(true);
    $added = false;
    $observed = null;
    do {
        curl_multi_exec($multi, $running);
        // Request arrival offset only; no artificial SQL/application delay.
        if (!$added && (hrtime(true) - $start) >= 5e6) {
            $role = str_ends_with($control, 'different') ? 'parallel' : 'coach';
            $path = '/ajax_sportovec_treninky.php?rok=' . (str_starts_with($control, 'actual') ? date('Y') : '2099');
            $handles['short'] = $csrf === null ? $make($role, $path) : $make('coach', '/logout.php', ['csrf_token' => $csrf]);
            curl_multi_add_handle($multi, $handles['short']);
            $added = true;
            $running++;
        }
        if ($observe !== null && $observed === null) {
            $observed = $observe();
        }
        if ($running > 0) {
            curl_multi_select($multi, .001);
        }
    } while ($running > 0 || !$added);
    $out = ['observed' => $observed, 'pair_ms' => (hrtime(true) - $start) / 1e6];
    foreach ($handles as $name => $c) {
        $body = curl_multi_getcontent($c);
        $info = curl_getinfo($c);
        if (!is_string($body) || curl_errno($c)) {
            throw new RuntimeException('Owned concurrent HTTP transport failed.');
        }
        $r = ['status' => (int) $info['http_code'], 'body' => $body];
        if ($name === 'long' || $csrf === null) {
            $signature = profileSignature($r);
        } else {
            if ($r['status'] !== 303) {
                throw new RuntimeException('Canonical CSRF logout failed.');
            }
            $signature = ['status' => 303];
        }
        $out[$name] = $signature + ['total_ms' => $info['total_time'] * 1000, 'ttfb_ms' => $info['starttransfer_time'] * 1000];
        curl_multi_remove_handle($multi, $c);
        curl_close($c);
    }
    curl_multi_close($multi);
    return $out;
}

$credentials = json_decode(file_get_contents($output . '/credentials.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['before', 'after'] as $variant) {
    foreach (['coach', 'parallel'] as $role) {
        profileLogin($runtime, $output, $variant, $role, $credentials['coach']);
        profileGrant($runtime, $output, $variant, $role);
    }
}
$checksum = static function () use ($admin): array {
    $out = [];
    foreach (['treninky', 'trenink_sportovec', 'trenink_mereni', 'mereni_zaznamy', 'sportovec_poznamka'] as $table) {
        $out[$table] = $admin->query('CHECKSUM TABLE ' . $table . ' EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $out;
};
$checksums = $checksum();
if ($mode === 'diagnose') {
    $diagnostics = [];
    foreach (['before', 'after'] as $variant) {
        $cookie = file_get_contents($output . '/cookie-' . $variant . '-coach.txt');
        if (!preg_match('/\tEVIDENCESESSID\t([a-zA-Z0-9,-]+)\s*$/m', $cookie, $match)) {
            throw new RuntimeException('Owned session cookie required.');
        }
        $sessionFile = $runtime['runtime'] . '/sessions/sess_' . $match[1];
        $observe = static function () use ($admin, $runtime, $sessionFile): ?array {
            foreach ($admin->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $process) {
                if ($process['User'] !== $runtime['web_user'] || !str_contains((string) $process['Info'], 'ROW_NUMBER()')) {
                    continue;
                }
                $file = fopen($sessionFile, 'r');
                $available = flock($file, LOCK_EX | LOCK_NB);
                if ($available) {
                    flock($file, LOCK_UN);
                }
                fclose($file);
                return ['domain_sql_running' => true, 'session_lock_held' => !$available];
            }
            return null;
        };
        $pair = profilePair($runtime, $output, $variant, 'different', null, $observe);
        if ($pair['observed'] === null || $pair['observed']['session_lock_held'] !== ($variant === 'before')) {
            throw new RuntimeException('Expected lock mechanism not observed: ' . $variant);
        }
        $diagnostics[$variant] = $pair['observed'];
        profileSignature(profileGet($runtime, $output, $variant, 'coach', 'view=measurements', ['X-P11-Measure: profile-' . $variant]));
    }
    file_put_contents($output . '/profile-session-lock-diagnostics.json', json_encode($diagnostics, JSON_THROW_ON_ERROR));
    echo 'Actual running SQL and session lock observed independently.' . PHP_EOL;
    exit;
}
$report = ['complete' => false, 'size' => $size,
    'measurements' => (int) $admin->query('SELECT COUNT(*) FROM mereni_zaznamy WHERE sportovec_id=1001')->fetchColumn(),
    'queries' => [], 'controls' => [], 'single' => ['before' => [], 'after' => []]];
$queries = ['rok=0', 'rok=' . date('Y'), 'rok=2099', 'rok=0&profile_page=2', 'profile_page=1000000',
    'view=measurements', 'view=measurements&typ=KOLO', 'view=measurements&typ=kolo%20', 'view=measurements&typ=absent',
    'view=measurements&typ=unknown', 'view=measurements&profile_page=2', 'id=1002&sid=1002'];
foreach ($queries as $query) {
    $before = profileSignature(profileGet($runtime, $output, 'before', 'coach', $query));
    $after = profileSignature(profileGet($runtime, $output, 'after', 'coach', $query));
    if ($before !== $after) {
        throw new RuntimeException('Complete HTML fragment differs: ' . $query);
    }
    $report['queries'][] = ['query' => $query, 'signature' => $before, 'parity' => true];
}
for ($i = -4; $i < 17; $i++) {
    foreach ($i % 2 === 0 ? ['before', 'after'] : ['after', 'before'] as $variant) {
        $controls = ['same', 'different', 'actual_same', 'actual_different'];
        foreach ($i % 2 === 0 ? $controls : array_reverse($controls) as $control) {
            $pair = profilePair($runtime, $output, $variant, $control);
            unset($pair['observed']);
            $long = $report['queries'][5]['signature'];
            $short = $report['queries'][str_starts_with($control, 'actual') ? 1 : 2]['signature'];
            foreach (['long' => $long, 'short' => $short] as $name => $expected) {
                if (array_intersect_key($pair[$name], $expected) !== $expected) {
                    throw new RuntimeException('Concurrent response changed.');
                }
            }
            if ($i >= 0) {
                $report['controls'][$variant][$control][] = $pair;
            }
        }
        $r = profileGet($runtime, $output, $variant, 'coach', 'view=measurements');
        profileSignature($r);
        if ($i >= 0) {
            $report['single'][$variant][] = ['total_ms' => $r['total_ms'], 'ttfb_ms' => $r['ttfb_ms']];
        }
    }
}
foreach (['before', 'after'] as $variant) {
    foreach ($credentials as $role => $credential) {
        profileLogin($runtime, $output, $variant, $role, $credential);
        $r = fullHttpRequest(profileRuntime($runtime, $variant), $output, $variant . '-' . $role, '/sportovec_treninky.php?id=1001');
        $expected = in_array($role, ['coach', 'sports_lead', 'registrar'], true) ? 200 : 403;
        if ($r['status'] !== $expected) {
            throw new RuntimeException('Changed role grant: ' . $role . ':' . $r['status']);
        }
        $ajax = profileGet($runtime, $output, $variant, $role, 'view=measurements');
        if ($ajax['status'] !== $expected) {
            throw new RuntimeException('Changed AJAX role access: ' . $role);
        }
        $report['roles'][$variant][$role] = ['parent' => $r['status'], 'ajax' => $ajax['status']];
    }
    $token = public_profile_access_issue($admin, 1001, 7);
    $exchange = fullHttpRequest(
        profileRuntime($runtime, $variant),
        $output,
        $variant . '-bearer',
        '/sportovec_treninky.php?hash=' . $token['token']
    );
    if ($exchange['status'] !== 303 || ($exchange['headers']['location'] ?? '') !== 'sportovec_treninky.php') {
        throw new RuntimeException('Actual bearer exchange failed.');
    }
    foreach (['rok=0', 'view=measurements', 'view=measurements&id=1002&sid=1002'] as $query) {
        $sig = profileSignature(profileGet($runtime, $output, $variant, 'bearer', $query));
        $wanted = $report['queries'][$query === 'rok=0' ? 0 : 5]['signature'];
        if ($sig !== $wanted) {
            throw new RuntimeException('Bearer retargeted or changed read-only projection.');
        }
    }
    $admin->exec("UPDATE public_profile_access_tokens SET expires_at='2000-01-01' WHERE sportovec_id=1001");
    if (profileGet($runtime, $output, $variant, 'bearer')['status'] !== 403) {
        throw new RuntimeException('Expired bearer retained access.');
    }
    $admin->exec('UPDATE public_profile_access_tokens SET expires_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 DAY) WHERE sportovec_id=1001');
    fullHttpRequest(profileRuntime($runtime, $variant), $output, $variant . '-bearer', '/sportovec_treninky.php?hash=' . $token['token']);
    $admin->exec('UPDATE public_profile_access_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE sportovec_id=1001');
    if (profileGet($runtime, $output, $variant, 'bearer', 'view=measurements')['status'] !== 403) {
        throw new RuntimeException('Revoked bearer retained access.');
    }
    profileGrant($runtime, $output, $variant, 'coach');
    $admin->exec("UPDATE coach_scope_grants SET valid_to='2000-01-01' WHERE trainer_id=7 AND scope_kind='team' AND scope_id=2");
    try {
        if (profileGet($runtime, $output, $variant, 'coach')['status'] !== 403) {
            throw new RuntimeException('Expired scope retained access.');
        }
    } finally {
        $admin->exec("UPDATE coach_scope_grants SET valid_to=NULL WHERE trainer_id=7 AND scope_kind='team' AND scope_id=2");
    }
    profileGrant($runtime, $output, $variant, 'coach');
    $r = fullHttpRequest(profileRuntime($runtime, $variant), $output, $variant . '-coach', '/planovac.php');
    if ($r['status'] !== 200 || !preg_match('/name="csrf_token"\s+value="([^"]+)"/', $r['body'], $match)) {
        throw new RuntimeException('Actual CSRF form unavailable.');
    }
    $logout = profilePair($runtime, $output, $variant, 'same', $match[1]);
    $denied = profileGet($runtime, $output, $variant, 'coach');
    if ($denied['status'] !== 403) {
        throw new RuntimeException('Completed read resurrected logged-out session.');
    }
    $report['logout'][$variant] = ['logout_total_ms' => $logout['short']['total_ms'], 'read_total_ms' => $logout['long']['total_ms'],
        'csrf_accepted' => true, 'post_status' => $logout['short']['status'], 'subsequent_status' => 403];
    profileLogin($runtime, $output, $variant, 'coach', $credentials['coach']);
    profileGrant($runtime, $output, $variant, 'coach');
    $admin->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
    try {
        $status = profileGet($runtime, $output, $variant, 'coach')['status'];
        if (!in_array($status, [302, 303, 401, 403], true)) {
            throw new RuntimeException('Revoked account retained access.');
        }
        $report['revocation'][$variant] = $status;
    } finally {
        $admin->exec('UPDATE treneri SET aktivni=1 WHERE id=7');
    }
    foreach (['', 'view=measurements', 'id=1001&sid=1001'] as $query) {
        if (profileGet($runtime, $output, $variant, 'guest', $query)['status'] !== 403) {
            throw new RuntimeException('Guest retargeted profile.');
        }
    }
    $report['security'][$variant] = ['roles' => 12, 'bearer_303' => true, 'bearer_expiry_revocation' => true,
        'scope_expiry' => true, 'guest_denied' => true, 'profile_and_csrf_persisted' => true];
}
if ($report['roles']['before'] !== $report['roles']['after'] || $checksums !== $checksum()) {
    throw new RuntimeException('Role contract or domain data changed.');
}
$report['complete'] = true;
$report['domain_checksums_unchanged'] = true;
file_put_contents($output . '/profile-session-http-' . $size . '.json', json_encode($report, JSON_THROW_ON_ERROR));
echo 'Exact HTML, real authentication, concurrent reads and logout PASS: ' . $size . PHP_EOL;
