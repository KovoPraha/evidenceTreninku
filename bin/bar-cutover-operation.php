<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function barCutoverReadPlan(string $file): array
{
    if (!is_file($file) || is_link($file) || filesize($file) > 32768) {
        throw new InvalidArgumentException('Chybí přiměřeně velký plán převodu.');
    }
    $plan = json_decode((string)file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($plan) || array_diff(array_keys($plan), ['prepare_run', 'bundle_sha256', 'release_sha', 'administrator_name', 'backup']) !== []
        || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)($plan['prepare_run'] ?? ''))
        || !is_string($plan['bundle_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $plan['bundle_sha256'])
        || !is_string($plan['release_sha'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $plan['release_sha'])
        || !is_string($plan['administrator_name'] ?? null) || !mb_check_encoding($plan['administrator_name'], 'UTF-8')
        || mb_strlen(trim($plan['administrator_name']), 'UTF-8') < 3 || mb_strlen($plan['administrator_name'], 'UTF-8') > 100
        || preg_match('/[\x00-\x1f\x7f]/', $plan['administrator_name'])) {
        throw new InvalidArgumentException('Neplatný konkrétní plán převodu.');
    }
    return $plan;
}

/** Names and fingerprints are explicit. The full protected restore run is verified by the workflow. */
function barCutoverVerifyBackup(array $proof, string $directory, ?int $existingBarProducts = null): array
{
    if (!preg_match('/^evidence_[0-9A-Za-z_-]+\.sql\.gz$/D', (string)($proof['sql_file'] ?? ''))
        || !preg_match('/^kis-files-[1-9][0-9]{0,19}\.zip$/D', (string)($proof['files_archive'] ?? ''))
        || !preg_match('/^[a-f0-9]{64}$/D', (string)($proof['sql_sha256'] ?? ''))
        || !preg_match('/^[a-f0-9]{64}$/D', (string)($proof['files_sha256'] ?? ''))
        || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)($proof['restore_run'] ?? ''))
        || $proof['files_archive'] !== 'kis-files-' . $proof['restore_run'] . '.zip') {
        throw new InvalidArgumentException('Chybí přesný bod obnovy.');
    }
    $sql = $directory . '/' . $proof['sql_file'];
    $files = $directory . '/files/' . $proof['files_archive'];
    foreach ([$sql, $files] as $path) {
        if (is_link($path) || !is_file($path) || filemtime($path) < time() - 3600) {
            throw new RuntimeException('Bod obnovy chybí nebo je starší než hodinu.');
        }
    }
    if (!hash_equals($proof['sql_sha256'], (string)hash_file('sha256', $sql))
        || !hash_equals($proof['files_sha256'], (string)hash_file('sha256', $files))) {
        throw new RuntimeException('Otisk bodu obnovy nesouhlasí.');
    }
    $manifest = json_decode((string)file_get_contents(substr($sql, 0, -7) . '.manifest.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($manifest['application'] ?? null) !== 'evidence' || ($manifest['sql_file'] ?? null) !== $proof['sql_file']
        || ($manifest['sha256'] ?? null) !== $proof['sql_sha256'] || !is_array($manifest['tables'] ?? null)
        || ($manifest['tables']['kis_bar_products'] ?? null) !== ($existingBarProducts ?? 0)
        || ($existingBarProducts === null
            ? ($manifest['tables']['kis_bar_import_runs'] ?? null) !== 0
            : ($existingBarProducts < 1 || ($manifest['tables']['kis_bar_import_runs'] ?? 0) < 1))) {
        throw new RuntimeException('Záloha neodpovídá očekávanému katalogu baru.');
    }
    return ['verified' => true, 'restore_run' => (string)$proof['restore_run'], 'sql_file' => $proof['sql_file'],
        'sql_sha256' => $proof['sql_sha256'], 'files_archive' => $proof['files_archive'], 'files_sha256' => $proof['files_sha256']];
}

