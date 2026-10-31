<?php

declare(strict_types=1);

require_once __DIR__ . '/password_security.php';
require_once __DIR__ . '/auth_mail_outbox.php';

const TRAINER_PASSWORD_RESET_PURPOSE = 'trainer-password-reset-v1';

/** Admin-maintained address, proved by possession of the emailed token. Never link accounts by email. */
function trainerPasswordResetTarget(PDO $pdo, string $email, bool $lock = false): ?array
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = $pdo->prepare('SELECT id,email,session_version FROM treneri WHERE LOWER(TRIM(email))=? AND aktivni=1 ORDER BY id LIMIT 2' . $suffix);
    $query->execute([$email]);
    $trainers = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($trainers) !== 1) {
        return null;
    }
    $trainer = $trainers[0];
    $linked = $pdo->prepare('SELECT id,email,aktivni,email_overeno,session_version FROM verejni_uzivatele WHERE trener_id=? ORDER BY id LIMIT 2' . $suffix);
    $linked->execute([(int)$trainer['id']]);
    $accounts = $linked->fetchAll(PDO::FETCH_ASSOC);
    if (count($accounts) > 1) {
        return null;
    }
    $account = $accounts[0] ?? null;
    if ($account !== null && ((int)$account['aktivni'] !== 1 || (int)$account['email_overeno'] !== 1 || strtolower(trim((string)$account['email'])) !== $email)) {
        return null;
    }
    return [
        'trainer_id' => (int)$trainer['id'],
        'recipient_email' => $email,
        'session_version' => (int)$trainer['session_version'],
        'public_account_id' => $account === null ? null : (int)$account['id'],
        'public_session_version' => $account === null ? null : (int)$account['session_version'],
    ];
}

/** @return array{accepted:true,issued:bool,token:?string} */
function trainerPasswordResetQueueRequest(PDO $pdo, string $email, string $resetUrl, ?array $keys = null, ?int $now = null): array
{
    $pdo->beginTransaction();
    try {
        $target = trainerPasswordResetTarget($pdo, $email, true);
        if ($target === null) {
            $pdo->rollBack();
            return ['accepted' => true, 'issued' => false, 'token' => null];
        }
        $issued = one_time_token_issue(TRAINER_PASSWORD_RESET_PURPOSE, 3600, $now);
        $insert = $pdo->prepare(
            'INSERT INTO trainer_password_reset_tokens(trainer_id,recipient_email,session_version,public_account_id,public_session_version,token_hash,expires_at) VALUES(?,?,?,?,?,?,?)'
        );
        $insert->execute([...array_values($target), $issued['hash'], $issued['expires_at']]);
        $link = $resetUrl . '#token=' . rawurlencode($issued['token']);
        authMailEnqueue(
            $pdo,
            'reset',
            'trainer',
            $target['trainer_id'],
            $issued['hash'],
            $target['recipient_email'],
            'Obnova trenérského hesla — Kovopraha',
            "Pro nastavení nového hesla k trenérskému účtu otevřete odkaz:\n{$link}\n\nOdkaz je platný 60 minut. Pokud jste o změnu nežádali, zprávu ignorujte.",
            $keys
        );
        $pdo->commit();
        return ['accepted' => true, 'issued' => true, 'token' => $issued['token']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function trainerPasswordResetStillAuthorized(PDO $pdo, array $reset, bool $lock = false): bool
{
    $current = trainerPasswordResetTarget($pdo, (string)$reset['recipient_email'], $lock);
    if ($current === null) {
        return false;
    }
    foreach ($current as $key => $value) {
        if (($value === null) !== ($reset[$key] === null) || (string)$value !== (string)$reset[$key]) {
            return false;
        }
    }
    return true;
}

function trainerPasswordResetMailIsCurrent(PDO $pdo, array $mail, string $nowSql): bool
{
    if ((string)$mail['kind'] !== 'reset') {
        return false;
    }
    $query = $pdo->prepare('SELECT * FROM trainer_password_reset_tokens WHERE trainer_id=? AND token_hash=? AND consumed_at IS NULL AND expires_at>=?');
    $query->execute([(int)$mail['target_id'], (string)$mail['token_hash'], $nowSql]);
    $reset = $query->fetch(PDO::FETCH_ASSOC);
    return $reset && hash_equals((string)$reset['recipient_email'], (string)$mail['recipient_email']) && trainerPasswordResetStillAuthorized($pdo, $reset);
}

/** @return array{target_type:string,target_id:int}|null */
function trainerPasswordResetConsume(PDO $pdo, string $token, string $password, ?int $now = null): ?array
{
    passwordPolicyValidate($password);
    $hash = one_time_token_hash(TRAINER_PASSWORD_RESET_PURPOSE, trim($token));
    if ($hash === '') {
        return null;
    }
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $pdo->prepare('SELECT * FROM trainer_password_reset_tokens WHERE token_hash=? AND consumed_at IS NULL AND expires_at>=?' . $suffix);
        $query->execute([$hash, $nowSql]);
        $reset = $query->fetch(PDO::FETCH_ASSOC);
        if (!$reset || !trainerPasswordResetStillAuthorized($pdo, $reset, true)) {
            $pdo->rollBack();
            return null;
        }
        $newHash = trainer_password_hash($password);
        $update = $pdo->prepare('UPDATE treneri SET heslo=?,session_version=session_version+1 WHERE id=? AND aktivni=1 AND session_version=? AND LOWER(TRIM(email))=?');
        $update->execute([$newHash, (int)$reset['trainer_id'], (int)$reset['session_version'], (string)$reset['recipient_email']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Trainer changed during password reset.');
        }
        if ($reset['public_account_id'] !== null) {
            $update = $pdo->prepare('UPDATE verejni_uzivatele SET heslo_hash=?,session_version=session_version+1 WHERE id=? AND trener_id=? AND session_version=? AND aktivni=1 AND email_overeno=1');
            $update->execute([$newHash, (int)$reset['public_account_id'], (int)$reset['trainer_id'], (int)$reset['public_session_version']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Linked account changed during password reset.');
            }
            $pdo->prepare("UPDATE password_reset_tokens SET consumed_at=? WHERE target_type='public' AND target_id=? AND consumed_at IS NULL")
                ->execute([$nowSql, (int)$reset['public_account_id']]);
        }
        $pdo->prepare('UPDATE trainer_password_reset_tokens SET consumed_at=? WHERE trainer_id=? AND consumed_at IS NULL')->execute([$nowSql, (int)$reset['trainer_id']]);
        $pdo->commit();
        return ['target_type' => 'trainer', 'target_id' => (int)$reset['trainer_id']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
