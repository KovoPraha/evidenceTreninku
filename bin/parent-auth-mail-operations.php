<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
$paths = null;
$workerLock = null;
try {
    $host = (string)getenv('APP_HOST');
    $action = (string)getenv('PARENT_AUTH_MAIL_ACTION');
    if ($host !== 'kis.kovopraha.cz' || !in_array($action, ['status', 'install', 'process'], true)) {
        throw new RuntimeException('parent_mail_invalid_operation');
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require_once $root . '/includes/parent_auth_mail_scheduler.php';
    require_once $root . '/includes/transactional_notification_worker.php';
    if (!defined('APP_BASE_URL') || APP_BASE_URL !== 'https://kis.kovopraha.cz'
        || !defined('JE_LOKALNE') || JE_LOKALNE !== false) {
        throw new RuntimeException('parent_mail_target_refused');
    }
    $paths = parentAuthMailPaths($root, (string)getenv('PARENT_AUTH_MAIL_OPERATIONS_DIR'));
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($action === 'status') {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $queues = transactionalNotificationStatus($pdo, 'mail', $host);
            $public = $pdo->query(
                'SELECT kind,target_type,status,COUNT(*) AS total, '
                . "SUM(CASE WHEN status='pending' AND available_at<=CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS due "
                . "FROM auth_mail_outbox WHERE (target_type='public' AND kind IN ('verify','reset')) "
                . "OR (target_type='child' AND kind='reset') GROUP BY kind,target_type,status"
            )
                ->fetchAll(PDO::FETCH_ASSOC);
            try {
                $last = $pdo->query("SELECT started_at,finished_at,status,summary_code FROM operational_job_runs WHERE job_key='transactional_notifications' ORDER BY id DESC LIMIT 1")
                    ->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException) {
                $last = ['issue' => 'operational_job_runs_unavailable'];
            }
            $result = ['read_only' => true, 'at' => gmdate(DATE_ATOM), 'queues' => $queues,
                'public_counts' => $public, 'shared_worker_last_run' => $last,
                'scheduler' => parentAuthMailSchedulerStatus($paths),
                'mail_function_available' => function_exists('mail')];
        } finally {
            $pdo->rollBack();
        }
    } elseif ($action === 'install') {
        if (!transactionalNotificationStatus($pdo, 'mail', $host)['ready']) {
            throw new RuntimeException('parent_mail_prerequisites_unavailable');
        }
        $result = parentAuthMailInstall($paths);
    } else {
        $lockPath = $paths['operations'] . '/parent-auth-mail-worker.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('parent_mail_worker_lock_symlink');
        }
        $workerLock = fopen($lockPath, 'c');
        if ($workerLock === false || !flock($workerLock, LOCK_EX | LOCK_NB)) {
            echo "{\"ok\":true,\"skipped\":\"worker_already_running\"}\n";
            exit(0);
        }
        if (!transactionalNotificationStatus($pdo, 'mail', $host)['ready']) {
            throw new RuntimeException('parent_mail_prerequisites_unavailable');
        }
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'public_only' => true];
        while ($result['processed'] < 20) {
            $outcome = authMailProcessOne($pdo, 'clubEventNotificationMailSender', null, false, true);
            if ($outcome === null) {
                break;
            }
            $result['processed']++;
            $result[$outcome]++;
        }
        if (getenv('PARENT_AUTH_MAIL_TRIGGER') === 'hosting_cron') {
            parentAuthMailHeartbeat($paths, $result, $result['failed'] === 0);
        }
    }
    echo json_encode($result + ['ok' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(($result['failed'] ?? 0) > 0 ? 1 : 0);
} catch (Throwable $error) {
    if (is_array($paths) && is_resource($workerLock) && getenv('PARENT_AUTH_MAIL_TRIGGER') === 'hosting_cron') {
        try {
            parentAuthMailHeartbeat($paths, ['error' => 'worker_failed'], false);
        } catch (Throwable) {
        }
    }
    error_log('parent-auth-mail-operations: ' . $error->getMessage());
    fwrite(STDERR, "parent_auth_mail_operation_failed\n");
    exit(1);
}
