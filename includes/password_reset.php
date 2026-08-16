<?php
declare(strict_types=1);

require_once __DIR__ . '/one_time_token.php';
require_once __DIR__ . '/child_access.php';

final class PasswordResetException extends RuntimeException
{
}

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

    $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, 3600, $now);
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $invalidate = $pdo->prepare(
            'UPDATE password_reset_tokens SET consumed_at=? '
            . 'WHERE target_type=? AND target_id=? AND consumed_at IS NULL'
        );
        $invalidate->execute([$nowSql, $target['type'], $target['target_id']]);
        $insert = $pdo->prepare(
            'INSERT INTO password_reset_tokens '
            . '(target_type,target_id,delivery_account_id,token_hash,expires_at) VALUES (?,?,?,?,?)'
        );
        $insert->execute([
            $target['type'],
            $target['target_id'],
            $target['delivery_account_id'],
            $issued['hash'],
            $issued['expires_at'],
        ]);
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

/** @return array{type:string,target_id:int,delivery_account_id:int,email:string}|null */
function passwordResetResolveTarget(PDO $pdo, string $identifier): ?array
{
    $email = strtolower(trim($identifier));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $account = $pdo->prepare(
            'SELECT id,email FROM verejni_uzivatele '
            . 'WHERE email=? AND aktivni=1 AND email_overeno=1 LIMIT 1'
        );
        $account->execute([$email]);
        $row = $account->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'type' => 'public',
                'target_id' => (int)$row['id'],
                'delivery_account_id' => (int)$row['id'],
                'email' => (string)$row['email'],
            ];
        }
    }

    try {
        $loginKey = childAccessNormalizeLogin($identifier);
    } catch (InvalidArgumentException) {
        return null;
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

/** @return array{target_type:string,target_id:int}|null */
function passwordResetConsume(PDO $pdo, string $token, string $newPassword, ?int $now = null): ?array
{
    passwordPolicyValidate($newPassword);
    $hash = one_time_token_hash(ONE_TIME_TOKEN_PASSWORD_RESET, trim($token));
    if ($hash === '') {
        return null;
    }
    $nowSql = gmdate('Y-m-d H:i:s', $now ?? time());
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM password_reset_tokens '
            . 'WHERE token_hash=? AND consumed_at IS NULL AND expires_at>=? LIMIT 1';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$hash, $nowSql]);
        $reset = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$reset || !passwordResetTargetStillAuthorized($pdo, $reset)) {
            $pdo->rollBack();
            return null;
        }

        if ((string)$reset['target_type'] === 'public') {
            $account = $pdo->prepare(
                'SELECT trener_id FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1'
            );
            $account->execute([(int)$reset['target_id']]);
            $trainerId = $account->fetchColumn();
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare(
                'UPDATE verejni_uzivatele SET heslo_hash=?,session_version=session_version+1 '
                . 'WHERE id=? AND aktivni=1 AND email_overeno=1'
            );
            $update->execute([$newHash, (int)$reset['target_id']]);
            if ($trainerId !== false && $trainerId !== null) {
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
            $update = $pdo->prepare(
                'UPDATE child_access_accounts SET password_hash=?,password_changed_at=CURRENT_TIMESTAMP,'
                . 'session_version=session_version+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND active=1'
            );
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$reset['target_id']]);
        }
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }

        $consume = $pdo->prepare(
            'UPDATE password_reset_tokens SET consumed_at=? '
            . 'WHERE target_type=? AND target_id=? AND consumed_at IS NULL'
        );
        $consume->execute([$nowSql, $reset['target_type'], (int)$reset['target_id']]);
        if ((string)$reset['target_type'] === 'child') {
            childAccessEvent(
                $pdo,
                (int)$reset['target_id'],
                'system',
                null,
                'password_reset',
                'Samoobslužná obnova hesla přes ověřený účet.'
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
            'SELECT 1 FROM verejni_uzivatele '
            . 'WHERE id=? AND id=? AND aktivni=1 AND email_overeno=1 LIMIT 1'
        );
        $statement->execute([(int)$reset['target_id'], (int)$reset['delivery_account_id']]);
        return (bool)$statement->fetchColumn();
    }
    if ((string)$reset['target_type'] !== 'child') {
        return false;
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
