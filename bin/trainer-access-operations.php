<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
$workerLock = null;
$schedulerPaths = null;
$host = (string)getenv('APP_HOST');
$action = (string)getenv('TRAINER_RESET_ACTION');
if (!preg_match('/^[a-z0-9.-]+$/D', $host) || !in_array($action, ['status', 'process', 'probe', 'install'], true)) {
    fwrite(STDERR, "trainer_access_invalid_configuration\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host;
$root = dirname(__DIR__);
try {
    require $root . '/config.php';
    require $root . '/includes/trainer_access_readiness.php';
    require $root . '/includes/trainer_reset_scheduler.php';
    if (!trainerResetSchedulerIdentity($host, defined('APP_BASE_URL') ? APP_BASE_URL : null)) {
        throw new RuntimeException('Unexpected operations target.');
    }
    $schedulerPaths = trainerResetSchedulerPaths($root, (string)getenv('TRAINER_RESET_OPERATIONS_DIR'));
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($action === 'status') {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $result = trainerAccessReadiness($pdo, $host);
            $result['at'] = gmdate(DATE_ATOM);
            $result['scheduler'] = trainerResetSchedulerStatus($schedulerPaths);
        } finally {
            $pdo->rollBack();
        }
    } elseif ($action === 'install') {
        $result = trainerResetSchedulerInstall($schedulerPaths);
    } elseif ($action === 'process') {
        $workerLock = fopen($schedulerPaths['operations'] . '/trainer-reset-worker.lock', 'c');
        if ($workerLock === false || !flock($workerLock, LOCK_EX | LOCK_NB)) {
            echo "{\"skipped\":\"worker_already_running\"}\n";
            exit(0);
        }
        // No public-account or club-event backlog is picked up by this trainer-only worker.
        transactionalNotificationAssertQueues($pdo);
        personSensitiveConfig();
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'trainer_only' => true];
        while ($result['processed'] < 20) {
            $status = authMailProcessOne($pdo, 'clubEventNotificationMailSender', null, true);
            if ($status === null) {
                break;
            }
            $result['processed']++;
            $result[$status]++;
        }
        if (getenv('TRAINER_RESET_TRIGGER') === 'hosting_cron') {
            trainerResetSchedulerHeartbeat($schedulerPaths, $result, $result['failed'] === 0);
        }
    } else {
        // Owner authorized exactly one transport probe to this address on 2026-10-31.
        // The exclusive marker is retained even when transport outcome is ambiguous.
        $directory = realpath((string)getenv('TRAINER_RESET_OPERATIONS_DIR'));
        if ($host !== 'kis.kovopraha.cz' || $directory === false || !is_dir($directory)) {
            throw new RuntimeException('Probe scope refused.');
        }
        $marker = fopen($directory . '/trainer-mail-probe-20261031.started', 'x');
        if ($marker === false) {
            throw new RuntimeException('Probe already attempted.');
        }
        fwrite($marker, gmdate(DATE_ATOM));
        fclose($marker);
        $accepted = clubEventNotificationMailSender(
            'info@kovopraha.cz',
            'EvidencePavel — ověření doručování pro trenéry',
            "Toto je vámi schválená zkušební zpráva pro ověření doručování obnovy trenérských hesel.\n\nKontrolní označení: EP-TRENER-20261031.\nŽádné heslo nebylo změněno."
        );
        $result = ['probe' => 'EP-TRENER-20261031', 'transport_accepted' => $accepted, 'mailbox_delivery_verified' => false, 'passwords_changed' => false];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['failed'] ?? 0) > 0 || ($result['transport_accepted'] ?? true) === false ? 1 : 0);
} catch (Throwable) {
    if (is_array($schedulerPaths) && is_resource($workerLock) && getenv('TRAINER_RESET_TRIGGER') === 'hosting_cron') {
        try {
            trainerResetSchedulerHeartbeat($schedulerPaths, ['error' => 'worker_failed'], false);
        } catch (Throwable) {
            // Keep the previous heartbeat stale rather than claim a successful run.
        }
    }
    fwrite(STDERR, "trainer_access_operation_failed\n");
    exit(1);
}
