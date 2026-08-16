<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$envLimit = getenv('CLUB_EVENT_NOTIFICATION_LIMIT');
$limit = is_string($envLimit) && preg_match('/^(?:[1-9]|[1-9][0-9])$/D', $envLimit) === 1
    ? (int)$envLimit
    : 20;
$envTransport = getenv('CLUB_EVENT_NOTIFICATION_TRANSPORT');
$transport = is_string($envTransport) && $envTransport !== '' ? $envTransport : 'mail';
foreach ($arguments as $argument) {
    if (preg_match('/^--limit=([1-9]|[1-9][0-9])$/D', $argument, $match) === 1) {
        $limit = (int)$match[1];
        continue;
    }
    if (preg_match('/^--transport=(mail|local-outbox)$/D', $argument, $match) === 1) {
        $transport = $match[1];
        continue;
    }
    if ($argument === '--help') {
        echo "Pouziti: APP_HOST=<host> php bin/club-event-notifications.php [--limit=20] [--transport=mail|local-outbox]\n";
        exit(0);
    }
    fwrite(STDERR, "Pouziti: APP_HOST=<host> php bin/club-event-notifications.php [--limit=20] [--transport=mail|local-outbox]\n");
    exit(64);
}
if (!in_array($transport, ['mail', 'local-outbox'], true)) {
    fwrite(STDERR, "CLUB_EVENT_NOTIFICATION_TRANSPORT musi byt mail nebo local-outbox.\n");
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
    require_once $root . '/includes/club_event_notification.php';
    require_once $root . '/includes/local_message_outbox.php';
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $constant) {
        if (!defined($constant)) {
            throw new RuntimeException('missing_database_configuration');
        }
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $sender = 'clubEventNotificationMailSender';
    if ($transport === 'local-outbox') {
        $configuredDirectory = getenv('CLUB_EVENT_NOTIFICATION_OUTBOX_DIR');
        $directory = is_string($configuredDirectory) && trim($configuredDirectory) !== ''
            ? $configuredDirectory
            : $root . '/var/club-event-notification-outbox';
        $sender = localMessageOutboxSender(
            $appHost,
            'evidence.transactional-notification.v1',
            $directory
        );
    }
    $processed = 0;
    $sent = 0;
    $failed = 0;
    while ($processed < $limit) {
        $result = clubEventNotificationProcessOne($pdo, $sender);
        if ($result === null) {
            break;
        }
        $processed++;
        $result ? $sent++ : $failed++;
    }
    echo json_encode(
        ['processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'transport' => $transport],
        JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    error_log('club-event-notifications.php: ' . $exception->getMessage());
    fwrite(STDERR, "Zpracovani oznameni selhalo; podrobnosti jsou pouze v serverovem logu.\n");
    exit(1);
}
