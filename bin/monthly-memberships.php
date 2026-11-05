<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (!in_array($arg, ['--apply','--send','--local-outbox','--dry-run'], true)) {
        fwrite(STDERR, "Neplatný argument.\n");
        exit(64);
    }
}
if (in_array('--dry-run', $args, true) && (in_array('--apply', $args, true) || in_array('--send', $args, true))) {
    fwrite(STDERR, "Dry-run nelze kombinovat se zápisem.\n");
    exit(64);
}
try {
    $host = getenv('APP_HOST');
    if (!is_string($host) || !preg_match('/^[a-z0-9.-]+(?::[0-9]+)?$/Di', $host)) {
        throw new RuntimeException('APP_HOST musí být výslovně nastaven.');
    }
    $_SERVER['HTTP_HOST'] = $host;
    require dirname(__DIR__).'/config.php';
    require dirname(__DIR__).'/includes/monthly_membership_worker.php';
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE
    => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE
    => PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES => false]);
    $apply = in_array('--apply', $args, true);
    $send = in_array('--send', $args, true);
    if ($send && !$apply) {
        throw new RuntimeException('Odesílání vyžaduje --apply.');
    }
    if ($apply && (!defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED') || MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED !== true)) {
        throw new RuntimeException('Automatizace měsíčního členství není povolena.');
    }
    if ($send && !in_array('--local-outbox', $args, true) && (!defined('MONTHLY_MEMBERSHIP_MAIL_ENABLED') || MONTHLY_MEMBERSHIP_MAIL_ENABLED !== true)) {
        throw new RuntimeException('Produkční rozesílání členství není povoleno.');
    }
    $sender = in_array('--local-outbox', $args, true) ? memberChargeReminderLocalOutboxSender($host) : 'memberChargeReminderMailSender';
    require_once dirname(__DIR__).'/includes/operational_jobs.php';
    $run = $apply ? operationalJobStart($pdo, 'monthly_memberships') : null;
    $result = monthlyMembershipRun($pdo, $apply, $send, $sender);
    if ($run !== null) {
        operationalJobFinish($pdo, $run, $result['errors'] === [], $result['errors'] === [] ? 'completed' : 'billing_or_document_failed');
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit($result['errors'] === [] ? 0 : 1);
} catch (Throwable $error) {
    if (isset($run,$pdo)) {
        try {
            operationalJobFinish($pdo, $run, false, 'worker_exception');
        } catch (Throwable) {
        }
    }
    error_log('monthly-memberships: '.$error->getMessage());
    fwrite(STDERR, "Zpracování měsíčních členství selhalo; viz soukromý serverový log.\n");
    exit(1);
}
