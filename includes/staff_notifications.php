<?php

declare(strict_types=1);

require_once __DIR__ . '/app_url.php';
require_once __DIR__ . '/staff_email_policy.php';

/** Closed registry: never accept table names, routes or recipients from a request. */
function staffNotificationTypes(): array
{
    return ['person_claim' => [
        'position' => 'registrar', 'table' => 'account_person_claim_requests',
        'title' => 'Žádost o propojení nebo registraci sportovce',
        'text' => 'Zkontrolujte žádost a ověřte vztah účtu ke sportovci.',
        'route' => 'eshop_identity_admin.php',
    ]];
}

function staffNotificationType(string $type): array
{
    return staffNotificationTypes()[$type] ?? throw new InvalidArgumentException('Neznámý druh upozornění.');
}

/** Record in the same transaction as the source. Fan-out is deferred to the worker. */
function staffNotificationRecord(PDO $pdo, string $type, int $sourceId): int
{
    staffNotificationType($type);
    if (!$pdo->inTransaction() || $sourceId < 1) {
        throw new LogicException('Upozornění vyžaduje transakci a zdroj.');
    }
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'INSERT INTO staff_notification_events(event_type,source_id) VALUES (?,?) ON DUPLICATE KEY UPDATE id=id'
        : 'INSERT INTO staff_notification_events(event_type,source_id) VALUES (?,?) ON CONFLICT(event_type,source_id) DO NOTHING';
    $pdo->prepare($sql)->execute([$type, $sourceId]);
    $q = $pdo->prepare('SELECT id FROM staff_notification_events WHERE event_type=? AND source_id=?');
    $q->execute([$type, $sourceId]);
    return (int)$q->fetchColumn();
}

function staffNotificationResolve(PDO $pdo, string $type, int $sourceId): void
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Vyřízení upozornění vyžaduje transakci.');
    }
    $pdo->prepare('UPDATE staff_notification_events SET resolved_at=CURRENT_TIMESTAMP WHERE event_type=? AND source_id=? AND resolved_at IS NULL')
        ->execute([$type, $sourceId]);
}

function staffNotificationAuthorized(PDO $pdo, int $trainerId, string $position): bool
{
    $q = $pdo->prepare('SELECT 1 FROM treneri t WHERE t.id=? AND t.aktivni=1 AND '
        . '(EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) '
        . 'OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id))');
    $q->execute([$trainerId, $position]);
    return (bool)$q->fetchColumn();
}

function staffNotificationSourcePending(PDO $pdo, array $event): bool
{
    $type = staffNotificationType((string)$event['event_type']);
    $q = $pdo->prepare('SELECT 1 FROM ' . $type['table'] . " WHERE id=? AND status='pending'");
    $q->execute([(int)$event['source_id']]);
    return (bool)$q->fetchColumn();
}

