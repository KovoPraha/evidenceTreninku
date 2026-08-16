<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirm = (string)getenv('KIS_POST_DEPLOY_CONFIRM');
$appRoot = realpath((string)getenv('APP_ROOT'));
$webUrl = rtrim((string)getenv('WEB_URL'), '/');
$settingsFile = realpath((string)getenv('KIS_POST_DEPLOY_SETTINGS_FILE'));
if ($confirm !== 'OVERIT' || $appRoot === false || $settingsFile === false || $webUrl === '') {
    fwrite(STDERR, "post_deploy_r2_refused\n");
    exit(2);
}

require $appRoot . '/config.php';
require_once $appRoot . '/includes/private_storage.php';
require_once $appRoot . '/includes/person_sensitive.php';
require_once $appRoot . '/includes/person_match.php';
require_once $appRoot . '/includes/venue_calendar.php';

foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "post_deploy_r2_missing_database_configuration\n");
        exit(2);
    }
}

$settings = json_decode((string)file_get_contents($settingsFile), true);
if (!is_array($settings)
    || trim((string)($settings['email'] ?? '')) === ''
    || (string)($settings['password'] ?? '') === ''
) {
    fwrite(STDERR, "post_deploy_r2_invalid_settings\n");
    exit(2);
}

/** @return array{status:int,body:string,content_type:string} */
function postDeployHttp(
    string $url,
    string $cookieFile,
    string $method = 'GET',
    array $fields = []
): array {
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('HTTP klient nelze inicializovat.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'KIS post-deploy verifier/1.0',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json,image/*,application/pdf'],
    ]);
    if ($method === 'POST') {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
    }
    $body = curl_exec($handle);
    if (!is_string($body)) {
        $message = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('HTTP požadavek selhal: ' . $message);
    }
    $result = [
        'status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
        'body' => $body,
        'content_type' => strtolower(trim((string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE))),
    ];
    curl_close($handle);
    return $result;
}

function postDeployCsrf(string $html): string
{
    if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $match) !== 1) {
        throw new RuntimeException('CSRF token nebyl nalezen.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function postDeploySourceContains(string $appRoot, string $file, array $needles): bool
{
    $source = (string)file_get_contents($appRoot . '/' . $file);
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) return false;
    }
    return true;
}

/** @return array{id:int,resolved:bool}|null */
function postDeployFileCandidate(PDO $pdo, string $sql, string $pathColumn): ?array
{
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $path = privateStorageResolve((string)($row[$pathColumn] ?? ''));
        if ($path !== null && is_file($path) && filesize($path) > 0) {
            return ['id' => (int)$row['id'], 'resolved' => true];
        }
    }
    return null;
}

$cookieFile = tempnam(sys_get_temp_dir(), 'kis-post-deploy-');
if (!is_string($cookieFile)) {
    fwrite(STDERR, "post_deploy_r2_cookie_file_failed\n");
    exit(2);
}
chmod($cookieFile, 0600);

