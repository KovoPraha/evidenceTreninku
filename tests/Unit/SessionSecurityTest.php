<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/session_security.php';

final class SessionSecurityTest extends TestCase
{
    public function testPolicyUsesExpectedDefaults(): void
    {
        $policy = \app_session_policy(['HTTP_HOST' => 'data.kovopraha.cz']);

        self::assertSame('EVIDENCESESSID', $policy['name']);
        self::assertSame(7200, $policy['idle_timeout']);
        self::assertSame(43200, $policy['absolute_timeout']);
        self::assertSame(900, $policy['rotation_interval']);
        self::assertSame('/', $policy['cookie_path']);
        self::assertTrue($policy['cookie_secure']);
        self::assertTrue($policy['cookie_httponly']);
        self::assertSame('Lax', $policy['cookie_samesite']);
    }

    public function testSecureCookieDetectionCoversLocalAndProductionRequests(): void
    {
        self::assertTrue(\app_session_request_is_local(['HTTP_HOST' => 'localhost'], 'Windows'));
        self::assertFalse(\app_session_policy(['HTTP_HOST' => 'localhost'], [], 'Windows')['cookie_secure']);
        self::assertFalse(\app_session_policy(['HTTP_HOST' => 'evidence.test:8080'], [], 'Windows')['cookie_secure']);
        self::assertTrue(\app_session_policy([
            'HTTP_HOST' => 'localhost',
            'HTTPS' => 'on',
        ], [], 'Windows')['cookie_secure']);
        self::assertTrue(\app_session_policy(['HTTP_HOST' => 'data.kovopraha.cz'])['cookie_secure']);
    }

    public function testLinuxRequestCannotDisableSecureCookieWithLocalHostHeader(): void
    {
        self::assertFalse(\app_session_request_is_local(['HTTP_HOST' => 'localhost'], 'Linux'));
        self::assertTrue(\app_session_policy(['HTTP_HOST' => 'localhost'], [], 'Linux')['cookie_secure']);
        self::assertTrue(\app_session_policy(['HTTP_HOST' => 'evidence.test'], [], 'Linux')['cookie_secure']);
    }

    public function testIdleExpiryHasPriorityOverRotation(): void
    {
        $decision = \app_session_lifecycle_decision($this->authenticatedState(
            authenticatedAt: 100,
            lastActivityAt: 100,
            rotatedAt: 100,
        ), 7300, $this->policy());

        self::assertSame('idle', $decision['expired']);
        self::assertFalse($decision['rotate']);
        self::assertFalse($decision['initialize']);
    }

    public function testAbsoluteExpiryIsDetected(): void
    {
        $decision = \app_session_lifecycle_decision($this->authenticatedState(
            authenticatedAt: 100,
            lastActivityAt: 43199,
            rotatedAt: 43199,
        ), 43300, $this->policy());

        self::assertSame('absolute', $decision['expired']);
        self::assertFalse($decision['rotate']);
    }

    public function testRotationDecisionDoesNotExpireActiveSession(): void
    {
        $decision = \app_session_lifecycle_decision($this->authenticatedState(
            authenticatedAt: 100,
            lastActivityAt: 999,
            rotatedAt: 100,
        ), 1000, $this->policy());

        self::assertNull($decision['expired']);
        self::assertTrue($decision['rotate']);
        self::assertFalse($decision['initialize']);
    }

    public function testLegacyAuthenticatedSessionIsInitializedWithoutImmediateLogout(): void
    {
        $decision = \app_session_lifecycle_decision(['trener_id' => 5], 1000, $this->policy());

        self::assertNull($decision['expired']);
        self::assertFalse($decision['rotate']);
        self::assertTrue($decision['initialize']);
    }

