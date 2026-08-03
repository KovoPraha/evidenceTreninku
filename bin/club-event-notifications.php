<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$limit = 20;
foreach ($arguments as $argument) {
    if (preg_match('/^--limit=([1-9]|[1-9][0-9])$/D', $argument, $match) === 1) {
        $limit = (int)$match[1];
        continue;
    }
    if ($argument === '--help') {
        echo "Pouziti: APP_HOST=<host> php bin/club-event-notifications.php [--limit=20]\n";
        exit(0);
    }
    fwrite(STDERR, "Pouziti: APP_HOST=<host> php bin/club-event-notifications.php [--limit=20]\n");
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
    $processed = 0;
    $sent = 0;
    $failed = 0;
    while ($processed < $limit) {
        $result = clubEventNotificationProcessOne($pdo, 'clubEventNotificationMailSender');
        if ($result === null) {
            break;
        }
        $processed++;
        $result ? $sent++ : $failed++;
    }
    echo json_encode(
        ['processed' => $processed, 'sent' => $sent, 'failed' => $failed],
        JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    error_log('club-event-notifications.php: ' . $exception->getMessage());
    fwrite(STDERR, "Zpracovani oznameni selhalo; podrobnosti jsou pouze v serverovem logu.\n");
    exit(1);
}
