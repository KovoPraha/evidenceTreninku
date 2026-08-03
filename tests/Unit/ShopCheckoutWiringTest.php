<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ShopCheckoutWiringTest extends TestCase
{
    public function testPublicCheckoutRequiresAccountCsrfAndServerService():void
    {
        $root=dirname(__DIR__,2);$shop=(string)file_get_contents($root.'/booking/eshop.php');$order=(string)file_get_contents($root.'/booking/objednavka.php');
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])",$shop);self::assertStringContainsString('csrf_verify',$shop);self::assertStringContainsString('shopCheckoutPlace',$shop);self::assertStringContainsString('shopBankSettingsFromConfig',$shop);self::assertStringContainsString('hash_equals',$shop);self::assertStringContainsString('cart_fingerprint',$shop);
        self::assertStringContainsString('shopOrderByCode',$order);self::assertStringContainsString("(int)\$_SESSION['verejny_uzivatel_id']",$order);self::assertStringContainsString('shopPaymentQrDataUri',$order);
    }
    public function testSchemaHasSnapshotsIdempotencyPaymentsAndInventoryAudit():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/migrations/20260803230000_shop_checkout.php');
        foreach(['idempotency_key_hash','product_name_snapshot','unit_amount_minor','vat_rate_basis_points_snapshot','variable_symbol','spd_payload','shop_inventory_movements','uq_shop_order_idempotency']as$needle)self::assertStringContainsString($needle,$source);
    }
    public function testAdminBankConfirmationRequiresRoleCsrfReasonAndConfirmation():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/eshop_orders_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);self::assertStringContainsString('csrf_verify',$source);self::assertStringContainsString("(\$_POST['confirm_payment']??'')==='1'",$source);self::assertStringContainsString('shopOrderAdminConfirmBankPayment',$source);self::assertStringContainsString('reason',$source);
    }
}
