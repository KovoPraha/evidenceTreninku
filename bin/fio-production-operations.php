<?php

declare(strict_types=1);

function kisFioHostingIdentity(mixed $baseUrl, mixed $local): bool
{
    return $local === false && is_string($baseUrl) && rtrim($baseUrl, '/') === 'https://kis.kovopraha.cz';
}

/** Keep this operational worker outside the webroot; never touch other cron jobs. */
function kisFioHostingPaths(string $root, string $operations): array
{
    if (is_link($root) || is_link($operations)) {
        throw new RuntimeException('fio_scheduler_symlink_scope');
    }
    $root = realpath($root);
    $operations = realpath($operations);
    if ($root === false || $operations === false || basename($root) !== 'kis.kovopraha.cz'
        || is_link(dirname($root).'/data') || is_link(dirname($root).'/data/.kis-deploy')
        || $operations !== realpath(dirname($root).'/data/.kis-deploy')) {
        throw new RuntimeException('fio_scheduler_scope');
    }
    return ['root' => $root, 'operations' => $operations,
        'cron' => dirname($root).'/CRON.1minuta/zz-evidence-fio-import.php',
        'worker' => $operations.'/fio-hosting-worker-v1.php',
        'state' => $operations.'/fio-hosting-state-v1.json',
        'lock' => $operations.'/fio-hosting-worker-v1.lock'];
}

function kisFioHostingSource(array $paths): string
{
    return "<?php\n// EvidencePavel Fio hosting scheduler v1; no bank transfers.\n"
        ."if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }\n"
        ."putenv('APP_HOST=kis.kovopraha.cz');\nputenv('FIO_HOSTING_ACTION=tick');\n"
        ."putenv('FIO_HOSTING_TRIGGER=hosting_cron');\n"
        .'putenv('.var_export('APP_ROOT='.$paths['root'], true).");\n"
        .'putenv('.var_export('FIO_HOSTING_OPERATIONS='.$paths['operations'], true).");\n"
        .'require '.var_export($paths['worker'], true).";\nkisFioHostingMain();\n";
}

function kisFioHostingRead(array $paths): array
{
    if (is_link($paths['state'])) {
        throw new RuntimeException('fio_scheduler_state_symlink');
    }
    if (!is_file($paths['state'])) {
        return [];
    }
    $state = json_decode((string)file_get_contents($paths['state']), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($state) || ($state['version'] ?? null) !== 1 || ($state['trigger'] ?? '') !== 'hosting_cron') {
        throw new RuntimeException('fio_scheduler_invalid_state');
    }
    return $state;
}

function kisFioHostingWrite(array $paths, array $state): void
{
    if (is_link($paths['state'])) {
        throw new RuntimeException('fio_scheduler_state_symlink');
    }
    $text = json_encode($state, JSON_THROW_ON_ERROR);
    $tmp = tempnam($paths['operations'], 'fio-state-');
    try {
        if ($tmp === false || file_put_contents($tmp, $text) !== strlen($text)
            || !chmod($tmp, 0600) || !rename($tmp, $paths['state'])) {
            throw new RuntimeException('fio_scheduler_state_write');
        }
        $tmp = null;
    } finally {
        if (is_string($tmp) && is_file($tmp)) {
            unlink($tmp);
        }
    }
}

function kisFioHostingStatus(array $paths, ?int $now = null): array
{
    $now ??= time();
    $installed = is_file($paths['cron']) && !is_link($paths['cron']) && is_file($paths['worker']) && !is_link($paths['worker'])
        && hash_equals(hash('sha256', kisFioHostingSource($paths)), (string)hash_file('sha256', $paths['cron']))
        && hash_equals((string)hash_file('sha256', __FILE__), (string)hash_file('sha256', $paths['worker']));
    $state = kisFioHostingRead($paths);
    $tick = (int)($state['last_tick_at'] ?? 0);
    $completed = (int)($state['last_run']['completed_at'] ?? 0);
    return ['installed_expected_source' => $installed,
        'hosting_directory_available' => is_dir(dirname($paths['cron'])) && !is_link(dirname($paths['cron']))
            && is_writable(dirname($paths['cron'])),
        'interval_seconds' => 600, 'state' => $state,
        'recent_hosting_tick' => $installed && $tick > 0 && $tick <= $now && $now - $tick <= 180,
        'recent_successful_import' => $installed && ($state['last_run']['ok'] ?? false) === true
            && $completed > 0 && $completed >= (int)($state['last_attempt_at'] ?? 0)
            && $completed <= $now && $now - $completed <= 900];
}

