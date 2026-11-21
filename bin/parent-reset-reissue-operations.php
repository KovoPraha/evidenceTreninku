<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
try {
    $host = (string)getenv('APP_HOST');
    $action = (string)getenv('PARENT_RESET_ACTION');
    if ($host !== 'kis.kovopraha.cz' || !in_array($action, ['inspect', 'issue'], true)) {
        throw new RuntimeException('parent_reset_invalid_operation');
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require_once $root . '/includes/parent_auth_mail_scheduler.php';
    require_once $root . '/includes/parent_reset_reissue.php';
    require_once $root . '/includes/transactional_notification_worker.php';
    require_once $root . '/includes/app_url.php';
    if (!parentAuthMailIdentity(
        $host,
        defined('APP_BASE_URL') ? APP_BASE_URL : null,
        defined('JE_LOKALNE') ? JE_LOKALNE : null
    )) {
        throw new RuntimeException('parent_reset_target_refused');
    }
    $paths = parentAuthMailPaths($root, (string)getenv('PARENT_RESET_OPERATIONS_DIR'));
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if (!transactionalNotificationStatus($pdo, 'mail', $host)['ready']) {
        throw new RuntimeException('parent_reset_prerequisites_unavailable');
    }
    if (!parentAuthMailSchedulerStatus($paths)['recent_successful_hosting_run']) {
        throw new RuntimeException('parent_reset_scheduler_unhealthy');
    }
    if ($action === 'inspect') {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $result = parentResetReissueInspect($pdo);
        } finally {
            $pdo->rollBack();
        }
        $result['read_only'] = true;
    } else {
        $raw = (string)getenv('PARENT_RESET_OLD_IDS');
        if (preg_match('/^[1-9][0-9]{0,18},[1-9][0-9]{0,18}$/D', $raw) !== 1) {
            throw new RuntimeException('parent_reset_old_ids_invalid');
        }
        $result = parentResetReissueTwo($pdo, explode(',', $raw), appUrl('booking/nove_heslo.php'));
    }
    echo json_encode($result + ['ok' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    error_log('parent-reset-reissue-operations: ' . $exception->getMessage());
    fwrite(STDERR, "parent_reset_operation_failed\n");
    exit(1);
}
