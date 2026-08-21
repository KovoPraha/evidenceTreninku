<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ShopCheckoutWiringTest extends TestCase
{
    public function testPublicCheckoutRequiresAccountCsrfAndServerService():void
    {
        $root=dirname(__DIR__,2);$shop=(string)file_get_contents($root.'/booking/eshop.php');$order=(string)file_get_contents($root.'/booking/objednavka.php');$orders=(string)file_get_contents($root.'/booking/moje_objednavky.php');
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])",$shop);self::assertStringContainsString('csrf_verify',$shop);self::assertStringContainsString('shopCheckoutPlace',$shop);self::assertStringContainsString('shopBankSettingsEffective',$shop);self::assertStringContainsString('hash_equals',$shop);self::assertStringContainsString('cart_fingerprint',$shop);
        self::assertStringContainsString('shopOrderByCode',$order);self::assertStringContainsString("(int)\$_SESSION['verejny_uzivatel_id']",$order);self::assertStringContainsString("payment_record_status']==='pending'",$order);self::assertStringContainsString('shopPaymentQrDataUri',$order);
        self::assertStringContainsString("isset(\$_SESSION['verejny_uzivatel_id'])",$orders);self::assertStringContainsString('shopOrderListForAccount',$orders);self::assertStringContainsString('objednavka.php?code=',$orders);
    }
    public function testSchemaHasSnapshotsIdempotencyPaymentsAndInventoryAudit():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/migrations/20260803230000_shop_checkout.php');
        foreach(['idempotency_key_hash','product_name_snapshot','unit_amount_minor','vat_rate_basis_points_snapshot','variable_symbol','spd_payload','shop_inventory_movements','uq_shop_order_idempotency']as$needle)self::assertStringContainsString($needle,$source);
    }
    public function testMysqlCheckoutSerializesTheSameIdempotencyKey():void
    {
        $root=dirname(__DIR__,2);$service=(string)file_get_contents($root.'/includes/shop_checkout.php');$workflow=(string)file_get_contents($root.'/.github/workflows/tests.yml');
        self::assertStringContainsString('GET_LOCK',$service);self::assertStringContainsString('RELEASE_LOCK',$service);self::assertStringContainsString("'shop_checkout:'",$service);
        self::assertStringContainsString('php tests/Support/ShopCheckoutMariaDbSmoke.php',$workflow);
    }
    public function testAdminBankConfirmationRequiresRoleCsrfReasonAndConfirmation():void
    {
        $root=dirname(__DIR__,2);$source=(string)file_get_contents($root.'/eshop_orders_admin.php');$payments=(string)file_get_contents($root.'/eshop_payments_admin.php');
        self::assertStringContainsString("roleAtLeast('admin')",$source);self::assertStringContainsString('csrf_verify',$source);self::assertStringContainsString("(\$_POST['confirm_action']??'')==='1'",$source);self::assertStringContainsString('shopOrderAdminCancel',$source);self::assertStringContainsString('shopOrderAdminMarkReady',$source);self::assertStringContainsString('shopOrderAdminCompletePickup',$source);self::assertStringNotContainsString('shopOrderAdminConfirmBankPayment',$source);self::assertStringNotContainsString('shopOrderAdminConfirmRefund',$source);
        self::assertStringContainsString("staffRequireActivePosition('finance_manager')",$payments);self::assertStringContainsString('shopOrderAdminConfirmBankPayment',$payments);self::assertStringContainsString('shopOrderAdminConfirmRefund',$payments);self::assertStringContainsString('refund_reference',$payments);self::assertStringContainsString('reason',$payments);
    }
    public function testFulfillmentMigrationAndServiceKeepAuditAndRestockContract():void
    {
        $root=dirname(__DIR__,2);$migration=(string)file_get_contents($root.'/migrations/20260804010000_shop_order_fulfillment.php');$service=(string)file_get_contents($root.'/includes/shop_checkout.php');
        foreach(['cancelled_at','ready_at','completed_at']as$needle)self::assertStringContainsString($needle,$migration);
        foreach(['restock','refund_required','cancel','mark_ready','complete_pickup']as$needle)self::assertStringContainsString($needle,$service);
    }
    public function testRefundMigrationStoresAuditedBankConfirmation():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/migrations/20260804030000_shop_order_refunds.php');
        foreach(['refund_sent_at','refund_reference','refund_confirmed_by_trainer_id','refund_confirmation_note']as$needle)self::assertStringContainsString($needle,$source);
    }
    public function testCouponAdminCheckoutAndSchemaAreWiredSafely():void
    {
        $root=dirname(__DIR__,2);$admin=(string)file_get_contents($root.'/eshop_coupons_admin.php');$shop=(string)file_get_contents($root.'/booking/eshop.php');$migration=(string)file_get_contents($root.'/migrations/20260804050000_shop_coupons.php').(string)file_get_contents($root.'/migrations/20260804234000_shop_coupon_applicability.php');
        foreach(["roleAtLeast('admin')",'csrf_verify','shopCouponAdminCreate','shopCouponAdminSetActive','confirm_action']as$needle)self::assertStringContainsString($needle,$admin);
        foreach(['shopCouponApplyToCart','shopCouponRemoveFromCart','coupon_code']as$needle)self::assertStringContainsString($needle,$shop);
        foreach(['shop_coupons','shop_coupon_events','shop_coupon_redemptions','code_snapshot','discount_minor','usage_limit_total','applicability_mask','eligible_subtotal_minor']as$needle)self::assertStringContainsString($needle,$migration);
        foreach(['scope_goods','scope_program','scope_event','scope_velodrome','shopCouponApplicabilityLabels']as$needle)self::assertStringContainsString($needle,$admin);
    }
}
