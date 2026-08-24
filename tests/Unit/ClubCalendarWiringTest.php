<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ClubCalendarWiringTest extends TestCase
{
    public function testStaffAndFamilyPagesUseSharedServiceAndCsrf(): void
    {
        $root=dirname(__DIR__,2);$staff=(string)file_get_contents($root.'/club_calendar.php');$family=(string)file_get_contents($root.'/booking/klubovy_kalendar.php');
        self::assertStringContainsString("includes/club_calendar.php",$staff);self::assertStringContainsString('csrf_verify',$staff);
        self::assertStringContainsString("includes/club_calendar.php",$family);self::assertStringContainsString('csrf_verify',$family);
        self::assertStringContainsString('clubCalendarFamilyRegister',$family);self::assertStringContainsString("publicShellNav('calendar')",$family);
        self::assertStringNotContainsString("['internal_note']",$family);
    }

    public function testVisibilityAndVehicleConflictWiringIsFailClosedAndProminent(): void
    {
        $root=dirname(__DIR__,2);$service=(string)file_get_contents($root.'/includes/club_calendar.php');$feed=(string)file_get_contents($root.'/includes/public_calendar_feed.php');$dashboard=(string)file_get_contents($root.'/pracovni_pozice.php');
        self::assertStringContainsString("if (\$visibility === 'public') return true",$service);
        self::assertStringContainsString("\$visibility === 'staff'",$service);
        self::assertStringContainsString("e.visibility='public'",$feed);self::assertStringContainsString("e.planning_status='confirmed'",$feed);
        self::assertStringContainsString('Kolize klubových vozidel',$dashboard);self::assertStringContainsString('border-3',$dashboard);
    }

    public function testTrainerOwnsCalendarWithExplicitCrossPositionDelegates(): void
    {
        require_once dirname(__DIR__,2).'/includes/staff_workspaces.php';
        self::assertSame('coach',\staffRouteOwner('club_calendar.php'));
        self::assertEqualsCanonicalizing(['coach','sports_lead','program_coordinator','finance_manager'],\staffRouteAllowedPositions('club_calendar.php'));
        self::assertContains('club_calendar.php',\staffPositionPrimaryRoutes()['coach']);
    }
}
