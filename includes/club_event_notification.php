<?php
declare(strict_types=1);

final class ClubEventNotificationException extends RuntimeException
{
}

function clubEventNotificationEnqueuePromotion(PDO $pdo, int $registrationId, int $registrationEventId): int
{
    if (!$pdo->inTransaction() || $registrationId < 1 || $registrationEventId < 1) {
        throw new LogicException('Oznámení o povýšení vyžaduje aktivní registrační transakci.');
    }
    $statement = $pdo->prepare(
        'SELECT r.id,e.name,vu.email,vu.jmeno,vu.prijmeni,s.jmeno AS child_first_name,'
        . 's.prijmeni AS child_last_name FROM club_event_registrations r '
        . 'JOIN club_events e ON e.id=r.event_id JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id JOIN club_event_registration_events re '
        . "ON re.id=? AND re.registration_id=r.id AND re.action='promote_waitlist' "
        . "WHERE r.id=? AND r.status='confirmed'"
    );
    $statement->execute([$registrationEventId, $registrationId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row || !filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL)) {
        throw new ClubEventNotificationException('Povýšenou přihlášku nelze zařadit k oznámení.');
    }
    $recipientName = trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']);
    $childName = trim((string)$row['child_first_name'] . ' ' . (string)$row['child_last_name']);
    $subject = 'Uvolnilo se místo: ' . (string)$row['name'];
    $body = "Dobrý den" . ($recipientName !== '' ? ' ' . $recipientName : '') . ",\n\n"
        . "pro " . $childName . " se uvolnilo místo na akci „" . (string)$row['name'] . "“. "
        . "Přihláška byla automaticky potvrzena.\n\nKlub KOVO Praha";
    try {
        $insert = $pdo->prepare(
            'INSERT INTO club_event_notifications '
            . '(registration_id,registration_event_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) '
            . "VALUES (?,?,'waitlist_promoted',?,?,?,?)"
        );
        $insert->execute([
            $registrationId, $registrationEventId, (string)$row['email'], $recipientName, $subject, $body,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') {
            throw $exception;
        }
        $existing = $pdo->prepare(
            "SELECT id FROM club_event_notifications WHERE registration_event_id=? AND notification_type='waitlist_promoted'"
        );
        $existing->execute([$registrationEventId]);
        $id = (int)$existing->fetchColumn();
        if ($id < 1) {
            throw $exception;
        }
        return $id;
    }
}

/** @return array<string,mixed>|null */
function clubEventNotificationClaim(PDO $pdo): ?array
{
    $pdo->beginTransaction();
    try {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $staleExpression = $mysql
            ? 'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE'
            : "datetime('now','-15 minutes')";
        $sql = "SELECT * FROM club_event_notifications WHERE "
            . "(status='pending' AND available_at<=CURRENT_TIMESTAMP) OR "
            . "(status='processing' AND claimed_at<" . $staleExpression . ') ORDER BY id LIMIT 1';
        if ($mysql) {
            $sql .= ' FOR UPDATE';
        }
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $update = $pdo->prepare(
            "UPDATE club_event_notifications SET status='processing',attempts=attempts+1,"
            . 'claimed_at=CURRENT_TIMESTAMP,claim_token=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([$token, (int)$row['id']]);
        $pdo->commit();
        $row['claim_token'] = $token;
        $row['attempts'] = (int)$row['attempts'] + 1;
        return $row;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function clubEventNotificationComplete(PDO $pdo, int $id, string $token, bool $sent, ?string $error = null): void
{
    if ($id < 1 || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
        throw new InvalidArgumentException('Neplatné převzetí oznámení.');
    }
    if ($sent) {
        $statement = $pdo->prepare(
            "UPDATE club_event_notifications SET status='sent',sent_at=CURRENT_TIMESTAMP,"
            . 'claim_token=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP '
            . "WHERE id=? AND status='processing' AND claim_token=?"
        );
        $statement->execute([$id, $token]);
    } else {
        $attempts = $pdo->prepare('SELECT attempts FROM club_event_notifications WHERE id=? AND claim_token=?');
        $attempts->execute([$id, $token]);
        $attempt = (int)$attempts->fetchColumn();
        $terminal = $attempt >= 5;
        $delay = min(3600, 300 * max(1, $attempt));
        $safeError = mb_substr(trim((string)$error), 0, 500, 'UTF-8');
        $available = (new DateTimeImmutable('now +' . $delay . ' seconds'))->format('Y-m-d H:i:s');
        $statement = $pdo->prepare(
            'UPDATE club_event_notifications SET status=?,available_at=?,claim_token=NULL,last_error=?,'
            . 'updated_at=CURRENT_TIMESTAMP WHERE id=? AND status=\'processing\' AND claim_token=?'
        );
        $statement->execute([$terminal ? 'failed' : 'pending', $available, $safeError, $id, $token]);
    }
    if ($statement->rowCount() !== 1) {
        throw new ClubEventNotificationException('Oznámení už nevlastní tento proces.');
    }
}

/** @param callable(string,string,string):bool $sender */
function clubEventNotificationProcessOne(PDO $pdo, callable $sender): ?bool
{
    $notification = clubEventNotificationClaim($pdo);
    if ($notification === null) {
        return null;
    }
    try {
        $sent = $sender(
            (string)$notification['recipient_email'],
            (string)$notification['subject_plain'],
            (string)$notification['body_plain']
        );
        clubEventNotificationComplete(
            $pdo,
            (int)$notification['id'],
            (string)$notification['claim_token'],
            $sent,
            $sent ? null : 'Transport odmítl zprávu.'
        );
        return $sent;
    } catch (Throwable $exception) {
        clubEventNotificationComplete(
            $pdo,
            (int)$notification['id'],
            (string)$notification['claim_token'],
            false,
            'Transport vyvolal chybu typu ' . get_class($exception) . '.'
        );
        return false;
    }
}

function clubEventNotificationMailSender(string $recipient, string $subject, string $body): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $recipient . $subject) === 1
    ) {
        throw new ClubEventNotificationException('Oznámení obsahuje neplatnou e-mailovou hlavičku.');
    }
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return mail($recipient, $encodedSubject, $body, "Content-Type: text/plain; charset=UTF-8\r\n");
}
