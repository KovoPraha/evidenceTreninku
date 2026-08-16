<?php
declare(strict_types=1);

defined('AUTH_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS') || define('AUTH_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS', 5);
// Successful logins refund their IP reservation. Forty failed evaluations per
// 15 minutes leave room for a club or school network while still throttling
// password guessing from one public address.
defined('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS') || define('AUTH_RATE_LIMIT_IP_MAX_ATTEMPTS', 40);
defined('AUTH_RATE_LIMIT_WINDOW_SECONDS') || define('AUTH_RATE_LIMIT_WINDOW_SECONDS', 900);
defined('AUTH_RATE_LIMIT_BLOCK_SECONDS') || define('AUTH_RATE_LIMIT_BLOCK_SECONDS', 900);
defined('AUTH_TRUSTED_PROXIES') || define('AUTH_TRUSTED_PROXIES', []);

/**
 * @param array<string, int> $overrides
 * @return array{max_attempts:int,ip_max_attempts:int,window_seconds:int,block_seconds:int}
 */
function auth_rate_limit_policy(array $overrides = []): array
{
    $policy = [
        'max_attempts' => (int)AUTH_RATE_LIMIT_ACCOUNT_MAX_ATTEMPTS,
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

function auth_rate_limit_ip_matches_network(string $ipAddress, string $network): bool
{
    $ipAddress = auth_rate_limit_normalize_ip($ipAddress);
    $network = trim($network);
    if ($ipAddress === 'unknown' || $network === '') {
        return false;
    }

    if (!str_contains($network, '/')) {
        return hash_equals($ipAddress, auth_rate_limit_normalize_ip($network));
    }

    [$networkAddress, $prefixText] = array_pad(explode('/', $network, 2), 2, '');
    if ($prefixText === '' || preg_match('/^[0-9]{1,3}$/D', $prefixText) !== 1) {
        return false;
    }

    $ipPacked = @inet_pton($ipAddress);
    $networkPacked = @inet_pton(trim($networkAddress));
    if ($ipPacked === false || $networkPacked === false || strlen($ipPacked) !== strlen($networkPacked)) {
        return false;
    }

    $prefix = (int)$prefixText;
    $bitCount = strlen($ipPacked) * 8;
    if ($prefix < 0 || $prefix > $bitCount) {
        return false;
    }

    $fullBytes = intdiv($prefix, 8);
    if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($networkPacked, 0, $fullBytes)) {
        return false;
    }

    $remainingBits = $prefix % 8;
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($ipPacked[$fullBytes]) & $mask) === (ord($networkPacked[$fullBytes]) & $mask);
}

/** @param list<string>|null $trustedProxies */
function auth_rate_limit_ip_is_trusted(string $ipAddress, ?array $trustedProxies = null): bool
{
    if ($trustedProxies === null) {
        $configured = constant('AUTH_TRUSTED_PROXIES');
        $trustedProxies = is_array($configured) ? array_values($configured) : [];
    }

    foreach ($trustedProxies as $trustedProxy) {
        if (is_string($trustedProxy) && auth_rate_limit_ip_matches_network($ipAddress, $trustedProxy)) {
            return true;
        }
    }

    return false;
}

function auth_rate_limit_ip_is_private(string $ipAddress): bool
{
    foreach (['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'] as $network) {
        if (auth_rate_limit_ip_matches_network($ipAddress, $network)) {
            return true;
        }
    }

    return false;
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

/**
 * @param array<string, mixed>|null $server
 * @param list<string>|null $trustedProxies
 */
function auth_rate_limit_request_ip(?array $server = null, ?array $trustedProxies = null): string
{
    $server ??= $_SERVER;
    $remoteValue = $server['REMOTE_ADDR'] ?? '';
    $remoteAddress = auth_rate_limit_normalize_ip(is_string($remoteValue) ? $remoteValue : '');
    if (!auth_rate_limit_ip_is_trusted($remoteAddress, $trustedProxies)) {
        return $remoteAddress;
    }

    $forwardedValue = $server['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!is_string($forwardedValue) || trim($forwardedValue) === '') {
        return $remoteAddress;
    }

    $forwardedAddresses = array_reverse(explode(',', $forwardedValue));
    foreach ($forwardedAddresses as $forwardedAddress) {
        $candidate = auth_rate_limit_normalize_ip($forwardedAddress);
        if ($candidate !== 'unknown' && !auth_rate_limit_ip_is_trusted($candidate, $trustedProxies)) {
            return $candidate;
        }
    }

    return $remoteAddress;
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
