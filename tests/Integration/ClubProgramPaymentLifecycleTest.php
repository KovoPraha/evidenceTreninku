<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/includes/shop_program_quick_checkout.php';
require_once dirname(__DIR__,2) . '/includes/club_registration_consent_admin.php';

final class ClubProgramPaymentLifecycleTest extends TestCase
{
    private const BANK=['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'KOVO Praha','due_days'=>7];

    public function testSiblingsHaveIndependentCartLinesAndOneAtomicPayment(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'SIBLINGS');
        \shopCartSetQuantity($pdo,10,601,1,101);$before=\shopCartDetail($pdo,10);
        \shopCartSetQuantity($pdo,10,601,1,104);$cart=\shopCartDetail($pdo,10);
        self::assertCount(2,$cart['items']);self::assertSame(20000,$cart['total_minor']);
        self::assertNotSame($before['fingerprint'],$cart['fingerprint']);
        \shopCartSetQuantity($pdo,10,601,1,101);
        self::assertSame($cart['fingerprint'],\shopCartDetail($pdo,10)['fingerprint']);
        try { \shopCartSetBeneficiary($pdo,10,(int)$cart['items'][1]['cart_item_id'],101);self::fail('Duplicate child must fail.'); }
        catch (\ShopCheckoutException $e) { self::assertStringContainsString('už má',$e->getMessage()); }
        try { \shopCartSetQuantity($pdo,10,601,0);self::fail('Ambiguous removal must fail.'); }
        catch (\ShopCheckoutException) {}
        try { \shopCartSetQuantity($pdo,11,601,0,null,(int)$cart['items'][0]['cart_item_id']);self::fail('Foreign row must fail.'); }
        catch (\ShopCheckoutException) {}
        $key=bin2hex(random_bytes(16));$accepted=[];foreach($cart['items'] as $i)$accepted['item:'.$i['cart_item_id']]=1;
        try { \shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],[601=>1]);self::fail('Each child needs consent.'); }
        catch (\ShopCheckoutException) {}
        $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=10 AND sportovec_id=104");
        try { \shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],$accepted);self::fail('A revoked sibling must roll back the whole order.'); }
        catch (\ShopCheckoutException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $pdo->exec("UPDATE account_person_roles SET status='approved' WHERE account_id=10 AND sportovec_id=104");
        $order=\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],$accepted);
        self::assertSame(20000,(int)$order['total_minor']);
        self::assertSame([101=>10000,104=>10000],array_map('intval',$pdo->query('SELECT sportovec_id,amount_minor FROM shop_order_finance_shares ORDER BY sportovec_id')->fetchAll(PDO::FETCH_KEY_PAIR)));
        self::assertSame(10000,\clubFinanceBalanceForAthlete($pdo,101)['open_minor']);
        self::assertSame(10000,\clubFinanceBalanceForAthlete($pdo,104)['open_minor']);
        self::assertSame(0,\clubFinanceBalanceForAthlete($pdo,103)['open_minor']);
        self::assertSame(2,\clubProgramOfferForVariant($pdo,601)['held_order_count']);
        self::assertTrue(\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],$accepted)['replayed']);
        $paid=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'LOCALHOST společná platba.',true);
        self::assertSame(2,$paid['created']);
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE status='active'")->fetchColumn());
        self::assertFalse(\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'LOCALHOST opakování.',true)['changed']);
        self::assertSame(0,\clubFinanceBalanceForAthlete($pdo,101)['open_minor']);
        self::assertSame(0,\clubFinanceBalanceForAthlete($pdo,104)['open_minor']);
        $cancel=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'LOCALHOST storno rodiny.',true);
        self::assertSame(2,$cancel['cancelled']);self::assertSame(2,$cancel['rosters_ended']);
        self::assertSame(0,\clubProgramOfferForVariant($pdo,601)['occupied_count']);
        self::assertTrue(\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'LOCALHOST-FAMILY-RETURN','LOCALHOST společná vratka.',true)['changed']);
        self::assertFalse(\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'LOCALHOST-FAMILY-RETURN','LOCALHOST opakování vratky.',true)['changed']);
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn());
    }

    public function testFamilyCapacityFailureRollsBackEverythingAndRemovingOneChildPreservesOther(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,1,'LAST-PLACE');
        \shopCartSetQuantity($pdo,10,601,1,101);\shopCartSetQuantity($pdo,10,601,1,104);
        $cart=\shopCartDetail($pdo,10);$accepted=[];foreach($cart['items'] as $i)$accepted['item:'.$i['cart_item_id']]=1;
        try { \shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],$accepted);self::fail('Two children cannot hold one place.'); }
        catch (\ShopCheckoutException $e) { self::assertStringContainsString('Kapacita',$e->getMessage()); }
        foreach(['shop_orders','shop_order_items','payments','shop_order_finance_shares'] as $table)self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn());
        self::assertSame($cart['fingerprint'],\shopCartDetail($pdo,10)['fingerprint']);
        \shopCartSetQuantity($pdo,10,601,0,null,(int)$cart['items'][0]['cart_item_id']);
        $remaining=\shopCartDetail($pdo,10);self::assertCount(1,$remaining['items']);self::assertSame(104,(int)$remaining['items'][0]['beneficiary_sportovec_id']);
        self::assertSame(10000,(int)\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$remaining['fingerprint'],[601=>1])['total_minor']);
    }

    public function testFamilyMigrationPreservesLegacyCartAndIsRepeatable(): void
    {
        $pdo=$this->database(false);$this->offer($pdo,601,501,10,5,'MIGRATION');
        $order=$this->checkout($pdo,11,103,601);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'LOCALHOST migration history.',true);
        $historical=$pdo->query('SELECT id,order_id,beneficiary_sportovec_id,line_amount_minor,program_terms_snapshot_json FROM shop_order_items')->fetchAll();
        \shopCartSetQuantity($pdo,10,601,1,101);$before=\shopCartDetail($pdo,10);
        $migration=require dirname(__DIR__,2).'/migrations/20261023210000_family_course_cart.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        self::assertSame($historical,$pdo->query('SELECT id,order_id,beneficiary_sportovec_id,line_amount_minor,program_terms_snapshot_json FROM shop_order_items')->fetchAll());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn());
        self::assertFalse($pdo->query('PRAGMA foreign_key_check')->fetch());
        self::assertSame($before['fingerprint'],\shopCartDetail($pdo,10)['fingerprint']);
        self::assertSame(101,(int)$pdo->query('SELECT ci.participant_key FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id WHERE c.account_id=10')->fetchColumn());
        \shopCartSetQuantity($pdo,10,601,1,104);self::assertCount(2,\shopCartDetail($pdo,10)['items']);
        $this->expectException(\PDOException::class);
        $pdo->exec('INSERT INTO shop_cart_items(cart_id,variant_id,quantity,beneficiary_sportovec_id,participant_key) SELECT cart_id,variant_id,quantity,beneficiary_sportovec_id,participant_key FROM shop_cart_items LIMIT 1');
    }

    public function testFamilySharesRespectCouponCategoriesAndPreserveEveryMinorUnit(): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'SHARES');
        $order=$this->checkout($pdo,10,101,601);
        $items=[['offer_type'=>'goods','beneficiary_sportovec_id'=>null,'quantity'=>2,'amount_minor'=>101],
            ['offer_type'=>'program','beneficiary_sportovec_id'=>101,'quantity'=>1,'amount_minor'=>10001],
            ['offer_type'=>'program','beneficiary_sportovec_id'=>104,'quantity'=>1,'amount_minor'=>10000]];
        $pdo->beginTransaction();
        \clubFinanceStoreOrderShares($pdo,(int)$order['id'],$items,[],[],['discount_minor'=>101,'eligible_subtotal_minor'=>20001,'applicability_mask'=>2],104,50,20152);
        $pdo->commit();
        $shares=array_map('intval',$pdo->query('SELECT sportovec_id,amount_minor FROM shop_order_finance_shares ORDER BY sportovec_id')->fetchAll(PDO::FETCH_KEY_PAIR));
        self::assertSame([101=>9951,104=>10201],$shares);self::assertSame(20152,array_sum($shares));
    }

    public static function mixedFamilyCoupons(): array
    {
        return [
            'program-only fixed' => [SHOP_COUPON_CLUB_PROGRAM,'fixed',101,[101=>9950,104=>10151]],
            'goods-only fixed' => [SHOP_COUPON_GOODS,'fixed',101,[101=>10000,104=>10101]],
            'all categories fixed' => [SHOP_COUPON_ALL,'fixed',101,[101=>9950,104=>10151]],
            'program percentage' => [SHOP_COUPON_CLUB_PROGRAM,'percentage',9900,[101=>100,104=>302]],
            'zero second course share' => [SHOP_COUPON_CLUB_PROGRAM,'fixed',19999,[101=>1,104=>202]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mixedFamilyCoupons')]
    public function testMixedSiblingCheckoutKeepsCouponSharesAndStockTogether(int $mask,string $type,int $value,array $expectedShares): void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'MIXED-SIBLINGS');
        $migration=require dirname(__DIR__,2).'/migrations/20260804234000_shop_coupon_applicability.php';
        $migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $pdo->exec("INSERT INTO shop_products VALUES(503,'LOCALHOST NEPLATIT tričko','goods','active')");
        $pdo->exec("INSERT INTO shop_variants VALUES(603,503,'TEST-SHIRT','{}','fixed',101,'CZK',1,0,'5',1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO shop_product_publications VALUES(503,'active','LOCALHOST NEPLATIT tričko','Test')");
        // This fixture predates the optional financial-profile migration; exercise its current contract.
        $pdo->exec('ALTER TABLE shop_orders ADD COLUMN finance_sportovec_id INTEGER NULL');
        \shopCartSetQuantity($pdo,10,601,1,101);\shopCartSetQuantity($pdo,10,601,1,104);
        \shopCartSetQuantity($pdo,10,603,2);
        \shopCouponAdminCreate($pdo,7,'LOCALHOST-MIXED',$type,$value,0,null,1,'','','LOCALHOST isolated coupon.',true,$mask);
        \shopCouponApplyToCart($pdo,10,'LOCALHOST-MIXED');
        $cart=\shopCartDetail($pdo,10);$accepted=[];
        foreach($cart['items'] as $line)$accepted['item:'.$line['cart_item_id']]=1;
        try { \shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],$accepted);self::fail('Unassigned goods require an explicit financial profile.'); }
        catch (\ShopCheckoutException|\ClubFinanceException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(5,(int)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=603')->fetchColumn());
        $order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],$accepted,104);
        self::assertSame(array_sum($expectedShares),(int)$order['total_minor']);
        self::assertSame($expectedShares,array_map('intval',$pdo->query('SELECT sportovec_id,amount_minor FROM shop_order_finance_shares ORDER BY sportovec_id')->fetchAll(PDO::FETCH_KEY_PAIR)));
        self::assertSame(3,(int)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=603')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_coupon_redemptions')->fetchColumn());
        $paid=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'LOCALHOST mixed payment.',true);
        self::assertSame(2,$paid['created']);
        self::assertSame(3,(int)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=603')->fetchColumn());
        \shopOrderAdminCancel($pdo,(int)$order['id'],7,'LOCALHOST mixed cancellation.',true);
        self::assertSame(5,(int)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=603')->fetchColumn());
        self::assertSame(0,\clubProgramOfferForVariant($pdo,601)['occupied_count']);
    }

    public function testExistingAthleteCheckoutEnforcesCustomConsentsAndStoresOptionalDeclineOnce():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'CUSTOM-CONSENTS');$input=$this->installCustomConsents($pdo);
        \shopCartSetQuantity($pdo,10,601,1,101);$cart=\shopCartDetail($pdo,10);$key=bin2hex(random_bytes(16));
        try{\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],[601=>1]);self::fail('Missing custom confirmation must fail.');}catch(\InvalidArgumentException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $order=\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],[601=>1],customConsentInput:$input);
        self::assertSame([1,0],array_map('intval',$pdo->query('SELECT accepted FROM club_registration_consent_acceptances ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(101,(int)$pdo->query('SELECT oi.beneficiary_sportovec_id FROM shop_order_items oi JOIN club_registration_consent_acceptances a ON a.order_item_id=oi.id LIMIT 1')->fetchColumn());
        $repeat=\shopCheckoutPlace($pdo,10,$key,self::BANK,$cart['fingerprint'],[601=>1]);
        self::assertSame($order['id'],$repeat['id']);self::assertTrue($repeat['replayed']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_registration_consent_acceptances')->fetchColumn());
    }

    public function testCustomConsentSnapshotFailureRollsBackTheWholeCheckout():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'CUSTOM-FAIL');$input=$this->installCustomConsents($pdo);
        \shopCartSetQuantity($pdo,10,601,1,101);$cart=\shopCartDetail($pdo,10);
        $pdo->exec("CREATE TRIGGER fail_consent_snapshot BEFORE INSERT ON club_registration_consent_acceptances BEGIN SELECT RAISE(ABORT,'LOCALHOST injected failure'); END");
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],[601=>1],customConsentInput:$input);self::fail('Snapshot failure must roll back the order.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_items')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    private function installCustomConsents(PDO $pdo):array
    {
        foreach(['20261021150000_athlete_registration_text_drafts.php','20261021170000_registration_text_titles.php','20261021190000_custom_club_registration_consents.php'] as $file){$m=require dirname(__DIR__,2).'/migrations/'.$file;($m['up'])($pdo);self::assertTrue(($m['verify'])($pdo));}
        $prior=$_SESSION??[];$_SESSION=['trener_id'=>7,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];
        try{foreach([true,false] as $required){$c=\clubConsentCreate($pdo,$required?'LOCALHOST Povinný':'LOCALHOST Volitelný','LOCALHOST Platný souhlas.','LOCALHOST test',$required,true);\clubConsentPublish($pdo,$c['id'],$c['draft_id'],true);}}finally{$_SESSION=$prior;}
        $terms=\clubConsentCurrent($pdo);$accepted=[];foreach($terms as $term)if((int)$term['custom_required']===1)$accepted[(int)$term['id']]='1';
        return ['club_consent_manifest'=>\clubConsentManifest($terms),'club_consent_accepted'=>$accepted];
    }

    public function testCashActivatesProgramOnceAndCapacityFailureRollsBackMethod():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-CASH');
        $first=$this->checkout($pdo,10,101,601);$second=$this->checkout($pdo,11,103,601);
        $result=\shopOrderAdminConfirmManualPayment($pdo,(int)$first['payment_id'],7,'cash','Hotovost převzata.',true);
        self::assertTrue($result['changed']);self::assertSame(1,$result['created']);
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        self::assertFalse(\shopOrderAdminConfirmManualPayment($pdo,(int)$first['payment_id'],7,'cash','Opakování.',true)['changed']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
        $pdo->exec('UPDATE club_program_offers SET capacity=1');
        try{\shopOrderAdminConfirmManualPayment($pdo,(int)$second['payment_id'],7,'cash','Další hotovost.',true);self::fail('Capacity failure must roll back cash receipt.');}catch(\ShopCheckoutException){}
        $payment=$pdo->query('SELECT method,status FROM payments WHERE id='.(int)$second['payment_id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['method'=>'bank_transfer','status'=>'pending'],$payment);
        self::assertFalse($pdo->inTransaction());
    }

    public function testPaidConfirmationAutomaticallyActivatesExactlyOnce():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);
        $result=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba ověřena.',true);
        self::assertTrue($result['changed']);self::assertSame(1,$result['program_items']);self::assertSame(1,$result['created']);
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        self::assertSame('trainer',$pdo->query('SELECT actor_type FROM club_program_enrollment_events')->fetchColumn());
        self::assertStringContainsString('Kroužek byl po přijetí platby aktivován',(string)$pdo->query('SELECT body_plain FROM club_event_notifications')->fetchColumn());
        try{\shopOrderAdminMarkReady($pdo,(int)$order['id'],7,'Pokus o výdej kroužku.',true);self::fail('Program order must not enter goods fulfillment.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Kroužek nemá výdejový stav',$exception->getMessage());}
        $repeat=\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Opakované ověření.',true);
        self::assertFalse($repeat['changed']);self::assertSame(0,$repeat['created']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
    }

    public function testUnauthorizedOrFullOfferRollsBackPaymentConfirmation():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$first=$this->checkout($pdo,10,101,601);$second=$this->checkout($pdo,11,103,601);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);
        $pdo->exec('UPDATE club_program_offers SET capacity=1');
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Druhá platba.',true);self::fail('Full capacity must roll back payment.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Kapacita',$exception->getMessage());}
        $secondState=$pdo->query('SELECT o.status,o.payment_status,p.status AS payment_record_status FROM shop_orders o JOIN payments p ON p.payable_id=o.id AND p.payable_type=\'shop_order\' WHERE o.id='.(int)$second['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['status'=>'placed','payment_status'=>'pending','payment_record_status'=>'pending'],$secondState);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());self::assertFalse($pdo->inTransaction());

        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);$pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to=CURRENT_TIMESTAMP WHERE account_id=10 AND sportovec_id=101");
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Pozdní platba.',true);self::fail('Revoked beneficiary must roll back payment.');}catch(\ShopCheckoutException){}
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
    }

    public function testDuplicateOfferForSameChildCannotBecomeSecondPaidOrder():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,3,'OFFER-A');$first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);$duplicate=$this->checkout($pdo,10,101,601);
        try{\shopOrderAdminConfirmBankPayment($pdo,(int)$duplicate['payment_id'],7,'Duplicitní platba.',true);self::fail('A second order must not alias the first enrollment.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Duplicitní',$exception->getMessage());}
        $statement=$pdo->prepare('SELECT o.status,o.payment_status,p.status AS payment_record_status FROM shop_orders o JOIN payments p ON p.payable_id=o.id AND p.payable_type=\'shop_order\' WHERE o.id=?');$statement->execute([(int)$duplicate['id']]);self::assertSame(['status'=>'placed','payment_status'=>'pending','payment_record_status'=>'pending'],$statement->fetch(PDO::FETCH_ASSOC));self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
    }

    public function testPaidCancellationEndsEnrollmentAndRefundNeverChangesItAgain():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba.',true);
        $cancel=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Rodina odstoupila.',true);self::assertSame('refund_required',$cancel['payment_status']);self::assertSame(1,$cancel['cancelled']);self::assertSame(1,$cancel['rosters_ended']);
        $enrollment=$pdo->query('SELECT status,ended_at,ended_reason,ended_by_trainer_id FROM club_program_enrollments')->fetch(PDO::FETCH_ASSOC);self::assertSame('cancelled',$enrollment['status']);self::assertNotEmpty($enrollment['ended_at']);self::assertSame('Rodina odstoupila.',$enrollment['ended_reason']);self::assertSame(7,(int)$enrollment['ended_by_trainer_id']);self::assertSame('removed',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $eventsBefore=(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn();$repeat=\shopOrderAdminCancel($pdo,(int)$order['id'],7,'Opakování.',true);self::assertFalse($repeat['changed']);self::assertSame($eventsBefore,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());
        $refund=\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'REF-1','Vratka odeslána.',true);self::assertTrue($refund['changed']);$repeatRefund=\shopOrderAdminConfirmRefund($pdo,(int)$order['id'],7,'REF-2','Opakování.',true);self::assertFalse($repeatRefund['changed']);self::assertSame($eventsBefore,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollment_events')->fetchColumn());self::assertSame('cancelled',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());
    }

    public function testCancelledAndRefundedProgramCanBeBoughtAgainSafely():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');
        $first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První platba.',true);
        \shopOrderAdminCancel($pdo,(int)$first['id'],7,'Rodina odstoupila.',true);\shopOrderAdminConfirmRefund($pdo,(int)$first['id'],7,'REF-REBUY-1','Vratka odeslána.',true);
        $second=$this->checkout($pdo,10,101,601);$confirmed=\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Nová platba.',true);

        self::assertTrue($confirmed['changed']);self::assertSame(1,$confirmed['created']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active' AND active_token='active'")->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());

        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_events WHERE action='restore'")->fetchColumn());
    }

    public function testRosterStaysWhileAnotherActiveProgramUsesSamePersonAndTeam():void
    {
        $pdo=$this->database();$program=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];$this->offer($pdo,601,501,10,5,'OFFER-A',$program);$this->offer($pdo,602,502,10,5,'OFFER-B',$program,'2027-02-01','2027-06-30');
        $first=$this->checkout($pdo,10,101,601);\shopOrderAdminConfirmBankPayment($pdo,(int)$first['payment_id'],7,'První období.',true);$second=$this->checkout($pdo,10,101,602);\shopOrderAdminConfirmBankPayment($pdo,(int)$second['payment_id'],7,'Druhé období.',true);
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_program_enrollments WHERE status='active'")->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_roster_members')->fetchColumn());
        $firstCancel=\shopOrderAdminCancel($pdo,(int)$first['id'],7,'Storno prvního období.',true);self::assertSame(0,$firstCancel['rosters_ended']);self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $secondCancel=\shopOrderAdminCancel($pdo,(int)$second['id'],7,'Storno druhého období.',true);self::assertSame(1,$secondCancel['rosters_ended']);self::assertSame('removed',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
    }

    public function testTwiceWeeklyOccupiesBothDayCapacitiesAndBothAttendanceRosters():void
    {
        $pdo=$this->database();
        $migration=require dirname(__DIR__,2).'/migrations/20261020140000_club_program_physical_teams.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(11,1,'TU','Úterý','vše','děti','active',7),(12,1,'TH','Čtvrtek','vše','děti','active',7)");
        $pdo->exec("INSERT INTO shop_products VALUES(503,'Točňáci 2×','program','active');INSERT INTO shop_variants(id,product_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(603,503,'BOTH','{}','fixed',15000,'CZK',1,0,NULL,1,'active');INSERT INTO shop_product_publications VALUES(503,'active','Točňáci 2×','Oba dny')");
        $tuesday=(int)\clubProgramCreate($pdo,7,'TEST-TU','Točňáci úterý')['id'];
        $thursday=(int)\clubProgramCreate($pdo,7,'TEST-TH','Točňáci čtvrtek')['id'];
        $both=(int)\clubProgramCreate($pdo,7,'TEST-BOTH','Točňáci 2×')['id'];
        $this->offer($pdo,601,501,11,2,'TUESDAY',$tuesday);
        $this->offer($pdo,602,502,12,2,'THURSDAY',$thursday);
        $this->offer($pdo,603,503,10,2,'BOTH',$both);
        $pdo->prepare('INSERT INTO club_program_physical_teams(program_id,team_id) VALUES(?,11),(?,12)')->execute([$both,$both]);
        $tuesdayOrder=$this->checkout($pdo,10,101,601);
        $bothOrder=$this->checkout($pdo,11,103,603);
        self::assertSame(0,\clubProgramOfferForVariant($pdo,601)['available_count']);
        self::assertSame(1,\clubProgramOfferForVariant($pdo,602)['available_count']);
        self::assertSame(0,\clubProgramOfferForVariant($pdo,603)['available_count']);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$tuesdayOrder['payment_id'],7,'Platba úterý.',true);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$bothOrder['payment_id'],7,'Platba oba dny.',true);
        self::assertSame([10,11,12],array_map('intval',$pdo->query("SELECT team_id FROM club_roster_members WHERE sportovec_id=103 AND status='active' ORDER BY team_id")->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame([11],array_map('intval',$pdo->query("SELECT team_id FROM club_roster_members WHERE sportovec_id=101 AND status='active' ORDER BY team_id")->fetchAll(PDO::FETCH_COLUMN)));
        \shopOrderAdminCancel($pdo,(int)$bothOrder['id'],7,'Odhlášení z obou dnů.',true);
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=103 AND status='active'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id=101 AND status='active'")->fetchColumn());
        self::assertSame(1,\clubProgramOfferForVariant($pdo,601)['available_count']);
        self::assertSame(2,\clubProgramOfferForVariant($pdo,602)['available_count']);
    }

    public function testExistingTwiceWeeklyEnrollmentIsBackfilledIntoBothDayRostersIdempotently():void
    {
        require_once dirname(__DIR__,2).'/includes/club_tocnaci_split.php';
        $pdo=$this->database();
        $migration=require dirname(__DIR__,2).'/migrations/20261020140000_club_program_physical_teams.php';$migration['up']($pdo);
        $pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(11,1,'TU','Úterý','vše','děti','active',7),(12,1,'TH','Čtvrtek','vše','děti','active',7)");
        $program=(int)\clubProgramCreate($pdo,7,'KROUZKY-2627-TOCNACI-2X-TYDNE','Točňáci 2× týdně')['id'];
        $this->offer($pdo,601,501,10,30,'TOCNACI-BOTH',$program);
        $order=$this->checkout($pdo,10,101,601);
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba před rozdělením.',true);
        self::assertSame([10],array_map('intval',$pdo->query('SELECT team_id FROM club_roster_members ORDER BY team_id')->fetchAll(PDO::FETCH_COLUMN)));
        $targets=['UTERY'=>['team_id'=>11],'CTVRTEK'=>['team_id'=>12]];
        $first=\clubTocnaciConnectTwiceWeekly($pdo,$targets,1,7);
        self::assertSame(2,$first['rosters_created']);
        self::assertSame(0,\clubTocnaciConnectTwiceWeekly($pdo,$targets,1,7)['rosters_created']);
        self::assertSame([10,11,12],array_map('intval',$pdo->query("SELECT team_id FROM club_roster_members WHERE status='active' ORDER BY team_id")->fetchAll(PDO::FETCH_COLUMN)));
        \shopOrderAdminCancel($pdo,(int)$order['id'],7,'Odhlášení.',true);
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM club_roster_members WHERE status='active'")->fetchColumn());
    }

    public function testLifecycleMigrationIsRepeatableOnSqlite():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260804160000_club_program_lifecycle.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
    }

    public function testPendingProgramOrderCanExpireWithoutInventingTrainerActor():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-A');$order=$this->checkout($pdo,10,101,601);
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2030-01-01 12:00:00'");
        $result=\shopOrderExpirePending($pdo,(int)$order['id'],new \DateTimeImmutable('2030-01-02 12:00:00'),true);
        self::assertTrue($result['changed']);self::assertSame('cancelled',$result['payment_status']);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame('system',$pdo->query("SELECT actor_type FROM shop_order_events WHERE action='expire'")->fetchColumn());
    }

    public function testBirthYearRestrictionWarnsButDoesNotBlockCartOrCheckout():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'OFFER-AGE',null,'2026-09-01','2027-01-31',2016,2019);
        \shopCartSetQuantity($pdo,11,601,1,103);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_cart_items')->fetchColumn());
        self::assertStringContainsString('mimo doporučení',\clubProgramBeneficiaryBirthYearWarning($pdo,\clubProgramOfferForVariant($pdo,601),103));
        $foreignCart=\shopCartDetail($pdo,11);$order=\shopCheckoutPlace($pdo,11,bin2hex(random_bytes(16)),self::BANK,$foreignCart['fingerprint'],[601=>1]);
        self::assertSame('placed',$order['status']);
        self::assertStringContainsString('Věk mimo doporučení',(string)$pdo->query("SELECT note FROM shop_order_events WHERE action='place'")->fetchColumn());

        \shopCartSetQuantity($pdo,10,601,1,104);
        self::assertStringContainsString('nebylo možné ověřit',\clubProgramBeneficiaryBirthYearWarning($pdo,\clubProgramOfferForVariant($pdo,601),104));
        $allowedItem=(int)$pdo->query('SELECT ci.id FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id WHERE c.account_id=10')->fetchColumn();
        \shopCartSetBeneficiary($pdo,10,$allowedItem,101);
        self::assertSame(101,(int)$pdo->query('SELECT beneficiary_sportovec_id FROM shop_cart_items WHERE id='.$allowedItem)->fetchColumn());
    }

    public function testQuickProgramCheckoutPaysBeforeReviewThenActivatesAfterApproval():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'OFFER-QUICK',null,'2026-09-01','2027-01-31',2016,2019);
        $seriesMigration=require dirname(__DIR__,2).'/migrations/20261021210000_shop_number_series.php';$seriesMigration['up']($pdo);
        $terms=\athleteRegistrationCurrentTerms($pdo);$versions=[];foreach($terms as$purpose=>$term)$versions[$purpose]=$term['version'];
        $programTerms=\clubProgramTermsEffective($pdo,(int)$pdo->query('SELECT id FROM club_programs')->fetchColumn(),(int)$pdo->query('SELECT id FROM club_program_offers')->fetchColumn());
        $input=[
            'parent_first_name'=>'Rychlý','parent_last_name'=>'Rodič','email'=>'quick@example.test','contact_phone'=>'+420 777 123 456',
            'requested_role'=>'guardian','jmeno'=>'Starší','prijmeni'=>'Dítě','narozeni'=>'2012-05-06','citizenship_country_code'=>'SK',
            'address_street'=>'Testovací','address_house_number'=>'12','address_orientation_number'=>'3','address_city'=>'Praha','address_postcode'=>'19000',
            'has_czech_birth_number'=>'0','birth_number'=>'','member_data_notice'=>'1','birth_number_legal_notice'=>'1','photo_internal'=>'0','photo_public'=>'0',
            'program_terms_accepted'=>'1','program_term_version'=>['program_cancellation'=>$programTerms['program_cancellation']['terms_version'],'program_consent'=>$programTerms['program_consent']['terms_version']],'message'=>'',
        ];
        $input+=$this->installCustomConsents($pdo);
        $missing=$input;$missing['club_consent_accepted']=[];
        try{\shopProgramQuickCheckoutPlace($pdo,601,$missing,$versions,bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK);self::fail('Mandatory custom consent must fail before creating an account.');}catch(\InvalidArgumentException $e){self::assertStringContainsString('povinný souhlas',$e->getMessage());}
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $stale=$input;$stale['program_term_version']['program_consent']='stale';
        try{\shopProgramQuickCheckoutPlace($pdo,601,$stale,$versions,bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK);self::fail('Stale consent must fail before account or order creation.');}catch(\ShopCheckoutException $exception){self::assertStringContainsString('Podmínky kroužku se změnily',$exception->getMessage());}
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM account_person_claim_requests')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $result=\shopProgramQuickCheckoutPlace($pdo,601,$input,$versions,bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK);
        self::assertSame((new \DateTimeImmutable('now',new \DateTimeZone('Europe/Prague')))->format('y').'10000001',$result['variable_symbol']);
        self::assertSame('Kroužky – bez rozlišení',$pdo->query('SELECT category_name_snapshot FROM shop_order_accounting_items')->fetchColumn());
        self::assertSame('quick_program',$result['checkout_mode']);self::assertStringContainsString('mimo doporučení',(string)$result['age_warning']);
        self::assertSame([1,0],array_map('intval',$pdo->query('SELECT accepted FROM club_registration_consent_acceptances ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(0,(int)$pdo->query("SELECT email_overeno FROM verejni_uzivatele WHERE email='quick@example.test'")->fetchColumn());
        self::assertSame('pending',$pdo->query("SELECT status FROM account_person_claim_requests WHERE request_kind='athlete_registration'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT athlete_registration_request_id FROM shop_order_items')->fetchColumn());
        self::assertNull($pdo->query('SELECT beneficiary_sportovec_id FROM shop_order_items')->fetchColumn());
        self::assertNull($pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());

        $paid=\shopOrderAdminConfirmBankPayment($pdo,(int)$result['payment_id'],7,'Platba rychlé přihlášky.',true);
        self::assertSame(1,$paid['pending_identity']);self::assertSame(0,$paid['created']);self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        $paymentBody=(string)$pdo->query("SELECT body_plain FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn();
        self::assertStringContainsString('Údaje sportovce nyní zkontroluje klub',$paymentBody);self::assertStringNotContainsString('Kroužek byl po přijetí platby aktivován',$paymentBody);

        $accountId=(int)$pdo->query("SELECT id FROM verejni_uzivatele WHERE email='quick@example.test'")->fetchColumn();
        $pdo->exec("INSERT INTO sportovci(id,jmeno,prijmeni,narozeni) VALUES(105,'Starší','Dítě','2012-05-06')");
        $pdo->prepare("UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=?")->execute([$accountId]);
        $pdo->prepare("UPDATE account_person_claim_requests SET status='approved',matched_sportovec_id=105,active_fingerprint=NULL,decided_by_trainer_id=7,decided_at=CURRENT_TIMESTAMP WHERE id=1")->execute();
        $pdo->prepare("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(?,105,'guardian','approved','claim',CURRENT_TIMESTAMP,7,7,'Schváleno v testu.')")->execute([$accountId]);
        $pdo->beginTransaction();$linked=\clubProgramLinkApprovedAthleteRequestInTransaction($pdo,1,105,7);$pdo->commit();
        self::assertSame(['linked'=>1,'activated'=>1],$linked);
        self::assertSame(105,(int)$pdo->query('SELECT beneficiary_sportovec_id FROM shop_order_items')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_enrollments')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_roster_members')->fetchColumn());
        $notification=$pdo->query("SELECT * FROM club_event_notifications WHERE notification_type='program_activated'")->fetch();
        self::assertSame('quick@example.test',$notification['recipient_email']);
        self::assertStringContainsString('zařazen do soupisky',$notification['body_plain']);
        self::assertStringContainsString('/booking/moje_programy.php',$notification['body_plain']);
        $pdo->beginTransaction();$repeated=\clubProgramLinkApprovedAthleteRequestInTransaction($pdo,1,105,7);$pdo->commit();
        self::assertSame(0,$repeated['activated']);
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='program_activated'")->fetchColumn());
    }

    public function testEmptyBirthYearRangeDoesNotRejectMissingBirthDate():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,5,'OFFER-ANY');\shopCartSetQuantity($pdo,10,601,1,104);
        self::assertSame(104,(int)$pdo->query('SELECT beneficiary_sportovec_id FROM shop_cart_items')->fetchColumn());
        self::assertSame('bez omezení ročníku',\clubProgramBirthYearLabel(\clubProgramOfferForVariant($pdo,601)));
    }

    public function testPendingOrderHoldsCapacityAndDeadlineReleasesItLazily():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,1,'OFFER-HOLD');$this->checkout($pdo,10,101,601);
        $held=\clubProgramOfferForVariant($pdo,601);
        self::assertSame(0,$held['active_enrollment_count']);self::assertSame(1,$held['held_order_count']);self::assertSame(0,$held['available_count']);
        try{\shopCartSetQuantity($pdo,11,601,1,103);self::fail('A valid unpaid order must hold the final place.');}
        catch(\ShopCheckoutException $exception){self::assertStringContainsString('dostupná',$exception->getMessage());}
        $pdo->exec("UPDATE shop_orders SET payment_expires_at='2000-01-01 00:00:00'");
        $released=\clubProgramOfferForVariant($pdo,601);
        self::assertSame(0,$released['held_order_count']);self::assertSame(1,$released['available_count']);
        self::assertSame('placed',$pdo->query('SELECT status FROM shop_orders')->fetchColumn(),'Lazy release must not require a background status mutation.');
        \shopCartSetQuantity($pdo,11,601,1,103);self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id WHERE c.active_account_id=11")->fetchColumn());
    }

    public function testCancellationReleasesHoldAndPaymentDoesNotDoubleCount():void
    {
        $cancelDb=$this->database();$this->offer($cancelDb,601,501,10,1,'OFFER-CANCEL');$cancelOrder=$this->checkout($cancelDb,10,101,601);
        \shopOrderAdminCancel($cancelDb,(int)$cancelOrder['id'],7,'Storno neuhrazené rezervace.',true);
        $cancelled=\clubProgramOfferForVariant($cancelDb,601);self::assertSame(0,$cancelled['held_order_count']);self::assertSame(1,$cancelled['available_count']);

        $paidDb=$this->database();$this->offer($paidDb,601,501,10,1,'OFFER-PAID');$paidOrder=$this->checkout($paidDb,10,101,601);
        \shopOrderAdminConfirmBankPayment($paidDb,(int)$paidOrder['payment_id'],7,'Potvrzená platba.',true);
        $paid=\clubProgramOfferForVariant($paidDb,601);
        self::assertSame(1,$paid['active_enrollment_count']);self::assertSame(0,$paid['held_order_count']);self::assertSame(1,$paid['occupied_count']);self::assertSame(0,$paid['available_count']);
        $admin=\clubProgramAdminOffers($paidDb);self::assertSame(1,$admin[0]['active_enrollment_count']);self::assertSame(0,$admin[0]['held_order_count']);
    }

    public function testLateKnownAthletePaymentCannotTakePaidIdentityReviewReservation():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,1,'OFFER-PAID-REVIEW');
        $late=$this->checkout($pdo,10,101,601);
        $pdo->prepare("UPDATE shop_orders SET payment_expires_at='2000-01-01' WHERE id=?")->execute([(int)$late['id']]);
        $review=$this->checkout($pdo,11,103,601);
        $pdo->exec("INSERT INTO account_person_claim_requests(id,account_id,requested_role,claimed_jmeno,claimed_prijmeni,claimed_narozeni,requester_message,status,request_kind) VALUES(99,11,'guardian','LOCALHOST','NEPLATIT','2012-05-06','','pending','athlete_registration')");
        $pdo->prepare('UPDATE shop_order_items SET beneficiary_sportovec_id=NULL,athlete_registration_request_id=99 WHERE order_id=?')->execute([(int)$review['id']]);
        $confirmed=\shopOrderAdminConfirmBankPayment($pdo,(int)$review['payment_id'],7,'Syntetická platba čekající na ověření osoby.',true);
        self::assertSame(1,$confirmed['pending_identity']);

        try{
            \shopOrderAdminConfirmBankPayment($pdo,(int)$late['payment_id'],7,'Syntetická opožděná platba.',true);
            self::fail('A known athlete must not take the paid place of a family awaiting identity review.');
        }catch(\ShopCheckoutException $exception){
            self::assertStringContainsString('Kapacita',$exception->getMessage());
        }
        $payment=$pdo->prepare('SELECT status FROM payments WHERE id=?');$payment->execute([(int)$late['payment_id']]);self::assertSame('pending',$payment->fetchColumn());
        $payment->execute([(int)$review['payment_id']]);self::assertSame('paid',$payment->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_enrollments')->fetchColumn());
        self::assertSame(1,\clubProgramOfferForVariant($pdo,601)['held_order_count']);
    }

    public function testAgeMigrationIsRepeatableAndValidatesRange():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260817130000_club_program_offer_age.php';$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        try{$this->offer($pdo,601,501,10,5,'OFFER-BAD',null,'2026-09-01','2027-01-31',2020,2019);self::fail('Reversed range must fail.');}
        catch(\InvalidArgumentException $exception){self::assertStringContainsString('Počáteční ročník',$exception->getMessage());}
    }

    public function testAcceptedProgramTermsStayImmutableAfterNewVersion():void
    {
        $pdo=$this->database();$this->offer($pdo,601,501,10,2,'OFFER-TERMS');
        \shopCartSetQuantity($pdo,10,601,1,101);$cart=\shopCartDetail($pdo,10);
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint']);self::fail('Explicit acceptance is required.');}
        catch(\ShopCheckoutException $exception){self::assertStringContainsString('potvrďte storno podmínky',$exception->getMessage());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],[601=>1]);
        $snapshot=(string)$pdo->query('SELECT program_terms_snapshot_json FROM shop_order_items')->fetchColumn();
        self::assertTrue(\clubProgramTermsSnapshotValid($snapshot));self::assertStringContainsString('Syntetický schválený souhlas', $snapshot);
        $programId=(int)$pdo->query('SELECT id FROM club_programs')->fetchColumn();
        $new=\clubProgramTermsConfigure($pdo,7,'program',$programId,'program_consent','Nové znění souhlasu pro další objednávky.',true);
        self::assertSame('v2',$new['terms_version']);self::assertSame('archived',$pdo->query("SELECT status FROM club_event_term_versions WHERE consent_purpose='program_consent' AND terms_version='v1'")->fetchColumn());
        self::assertSame($snapshot,(string)$pdo->query('SELECT program_terms_snapshot_json FROM shop_order_items')->fetchColumn());
        \shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'Platba se snapshotem.',true);
        self::assertSame($snapshot,(string)$pdo->query('SELECT terms_snapshot_json FROM club_program_enrollments')->fetchColumn());
        $offerId=(int)$pdo->query('SELECT id FROM club_program_offers')->fetchColumn();
        \clubProgramTermsConfigure($pdo,7,'offer',$offerId,'program_cancellation','Speciální storno pouze pro toto období.',true);
        $effective=\clubProgramTermsEffective($pdo,$programId,$offerId);
        self::assertSame('Speciální storno pouze pro toto období.',$effective['program_cancellation']['consent_text_plain']);
        self::assertSame('Nové znění souhlasu pro další objednávky.',$effective['program_consent']['consent_text_plain']);
    }

    private function checkout(PDO $pdo,int $accountId,int $sportovecId,int $variantId):array
    {
        \shopCartSetQuantity($pdo,$accountId,$variantId,1,$sportovecId);$cart=\shopCartDetail($pdo,$accountId);return \shopCheckoutPlace($pdo,$accountId,bin2hex(random_bytes(16)),self::BANK,$cart['fingerprint'],[$variantId=>1]);
    }

    private function offer(PDO $pdo,int $variantId,int $productId,int $teamId,int $capacity,string $code,?int $programId=null,string $from='2026-09-01',string $to='2027-01-31',?int $birthYearFrom=null,?int $birthYearTo=null):int
    {
        $programId??=(int)\clubProgramCreate($pdo,7,'BIKE-SCHOOL','Cyklistická škola')['id'];
        \clubProgramTermsConfigure($pdo,7,'program',$programId,'program_cancellation','Syntetické schválené storno podmínky pro integrační test.',true);
        \clubProgramTermsConfigure($pdo,7,'program',$programId,'program_consent','Syntetický schválený souhlas s účastí pro integrační test.',true);
        return(int)\clubProgramCreateOffer($pdo,7,$programId,1,$teamId,$productId,$variantId,$code,'Období '.$code,$from,$to,null,null,$capacity,'active',$birthYearFrom,$birthYearTo)['id'];
    }

    private function database(bool $family=true):PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,email TEXT,heslo_hash TEXT DEFAULT \'\',telefon TEXT NULL,verifikacni_token TEXT NULL,verifikacni_token_expires_at TEXT NULL,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno) VALUES(10,'Rodič','A','a@test.test',1,1),(11,'Rodič','B','b@test.test',1,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT NULL)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna','2017-04-03'),(103,'Dítě','Cizí','2012-05-06'),(104,'Bez','Data',NULL)");
        $roles=require dirname(__DIR__,2).'/migrations/20260802230000_account_person_roles.php';$roles['up']($pdo);$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(10,101,'guardian','approved','admin','2020-01-01',7,7,'test'),(11,103,'guardian','approved','admin','2020-01-01',7,7,'test'),(10,104,'guardian','approved','admin','2020-01-01',7,7,'test')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(501,'Kroužek A','program','active'),(502,'Kroužek B','program','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_variants VALUES(601,501,'A','{}','fixed',10000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(602,502,'B','{}','fixed',12000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');$pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Kroužek A','A'),(502,'active','Kroužek B','B')");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT NOT NULL,scope_key TEXT NOT NULL,consent_purpose TEXT NOT NULL,event_id INTEGER NULL,terms_version TEXT NOT NULL,consent_text_plain TEXT NOT NULL,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))');
        foreach(['20260802233000_account_person_claim_requests.php','20260816143000_athlete_registration_foundation.php','20260816180000_registration_terms_scope.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $pdo->exec("UPDATE club_event_term_versions SET consent_text_plain='Syntetické schválené informace pro integrační test.' WHERE scope_type='athlete_registration' AND consent_purpose IN ('member_data_notice','birth_number_legal_notice')");
        foreach(['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804120000_shop_item_beneficiaries.php','20260804090000_kis_teams_rosters.php','20260809090000_stripe_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'SCHOOL','Školní rok','2026-09-01','2027-08-31','active',7)");$pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(10,1,'A','Kroužek A','vše','děti','active',7)");
        foreach(['20260804140000_club_programs.php','20260804160000_club_program_lifecycle.php','20260804235000_club_program_repeat_enrollment.php','20260817090000_club_program_events.php','20260817130000_club_program_offer_age.php','20260817150000_club_program_terms.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $expiration=require dirname(__DIR__,2).'/migrations/20260804210000_shop_order_expiration.php';$expiration['up']($pdo);self::assertTrue($expiration['verify']($pdo));
        foreach(['20260922140000_low_friction_storefront.php','20260922150000_quick_program_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        \Tests\Support\ParentJourneyNotificationFixture::install($pdo);
        if($family){$m=require dirname(__DIR__,2).'/migrations/20261023210000_family_course_cart.php';$m['up']($pdo);self::assertTrue($m['verify']($pdo));}
        return$pdo;
    }
}
