<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ClubProgramPaymentLifecycleWiringTest extends TestCase
{
    public function testShopTransitionsCallProgramLifecycleInsideTheirTransactions():void
    {
        $root=dirname(__DIR__,2);$checkout=(string)file_get_contents($root.'/includes/shop_checkout.php');$program=(string)file_get_contents($root.'/includes/club_program.php');
        self::assertStringContainsString("require_once __DIR__.'/club_program.php'",$checkout);
        self::assertStringContainsString('clubProgramActivatePaidOrderInTransaction',$checkout);self::assertStringContainsString('clubProgramCancelOrderInTransaction',$checkout);self::assertStringContainsString('clubProgramAssertOrderHasNoActiveEnrollments',$checkout);
        self::assertStringContainsString('FOR UPDATE',$program);self::assertStringContainsString("e.status='active'",$program);self::assertStringContainsString("['source']!=='shop'",$program);self::assertStringContainsString('inTransaction',$program);
    }

    public function testManualActivationUsesOrderThenTeamThenEnrollmentLockOrder():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/includes/club_program.php');$start=strpos($source,'function clubProgramActivateOrderItemInTransaction');$end=strpos($source,'function clubProgramActivatePaidOrderInTransaction');$body=substr($source,$start,$end-$start);
        $item=strpos($body,'$itemSql=');$team=strpos($body,'$teamLockSql=');$enrollment=strpos($body,"SELECT id FROM club_program_enrollments WHERE source_order_item_id=?");$member=strpos($body,'$memberSql=');
        self::assertNotFalse($item);self::assertNotFalse($team);self::assertNotFalse($enrollment);self::assertNotFalse($member);self::assertLessThan($team,$item);self::assertLessThan($enrollment,$team);self::assertLessThan($member,$enrollment);
    }
}
