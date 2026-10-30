<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClubCalendarWiringTest extends TestCase
{
    public function testStaffAndFamilyPagesUseSharedServiceAndCsrf(): void
    {
        $root = dirname(__DIR__, 2);
        $staff = (string)file_get_contents($root.'/club_calendar.php');
        $family = (string)file_get_contents($root.'/booking/klubovy_kalendar.php');
        self::assertStringContainsString("includes/club_calendar.php", $staff);
        self::assertStringContainsString('csrf_verify', $staff);
        self::assertStringContainsString("includes/club_calendar.php", $family);
        self::assertStringContainsString('csrf_verify', $family);
        self::assertStringContainsString('clubCalendarFamilyRegister', $family);
        self::assertStringContainsString("publicShellNav('calendar')", $family);
        self::assertStringContainsString('$usesShop', $family);
        self::assertStringContainsString('Vybrat účastníka a přidat do košíku', $family);
        self::assertStringContainsString('consent_version', $family);
        self::assertStringContainsString('cancellation_policy_plain', $family);
        self::assertStringContainsString('krouzky.php#moje-prihlasky', $family);
        self::assertStringContainsString("include __DIR__.'/free_event_registration_form.php'", $family);
        self::assertStringContainsString("include __DIR__.'/free_event_registration_form.php'", (string)file_get_contents($root.'/booking/krouzky.php'));
        $form = (string)file_get_contents($root.'/booking/free_event_registration_form.php');
        self::assertStringContainsString('name="consented"', $form);
        self::assertStringContainsString('cancellation_policy_plain', $form);
        self::assertStringNotContainsString("['internal_note']", $family);
        self::assertStringContainsString('Potvrdit akci a otevřít přihlašování', $staff);
        $service = (string)file_get_contents($root.'/includes/club_calendar.php');
        self::assertStringContainsString('calendar_confirm_and_open_registration', $service);
        self::assertStringContainsString('clubEventRegisterParticipant', $service);
        self::assertStringContainsString('clubEventPromoteNextWaitlisted', $service);
    }

    public function testStaffAthleteChooserSearchesDatabaseInsteadOfLoadingTwoThousandNames(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2).'/club_calendar.php');
        self::assertStringContainsString('athlete_q', $source);
        self::assertStringContainsString('LIMIT 25', $source);
        self::assertStringContainsString('Vyhledat sportovce jménem nebo ID', $source);
        self::assertStringNotContainsString('LIMIT 2000', $source);
    }

    public function testVisibilityAndVehicleConflictWiringIsFailClosedAndProminent(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string)file_get_contents($root.'/includes/club_calendar.php');
        $feed = (string)file_get_contents($root.'/includes/public_calendar_feed.php');
        $dashboard = (string)file_get_contents($root.'/pracovni_pozice.php');
        require_once $root . '/includes/club_calendar.php';
        $pdo = new \PDO('sqlite::memory:');
        self::assertTrue(\clubCalendarAccountCanSee($pdo, 1, null, 'public'));
        self::assertFalse(\clubCalendarAccountCanSee($pdo, 1, null, 'members'));
        self::assertFalse(\clubCalendarAccountCanSee($pdo, 1, 0, 'members'));
        self::assertTrue(\clubCalendarAccountCanSee($pdo, 1, 100, 'members'));
        self::assertFalse(\clubCalendarAccountCanSee($pdo, 1, 100, 'staff'));
        self::assertFalse(\clubCalendarAccountCanSee($pdo, 1, 100, 'unknown'));
        self::assertStringContainsString("e.visibility='public'", $feed);
        self::assertStringContainsString("e.planning_status='confirmed'", $feed);
        self::assertStringContainsString('Kolize klubových vozidel', $dashboard);
        self::assertStringContainsString('border-3', $dashboard);
    }

    public function testTrainerOwnsCalendarWithExplicitCrossPositionDelegates(): void
    {
        require_once dirname(__DIR__, 2).'/includes/staff_workspaces.php';
        self::assertSame('coach', \staffRouteOwner('club_calendar.php'));
        self::assertEqualsCanonicalizing(['coach','sports_lead','program_coordinator','finance_manager'], \staffRouteAllowedPositions('club_calendar.php'));
        self::assertContains('club_calendar.php', \staffPositionPrimaryRoutes()['coach']);
    }
}
