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

/** @return array{pending:int,processing:int,failed:int,sent:int} */
function clubEventNotificationAdminSummary(PDO $pdo): array
{
    $summary = ['pending' => 0, 'processing' => 0, 'failed' => 0, 'sent' => 0];
    foreach ($pdo->query(
        'SELECT status,COUNT(*) AS total FROM club_event_notifications GROUP BY status'
    )->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists((string)$row['status'], $summary)) {
            $summary[(string)$row['status']] = (int)$row['total'];
        }
    }
    return $summary;
}

/** @return list<array<string,mixed>> */
function clubEventNotificationAdminList(PDO $pdo, string $status = '', int $limit = 100): array
{
    if (!in_array($status, ['', 'pending', 'processing', 'failed', 'sent'], true)) {
        throw new InvalidArgumentException('Neplatný filtr stavu oznámení.');
    }
    $limit = max(1, min(500, $limit));
    $sql = 'SELECT n.*,e.name AS event_name,s.jmeno AS child_first_name,'
        . 's.prijmeni AS child_last_name FROM club_event_notifications n '
        . 'JOIN club_event_registrations r ON r.id=n.registration_id '
        . 'JOIN club_events e ON e.id=r.event_id JOIN sportovci s ON s.id=r.sportovec_id ';
    $parameters = [];
    if ($status !== '') {
        $sql .= 'WHERE n.status=? ';
        $parameters[] = $status;
    } else {
        $sql .= "WHERE n.status<>'sent' ";
    }
    $sql .= 'ORDER BY CASE n.status WHEN \'failed\' THEN 0 WHEN \'processing\' THEN 1 ELSE 2 END,'
        . 'n.available_at,n.id LIMIT ' . $limit;
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,status:string,changed:bool} */
function clubEventNotificationAdminRetry(
    PDO $pdo,
    int $notificationId,
    int $actorTrainerId,
    string $reason,
    bool $confirmed
): array {
    $reason = trim($reason);
    if ($notificationId < 1 || $actorTrainerId < 1 || $reason === '' || !$confirmed) {
        throw new InvalidArgumentException(
            'Ruční opakování vyžaduje oznámení, administrátora, důvod a výslovné potvrzení.'
        );
    }
    if (mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Důvod smí mít nejvýše 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT id,status,attempts,available_at FROM club_event_notifications WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$notificationId]);
        $notification = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$notification) {
            throw new ClubEventNotificationException('Oznámení nebylo nalezeno.');
        }
        if ($notification['status'] === 'sent') {
            throw new ClubEventNotificationException('Odeslané oznámení nelze znovu zařadit bez nového případu.');
        }
        if ($notification['status'] === 'processing') {
            throw new ClubEventNotificationException(
                'Oznámení právě vlastní worker. Vyčkejte alespoň 15 minut nebo ověřte jeho stav.'
            );
        }
        $alreadyReady = $notification['status'] === 'pending'
            && (int)$notification['attempts'] === 0
            && new DateTimeImmutable((string)$notification['available_at']) <= new DateTimeImmutable('now');
        if ($alreadyReady) {
            $pdo->commit();
            return ['id' => $notificationId, 'status' => 'pending', 'changed' => false];
        }
        $pdo->prepare(
            "UPDATE club_event_notifications SET status='pending',attempts=0,available_at=CURRENT_TIMESTAMP,"
            . 'claimed_at=NULL,claim_token=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$notificationId]);
        $audit = $pdo->prepare(
            'INSERT INTO club_event_notification_events '
            . '(notification_id,actor_trainer_id,action,from_status,attempts_before,reason) '
            . "VALUES (?,?,'manual_retry',?,?,?)"
        );
        $audit->execute([
            $notificationId,
            $actorTrainerId,
            (string)$notification['status'],
            (int)$notification['attempts'],
            $reason,
        ]);
        $pdo->commit();
        return ['id' => $notificationId, 'status' => 'pending', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventNotificationException
        ) {
            throw $exception;
        }
        throw new ClubEventNotificationException('Ruční opakování selhalo bez částečného zápisu.', 0, $exception);
    }
}
