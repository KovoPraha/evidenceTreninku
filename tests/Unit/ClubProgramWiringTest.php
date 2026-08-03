<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ClubProgramWiringTest extends TestCase
{
    public function testPublicPagesUseSessionCsrfBeneficiaryAndActivationServices():void
    {
        $root=dirname(__DIR__,2);$shop=(string)file_get_contents($root.'/booking/eshop.php');$mine=(string)file_get_contents($root.'/booking/moje_programy.php');
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])",$shop);self::assertStringContainsString('csrf_verify',$shop);self::assertStringContainsString('shopCartSetBeneficiary',$shop);self::assertStringContainsString('clubProgramOfferForVariant',$shop);self::assertStringContainsString('clubProgramProductHasActiveOffer',$shop);
        self::assertStringContainsString('csrf_verify',$mine);self::assertStringContainsString('clubProgramActivateOrderItem',$mine);self::assertStringContainsString('clubProgramEnrollmentsForAccount',$mine);
    }
    public function testAdminRequiresPermissionAndProgramServiceUsesTransactions():void
    {
        $root=dirname(__DIR__,2);$admin=(string)file_get_contents($root.'/club_programs_admin.php');$service=(string)file_get_contents($root.'/includes/club_program.php');
        self::assertStringContainsString("canAccess('sync_evidence')",$admin);self::assertStringContainsString('csrf_verify',$admin);self::assertStringContainsString('clubProgramCreateOffer',$admin);
        self::assertStringContainsString('beginTransaction',$service);self::assertStringContainsString("o.account_id=?",$service);self::assertStringContainsString('shopBeneficiaryAssertAccessible',$service);self::assertStringContainsString("payment_status'] !== 'paid'",$service);self::assertStringContainsString("FOR UPDATE",$service);self::assertStringContainsString('club_roster_members',$service);
    }
}
