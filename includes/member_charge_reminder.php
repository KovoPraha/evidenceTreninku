<?php
declare(strict_types=1);

final class MemberChargeReminderException extends RuntimeException
{
}

function memberChargeReminderTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

/** @return array{enabled:bool,days_before:int} */
function memberChargeReminderPreference(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !memberChargeReminderTableExists($pdo, 'member_charge_reminder_preferences')) {
        return ['enabled' => false, 'days_before' => 7];
    }
    $statement = $pdo->prepare('SELECT enabled,days_before FROM member_charge_reminder_preferences WHERE account_id=?');
    $statement->execute([$accountId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ? ['enabled' => (int)$row['enabled'] === 1, 'days_before' => (int)$row['days_before']]
        : ['enabled' => false, 'days_before' => 7];
}

/** @return array{pending:int,processing:int,sent:int,failed:int,cancelled:int} */
function memberChargeReminderAccountSummary(PDO $pdo, int $accountId): array
{
    $summary = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    if ($accountId < 1 || !memberChargeReminderTableExists($pdo, 'member_charge_reminders')) return $summary;
    $statement = $pdo->prepare('SELECT status,COUNT(*) AS total FROM member_charge_reminders WHERE account_id=? GROUP BY status');
    $statement->execute([$accountId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists((string)$row['status'], $summary)) $summary[(string)$row['status']] = (int)$row['total'];
    }
    return $summary;
}

/** @return array{enabled:bool,days_before:int,changed:bool} */
function memberChargeReminderSavePreference(PDO $pdo, int $accountId, bool $enabled, int $daysBefore): array
{
    if ($accountId < 1 || !in_array($daysBefore, [3, 7, 14], true)
        || !memberChargeReminderTableExists($pdo, 'member_charge_reminder_preferences')
        || !memberChargeReminderTableExists($pdo, 'member_charge_reminder_events')
    ) {
        throw new InvalidArgumentException('Neplatné nastavení připomínek.');
    }
    $current = memberChargeReminderPreference($pdo, $accountId);
    $changed = $current['enabled'] !== $enabled || $current['days_before'] !== $daysBefore;
    if (!$changed) return ['enabled' => $enabled, 'days_before' => $daysBefore, 'changed' => false];
    $pdo->beginTransaction();
    try {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->prepare(
                'INSERT INTO member_charge_reminder_preferences(account_id,enabled,days_before) VALUES(?,?,?) '
                . 'ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),days_before=VALUES(days_before),updated_at=CURRENT_TIMESTAMP'
            )->execute([$accountId, $enabled ? 1 : 0, $daysBefore]);
        } else {
            $pdo->prepare(
                'INSERT INTO member_charge_reminder_preferences(account_id,enabled,days_before) VALUES(?,?,?) '
                . 'ON CONFLICT(account_id) DO UPDATE SET enabled=excluded.enabled,days_before=excluded.days_before,updated_at=CURRENT_TIMESTAMP'
            )->execute([$accountId, $enabled ? 1 : 0, $daysBefore]);
        }
        $pdo->prepare(
            'INSERT INTO member_charge_reminder_events(reminder_id,account_id,action,from_status,to_status,note) '
            . 'VALUES(NULL,?,?,?,?,?)'
        )->execute([
            $accountId, 'preference_change', $current['enabled'] ? 'enabled' : 'disabled',
            $enabled ? 'enabled' : 'disabled', 'Předstih: ' . $daysBefore . ' dní.',
        ]);
        if (!$enabled && memberChargeReminderTableExists($pdo, 'member_charge_reminders')) {
            $pending = $pdo->prepare("SELECT id,status FROM member_charge_reminders WHERE account_id=? AND status IN ('pending','failed')");
            $pending->execute([$accountId]);
            foreach ($pending->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pdo->prepare(
                    "UPDATE member_charge_reminders SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,"
                    . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
                )->execute([(int)$row['id']]);
                memberChargeReminderAudit($pdo, (int)$row['id'], $accountId, 'cancel_opt_out', (string)$row['status'], 'cancelled', 'Uživatel vypnul připomínky.');
            }
        }
        $pdo->commit();
        return ['enabled' => $enabled, 'days_before' => $daysBefore, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw new MemberChargeReminderException('Nastavení připomínek se nepodařilo uložit.', 0, $exception);
    }
}

/** @return array{queued:int,existing:int,skipped:int} */
function memberChargeReminderGenerate(PDO $pdo, ?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    foreach (['member_charge_reminder_preferences', 'member_charge_reminders', 'member_charge_reminder_events',
        'club_member_charges', 'account_person_roles', 'verejni_uzivatele', 'sportovci'] as $table) {
        if (!memberChargeReminderTableExists($pdo, $table)) return ['queued' => 0, 'existing' => 0, 'skipped' => 0];
    }
    $statement = $pdo->prepare(
        'SELECT DISTINCT p.account_id,p.days_before,a.email,a.jmeno AS account_first_name,a.prijmeni AS account_last_name,'
        . 'c.id AS charge_id,c.title_snapshot,c.amount_minor,c.currency,c.due_on,s.jmeno AS child_first_name,s.prijmeni AS child_last_name '
        . 'FROM member_charge_reminder_preferences p JOIN verejni_uzivatele a ON a.id=p.account_id '
        . 'JOIN account_person_roles r ON r.account_id=p.account_id '
        . 'JOIN club_member_charges c ON c.sportovec_id=r.sportovec_id JOIN sportovci s ON s.id=c.sportovec_id '
        . "WHERE p.enabled=1 AND a.aktivni=1 AND a.email_overeno=1 AND r.status='approved' "
        . "AND r.relation_role IN ('self','guardian') AND r.valid_from<=CURRENT_TIMESTAMP "
        . 'AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . "AND c.status='pending' AND c.due_on BETWEEN ? AND ? ORDER BY p.account_id,c.due_on,c.id"
    );
    $statement->execute([$today->format('Y-m-d'), $today->modify('+14 days')->format('Y-m-d')]);
    $queued = $existing = $skipped = 0;
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dueOn = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$row['due_on'], new DateTimeZone('Europe/Prague'));
        if (!$dueOn || $dueOn > $today->modify('+' . (int)$row['days_before'] . ' days')
            || !filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $skipped++;
            continue;
        }
        $accountId = (int)$row['account_id'];
        $chargeId = (int)$row['charge_id'];
        $recipientName = trim((string)$row['account_first_name'] . ' ' . (string)$row['account_last_name']);
        $childName = trim((string)$row['child_first_name'] . ' ' . (string)$row['child_last_name']);
        $amount = number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . (string)$row['currency'];
        $link = memberChargeReminderAccountUrl();
        $subject = 'Připomínka platby: ' . (string)$row['title_snapshot'];
        $body = 'Dobrý den' . ($recipientName !== '' ? ' ' . $recipientName : '') . ",\n\n"
            . 'připomínáme blížící se splatnost „' . (string)$row['title_snapshot'] . '“ pro ' . $childName . ".\n"
            . 'Splatnost: ' . $dueOn->format('d. m. Y') . "\nČástka: " . $amount . "\n\n"
            . 'Přehled plateb po přihlášení: ' . $link . "\n\nKlub KOVO Praha";
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO member_charge_reminders(charge_id,account_id,reminder_type,recipient_email,recipient_name,subject_plain,body_plain) '
                . "VALUES(?,?,'due_soon',?,?,?,?)"
            );
            $insert->execute([$chargeId, $accountId, (string)$row['email'], $recipientName, $subject, $body]);
            $id = (int)$pdo->lastInsertId();
            memberChargeReminderAudit($pdo, $id, $accountId, 'enqueue', null, 'pending', 'Připomínka byla idempotentně zařazena.');
            $pdo->commit();
            $queued++;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$exception->getCode() !== '23000') throw $exception;
            $existing++;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
    return compact('queued', 'existing', 'skipped');
}

