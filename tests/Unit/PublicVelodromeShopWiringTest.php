<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicVelodromeShopWiringTest extends TestCase
{
    public function testMariaDbConcurrencyContractAndLifecycleHooksAreWired(): void
    {
        $module = file_get_contents(dirname(__DIR__, 2) . '/includes/public_velodrome_shop.php');
        $checkout = file_get_contents(dirname(__DIR__, 2) . '/includes/shop_checkout.php');
        $migration = file_get_contents(dirname(__DIR__, 2) . '/migrations/20260804200000_public_velodrome_shop.php');
        self::assertIsString($module);
        self::assertIsString($checkout);
        self::assertIsString($migration);
        self::assertStringContainsString("ORDER BY lesson_id,id", $module);
        self::assertStringContainsString("FOR UPDATE", $module);
        self::assertStringContainsString('publicVelodromeShopAssertCapacity', $module);
        self::assertStringContainsString('publicVelodromeShopActivatePaidOrderInTransaction', $checkout);
        self::assertStringContainsString('publicVelodromeShopCancelOrderInTransaction', $checkout);
        self::assertStringContainsString('publicVelodromeShopAssertRefundableInTransaction', $checkout);
        self::assertStringContainsString('UNIQUE KEY uq_public_velo_cart_slot_person', $migration);
        self::assertStringContainsString('UNIQUE KEY uq_public_velo_order_reservation', $migration);
        self::assertStringContainsString("'id' => '20260804200000_public_velodrome_shop'", $migration);
    }
}
