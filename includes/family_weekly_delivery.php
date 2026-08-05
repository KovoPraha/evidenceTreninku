<?php
declare(strict_types=1);

require_once __DIR__ . '/family_weekly_summary.php';
require_once __DIR__ . '/local_message_outbox.php';

final class FamilyWeeklyDeliveryException extends RuntimeException
{
}

function familyWeeklyDeliveryTableExists(PDO $pdo, string $table): bool
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

/** @return array{enabled:bool} */
function familyWeeklyDeliveryPreference(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !familyWeeklyDeliveryTableExists($pdo, 'family_weekly_summary_preferences')) {
        return ['enabled' => false];
    }
    $statement = $pdo->prepare('SELECT enabled FROM family_weekly_summary_preferences WHERE account_id=?');
    $statement->execute([$accountId]);
    return ['enabled' => (int)$statement->fetchColumn() === 1];
}

/** @return array{pending:int,processing:int,sent:int,failed:int,cancelled:int} */
function familyWeeklyDeliveryAccountSummary(PDO $pdo, int $accountId): array
{
    $summary = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    if ($accountId < 1 || !familyWeeklyDeliveryTableExists($pdo, 'family_weekly_summaries')) return $summary;
    $statement = $pdo->prepare('SELECT status,COUNT(*) AS total FROM family_weekly_summaries WHERE account_id=? GROUP BY status');
    $statement->execute([$accountId]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists((string)$row['status'], $summary)) $summary[(string)$row['status']] = (int)$row['total'];
    }
    return $summary;
}

function familyWeeklyDeliveryAudit(
    PDO $pdo,
    ?int $summaryId,
    int $accountId,
    string $action,
    ?string $from,
    ?string $to,
    string $note,
    string $actorType = 'system',
    ?int $actorId = null
): void {
    $pdo->prepare(
        'INSERT INTO family_weekly_summary_events(summary_id,account_id,actor_type,actor_id,action,from_status,to_status,note) '
        . 'VALUES(?,?,?,?,?,?,?,?)'
    )->execute([$summaryId, $accountId, $actorType, $actorId, $action, $from, $to, mb_substr(trim($note), 0, 500, 'UTF-8')]);
}

/** @return array{enabled:bool,changed:bool,cancelled:int} */
function familyWeeklyDeliverySavePreference(PDO $pdo, int $accountId, bool $enabled): array
{
    foreach (['family_weekly_summary_preferences', 'family_weekly_summaries', 'family_weekly_summary_events'] as $table) {
        if ($accountId < 1 || !familyWeeklyDeliveryTableExists($pdo, $table)) {
            throw new InvalidArgumentException('Týdenní souhrny zatím nejsou v databázi připravené.');
        }
    }
    $current = familyWeeklyDeliveryPreference($pdo, $accountId);
    if ($current['enabled'] === $enabled) return ['enabled' => $enabled, 'changed' => false, 'cancelled' => 0];

    $pdo->beginTransaction();
    try {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $sql = $mysql
            ? 'INSERT INTO family_weekly_summary_preferences(account_id,enabled) VALUES(?,?) '
                . 'ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=CURRENT_TIMESTAMP'
            : 'INSERT INTO family_weekly_summary_preferences(account_id,enabled) VALUES(?,?) '
                . 'ON CONFLICT(account_id) DO UPDATE SET enabled=excluded.enabled,updated_at=CURRENT_TIMESTAMP';
        $pdo->prepare($sql)->execute([$accountId, $enabled ? 1 : 0]);
        familyWeeklyDeliveryAudit(
            $pdo, null, $accountId, 'preference_change',
            $current['enabled'] ? 'enabled' : 'disabled', $enabled ? 'enabled' : 'disabled',
            $enabled ? 'Uživatel zapnul týdenní rodinný souhrn.' : 'Uživatel vypnul týdenní rodinný souhrn.',
            'account', $accountId
        );
        $cancelled = 0;
        if (!$enabled) {
            $rows = $pdo->prepare("SELECT id,status FROM family_weekly_summaries WHERE account_id=? AND status IN ('pending','processing','failed')");
            $rows->execute([$accountId]);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pdo->prepare(
                    "UPDATE family_weekly_summaries SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,claim_token=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?"
                )->execute([(int)$row['id']]);
                familyWeeklyDeliveryAudit($pdo, (int)$row['id'], $accountId, 'cancel_opt_out', (string)$row['status'], 'cancelled', 'Neodeslaný souhrn byl zrušen po odhlášení odběru.', 'account', $accountId);
                $cancelled++;
            }
        }
        $pdo->commit();
        return ['enabled' => $enabled, 'changed' => true, 'cancelled' => $cancelled];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw new FamilyWeeklyDeliveryException('Nastavení týdenního souhrnu se nepodařilo uložit.', 0, $exception);
    }
}

