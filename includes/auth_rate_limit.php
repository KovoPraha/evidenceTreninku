<?php
declare(strict_types=1);

defined('AUTH_RATE_LIMIT_MAX_ATTEMPTS') || define('AUTH_RATE_LIMIT_MAX_ATTEMPTS', 5);
defined('AUTH_RATE_LIMIT_WINDOW_SECONDS') || define('AUTH_RATE_LIMIT_WINDOW_SECONDS', 900);
defined('AUTH_RATE_LIMIT_BLOCK_SECONDS') || define('AUTH_RATE_LIMIT_BLOCK_SECONDS', 900);

/**
 * @param array<string, int> $overrides
 * @return array{max_attempts:int,window_seconds:int,block_seconds:int}
 */
function auth_rate_limit_policy(array $overrides = []): array
{
    $policy = [
        'max_attempts' => (int)AUTH_RATE_LIMIT_MAX_ATTEMPTS,
        'window_seconds' => (int)AUTH_RATE_LIMIT_WINDOW_SECONDS,
        'block_seconds' => (int)AUTH_RATE_LIMIT_BLOCK_SECONDS,
    ];

    foreach ($overrides as $key => $value) {
        if (array_key_exists($key, $policy)) {
            $policy[$key] = $value;
        }
    }

    foreach ($policy as $value) {
        if ($value < 1) {
            throw new InvalidArgumentException('Rate-limit policy values must be positive.');
        }
    }

    return $policy;
}

function auth_rate_limit_normalize_identifier(string $identifier): string
{
    return strtolower(trim($identifier));
}

function auth_rate_limit_normalize_ip(string $ipAddress): string
{
    $ipAddress = trim($ipAddress);
    $packed = filter_var($ipAddress, FILTER_VALIDATE_IP) === false
        ? false
        : @inet_pton($ipAddress);

    return $packed === false ? 'unknown' : (string)inet_ntop($packed);
}

function auth_rate_limit_hash(string $scope, string $dimension, string $value): string
{
    return hash(
        'sha256',
        "evidence-auth-rate-limit-v1\0{$scope}\0{$dimension}\0{$value}"
    );
}

/** @return array{identifier:string,ip:string} */
function auth_rate_limit_keys(string $scope, string $identifier, string $ipAddress): array
{
    return [
        'identifier' => auth_rate_limit_hash(
            $scope,
            'identifier',
            auth_rate_limit_normalize_identifier($identifier)
        ),
        'ip' => auth_rate_limit_hash(
            $scope,
            'ip',
            auth_rate_limit_normalize_ip($ipAddress)
        ),
    ];
}

/** @param array<string, mixed>|null $server */
function auth_rate_limit_request_ip(?array $server = null): string
{
    $server ??= $_SERVER;
    return auth_rate_limit_normalize_ip((string)($server['REMOTE_ADDR'] ?? ''));
}

function auth_rate_limit_is_allowed(
    PDO $pdo,
    string $scope,
    string $identifier,
    string $ipAddress,
    ?int $now = null
): bool {
    $now ??= time();
    $statement = $pdo->prepare(
        'SELECT blocked_until FROM auth_login_limits '
        . 'WHERE scope = :scope AND key_hash = :key_hash LIMIT 1'
    );

    foreach (auth_rate_limit_keys($scope, $identifier, $ipAddress) as $keyHash) {
        $statement->execute(['scope' => $scope, 'key_hash' => $keyHash]);
        $blockedUntil = $statement->fetchColumn();
        if ($blockedUntil !== false && (int)$blockedUntil > $now) {
            return false;
        }
    }

    return true;
}

/** @param array<string, int>|null $policy */
function auth_rate_limit_record_failure(
    PDO $pdo,
    string $scope,
    string $identifier,
    string $ipAddress,
    ?int $now = null,
    ?array $policy = null
): void {
    $now ??= time();
    $policy = auth_rate_limit_policy($policy ?? []);
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true)) {
        throw new RuntimeException('Unsupported database driver for authentication rate limiting.');
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        foreach (auth_rate_limit_keys($scope, $identifier, $ipAddress) as $keyHash) {
            if ($driver === 'mysql') {
                $insert = $pdo->prepare(
                    'INSERT IGNORE INTO auth_login_limits '
                    . '(scope, key_hash, window_started_at, attempts, blocked_until, updated_at) '
                    . 'VALUES (:scope, :key_hash, :now, 0, 0, :now)'
                );
            } else {
                $insert = $pdo->prepare(
                    'INSERT OR IGNORE INTO auth_login_limits '
                    . '(scope, key_hash, window_started_at, attempts, blocked_until, updated_at) '
                    . 'VALUES (:scope, :key_hash, :now, 0, 0, :now)'
                );
            }
            $insert->execute(['scope' => $scope, 'key_hash' => $keyHash, 'now' => $now]);

            $selectSql = 'SELECT window_started_at, attempts, blocked_until '
                . 'FROM auth_login_limits WHERE scope = :scope AND key_hash = :key_hash';
            if ($driver === 'mysql') {
                $selectSql .= ' FOR UPDATE';
            }
            $select = $pdo->prepare($selectSql);
            $select->execute(['scope' => $scope, 'key_hash' => $keyHash]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('Rate-limit row could not be locked.');
            }

            $windowStartedAt = (int)$row['window_started_at'];
            $attempts = (int)$row['attempts'];
            $blockedUntil = (int)$row['blocked_until'];

            if ($now - $windowStartedAt >= $policy['window_seconds']) {
                $windowStartedAt = $now;
                $attempts = 0;
                $blockedUntil = 0;
            }

            $attempts++;
            if ($attempts >= $policy['max_attempts']) {
                $blockedUntil = max($blockedUntil, $now + $policy['block_seconds']);
            }

            $update = $pdo->prepare(
                'UPDATE auth_login_limits SET '
                . 'window_started_at = :window_started_at, attempts = :attempts, '
                . 'blocked_until = :blocked_until, updated_at = :updated_at '
                . 'WHERE scope = :scope AND key_hash = :key_hash'
            );
            $update->execute([
                'window_started_at' => $windowStartedAt,
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
                'updated_at' => $now,
                'scope' => $scope,
                'key_hash' => $keyHash,
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function auth_rate_limit_clear_identifier(
    PDO $pdo,
    string $scope,
    string $identifier
): void {
    $statement = $pdo->prepare(
        'DELETE FROM auth_login_limits WHERE scope = :scope AND key_hash = :identifier_hash'
    );
    $statement->execute([
        'scope' => $scope,
        'identifier_hash' => auth_rate_limit_hash(
            $scope,
            'identifier',
            auth_rate_limit_normalize_identifier($identifier)
        ),
    ]);
}