/** Active role holders, or superadmins when nobody holds the role. */
function staffNotificationRecipients(PDO $pdo, string $position): array
{
    $q = $pdo->prepare('SELECT t.* FROM treneri t WHERE t.aktivni=1 AND EXISTS '
        . '(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code=?) ORDER BY t.id');
    $q->execute([$position]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    return $rows ?: $pdo->query('SELECT t.* FROM treneri t WHERE t.aktivni=1 AND EXISTS '
        . '(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id) ORDER BY t.id')->fetchAll(PDO::FETCH_ASSOC);
}

/** CLI worker only. Backfill pending source records and recover recipients without HTTP writes. */
function staffNotificationSync(PDO $pdo): array
{
    $result = ['events' => 0, 'recipients' => 0, 'emails' => 0, 'without_recipient' => 0, 'invalid_email' => 0];
    foreach (staffNotificationTypes() as $type => $def) {
        $missing = $pdo->prepare('SELECT s.id FROM ' . $def['table'] . " s WHERE s.status='pending' AND NOT EXISTS "
            . '(SELECT 1 FROM staff_notification_events e WHERE e.event_type=? AND e.source_id=s.id) ORDER BY s.id LIMIT 500');
        $missing->execute([$type]);
        foreach ($missing->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $pdo->beginTransaction();
            try {
                staffNotificationRecord($pdo, $type, (int)$id);
                $pdo->commit();
                $result['events']++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                } throw $e;
            }
        }
    }
    // Pagination scans all open events, so an old unresolved task cannot starve new ones.
    $after = 0;
    do {
        $q = $pdo->prepare('SELECT id FROM staff_notification_events WHERE resolved_at IS NULL AND id>? ORDER BY id LIMIT 100');
        $q->execute([$after]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $after = (int)$id;
            $pdo->beginTransaction();
            try {
                $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
                $q = $pdo->prepare('SELECT * FROM staff_notification_events WHERE id=?' . $lock);
                $q->execute([$id]);
                $event = $q->fetch(PDO::FETCH_ASSOC);
                if (!$event || $event['resolved_at'] !== null) {
                    $pdo->commit();
                    continue;
                }
                $def = staffNotificationType((string)$event['event_type']);
                if (!staffNotificationSourcePending($pdo, $event)) {
                    staffNotificationResolve($pdo, $event['event_type'], (int)$event['source_id']);
                    $pdo->commit();
                    continue;
                }
                $recipients = staffNotificationRecipients($pdo, $def['position']);
                if (!$recipients) {
                    $result['without_recipient']++;
                }
                foreach ($recipients as $person) {
                    $q = $pdo->prepare('SELECT * FROM staff_notifications WHERE event_id=? AND trainer_id=?');
                    $q->execute([$id, $person['id']]);
                    $notice = $q->fetch(PDO::FETCH_ASSOC);
                    if (!$notice) {
                        $pdo->prepare('INSERT INTO staff_notifications(event_id,trainer_id) VALUES (?,?)')->execute([$id, $person['id']]);
                        $notice = ['id' => (int)$pdo->lastInsertId(), 'read_at' => null, 'mail_id' => null];
                        $result['recipients']++;
                    }
                    if ($notice['mail_id'] !== null || $notice['read_at'] !== null) {
                        continue;
                    }
                    $email = trim((string)($person['email'] ?? ''));
                    if ((int)($person['technical_email'] ?? 0) === 1 || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
                        $result['invalid_email']++;
                        continue;
                    }
                    $subject = 'KOVO Praha: ' . $def['title'] . ' #' . $event['source_id'];
                    $body = "Dobrý den,\n\n" . $def['text'] . "\nŽádost #" . $event['source_id']
                        . "\n\nOtevřít pracovní upozornění: " . appUrl('staff_notifications.php?notice=' . $notice['id'])
                        . "\n\nPro vyřízení se přihlaste svým pracovním účtem.\nKOVO Praha";
                    $pdo->prepare('INSERT INTO club_event_notifications(dedupe_key,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES (?,?,?,?,?,?)')
                        ->execute(['staff_action:' . $notice['id'], 'staff_action', $email, '', $subject, $body]);
                    $mailId = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE staff_notifications SET mail_id=? WHERE id=?')->execute([$mailId, $notice['id']]);
                    $result['emails']++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                } throw $e;
            }
        }
    } while (count($ids) === 100);
    return $result;
}

/** Restricts every read/write to the recipient AND their current role, across active workspaces. */
function staffNotificationVisibilitySql(): string
{
    $types = [];
    foreach (staffNotificationTypes() as $type => $def) {
        $types[] = "(e.event_type='$type' AND (EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='" . $def['position'] . "') "
            . 'OR EXISTS(SELECT 1 FROM staff_superadmins a WHERE a.trainer_id=t.id)))';
    }
    return 'n.trainer_id=? AND t.aktivni=1 AND (' . implode(' OR ', $types) . ')';
}

function staffNotificationOpenSql(): string
{
    $types = [];
    foreach (staffNotificationTypes() as $type => $def) {
        $types[] = "(e.event_type='$type' AND EXISTS(SELECT 1 FROM " . $def['table'] . " s WHERE s.id=e.source_id AND s.status='pending'))";
    }
    return '(e.resolved_at IS NULL AND (' . implode(' OR ', $types) . '))';
}

