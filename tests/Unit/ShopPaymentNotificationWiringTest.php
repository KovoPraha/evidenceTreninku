<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopPaymentNotificationWiringTest extends TestCase
{
    public function testPaymentTransitionUsesSharedTransactionalOutboxWithOrderEventKey(): void
    {
        $root = dirname(__DIR__, 2);
        $checkout = (string)file_get_contents($root . '/includes/shop_checkout.php');
        $service = (string)file_get_contents($root . '/includes/shop_payment_notification.php');
        $migration = (string)file_get_contents(
            $root . '/migrations/20260816170000_shop_payment_received_notification.php'
        );

        self::assertStringContainsString('shopPaymentNotificationEnqueue($pdo,$orderId)', $checkout);
        self::assertStringContainsString("'shop_payment_received'", $service);
        self::assertStringContainsString('club_event_notifications', $service);
        self::assertStringContainsString('uq_shop_payment_notification', $migration);
        self::assertStringContainsString('(order_id,notification_type)', $migration);
        self::assertStringNotContainsString('mail(', $service);
        self::assertStringContainsString("appUrl('booking/prihlaseni.php?redirect=moje_objednavky.php')", $service);
    }
}
