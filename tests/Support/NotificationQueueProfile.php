<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once dirname(__DIR__, 2) . '/includes/transactional_notification_worker.php';

$schema = 'w11_queue_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $keys = ['keys' => ['v1' => random_bytes(32)], 'active_version' => 'v1', 'index_key' => random_bytes(32)];
    $insert = $pdo->prepare('INSERT INTO club_event_notifications '
        . '(notification_type,recipient_email,recipient_name,subject_plain,body_plain,status) '
        . "VALUES('synthetic','local@example.invalid','Synthetic','Synthetic','Synthetic',?)");
    $auth = $pdo->prepare('INSERT INTO auth_mail_outbox '
        . '(kind,target_type,target_id,token_hash,recipient_email,subject_plain,key_version,status) '
        . "VALUES('verify','public',1,?,'local@example.invalid','Synthetic','v1','sent')");
    $results = [];
    foreach ([0, 100, 10000] as $size) {
        $pdo->exec('DELETE FROM club_event_notifications');
        $pdo->exec('DELETE FROM auth_mail_outbox');
        $pdo->beginTransaction();
        for ($i = 0; $i < $size; $i++) {
            // Due event queue plus equally sized auth history; auth history has no secret body.
            $insert->execute(['pending']);
            $auth->execute([hash('sha256', (string) $i)]);
        }
        $pdo->commit();
        $pdo->profile = [];
        $pdo->profiling = true;
        $status = transactionalNotificationStatus($pdo, 'local-outbox', 'localhost', sys_get_temp_dir(), $keys);
        $pdo->profiling = false;
        if (!$status['ready'] || $status['queues']['club_event_notifications']['due'] !== $size
            || $status['queues']['auth_mail_outbox']['counts']['sent'] !== $size
            || str_contains(json_encode($status, JSON_THROW_ON_ERROR), 'example.invalid')) {
            throw new RuntimeException('Status count, readiness or privacy contract failed.');
        }
        $counts = ['prepared_sql' => count($pdo->profile), 'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))];
        $samples = [];
        $plain = w72Connection($schema);
        for ($sample = -2; $sample < 10; $sample++) {
            memory_reset_peak_usage();
            $memory = memory_get_usage();
            $start = hrtime(true);
            transactionalNotificationStatus($plain, 'local-outbox', 'localhost', sys_get_temp_dir(), $keys);
            if ($sample >= 0) {
                $samples[] = ['ms' => (hrtime(true) - $start) / 1e6, 'extra_peak_bytes' => memory_get_peak_usage() - $memory];
            }
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        $calls = 0;
        $start = hrtime(true);
        $drain = transactionalNotificationDrain($pdo, static function () use (&$calls): bool {
            $calls++;
            return true;
        }, 20, $keys);
        $drainMs = (hrtime(true) - $start) / 1e6;
        $pdo->profiling = false;
        if ($drain['sent'] !== min(20, $size) || $drain['processed'] !== $calls || $calls !== min(20, $size)) {
            throw new RuntimeException('Bounded drain contract failed.');
        }
        if ($counts['prepared_sql'] > 9 || $counts['fetched_rows'] > 7 || count($pdo->profile) > 75) {
            throw new RuntimeException('Queue SQL/row budget exceeded.');
        }
        $results[] = ['rows_per_queue' => $size, 'status' => $counts + ['warmups' => 2, 'samples' => $samples],
            'drain' => $drain + ['instrumented_single_ms' => $drainMs, 'prepared_sql' => count($pdo->profile),
                'fetched_rows' => array_sum(array_column($pdo->profile, 'fetched_rows'))],
            'claim_explain' => $pdo->query("EXPLAIN SELECT * FROM club_event_notifications WHERE status='pending' "
                . 'AND available_at<=CURRENT_TIMESTAMP ORDER BY id LIMIT 1')->fetchAll()];
    }
    echo json_encode(['schema' => 'full migrations, FK enabled, owned random localhost DB',
        'transport' => 'test callback only; no network', 'count_scope' => 'prepared/query statements; exec and transaction control excluded',
        'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo = $plain = null;
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
