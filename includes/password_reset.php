<?php

declare(strict_types=1);

require_once __DIR__ . '/one_time_token.php';
require_once __DIR__ . '/child_access.php';
require_once __DIR__ . '/auth_mail_outbox.php';
require_once __DIR__ . '/staff_email_policy.php';
require_once __DIR__ . '/existing_email_trust.php';

final class PasswordResetException extends RuntimeException
{
}

const PASSWORD_RESET_EMAIL_TTL_SECONDS = 3 * 86400;

/**
 * @param callable(string,string):bool $deliver receives verified e-mail and plaintext token
 * @return array{accepted:true,issued:bool}
 */
function passwordResetRequest(PDO $pdo, string $identifier, callable $deliver, ?int $now = null): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || strlen($identifier) > 160) {
        return ['accepted' => true, 'issued' => false];
    }

    $target = passwordResetResolveTarget($pdo, $identifier);
    if ($target === null) {
        return ['accepted' => true, 'issued' => false];
    }

    $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, PASSWORD_RESET_EMAIL_TTL_SECONDS, $now);
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare(
            'UPDATE password_reset_tokens SET consumed_at=? '
            . 'WHERE target_type=? AND target_id=? AND consumed_at IS NULL'
        );
        $invalidate->execute([$nowSql, $target['type'], $target['target_id']]);
        passwordResetInsertToken($pdo, $target, $issued);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new PasswordResetException('Žádost o obnovu hesla se nepodařilo bezpečně uložit.', 0, $exception);
    }

    try {
        $deliver($target['email'], $issued['token']);
    } catch (Throwable $exception) {
        error_log('Password reset delivery failed: ' . $exception->getMessage());
    }
    return ['accepted' => true, 'issued' => true];
}

/** Queue an encrypted reset link. Earlier delivered links remain valid until used or expired. */
function passwordResetQueueRequest(PDO $pdo, string $identifier, string $resetUrl, ?array $keyConfig = null, ?int $now = null, ?string $auditRequestId = null): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || strlen($identifier) > 160) {
        return ['accepted' => true,'issued' => false,'token' => null];
    }
    try {
        $target = passwordResetResolveTarget($pdo, $identifier);
    } catch (Throwable $exception) {
        if ($auditRequestId === null) {
            throw $exception;
        }
        throw new PasswordResetException('lookup_error', 0, $exception);
    }
    if ($target === null) {
        return ['accepted' => true,'issued' => false,'token' => null];
    }
    $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, PASSWORD_RESET_EMAIL_TTL_SECONDS, $now);
    $pdo->beginTransaction();
    $stage = 'token_insert_error';
    try {
        passwordResetInsertToken($pdo, $target, $issued);
        $link = $resetUrl.'#token='.rawurlencode($issued['token']);
        $loginNote = isset($target['login_name']) ? "\nPřihlašovací jméno sportovce: {$target['login_name']}\n" : '';
        $stage = 'mail_enqueue_error';
        $outboxId = authMailEnqueue(
            $pdo,
            'reset',
            $target['type'],
            $target['target_id'],
            $issued['hash'],
            (string)$target['email'],
            'Obnova hesla — Kovopraha',
            "Pro nastavení nového hesla otevřete odkaz:\n{$link}\n{$loginNote}\nOdkaz je platný 3 dny.",
            $keyConfig
        );
        if ($auditRequestId !== null) {
            $stage = 'audit_update_error';
            passwordResetAuditFinish($pdo, $auditRequestId, 'queued', 'queued', $outboxId);
        }
        $stage = 'commit_error';
        $pdo->commit();
        return ['accepted' => true,'issued' => true,'token' => $issued['token']];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($auditRequestId === null) {
            throw $exception;
        }
        throw new PasswordResetException($stage, 0, $exception);
    }
}

