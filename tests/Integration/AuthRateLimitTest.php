<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/auth_rate_limit.php';

final class AuthRateLimitTest extends TestCase
{
    public function testPolicyDefaultsAreConservativeAndOverridable(): void
    {
        self::assertSame([
            'max_attempts' => 5,
            'window_seconds' => 900,
            'block_seconds' => 900,
        ], \auth_rate_limit_policy());
        self::assertSame(3, \auth_rate_limit_policy(['max_attempts' => 3])['max_attempts']);
    }

    public function testOnlyScopedHashesArePersistedAndFifthFailureBlocksBothDimensions(): void
    {
        $pdo = $this->database();
        $scope = 'public_login';
        $email = 'Person@example.test';
        $ip = '192.0.2.44';

        for ($attempt = 0; $attempt < 4; $attempt++) {
            self::assertTrue(\auth_rate_limit_is_allowed($pdo, $scope, $email, $ip, 1000 + $attempt));
            \auth_rate_limit_record_failure($pdo, $scope, $email, $ip, 1000 + $attempt);
        }
        self::assertTrue(\auth_rate_limit_is_allowed($pdo, $scope, $email, $ip, 1004));
        \auth_rate_limit_record_failure($pdo, $scope, $email, $ip, 1004);

        self::assertFalse(\auth_rate_limit_is_allowed($pdo, $scope, $email, $ip, 1004));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM auth_login_limits')->fetchColumn());

        $rows = $pdo->query(
            'SELECT scope, key_hash, attempts, blocked_until FROM auth_login_limits ORDER BY key_hash'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            self::assertSame($scope, $row['scope']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string)$row['key_hash']);
            self::assertStringNotContainsString(strtolower($email), (string)$row['key_hash']);
            self::assertStringNotContainsString($ip, (string)$row['key_hash']);
            self::assertSame(5, (int)$row['attempts']);
            self::assertSame(1904, (int)$row['blocked_until']);
        }
    }

    public function testExpiredWindowResetsAndSuccessfulLoginClearsOnlyIdentifierDimension(): void
    {
        $pdo = $this->database();
        $scope = 'trainer_login';
        $identifier = 'Trainer';
        $ip = '2001:db8::1';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            \auth_rate_limit_record_failure($pdo, $scope, $identifier, $ip, 1000 + $attempt);
        }
        self::assertFalse(\auth_rate_limit_is_allowed($pdo, $scope, $identifier, $ip, 1500));
        self::assertTrue(\auth_rate_limit_is_allowed($pdo, $scope, $identifier, $ip, 1904));

        \auth_rate_limit_record_failure($pdo, $scope, $identifier, $ip, 1904);
        self::assertSame(
            1,
            (int)$pdo->query('SELECT MIN(attempts) FROM auth_login_limits')->fetchColumn()
        );

        \auth_rate_limit_clear_identifier($pdo, $scope, $identifier);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM auth_login_limits')->fetchColumn());
        $remaining = (string)$pdo->query('SELECT key_hash FROM auth_login_limits')->fetchColumn();
        self::assertSame(\auth_rate_limit_keys($scope, $identifier, $ip)['ip'], $remaining);
    }

    public function testIdentifierHashIsCaseNormalizedAndSeparatedFromIpHash(): void
    {
        $first = \auth_rate_limit_keys('trainer_login', ' Coach ', '192.0.2.1');
        $second = \auth_rate_limit_keys('trainer_login', 'coach', '192.0.2.1');

        self::assertSame($first['identifier'], $second['identifier']);
        self::assertSame($first['ip'], $second['ip']);
        self::assertNotSame($first['identifier'], $first['ip']);
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec(
            'CREATE TABLE auth_login_limits ('
            . 'scope TEXT NOT NULL, key_hash TEXT NOT NULL, '
            . 'window_started_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, '
            . 'blocked_until INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL, '
            . 'PRIMARY KEY (scope, key_hash))'
        );
        return $pdo;
    }
}