function barCutoverOperationMain(): void
{
    umask(0077);
    $result = ['ok' => false, 'catalog_committed' => false, 'permissions_committed' => false, 'phase' => 'plan'];
    try {
        $mode = (string)getenv('BAR_CUTOVER_MODE');
        $plan = barCutoverReadPlan((string)getenv('BAR_CUTOVER_PLAN'));
        $confirmation = $mode === 'apply' ? 'PREVEST_BAR' : 'OVERIT';
        if (!in_array($mode, ['inspect', 'apply', 'status'], true) || getenv('BAR_CUTOVER_CONFIRM') !== $confirmation) {
            throw new InvalidArgumentException('Neplatná operace nebo potvrzení.');
        }
        $root = realpath((string)getenv('APP_ROOT'));
        $parent = realpath((string)getenv('BAR_PACKAGE_PARENT'));
        if ($root === false || $parent === false || getenv('APP_HOST') !== 'kis.kovopraha.cz') {
            throw new RuntimeException('Unexpected production paths.');
        }
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        ob_start();
        try {
            (static function (string $file): void {
                require_once $file;
            })($root . '/config.php');
        } finally {
            $output = ob_get_clean();
        }
        $config = get_defined_constants();
        if ($output !== '' || ($config['JE_LOKALNE'] ?? null) !== false
            || ($config['APP_BASE_URL'] ?? null) !== 'https://kis.kovopraha.cz') {
            throw new RuntimeException('Unexpected production identity.');
        }
        $deployment = json_decode((string)file_get_contents($root . '/var/deployment.json'), true, 16, JSON_THROW_ON_ERROR);
        if (($deployment['sha'] ?? null) !== $plan['release_sha']) {
            throw new RuntimeException('Unexpected deployed release.');
        }
        require_once $root . '/includes/bar/transfer_package.php';
        require_once is_file(__DIR__ . '/bar-transfer-verification.php')
            ? __DIR__ . '/bar-transfer-verification.php' : dirname(__DIR__) . '/includes/bar/transfer_verification.php';
        require_once is_file(__DIR__ . '/bar-cutover-permissions.php')
            ? __DIR__ . '/bar-cutover-permissions.php' : dirname(__DIR__) . '/includes/bar/cutover_permissions.php';
        require_once __DIR__ . '/bar-cutover-prepare.php';
        $pdo = new PDO(
            'mysql:host=' . $config['DB_HOST'] . ';dbname=' . $config['DB_NAME'] . ';charset=utf8mb4',
            $config['DB_USER'],
            $config['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $result['phase'] = 'package';
        $package = $parent . '/bar-transfer-' . $plan['prepare_run'];
        $file = $package . '/bundle.json';
        if (is_link($package) || !is_file($file) || is_link($file) || filesize($file) > 32 * 1024 * 1024) {
            throw new RuntimeException('Invalid private package.');
        }
        $bundle = barValidateTransferBundle(json_decode((string)file_get_contents($file), true, 32, JSON_THROW_ON_ERROR), $package . '/images');
        if (!hash_equals($plan['bundle_sha256'], $bundle['sha256'])) {
            throw new RuntimeException('Package differs from the rehearsed fingerprint.');
        }
        $result['bundle_sha256'] = $bundle['sha256'];
        $result['phase'] = 'identity';
        $administrator = barCutoverResolveAdministrator($pdo, $plan['administrator_name']);
        $result['administrator_id'] = $administrator['id'];
        $result['permission_state'] = barCutoverPermissionState($pdo, $administrator['id']);
        $result['permissions_committed'] = !in_array(false, $result['permission_state'], true);
        $_SESSION = ['trener_id' => $administrator['id'], 'trener_session_version' => $administrator['session_version'], 'staff_active_position' => 'system_admin'];
        barActor($pdo, 'transfer');
        $query = $pdo->prepare('SELECT trainer_id,summary_json FROM kis_bar_import_runs WHERE bundle_sha256=?');
        $query->execute([$bundle['sha256']]);
        $existing = $query->fetch(PDO::FETCH_ASSOC);
        $result['catalog_committed'] = (bool)$existing;
        if ($mode === 'status') {
            if (!$existing) {
                throw new RuntimeException('The selected transfer is not committed.');
            }
            $result['verification'] = barVerifyInitialTransfer($pdo, $bundle, $package . '/images');
        } elseif (!$existing) {
            $result['phase'] = 'fresh_source_and_target';
            putenv('BAR_PREPARE_RUN=' . $plan['prepare_run']);
            $preflight = barCutoverPrepare();
            if (!$preflight['ready_for_import'] || !hash_equals($plan['bundle_sha256'], $preflight['bundle_sha256'])) {
                throw new RuntimeException('Source or target is not ready.');
            }
            $result['preflight'] = $preflight;
            // These guards are checked before any write, including the account/role transaction.
            foreach (["SELECT COUNT(*) FROM staff_user_positions WHERE position_code='bar_admin' AND trainer_id<>?",
                "SELECT COUNT(*) FROM kis_bar_permissions WHERE permission_code='own_consumption' AND trainer_id<>?"] as $sql) {
                $query = $pdo->prepare($sql);
                $query->execute([$administrator['id']]);
                if ((int)$query->fetchColumn() !== 0) {
                    throw new RuntimeException('Another opening administrator or consumption grant exists.');
                }
            }
            if ($mode === 'apply') {
                $result['phase'] = 'backup';
                $result['backup'] = barCutoverVerifyBackup($plan['backup'] ?? [], $parent);
                $result['phase'] = 'import';
                $result['import'] = barImportTransfer($pdo, $bundle, $package . '/images');
                $result['catalog_committed'] = true;
                $result['phase'] = 'verify';
                $result['verification'] = barVerifyInitialTransfer($pdo, $bundle, $package . '/images');
            }
        } else {
            // An uncertain SSH response is resolved by status; a deliberate replay never reimports data.
            $result['import'] = json_decode($existing['summary_json'], true, 16, JSON_THROW_ON_ERROR) + ['repeated' => true];
            $result['verification'] = barVerifyInitialTransfer($pdo, $bundle, $package . '/images');
        }
        if ($mode === 'apply') {
            $result['phase'] = 'permissions';
            $result['permissions'] = barCutoverGrantAdministrator($pdo, $administrator['id']);
            $result['permission_state'] = barCutoverPermissionState($pdo, $administrator['id']);
            $result['permissions_committed'] = !in_array(false, $result['permission_state'], true);
            if (!$result['permissions_committed']) {
                throw new RuntimeException('Opening permission scope differs.');
            }
            $result['phase'] = 'source_after_commit';
            putenv('BAR_PREPARE_RUN=' . $plan['prepare_run']);
            $after = barCutoverPrepare();
            if (!hash_equals($plan['bundle_sha256'], $after['bundle_sha256'])) {
                throw new RuntimeException('Source changed after commit; preserve the committed target and review the difference.');
            }
            $result['source_still_matches'] = true;
        } elseif ($mode === 'status' && !$result['permissions_committed']) {
            throw new RuntimeException('Opening permission scope differs.');
        }
        $result['ok'] = true;
        $result['phase'] = 'complete';
        $result['mode'] = $mode;
    } catch (Throwable $error) {
        $result['error_type'] = get_debug_type($error);
        $result['message'] = $error instanceof DomainException || $error instanceof InvalidArgumentException
            ? $error->getMessage() : 'bar_cutover_operation_failed_check_commit_state_before_any_retry';
    }
    // Persist the outcome before emitting it; a disconnected SSH call is never retried automatically.
    $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents(__DIR__ . '/result.json', $json . PHP_EOL, LOCK_EX);
    @chmod(__DIR__ . '/result.json', 0600);
    echo $json . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}
