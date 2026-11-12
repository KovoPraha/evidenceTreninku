<?php

declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';

final class ImportedAccountActivationException extends RuntimeException
{
}

/** @return array{account:array<string,mixed>,mode:string,reason:string} */
function importedAccountInviteEligibility(PDO $pdo, int $accountId): array
{
    $sql = 'SELECT id,email,aktivni,email_overeno,trener_id FROM verejni_uzivatele WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && $pdo->inTransaction()) {
        $sql .= ' FOR UPDATE';
    }
    $query = $pdo->prepare($sql);
    $query->execute([$accountId]);
    $account = $query->fetch(PDO::FETCH_ASSOC);
    if (!$account || $account['trener_id'] !== null || !filter_var((string)$account['email'], FILTER_VALIDATE_EMAIL)) {
        return ['account' => $account ?: [], 'mode' => 'blocked', 'reason' => 'Účet nemá vhodnou kontaktní adresu.'];
    }
    $email = mb_strtolower(trim((string)$account['email']), 'UTF-8');
    $duplicates = $pdo->prepare('SELECT COUNT(*) FROM verejni_uzivatele WHERE LOWER(email)=?');
    $duplicates->execute([$email]);
    $trainer = $pdo->prepare('SELECT COUNT(*) FROM treneri WHERE LOWER(email)=?');
    $trainer->execute([$email]);
    if ((int)$duplicates->fetchColumn() !== 1 || (int)$trainer->fetchColumn() !== 0) {
        return ['account' => $account, 'mode' => 'blocked', 'reason' => 'E-mail není jednoznačný mezi účty.'];
    }
    $roleSql = 'SELECT r.status,r.relation_role,r.source,'
        . '(r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP)) AS is_current '
        . 'FROM account_person_roles r '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . 'LEFT JOIN kis_person_profiles p ON p.sportovec_id=s.id '
        . "WHERE r.account_id=? AND (p.sportovec_id IS NOT NULL OR NULLIF(TRIM(s.kis_external_id),'') IS NOT NULL)";
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && $pdo->inTransaction()) {
        $roleSql .= ' FOR UPDATE';
    }
    $roles = $pdo->prepare($roleSql);
    $roles->execute([$accountId]);
    $approved = 0;
    $pending = 0;
    foreach ($roles->fetchAll(PDO::FETCH_ASSOC) as $role) {
        if ($role['status'] === 'pending') {
            $pending++;
        }
        if ($role['status'] === 'approved' && in_array($role['relation_role'], ['guardian', 'self'], true)
            && (int)$role['is_current'] === 1) {
            $approved++;
        }
    }
    if ($approved < 1 || $pending > 0) {
        return ['account' => $account, 'mode' => 'blocked', 'reason' => 'Nejprve ověřte a schvalte všechny importované vazby.'];
    }
    $mode = (int)$account['aktivni'] === 1 && (int)$account['email_overeno'] === 1 ? 'reset' : 'activate';
    return ['account' => $account, 'mode' => $mode, 'reason' => ''];
}

