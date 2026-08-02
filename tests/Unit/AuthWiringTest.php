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
        self::assertStringContainsString('http_response_code(401);', $source);
        self::assertStringContainsString("exit('Přihlášení již není platné.", $source);
    }

    public function testBothLoginFlowsUseRateLimitAndBindSessionVersion(): void
    {
        $trainer = $this->source('login.php');
        $public = $this->source('booking/prihlaseni.php');

        self::assertStringContainsString("\$rateScope = 'trainer_login';", $trainer);
        self::assertStringContainsString('auth_rate_limit_is_allowed', $trainer);
        self::assertStringContainsString('auth_rate_limit_record_failure', $trainer);
        self::assertStringContainsString('auth_session_bind_trainer', $trainer);
        self::assertStringContainsString('session_version', $trainer);
        self::assertStringContainsString(
            "Neplatné přihlašovací jméno / email nebo heslo.",
            $trainer
        );

        self::assertStringContainsString("\$rateScope = 'public_login';", $public);
        self::assertStringContainsString('auth_rate_limit_is_allowed', $public);
        self::assertStringContainsString('auth_rate_limit_record_failure', $public);
        self::assertStringContainsString('auth_session_bind_public_user', $public);
        self::assertStringContainsString('Nesprávný email nebo heslo.', $public);
    }

    public function testVerificationAndSsoBindVersionedIdentities(): void
    {
        $verification = $this->source('booking/overeni.php');
        $sso = $this->source('auth/sso_bridge.php');

        self::assertStringContainsString('SELECT id, session_version', $verification);
        self::assertStringContainsString('auth_session_bind_public_user', $verification);
        self::assertStringContainsString('auth_session_active_version', $sso);
        self::assertStringContainsString('auth_session_bind_trainer', $sso);
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
