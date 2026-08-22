<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
$generate = in_array('--generate', $arguments, true);
$sendLocal = in_array('--send-local', $arguments, true);
$sendMail = in_array('--send', $arguments, true);
$force = in_array('--force', $arguments, true);
$limit = 20;
foreach ($arguments as $argument) {
    if (in_array($argument, ['--generate', '--send', '--send-local', '--force'], true)) continue;
    if (preg_match('/^--limit=([1-9]|[1-9][0-9])$/D', $argument, $match) === 1) {
        $limit = (int)$match[1];
        continue;
    }
    if ($argument === '--help') {
        echo "Pouziti: APP_HOST=<host> php bin/family-weekly-summaries.php --generate [--force] [--send|--send-local] [--limit=20]\n";
        exit(0);
    }
    fwrite(STDERR, "Neplatne argumenty. Pouzijte --help.\n");
    exit(64);
}
if (!$generate && !$sendLocal && !$sendMail) {
    fwrite(STDERR, "Je nutne zvolit --generate, --send nebo --send-local.\n");
    exit(64);
}
if ($sendLocal && $sendMail) {
    fwrite(STDERR, "--send a --send-local nelze kombinovat.\n");
    exit(64);
}
$appHost = getenv('APP_HOST');
if (!is_string($appHost) || preg_match('/^[a-z0-9.-]+(?::\d+)?$/Di', $appHost) !== 1) {
    fwrite(STDERR, "APP_HOST musi byt explicitne nastaveny.\n");
    exit(64);
}
$normalizedHost = strtolower((string)preg_replace('/:\d+$/D', '', $appHost));
if ($sendLocal && !in_array($normalizedHost, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Lokální outbox je povolen pouze pro localhost.\n");
    exit(64);
}
$_SERVER['HTTP_HOST'] = $appHost;
$_SERVER['SERVER_NAME'] = $normalizedHost;

try {
    require_once $root . '/config.php';
    require_once $root . '/includes/family_weekly_delivery.php';
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $generation = $generate ? familyWeeklyDeliveryGenerate($pdo, null, $force) : null;
    $processed = $sent = $failed = 0;
    $transport = 'none';
    if ($sendLocal || $sendMail) {
        $transport = $sendLocal ? 'local-outbox' : 'mail';
        $sender = $sendLocal ? familyWeeklyDeliveryLocalOutboxSender($appHost) : 'familyWeeklyDeliveryMailSender';
        while ($processed < $limit) {
            $outcome = familyWeeklyDeliveryProcessOne($pdo, $sender);
            if ($outcome === null) break;
            $processed++;
            $outcome ? $sent++ : $failed++;
        }
    }
    echo json_encode(['generation' => $generation, 'transport' => $transport, 'processed' => $processed, 'sent' => $sent, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    error_log('family-weekly-summaries.php: ' . $exception->getMessage());
    fwrite(STDERR, "Zpracovani tydennich souhrnu selhalo; podrobnosti jsou pouze v serverovem logu.\n");
    exit(1);
}
