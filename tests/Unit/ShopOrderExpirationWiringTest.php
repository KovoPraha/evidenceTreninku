<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopOrderExpirationWiringTest extends TestCase
{
    public function testExpirationHasNoPublicEndpointAndRequiresExplicitAdminOrCliApply():void
    {
        $root=dirname(__DIR__,2);
        $service=(string)file_get_contents($root.'/includes/shop_checkout.php');
        $admin=(string)file_get_contents($root.'/eshop_order_expiry_admin.php');
        $cli=(string)file_get_contents($root.'/bin/expire-shop-orders.php');
        $migration=(string)file_get_contents($root.'/migrations/20260804210000_shop_order_expiration.php');
        self::assertStringContainsString('function shopOrderExpirePending',$service);
        self::assertStringContainsString("'system',null",$service);
        self::assertStringContainsString("'expire'",$service);
        self::assertStringContainsString("roleAtLeast('admin')",$admin);
        self::assertStringContainsString('csrf_verify',$admin);
        self::assertStringContainsString('confirm_expire',$admin);
        self::assertStringContainsString('$apply=in_array(\'--apply\'',$cli);
        self::assertStringContainsString('$apply=false',$service);
        self::assertStringContainsString('payment_expires_at',$migration);
        self::assertStringContainsString('expired_at',$migration);
    }
}
