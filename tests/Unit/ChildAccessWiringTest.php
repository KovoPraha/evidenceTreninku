<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ChildAccessWiringTest extends TestCase
{
    public function testDashboardCannotSelectPersonFromRequestAndHasNoFamilyMutations(): void
    {
        $dashboard = $this->source('booking/muj_sport.php');
        self::assertStringNotContainsString("\$_GET['sportovec_id']", $dashboard);
        self::assertStringNotContainsString("\$_POST['sportovec_id']", $dashboard);
        self::assertStringNotContainsString('familyPortalOverview', $dashboard);
        self::assertStringNotContainsString('accountPersonCanManage', $dashboard);
        self::assertStringContainsString("\$_SESSION['sportovec_pristup_id']", $dashboard);
        self::assertStringContainsString('childAccessOverview($pdo', $dashboard);
    }

    public function testDashboardHasUnifiedPortalNavigationAndHumanReadableStatuses(): void
    {
        $dashboard = $this->source('booking/muj_sport.php');
        $shell = $this->source('includes/ui_shell.php');
        self::assertStringContainsString('publicShellNav()', $dashboard);
        self::assertStringContainsString('href="../index.php"', $shell);
        self::assertStringContainsString('aria-label="Souhrn sportovce"', $dashboard);
        self::assertStringContainsString("'confirmed' => 'Přihlášeno'", $dashboard);
        self::assertStringContainsString("'payment_pending' => 'Čeká na úhradu'", $dashboard);
        self::assertStringContainsString("'paid' => 'Uhrazeno'", $dashboard);
        self::assertStringContainsString("'club_event' => 'Klubová událost'", $dashboard);
        self::assertStringContainsString("format(\$withTime ? 'j. n. Y H:i' : 'j. n. Y')", $dashboard);
        self::assertStringContainsString("childPageStatusLabel((string)\$row['status'], 'event')", $dashboard);
        self::assertStringContainsString("childPageStatusLabel((string)\$row['payment_status'], 'payment')", $dashboard);
    }

    public function testLoginIsRateLimitedAndClearsHigherPrivilegeIdentities(): void
    {
        $login = $this->source('booking/sportovec_prihlaseni.php');
        self::assertStringContainsString("\$scope = 'athlete_login';", $login);
        self::assertStringContainsString('auth_rate_limit_reserve_attempt', $login);
        self::assertStringContainsString("auth_session_clear_identity('trainer')", $login);
        self::assertStringContainsString("auth_session_clear_identity('public')", $login);
        self::assertStringContainsString('app_session_mark_authenticated()', $login);
        self::assertStringContainsString('auth_session_bind_child', $login);
    }

    public function testLogoutIsPostAndCsrfOnly(): void
    {
        $logout = $this->source('booking/sportovec_odhlaseni.php');
        self::assertStringContainsString("!== 'POST'", $logout);
        self::assertStringContainsString('csrf_verify', $logout);
        self::assertStringContainsString('app_session_logout_child_identity', $logout);
    }

    public function testMigrationUsesOneToOnePersonAndRevocationVersion(): void
    {
        $migration = $this->source('migrations/20260804190000_child_access_accounts.php');
        self::assertStringContainsString('UNIQUE KEY uq_child_access_sportovec (sportovec_id)', $migration);
        self::assertStringContainsString('UNIQUE KEY uq_child_access_login_key (login_key)', $migration);
        self::assertStringContainsString('session_version', $migration);
        self::assertStringContainsString('child_access_events', $migration);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }
}
