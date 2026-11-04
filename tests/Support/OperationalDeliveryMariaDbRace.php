<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2).'/includes/club_event_notification.php';
require_once dirname(__DIR__, 2).'/includes/local_message_outbox.php';
require_once dirname(__DIR__, 2).'/includes/family_weekly_delivery.php';

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
// A second connection changes the source after preview composition and before enqueue.
// Run in the existing disposable full-catalog schema on both CI MariaDB versions.
$other = w72Connection($schema);
$previewFactory = static fn (PDO $database, int $id, string $from): array => [
    'to' => (new DateTimeImmutable($from))->modify('+6 days')->format('Y-m-d'),
    'subject' => 'LOCALHOST weekly snapshot', 'body' => 'LOCALHOST old family content',
    'counts' => ['total' => 1],
];
$changes = ['opt_out', 'revoked_family', 'changed_email', 'inactive_account', 'unverified_email'];
foreach ($changes as $index => $change) {
    $pdo->exec("UPDATE verejni_uzivatele SET email='weekly@example.invalid',aktivni=1,email_overeno=1 WHERE id=11");
    $pdo->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=11");
    familyWeeklyDeliverySavePreference($pdo, 11, true);
    $result = familyWeeklyDeliveryGenerate(
        $pdo,
        new DateTimeImmutable(sprintf('2036-01-%02d', $index + 1), new DateTimeZone('Europe/Prague')),
        true,
        static function (PDO $database, int $id, string $from) use ($other, $change, $previewFactory): array {
            $preview = $previewFactory($database, $id, $from);
            if ($change === 'opt_out') {
                familyWeeklyDeliverySavePreference($other, $id, false);
            } elseif ($change === 'revoked_family') {
                $other->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=11");
            } elseif ($change === 'changed_email') {
                $other->exec("UPDATE verejni_uzivatele SET email='changed@example.invalid' WHERE id=11");
            } elseif ($change === 'inactive_account') {
                $other->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=11');
            } else {
                $other->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=11');
            }
            return $preview;
        }
    );
    $assert($result['queued'] === 0 && $result['skipped'] === 1, 'Changed source skips old weekly content: '.$change);
    $assert((int)$pdo->query('SELECT COUNT(*) FROM family_weekly_summaries')->fetchColumn() === 0, 'No partial weekly queue row: '.$change);
    $snapshots = (int)$pdo->query("SELECT COUNT(*) FROM notification_authorization_snapshots WHERE queue_key='family_weekly_summaries'")->fetchColumn();
    $assert($snapshots === 0, 'No partial authorization snapshot: '.$change);
    $calls = 0;
    $processed = familyWeeklyDeliveryProcessOne($pdo, static function () use (&$calls): bool {
        $calls++;
        return true;
    });
    $assert($processed === null && $calls === 0, 'No old weekly content reaches the sender: '.$change);
    $assert(!$pdo->inTransaction(), 'Skipped preview closes the transaction: '.$change);
}
$pdo->exec("UPDATE verejni_uzivatele SET email='weekly@example.invalid',aktivni=1,email_overeno=1 WHERE id=11");
$pdo->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=11");
$fresh = familyWeeklyDeliveryGenerate($pdo, new DateTimeImmutable('2036-01-01'), true, $previewFactory);
$assert($fresh['queued'] === 1, 'Fresh preview remains possible after the skipped attempt.');
$calls = 0;
$sender = static function () use (&$calls): bool {
    $calls++;
    return true;
};
$assert(familyWeeklyDeliveryProcessOne($pdo, $sender) === true && $calls === 1, 'Fresh preview can be processed once.');
$assert(familyWeeklyDeliveryProcessOne($pdo, $sender) === null && $calls === 1, 'Fresh preview cannot be processed twice.');

echo json_encode(
    ['checks' => 49,'two_process_claim' => 'pass','single_initial_local_outbox_artifact' => true,
    'unknown_delivery_held' => true,'process_exit_before_and_after_transport' => 'pass',
    'known_rejection_backoff' => 'pass','weekly_preview_interleavings' => $changes,
    'weekly_preview_change_checks' => 28,'schema' => 'full catalog, FK enabled, random localhost database, cleanup on exit'],
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
).PHP_EOL;
