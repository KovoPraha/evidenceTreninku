<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
umask(0077);
try {
    $action = (string)getenv('MEMBERSHIP_AUDIT_ACTION');
    if (getenv('APP_HOST') !== 'kis.kovopraha.cz' || !in_array($action, ['status', 'install', 'tick'], true)) {
        throw new RuntimeException('membership_audit_operation');
    }
    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'kis.kovopraha.cz';
    $root = dirname(__DIR__);
    require $root . '/config.php';
    require $root . '/includes/membership_morning_audit.php';
    if (!membershipSchedulerIdentity(defined('APP_BASE_URL') ? APP_BASE_URL : null, defined('JE_LOKALNE') ? JE_LOKALNE : null)) {
        throw new RuntimeException('membership_audit_target');
    }
    $paths = membershipMorningPaths($root, (string)getenv('MEMBERSHIP_AUDIT_DIR'));
    $context = static function (bool $withJobs) use ($paths): array {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec('START TRANSACTION READ ONLY');
        try {
            $latest = $pdo->query("SELECT MAX(finished_at) FROM operational_job_runs WHERE job_key='monthly_memberships' AND status='success'")->fetchColumn();
            $success = $latest ? (new DateTimeImmutable((string)$latest, new DateTimeZone('UTC')))->getTimestamp() : null;
            $membership = membershipSchedulerStatus($paths, $success) + [
                'automation_enabled' => defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') && MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED === true,
                'mail_enabled' => defined('MONTHLY_MEMBERSHIP_MAIL_ENABLED') && MONTHLY_MEMBERSHIP_MAIL_ENABLED === true,
            ];
            $jobs = [];
            if ($withJobs) {
                $q = $pdo->query("SELECT status,started_at,finished_at FROM operational_job_runs WHERE job_key='monthly_memberships' "
                    . "AND started_at>='2026-11-07 00:00:00' AND started_at<'2026-11-08 00:00:00' ORDER BY started_at LIMIT 101");
                $jobs = $q->fetchAll();
            }
            return [$membership, $jobs];
        } finally {
            $pdo->rollBack();
        }
    };
    if ($action === 'status') {
        $result = membershipMorningStatus($paths);
        try {
            $result['membership'] = $context(false)[0];
        } catch (Throwable $error) {
            error_log('membership-audit context: ' . $error->getMessage());
            $result['membership_error'] = 'read_only_context_failed';
        }
    } else {
        if ($action === 'install' && (getenv('MEMBERSHIP_AUDIT_CONFIRM') !== 'ZAPNOUT-RANNI-AUDIT'
            || !$context(false)[0]['installed_expected_source'])) {
            throw new RuntimeException('membership_audit_install_confirmation');
        }
        if ($action === 'tick' && getenv('MEMBERSHIP_AUDIT_TRIGGER') !== 'hosting_cron') {
            throw new RuntimeException('membership_audit_trigger');
        }
        $capture = static function (int $now) use ($context): array {
            [$membership, $jobs] = $context(true);
            return membershipMorningEvaluate($membership, $jobs, $now);
        };
        $result = membershipMorningOperate($paths, $action === 'install', $capture);
    }
    echo json_encode(['ok' => true, 'checked_at_utc' => gmdate(DATE_ATOM)] + $result, JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    error_log('membership-morning-audit: ' . $error->getMessage());
    fwrite(STDERR, "membership_morning_audit_failed; see private log\n");
    exit(1);
}