/** @return array{type:string,target_id:int,delivery_account_id:int|null,email:string,login_name?:string}|null */
function passwordResetResolveTarget(PDO $pdo, string $identifier): ?array
{
    $email = mb_strtolower(trim($identifier), 'UTF-8');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $account = $pdo->prepare(
            'SELECT * FROM verejni_uzivatele '
            . 'WHERE LOWER(TRIM(email))=? ORDER BY id LIMIT 2'
        );
        $account->execute([$email]);
        $accounts = $account->fetchAll(PDO::FETCH_ASSOC);
        if (count($accounts) > 1) {
            return null;
        }
        $row = $accounts[0] ?? null;
        if ($row && (int)($row['trener_id'] ?? 0) > 0 && staffAccountHasTechnicalEmail($pdo, (int)$row['trener_id'])) {
            return null;
        }
        if ($row && filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL)
            && (((int)$row['aktivni'] === 1 && (int)$row['email_overeno'] === 1)
                || existingEmailTrustCanActivate($pdo, $row))) {
            return [
                'type' => 'public',
                'target_id' => (int)$row['id'],
                'delivery_account_id' => (int)$row['id'],
                'email' => (string)$row['email'],
            ];
        }
        if (!$row && existingEmailTrustHasTable($pdo, 'athlete_email_trust')) {
            $q = $pdo->prepare('SELECT ca.id FROM child_access_accounts ca JOIN sportovci s ON s.id=ca.sportovec_id '
                . 'WHERE LOWER(TRIM(s.email))=? ORDER BY ca.id LIMIT 2');
            $q->execute([$email]);
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            if (count($ids) === 1) {
                return passwordResetAthleteTarget($pdo, (int)$ids[0]);
            }
        }
    }

    try {
        $loginKey = childAccessNormalizeLogin($identifier);
    } catch (InvalidArgumentException) {
        return null;
    }
    $child = $pdo->prepare('SELECT id FROM child_access_accounts WHERE login_key=? LIMIT 1');
    $child->execute([$loginKey]);
    $childId = $child->fetchColumn();
    if ($childId !== false) {
        $direct = passwordResetAthleteTarget($pdo, (int)$childId);
        if ($direct !== null) {
            return $direct;
        }
    }
    $contact = $pdo->prepare(
        'SELECT ca.id AS child_id,vu.id AS account_id,vu.email '
        . 'FROM child_access_accounts ca '
        . 'JOIN account_person_roles r ON r.sportovec_id=ca.sportovec_id '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . "WHERE ca.login_key=? AND ca.active=1 AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . "AND r.relation_role IN ('self','guardian') AND vu.aktivni=1 AND vu.email_overeno=1 "
        . "ORDER BY CASE r.relation_role WHEN 'self' THEN 0 ELSE 1 END,vu.id LIMIT 1"
    );
    $contact->execute([$loginKey]);
    $row = $contact->fetch(PDO::FETCH_ASSOC);
    if (!$row || !filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return [
        'type' => 'child',
        'target_id' => (int)$row['child_id'],
        'delivery_account_id' => (int)$row['account_id'],
        'email' => (string)$row['email'],
    ];
}