function staffNotificationInbox(PDO $pdo, int $trainerId, bool $unreadOnly = false, int $page = 1, int $limit = 25): array
{
    $limit = max(1, min(25, $limit));
    $page = max(1, min(10000, $page));
    $from = ' FROM staff_notifications n JOIN staff_notification_events e ON e.id=n.event_id JOIN treneri t ON t.id=n.trainer_id ';
    $open = staffNotificationOpenSql();
    $where = staffNotificationVisibilitySql();
    $count = $pdo->prepare('SELECT COUNT(*)' . $from . 'WHERE ' . $where . ' AND n.read_at IS NULL AND ' . $open);
    $count->execute([$trainerId]);
    $unread = (int)$count->fetchColumn();
    $q = $pdo->prepare('SELECT n.*,e.event_type,e.source_id,e.resolved_at,CASE WHEN ' . $open . ' THEN 1 ELSE 0 END AS actionable'
        . $from . 'WHERE ' . $where . ($unreadOnly ? ' AND n.read_at IS NULL AND ' . $open : '')
        . ' ORDER BY n.id DESC LIMIT ' . ($limit + 1) . ' OFFSET ' . (($page - 1) * $limit));
    $q->execute([$trainerId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rows) > $limit;
    if ($more) {
        array_pop($rows);
    }
    foreach ($rows as &$row) {
        $def = staffNotificationType($row['event_type']);
        $row['title'] = $def['title'] . ' #' . $row['source_id'];
        $row['text'] = $def['text'];
    }
    return ['unread' => $unread, 'items' => $rows, 'has_next' => $more];
}

function staffNotificationGet(PDO $pdo, int $trainerId, int $noticeId): array
{
    $q = $pdo->prepare('SELECT n.*,e.event_type,e.source_id,e.resolved_at FROM staff_notifications n '
        . 'JOIN staff_notification_events e ON e.id=n.event_id JOIN treneri t ON t.id=n.trainer_id '
        . 'WHERE ' . staffNotificationVisibilitySql() . ' AND n.id=?');
    $q->execute([$trainerId, $noticeId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: throw new DomainException('Upozornění není dostupné pro váš pracovní účet.');
}

function staffNotificationRead(PDO $pdo, int $trainerId, int $noticeId, bool $read): array
{
    $notice = staffNotificationGet($pdo, $trainerId, $noticeId);
    $pdo->prepare('UPDATE staff_notifications SET read_at=' . ($read ? 'CURRENT_TIMESTAMP' : 'NULL') . ' WHERE id=? AND trainer_id=?')
        ->execute([$noticeId, $trainerId]);
    return $notice;
}

/** Rechecked immediately before delivery. No birth date, name or bearer token in the message. */
function staffNotificationDeliveryInvalidReason(PDO $pdo, array $mail): ?string
{
    $q = $pdo->prepare('SELECT n.*,e.event_type,e.source_id,e.resolved_at,t.email,t.aktivni FROM staff_notifications n '
        . 'JOIN staff_notification_events e ON e.id=n.event_id JOIN treneri t ON t.id=n.trainer_id WHERE n.mail_id=?');
    $q->execute([(int)$mail['id']]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || $mail['dedupe_key'] !== 'staff_action:' . $row['id']) {
        return 'missing_staff_context';
    }
    $def = staffNotificationType($row['event_type']);
    if (!staffNotificationAuthorized($pdo, (int)$row['trainer_id'], $def['position'])) {
        return 'staff_authorization_revoked';
    }
    if ($row['email'] !== $mail['recipient_email'] || staffAccountHasTechnicalEmail($pdo, (int)$row['trainer_id'])) {
        return 'staff_recipient_changed';
    }
    if ($row['resolved_at'] !== null || !staffNotificationSourcePending($pdo, $row)) {
        return 'staff_task_resolved';
    }
    if ($row['read_at'] !== null) {
        return 'staff_notice_read';
    }
    return null;
}