/**
 * @param null|callable(PDO,int,string):array<string,mixed> $previewFactory
 * @return array{due:bool,queued:int,existing:int,skipped:int,period_from:string}
 */
function familyWeeklyDeliveryGenerate(
    PDO $pdo,
    ?DateTimeImmutable $today = null,
    bool $force = false,
    ?callable $previewFactory = null
): array {
    $today ??= new DateTimeImmutable('today', new DateTimeZone('Europe/Prague'));
    $today = $today->setTimezone(new DateTimeZone('Europe/Prague'))->setTime(0, 0);
    $periodFrom = $today->format('Y-m-d');
    $result = ['due' => $force || $today->format('N') === '1', 'queued' => 0, 'existing' => 0, 'skipped' => 0, 'period_from' => $periodFrom];
    if (!$result['due']) return $result;
    foreach (['family_weekly_summary_preferences', 'family_weekly_summaries', 'family_weekly_summary_events', 'verejni_uzivatele'] as $table) {
        if (!familyWeeklyDeliveryTableExists($pdo, $table)) return $result;
    }
    $previewFactory ??= static fn (PDO $database, int $accountId, string $from): array => familyWeeklySummaryPreview($database, $accountId, $from);
    $accounts = $pdo->query(
        'SELECT p.account_id,a.email,a.jmeno,a.prijmeni FROM family_weekly_summary_preferences p '
        . 'JOIN verejni_uzivatele a ON a.id=p.account_id WHERE p.enabled=1 AND a.aktivni=1 AND a.email_overeno=1 ORDER BY p.account_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($accounts as $account) {
        if (!filter_var((string)$account['email'], FILTER_VALIDATE_EMAIL)) {
            $result['skipped']++;
            continue;
        }
        $accountId = (int)$account['account_id'];
        $preview = $previewFactory($pdo, $accountId, $periodFrom);
        $recipientName = trim((string)$account['jmeno'] . ' ' . (string)$account['prijmeni']);
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO family_weekly_summaries(account_id,period_from,period_to,recipient_email,recipient_name,subject_plain,body_plain,item_count) '
                . 'VALUES(?,?,?,?,?,?,?,?)'
            )->execute([
                $accountId, $periodFrom, (string)$preview['to'], (string)$account['email'], $recipientName,
                (string)$preview['subject'], (string)$preview['body'], (int)($preview['counts']['total'] ?? count($preview['items'] ?? [])),
            ]);
            $id = (int)$pdo->lastInsertId();
            familyWeeklyDeliveryAudit($pdo, $id, $accountId, 'enqueue', null, 'pending', 'Souhrn byl idempotentně zařazen do fronty.');
            $pdo->commit();
            $result['queued']++;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$exception->getCode() !== '23000') throw $exception;
            $result['existing']++;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
    return $result;
}

