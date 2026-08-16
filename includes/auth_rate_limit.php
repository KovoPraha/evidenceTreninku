<?php
declare(strict_types=1);

defined('AUTH_RATE_LIMIT_MAX_ATTEMPTS') || define('AUTH_RATE_LIMIT_MAX_ATTEMPTS', 5);
// Shared networks (families, clubs, schools) need more room than one account,
// while the per-account limit remains deliberately strict.
defined('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS') || define('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS', 20);
defined('AUTH_RATE_LIMIT_WINDOW_SECONDS') || define('AUTH_RATE_LIMIT_WINDOW_SECONDS', 900);
defined('AUTH_RATE_LIMIT_BLOCK_SECONDS') || define('AUTH_RATE_LIMIT_BLOCK_SECONDS', 900);

/**
 * @param array<string, int> $overrides
 * @return array{max_attempts:int,ip_max_attempts:int,window_seconds:int,block_seconds:int}
 */
function auth_rate_limit_policy(array $overrides = []): array
{
    $policy = [
        'max_attempts' => (int)AUTH_RATE_LIMIT_MAX_ATTEMPTS,
        'ip_max_attempts' => (int)AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS,
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

function auth_rate_limit_validate_pepper(mixed $pepper): string
{
    if (!is_string($pepper) || strlen($pepper) < 32) {
        throw new RuntimeException(
            'Authentication rate-limit pepper is missing or shorter than 32 characters.'
        );
    }

    return $pepper;
}

function auth_rate_limit_pepper(): string
{
    return auth_rate_limit_validate_pepper(
        defined('AUTH_RATE_LIMIT_PEPPER') ? constant('AUTH_RATE_LIMIT_PEPPER') : null
    );
}

function auth_rate_limit_hash(string $scope, string $dimension, string $value): string
{
    return hash_hmac(
        'sha256',
        "evidence-auth-rate-limit-v2\0{$scope}\0{$dimension}\0{$value}",
        auth_rate_limit_pepper()
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

/** @return array{identifier:string,ip:string} */
function auth_rate_limit_ordered_keys(
    string $scope,
    string $identifier,
    string $ipAddress
): array {
    $keys = auth_rate_limit_keys($scope, $identifier, $ipAddress);
    asort($keys, SORT_STRING);
    return $keys;
}

/** @param array<string, mixed>|null $server */
function auth_rate_limit_request_ip(?array $server = null): string
{
    $server ??= $_SERVER;
    return auth_rate_limit_normalize_ip((string)($server['REMOTE_ADDR'] ?? ''));
}

/**
 * Atomically reserve one credential evaluation for both the account and IP
 * dimensions. The reservation itself counts as an attempt, so callers must
 * not perform a separate pre-check followed by a failure write.
 *
 * @param array<string, int>|null $policy
 */
function auth_rate_limit_reserve_attempt(
    PDO $pdo,
    string $scope,
    string $identifier,
    string $ipAddress,
    ?int $now = null,
    ?array $policy = null
): bool {
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
        $keys = auth_rate_limit_ordered_keys($scope, $identifier, $ipAddress);

        /** @var array<string, array{window_started_at:int,attempts:int,blocked_until:int}> $rows */
        $rows = [];
        foreach ($keys as $dimension => $keyHash) {
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

            $rows[$dimension] = [
                'window_started_at' => (int)$row['window_started_at'],
                'attempts' => (int)$row['attempts'],
                'blocked_until' => (int)$row['blocked_until'],
            ];
        }

        $allowed = true;
        foreach ($rows as $dimension => $row) {
            if ($now - $row['window_started_at'] >= $policy['window_seconds']) {
                $row = [
                    'window_started_at' => $now,
                    'attempts' => 0,
                    'blocked_until' => 0,
                ];
            }

            $maxAttempts = $dimension === 'ip'
                ? $policy['ip_max_attempts']
                : $policy['max_attempts'];

            if ($row['blocked_until'] > $now) {
                $allowed = false;
            } elseif ($row['attempts'] >= $maxAttempts) {
                $row['blocked_until'] = $now + $policy['block_seconds'];
                $allowed = false;
            }

            $rows[$dimension] = $row;
        }

        foreach ($rows as $dimension => $row) {
            if ($allowed) {
                $row['attempts']++;
            }

            $update = $pdo->prepare(
                'UPDATE auth_login_limits SET '
                . 'window_started_at = :window_started_at, attempts = :attempts, '
                . 'blocked_until = :blocked_until, updated_at = :updated_at '
                . 'WHERE scope = :scope AND key_hash = :key_hash'
            );
            $update->execute([
                'window_started_at' => $row['window_started_at'],
                'attempts' => $row['attempts'],
                'blocked_until' => $row['blocked_until'],
                'updated_at' => $now,
                'scope' => $scope,
                'key_hash' => $keys[$dimension],
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $allowed;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Complete a successful reserved evaluation. Account failures are cleared;
 * only this request's reservation is refunded from the shared IP dimension.
 *
 * @param array<string, int>|null $policy
 */
function auth_rate_limit_record_success(
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

    $keys = auth_rate_limit_ordered_keys($scope, $identifier, $ipAddress);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        /** @var array<string, array<string, mixed>|null> $rows */
        $rows = [];
        foreach ($keys as $dimension => $keyHash) {
            $selectSql = 'SELECT window_started_at, attempts, blocked_until '
                . 'FROM auth_login_limits WHERE scope = :scope AND key_hash = :key_hash';
            if ($driver === 'mysql') {
                $selectSql .= ' FOR UPDATE';
            }
            $select = $pdo->prepare($selectSql);
            $select->execute(['scope' => $scope, 'key_hash' => $keyHash]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            $rows[$dimension] = is_array($row) ? $row : null;
        }

        $deleteIdentifier = $pdo->prepare(
            'DELETE FROM auth_login_limits WHERE scope = :scope AND key_hash = :key_hash'
        );
        $deleteIdentifier->execute([
            'scope' => $scope,
            'key_hash' => $keys['identifier'],
        ]);

        $row = $rows['ip'] ?? null;

        if (is_array($row)) {
            $windowStartedAt = (int)$row['window_started_at'];
            $attempts = (int)$row['attempts'];
            $blockedUntil = (int)$row['blocked_until'];

            if ($now - $windowStartedAt >= $policy['window_seconds'] || $attempts <= 1) {
                $deleteIp = $pdo->prepare(
                    'DELETE FROM auth_login_limits WHERE scope = :scope AND key_hash = :key_hash'
                );
                $deleteIp->execute(['scope' => $scope, 'key_hash' => $keys['ip']]);
            } else {
                $attempts--;
                if ($attempts < $policy['ip_max_attempts']) {
                    $blockedUntil = 0;
                }

                $update = $pdo->prepare(
                    'UPDATE auth_login_limits SET attempts = :attempts, '
                    . 'blocked_until = :blocked_until, updated_at = :updated_at '
                    . 'WHERE scope = :scope AND key_hash = :key_hash'
                );
                $update->execute([
                    'attempts' => $attempts,
                    'blocked_until' => $blockedUntil,
                    'updated_at' => $now,
                    'scope' => $scope,
                    'key_hash' => $keys['ip'],
                ]);
            }
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
