<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ClubEventWiringTest extends TestCase
{
    public function testK3AdminIsProtectedAndDoesNotCreateRegistrationOrKisWrites(): void
    {
        $root=dirname(__DIR__,2);$source=(string)file_get_contents($root.'/eshop_events_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);self::assertStringContainsString('csrf_verify',$source);
        self::assertStringContainsString('clubEventCreateDraft',$source);self::assertStringContainsString('clubEventAddSession',$source);self::assertStringContainsString('clubEventLinkProduct',$source);
        self::assertStringContainsString('nevytváří přihlášku, platbu, soupisku ani zápis do KIS',$source);
        self::assertStringNotContainsString('club_event_registrations',$source);self::assertStringNotContainsString('INSERT INTO shop_orders',$source);self::assertStringNotContainsString('kis_',$source);
    }
    public function testAdminNavigationLinksToK3(): void
    {
        $root=dirname(__DIR__,2);foreach(['hlavicka.php','index.php','eshop_admin.php'] as $file)self::assertStringContainsString('eshop_events_admin.php',(string)file_get_contents($root.'/'.$file),$file);
    }
}
