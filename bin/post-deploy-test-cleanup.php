<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirm = (string)getenv('KIS_POST_DEPLOY_CLEANUP_CONFIRM');
$appRoot = realpath((string)getenv('APP_ROOT'));
$webUrl = rtrim((string)getenv('WEB_URL'), '/');
$settingsFile = realpath((string)getenv('KIS_POST_DEPLOY_SETTINGS_FILE'));
if ($confirm !== 'UKLID-5-6-1596-8' || $appRoot === false || $settingsFile === false || $webUrl === '') {
    fwrite(STDERR, "post_deploy_cleanup_refused\n");
    exit(2);
}

require $appRoot . '/config.php';
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
    if (!defined($constant)) {
        fwrite(STDERR, "post_deploy_cleanup_missing_database_configuration\n");
        exit(2);
    }
}

$settings = json_decode((string)file_get_contents($settingsFile), true);
if (!is_array($settings) || trim((string)($settings['email'] ?? '')) === '' || (string)($settings['password'] ?? '') === '') {
    fwrite(STDERR, "post_deploy_cleanup_invalid_settings\n");
    exit(2);
}

/** @return array{status:int,body:string} */
function cleanupHttp(string $url, string $cookieFile, string $method = 'GET', array $fields = []): array
{
    $handle = curl_init($url);
    if ($handle === false) throw new RuntimeException('HTTP klient nelze inicializovat.');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_USERAGENT => 'KIS post-deploy cleanup/1.0',
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
    $result = ['status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
    curl_close($handle);
    return $result;
}

function cleanupCsrf(string $html): string
{
    if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)["\']/i', $html, $match) !== 1) {
        throw new RuntimeException('CSRF token nebyl nalezen.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function cleanupPost(string $webUrl, string $cookieFile, string $page, string $endpoint, array $fields): void
{
    $form = cleanupHttp($webUrl . $page, $cookieFile);
    if ($form['status'] !== 200) throw new RuntimeException('Formulář úklidu není dostupný.');
    $fields['csrf_token'] = cleanupCsrf($form['body']);
    $response = cleanupHttp($webUrl . $endpoint, $cookieFile, 'POST', $fields);
    if ($response['status'] !== 200) throw new RuntimeException('Úklidový požadavek nebyl přijat.');
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$announcement = $pdo->query("SELECT id,nazev,datum FROM oznameni WHERE id=5")->fetch();
$plan = $pdo->query(
    "SELECT id,nazev,datum,cas_od,cas_do,stav,trenink_id,rezervace_id FROM planovane_treninky WHERE id=6"
)->fetch();
$trainingExists = (int)$pdo->query('SELECT COUNT(*) FROM treninky WHERE id=1596')->fetchColumn() === 1;
$reservation = $pdo->query(
    "SELECT id,datum,cas_od,cas_do,trenink_id FROM rezervace_sportovist WHERE id=8"
)->fetch();

$announcementOk = is_array($announcement)
    && (string)$announcement['nazev'] === 'POSTDEPLOY F1 – dočasný test'
    && (string)$announcement['datum'] === '2026-08-09';
$planOk = is_array($plan)
    && (string)$plan['nazev'] === 'POSTDEPLOY F8 – dočasný test'
    && (string)$plan['datum'] === '2026-08-17'
    && substr((string)$plan['cas_od'], 0, 5) === '06:00'
    && substr((string)$plan['cas_do'], 0, 5) === '07:00'
    && (string)$plan['stav'] === 'evidovany'
    && (int)$plan['trenink_id'] === 1596
    && (int)$plan['rezervace_id'] === 8;
$reservationOk = is_array($reservation)
    && (string)$reservation['datum'] === '2026-08-17'
    && substr((string)$reservation['cas_od'], 0, 5) === '06:00'
    && substr((string)$reservation['cas_do'], 0, 5) === '07:00'
    && (int)$reservation['trenink_id'] === 1596;
if (!$announcementOk || !$planOk || !$trainingExists || !$reservationOk) {
    fwrite(STDERR, "post_deploy_cleanup_precondition_failed\n");
    exit(3);
}

$cookieFile = tempnam(sys_get_temp_dir(), 'kis-post-deploy-cleanup-');
if (!is_string($cookieFile)) throw new RuntimeException('Cookie soubor nelze vytvořit.');
chmod($cookieFile, 0600);

try {
    $loginPage = cleanupHttp($webUrl . '/login.php', $cookieFile);
    $login = cleanupHttp($webUrl . '/login.php', $cookieFile, 'POST', [
        'csrf_token' => cleanupCsrf($loginPage['body']),
        'jmeno' => (string)$settings['email'],
        'heslo' => (string)$settings['password'],
    ]);
    if ($login['status'] !== 200 || !str_contains($login['body'], 'Odhlásit')) {
        throw new RuntimeException('Přihlášení pro úklid selhalo.');
    }

    cleanupPost(
        $webUrl,
        $cookieFile,
        '/kalendar_sportovist.php?datum=2026-08-17',
        '/kalendar_sportovist.php?datum=2026-08-17',
        ['action' => 'delete', 'rezervace_id' => '8']
    );
    cleanupPost(
        $webUrl,
        $cookieFile,
        '/sprava_vsech_treninku.php',
        '/smazat_trenink.php',
        ['trenink_id' => '1596']
    );
    cleanupPost(
        $webUrl,
        $cookieFile,
        '/planovac.php?datum=2026-08-17&jen_moje=1',
        '/planovac.php?datum=2026-08-17&jen_moje=1',
        ['action' => 'zrusit', 'plan_id' => '6']
    );
    cleanupPost(
        $webUrl,
        $cookieFile,
        '/oznameni.php?edit=5',
        '/oznameni.php?edit=5',
        ['delete_id' => '5']
    );

    $finalPlan = $pdo->query(
        "SELECT stav,trenink_id,rezervace_id FROM planovane_treninky WHERE id=6"
    )->fetch();
    $result = [
        'ok' => (int)$pdo->query('SELECT COUNT(*) FROM oznameni WHERE id=5')->fetchColumn() === 0
            && (int)$pdo->query('SELECT COUNT(*) FROM treninky WHERE id=1596')->fetchColumn() === 0
            && (int)$pdo->query('SELECT COUNT(*) FROM rezervace_sportovist WHERE id=8')->fetchColumn() === 0
            && is_array($finalPlan)
            && (string)$finalPlan['stav'] === 'zruseny'
            && $finalPlan['trenink_id'] === null
            && $finalPlan['rezervace_id'] === null,
        'announcement_deleted' => (int)$pdo->query('SELECT COUNT(*) FROM oznameni WHERE id=5')->fetchColumn() === 0,
        'training_deleted' => (int)$pdo->query('SELECT COUNT(*) FROM treninky WHERE id=1596')->fetchColumn() === 0,
        'reservation_deleted' => (int)$pdo->query('SELECT COUNT(*) FROM rezervace_sportovist WHERE id=8')->fetchColumn() === 0,
        'plan_cancelled' => is_array($finalPlan) && (string)$finalPlan['stav'] === 'zruseny',
        'plan_links_cleared' => is_array($finalPlan) && $finalPlan['trenink_id'] === null && $finalPlan['rezervace_id'] === null,
    ];
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "post_deploy_cleanup_failed\n");
    exit(1);
} finally {
    @unlink($cookieFile);
}
