<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopPaymentPolicyWiringTest extends TestCase
{
    public function testAdminAndCheckoutUseImmutablePolicySnapshot(): void
    {
        $root=dirname(__DIR__,2);
        $checkout=(string)file_get_contents($root.'/includes/shop_checkout.php');
        $sumup=(string)file_get_contents($root.'/includes/sumup_gateway.php');
        $admin=(string)file_get_contents($root.'/eshop_payment_methods_admin.php');
        self::assertStringContainsString('accepted_payment_methods',$checkout);
        self::assertStringContainsString('shopPaymentPolicyForItemGroups',$checkout);
        self::assertStringContainsString('shopPaymentPolicyAllowsSumUp',$sumup);
        self::assertStringContainsString('set_product',$admin);
        self::assertStringContainsString('set_velodrome',$admin);
        self::assertStringContainsString('Smíšený košík',$admin);
    }
}
