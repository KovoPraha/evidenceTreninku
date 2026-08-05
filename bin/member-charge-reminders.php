<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$generate = in_array('--generate', $arguments, true);
$send = in_array('--send', $arguments, true);
$transport = 'mail';
$limit = 20;
foreach ($arguments as $argument) {
    if (in_array($argument, ['--generate', '--send'], true)) continue;
    if ($argument === '--transport=local-outbox') {
        $transport = 'local-outbox';
        continue;
    }
    if (preg_match('/^--limit=([1-9]|[1-9][0-9])$/D', $argument, $match) === 1) {
        $limit = (int)$match[1];
        continue;
    }
    if ($argument === '--help') {
        echo "Pouziti: APP_HOST=<host> php bin/member-charge-reminders.php --generate [--send] [--transport=local-outbox] [--limit=20]\n";
        exit(0);
    }
    fwrite(STDERR, "Neplatne argumenty. Pouzijte --help.\n");
    exit(64);
}
if (!$generate && !$send) {
    fwrite(STDERR, "Je nutne zvolit --generate nebo --send.\n");
    exit(64);
}
$appHost = getenv('APP_HOST');
if (!is_string($appHost) || preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $appHost) !== 1) {
    fwrite(STDERR, "APP_HOST musi byt explicitne nastaveny na hostname aplikace.\n");
    exit(64);
}
$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = (string)preg_replace('/:\d+$/', '', $appHost);

try {
    require_once $root . '/config.php';
    require_once $root . '/includes/member_charge_reminder.php';
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) if (!defined($constant)) throw new RuntimeException('missing_database_configuration');
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $generation = $generate ? memberChargeReminderGenerate($pdo) : ['queued' => 0, 'existing' => 0, 'skipped' => 0];
    $processed = $sent = $failed = 0;
    if ($send) {
        $sender = $transport === 'local-outbox'
            ? memberChargeReminderLocalOutboxSender($appHost)
            : 'memberChargeReminderMailSender';
        while ($processed < $limit) {
            $result = memberChargeReminderProcessOne($pdo, $sender);
            if ($result === null) break;
            $processed++;
            $result ? $sent++ : $failed++;
        }
    }
    echo json_encode(['generation' => $generation, 'transport' => $transport, 'processed' => $processed, 'sent' => $sent, 'failed' => $failed], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    error_log('member-charge-reminders.php: ' . $exception->getMessage());
    fwrite(STDERR, "Zpracovani pripominek selhalo; podrobnosti jsou pouze v serverovem logu.\n");
    exit(1);
}
