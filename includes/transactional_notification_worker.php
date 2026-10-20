<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_mail_outbox.php';
require_once __DIR__ . '/club_event_notification.php';

/** Validate both queues before claiming or delivering any message. */
function transactionalNotificationAssertQueues(PDO $pdo): void
{
    foreach (['auth_mail_outbox', 'club_event_notifications'] as $table) {
        $pdo->query("SELECT id,status,attempts,available_at,claimed_at,claim_token,created_at,updated_at,sent_at,last_error FROM $table WHERE 1=0");
    }
    $pdo->query('SELECT kind,target_type,target_id,token_hash,recipient_email,subject_plain,body_ciphertext,key_version FROM auth_mail_outbox WHERE 1=0');
    $pdo->query('SELECT recipient_email,subject_plain,body_plain FROM club_event_notifications WHERE 1=0');
}

/**
 * Read-only, aggregate diagnostics. "ready" describes local prerequisites only,
 * never scheduler activity or delivery to a recipient's mailbox.
 */
function transactionalNotificationStatus(
    PDO $pdo,
    string $transport,
    string $appHost,
    ?string $outboxDirectory = null,
    ?array $keyConfig = null
): array {
    $issues = [];
    $queues = [];
    $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $stale = $mysql ? 'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE' : "datetime('now','-15 minutes')";
    $age = $mysql ? 'UNIX_TIMESTAMP(CURRENT_TIMESTAMP)-UNIX_TIMESTAMP(created_at)'
        : "(julianday('now')-julianday(created_at))*86400";
    foreach (['auth_mail_outbox', 'club_event_notifications'] as $table) {
        try {
            $counts = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
            foreach ($pdo->query("SELECT status,COUNT(*) AS total FROM $table GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (array_key_exists((string)$row['status'], $counts)) {
                    $counts[(string)$row['status']] = (int)$row['total'];
                }
            }
            $row = $pdo->query(
                "SELECT SUM(CASE WHEN status='pending' AND available_at<=CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS due,"
                . "SUM(CASE WHEN status='processing' AND claimed_at<$stale THEN 1 ELSE 0 END) AS stale_processing,"
                . "MAX(CASE WHEN status='pending' THEN $age ELSE NULL END) AS oldest_pending_age_seconds"
                . " FROM $table"
            )->fetch(PDO::FETCH_ASSOC);
            $queues[$table] = ['available' => true, 'counts' => $counts, 'due' => (int)$row['due'],
                'stale_processing' => (int)$row['stale_processing'],
                'oldest_pending_age_seconds' => $row['oldest_pending_age_seconds'] === null
                    ? null : max(0, (int)round((float)$row['oldest_pending_age_seconds']))];
        } catch (PDOException) {
            $queues[$table] = ['available' => false];
            $issues[] = $table . '_schema_unavailable';
        }
    }
    try {
        transactionalNotificationAssertQueues($pdo);
    } catch (PDOException) {
        $issues[] = 'worker_queue_schema_unavailable';
    }
    $missingKeyVersions = null;
    try {
        $keys = personSensitiveConfig($keyConfig);
        if (($queues['auth_mail_outbox']['available'] ?? false) === true) {
            $missingKeyVersions = 0;
            foreach ($pdo->query("SELECT DISTINCT key_version FROM auth_mail_outbox WHERE status IN ('pending','processing','failed') AND body_ciphertext IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $version) {
                if (!isset($keys['keys'][(string)$version])) $missingKeyVersions++;
            }
            if ($missingKeyVersions > 0) $issues[] = 'queued_encryption_keys_unavailable';
        }
    } catch (PersonSensitiveException) {
        $issues[] = 'encryption_configuration_unavailable';
    } catch (PDOException) {
        $issues[] = 'auth_mail_outbox_schema_unavailable';
    }
    if ($transport === 'local-outbox') {
        $host = strtolower((string)preg_replace('/:\d+$/D', '', trim($appHost)));
        if (!in_array($host, ['localhost', '127.0.0.1'], true)) $issues[] = 'local_outbox_requires_localhost';
        // Check an existing ancestor without creating the directory or a probe file.
        $directory = $outboxDirectory ?? '';
        if ($directory === '') {
            $issues[] = 'outbox_directory_unavailable';
        } else {
            while (!file_exists($directory) && dirname($directory) !== $directory) $directory = dirname($directory);
            if (!is_dir($directory) || !is_writable($directory)) $issues[] = 'outbox_directory_unavailable';
        }
    } elseif ($transport !== 'mail' || !function_exists('mail')) {
        $issues[] = 'transport_unavailable';
    }
    $attention = $issues !== [];
    foreach ($queues as $queue) {
        $attention = $attention || ($queue['counts']['failed'] ?? 0) > 0
            || ($queue['stale_processing'] ?? 0) > 0
            || ($queue['oldest_pending_age_seconds'] ?? 0) >= 900;
    }
    return ['schema' => 'evidence.transactional-notification-status.v1', 'read_only' => true,
        'ready' => $issues === [], 'attention_required' => $attention, 'transport' => $transport,
        'scheduler_verified' => false, 'mailbox_delivery_verified' => false,
        'missing_queued_key_versions' => $missingKeyVersions, 'issues' => array_values(array_unique($issues)),
        'queues' => $queues];
}

/** @param callable(string,string,string):bool $sender */
function transactionalNotificationDrain(PDO $pdo, callable $sender, int $limit, ?array $keyConfig = null): array
{
    if ($limit < 1 || $limit > 99) throw new InvalidArgumentException('Limit workeru musí být 1 až 99.');
    transactionalNotificationAssertQueues($pdo);
    $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0, 'auth_processed' => 0, 'event_processed' => 0];
    while ($result['processed'] < $limit) {
        $authFirst = ($result['processed'] % 2) === 0;
        $auth = $authFirst ? authMailProcessOne($pdo, $sender, $keyConfig) : null;
        $event = $auth === null ? clubEventNotificationProcessOne($pdo, $sender) : null;
        if ($auth === null && $event === null && !$authFirst) $auth = authMailProcessOne($pdo, $sender, $keyConfig);
        if ($auth === null && $event === null) break;
        $result['processed']++;
        if ($auth !== null) {
            $result['auth_processed']++;
            $result[in_array($auth, ['sent', 'cancelled'], true) ? $auth : 'failed']++;
        } else {
            $result['event_processed']++;
            $result[$event ? 'sent' : 'failed']++;
        }
    }
    return $result;
}