/** Refuse existing foreign files, including same-named jobs. No directory is provisioned here. */
function kisFioHostingInstall(array $paths): array
{
    if (!is_dir(dirname($paths['cron'])) || is_link(dirname($paths['cron'])) || !is_writable(dirname($paths['cron']))) {
        throw new RuntimeException('fio_scheduler_hosting_directory_missing');
    }
    $changed = false;
    foreach ([$paths['worker'] => (string)file_get_contents(__FILE__), $paths['cron'] => kisFioHostingSource($paths)] as $target => $text) {
        if (file_exists($target) || is_link($target)) {
            if (!is_file($target) || is_link($target) || !hash_equals(hash('sha256', $text), (string)hash_file('sha256', $target))) {
                throw new RuntimeException('fio_scheduler_foreign_file');
            }
        }
    }
    // Holding the same lock prevents a half-installed worker from running during installation.
    if (is_link($paths['lock'])) {
        throw new RuntimeException('fio_scheduler_lock_symlink');
    }
    $lock = fopen($paths['lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('fio_scheduler_lock');
    }
    try {
        foreach ([$paths['worker'] => (string)file_get_contents(__FILE__), $paths['cron'] => kisFioHostingSource($paths)] as $target => $text) {
            if (file_exists($target) || is_link($target)) {
                if (!is_file($target) || is_link($target) || !hash_equals(hash('sha256', $text), (string)hash_file('sha256', $target))) {
                    throw new RuntimeException('fio_scheduler_foreign_file');
                }
                continue;
            }
            $tmp = tempnam(dirname($target), 'fio-install-');
            if ($tmp === false || file_put_contents($tmp, $text) !== strlen($text) || !chmod($tmp, 0700) || !rename($tmp, $target)) {
                if (is_string($tmp) && is_file($tmp)) {
                    unlink($tmp);
                }
                throw new RuntimeException('fio_scheduler_install');
            }
            $changed = true;
        }
        return ['installed' => true, 'changed' => $changed];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** A real hosting invocation owns this heartbeat. Status and manual imports never manufacture it. */
function kisFioHostingTick(array $paths, callable $import, ?int $now = null): array
{
    $wallClock = $now === null;
    $now ??= time();
    if (is_link($paths['lock'])) {
        throw new RuntimeException('fio_scheduler_lock_symlink');
    }
    $lock = fopen($paths['lock'], 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['skipped' => 'worker_already_running'];
    }
    try {
        $state = kisFioHostingRead($paths);
        $lastAttempt = (int)($state['last_attempt_at'] ?? 0);
        $state = array_replace($state, ['version' => 1, 'trigger' => 'hosting_cron', 'last_tick_at' => $now,
            'ticks' => (int)($state['ticks'] ?? 0) + 1]);
        if ($lastAttempt > $now || ($lastAttempt > 0 && $now - $lastAttempt < 600)) {
            $state['last_tick_outcome'] = 'not_due';
            kisFioHostingWrite($paths, $state);
            return ['skipped' => 'not_due'];
        }
        $state['last_attempt_at'] = $now;
        $state['last_tick_outcome'] = 'running';
        // Persist the rate gate before the provider call, including crashes or unknown transport outcomes.
        kisFioHostingWrite($paths, $state);
        try {
            $result = $import();
            $state['last_run'] = ['ok' => true, 'completed_at' => $wallClock ? time() : $now, 'result' => $result];
            $state['last_tick_outcome'] = 'imported';
        } catch (Throwable $error) {
            $code = preg_match('/^fio_[a-z0-9_]{1,80}$/D', $error->getMessage()) === 1 ? $error->getMessage() : 'worker_failed';
            $state['last_run'] = ['ok' => false, 'completed_at' => $wallClock ? time() : $now, 'error' => $code];
            $state['last_tick_outcome'] = 'failed';
        }
        kisFioHostingWrite($paths, $state);
        return $state['last_run'];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Canonical importer and its shared DB lock: manual and hosting workers cannot overlap. */
function kisFioHostingImport(PDO $pdo, string $root): array
{
    if (!defined('FIO_IMPORT_ENABLED') || FIO_IMPORT_ENABLED !== true || !defined('FIO_API_TOKEN') || FIO_API_TOKEN === '') {
        throw new RuntimeException('fio_import_disabled_or_missing_token');
    }
    require_once $root.'/includes/fio_sync.php';
    require_once $root.'/includes/shop_bank_settings.php';
    require_once $root.'/includes/operational_jobs.php';
    if ((int)$pdo->query("SELECT GET_LOCK('evidence:fio-readonly-import',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('fio_import_already_running');
    }
    $job = null;
    try {
        $job = operationalJobStart($pdo, 'fio_import');
        $iban = shopBankSettingsEffective($pdo)['iban'];
        $result = fioSynchronize(
            $pdo,
            $iban,
            static fn (string $from, string $to): string => fioFetchPeriodJson((string)constant('FIO_API_TOKEN'), $from, $to),
            new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')),
            defined('FIO_AUTO_CONFIRM_ENABLED') && FIO_AUTO_CONFIRM_ENABLED === true,
            defined('FIO_IMPORT_LOOKBACK_DAYS') ? (int)FIO_IMPORT_LOOKBACK_DAYS : 3,
            defined('FIO_IMPORT_START_DATE') ? FIO_IMPORT_START_DATE : null
        );
        operationalJobFinish($pdo, $job, true);
        return $result;
    } catch (Throwable $error) {
        if ($job !== null) {
            operationalJobFinish($pdo, $job, false, 'worker_exception');
        }
        throw $error;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('evidence:fio-readonly-import')");
    }
}

/** Read-only, one row per review item; no VS, amount, account, name or provider payload escapes. */
function kisFioReviewAudit(PDO $pdo, string $iban): array
{
    $q = $pdo->prepare('SELECT m.id,m.amount_minor,m.currency,m.variable_symbol,m.booked_on,m.match_status '
        .'FROM fio_account_movements m JOIN fio_import_runs r ON r.id=m.import_run_id '
        ."WHERE r.source_account_iban=? AND m.match_status LIKE 'review_%' ORDER BY m.id LIMIT 1001");
    $q->execute([fioNormalizeIban($iban)]);
    $movements = $q->fetchAll(PDO::FETCH_ASSOC);
    if (count($movements) > 1000) {
        throw new RuntimeException('fio_review_audit_limit');
    }
    $vsCount = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE variable_symbol=?');
    $amountCount = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status='pending' AND method='bank_transfer' AND amount_minor=? AND currency=?");
    $later = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status='pending' AND method='bank_transfer' AND created_at>=?");
    $items = [];
    $counts = [];
    foreach ($movements as $m) {
        $proposal = fioProposePaymentMatch($pdo, (int)$m['amount_minor'], (string)$m['currency'], $m['variable_symbol']);
        $vsCount->execute([$m['variable_symbol']]);
        $amountCount->execute([(int)$m['amount_minor'], (string)$m['currency']]);
        $later->execute([(new DateTimeImmutable((string)$m['booked_on']))->modify('+1 day')->format('Y-m-d')]);
        $status = (string)$proposal['status'];
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        $items[] = ['movement_id' => (int)$m['id'], 'stored_status' => $m['match_status'], 'current_status' => $status,
            'vs_payment_count' => (int)$vsCount->fetchColumn(), 'same_amount_pending_count' => (int)$amountCount->fetchColumn(),
            'pending_created_after_booking_day' => (int)$later->fetchColumn()];
    }
    $payments = $pdo->query('SELECT status,COUNT(*) AS count FROM payments GROUP BY status ORDER BY status')->fetchAll(PDO::FETCH_ASSOC);
    return ['read_only' => true, 'reviewed' => count($items), 'classifications' => $counts, 'items' => $items, 'payments' => $payments];
}

function kisFioHostingMain(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }
    umask(0077);
    try {
        if (getenv('APP_HOST') !== 'kis.kovopraha.cz') {
            throw new RuntimeException('fio_hosting_target');
        }
        $paths = kisFioHostingPaths((string)getenv('APP_ROOT'), (string)getenv('FIO_HOSTING_OPERATIONS'));
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
        require $paths['root'].'/config.php';
        if (!kisFioHostingIdentity(defined('APP_BASE_URL') ? APP_BASE_URL : null, defined('JE_LOKALNE') ? JE_LOKALNE : null)) {
            throw new RuntimeException('fio_hosting_environment');
        }
        $action = getenv('FIO_HOSTING_ACTION');
        if (!in_array($action, ['status', 'audit', 'install', 'tick'], true)) {
            throw new RuntimeException('fio_hosting_action');
        }
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false]);
        if ($action === 'install') {
            if (getenv('FIO_HOSTING_CONFIRM') !== 'ZAPNOUT-FIO-HOSTING' || !defined('FIO_IMPORT_ENABLED') || FIO_IMPORT_ENABLED !== true) {
                throw new RuntimeException('fio_hosting_install_confirmation');
            }
            $result = kisFioHostingInstall($paths) + ['scheduler' => kisFioHostingStatus($paths)];
        } elseif ($action === 'tick') {
            if (getenv('FIO_HOSTING_TRIGGER') !== 'hosting_cron' || realpath(__FILE__) !== realpath($paths['worker'])
                || !kisFioHostingStatus($paths)['installed_expected_source']) {
                throw new RuntimeException('fio_hosting_tick_identity');
            }
            $result = kisFioHostingTick($paths, static fn (): array => kisFioHostingImport($pdo, $paths['root']));
        } else {
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            $pdo->beginTransaction();
            try {
                $result = ['read_only' => true, 'scheduler' => kisFioHostingStatus($paths)];
                if ($action === 'audit') {
                    require_once $paths['root'].'/includes/fio_readonly_import.php';
                    require_once $paths['root'].'/includes/shop_bank_settings.php';
                    $result['review'] = kisFioReviewAudit($pdo, shopBankSettingsEffective($pdo)['iban']);
                }
            } finally {
                $pdo->rollBack();
            }
        }
        echo json_encode(['ok' => true, 'action' => $action] + $result, JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable) {
        fwrite(STDERR, "fio_hosting_operation_failed; no token or bank payload was printed\n");
        exit(1);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    kisFioHostingMain();
}
