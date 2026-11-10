<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_checkout.php';

final class ShopOrderAdminListSortTest extends TestCase
{
    public function testNewestOrderWinsOverPaymentStatusAndIdBreaksTimeTies(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(<<<'SQL'
            CREATE TABLE shop_orders (
                id INTEGER PRIMARY KEY, public_code TEXT, customer_name_snapshot TEXT,
                customer_email_snapshot TEXT, created_at TEXT
            );
            CREATE TABLE payments (
                id INTEGER PRIMARY KEY, payable_type TEXT, payable_id INTEGER,
                method TEXT, status TEXT, variable_symbol TEXT, due_at TEXT,
                paid_at TEXT, refund_sent_at TEXT, refund_reference TEXT,
                refund_confirmed_by_trainer_id INTEGER, refund_confirmation_note TEXT
            );
            CREATE TABLE shop_coupon_redemptions (order_id INTEGER, code_snapshot TEXT);
            CREATE TABLE shop_order_events (
                id INTEGER PRIMARY KEY, order_id INTEGER, action TEXT,
                actor_id INTEGER, note TEXT, created_at TEXT
            );
            INSERT INTO shop_orders VALUES
                (1, 'OLD', 'Starší', 'old@example.test', '2026-11-08 09:00:00'),
                (2, 'NEW', 'Novější', 'new@example.test', '2026-11-10 09:00:00'),
                (3, 'LAST', 'Poslední', 'last@example.test', '2026-11-10 09:00:00');
            INSERT INTO payments (id,payable_type,payable_id,method,status,variable_symbol) VALUES
                (1, 'shop_order', 1, 'bank', 'pending', '0000000001'),
                (2, 'shop_order', 2, 'bank', 'paid', '0000000002'),
                (3, 'shop_order', 3, 'bank', 'refund_required', '0000000003');
            SQL);

        $orders = \shopOrderAdminList($pdo);
        self::assertSame([3, 2, 1], array_map(static fn (array $row): int => (int)$row['id'], $orders));
        self::assertSame(['refund_required', 'paid', 'pending'], array_column($orders, 'payment_record_status'));
        self::assertSame(3, (int)\shopOrderAdminList($pdo, 1)[0]['id']);
        self::assertSame([2], array_map(
            static fn (array $row): int => (int)$row['id'],
            \shopOrderAdminList($pdo, 200, 'Novější')
        ));
    }
}
