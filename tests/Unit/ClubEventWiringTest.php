<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ClubEventWiringTest extends TestCase
{
    public function testK3AdminIsProtectedAndSupportsTheCompleteEventLifecycle(): void
    {
        $root=dirname(__DIR__,2);$source=(string)file_get_contents($root.'/eshop_events_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);self::assertStringContainsString('csrf_verify',$source);
        self::assertStringContainsString('clubEventCreateDraft',$source);self::assertStringContainsString('clubEventAddSession',$source);self::assertStringContainsString('clubEventLinkProduct',$source);self::assertStringContainsString('clubEventUpdateDraft',$source);self::assertStringContainsString('clubEventUpdateSession',$source);self::assertStringContainsString('clubEventArchive',$source);
        self::assertStringContainsString('clubEventOpenFreeRegistration',$source);self::assertStringContainsString('clubEventOpenPaidRegistration',$source);
        self::assertStringContainsString('celý životní cyklus bezplatných i placených akcí',$source);
        self::assertStringNotContainsString('INSERT INTO shop_orders',$source);self::assertStringNotContainsString('kis_',$source);
    }
    public function testAdminNavigationLinksToK3(): void
    {
        $root=dirname(__DIR__,2);$registry=(string)file_get_contents($root.'/includes/staff_workspaces.php');$header=(string)file_get_contents($root.'/hlavicka.php');
        self::assertStringContainsString("'program_coordinator'",$registry);self::assertStringContainsString('eshop_events_admin.php',$registry);self::assertStringContainsString('staffPositionMenuGroups',$header);self::assertStringContainsString('foreach ($staff_menu_groups as $staff_group)',$header);
    }
}
