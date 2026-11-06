<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
$lock = null;
$paths = null;
$job = null;
try {
    $host = (string)getenv('APP_HOST');
    $action = (string)getenv('STAFF_NOTIFICATION_ACTION');
    if ($host !== 'kis.kovopraha.cz' || !in_array($action, ['status', 'install', 'process'], true)) {
        throw new RuntimeException('staff_notification_invalid_operation');
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require $root . '/includes/staff_notification_scheduler.php';
    require $root . '/includes/staff_notification_worker.php';
    require $root . '/includes/operational_jobs.php';
    if (!staffNotificationSchedulerIdentity($host, defined('APP_BASE_URL') ? APP_BASE_URL : null)
        || !defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('staff_notification_target_refused');
    }
    $paths = staffNotificationSchedulerPaths($root, (string)getenv('STAFF_NOTIFICATION_OPERATIONS_DIR'));
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($action === 'status') {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $result = staffNotificationWorkerStatus($pdo);
            $result['scheduler'] = staffNotificationSchedulerStatus($paths);
            $result['at'] = gmdate(DATE_ATOM);
        } finally {
            $pdo->rollBack();
        }
    } elseif ($action === 'install') {
        // Require the deployed schema before enabling a persistent job.
        staffNotificationWorkerStatus($pdo);
        $result = staffNotificationSchedulerInstall($paths);
    } else {
        $lockPath = $paths['operations'] . '/staff-notification-worker.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('staff_notification_lock_symlink');
        }
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            echo "{\"ok\":true,\"skipped\":\"worker_already_running\"}\n";
            exit;
        }
        $job = operationalJobStart($pdo, 'staff_notifications');
        $result = staffNotificationDrain($pdo, 'clubEventNotificationMailSender');
        $ok = $result['failed'] === 0 && $result['sync']['without_recipient'] === 0 && $result['sync']['invalid_email'] === 0;
        operationalJobFinish($pdo, $job, $ok, $ok ? 'completed' : 'staff_delivery_attention');
        $job = null;
        if (getenv('STAFF_NOTIFICATION_TRIGGER') === 'hosting_cron') {
            staffNotificationSchedulerHeartbeat($paths, $result, $ok);
        }
        $result['ok'] = $ok;
    }
    echo json_encode($result + ['ok' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['ok'] ?? true) ? 0 : 1);
} catch (Throwable $error) {
    if (isset($pdo) && $job !== null) {
        try {
            operationalJobFinish($pdo, $job, false, 'staff_worker_exception');
        } catch (Throwable) {
        }
    }
    if (is_array($paths) && is_resource($lock) && getenv('STAFF_NOTIFICATION_TRIGGER') === 'hosting_cron') {
        try {
            staffNotificationSchedulerHeartbeat($paths, ['error' => 'worker_failed'], false);
        } catch (Throwable) {
        }
    }
    error_log('staff_notification_operations: ' . $error->getMessage());
    fwrite(STDERR, "staff_notification_operation_failed\n");
    exit(1);
}
