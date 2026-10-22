<?php
declare(strict_types=1);

/** No recipient addresses, names or documents are retained in this authorization fingerprint. */
function notificationAuthorizationHash(PDO $pdo, int $accountId): string
{
    $q=$pdo->prepare("SELECT sportovec_id,relation_role,status,valid_from,valid_to FROM account_person_roles WHERE account_id=? AND status='approved' AND relation_role IN ('self','guardian') AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP) ORDER BY sportovec_id,relation_role");
    $q->execute([$accountId]);
    return hash('sha256',json_encode($q->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR));
}

function notificationAuthorizationRecord(PDO $pdo, string $queue, int $id, int $accountId): void
{
    if (!$pdo->inTransaction()) throw new LogicException('Snapshot oprávnění vyžaduje transakci.');
    $pdo->prepare('INSERT INTO notification_authorization_snapshots(queue_key,message_id,authorization_hash) VALUES(?,?,?)')
        ->execute([$queue,$id,notificationAuthorizationHash($pdo,$accountId)]);
}

function notificationAuthorizationCurrent(PDO $pdo, string $queue, array $row): bool
{
    $q=$pdo->prepare('SELECT authorization_hash FROM notification_authorization_snapshots WHERE queue_key=? AND message_id=?');
    $q->execute([$queue,(int)$row['id']]);$hash=$q->fetchColumn();
    $a=$pdo->prepare('SELECT email,aktivni,email_overeno FROM verejni_uzivatele WHERE id=?');
    $a->execute([(int)$row['account_id']]);$account=$a->fetch(PDO::FETCH_ASSOC);
    return is_string($hash) && $account && (int)$account['aktivni']===1 && (int)$account['email_overeno']===1
        && hash_equals((string)$row['recipient_email'],(string)$account['email'])
        && hash_equals($hash,notificationAuthorizationHash($pdo,(int)$row['account_id']));
}

function notificationQueueHoldStale(PDO $pdo, string $table, string $stale): void
{
    notificationQueueAssertTable($table);
    $pdo->exec("UPDATE $table SET status='failed',claim_token=NULL,last_error='delivery_unknown: ověřit doručení před ručním opakováním.',updated_at=CURRENT_TIMESTAMP WHERE status='processing' AND claimed_at<".$stale);
}

function notificationQueueHold(PDO $pdo, string $table, array $row, string $reason): void
{
    notificationQueueAssertTable($table);
    $q=$pdo->prepare("UPDATE $table SET status='failed',claim_token=NULL,last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='processing' AND claim_token=?");
    $q->execute([$reason,(int)$row['id'],(string)$row['claim_token']]);
}

function notificationQueueAssertTable(string $table): void
{
    if (!in_array($table,['auth_mail_outbox','family_weekly_summaries','member_charge_reminders','roster_message_recipients'],true)) throw new InvalidArgumentException('Neplatná fronta.');
}
