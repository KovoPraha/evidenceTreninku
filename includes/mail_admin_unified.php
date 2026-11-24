<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_request_audit.php';

final class MailAdminException extends RuntimeException
{
}

/** @return array<string,string> */
function mailAdminSources(): array
{
    return [
        'auth' => 'auth_mail_outbox',
        'club' => 'club_event_notifications',
        'roster' => 'roster_message_recipients',
        'weekly' => 'family_weekly_summaries',
        'reminder' => 'member_charge_reminders',
        'monthly' => 'monthly_membership_messages',
    ];
}

function mailAdminAssertActor(int $actorId): void
{
    if ($actorId < 1 || !isset($_SESSION['trener_id']) || (int)$_SESSION['trener_id'] !== $actorId
        || !function_exists('roleAtLeast') || !roleAtLeast('admin')) {
        throw new MailAdminException('K této akci nemáte oprávnění.');
    }
}

function mailAdminEvent(PDO $pdo, ?string $source, ?int $id, int $actorId, string $action, string $reason): void
{
    $pdo->prepare('INSERT INTO mail_admin_events(source_type,source_id,actor_trainer_id,action,reason) VALUES(?,?,?,?,?)')
        ->execute([$source, $id, $actorId, $action, $reason]);
}

/** @return array{enabled_until:?string,active:bool} */
function mailAdminBccSettings(PDO $pdo): array
{
    $row = $pdo->query('SELECT enabled_until FROM mail_admin_bcc_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new MailAdminException('Nastavení skryté kopie není dostupné.');
    }
    $active = (bool)$pdo->query('SELECT 1 FROM mail_admin_bcc_settings WHERE id=1 AND enabled_until>CURRENT_TIMESTAMP')->fetchColumn();
    return ['enabled_until' => $row['enabled_until'], 'active' => $active];
}

