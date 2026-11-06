<?php

declare(strict_types=1);
require_once __DIR__.'/monthly_membership.php';
require_once __DIR__.'/member_charge_reminder.php';

function monthlyMembershipMessage(PDO $pdo, int $messageId, ?DateTimeImmutable $now = null): ?array
{
    $q = $pdo->prepare(('SELECT n.*,c.status AS charge_status,c.due_on,c.title_snapshot,m.sportovec_id,m.id AS '
    .
    'membership_id,a.email,a.aktivni,a.email_overeno,p.document_id FROM monthly_membership_messages n JOIN club_member_charges c '
    .
    'ON c.id=n.charge_id JOIN monthly_membership_periods p ON p.charge_id=c.id JOIN monthly_memberships m ON m.id=p.membership_id '
    .
    'JOIN verejni_uzivatele a ON a.id=n.account_id WHERE n.id=?' . ($pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
    ? ' FOR UPDATE' : '')));
    $q->execute([$messageId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['aktivni'] !== 1 || (int)$row['email_overeno'] !== 1 || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)
    || !familyPortalCanViewPerson(
        $pdo,
        (int)$row['account_id'],
        (int)$row['sportovec_id']
    )) {
        return null;
    }
    $remaining = monthlyMembershipRemaining($pdo, (int)$row['charge_id']);
    if ($row['kind'] === 'issued') {
        if (!$row['document_id'] || $row['charge_status'] === 'cancelled') {
            return null;
        }
        $doc = accountingDocumentGet($pdo, (int)$row['document_id']);
        if ($doc['status'] !== 'issued') {
            return null;
        }
        $subject = 'Doklad k členství v klubu';
        $body = 'Byl vystaven doklad '.$doc['number'].'.';
    } else {
        $days = (int)(new DateTimeImmutable($row['due_on']))->diff(new DateTimeImmutable(monthlyMembershipToday($now)))
    ->format('%r%a');
        $stage = (int)substr($row['kind'], 7);
        if ($row['charge_status'] !== 'pending' || $remaining < 1 || !in_array($stage, [3,7,14], true) || $days < $stage) {
            return null;
        }
        $subject = 'Neuhrazené členství po splatnosti';
        $body = 'Evidujeme neuhrazený zůstatek předpisu „'.$row['title_snapshot'].'“ se splatností '.$row['due_on']
    .'.';
    }
    $amount = number_format($remaining / 100, 2, ',', ' ');
    $body .= "\nZbývá uhradit: ".$amount." Kč.\nPřehled, doklad a platební údaje po přihlášení: ".appUrl('booking/mesicni_clenstvi.php')
    ."\nPokud jste právě zaplatili, vyčkejte na zpracování bankovního pohybu.\nKOVO Praha";
    return $row + ['subject' => $subject,'body' => $body,'remaining_minor' => $remaining];
}

/** Uncertain deliveries are held, never blindly replayed after a worker crash. */
function monthlyMembershipSendOne(PDO $pdo, callable $sender, ?DateTimeImmutable $now = null): ?bool
{
    $id = monthlyMembershipTransaction($pdo, static function () use ($pdo): ?int {
        $stale = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'CURRENT_TIMESTAMP - INTERVAL 15 MINUTE' : "datetime('now','-15 minutes')";
        $pdo->exec(('UPDATE monthly_membership_messages SET status=\'failed\',last_error=\'delivery_unknown: ověřit doručení před opakováním\' WHERE '
    .
    'status=\'processing\' AND claimed_at<').$stale);
        $id = $pdo->query("SELECT id FROM monthly_membership_messages WHERE status='pending' ORDER BY id LIMIT 1")
    ->fetchColumn();
        if (!$id) {
            return null;
        }
        $pdo->prepare("UPDATE monthly_membership_messages SET status='processing',claim_token=?,attempts=attempts+1,claimed_at=CURRENT_TIMESTAMP WHERE id=?")
    ->execute([bin2hex(random_bytes(16)),$id]);
        return (int)$id;
    });
    if ($id === null) {
        return null;
    }
    try {
        return monthlyMembershipTransaction($pdo, static function () use ($pdo, $id, $sender, $now): bool {
            $q = $pdo->prepare('SELECT status FROM monthly_membership_messages WHERE id=?');
            $q->execute([$id]);
            if ($q->fetchColumn() !== 'processing') {
                return false;
            }
            $message = monthlyMembershipMessage($pdo, $id, $now);
            if ($message === null) {
                $pdo->prepare("UPDATE monthly_membership_messages SET status='cancelled',claim_token=NULL WHERE id=?")
    ->execute([$id]);
                return false;
            }
            // All membership bank allocations hold the same lock until delivery completes.
            $sent = (bool)$sender($message['email'], $message['subject'], $message['body']);
            $pdo->prepare("UPDATE monthly_membership_messages SET status=?,sent_at=?,claim_token=NULL,last_error=? WHERE id=?")
                ->execute([$sent ? 'sent' : 'failed',$sent ? date('Y-m-d H:i:s') : null,$sent ? null : 'Transport odmítl zprávu; ověřte před opakováním.'
    ,$id]);
            monthlyMembershipEvent($pdo, (int)$message['membership_id'], 'system', null, $sent ? 'message_sent' : 'message_failed', ['message_id' =>
    $id,'kind' => $message['kind'],'remaining_minor' => $message['remaining_minor']]);
            return $sent;
        });
    } catch (Throwable $error) {
        // The transport may already have accepted the message. Keep it out of automatic retries.
        $pdo->prepare("UPDATE monthly_membership_messages SET status='failed',last_error='delivery_unknown: ověřit doručení před opakováním' WHERE id=?")
    ->execute([$id]);
        throw $error;
    }
}