/** @return array<string,mixed>|null */
function memberChargeReminderClaim(PDO $pdo): ?array
{
    $pdo->beginTransaction();
    try {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $stale = $mysql ? 'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE' : "datetime('now','-15 minutes')";
        $recent = $mysql ? 'CURRENT_TIMESTAMP - INTERVAL 20 HOUR' : "datetime('now','-20 hours')";
        $sql = 'SELECT n.* FROM member_charge_reminders n '
            . 'JOIN member_charge_reminder_preferences p ON p.account_id=n.account_id '
            . 'JOIN club_member_charges c ON c.id=n.charge_id '
            . "WHERE p.enabled=1 AND c.status='pending' AND ((n.status='pending' AND n.available_at<=CURRENT_TIMESTAMP) "
            . "OR (n.status='processing' AND n.claimed_at<" . $stale . ')) '
            . "AND NOT EXISTS(SELECT 1 FROM member_charge_reminders sent WHERE sent.account_id=n.account_id "
            . "AND sent.status='sent' AND sent.sent_at>" . $recent . ') '
            . "AND NOT EXISTS(SELECT 1 FROM member_charge_reminders busy WHERE busy.account_id=n.account_id "
            . "AND busy.id<>n.id AND busy.status='processing' AND busy.claimed_at>=" . $stale . ') '
            . 'ORDER BY n.available_at,n.id LIMIT 1';
        if ($mysql) $sql .= ' FOR UPDATE';
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $pdo->prepare(
            "UPDATE member_charge_reminders SET status='processing',attempts=attempts+1,claimed_at=CURRENT_TIMESTAMP,"
            . 'claim_token=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$token, (int)$row['id']]);
        memberChargeReminderAudit($pdo, (int)$row['id'], (int)$row['account_id'], 'claim', (string)$row['status'], 'processing', 'Zprávu převzal worker.');
        $pdo->commit();
        $row['claim_token'] = $token;
        $row['attempts'] = (int)$row['attempts'] + 1;
        return $row;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function memberChargeReminderComplete(PDO $pdo, int $id, string $token, bool $sent, ?string $error = null): void
{
    if ($id < 1 || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) throw new InvalidArgumentException('Neplatné převzetí připomínky.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT account_id,status,attempts FROM member_charge_reminders WHERE id=? AND claim_token=?');
        $statement->execute([$id, $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)$row['status'] !== 'processing') throw new MemberChargeReminderException('Připomínku již nevlastní tento proces.');
        if ($sent) {
            $pdo->prepare(
                "UPDATE member_charge_reminders SET status='sent',sent_at=CURRENT_TIMESTAMP,claim_token=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?"
            )->execute([$id]);
            memberChargeReminderAudit($pdo, $id, (int)$row['account_id'], 'sent', 'processing', 'sent', 'Transport potvrdil odeslání.');
        } else {
            $terminal = (int)$row['attempts'] >= 5;
            $next = $terminal ? 'failed' : 'pending';
            $available = (new DateTimeImmutable('now +' . min(3600, 300 * max(1, (int)$row['attempts'])) . ' seconds'))->format('Y-m-d H:i:s');
            $safeError = mb_substr(trim((string)$error), 0, 500, 'UTF-8');
            $pdo->prepare(
                'UPDATE member_charge_reminders SET status=?,available_at=?,claim_token=NULL,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([$next, $available, $safeError, $id]);
            memberChargeReminderAudit($pdo, $id, (int)$row['account_id'], 'send_failed', 'processing', $next, $safeError !== '' ? $safeError : 'Transport odmítl zprávu.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** @param callable(string,string,string):bool $sender */
function memberChargeReminderProcessOne(PDO $pdo, callable $sender): ?bool
{
    $row = memberChargeReminderClaim($pdo);
    if ($row === null) return null;
    try {
        $sent = $sender((string)$row['recipient_email'], (string)$row['subject_plain'], (string)$row['body_plain']);
        memberChargeReminderComplete($pdo, (int)$row['id'], (string)$row['claim_token'], $sent, $sent ? null : 'Transport odmítl zprávu.');
        return $sent;
    } catch (Throwable $exception) {
        memberChargeReminderComplete($pdo, (int)$row['id'], (string)$row['claim_token'], false, 'Transport vyvolal chybu typu ' . get_class($exception) . '.');
        return false;
    }
}

function memberChargeReminderMailSender(string $recipient, string $subject, string $body): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient . $subject) === 1) {
        throw new MemberChargeReminderException('Připomínka obsahuje neplatnou e-mailovou hlavičku.');
    }
    return mail($recipient, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, "Content-Type: text/plain; charset=UTF-8\r\n");
}

function memberChargeReminderAccountUrl(): string
{
    return defined('JE_LOKALNE') && JE_LOKALNE === true
        ? 'http://localhost/evidencePavel/booking/sportovni_prehled.php'
        : 'https://data.kovopraha.cz/evidence/booking/sportovni_prehled.php';
}

function memberChargeReminderAudit(PDO $pdo, ?int $reminderId, int $accountId, string $action, ?string $from, ?string $to, string $note): void
{
    $pdo->prepare(
        'INSERT INTO member_charge_reminder_events(reminder_id,account_id,action,from_status,to_status,note) VALUES(?,?,?,?,?,?)'
    )->execute([$reminderId, $accountId, $action, $from, $to, mb_substr($note, 0, 500, 'UTF-8')]);
}
