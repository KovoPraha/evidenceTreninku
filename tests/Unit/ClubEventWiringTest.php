<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ClubEventWiringTest extends TestCase
{
    public function testK3AdminIsProtectedAndOnlyOpensTheIsolatedFreeRegistrationFlow(): void
    {
        $root=dirname(__DIR__,2);$source=(string)file_get_contents($root.'/eshop_events_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);self::assertStringContainsString('csrf_verify',$source);
        self::assertStringContainsString('clubEventCreateDraft',$source);self::assertStringContainsString('clubEventAddSession',$source);self::assertStringContainsString('clubEventLinkProduct',$source);
        self::assertStringContainsString('clubEventOpenFreeRegistration',$source);
        self::assertStringContainsString('Nevytváří objednávku, platbu, soupisku ani zápis do KIS',$source);
        self::assertStringNotContainsString('INSERT INTO shop_orders',$source);self::assertStringNotContainsString('kis_',$source);
    }
    public function testAdminNavigationLinksToK3(): void
    {
        // index.php's card-wall was retired 2026-08-08 in favor of the hlavicka.php
        // navbar as the single admin navigation source (see docs/navrh-informacni-architektury.md).
        $root=dirname(__DIR__,2);foreach(['hlavicka.php','eshop_admin.php'] as $file)self::assertStringContainsString('eshop_events_admin.php',(string)file_get_contents($root.'/'.$file),$file);
    }
}
