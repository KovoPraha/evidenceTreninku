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
            'ip_max_attempts' => 40,
            'window_seconds' => 900,
            'block_seconds' => 900,
        ], \auth_rate_limit_policy());
        self::assertSame(3, \auth_rate_limit_policy(['max_attempts' => 3])['max_attempts']);
        self::assertSame(12, \auth_rate_limit_policy(['ip_max_attempts' => 12])['ip_max_attempts']);
    }

    public function testPepperIsRequiredAndMustHaveAtLeastThirtyTwoCharacters(): void
    {
        foreach ([null, '', str_repeat('x', 31)] as $invalidPepper) {
            try {
                \auth_rate_limit_validate_pepper($invalidPepper);
                self::fail('Invalid rate-limit pepper was accepted.');
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }

        $validPepper = str_repeat('x', 32);
        self::assertSame($validPepper, \auth_rate_limit_validate_pepper($validPepper));
    }

    public function testAtomicReservationPermitsOnlyConfiguredNumberOfEvaluations(): void
    {
        $pdo = $this->database();
        $scope = 'public_login';
        $email = 'Person@example.test';
        $ip = '192.0.2.44';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue(
                \auth_rate_limit_reserve_attempt($pdo, $scope, $email, $ip, 1000 + $attempt)
            );
        }
        self::assertFalse(\auth_rate_limit_reserve_attempt($pdo, $scope, $email, $ip, 1005));
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM auth_login_limits')->fetchColumn());

        $keys = \auth_rate_limit_keys($scope, $email, $ip);
        $select = $pdo->prepare(
            'SELECT attempts, blocked_until FROM auth_login_limits WHERE scope=? AND key_hash=?'
        );
        $select->execute([$scope, $keys['identifier']]);
        self::assertSame(
            ['attempts' => 5, 'blocked_until' => 1905],
            array_map('intval', $select->fetch(PDO::FETCH_ASSOC))
        );
        $select->execute([$scope, $keys['ip']]);
        self::assertSame(
            ['attempts' => 5, 'blocked_until' => 0],
            array_map('intval', $select->fetch(PDO::FETCH_ASSOC))
        );
    }

    public function testDefaultConfigurationIgnoresForwardedHeaders(): void
    {
        self::assertSame('198.51.100.23', \auth_rate_limit_request_ip([
            'REMOTE_ADDR' => '198.51.100.23',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
            'HTTP_X_REAL_IP' => '203.0.113.9',
        ]));
    }

    public function testSpoofedForwardedHeaderFromUntrustedPeerIsIgnored(): void
    {
        self::assertSame('198.51.100.23', \auth_rate_limit_request_ip([
            'REMOTE_ADDR' => '198.51.100.23',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.99',
        ], ['10.0.0.0/8']));
    }

    public function testTrustedProxyChainReturnsRightmostUntrustedClient(): void
    {
        self::assertSame('198.51.100.77', \auth_rate_limit_request_ip([
            'REMOTE_ADDR' => '10.20.30.40',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 198.51.100.77, 192.0.2.15',
        ], ['10.0.0.0/8', '192.0.2.0/24']));
    }

    public function testTrustedProxyMatchingSupportsIpv4AndIpv6Cidrs(): void
    {
        self::assertTrue(\auth_rate_limit_ip_matches_network('10.12.3.4', '10.0.0.0/8'));
        self::assertFalse(\auth_rate_limit_ip_matches_network('11.12.3.4', '10.0.0.0/8'));
        self::assertTrue(\auth_rate_limit_ip_matches_network('2001:db8:abcd::1', '2001:db8::/32'));
        self::assertFalse(\auth_rate_limit_ip_matches_network('2001:db9::1', '2001:db8::/32'));
    }

    public function testPrivateAddressDetectionUsesPrivateNotDocumentationRanges(): void
    {
        foreach (['10.1.2.3', '172.31.255.254', '192.168.1.1', 'fd12:3456::1'] as $privateIp) {
            self::assertTrue(\auth_rate_limit_ip_is_private($privateIp), $privateIp);
        }
        foreach (['172.32.0.1', '198.51.100.1', '2001:db8::1'] as $publicIp) {
            self::assertFalse(\auth_rate_limit_ip_is_private($publicIp), $publicIp);
        }
    }

    public function testSharedIpAllowsSeveralAccountsBeforeItsHigherThresholdBlocks(): void
    {
        $pdo = $this->database();
        $policy = ['max_attempts' => 5, 'ip_max_attempts' => 40];

        for ($attempt = 0; $attempt < 40; $attempt++) {
            self::assertTrue(\auth_rate_limit_reserve_attempt(
                $pdo,
                'public_login',
                'person-' . $attempt . '@example.test',
                '192.0.2.99',
                1000 + $attempt,
                $policy
            ));
        }

        self::assertFalse(\auth_rate_limit_reserve_attempt(
            $pdo,
            'public_login',
            'person-41@example.test',
            '192.0.2.99',
            1040,
            $policy
        ));
    }

    public function testSuccessClearsAccountAndRefundsOnlyItsSharedIpReservation(): void
    {
        $pdo = $this->database();
        $scope = 'trainer_login';
        $identifier = 'Successful Trainer';
        $ip = '2001:db8::1';

        for ($attempt = 0; $attempt < 4; $attempt++) {
            self::assertTrue(\auth_rate_limit_reserve_attempt(
                $pdo,
                $scope,
                'failed-' . $attempt,
                $ip,
                1000 + $attempt
            ));
        }
        self::assertTrue(\auth_rate_limit_reserve_attempt($pdo, $scope, $identifier, $ip, 1004));
        \auth_rate_limit_record_success($pdo, $scope, $identifier, $ip, 1005);

        $keys = \auth_rate_limit_keys($scope, $identifier, $ip);
        $identifierQuery = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_login_limits WHERE scope = ? AND key_hash = ?'
        );
        $identifierQuery->execute([$scope, $keys['identifier']]);
        self::assertSame(0, (int)$identifierQuery->fetchColumn());

        $ipQuery = $pdo->prepare(
            'SELECT attempts, blocked_until FROM auth_login_limits WHERE scope = ? AND key_hash = ?'
        );
        $ipQuery->execute([$scope, $keys['ip']]);
        self::assertSame(['attempts' => 4, 'blocked_until' => 0], array_map(
            'intval',
            $ipQuery->fetch(PDO::FETCH_ASSOC)
        ));
        self::assertTrue(\auth_rate_limit_reserve_attempt(
            $pdo,
            $scope,
            'next-valid-account',
            $ip,
            1006
        ));
    }

    public function testExpiredWindowIsResetDuringReservation(): void
    {
        $pdo = $this->database();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue(\auth_rate_limit_reserve_attempt(
                $pdo,
                'trainer_login',
                'Trainer',
                '192.0.2.12',
                1000 + $attempt
            ));
        }
        self::assertFalse(\auth_rate_limit_reserve_attempt(
            $pdo,
            'trainer_login',
            'Trainer',
            '192.0.2.12',
            1005
        ));
        self::assertTrue(\auth_rate_limit_reserve_attempt(
            $pdo,
            'trainer_login',
            'Trainer',
            '192.0.2.12',
            1905
        ));
        self::assertSame(
            1,
            (int)$pdo->query('SELECT MIN(attempts) FROM auth_login_limits')->fetchColumn()
        );
    }

    public function testIdentifierHashIsCaseNormalizedAndSeparatedFromIpHash(): void
    {
        $first = \auth_rate_limit_keys('trainer_login', ' Coach ', '192.0.2.1');
        $second = \auth_rate_limit_keys('trainer_login', 'coach', '192.0.2.1');

        self::assertSame($first['identifier'], $second['identifier']);
        self::assertSame($first['ip'], $second['ip']);
        self::assertNotSame($first['identifier'], $first['ip']);
        self::assertNotSame(
            hash('sha256', "evidence-auth-rate-limit-v2\0trainer_login\0identifier\0coach"),
            $first['identifier']
        );
    }

    public function testBothMutationFlowsShareHashSortedLockOrder(): void
    {
        $keys = \auth_rate_limit_keys('trainer_login', 'Coach', '192.0.2.1');
        $expected = $keys;
        asort($expected, SORT_STRING);

        self::assertSame(
            $expected,
            \auth_rate_limit_ordered_keys('trainer_login', 'Coach', '192.0.2.1')
        );

        $source = file_get_contents(dirname(__DIR__, 2) . '/includes/auth_rate_limit.php');
        self::assertIsString($source);
        self::assertSame(3, substr_count($source, 'auth_rate_limit_ordered_keys('));
        $success = substr($source, (int)strpos($source, 'function auth_rate_limit_record_success('));
        self::assertLessThan(
            strpos($success, '$deleteIdentifier ='),
            strpos($success, 'foreach ($keys as $dimension => $keyHash)')
        );
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