/** @return array<string,mixed>|null */
function passwordResetAthleteTarget(PDO $pdo, int $accessId): ?array
{
    if (!existingEmailTrustHasTable($pdo, 'athlete_email_trust')) {
        return null;
    }
    $q = $pdo->prepare('SELECT ca.login_name,s.email FROM child_access_accounts ca '
        . 'JOIN sportovci s ON s.id=ca.sportovec_id WHERE ca.id=?');
    $q->execute([$accessId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || !athleteEmailTrustCanReset($pdo, $accessId, (string)$row['email'])) {
        return null;
    }
    return ['type' => 'child', 'target_id' => $accessId, 'delivery_account_id' => null,
        'email' => (string)$row['email'], 'login_name' => (string)$row['login_name']];
}

/** Keep the destination snapshot, so changing the account address invalidates the link. */
function passwordResetInsertToken(PDO $pdo, array $target, array $issued): void
{
    if (existingEmailTrustHasColumn($pdo, 'password_reset_tokens', 'delivery_email')) {
        $pdo->prepare('INSERT INTO password_reset_tokens(target_type,target_id,delivery_account_id,token_hash,expires_at,delivery_email) VALUES(?,?,?,?,?,?)')
            ->execute([$target['type'], $target['target_id'], $target['delivery_account_id'], $issued['hash'], $issued['expires_at'], $target['email']]);
        return;
    }
    $pdo->prepare('INSERT INTO password_reset_tokens(target_type,target_id,delivery_account_id,token_hash,expires_at) VALUES(?,?,?,?,?)')
        ->execute([$target['type'], $target['target_id'], $target['delivery_account_id'], $issued['hash'], $issued['expires_at']]);
}

/** Read-only preflight; consumption repeats the lookup while holding the token lock. */
function passwordResetLookup(PDO $pdo, string $token, ?int $now = null, bool $lock = false): ?array
{
    $hash = one_time_token_hash(ONE_TIME_TOKEN_PASSWORD_RESET, trim($token));
    if ($hash === '') {
        return null;
    }
    $sql = 'SELECT * FROM password_reset_tokens '
        . 'WHERE token_hash=? AND consumed_at IS NULL AND expires_at>=? LIMIT 1';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$hash, gmdate('Y-m-d H:i:s', $now ?? time())]);
    $reset = $statement->fetch(PDO::FETCH_ASSOC);
    return $reset && passwordResetTargetStillAuthorized($pdo, $reset) ? $reset : null;
}

/** @return array{target_type:string,target_id:int}|null */
function passwordResetConsume(PDO $pdo, string $token, string $newPassword, ?int $now = null): ?array
{
    passwordPolicyValidate($newPassword);
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $firstPassword = false;
    $pdo->beginTransaction();
    try {
        $reset = passwordResetLookup($pdo, $token, $now, true);
        if ($reset === null) {
            $pdo->rollBack();
            return null;
        }

        if ((string)$reset['target_type'] === 'public') {
            $account = $pdo->prepare(
                'SELECT * FROM verejni_uzivatele WHERE id=? AND email_overeno=1'
            );
            $account->execute([(int)$reset['target_id']]);
            $accountRow = $account->fetch(PDO::FETCH_ASSOC);
            if (!$accountRow) {
                $pdo->rollBack();
                return null;
            }
            $firstPassword = (int)$accountRow['aktivni'] === 0 && existingEmailTrustCanActivate($pdo, $accountRow);
            $trainerId = $accountRow['trener_id'] ?? null;
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare(
                'UPDATE verejni_uzivatele SET heslo_hash=?,aktivni=1,session_version=session_version+1 '
                . 'WHERE id=? AND aktivni=? AND email_overeno=1 '
                . 'AND (? IS NULL OR LOWER(email)=LOWER(?))'
            );
            $deliveryEmail = $reset['delivery_email'] ?? null;
            $update->execute([$newHash, (int)$reset['target_id'], $firstPassword ? 0 : 1, $deliveryEmail, $deliveryEmail]);
            if ($trainerId !== null) {
                $trainerUpdate = $pdo->prepare(
                    'UPDATE treneri SET heslo=?,session_version=session_version+1 WHERE id=? AND aktivni=1'
                );
                $trainerUpdate->execute([$newHash, (int)$trainerId]);
                if ($trainerUpdate->rowCount() !== 1) {
                    $pdo->rollBack();
                    return null;
                }
            }
        } else {
            $direct = $reset['delivery_account_id'] === null;
            if ($direct && !athleteEmailTrustCanReset($pdo, (int)$reset['target_id'], (string)$reset['delivery_email'])) {
                $pdo->rollBack();
                return null;
            }
            $update = $pdo->prepare(
                'UPDATE child_access_accounts SET password_hash=?,active=1,password_changed_at=CURRENT_TIMESTAMP,'
                . 'session_version=session_version+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND active=?'
            );
            $active = 1;
            if ($direct) {
                $check = $pdo->prepare('SELECT active FROM child_access_accounts WHERE id=?');
                $check->execute([(int)$reset['target_id']]);
                $active = (int)$check->fetchColumn();
            }
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$reset['target_id'], $active]);
        }
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }

        $consume = $pdo->prepare((string)$reset['target_type'] === 'public'
            ? "UPDATE password_reset_tokens SET consumed_at=? WHERE target_type IN ('public','activation') AND target_id=? AND consumed_at IS NULL"
            : 'UPDATE password_reset_tokens SET consumed_at=? WHERE target_type=? AND target_id=? AND consumed_at IS NULL');
        $consume->execute((string)$reset['target_type'] === 'public'
            ? [$nowSql, (int)$reset['target_id']]
            : [$nowSql, $reset['target_type'], (int)$reset['target_id']]);
        if ((string)$reset['target_type'] === 'public') {
            passwordResetAuditPublic($pdo, (int)$reset['target_id'], $firstPassword ? 'legacy_first_password' : 'password_reset');
        }
        if ((string)$reset['target_type'] === 'child') {
            childAccessEvent(
                $pdo,
                (int)$reset['target_id'],
                'system',
                null,
                'password_reset',
                ($reset['delivery_account_id'] === null
                    ? 'Samoobslužné první heslo nebo obnova přes e-mail sportovce uznaný vlastníkem.'
                    : 'Samoobslužná obnova hesla přes ověřený účet.')
            );
        }
        $pdo->commit();
        return ['target_type' => (string)$reset['target_type'], 'target_id' => (int)$reset['target_id']];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException) {
            throw $exception;
        }
        throw new PasswordResetException('Heslo se nepodařilo bezpečně změnit.', 0, $exception);
    }
}

