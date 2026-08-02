<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthWiringTest extends TestCase
{
    public function testDatabaseBootstrapTerminatesInvalidSessionRequest(): void
    {
        $source = $this->source('db.php');

        self::assertStringContainsString('if (!auth_session_validate($pdo))', $source);
        self::assertStringContainsString('if (!velocotaSsoBridge($pdo))', $source);
        self::assertStringContainsString('http_response_code(401);', $source);
        self::assertStringContainsString("exit('Přihlášení již není platné.", $source);
    }

    public function testBothLoginFlowsUseRateLimitAndBindSessionVersion(): void
    {
        $trainer = $this->source('login.php');
        $public = $this->source('booking/prihlaseni.php');

        self::assertStringContainsString("\$rateScope = 'trainer_login';", $trainer);
        self::assertStringContainsString('auth_rate_limit_reserve_attempt', $trainer);
        self::assertStringContainsString('auth_rate_limit_record_success', $trainer);
        self::assertStringNotContainsString('auth_rate_limit_is_allowed', $trainer);
        self::assertStringNotContainsString('auth_rate_limit_record_failure', $trainer);
        self::assertStringContainsString('catch (Throwable $e)', $trainer);
        self::assertStringContainsString('auth_session_bind_trainer', $trainer);
        self::assertStringContainsString('session_version', $trainer);
        self::assertStringContainsString(
            "Neplatné přihlašovací jméno / email nebo heslo.",
            $trainer
        );

        self::assertStringContainsString("\$rateScope = 'public_login';", $public);
        self::assertStringContainsString('auth_rate_limit_reserve_attempt', $public);
        self::assertStringContainsString('auth_rate_limit_record_success', $public);
        self::assertStringNotContainsString('auth_rate_limit_is_allowed', $public);
        self::assertStringNotContainsString('auth_rate_limit_record_failure', $public);
        self::assertStringContainsString('catch (Throwable $exception)', $public);
        self::assertStringContainsString('auth_session_bind_public_user', $public);
        self::assertStringContainsString('Nesprávný email nebo heslo.', $public);
    }

    public function testVerificationAndSsoBindVersionedIdentities(): void
    {
        $verification = $this->source('booking/overeni.php');
        $sso = $this->source('auth/sso_bridge.php');

        self::assertStringContainsString('one_time_email_verification_consume', $verification);
        self::assertStringContainsString('auth_session_bind_public_user', $verification);
        self::assertStringContainsString('auth_session_active_version', $sso);
        self::assertStringContainsString('auth_session_bind_trainer', $sso);
    }

    public function testSensitiveLinksUseFragmentsAndLogoutRequiresPostWithCsrf(): void
    {
        $registration = $this->source('booking/registrace.php');
        $reservation = $this->source('booking/rezervovat.php');
        $waitingList = $this->source('booking/waiting_list.php');
        $verification = $this->source('booking/overeni.php');
        $approval = $this->source('booking/potvrdit.php');
        $trainerLogout = $this->source('logout.php');
        $publicLogout = $this->source('booking/odhlaseni.php');

        self::assertStringContainsString("overeni.php#token=", $registration);
        self::assertStringContainsString("#token=", $reservation);
        self::assertStringContainsString("#token=", $waitingList);
        self::assertStringNotContainsString("overeni.php?token=", $registration);
        self::assertStringContainsString('history.replaceState', $verification);
        self::assertStringContainsString('csrf_verify', $verification);
        self::assertStringContainsString("\$_GET['token']", $verification);
        self::assertStringNotContainsString('.submit()', $verification);
        self::assertStringContainsString('history.replaceState', $approval);
        self::assertStringContainsString('csrf_verify', $approval);
        self::assertStringContainsString("\$_GET['token']", $approval);
        self::assertStringNotContainsString('.submit()', $approval);
        self::assertStringContainsString("!== 'POST'", $trainerLogout);
        self::assertStringContainsString('csrf_verify', $trainerLogout);
        self::assertStringContainsString("!== 'POST'", $publicLogout);
        self::assertStringContainsString('csrf_verify', $publicLogout);
    }

    public function testBookingAndWaitlistFailClosedUnderTheSameSlotLock(): void
    {
        $booking = $this->source('booking/rezervovat.php');
        $waitingList = $this->source('booking/waiting_list.php');

        self::assertStringContainsString("'rez_' . \$lekceId", $booking);
        self::assertStringContainsString('(int)$lockStatement->fetchColumn() === 1', $booking);
        self::assertStringContainsString("'rez_' . \$lekceId", $waitingList);
        self::assertStringContainsString('(int)$lockStatement->fetchColumn() !== 1', $waitingList);
        self::assertStringContainsString("WHERE id=? AND stav='cekaci_listina'", $waitingList);
        self::assertStringContainsString("active.stav IN ('ceka','potvrzena')", $waitingList);
        self::assertStringContainsString('SELECT RELEASE_LOCK(?)', $waitingList);
    }

    public function testTrainerPasswordAndRoleChangesRevokeButTransparentRehashDoesNot(): void
    {
        $management = $this->source('sprava_treneru.php');
        $login = $this->source('login.php');
        $migrator = $this->source('bin/migrate-trainer-passwords.php');

        self::assertStringContainsString('session_version = session_version + 1', $management);
        self::assertStringContainsString('CASE WHEN role <> ?', $management);
        self::assertStringNotContainsString(
            'UPDATE treneri SET heslo = ?, session_version',
            $login
        );
        self::assertStringNotContainsString('session_version', $migrator);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($source);
        return $source;
    }
}
