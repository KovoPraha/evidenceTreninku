<?php

declare(strict_types=1);

require_once __DIR__ . '/staff_notifications.php';
require_once __DIR__ . '/club_event_notification.php';

/** The hosting job processes staff actions only; other mail queues keep their existing lifecycle. */
function staffNotificationDrain(PDO $pdo, callable $sender, int $limit = 20): array
{
    if ($limit < 1 || $limit > 99) {
        throw new InvalidArgumentException('Invalid staff worker limit.');
    }
    $result = ['sync' => staffNotificationSync($pdo), 'processed' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    while ($result['processed'] < $limit) {
        $sent = clubEventNotificationProcessOne($pdo, $sender, true);
        if ($sent === null) {
            break;
        }
        $result['processed']++;
        $result[$sent === 'cancelled' ? 'cancelled' : ($sent ? 'sent' : 'failed')]++;
    }
    return $result;
}

/** Aggregate-only operational evidence; no addresses or message bodies in Actions logs. */
function staffNotificationWorkerStatus(PDO $pdo): array
{
    $counts = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    $rows = $pdo->query("SELECT status,COUNT(*) AS total FROM club_event_notifications WHERE notification_type='staff_action' GROUP BY status");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['status']] = (int)$row['total'];
    }
    $recipients = staffNotificationRecipients($pdo, 'registrar');
    return [
        'queue' => $counts,
        'pending_requests' => (int)$pdo->query("SELECT COUNT(*) FROM account_person_claim_requests WHERE status='pending'")->fetchColumn(),
        'events' => (int)$pdo->query('SELECT COUNT(*) FROM staff_notification_events')->fetchColumn(),
        'notices' => (int)$pdo->query('SELECT COUNT(*) FROM staff_notifications')->fetchColumn(),
        'recipient_trainer_ids' => array_map(static fn ($r) => (int)$r['id'], $recipients),
        'last_sent_at' => $pdo->query("SELECT MAX(sent_at) FROM club_event_notifications WHERE notification_type='staff_action' AND status='sent'")->fetchColumn() ?: null,
    ];
}