    public function testCookieContractsIncludeDeletionWithSameScope(): void
    {
        $created = \app_session_cookie_options(['HTTP_HOST' => 'data.kovopraha.cz']);
        $deleted = \app_session_expired_cookie_options([
            ...$created,
            'domain' => '',
        ], 50000);

        self::assertSame(0, $created['lifetime']);
        self::assertSame('/', $created['path']);
        self::assertTrue($created['secure']);
        self::assertTrue($created['httponly']);
        self::assertSame('Lax', $created['samesite']);
        self::assertSame(8000, $deleted['expires']);
        self::assertSame($created['path'], $deleted['path']);
        self::assertSame($created['secure'], $deleted['secure']);
        self::assertSame($created['httponly'], $deleted['httponly']);
        self::assertSame($created['samesite'], $deleted['samesite']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthenticationTransitionRotatesSessionAndCsrfToken(): void
    {
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        $_SESSION['csrf_token'] = 'old-csrf-token';
        $oldId = session_id();

        \app_session_mark_authenticated(1001);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame($oldId, session_id());
        self::assertNotSame('old-csrf-token', $_SESSION['csrf_token']);
        self::assertSame(1001, $_SESSION[APP_SESSION_AUTHENTICATED_AT]);
        self::assertSame(1001, $_SESSION[APP_SESSION_LAST_ACTIVITY_AT]);
        self::assertSame(1001, $_SESSION[APP_SESSION_ROTATED_AT]);

        \app_session_destroy(1002);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAddingSecondIdentityPreservesOriginalAbsoluteTimeoutStart(): void
    {
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        $_SESSION = $this->authenticatedState(1000, 4000, 4000) + [
            'csrf_token' => 'old-csrf-token',
        ];
        $oldId = session_id();

        \app_session_mark_authenticated(5000);

        self::assertSame(1000, $_SESSION[APP_SESSION_AUTHENTICATED_AT]);
        self::assertSame(5000, $_SESSION[APP_SESSION_LAST_ACTIVITY_AT]);
        self::assertSame(5000, $_SESSION[APP_SESSION_ROTATED_AT]);
        self::assertNotSame($oldId, session_id());
        self::assertNotSame('old-csrf-token', $_SESSION['csrf_token']);

        \app_session_destroy(5001);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExpiredAuthenticatedSessionIsClearedAndReplaced(): void
    {
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        $_SESSION = $this->authenticatedState(1000, 1000, 1000);
        $oldId = session_id();

        \app_session_start(['HTTP_HOST' => 'localhost'], 8200);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertNotSame($oldId, session_id());
        self::assertSame([], $_SESSION);

        \app_session_destroy(8201);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPublicLogoutPreservesTrainerIdentityAndRotatesSecurityState(): void
    {
        \app_session_start(['HTTP_HOST' => 'localhost'], 1000);
        $_SESSION = $this->authenticatedState(1000, 1000, 1000) + [
            'trener_id' => 7,
            'verejny_uzivatel_id' => 9,
            'verejny_uzivatel_jmeno' => 'Test User',
            'csrf_token' => 'old-csrf-token',
        ];
        $oldId = session_id();

        \app_session_logout_public_identity(1100);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame(7, $_SESSION['trener_id']);
        self::assertArrayNotHasKey('verejny_uzivatel_id', $_SESSION);
        self::assertArrayNotHasKey('verejny_uzivatel_jmeno', $_SESSION);
        self::assertNotSame($oldId, session_id());
        self::assertNotSame('old-csrf-token', $_SESSION['csrf_token']);
        self::assertSame(1100, $_SESSION[APP_SESSION_LAST_ACTIVITY_AT]);
        self::assertSame(1100, $_SESSION[APP_SESSION_ROTATED_AT]);

        \app_session_destroy(1101);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCompleteLogoutClearsSessionAndCookieIdentity(): void
    {
        \app_session_start(['HTTP_HOST' => 'data.kovopraha.cz'], 1000);
        $_SESSION['trener_id'] = 7;
        $_SESSION['csrf_token'] = 'csrf-token';
        $_COOKIE[session_name()] = session_id();
        $cookieName = session_name();

        \app_session_destroy(1100);

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
        self::assertArrayNotHasKey($cookieName, $_COOKIE);
        self::assertSame('', session_id());
    }

    /** @return array<string, int> */
    private function authenticatedState(int $authenticatedAt, int $lastActivityAt, int $rotatedAt): array
    {
        return [
            'trener_id' => 7,
            APP_SESSION_AUTHENTICATED_AT => $authenticatedAt,
            APP_SESSION_LAST_ACTIVITY_AT => $lastActivityAt,
            APP_SESSION_ROTATED_AT => $rotatedAt,
        ];
    }

    /** @return array<string, int|string|bool> */
    private function policy(): array
    {
        return \app_session_policy(['HTTP_HOST' => 'data.kovopraha.cz']);
    }
}
