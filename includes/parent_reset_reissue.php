<?php

declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';

const PARENT_RESET_INCIDENT_CANCEL_REASON = 'Odkaz byl nahrazen nebo již není platný.';
const PARENT_RESET_REISSUED_REASON = 'AUTH-MAIL-02: starý odkaz nahrazen novou obnovou.';

/** Return only non-personal identifiers and readiness; never expose recipients or tokens. */
function parentResetReissueInspect(PDO $pdo, bool $lock = false): array
{
    $sql = "SELECT id,target_id,recipient_email FROM auth_mail_outbox WHERE kind='reset' "
        . "AND target_type='public' AND status='cancelled' AND attempts=1 "
        . 'AND body_ciphertext IS NULL AND last_error=? ORDER BY id';
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $query = $pdo->prepare($sql);
    $query->execute([PARENT_RESET_INCIDENT_CANCEL_REASON]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static fn (array $row): int => (int)$row['id'], $rows);
    if (count($rows) !== 2 || (int)$rows[0]['target_id'] === (int)$rows[1]['target_id']) {
        return ['ready' => false, 'old_ids' => $ids, 'reason' => 'incident_scope_changed'];
    }
    foreach ($rows as $row) {
        $accountSql = 'SELECT id FROM verejni_uzivatele WHERE id=? AND email=? AND aktivni=1 AND email_overeno=1';
        if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $accountSql .= ' FOR UPDATE';
        }
        $account = $pdo->prepare($accountSql);
        $account->execute([(int)$row['target_id'], (string)$row['recipient_email']]);
        if (!$account->fetchColumn()) {
            return ['ready' => false, 'old_ids' => $ids, 'reason' => 'target_changed'];
        }
        $target = passwordResetResolveTarget($pdo, (string)$row['recipient_email']);
        if ($target === null || $target['type'] !== 'public'
            || $target['target_id'] !== (int)$row['target_id']
            || strcasecmp($target['email'], (string)$row['recipient_email']) !== 0) {
            return ['ready' => false, 'old_ids' => $ids, 'reason' => 'target_changed'];
        }
        $newer = $pdo->prepare("SELECT COUNT(*) FROM auth_mail_outbox WHERE kind='reset' AND target_type='public' AND target_id=? AND id>?");
        $newer->execute([(int)$row['target_id'], (int)$row['id']]);
        if ((int)$newer->fetchColumn() !== 0) {
            return ['ready' => false, 'old_ids' => $ids, 'reason' => 'newer_request_exists'];
        }
        $active = $pdo->prepare("SELECT COUNT(*) FROM password_reset_tokens WHERE target_type='public' AND target_id=? AND consumed_at IS NULL AND expires_at>=CURRENT_TIMESTAMP");
        $active->execute([(int)$row['target_id']]);
        if ((int)$active->fetchColumn() !== 0) {
            return ['ready' => false, 'old_ids' => $ids, 'reason' => 'active_link_exists'];
        }
    }
    return ['ready' => true, 'old_ids' => $ids, 'reason' => null];
}

/** Issue exactly two new, encrypted reset messages atomically; the old links remain cancelled. */
function parentResetReissueTwo(PDO $pdo, array $expectedIds, string $resetUrl, ?array $keyConfig = null): array
{
    $expectedIds = array_map('intval', $expectedIds);
    sort($expectedIds);
    if (count($expectedIds) !== 2 || $expectedIds[0] < 1 || $expectedIds[0] === $expectedIds[1]) {
        throw new InvalidArgumentException('parent_reset_expected_ids_invalid');
    }
    if ($pdo->inTransaction()) {
        throw new LogicException('parent_reset_transaction_already_active');
    }
    $pdo->beginTransaction();
    try {
        $inspect = parentResetReissueInspect($pdo, true);
        if (!$inspect['ready'] || $inspect['old_ids'] !== $expectedIds) {
            throw new RuntimeException('parent_reset_preflight_changed');
        }
        $oldRows = $pdo->prepare('SELECT id,target_id,recipient_email FROM auth_mail_outbox WHERE id IN (?,?) ORDER BY id');
        $oldRows->execute($expectedIds);
        $newIds = [];
        $expiresAt = [];
        foreach ($oldRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, PASSWORD_RESET_EMAIL_TTL_SECONDS);
            $pdo->prepare('INSERT INTO password_reset_tokens(target_type,target_id,delivery_account_id,token_hash,expires_at) VALUES(?,?,?,?,?)')
                ->execute(['public', (int)$row['target_id'], (int)$row['target_id'], $issued['hash'], $issued['expires_at']]);
            $link = $resetUrl . '#token=' . rawurlencode($issued['token']);
            $newIds[] = authMailEnqueue(
                $pdo,
                'reset',
                'public',
                (int)$row['target_id'],
                $issued['hash'],
                (string)$row['recipient_email'],
                'Obnova hesla — Kovopraha',
                "Pro nastavení nového hesla otevřete odkaz:\n{$link}\n\nOdkaz je platný 3 dny.",
                $keyConfig
            );
            $expiresAt[] = $issued['expires_at'];
        }
        $mark = $pdo->prepare("UPDATE auth_mail_outbox SET last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='cancelled' AND last_error=?");
        foreach ($expectedIds as $id) {
            $mark->execute([PARENT_RESET_REISSUED_REASON, $id, PARENT_RESET_INCIDENT_CANCEL_REASON]);
            if ($mark->rowCount() !== 1) {
                throw new RuntimeException('parent_reset_source_changed');
            }
        }
        $pdo->commit();
        return ['queued' => count($newIds), 'old_ids' => $expectedIds, 'new_ids' => $newIds, 'expires_at' => $expiresAt];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
