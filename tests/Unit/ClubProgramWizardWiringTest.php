<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ClubProgramWizardWiringTest extends TestCase
{
    public function testAdminWizardUsesOneTransactionalServiceAndAllDomainCores():void
    {
        $root=dirname(__DIR__,2);$page=(string)file_get_contents($root.'/club_program_wizard_admin.php');$service=(string)file_get_contents($root.'/includes/club_program_wizard.php');
        self::assertStringContainsString("roleAtLeast('admin')",$page);self::assertStringContainsString('csrf_verify',$page);self::assertStringContainsString('clubProgramWizardCreate',$page);self::assertStringContainsString("header('Location: club_program_wizard_admin.php",$page);
        self::assertStringContainsString('shopManualCatalogCreateInTransaction',$service);self::assertStringContainsString('kisRosterCreateSeasonInTransaction',$service);self::assertStringContainsString('kisRosterCreateTeamInTransaction',$service);self::assertStringContainsString('clubProgramCreateInTransaction',$service);self::assertStringContainsString('clubProgramCreateOfferInTransaction',$service);self::assertStringContainsString('clubProgramTermsConfigureInTransaction',$service);self::assertStringContainsString('shopCatalogPublicationActivateInTransaction',$service);
        self::assertLessThan(strpos($service,'shopCatalogPublicationActivateInTransaction'),strpos($service,'clubProgramTermsConfigureInTransaction'));
    }
}
