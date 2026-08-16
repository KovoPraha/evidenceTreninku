<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_checkout.php';
require_once dirname(__DIR__,2).'/includes/club_event_notification.php';
require_once dirname(__DIR__,2).'/includes/local_message_outbox.php';

final class ShopCheckoutTest extends TestCase
{
    private const BANK=['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'KOVO Praha','due_days'=>7];

    public function testCheckoutUsesCurrentServerPriceThenKeepsImmutableSnapshotAndIsIdempotent():void
    {
        $pdo=$this->database();
        $products=\shopStorefrontProducts($pdo);self::assertCount(1,$products);self::assertSame(601,(int)$products[0]['variant_id']);
        \shopCartSetQuantity($pdo,10,601,2);
        $oldFingerprint=\shopCartDetail($pdo,10)['fingerprint'];
        $pdo->exec('UPDATE shop_variants SET amount_minor=13000 WHERE id=601');
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$oldFingerprint);self::fail('Silent repricing must be rejected.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $key=bin2hex(random_bytes(16));$order=\shopCheckoutPlace($pdo,10,$key,self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        self::assertFalse($order['replayed']);self::assertSame(26000,(int)$order['total_minor']);self::assertSame('placed',$order['status']);
        self::assertSame('pending',$order['payment_record_status']);self::assertSame('personal_pickup',$order['fulfillment_method']);
        self::assertSame(26000,(int)$pdo->query('SELECT line_amount_minor FROM shop_order_items')->fetchColumn());
        self::assertSame('Tričko KOVO',$pdo->query('SELECT product_name_snapshot FROM shop_order_items')->fetchColumn());
        self::assertSame(3.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
        $replay=\shopCheckoutPlace($pdo,10,$key,self::BANK,str_repeat('0',64));self::assertTrue($replay['replayed']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
        $pdo->exec("UPDATE shop_variants SET amount_minor=99999,sku='CHANGED' WHERE id=601");
        $pdo->exec("UPDATE shop_product_publications SET public_name='Nový název' WHERE product_id=501");
        $snapshot=$pdo->query('SELECT product_name_snapshot,sku_snapshot,unit_amount_minor FROM shop_order_items')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['product_name_snapshot'=>'Tričko KOVO','sku_snapshot'=>'TRIKO-M','unit_amount_minor'=>13000],$snapshot);
        self::assertMatchesRegularExpression('/^SPD\*1\.0\*ACC:CZ6508000000192000145399\*AM:260\.00\*CC:CZK\*X-VS:[0-9]{10}\*MSG:/',(string)$order['spd_payload']);
        self::assertStringStartsWith('data:image/svg+xml',\shopPaymentQrDataUri((string)$order['spd_payload']));
        try{\shopOrderByCode($pdo,11,(string)$order['public_code']);self::fail('Foreign order must not be readable.');}catch(\ShopCheckoutException){}
        self::assertCount(1,\shopOrderListForAccount($pdo,10));self::assertSame((string)$order['public_code'],\shopOrderListForAccount($pdo,10)[0]['public_code']);self::assertSame([],\shopOrderListForAccount($pdo,11));
    }

    public function testCheckoutSnapshotsEligibleRosterPriceAndRejectsStaleClubQuote():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,10,90,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,20,90,'active','2026-01-01',NULL)");
        \shopMemberPricingSetProductPrice($pdo,20,501,10000,'CZK',7,'Klubová cena U15.');
        \shopCartSetQuantity($pdo,10,601,2);$old=\shopCartDetail($pdo,10);
        self::assertSame(20000,$old['subtotal_minor']);self::assertTrue($old['items'][0]['member_price']['is_member_price']);
        \shopMemberPricingSetProductPrice($pdo,20,501,9000,'CZK',7,'Nová klubová cena U15.');
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$old['fingerprint']);self::fail('Stale member price must be rejected.');}catch(\ShopCheckoutException){}
        $fresh=\shopCartDetail($pdo,10);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$fresh['fingerprint']);
        self::assertSame(18000,(int)$order['total_minor']);self::assertSame(9000,(int)$pdo->query('SELECT unit_amount_minor FROM shop_order_items')->fetchColumn());
    }

    public function testInvalidBankOrInsufficientStockRollsBackEverything():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,6);
        $fingerprint=\shopCartDetail($pdo,10)['fingerprint'];
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),['iban'=>'bad','bic'=>'','account_label'=>'','due_days'=>0],$fingerprint);self::fail('Invalid bank config must fail closed.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$fingerprint);self::fail('Stock must not go negative.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM shop_carts')->fetchColumn());
    }

    public function testAdminBankConfirmationIsExplicitAuditedAndIdempotent():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,1);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);$paymentId=(int)$order['payment_id'];
        try{\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Ověřeno ve Fio.',false);self::fail('Explicit confirmation is required.');}catch(\InvalidArgumentException){}
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
        $result=\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Ověřeno v bankovnictví podle VS a částky.',true);self::assertTrue($result['changed']);
        self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame('processing',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());self::assertSame('paid',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame('confirm_bank_payment',$pdo->query('SELECT action FROM shop_order_events ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
        $notification=$pdo->query('SELECT * FROM club_event_notifications')->fetch(PDO::FETCH_ASSOC);
        self::assertStringContainsString((string)$order['public_code'],$notification['subject_plain']);
        self::assertStringContainsString('125,00 CZK',$notification['body_plain']);
        self::assertStringContainsString('Tričko KOVO × 1',$notification['body_plain']);
        self::assertStringContainsString('osobnímu odběru',$notification['body_plain']);
        self::assertStringContainsString('booking/prihlaseni.php?redirect=moje_objednavky.php',$notification['body_plain']);
        foreach(['IBAN','X-VS','Bearer','sportovec_id=','order_id=','?id='] as $forbidden)self::assertStringNotContainsString($forbidden,$notification['body_plain']);
        $preview=\clubEventNotificationAdminPreview($pdo,(int)$notification['id']);self::assertSame($notification['subject_plain'],$preview['subject_plain']);self::assertSame($notification['body_plain'],$preview['body_plain']);
        self::assertFalse(\clubEventNotificationProcessOne($pdo,static fn():bool=>false));
        self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame('paid',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        $retry=\clubEventNotificationAdminRetry($pdo,(int)$notification['id'],7,'LOCALHOST kontrola adresy po odmítnutí transportu.',true);self::assertTrue($retry['changed']);self::assertSame('manual_retry',$pdo->query('SELECT action FROM club_event_notification_events')->fetchColumn());
        $outbox=sys_get_temp_dir().DIRECTORY_SEPARATOR.'evidence-payment-notification-'.bin2hex(random_bytes(6));
        self::assertTrue(\clubEventNotificationProcessOne($pdo,\localMessageOutboxSender('localhost','evidence.transactional-notification.v1',$outbox)));
        $files=glob($outbox.DIRECTORY_SEPARATOR.'*.json')?:[];self::assertCount(1,$files);
        $captured=json_decode((string)file_get_contents($files[0]),true,512,JSON_THROW_ON_ERROR);
        self::assertSame($notification['body_plain'],$captured['body']);
        unlink($files[0]);rmdir($outbox);
        self::assertFalse(\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Opakování.',true)['changed']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testCartRejectsInactiveNonGoodsAndMixedOrInvalidQuantity():void
    {
        $pdo=$this->database();
        foreach([[602,1],[603,1],[601,100]]as[$variant,$quantity]){
            try{\shopCartSetQuantity($pdo,10,$variant,$quantity);self::fail('Unsupported cart input must fail.');}
            catch(\InvalidArgumentException|\ShopCheckoutException){}
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_cart_items')->fetchColumn());
    }

    public function testPendingCancellationIsAuditedRestocksExactlyOnceAndCancelsPayment():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,2);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        try{\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Chybné objednání.',false);self::fail('Cancellation must be explicit.');}catch(\InvalidArgumentException){}
        self::assertSame(3.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        $result=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Zákazník požádal o storno před platbou.',true);
        self::assertTrue($result['changed']);self::assertSame('cancelled',$result['payment_status']);self::assertSame(1,$result['restocked_items']);
        self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame('cancelled',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame('cancelled',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());
        self::assertNotFalse($pdo->query('SELECT cancelled_at FROM shop_orders')->fetchColumn());self::assertSame('cancel',$pdo->query('SELECT action FROM shop_order_events ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
        $repeat=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Opakování stejného storna.',true);self::assertFalse($repeat['changed']);self::assertSame(0,$repeat['restocked_items']);
        self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
    }

    public function testPaidCancellationRestocksButRequiresSeparateRefund():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,1);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba ověřena.',true);
        $result=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Zákazník odstoupil, předáno účetní k vrácení.',true);
        self::assertTrue($result['changed']);self::assertSame('refund_required',$result['payment_status']);self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame('refund_required',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame('refund_required',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertStringContainsString('samostatné vrácení',$pdo->query("SELECT note FROM shop_order_events WHERE action='cancel'")->fetchColumn());
        try{\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'FIO-REF-2026-001','Ověřeno ve Fio.',false);self::fail('Refund confirmation must be explicit.');}catch(\InvalidArgumentException){}
        try{\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,"bad\nref",'Ověřeno ve Fio.',true);self::fail('Control characters must be rejected.');}catch(\InvalidArgumentException){}
        $refund=\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'FIO-REF-2026-001','Vratku odeslala a ověřila účetní.',true);self::assertTrue($refund['changed']);self::assertSame('refunded',$refund['payment_status']);
        $payment=$pdo->query('SELECT status,refund_sent_at,refund_reference,refund_confirmed_by_trainer_id,refund_confirmation_note FROM payments')->fetch(PDO::FETCH_ASSOC);self::assertSame('refunded',$payment['status']);self::assertNotNull($payment['refund_sent_at']);self::assertSame('FIO-REF-2026-001',$payment['refund_reference']);self::assertSame(7,(int)$payment['refund_confirmed_by_trainer_id']);self::assertStringContainsString('účetní',$payment['refund_confirmation_note']);
        self::assertSame('refunded',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());self::assertSame('cancelled',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());self::assertSame('confirm_refund',$pdo->query('SELECT action FROM shop_order_events ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertFalse(\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'JINÁ-REFERENCE','Opakování.',true)['changed']);self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
        self::assertFalse(\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Opakované storno po vratce.',true)['changed']);
        $mine=\shopOrderListForAccount($pdo,10);self::assertSame('refunded',$mine[0]['payment_record_status']);self::assertSame('FIO-REF-2026-001',$mine[0]['refund_reference']);
    }

    public function testPaidOrderMovesThroughReadyAndCompletedWithAudit():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,1);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        try{\shopOrderAdminMarkReady($pdo,(int)$order['id'],7,'Připraveno ve skladu.',true);self::fail('Unpaid order must not become ready.');}catch(\ShopCheckoutException){}
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba ověřena.',true);
        $ready=\shopOrderAdminMarkReady($pdo,(int)$order['id'],7,'Zabalil Admin, police A.',true);self::assertTrue($ready['changed']);self::assertSame('ready',$ready['status']);
        self::assertFalse(\shopOrderAdminMarkReady($pdo,(int)$order['id'],7,'Opakování.',true)['changed']);self::assertNotFalse($pdo->query('SELECT ready_at FROM shop_orders')->fetchColumn());
        $complete=\shopOrderAdminCompletePickup($pdo,(int)$order['id'],7,'Vydal Admin rodiči Novákovi.',true);self::assertTrue($complete['changed']);self::assertSame('completed',$complete['status']);
        self::assertFalse(\shopOrderAdminCompletePickup($pdo,(int)$order['id'],7,'Opakování.',true)['changed']);self::assertNotFalse($pdo->query('SELECT completed_at FROM shop_orders')->fetchColumn());
        self::assertSame(['place','confirm_bank_payment','mark_ready','complete_pickup'],$pdo->query('SELECT action FROM shop_order_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $admin=\shopOrderAdminList($pdo);self::assertSame('complete_pickup',$admin[0]['last_event_action']);self::assertSame(7,(int)$admin[0]['last_event_actor_id']);self::assertStringContainsString('Novákovi',$admin[0]['last_event_note']);
        try{\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Pozdní storno.',true);self::fail('Completed order must not be cancelled.');}catch(\ShopCheckoutException){}
        self::assertSame(4.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
    }

    public function testFixedCouponIsServerValidatedSnapshottedAndCountedOnce():void
    {
        $pdo=$this->database();$coupon=\shopCouponAdminCreate($pdo,7,' LETO-500 ','fixed',5000,20000,null,2,'','','Letní klubová akce.',true);self::assertSame('LETO-500',$coupon['code']);
        \shopCartSetQuantity($pdo,10,601,2);$quote=\shopCouponApplyToCart($pdo,10,'leto-500');self::assertSame(5000,(int)$quote['discount_minor']);
        $cart=\shopCartDetail($pdo,10);self::assertSame(25000,$cart['subtotal_minor']);self::assertSame(5000,$cart['discount_minor']);self::assertSame(20000,$cart['total_minor']);
        $key=bin2hex(random_bytes(16));$order=\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint']);self::assertSame(25000,(int)$order['subtotal_minor']);self::assertSame(5000,(int)$order['discount_minor']);self::assertSame(20000,(int)$order['total_minor']);self::assertSame('LETO-500',$order['coupon_code_snapshot']);
        $redemption=$pdo->query('SELECT code_snapshot,discount_type_snapshot,value_snapshot,discount_minor FROM shop_coupon_redemptions')->fetch(PDO::FETCH_ASSOC);self::assertSame(['code_snapshot'=>'LETO-500','discount_type_snapshot'=>'fixed','value_snapshot'=>5000,'discount_minor'=>5000],$redemption);self::assertSame(1,(int)$pdo->query('SELECT usage_count FROM shop_coupons')->fetchColumn());
        $replay=\shopCheckoutPlace($pdo,10,$key,self::BANK,str_repeat('0',64));self::assertTrue($replay['replayed']);self::assertSame(1,(int)$pdo->query('SELECT usage_count FROM shop_coupons')->fetchColumn());
    }

    public function testCouponValidityLimitToggleAndFingerprintFailClosed():void
    {
        $pdo=$this->database();$coupon=\shopCouponAdminCreate($pdo,7,'PERCENT10','percentage',1000,10000,1000,1,'','','Schválená desetiprocentní sleva.',true);
        self::assertCount(1,\shopCouponAdminList($pdo));try{\shopCouponAdminSetActive($pdo,(int)$coupon['id'],7,false,'Test bez potvrzení.',false);self::fail('Toggle must be explicit.');}catch(\InvalidArgumentException){}
        \shopCartSetQuantity($pdo,10,601,1);\shopCouponApplyToCart($pdo,10,'PERCENT10');$fingerprint=\shopCartDetail($pdo,10)['fingerprint'];
        self::assertTrue(\shopCouponAdminSetActive($pdo,(int)$coupon['id'],7,false,'Pozastavení kampaně.',true)['changed']);try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$fingerprint);self::fail('Inactive coupon must fail checkout.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());self::assertTrue(\shopCouponAdminSetActive($pdo,(int)$coupon['id'],7,true,'Obnovení kampaně.',true)['changed']);
        $cart=\shopCartDetail($pdo,10);self::assertSame(1000,$cart['discount_minor']);\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint']);self::assertSame(1,(int)$pdo->query('SELECT usage_count FROM shop_coupons')->fetchColumn());
        \shopCartSetQuantity($pdo,11,601,1);try{\shopCouponApplyToCart($pdo,11,'PERCENT10');self::fail('Usage limit must be enforced.');}catch(\ShopCouponException){}
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM shop_coupon_events')->fetchColumn());
    }

    public function testCouponScopeDefaultsToGoodsAndExplicitlySupportsSelectedServices():void
    {
        $pdo=$this->database();
        $goods=\shopCouponAdminCreate($pdo,7,'GOODS10','percentage',1000,0,5000,null,'','','Pouze běžné zboží.',true);
        self::assertSame(SHOP_COUPON_GOODS,(int)$goods['applicability_mask']);
        try{\shopCouponQuoteForBreakdown($goods,['goods'=>0,'club_program'=>10000,'club_event'=>0,'velodrome'=>0,'total'=>10000]);self::fail('Goods coupon must reject a service-only cart.');}catch(\ShopCouponException $exception){self::assertStringContainsString('nevztahuje',$exception->getMessage());}

        $scope=SHOP_COUPON_CLUB_PROGRAM|SHOP_COUPON_CLUB_EVENT;
        $services=\shopCouponAdminCreate($pdo,7,'SERVICES10','percentage',1000,0,null,null,'','','Výslovně jen kroužky a události.',true,$scope);
        $quote=\shopCouponQuoteForBreakdown($services,['goods'=>20000,'club_program'=>10000,'club_event'=>5000,'velodrome'=>7000,'total'=>42000]);
        self::assertSame(15000,(int)$quote['eligible_subtotal_minor']);self::assertSame(1500,(int)$quote['discount_minor']);self::assertSame($scope,(int)$quote['applicability_mask']);
        self::assertSame(['kroužky','události'],\shopCouponApplicabilityLabels($scope));
        try{\shopCouponQuoteForBreakdown($services,['goods'=>1,'club_program'=>1,'club_event'=>1,'velodrome'=>1,'total'=>5]);self::fail('Inconsistent subtotal must fail closed.');}catch(\ShopCouponException $exception){self::assertStringContainsString('neodpovídá',$exception->getMessage());}
        $services['applicability_mask']=17;try{\shopCouponQuoteForBreakdown($services,['goods'=>20000,'club_program'=>0,'club_event'=>0,'velodrome'=>0,'total'=>20000]);self::fail('Unknown scope bits must fail closed.');}catch(\ShopCouponException $exception){self::assertStringContainsString('neplatný rozsah',$exception->getMessage());}
    }

    public function testCouponBreakdownSeparatesGoodsProgramsEventsAndVelodrome():void
    {
        $pdo=$this->database();
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,variant_id INTEGER)');
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO club_program_offers VALUES(1,601)');
        $breakdown=\shopCouponBreakdownFromItems(
            $pdo,
            [['variant_id'=>601,'quantity'=>2,'amount_minor'=>1000,'currency'=>'CZK'],['variant_id'=>999,'quantity'=>1,'amount_minor'=>3000,'currency'=>'CZK']],
            [['line_amount_minor'=>4000,'currency'=>'CZK']],
            [['line_amount_minor'=>5000,'currency'=>'CZK']]
        );
        self::assertSame(['goods'=>3000,'club_program'=>2000,'club_event'=>4000,'velodrome'=>5000,'total'=>14000],$breakdown);
    }

    public function testExpiredPendingOrderUsesAuditedCancellationExactlyOnce():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,2);
        $order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        $now=new \DateTimeImmutable('2030-01-02 12:00:00');
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2030-01-01 12:00:00'");
        $preview=\shopOrderExpirationPreview($pdo,$now);self::assertCount(1,$preview);self::assertSame((int)$order['id'],(int)$preview[0]['id']);
        try{\shopOrderExpirePending($pdo,(int)$order['id'],$now,false);self::fail('Explicit confirmation is required.');}catch(\InvalidArgumentException){}
        $result=\shopOrderExpirePending($pdo,(int)$order['id'],$now,true);
        self::assertTrue($result['changed']);self::assertSame(1,$result['restocked_items']);self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        $stored=$pdo->query('SELECT status,payment_status,cancelled_at,expired_at FROM shop_orders')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('cancelled',$stored['status']);self::assertSame('cancelled',$stored['payment_status']);self::assertSame('2030-01-02 12:00:00',$stored['cancelled_at']);self::assertSame($stored['cancelled_at'],$stored['expired_at']);
        $event=$pdo->query("SELECT actor_type,actor_id,action,created_at FROM shop_order_events WHERE action='expire'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('system',$event['actor_type']);self::assertNull($event['actor_id']);self::assertSame('2030-01-02 12:00:00',$event['created_at']);
        self::assertFalse(\shopOrderExpirePending($pdo,(int)$order['id'],$now,true)['changed']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_order_events WHERE action='expire'")->fetchColumn());
    }

    public function testExpirationBatchDefaultsToDryRunAndRejectsPaidOrFutureOrders():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,1);
        $order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2030-01-01 12:00:00'");$now=new \DateTimeImmutable('2030-01-02 12:00:00');
        $dry=\shopOrderExpireBatch($pdo,$now);self::assertSame(1,$dry['examined']);self::assertSame(0,$dry['expired']);self::assertSame('placed',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Paid concurrently.',true);
        self::assertSame([],\shopOrderExpirationPreview($pdo,$now));
        try{\shopOrderExpirePending($pdo,(int)$order['id'],$now,true);self::fail('Paid order must never expire.');}catch(\ShopCheckoutException){}

        $pdo2=$this->database();\shopCartSetQuantity($pdo2,10,601,1);$future=\shopCheckoutPlace($pdo2,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo2,10)['fingerprint']);
        $pdo2->exec("UPDATE shop_orders SET payment_expires_at='2030-01-03 12:00:00'");
        try{\shopOrderExpirePending($pdo2,(int)$future['id'],$now,true);self::fail('Future order must not expire.');}catch(\ShopCheckoutException){}
        self::assertSame('placed',$pdo2->query('SELECT status FROM shop_orders')->fetchColumn());
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','parent@example.test',1,1),(11,'Cizí','Účet','foreign@example.test',1,1)");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'goods','active'),(502,'club_event','active'),(503,'goods','inactive')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'TRIKO-M','{\"size\":\"M\"}','fixed',12500,'CZK',1,2100,'5.000000',1,'active',CURRENT_TIMESTAMP),(602,502,'EVENT','{}','fixed',100,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(603,503,'OLD','{}','fixed',100,'CZK',1,0,NULL,1,'inactive',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Tričko KOVO','Klubové tričko.'),(502,'active','Kroužek','Nejde do košíku.'),(503,'inactive','Staré','Neaktivní.')");
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');$pdo->exec("INSERT INTO club_teams VALUES(20,'U15','active')");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE club_event_notification_events(id INTEGER PRIMARY KEY AUTOINCREMENT,notification_id INTEGER NOT NULL,actor_trainer_id INTEGER NOT NULL,action TEXT NOT NULL,from_status TEXT NOT NULL,attempts_before INTEGER NOT NULL,reason TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)');$pdo->exec("INSERT INTO shop_product_categories VALUES(1,501,'Oblečení',1,0)");
        foreach(['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804210000_shop_order_expiration.php','20260804234000_shop_coupon_applicability.php','20260805010000_shop_member_pricing.php','20260809090000_stripe_checkout.php'] as $filename){$migration=require dirname(__DIR__,2).'/migrations/'.$filename;$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}return $pdo;
    }
}