function monthlyMembershipRun(PDO $pdo, bool $apply, bool $send, callable $sender, int $limit = 100, ?DateTimeImmutable $now = null): array
{
    $locked = $apply && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($locked && (int)$pdo->query("SELECT GET_LOCK('evidence:monthly-memberships',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('membership_worker_already_running');
    }
    try {
        return monthlyMembershipRunUnlocked($pdo, $apply, $send, $sender, $limit, $now);
    } finally {
        if ($locked) {
            $pdo->query("SELECT RELEASE_LOCK('evidence:monthly-memberships')");
        }
    }
}

/** Called only by the public worker while holding the shared processing lock. */
function monthlyMembershipRunUnlocked(PDO $pdo, bool $apply, bool $send, callable $sender, int $limit = 100, ?DateTimeImmutable $now = null): array
{
    $today = monthlyMembershipToday($now);
    $result = ['apply' => $apply,'memberships' => 0,'charges' => 0,'documents' => 0,'queued' => 0,'sent' => 0,'errors'
    => []];
    $ids = $pdo->query('SELECT id FROM monthly_memberships ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $result['memberships'] = count($ids);
    if (!$apply) {
        return $result;
    }
    foreach ($ids as $id) {
        try {
            $result['charges'] += monthlyMembershipTransaction($pdo, static fn (): int => monthlyMembershipGenerateLocked($pdo, (int)$id, $today));
        } catch (Throwable $error) {
            $result['errors'][] = ['membership_id' => (int)$id,'stage' => 'billing'];
            error_log('Monthly billing: '.$error->getMessage());
        }
    }
    $periods = $pdo->query(('SELECT p.id FROM monthly_membership_periods p LEFT JOIN accounting_documents d ON d.id=p.document_id WHERE d.id IS NULL OR '
    .
    'd.status<>\'issued\' ORDER BY p.id'))->fetchAll(PDO::FETCH_COLUMN);
    foreach (array_slice($periods, 0, max(1, $limit)) as $id) {
        try {
            monthlyMembershipIssueDocument($pdo, (int)$id);
            $result['documents']++;
        } catch (Throwable $error) {
            $pdo->prepare('UPDATE monthly_membership_periods SET last_error=? WHERE id=?')->execute(['Vydání dokladu selhalo; zkontrolujte účetní nastavení a oprávnění.'
    ,$id]);
            $result['errors'][] = ['period_id' => (int)$id,'stage' => 'document'];
            error_log('Monthly document: '.$error->getMessage());
        }
    }
    $result['queued'] = monthlyMembershipQueueOverdue($pdo, $now);
    if ($send) {
        for ($i = 0;$i < max(1, $limit);$i++) {
            $sent = monthlyMembershipSendOne($pdo, $sender, $now);
            if ($sent === null) {
                break;
            }
            if ($sent) {
                $result['sent']++;
            }
        }
    }
    return $result;
}