/** @return array<string,mixed>|null */
function familyWeeklyDeliveryClaim(PDO $pdo, string $actorType = 'system', ?int $actorId = null): ?array
{
    $pdo->beginTransaction();
    try {
        $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $stale = $mysql ? 'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE' : "datetime('now','-15 minutes')";
        $sql = 'SELECT n.* FROM family_weekly_summaries n '
            . 'JOIN family_weekly_summary_preferences p ON p.account_id=n.account_id '
            . 'JOIN verejni_uzivatele a ON a.id=n.account_id '
            . "WHERE p.enabled=1 AND a.aktivni=1 AND a.email_overeno=1 AND ((n.status='pending' AND n.available_at<=CURRENT_TIMESTAMP) "
            . "OR (n.status='processing' AND n.claimed_at<" . $stale . ')) ORDER BY n.available_at,n.id LIMIT 1';
        if ($mysql) $sql .= ' FOR UPDATE';
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            return null;
        }
        $token = bin2hex(random_bytes(16));
        $pdo->prepare(
            "UPDATE family_weekly_summaries SET status='processing',attempts=attempts+1,claimed_at=CURRENT_TIMESTAMP,claim_token=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$token, (int)$row['id']]);
        familyWeeklyDeliveryAudit($pdo, (int)$row['id'], (int)$row['account_id'], 'claim', (string)$row['status'], 'processing', 'Zprávu převzal worker.', $actorType, $actorId);
        $pdo->commit();
        $row['claim_token'] = $token;
        $row['attempts'] = (int)$row['attempts'] + 1;
        return $row;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function familyWeeklyDeliveryComplete(PDO $pdo, int $id, string $token, bool $sent, ?string $error = null, string $actorType = 'system', ?int $actorId = null): void
{
    if ($id < 1 || preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) throw new InvalidArgumentException('Neplatné převzetí týdenního souhrnu.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT account_id,status,attempts FROM family_weekly_summaries WHERE id=? AND claim_token=?');
        $statement->execute([$id, $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)$row['status'] !== 'processing') throw new FamilyWeeklyDeliveryException('Souhrn již nevlastní tento proces.');
        if ($sent) {
            $pdo->prepare("UPDATE family_weekly_summaries SET status='sent',sent_at=CURRENT_TIMESTAMP,claim_token=NULL,last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
            familyWeeklyDeliveryAudit($pdo, $id, (int)$row['account_id'], 'sent', 'processing', 'sent', 'Testovací transport potvrdil zpracování.', $actorType, $actorId);
        } else {
            $terminal = (int)$row['attempts'] >= 5;
            $next = $terminal ? 'failed' : 'pending';
            $available = (new DateTimeImmutable('now +' . min(3600, 300 * max(1, (int)$row['attempts'])) . ' seconds'))->format('Y-m-d H:i:s');
            $safeError = mb_substr(trim((string)$error), 0, 500, 'UTF-8');
            $pdo->prepare('UPDATE family_weekly_summaries SET status=?,available_at=?,claim_token=NULL,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$next, $available, $safeError, $id]);
            familyWeeklyDeliveryAudit($pdo, $id, (int)$row['account_id'], 'send_failed', 'processing', $next, $safeError !== '' ? $safeError : 'Testovací transport odmítl zprávu.', $actorType, $actorId);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** @param callable(string,string,string):bool $sender */
function familyWeeklyDeliveryProcessOne(PDO $pdo, callable $sender, string $actorType = 'system', ?int $actorId = null): ?bool
{
    $row = familyWeeklyDeliveryClaim($pdo, $actorType, $actorId);
    if ($row === null) return null;
    try {
        $sent = $sender((string)$row['recipient_email'], (string)$row['subject_plain'], (string)$row['body_plain']);
    } catch (Throwable $exception) {
        try {
            familyWeeklyDeliveryComplete($pdo, (int)$row['id'], (string)$row['claim_token'], false, 'Transport vyvolal chybu typu ' . get_class($exception) . '.', $actorType, $actorId);
        } catch (FamilyWeeklyDeliveryException) {
            // Opt-out may safely cancel a claimed row while a local sender is finishing.
        }
        return false;
    }
    try {
        familyWeeklyDeliveryComplete($pdo, (int)$row['id'], (string)$row['claim_token'], $sent, $sent ? null : 'Testovací transport odmítl zprávu.', $actorType, $actorId);
    } catch (FamilyWeeklyDeliveryException) {
        // The user may have opted out after claim; their cancellation wins.
        return false;
    }
    return $sent;
}

/** @return callable(string,string,string):bool */
function familyWeeklyDeliveryLocalOutboxSender(string $appHost, ?string $directory = null): callable
{
    $directory ??= dirname(__DIR__) . '/var/family-weekly-summary-outbox';
    try {
        return localMessageOutboxSender($appHost, 'family-weekly-summary-local-outbox-v1', $directory);
    } catch (Throwable $exception) {
        throw new FamilyWeeklyDeliveryException($exception->getMessage(), 0, $exception);
    }
}

/** @return array{pending:int,processing:int,sent:int,failed:int,cancelled:int} */
function familyWeeklyDeliveryAdminSummary(PDO $pdo): array
{
    $summary = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    if (!familyWeeklyDeliveryTableExists($pdo, 'family_weekly_summaries')) return $summary;
    foreach ($pdo->query('SELECT status,COUNT(*) AS total FROM family_weekly_summaries GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (array_key_exists((string)$row['status'], $summary)) $summary[(string)$row['status']] = (int)$row['total'];
    }
    return $summary;
}

/** @return list<array<string,mixed>> */
function familyWeeklyDeliveryAdminList(PDO $pdo, string $status = '', int $limit = 100): array
{
    if (!in_array($status, ['', 'pending', 'processing', 'sent', 'failed', 'cancelled'], true)) throw new InvalidArgumentException('Neplatný filtr souhrnů.');
    $limit = max(1, min(250, $limit));
    $where = $status === '' ? "status IN ('pending','processing','failed')" : 'status=?';
    $statement = $pdo->prepare('SELECT * FROM family_weekly_summaries WHERE ' . $where . ' ORDER BY created_at DESC,id DESC LIMIT ' . $limit);
    $statement->execute($status === '' ? [] : [$status]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function familyWeeklyDeliveryAdminPreview(PDO $pdo, int $id): array
{
    if ($id < 1) throw new InvalidArgumentException('Neplatný souhrn.');
    $statement = $pdo->prepare('SELECT * FROM family_weekly_summaries WHERE id=?');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new FamilyWeeklyDeliveryException('Týdenní souhrn nebyl nalezen.');
    return $row;
}
