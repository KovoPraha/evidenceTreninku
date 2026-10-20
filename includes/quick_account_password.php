<?php
declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/booking_return.php';

/**
 * Call only immediately after consuming an email verification token. The grant
 * stays in that authenticated session; it is never put in a URL or sent by mail.
 *
 * @return array{account_id:int,session_version:int,token:string,expires_at:int}|null
 */
function quickAccountPasswordSetupIssue(PDO $pdo, int $accountId, int $sessionVersion, ?int $now = null): ?array
{
    $account = $pdo->prepare(
        'SELECT 1 FROM verejni_uzivatele u WHERE u.id=? AND u.aktivni=1 '
        . 'AND u.email_overeno=1 AND u.session_version=? '
        . "AND EXISTS(SELECT 1 FROM shop_orders o WHERE o.account_id=u.id AND o.checkout_mode='quick_program')"
    );
    $account->execute([$accountId, $sessionVersion]);
    if (!$account->fetchColumn()) {
        return null;
    }
    $now ??= time();
    $issued = one_time_token_issue(ONE_TIME_TOKEN_PASSWORD_RESET, 1800, $now);
    $pdo->prepare(
        'INSERT INTO password_reset_tokens(target_type,target_id,delivery_account_id,token_hash,expires_at) VALUES(?,?,?,?,?)'
    )->execute(['public', $accountId, $accountId, $issued['hash'], $issued['expires_at']]);
    return [
        'account_id' => $accountId,
        'session_version' => $sessionVersion,
        'token' => $issued['token'],
        'expires_at' => $now + 1800,
    ];
}

function quickAccountPasswordSetupValid(PDO $pdo, array $grant, int $accountId, int $sessionVersion, ?int $now = null): bool
{
    if ($accountId < 1 || $sessionVersion < 1
        || (int)($grant['account_id'] ?? 0) !== $accountId
        || (int)($grant['session_version'] ?? 0) !== $sessionVersion
        || (int)($grant['expires_at'] ?? 0) < ($now ?? time())) {
        return false;
    }
    $hash = one_time_token_hash(ONE_TIME_TOKEN_PASSWORD_RESET, (string)($grant['token'] ?? ''));
    if ($hash === '') {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM verejni_uzivatele u JOIN password_reset_tokens t ON t.target_id=u.id '
        . "AND t.target_type='public' AND t.delivery_account_id=u.id "
        . 'WHERE u.id=? AND u.session_version=? AND u.aktivni=1 AND u.email_overeno=1 '
        . 'AND t.token_hash=? AND t.consumed_at IS NULL AND t.expires_at>=?'
    );
    $statement->execute([$accountId, $sessionVersion, $hash, gmdate('Y-m-d H:i:s', $now ?? time())]);
    return (bool)$statement->fetchColumn();
}

/** Returns the new session version; the caller must rebind only this account. */
function quickAccountPasswordSetupConsume(PDO $pdo, array $grant, int $accountId, int $sessionVersion, string $password, ?int $now = null): ?int
{
    if (!quickAccountPasswordSetupValid($pdo, $grant, $accountId, $sessionVersion, $now)) {
        return null;
    }
    $result = passwordResetConsume($pdo, (string)$grant['token'], $password, $now);
    if ($result === null || $result['target_type'] !== 'public' || $result['target_id'] !== $accountId) {
        return null;
    }
    $statement = $pdo->prepare('SELECT session_version FROM verejni_uzivatele WHERE id=? AND aktivni=1 AND email_overeno=1');
    $statement->execute([$accountId]);
    $version = $statement->fetchColumn();
    return $version === false ? null : (int)$version;
}

function quickAccountPasswordSetupReturn(mixed $value): string
{
    return bookingReturnDestination($value, 'moje_osoby.php');
}