/** Admin reviewed invitation. Each account is committed independently for safe bulk processing. */
function importedAccountInvite(PDO $pdo, int $accountId, int $actorId, string $reason, string $resetUrl, string $activationUrl, ?array $keyConfig = null): string
{
    $reason = trim($reason);
    if ($accountId < 1 || $actorId < 1 || mb_strlen($reason, 'UTF-8') < 10 || mb_strlen($reason, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Zadejte účet a věcný důvod kontroly (10–1000 znaků).');
    }
    $pdo->beginTransaction();
    try {
        $decision = importedAccountInviteEligibility($pdo, $accountId);
        if ($decision['mode'] === 'blocked') {
            throw new ImportedAccountActivationException($decision['reason']);
        }
        $mode = $decision['mode'];
        $account = $decision['account'];
        $now = gmdate('Y-m-d H:i:s');
        $existing = $pdo->prepare(
            'SELECT COUNT(*) FROM password_reset_tokens WHERE target_id=? AND target_type IN (\'public\',\'activation\') '
            . 'AND consumed_at IS NULL AND expires_at>=?'
        );
        $existing->execute([$accountId, $now]);
        if ((int)$existing->fetchColumn() > 0) {
            throw new ImportedAccountActivationException('Pro účet už existuje platný odkaz. Vyčkejte na jeho použití nebo vypršení.');
        }
        $purpose = $mode === 'activate' ? ONE_TIME_TOKEN_ACCOUNT_ACTIVATION : ONE_TIME_TOKEN_PASSWORD_RESET;
        $issued = one_time_token_issue($purpose, 3600);
        $pdo->prepare(
            'INSERT INTO password_reset_tokens(target_type,target_id,delivery_account_id,token_hash,expires_at,delivery_email) '
            . 'VALUES(?,?,?,?,?,?)'
        )->execute([$mode === 'activate' ? 'activation' : 'public', $accountId, $accountId,
            $issued['hash'], $issued['expires_at'], (string)$account['email']]);
        $link = ($mode === 'activate' ? $activationUrl : $resetUrl) . '#token=' . rawurlencode($issued['token']);
        $body = "Dobrý den,\n\n"
            . "Po ověření v TJ KOVO Praha můžete nastavit vlastní heslo ke svému účtu KIS:\n{$link}\n\n"
            . 'Odkaz platí 60 minut a lze jej použít jen jednou. Pokud jste o přístup nežádali, zprávu ignorujte.';
        authMailEnqueue(
            $pdo,
            'reset',
            'public',
            $accountId,
            $issued['hash'],
            (string)$account['email'],
            'Pozvání do KIS — TJ KOVO Praha',
            $body,
            $keyConfig
        );
        $pdo->prepare(
            'INSERT INTO public_account_activation_events(account_id,actor_trainer_id,action,reason) VALUES(?,?,?,?)'
        )->execute([$accountId, $actorId, 'invite_' . $mode, $reason]);
        $pdo->commit();
        return $mode;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function importedAccountActivationConsume(PDO $pdo, string $token, string $newPassword, ?int $now = null): bool
{
    passwordPolicyValidate($newPassword);
    $hash = one_time_token_hash(ONE_TIME_TOKEN_ACCOUNT_ACTIVATION, trim($token));
    if ($hash === '') {
        return false;
    }
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $sql = "SELECT * FROM password_reset_tokens WHERE target_type='activation' AND token_hash=? "
            . 'AND consumed_at IS NULL AND expires_at>=? LIMIT 1';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $query = $pdo->prepare($sql);
        $query->execute([$hash, $nowSql]);
        $tokenRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!$tokenRow || !importedAccountActivationTokenAuthorized($pdo, $tokenRow)) {
            $pdo->rollBack();
            return false;
        }
        $accountId = (int)$tokenRow['target_id'];
        $update = $pdo->prepare(
            'UPDATE verejni_uzivatele SET heslo_hash=?,aktivni=1,email_overeno=1,session_version=session_version+1,'
            . 'verifikacni_token=NULL,verifikacni_token_expires_at=NULL WHERE id=? AND email=? AND trener_id IS NULL'
        );
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $accountId, (string)$tokenRow['delivery_email']]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare(
            "UPDATE password_reset_tokens SET consumed_at=? WHERE target_id=? AND target_type IN ('public','activation') AND consumed_at IS NULL"
        )->execute([$nowSql, $accountId]);
        $pdo->prepare(
            'INSERT INTO public_account_activation_events(account_id,actor_trainer_id,action,reason) VALUES(?,NULL,?,?)'
        )->execute([$accountId, 'email_verified_password_set', 'Jednorázový odkaz použit; staré relace a tokeny zneplatněny.']);
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array<string,mixed> $tokenRow */
function importedAccountActivationTokenAuthorized(PDO $pdo, array $tokenRow): bool
{
    if (($tokenRow['target_type'] ?? '') !== 'activation'
        || (int)$tokenRow['target_id'] !== (int)$tokenRow['delivery_account_id']
        || !filter_var((string)($tokenRow['delivery_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $decision = importedAccountInviteEligibility($pdo, (int)$tokenRow['target_id']);
    return $decision['mode'] === 'activate'
        && hash_equals(
            mb_strtolower((string)$decision['account']['email'], 'UTF-8'),
            mb_strtolower((string)$tokenRow['delivery_email'], 'UTF-8')
        );
}
