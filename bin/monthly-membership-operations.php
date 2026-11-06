<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
try {
    $action = (string)getenv('MEMBERSHIP_SCHEDULER_ACTION');
    if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || !in_array($action, ['status', 'install', 'tick'], true)) {
        throw new RuntimeException('membership_scheduler_operation');
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require $root . '/includes/monthly_membership_scheduler.php';
    require $root . '/includes/monthly_membership_worker.php';
    require_once $root . '/includes/operational_jobs.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false || !defined('APP_BASE_URL')
        || rtrim(APP_BASE_URL, '/') !== 'https://kis.kovopraha.cz') {
        throw new RuntimeException('membership_scheduler_target');
    }
    $paths = membershipSchedulerPaths($root, (string)getenv('MEMBERSHIP_SCHEDULER_DIR'));
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $enabled = defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') && MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED === true;
    $mail = defined('MONTHLY_MEMBERSHIP_MAIL_ENABLED') && MONTHLY_MEMBERSHIP_MAIL_ENABLED === true;
    $success = static function () use ($pdo): ?int {
        $value = $pdo->query("SELECT MAX(finished_at) FROM operational_job_runs WHERE job_key='monthly_memberships' AND status='success'")->fetchColumn();
        return $value ? (new DateTimeImmutable((string)$value, new DateTimeZone('UTC')))->getTimestamp() : null;
    };
    if ($action === 'install') {
        if (!$enabled || getenv('MEMBERSHIP_SCHEDULER_CONFIRM') !== 'ZAPNOUT-CLENSTVI-CRON') {
            throw new RuntimeException('membership_scheduler_install_confirmation');
        }
        $success();
        $result = membershipSchedulerInstall($paths);
    } elseif ($action === 'tick') {
        if (getenv('MEMBERSHIP_SCHEDULER_TRIGGER') !== 'hosting_cron'
            || !membershipSchedulerStatus($paths, $success())['installed_expected_source']) {
            throw new RuntimeException('membership_scheduler_tick_identity');
        }
        $run = static function () use ($pdo, $mail, $success): array {
            if ((int)$pdo->query("SELECT GET_LOCK('evidence:monthly-memberships',0)")->fetchColumn() !== 1) {
                throw new RuntimeException('membership_worker_already_running');
            }
            $job = null;
            try {
                // Manual processing may have finished between the initial gate and acquiring this DB lock.
                if (!membershipSchedulerDue([], time(), $success())) {
                    return ['errors' => [], 'skipped' => 'already_completed'];
                }
                $job = operationalJobStart($pdo, 'monthly_memberships');
                $result = monthlyMembershipRunUnlocked($pdo, true, $mail, 'memberChargeReminderMailSender');
                operationalJobFinish($pdo, $job, $result['errors'] === [], $result['errors'] === [] ? 'completed' : 'billing_or_document_failed');
                $job = null;
                return $result;
            } catch (Throwable $error) {
                if ($job !== null) {
                    operationalJobFinish($pdo, $job, false, 'worker_exception');
                }
                throw $error;
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('evidence:monthly-memberships')");
            }
        };
        $result = membershipSchedulerTick($paths, $success, $run, null, $enabled);
    } else {
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $result = membershipSchedulerStatus($paths, $success()) + ['automation_enabled' => $enabled, 'mail_enabled' => $mail];
        } finally {
            $pdo->rollBack();
        }
    }
    $ok = ($result['outcome'] ?? null) !== 'failed';
    echo json_encode(['ok' => $ok, 'action' => $action] + $result, JSON_THROW_ON_ERROR) . PHP_EOL;
    if (!$ok) {
        exit(1);
    }
} catch (Throwable $error) {
    error_log('membership-scheduler: ' . $error->getMessage());
    fwrite(STDERR, "membership_scheduler_operation_failed; see private log\n");
    exit(1);
}
