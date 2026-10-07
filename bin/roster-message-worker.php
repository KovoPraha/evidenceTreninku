<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$argv = $argv ?? [];
require_once $root . '/includes/roster_message_worker_options.php';
try {
    $options = rosterMessageWorkerOptions(
        array_slice($argv, 1),
        getenv('ROSTER_MESSAGE_LIMIT'),
        getenv('ROSTER_MESSAGE_TRANSPORT')
    );
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(64);
}
$limit = $options['limit'];
$transport = $options['transport'];

$appHost = getenv('APP_HOST');
if (!is_string($appHost) || preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $appHost) !== 1) {
    fwrite(STDERR, "APP_HOST musi byt explicitne nastaveny.\n");
    exit(64);
}
$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = (string)preg_replace('/:\d+$/', '', $appHost);

try {
    require_once $root . '/config.php';
    require_once $root . '/includes/roster_message.php';
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
    $sender = 'rosterMessageMailSender';
    if ($transport === 'local-outbox') {
        $directory = getenv('ROSTER_MESSAGE_OUTBOX_DIR');
        if (!is_string($directory) || trim($directory) === '') {
            $directory = $root . '/var/roster-message-outbox';
        }
        $sender = localMessageOutboxSender($appHost, 'roster-message-local-outbox-v1', $directory);
    }
    $processed = 0;
    $sent = 0;
    $failed = 0;
    while ($processed < $limit) {
        $result = rosterMessageProcessOne($pdo, $sender);
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
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $exception) {
    error_log('roster-message-worker.php: ' . $exception->getMessage());
    fwrite(STDERR, "Zpracovani zprav soupisky selhalo.\n");
    exit(1);
}