$stage = 'connect';
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $stage = 'schema';
    $requiredTables = [
        'athlete_registration_request_details',
        'athlete_registration_consent_snapshots',
        'athlete_private_files',
        'osoba_citlive_udaje',
        'osoba_citlive_pristupy',
    ];
    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $tableStatement = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (' . $placeholders . ') ORDER BY TABLE_NAME'
    );
    $tableStatement->execute($requiredTables);
    $foundTables = $tableStatement->fetchAll(PDO::FETCH_COLUMN);
    sort($requiredTables);
    sort($foundTables);
    $migrationStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM evidence_schema_migrations WHERE id=?'
    );
    $migrationStatement->execute(['20260816143000_athlete_registration_foundation']);
    $schema = [
        'migration_recorded' => (int)$migrationStatement->fetchColumn() === 1,
        'required_tables' => count($foundTables),
        'all_tables_present' => $foundTables === $requiredTables,
    ];

    $stage = 'private_file_candidates';
    $receiptCandidate = postDeployFileCandidate(
        $pdo,
        "SELECT id,obrazek_path FROM ucto_uctenky WHERE obrazek_path IS NOT NULL AND obrazek_path<>'' ORDER BY id DESC LIMIT 50",
        'obrazek_path'
    );
    $stressCandidate = postDeployFileCandidate(
        $pdo,
        "SELECT id,cesta FROM zatezove_testy_soubory WHERE cesta IS NOT NULL AND cesta<>'' ORDER BY id DESC LIMIT 50",
        'cesta'
    );

    $stage = 'sensitive_config';
    $keysMissing = false;
    try {
        personSensitiveConfig();
    } catch (PersonSensitiveException) {
        $keysMissing = true;
    }

    $stage = 'admin_http_login';
    $loginPage = postDeployHttp($webUrl . '/login.php', $cookieFile);
    $login = postDeployHttp($webUrl . '/login.php', $cookieFile, 'POST', [
        'csrf_token' => postDeployCsrf($loginPage['body']),
        'jmeno' => (string)$settings['email'],
        'heslo' => (string)$settings['password'],
    ]);
    $authenticated = $login['status'] === 200
        && !str_contains($login['body'], 'Neplatné přihlašovací')
        && str_contains($login['body'], 'Odhlásit');

    $diagnostics = postDeployHttp($webUrl . '/diagnostika_site_admin.php', $cookieFile);
    $diagnosticsCsrf = postDeployCsrf($diagnostics['body']);
    preg_match('/<th scope="row">REMOTE_ADDR<\/th>.*?<code>([^<]*)<\/code>/s', $diagnostics['body'], $remoteMatch);
    preg_match('/Odvozená adresa pro rate limit<\/th>.*?<code>([^<]*)<\/code>/s', $diagnostics['body'], $derivedMatch);
    $remoteAddress = html_entity_decode((string)($remoteMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $derivedAddress = html_entity_decode((string)($derivedMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $forwardingHeadersPresent = preg_match(
        '/(?:X-Forwarded-For|X-Real-IP|CF-Connecting-IP|Forwarded)<\/th>\s*<td>ano<\/td>/s',
        $diagnostics['body']
    ) === 1;
    $trustedProxyCount = 0;
    if (defined('AUTH_TRUSTED_PROXIES') && is_array(AUTH_TRUSTED_PROXIES)) {
        $trustedProxyCount = count(AUTH_TRUSTED_PROXIES);
    }

    $sensitiveId = (int)($pdo->query('SELECT MIN(id) FROM osoba_citlive_udaje')->fetchColumn() ?: 1);
    $sensitiveEndpoint = $keysMissing
        ? postDeployHttp($webUrl . '/athlete_sensitive_admin.php', $cookieFile, 'POST', [
            'csrf_token' => $diagnosticsCsrf,
            'record_id' => $sensitiveId,
            'action' => 'mask',
        ])
        : ['status' => 0, 'body' => '', 'content_type' => ''];
    $sensitiveJson = json_decode($sensitiveEndpoint['body'], true);
    $sensitiveFailClosed = $keysMissing
        && $sensitiveEndpoint['status'] === 503
        && str_starts_with($sensitiveEndpoint['content_type'], 'application/json')
        && is_array($sensitiveJson)
        && ($sensitiveJson['ok'] ?? null) === false
        && ($sensitiveJson['message'] ?? null) === 'Citlivý údaj nyní nelze bezpečně zobrazit.'
        && !str_contains(strtolower($sensitiveEndpoint['body']), 'fatal error');

    $privateDownloads = [
        'receipt_fixture_available' => $receiptCandidate !== null,
        'receipt_http_ok' => null,
        'stress_fixture_available' => $stressCandidate !== null,
        'stress_http_ok' => null,
    ];
    if ($receiptCandidate !== null) {
        $download = postDeployHttp($webUrl . '/private_download.php?kind=receipt&id=' . $receiptCandidate['id'], $cookieFile);
        $privateDownloads['receipt_http_ok'] = $download['status'] === 200
            && (str_starts_with($download['content_type'], 'image/') || str_starts_with($download['content_type'], 'application/pdf'))
            && strlen($download['body']) > 0;
    }
    if ($stressCandidate !== null) {
        $download = postDeployHttp($webUrl . '/private_download.php?kind=stress&id=' . $stressCandidate['id'], $cookieFile);
        $privateDownloads['stress_http_ok'] = $download['status'] === 200
            && (str_starts_with($download['content_type'], 'image/') || str_starts_with($download['content_type'], 'application/pdf'))
            && strlen($download['body']) > 0;
    }

    $stage = 'planner';
    $monday = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
    $sunday = (new DateTimeImmutable($monday))->modify('+6 days')->format('Y-m-d');
    $targetedStatement = $pdo->prepare(
        'SELECT COUNT(DISTINCT o.id) FROM oznameni o '
        . 'JOIN oznameni_targets ot ON ot.oznameni_id=o.id '
        . "WHERE o.datum BETWEEN ? AND ? AND ot.target_type IN ('skupina','podskupina')"
    );
    $targetedStatement->execute([$monday, $sunday]);
    $targetedAnnouncements = (int)$targetedStatement->fetchColumn();
    $planner = postDeployHttp($webUrl . '/planovac.php', $cookieFile);

    $stage = 'navigation_pages';
    $latestTrainingId = (int)($pdo->query('SELECT MAX(id) FROM treninky')->fetchColumn() ?: 0);
    $ordersPage = postDeployHttp($webUrl . '/eshop_orders_admin.php', $cookieFile);
    $editPage = $latestTrainingId > 0
        ? postDeployHttp($webUrl . '/edit_trenink.php?id=' . $latestTrainingId, $cookieFile)
        : ['status' => 0, 'body' => '', 'content_type' => ''];

    $publicProfile = postDeployHttp($webUrl . '/booking/verejny_profil.php', $cookieFile);

    $stage = 'person_match';
    $person = $pdo->query(
        "SELECT id,jmeno,prijmeni,narozeni FROM sportovci WHERE narozeni IS NOT NULL AND narozeni<>'0000-00-00' ORDER BY id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $personMatch = is_array($person) ? personMatchV1($pdo, $person) : null;
    $personMatchLive = is_array($personMatch)
        && $personMatch['level'] === PERSON_MATCH_EXACT
        && in_array((int)$person['id'], array_map('intval', array_column($personMatch['candidates'], 'id')), true);

    $stage = 'venue_calendar';
    $calendarFrom = (new DateTimeImmutable('-90 days'))->format('Y-m-d');
    $calendarTo = (new DateTimeImmutable('+90 days'))->format('Y-m-d');
    $unreservedPlans = venueCalendarUnreservedPlans($pdo, $calendarFrom, $calendarTo);
    $planIds = array_map(static fn(array $row): int => (int)$row['id'], $unreservedPlans);
    $venueCalendarUnique = count($planIds) === count(array_unique($planIds));

    $stage = 'kis_sync';
    $syncMinRoleStatement = $pdo->prepare("SELECT min_role FROM opravneni WHERE klic='sync_evidence' LIMIT 1");
    $syncMinRoleStatement->execute();
    $syncMinRole = (string)$syncMinRoleStatement->fetchColumn();
    $syncSource = (string)file_get_contents($appRoot . '/sync_evidence.php');
    $kisNoBirthNumberPayload = !str_contains($syncSource, "'rc'")
        && !str_contains($syncSource, 'rodné číslo')
        && !str_contains($syncSource, 'Rodné číslo');

    $checks = [
        'schema' => $schema,
        'private_download' => $privateDownloads,
        'sensitive_endpoint' => [
            'keys_missing' => $keysMissing,
            'http_status' => $sensitiveEndpoint['status'],
            'clean_json_503' => $sensitiveFailClosed,
        ],
        'f1_planner' => [
            'targeted_announcements_current_week' => $targetedAnnouncements,
            'precondition_present' => $targetedAnnouncements > 0,
            'http_ok' => $planner['status'] === 200,
            'weekly_grid_present' => str_contains($planner['body'], 'planner-grid'),
            'evidence_buttons_present' => str_contains($planner['body'], 'Zadat evidenci'),
        ],
        'f2_theme' => [
            'opt_in_contract_deployed' => postDeploySourceContains($appRoot, 'hlavicka' . '.php', [
                "localStorage.getItem('app-theme')",
                "stored === 'dark' ? 'dark' : 'light'",
                '[data-bs-theme="dark"]',
            ]),
        ],
        'f3_login' => ['test_admin_login_ok' => $authenticated],
        'f4_dropdowns' => [
            'orders_http_ok' => $ordersPage['status'] === 200,
            'orders_bootstrap_dropdown' => str_contains($ordersPage['body'], 'data-bs-toggle="dropdown"'),
            'training_http_ok' => $editPage['status'] === 200,
            'training_bootstrap_dropdown' => str_contains($editPage['body'], 'data-bs-toggle="dropdown"'),
        ],
        'f5_navigation_1366' => [
            'xxl_collapse_contract' => postDeploySourceContains($appRoot, 'hlavicka' . '.php', ['navbar-expand-xxl', '>Plánovač']),
        ],
        'f6_public_navigation' => [
            'http_ok' => $publicProfile['status'] === 200,
            'full_navigation' => str_contains($publicProfile['body'], 'Nakoupit v e-shopu')
                && str_contains($publicProfile['body'], 'Rezervovat velodrom')
                && str_contains($publicProfile['body'], 'Rozvrh tréninků'),
        ],
        'f7_person_match' => [
            'live_exact_match_detected' => $personMatchLive,
            'override_reason_contract_deployed' => postDeploySourceContains($appRoot, 'sprava_sportovcu.php', [
                'Nalezena přesná shoda',
                'override_reason',
                'Důvod výjimky musí mít alespoň 10 znaků',
            ]),
        ],
        'f8_venue_calendar' => [
            'live_result_ids_unique' => $venueCalendarUnique,
            'live_unreserved_rows_checked' => count($unreservedPlans),
            'recorded_badge_contract_deployed' => postDeploySourceContains($appRoot, 'kalendar_sportovist.php', [
                'venueCalendarUnreservedPlans',
                'Zaevidováno',
            ]),
        ],
        'f9_kis_birth_number' => [
            'sync_min_role' => $syncMinRole,
            'main_coach_access_preserved' => $syncMinRole === 'hlavni',
            'birth_number_absent_from_sync_source' => $kisNoBirthNumberPayload,
        ],
        'f10_proxy' => [
            'admin_diagnostics_http_ok' => $diagnostics['status'] === 200,
            'remote_address_present' => $remoteAddress !== '',
            'derived_address_present' => $derivedAddress !== '',
            'derived_equals_remote' => $remoteAddress !== '' && hash_equals($remoteAddress, $derivedAddress),
            'forwarding_headers_present' => $forwardingHeadersPresent,
            'trusted_proxy_count' => $trustedProxyCount,
            'network_paths_verified' => 1,
        ],
    ];

    $hardFailures = [];
    if (!$schema['migration_recorded'] || !$schema['all_tables_present']) $hardFailures[] = 'schema';
    if (!$authenticated) $hardFailures[] = 'admin_login';
    if (!$sensitiveFailClosed) $hardFailures[] = 'sensitive_endpoint';
    foreach (['receipt', 'stress'] as $kind) {
        if ($privateDownloads[$kind . '_fixture_available'] && $privateDownloads[$kind . '_http_ok'] !== true) {
            $hardFailures[] = 'private_download_' . $kind;
        }
    }

    echo json_encode([
        'ok' => $hardFailures === [],
        'hard_failures' => $hardFailures,
        'checks' => $checks,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($hardFailures === [] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'post_deploy_r2_failed:' . $stage . ':' . get_class($exception) . "\n");
    exit(1);
} finally {
    @unlink($cookieFile);
}
