<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2).'/includes/club_event_notification.php';
require_once dirname(__DIR__, 2).'/includes/local_message_outbox.php';

require_once __DIR__.'/FamilyOverviewPageFixture.php';
$worker = ($argv[1] ?? '') === '--worker';
$schema = $worker ? ($argv[2] ?? '') : 'w11_delivery_'.bin2hex(random_bytes(8));
if (!preg_match('/^w11_delivery_[a-f0-9]{16}$/D', $schema)) {
    throw new RuntimeException('Invalid owned schema.');
}
$runtime = sys_get_temp_dir().'/'.$schema;
$admin = w72Connection();
$children = [];
if (!$worker) {
    $admin->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    if (!mkdir($runtime, 0700)) {
        throw new RuntimeException('Cannot create owned runtime.');
    }
    register_shutdown_function(static function () use ($admin, $schema, $runtime, &$children): void {
        foreach ($children as $child) {
            if (is_resource($child)) {
                proc_terminate($child);
                proc_close($child);
            }
        }
        $admin->exec('DROP DATABASE `'.$schema.'`');
        foreach (glob($runtime.'/*.outbox/*.json') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($runtime.'/*.outbox') ?: [] as $dir) {
            rmdir($dir);
        }
        foreach (glob($runtime.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($runtime);
    });
}
$pdo = w72Connection($schema);
if (!$worker) {
    w72Seed($pdo);
}
if ($worker) {
    $barrier = $runtime.'/race';
    $slot = $argv[3];
    file_put_contents($barrier.'.'.$slot.'.ready', 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($barrier.'.go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout.');
        }usleep(10000);
    }
    $sender = localMessageOutboxSender('127.0.0.1', 'r11-concurrency-v1', $barrier.'.outbox');
    if ($slot === 'before') {
        clubEventNotificationClaim($pdo);
        exit(23);
    }
    $outcome = clubEventNotificationProcessOne($pdo, static function (
        $email,
        $subject,
        $body
    ) use ($sender, $slot) {
        usleep(150000);
        $result = $sender($email, $subject, $body);
        if ($slot === 'after') {
            exit(23);
        }return $result;
    });
    echo json_encode(['outcome' => $outcome], JSON_THROW_ON_ERROR);
    exit;
}
$pdo->exec(('INSERT INTO club_event_notifications(notification_type,recipient_email,recipient_name,subject_plain,'
 . 'body_plain) VALUES(\'synthetic\',\'local@example.invalid\',\'Synthetic\',\'LOCALHOST NEPLATIT\',\'Synthetic c'
 . 'oncurrency proof\')'));
$barrier = $runtime.'/race';
$children = [];
foreach (['a','b'] as $slot) {
    $process = proc_open([PHP_BINARY,__FILE__,'--worker',$schema,$slot], [0 => ['pipe',
    'r'],1 => ['file',$barrier.'.'.$slot.'.out','w'],2 => ['file',$barrier.'.'.$slot.'.err',
    'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Worker start failed.');
    }fclose($pipes[0]);
    $children[$slot] = $process;
}
$deadline = microtime(true) + 15;
while (!is_file($barrier.'.a.ready') || !is_file($barrier.'.b.ready')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Worker readiness timeout.');
    }usleep(10000);
}
file_put_contents($barrier.'.go', 'go');
$outcomes = [];
foreach ($children as $slot => $child) {
    if (proc_close($child) !== 0) {
        throw new RuntimeException('Worker failed: '.file_get_contents($barrier.'.'.$slot
    .'.err'));
    }$outcomes[] = json_decode(
        (string)file_get_contents($barrier.'.'.$slot.'.out'),
        true,
        512,
        JSON_THROW_ON_ERROR
    )['outcome'];
}
$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$assert(count(array_filter($outcomes, static fn ($x) => $x === true)) === 1, 'Exactly one worker must send.');
$assert(count(glob($barrier.'.outbox/*.json') ?: []) === 1, 'Exactly one local artifact must exist.');
$assert(
    (int)$pdo->query('SELECT attempts FROM club_event_notifications')->fetchColumn() === 1,
    'Competing claim must not increment attempts.'
);
$assert(clubEventNotificationProcessOne($pdo, static fn () => true) === null, 'A repeat must not send.');
$pdo->exec("UPDATE club_event_notifications SET status='pending',available_at=CURRENT_TIMESTAMP");
$assert(clubEventNotificationProcessOne($pdo, static function () {
    throw new RuntimeException('Synthetic ambiguous transport');
}) === false, 'Ambiguous delivery must be held.');
$assert(
    $pdo->query('SELECT status FROM club_event_notifications')->fetchColumn() === 'failed',
    'Unknown delivery must be terminal until reviewed.'
);
$assert(clubEventNotificationProcessOne($pdo, static fn () => true) === null, 'Unknown delivery must never automatically retry.');
foreach (['before','after'] as $slot) {
    $pdo->exec("UPDATE club_event_notifications SET status='pending',available_at=CURRENT_TIMESTAMP,claim_token=NULL");
    $before = count(glob($barrier.'.outbox/*.json') ?: []);
    $process = proc_open([PHP_BINARY,__FILE__,'--worker',$schema,$slot], [0 => ['pipe',
    'r'],1 => ['file',$barrier.'.'.$slot.'.out','w'],2 => ['file',$barrier.'.'.$slot.'.err',
    'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Crash worker start failed.');
    }
    fclose($pipes[0]);
    $children[$slot] = $process;
    $assert(proc_close($process) === 23, 'Worker must exit before completion.');
    $assert(
        $pdo->query('SELECT status FROM club_event_notifications')->fetchColumn() === 'processing',
        'Unfinished claim remains processing.'
    );
    $assert(
        count(glob($barrier.'.outbox/*.json') ?: []) === $before + ($slot === 'after' ? 1 : 0),
        'Crash boundary artifact count.'
    );
    $pdo->exec("UPDATE club_event_notifications SET claimed_at=CURRENT_TIMESTAMP-INTERVAL 1 HOUR");
    $assert(clubEventNotificationProcessOne($pdo, static function () {
        throw new RuntimeException('Must never send stale claim');
    }) === null, 'Stale claim must not retry.');
    $assert(
        $pdo->query('SELECT status FROM club_event_notifications')->fetchColumn() === 'failed',
        'Stale claim held for review.'
    );
}
$pdo->exec("UPDATE club_event_notifications SET status='pending',attempts=0,available_at=CURRENT_TIMESTAMP");
$assert(clubEventNotificationProcessOne($pdo, static fn () => false) === false, 'Known refusal reported.');
$assert(
    $pdo->query('SELECT status FROM club_event_notifications')->fetchColumn() === 'pending',
    'Known refusal is retryable.'
);
$assert(clubEventNotificationProcessOne($pdo, static fn () => true) === null, 'Backoff prevents immediate retry.');
$pdo->exec("UPDATE club_event_notifications SET available_at=CURRENT_TIMESTAMP-INTERVAL 1 MINUTE");
$assert(clubEventNotificationProcessOne($pdo, static fn () => true) === true, 'Known refusal can retry after backoff.');
echo json_encode(
    ['checks' => 21,'two_process_claim' => 'pass','single_initial_local_outbox_artifact' => true,
    'unknown_delivery_held' => true,'process_exit_before_and_after_transport' => 'pass',
    'known_rejection_backoff' => 'pass','schema' => 'full catalog, FK enabled, random localhost database, cleanup on exit'],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
).PHP_EOL;