function mailAdminBccToggle(PDO $pdo, int $actorId, int $hours, string $reason): void
{
    mailAdminAssertActor($actorId);
    $reason = trim($reason);
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 1000 || $hours < 0 || $hours > 168) {
        throw new InvalidArgumentException('Zadejte důvod a dobu testu nejvýše 168 hodin.');
    }
    $until = $hours === 0 ? null : $pdo->query(
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'SELECT DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . $hours . ' HOUR)'
            : "SELECT datetime(CURRENT_TIMESTAMP, '+" . $hours . " hours')"
    )->fetchColumn();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $pdo->query('SELECT id FROM mail_admin_bcc_settings WHERE id=1' . $lock)->fetchColumn();
        $pdo->prepare('UPDATE mail_admin_bcc_settings SET enabled_until=?,changed_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=1')
            ->execute([$until, $actorId]);
        if ($hours === 0) {
            $pdo->exec('UPDATE mail_admin_bcc SET cancelled_at=CURRENT_TIMESTAMP,skipped_at=CURRENT_TIMESTAMP '
                . 'WHERE cancelled_at IS NULL');
        }
        mailAdminEvent($pdo, null, null, $actorId, $hours === 0 ? 'bcc_disabled' : 'bcc_enabled', $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function mailAdminBccAttach(PDO $pdo, string $source, int $id, string $email, int $actorId, string $reason): void
{
    mailAdminAssertActor($actorId);
    $email = strtolower(trim($email));
    $reason = trim($reason);
    if (!isset(mailAdminSources()[$source]) || $id < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n\x00-\x1f\x7f]/', $email) || strlen($email) > 254
        || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Neplatná zpráva, adresa skryté kopie nebo důvod.');
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $enabledUntil = $pdo->query('SELECT enabled_until FROM mail_admin_bcc_settings WHERE id=1 AND enabled_until>CURRENT_TIMESTAMP' . $lock)->fetchColumn();
        if (!is_string($enabledUntil)) {
            throw new MailAdminException('Testovací skrytá kopie je vypnutá.');
        }
        $table = mailAdminSources()[$source];
        $statement = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE id=?' . $lock);
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || !in_array((string)$row['status'], ['pending', 'failed'], true)) {
            throw new MailAdminException('Skrytou kopii lze přidat jen před odesláním.');
        }
        if ($source === 'auth') {
            require_once __DIR__ . '/auth_mail_outbox.php';
            if (empty($row['body_ciphertext']) || !authMailTokenIsCurrent($pdo, $row)) {
                throw new MailAdminException('Odkaz již není platný; nejprve vydejte nový.');
            }
        }
        $existing = $pdo->prepare('SELECT bcc_email,cancelled_at FROM mail_admin_bcc WHERE source_type=? AND source_id=?');
        $existing->execute([$source, $id]);
        if ($existing->fetch(PDO::FETCH_ASSOC)) {
            throw new MailAdminException('Tato zpráva již má nastavenou nebo zrušenou skrytou kopii.');
        }
        $pdo->prepare('INSERT INTO mail_admin_bcc(source_type,source_id,bcc_email,actor_trainer_id,reason,expires_at) VALUES(?,?,?,?,?,?)')
            ->execute([$source, $id, $email, $actorId, $reason, $enabledUntil]);
        mailAdminEvent($pdo, $source, $id, $actorId, 'bcc_attached', $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function mailAdminBccFor(PDO $pdo, string $source, int $id): ?string
{
    if (!isset(mailAdminSources()[$source]) || $id < 1) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT bcc_email,expires_at FROM mail_admin_bcc WHERE source_type=? AND source_id=? AND cancelled_at IS NULL');
        $statement->execute([$source, $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        // Legacy in-memory worker fixtures predate this optional administration table.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            && str_contains($exception->getMessage(), 'no such table: mail_admin_bcc')) {
            return null;
        }
        throw $exception;
    }
    if (!$row || !is_string($row['bcc_email'])) {
        return null;
    }
    $current = $pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    if (!mailAdminBccSettings($pdo)['active'] || (string)$row['expires_at'] <= (string)$current) {
        $pdo->prepare('UPDATE mail_admin_bcc SET skipped_at=CURRENT_TIMESTAMP WHERE source_type=? AND source_id=? AND skipped_at IS NULL')
            ->execute([$source, $id]);
        return null;
    }
    return (string)$row['bcc_email'];
}

function mailAdminBccAccepted(PDO $pdo, string $source, int $id, ?string $bcc): void
{
    if ($bcc === null) {
        return;
    }
    $pdo->prepare('UPDATE mail_admin_bcc SET accepted_at=CURRENT_TIMESTAMP,accepted_count=accepted_count+1 '
        . 'WHERE source_type=? AND source_id=? AND bcc_email=?')
        ->execute([$source, $id, $bcc]);
}

function mailAdminBccHeader(?string $bcc): string
{
    if ($bcc === null) {
        return '';
    }
    if (!filter_var($bcc, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\x00-\x1f\x7f]/', $bcc)) {
        throw new MailAdminException('Neplatná adresa skryté kopie.');
    }
    return "Bcc: " . $bcc . "\r\n";
}

/** An explicit administrator resend returns an existing non-auth message to its guarded worker. */
function mailAdminResend(PDO $pdo, string $source, int $id, int $actorId, string $reason, bool $confirmed): void
{
    mailAdminAssertActor($actorId);
    $reason = trim($reason);
    if ($source === 'auth') {
        throw new MailAdminException('Odkazy se opakují samostatnou bezpečnou akcí.');
    }
    if (!isset(mailAdminSources()[$source]) || $id < 1 || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000 || !$confirmed) {
        throw new InvalidArgumentException('Vyberte zprávu, zadejte důvod a potvrďte opakování.');
    }
    $table = mailAdminSources()[$source];
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $select = $pdo->prepare('SELECT status FROM ' . $table . ' WHERE id=?' . $lock);
        $select->execute([$id]);
        $status = $select->fetchColumn();
        if (!in_array($status, ['failed', 'sent'], true)) {
            throw new MailAdminException('Opakovat lze pouze selhanou nebo dříve odeslanou zprávu.');
        }
        $set = "status='pending',attempts=0,claim_token=NULL,claimed_at=NULL,last_error=NULL";
        if ($source !== 'monthly') {
            $set .= ',available_at=CURRENT_TIMESTAMP';
        }
        $update = $pdo->prepare('UPDATE ' . $table . ' SET ' . $set . ' WHERE id=? AND status=?');
        $update->execute([$id, $status]);
        if ($update->rowCount() !== 1) {
            throw new MailAdminException('Stav zprávy se mezitím změnil.');
        }
        mailAdminEvent($pdo, $source, $id, $actorId, 'resend_from_' . $status, $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** Requeue an intact failed link, or issue one new public link atomically and only once per original message. */
function mailAdminAuthResend(PDO $pdo, int $id, int $actorId, string $reason, bool $confirmed, ?array $keyConfig = null, ?string $verificationUrl = null, ?string $resetUrl = null): void
{
    mailAdminAssertActor($actorId);
    $reason = trim($reason);
    if ($id < 1 || $reason === '' || mb_strlen($reason, 'UTF-8') > 1000 || !$confirmed) {
        throw new InvalidArgumentException('Vyberte zprávu, zadejte důvod a potvrďte opakování.');
    }
    require_once __DIR__ . '/auth_mail_outbox.php';
    require_once __DIR__ . '/password_reset.php';
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $select = $pdo->prepare('SELECT * FROM auth_mail_outbox WHERE id=?' . $lock);
        $select->execute([$id]);
        $old = $select->fetch(PDO::FETCH_ASSOC);
        if (!$old || !in_array($old['status'], ['failed', 'sent'], true)) {
            throw new MailAdminException('Zpráva už není ve stavu vhodném pro opakování.');
        }
        if ($old['status'] === 'failed' && !empty($old['body_ciphertext']) && authMailTokenIsCurrent($pdo, $old)) {
            $pdo->prepare("UPDATE auth_mail_outbox SET status='pending',attempts=0,available_at=CURRENT_TIMESTAMP,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$id]);
            mailAdminEvent($pdo, 'auth', $id, $actorId, 'retry_valid_link', $reason);
            $pdo->commit();
            return;
        }
        $already = $pdo->prepare('SELECT new_outbox_id FROM mail_admin_reissues WHERE original_outbox_id=?');
        $already->execute([$id]);
        if ($already->fetchColumn()) {
            throw new MailAdminException('K této zprávě už byl vydán nový odkaz.');
        }
        if ($old['target_type'] === 'public') {
            $account = $pdo->prepare('SELECT * FROM verejni_uzivatele WHERE id=?' . $lock);
            $account->execute([(int)$old['target_id']]);
            $target = $account->fetch(PDO::FETCH_ASSOC);
            if (!$target || strcasecmp((string)$target['email'], (string)$old['recipient_email']) !== 0
                || ((int)$target['aktivni'] !== 1 && !($old['kind'] === 'reset' && existingEmailTrustCanActivate($pdo, $target)))
                || (int)$target['email_overeno'] !== ($old['kind'] === 'verify' ? 0 : 1)) {
                throw new MailAdminException('Účet nebo jeho adresa se změnily; nový odkaz nelze vydat.');
            }
            $targetId = (int)$target['id'];
            $email = (string)$target['email'];
        }
        if ($old['target_type'] === 'public' && $old['kind'] === 'verify') {
            $issued = one_time_token_issue(ONE_TIME_TOKEN_EMAIL_VERIFICATION, 3 * 86400);
            $pdo->prepare('UPDATE verejni_uzivatele SET verifikacni_token=?,verifikacni_token_expires_at=? WHERE id=?')
                ->execute([$issued['hash'], $issued['expires_at'], $targetId]);
            $pdo->prepare("UPDATE auth_mail_outbox SET status='cancelled',body_ciphertext=NULL,"
                . "last_error='Nahrazeno novým ověřovacím odkazem.' WHERE kind='verify' "
                . "AND target_type='public' AND target_id=? AND status IN ('pending','failed')")
                ->execute([$targetId]);
            $link = ($verificationUrl ?? appUrl('booking/overeni.php')) . '#token=' . rawurlencode($issued['token']) . '&redirect=moje_osoby.php';
            $newId = authMailEnqueue(
                $pdo,
                'verify',
                'public',
                $targetId,
                $issued['hash'],
                $email,
                'Ověření registrace — Kovopraha',
                "Dobrý den {$target['jmeno']},\n\nPro dokončení registrace otevřete odkaz:\n{$link}\n\nOdkaz je platný 3 dny.",
                $keyConfig
            );
        } elseif ($old['target_type'] === 'public' && $old['kind'] === 'reset') {
            $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, PASSWORD_RESET_EMAIL_TTL_SECONDS);
            passwordResetInsertToken($pdo, ['type' => 'public', 'target_id' => $targetId,
                'delivery_account_id' => $targetId, 'email' => $email], $issued);
            $link = ($resetUrl ?? appUrl('booking/nove_heslo.php')) . '#token=' . rawurlencode($issued['token']);
            $newId = authMailEnqueue(
                $pdo,
                'reset',
                'public',
                $targetId,
                $issued['hash'],
                $email,
                'Obnova hesla — Kovopraha',
                "Pro nastavení nového hesla otevřete odkaz:\n{$link}\n\nOdkaz je platný 3 dny.",
                $keyConfig
            );
        } elseif ($old['target_type'] === 'trainer' && $old['kind'] === 'reset') {
            require_once __DIR__ . '/trainer_password_reset.php';
            $trainer = trainerPasswordResetTarget($pdo, (string)$old['recipient_email'], true);
            if (!$trainer || (int)$trainer['trainer_id'] !== (int)$old['target_id']
                || strcasecmp((string)$trainer['recipient_email'], (string)$old['recipient_email']) !== 0) {
                throw new MailAdminException('Trenérský účet nebo adresa se změnily; nový odkaz nelze vydat.');
            }
            $issued = one_time_token_issue(TRAINER_PASSWORD_RESET_PURPOSE, 3600);
            $pdo->prepare('INSERT INTO trainer_password_reset_tokens(trainer_id,recipient_email,session_version,public_account_id,public_session_version,token_hash,expires_at) VALUES(?,?,?,?,?,?,?)')
                ->execute([
                    $trainer['trainer_id'], $trainer['recipient_email'], $trainer['session_version'],
                    $trainer['public_account_id'], $trainer['public_session_version'],
                    $issued['hash'], $issued['expires_at'],
                ]);
            $url = $resetUrl ?? appUrl('booking/nove_heslo.php') . '?pro=trener';
            $link = $url . '#token=' . rawurlencode($issued['token']);
            $newId = authMailEnqueue(
                $pdo,
                'reset',
                'trainer',
                (int)$trainer['trainer_id'],
                $issued['hash'],
                (string)$trainer['recipient_email'],
                'Obnova trenérského hesla — Kovopraha',
                "Pro nastavení nového hesla k trenérskému účtu otevřete odkaz:\n{$link}\n\nOdkaz je platný 60 minut. Pokud jste o změnu nežádali, zprávu ignorujte.",
                $keyConfig
            );
        } elseif ($old['target_type'] === 'child' && $old['kind'] === 'reset') {
            $child = $pdo->prepare('SELECT login_key FROM child_access_accounts WHERE id=?' . $lock);
            $child->execute([(int)$old['target_id']]);
            $login = $child->fetchColumn();
            $contact = is_string($login) ? passwordResetResolveTarget($pdo, $login) : null;
            if (!$contact || $contact['type'] !== 'child' || (int)$contact['target_id'] !== (int)$old['target_id']
                || strcasecmp((string)$contact['email'], (string)$old['recipient_email']) !== 0) {
                throw new MailAdminException('Oprávněný kontakt dítěte se změnil; nový odkaz nelze vydat.');
            }
            $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, PASSWORD_RESET_EMAIL_TTL_SECONDS);
            passwordResetInsertToken($pdo, $contact, $issued);
            $link = ($resetUrl ?? appUrl('booking/nove_heslo.php')) . '#token=' . rawurlencode($issued['token']);
            $newId = authMailEnqueue(
                $pdo,
                'reset',
                'child',
                (int)$contact['target_id'],
                $issued['hash'],
                (string)$contact['email'],
                'Obnova hesla — Kovopraha',
                "Pro nastavení nového hesla otevřete odkaz:\n{$link}\n"
                    . (isset($contact['login_name']) ? "\nPřihlašovací jméno sportovce: {$contact['login_name']}\n" : '')
                    . "\nOdkaz je platný 3 dny.",
                $keyConfig
            );
        } else {
            throw new MailAdminException('Neznámý typ bezpečnostní zprávy.');
        }
        $pdo->prepare('INSERT INTO mail_admin_reissues(original_outbox_id,new_outbox_id,actor_trainer_id) VALUES(?,?,?)')
            ->execute([$id, $newId, $actorId]);
        if ($old['status'] === 'failed') {
            $pdo->prepare("UPDATE auth_mail_outbox SET status='cancelled',body_ciphertext=NULL,last_error='Nahrazeno novým odkazem.' WHERE id=?")
                ->execute([$id]);
        }
        mailAdminEvent($pdo, 'auth', $id, $actorId, 'new_link_' . $newId, $reason);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @return array{rows:list<array<string,mixed>>,total:int,page:int} */
function mailAdminUnifiedList(PDO $pdo, array $filters = []): array
{
    mailAdminAssertActor((int)($_SESSION['trener_id'] ?? 0));
    $source = (string)($filters['source'] ?? '');
    $status = (string)($filters['status'] ?? '');
    $email = strtolower(trim((string)($filters['email'] ?? '')));
    $reference = strtolower(trim((string)($filters['reference'] ?? '')));
    $page = max(1, min(100000, (int)($filters['page'] ?? 1)));
    if ($source !== '' && !in_array($source, array_merge(array_keys(mailAdminSources()), ['registration_request','verify_request','reset_request']), true)) {
        throw new InvalidArgumentException('Neplatný zdroj e-mailů.');
    }
    if ($status !== '' && !in_array($status, ['received','rejected','queued','pending','processing','sent','failed','cancelled'], true)) {
        throw new InvalidArgumentException('Neplatný stav e-mailů.');
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254)) {
        throw new InvalidArgumentException('Zadejte úplnou platnou e-mailovou adresu.');
    }
    if ($reference !== '' && preg_match('/^[a-z0-9_-]{1,80}$/D', $reference) !== 1) {
        throw new InvalidArgumentException('Neplatná reference.');
    }
    $union = <<<'SQL'
        SELECT 'auth' AS source,o.id AS source_id,o.kind AS kind,o.recipient_email AS recipient_email,
        o.status AS status,o.attempts AS attempts,o.available_at AS available_at,o.sent_at AS sent_at,
        o.last_error AS reason,o.created_at AS created_at,COALESCE(r.request_id,p.request_id) AS reference,
        NULL AS identifier_hash
        FROM auth_mail_outbox o LEFT JOIN mail_request_audit r ON r.outbox_id=o.id LEFT JOIN password_reset_request_audit p ON p.outbox_id=o.id
        UNION ALL SELECT 'club',id,notification_type,recipient_email,status,attempts,available_at,sent_at,last_error,created_at,NULL,NULL FROM club_event_notifications
        UNION ALL SELECT 'roster',id,'roster_message',recipient_email,status,attempts,available_at,sent_at,last_error,created_at,NULL,NULL FROM roster_message_recipients
        UNION ALL SELECT 'weekly',id,'family_weekly',recipient_email,status,attempts,available_at,sent_at,last_error,created_at,NULL,NULL FROM family_weekly_summaries
        UNION ALL SELECT 'reminder',id,reminder_type,recipient_email,status,attempts,available_at,sent_at,last_error,created_at,NULL,NULL FROM member_charge_reminders
        UNION ALL SELECT 'monthly',m.id,m.kind,a.email,m.status,m.attempts,NULL,m.sent_at,m.last_error,
        m.created_at,NULL,NULL FROM monthly_membership_messages m JOIN verejni_uzivatele a ON a.id=m.account_id
        UNION ALL SELECT CASE WHEN kind='registration' THEN 'registration_request' ELSE 'verify_request' END,
        0,kind,NULL,outcome,0,NULL,NULL,reason_code,created_at,request_id,identifier_hash
        FROM mail_request_audit WHERE outbox_id IS NULL
        UNION ALL SELECT 'reset_request',0,audience,NULL,outcome,0,NULL,NULL,reason_code,created_at,request_id,identifier_hash FROM password_reset_request_audit WHERE outbox_id IS NULL
        SQL;
    $where = [];
    $params = [];
    if ($source !== '') {
        $where[] = 'feed.source=?';
        $params[] = $source;
    }
    if ($status !== '') {
        $where[] = 'feed.status=?';
        $params[] = $status;
    }
    if ($email !== '') {
        $where[] = '(LOWER(feed.recipient_email)=? OR feed.identifier_hash=?)';
        $params[] = $email;
        $params[] = mailRequestIdentifierHash($email);
    }
    if ($reference !== '') {
        if (preg_match('/^(auth|club|roster|weekly|reminder|monthly)-([1-9][0-9]*)$/D', $reference, $match) === 1) {
            $where[] = '(feed.source=? AND feed.source_id=?)';
            $params[] = $match[1];
            $params[] = (int)$match[2];
        } else {
            $where[] = 'feed.reference=?';
            $params[] = $reference;
        }
    }
    $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM (' . $union . ') feed' . $condition);
    $count->execute($params);
    $statement = $pdo->prepare('SELECT feed.source,feed.source_id,feed.kind,feed.recipient_email,feed.status,'
        . 'feed.attempts,feed.available_at,feed.sent_at,feed.reason,feed.created_at,feed.reference,'
        . 'b.bcc_email,b.accepted_at AS bcc_accepted_at,b.accepted_count AS bcc_accepted_count,'
        . 'b.skipped_at AS bcc_skipped_at,'
        . 'b.cancelled_at AS bcc_cancelled_at FROM (' . $union . ') feed'
        . ' LEFT JOIN mail_admin_bcc b ON b.source_type=feed.source AND b.source_id=feed.source_id'
        . $condition . ' ORDER BY feed.created_at DESC,feed.source DESC,feed.source_id DESC LIMIT 50 OFFSET ' . (($page - 1) * 50));
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        if ($row['reference'] === null) {
            $row['reference'] = $row['source'] . '-' . $row['source_id'];
        }
    }
    unset($row);
    return ['rows' => $rows, 'total' => (int)$count->fetchColumn(), 'page' => $page];
}