/** @param array<string,mixed> $reset */
function passwordResetTargetStillAuthorized(PDO $pdo, array $reset): bool
{
    if ((string)$reset['target_type'] === 'public') {
        $statement = $pdo->prepare(
            'SELECT * FROM verejni_uzivatele '
            . 'WHERE id=? AND id=? AND email_overeno=1 LIMIT 1'
        );
        $statement->execute([(int)$reset['target_id'], (int)$reset['delivery_account_id']]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        $snapshot = $reset['delivery_email'] ?? null;
        return $account
            && ((int)$account['aktivni'] === 1 || existingEmailTrustCanActivate($pdo, $account))
            && ($snapshot === null || mb_strtolower((string)$account['email'], 'UTF-8') === mb_strtolower((string)$snapshot, 'UTF-8'))
            && ((int)($account['trener_id'] ?? 0) < 1 || !staffAccountHasTechnicalEmail($pdo, (int)$account['trener_id'], $pdo->inTransaction()));
    }
    if ((string)$reset['target_type'] !== 'child') {
        return false;
    }
    if ($reset['delivery_account_id'] === null) {
        return isset($reset['delivery_email']) && athleteEmailTrustCanReset($pdo, (int)$reset['target_id'], (string)$reset['delivery_email']);
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM child_access_accounts ca '
        . 'JOIN account_person_roles r ON r.sportovec_id=ca.sportovec_id AND r.account_id=? '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . "WHERE ca.id=? AND ca.active=1 AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . "AND r.relation_role IN ('self','guardian') AND vu.aktivni=1 AND vu.email_overeno=1 LIMIT 1"
    );
    $statement->execute([(int)$reset['delivery_account_id'], (int)$reset['target_id']]);
    return (bool)$statement->fetchColumn();
}

function passwordResetAuditPublic(PDO $pdo, int $accountId, string $action = 'password_reset'): void
{
    // Older isolated SQLite fixtures predate the activation migration.
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        && !$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='public_account_activation_events'")->fetchColumn()) {
        return;
    }
    $pdo->prepare('INSERT INTO public_account_activation_events(account_id,actor_trainer_id,action,reason) VALUES(?,NULL,?,?)')
        ->execute([$accountId, $action, $action === 'legacy_first_password'
            ? 'První heslo importovaného účtu přes adresu uznanou rozhodnutím vlastníka; vazby na osoby nejsou schváleny.'
            : 'Jednorázovým odkazem změněno heslo; staré relace a odkazy zneplatněny.']);
}
