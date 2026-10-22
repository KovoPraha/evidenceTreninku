<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once __DIR__.'/ShopCheckoutTest.php';
require_once dirname(__DIR__,2).'/includes/shop_assisted_sale.php';
final class ShopFulfillmentTest extends TestCase
{
 private const BANK=['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'LOCALHOST TEST','due_days'=>7];
 private function db(bool $open=true):PDO {
  $fixture=new ShopCheckoutTest('testGuestGoodsCheckoutUsesCanonicalOrderPaymentStockAndSecureToken');$pdo=(new \ReflectionMethod($fixture,'database'))->invoke($fixture);
  $pdo->exec("ALTER TABLE shop_products ADD COLUMN name TEXT DEFAULT 'TEST'; ALTER TABLE treneri ADD COLUMN aktivni INTEGER DEFAULT 1; ALTER TABLE shop_variants ADD COLUMN unit_code TEXT DEFAULT 'piece'; CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT); CREATE TABLE staff_superadmins(trainer_id INTEGER); INSERT INTO staff_user_positions VALUES(7,'catalog_manager'),(7,'finance_manager'),(7,'order_operator')");
  foreach(['20261022230000_shop_inventory','20261022231000_shop_fulfillment'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($pdo);$m['up']($pdo);self::assertTrue($m['verify']($pdo));}
  if($open){\shopInventoryConfigure($pdo,7,'TEST sklad','fifo','TEST schválení účetní','TEST schválená metodika',true);\shopInventoryOpen($pdo,7,601,['physical_quantity'=>5,'blocked_quantity'=>0,'unit_cost_minor'=>7000,'evidence'=>'TEST přepočtené kusy','effective_on'=>date('Y-m-d')],'TEST ověřený počátek',true,'opening:test');}
  return $pdo;
 }
 private function order(PDO $pdo,int $qty=2):array{return \shopGuestCheckoutPlace($pdo,601,$qty,['first_name'=>'TEST','last_name'=>'Kupující','email'=>'test@example.test','confirmed'=>'1'],bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK);}
 private function paid(PDO $pdo,array $order):void {\shopOrderAdminConfirmBankPayment($pdo,(int)$order['payment_id'],7,'TEST potvrzeno v bance',true);}
 public function testGuestAndAccountReservationsDoNotMovePhysicalStockAndCancelReleasesOnce():void {
  $pdo=$this->db();$order=$this->order($pdo);$b=\shopInventoryBalance($pdo,601);self::assertSame([5,2,3],[$b['physical_quantity'],$b['reserved_quantity'],$b['available_quantity']]);self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
  \shopCartSetQuantity($pdo,10,601,1);$account=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);self::assertSame(3,\shopInventoryBalance($pdo,601)['reserved_quantity']);
  \shopOrderAdminCancel($pdo,(int)$order['id'],7,'TEST storno před vydáním',true);\shopOrderAdminCancel($pdo,(int)$order['id'],7,'TEST opakované storno',true);self::assertSame(1,\shopInventoryBalance($pdo,601)['reserved_quantity']);self::assertSame(5,\shopInventoryBalance($pdo,601)['physical_quantity']);
 }
 public function testPartialIssueChangesPhysicalAndReservedTogetherAndReplayDoesNotDoubleIssue():void {
  $pdo=$this->db();$o=$this->order($pdo);$this->paid($pdo,$o);$item=(int)$o['items'][0]['id'];$date=date('Y-m-d H:i:s');
  $r=\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST přebírající',$date,'TEST osobní předání','test:issue1');self::assertTrue($r['changed']);self::assertSame('processing',$r['status']);$b=\shopInventoryBalance($pdo,601);self::assertSame([4,1,3],[$b['physical_quantity'],$b['reserved_quantity'],$b['available_quantity']]);
  self::assertFalse(\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST přebírající',$date,'TEST osobní předání','test:issue1')['changed']);
  $r=\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST přebírající',$date,'TEST druhé předání','test:issue2');self::assertSame('completed',$r['status']);self::assertSame(3,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertSame(14000,(int)$pdo->query('SELECT SUM(cost_minor) FROM shop_fulfillment_items')->fetchColumn());
 }
 public function testUnpaidOverIssueForeignItemAndForgedReplayRollback():void {
  $pdo=$this->db();$o=$this->order($pdo);$id=(int)$o['items'][0]['id'];$date=date('Y-m-d H:i:s');
  try{\shopFulfillmentIssue($pdo,(int)$o['id'],[$id=>1],7,'TEST',$date,'TEST důvod','test:unpaid');self::fail('unpaid');}catch(\ShopCheckoutException){}
  $this->paid($pdo,$o);foreach([[$id=>3],[99999=>1]] as $qty){try{\shopFulfillmentIssue($pdo,(int)$o['id'],$qty,7,'TEST',$date,'TEST důvod','test:invalid');self::fail('bad quantity or foreign item');}catch(\ShopCheckoutException|\ShopInventoryException){}}
  self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_fulfillments')->fetchColumn());self::assertSame(5,\shopInventoryBalance($pdo,601)['physical_quantity']);
  \shopFulfillmentIssue($pdo,(int)$o['id'],[$id=>1],7,'TEST',$date,'TEST důvod','test:unique');try{\shopFulfillmentIssue($pdo,(int)$o['id'],[$id=>2],7,'TEST',$date,'TEST důvod','test:unique');self::fail('key collision');}catch(\ShopCheckoutException){}self::assertSame(4,\shopInventoryBalance($pdo,601)['physical_quantity']);
 }
 public function testOldCompleteAndCancelCannotBypassPhysicalIssue():void {
  $pdo=$this->db();$o=$this->order($pdo);$this->paid($pdo,$o);\shopOrderAdminMarkReady($pdo,(int)$o['id'],7,'TEST připraveno',true);
  try{\shopOrderAdminCompletePickup($pdo,(int)$o['id'],7,'TEST starý výdej',true);self::fail('shortcut');}catch(\ShopCheckoutException){}
  \shopFulfillmentIssue($pdo,(int)$o['id'],[(int)$o['items'][0]['id']=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST předání','test:partial');
  try{\shopOrderAdminCancel($pdo,(int)$o['id'],7,'TEST storno vydaného',true);self::fail('issued cancellation');}catch(\ShopCheckoutException){}self::assertSame(4,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertSame(1,\shopInventoryBalance($pdo,601)['reserved_quantity']);
 }
 public function testAssistedPublicSaleCreatesNoFakeAccountNoMoneyAndReplaysSafely():void {
  $pdo=$this->db();$key=bin2hex(random_bytes(16));$token=bin2hex(random_bytes(32));$customer=['first_name'=>'TEST','last_name'=>'Veřejnost','email'=>'public@example.test','confirmed'=>'1','expected_unit_minor'=>12500];
  $sale=\shopAssistedSalePlace($pdo,601,2,$customer,7,'TEST osobní prodej',$key,$token,self::BANK,null,null,'TEST plátce','TEST přebírající');self::assertTrue($sale['sale_saved']);self::assertFalse($sale['money_confirmed']);self::assertFalse($sale['fulfillment_recorded']);self::assertNull($sale['account_id']);self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM verejni_uzivatele')->fetchColumn());
  $again=\shopAssistedSalePlace($pdo,601,2,$customer,7,'TEST osobní prodej',$key,$token,self::BANK,null,null,'TEST plátce','TEST přebírající');self::assertTrue($again['replayed']);self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_assisted_sales')->fetchColumn());
  try{\shopAssistedSalePlace($pdo,601,1,$customer,7,'TEST osobní prodej',$key,$token,self::BANK,null,null,'TEST plátce','TEST přebírající');self::fail('different content');}catch(\ShopCheckoutException){}self::assertSame(2,\shopInventoryBalance($pdo,601)['reserved_quantity']);
 }
 public function testAssistedMemberPriceUsesCurrentRosterAndRejectsStalePrice():void {
  $pdo=$this->db();$pdo->exec("INSERT INTO account_person_roles VALUES(1,10,90,'guardian','approved','2020-01-01',NULL); INSERT INTO club_roster_members VALUES(1,20,90,'active','2020-01-01',NULL)");\shopMemberPricingSetProductPrice($pdo,20,501,10000,'CZK',7,'TEST členská cena');
  $customer=['first_name'=>'TEST','last_name'=>'Člen','email'=>'member@example.test','confirmed'=>'1','expected_unit_minor'=>10000];$sale=\shopAssistedSalePlace($pdo,601,1,$customer,7,'TEST člen prodej',bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK,10,null,'TEST','TEST');self::assertSame(10000,(int)$sale['total_minor']);$snapshot=json_decode($pdo->query('SELECT price_snapshot_json FROM shop_assisted_sales')->fetchColumn(),true);self::assertTrue($snapshot['is_member_price']);
  $pdo->exec("UPDATE account_person_roles SET status='revoked'");try{\shopAssistedSalePlace($pdo,601,1,$customer,7,'TEST člen prodej',bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK,10,null,'TEST','TEST');self::fail('stale price');}catch(\ShopCheckoutException){}self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
 }
 public function testUnknownStockNeverBecomesUnlimitedAndOperatorGrantIsCurrent():void {
  $pdo=$this->db(false);$pdo->exec('UPDATE shop_variants SET stock_quantity_decimal=NULL WHERE id=601');try{$this->order($pdo);self::fail('unknown');}catch(\ShopCheckoutException){}self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
  $pdo=$this->db();$o=$this->order($pdo);$this->paid($pdo,$o);$pdo->exec("DELETE FROM staff_user_positions WHERE position_code='order_operator'");try{\shopFulfillmentIssue($pdo,(int)$o['id'],[(int)$o['items'][0]['id']=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST předání','test:revoked');self::fail('revoked operator');}catch(\ShopInventoryException){}self::assertSame(5,\shopInventoryBalance($pdo,601)['physical_quantity']);
 }
 public function testCarrierHandoffIssuesOnceAndLostEventNeverRestocks():void {
  $pdo=$this->db();$m=require dirname(__DIR__,2).'/migrations/20261019110000_shop_ppl_box.php';$m['up']($pdo);$o=$this->order($pdo);$this->paid($pdo,$o);$pdo->prepare("UPDATE shop_orders SET fulfillment_method='ppl_box',status='ready' WHERE id=?")->execute([$o['id']]);
  $sent=\shopOrderAdminDispatchPpl($pdo,(int)$o['id'],7,'TEST123456','TEST předání dopravci',true);self::assertTrue($sent['changed']);self::assertSame(3,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertFalse(\shopOrderAdminDispatchPpl($pdo,(int)$o['id'],7,'TEST123456','TEST opakované předání',true)['changed']);
  self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_delivery_events')->fetchColumn());\shopFulfillmentDeliveryRecord($pdo,(int)$o['id'],'lost',7,'TEST PPL',date('Y-m-d H:i:s'),'TEST pátrání dopravce','test:lost');self::assertSame(3,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertSame('paid',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
 }
 public function testPersonalSaleWithoutEmailUsesPaperContactAndDoesNotBlockCashPayment():void {
  $pdo=$this->db();$sale=\shopAssistedSalePlace($pdo,601,1,['first_name'=>'TEST','last_name'=>'Bez emailu','email'=>'','confirmed'=>'1'],7,'TEST osobní prodej',bin2hex(random_bytes(16)),bin2hex(random_bytes(32)),self::BANK,null,null,'TEST plátce','TEST příjemce');
  self::assertSame('', $sale['customer_email_snapshot']);self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());\shopOrderAdminConfirmManualPayment($pdo,(int)$sale['payment_id'],7,'cash','TEST hotovost přijata',true);\shopOrderAdminMarkReady($pdo,(int)$sale['id'],7,'TEST připraveno',true);self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
 }

 public function testQuantityCorrectionLimitsUnissuedGoodsWhilePriceConcessionKeepsPhysicalQuantity():void {
  $pdo=$this->db();$o=$this->order($pdo);$this->paid($pdo,$o);$item=(int)$o['items'][0]['id'];$pdo->exec('CREATE TABLE accounting_documents(id INTEGER PRIMARY KEY,kind TEXT,source_type TEXT,source_id INTEGER,status TEXT,snapshot_json TEXT)');
  $q=$pdo->prepare("INSERT INTO accounting_documents VALUES(1,'correction','order',?,'issued',?)");$q->execute([$o['id'],json_encode(['lines'=>[['line_key'=>'item:'.$item,'quantity'=>0,'amount_minor'=>-1000]]])]);
  $one=\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST cena sleva','test:priceonly');self::assertTrue($one['changed']);
  $pdo->prepare("UPDATE accounting_documents SET snapshot_json=? WHERE id=1")->execute([json_encode(['lines'=>[['line_key'=>'item:'.$item,'quantity'=>1,'amount_minor'=>-12500]]])]);
  try{\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST snížení kusů','test:corrected');self::fail('corrected quantity cannot issue');}catch(\ShopCheckoutException){}self::assertSame(4,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertSame(1,\shopInventoryBalance($pdo,601)['reserved_quantity']);
  $release=\shopFulfillmentReleaseRemainder($pdo,(int)$o['id'],$item,1,7,'TEST zrušený nevydaný kus','test:remainder');self::assertTrue($release['changed']);self::assertSame(0,\shopInventoryBalance($pdo,601)['reserved_quantity']);self::assertSame(4,\shopInventoryBalance($pdo,601)['physical_quantity']);self::assertSame('paid',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());self::assertSame(2,(int)$pdo->query('SELECT quantity FROM shop_order_items')->fetchColumn());self::assertFalse(\shopFulfillmentReleaseRemainder($pdo,(int)$o['id'],$item,1,7,'TEST zrušený nevydaný kus','test:remainder')['changed']);
 }
 public function testFullRefundDecisionStopsIssueUntilDecisionWithdrawn():void {
  $pdo=$this->db();$o=$this->order($pdo);$this->paid($pdo,$o);$item=(int)$o['items'][0]['id'];
  $pdo->exec('CREATE TABLE accounting_refund_decisions(id INTEGER PRIMARY KEY,original_movement_id INTEGER,amount_minor INTEGER); CREATE TABLE accounting_movements(id INTEGER PRIMARY KEY,case_type TEXT,case_id INTEGER); CREATE TABLE accounting_adjustment_events(decision_id INTEGER,event_type TEXT); CREATE TABLE accounting_movement_sources(movement_id INTEGER,source_type TEXT,source_id INTEGER)');
  $pdo->prepare("INSERT INTO accounting_movements VALUES(1,'shop_order',?)")->execute([$o['id']]);$pdo->exec('INSERT INTO accounting_refund_decisions VALUES(1,1,25000)');
  try{\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST plná vratka','test:fullrefund');self::fail('full refund cannot issue');}catch(\ShopCheckoutException){}self::assertSame(5,\shopInventoryBalance($pdo,601)['physical_quantity']);
  $pdo->exec("INSERT INTO accounting_adjustment_events VALUES(1,'decision_cancelled')");self::assertTrue(\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>1],7,'TEST',date('Y-m-d H:i:s'),'TEST zrušení vratky','test:refundwithdrawn')['changed']);
 }

 public function testPartialAllocationBlocksExpiryAndPendingCancellation():void {
  $pdo=$this->db();$o=$this->order($pdo);$pdo->exec('CREATE TABLE club_finance_allocations(id INTEGER PRIMARY KEY,order_id INTEGER,amount_minor INTEGER)');$pdo->prepare('INSERT INTO club_finance_allocations VALUES(1,?,1000)')->execute([$o['id']]);$pdo->exec("UPDATE shop_orders SET payment_expires_at='2020-01-01 00:00:00'");
  try{\shopOrderExpirePending($pdo,(int)$o['id'],new \DateTimeImmutable('now'),true);self::fail('partial paid expiration');}catch(\ShopCheckoutException $e){self::assertStringContainsString('částečnou úhradu',$e->getMessage());}
  try{\shopOrderAdminCancel($pdo,(int)$o['id'],7,'TEST částečná úhrada',true);self::fail('partial paid cancellation');}catch(\ShopCheckoutException){}self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame(2,\shopInventoryBalance($pdo,601)['reserved_quantity']);self::assertSame(5,\shopInventoryBalance($pdo,601)['physical_quantity']);
 }

 public function testRemainderUsesAllIssuedCorrectionsAfterEarlierPhysicalReturn():void {
  $pdo=$this->db();$o=$this->order($pdo,5);$this->paid($pdo,$o);$item=(int)$o['items'][0]['id'];\shopFulfillmentIssue($pdo,(int)$o['id'],[$item=>2],7,'TEST',date('Y-m-d H:i:s'),'TEST první dva kusy','test:priorissue');
  $movement=(int)$pdo->query('SELECT inventory_movement_id FROM shop_fulfillment_items')->fetchColumn();$pdo->beginTransaction();\shopInventoryReturnInTransaction($pdo,$movement,1,'test:priorreturn',7,'TEST jeden vrácený kus');$pdo->commit();
  $pdo->exec('CREATE TABLE accounting_documents(id INTEGER PRIMARY KEY,kind TEXT,source_type TEXT,source_id INTEGER,status TEXT,snapshot_json TEXT)');$q=$pdo->prepare("INSERT INTO accounting_documents VALUES(?,'correction','order',?,'issued',?)");foreach([1=>1,2=>3]as$id=>$qty)$q->execute([$id,$o['id'],json_encode(['lines'=>[['line_key'=>'item:'.$item,'quantity'=>$qty,'amount_minor'=>-$qty*12500]]])]);
  self::assertTrue(\shopFulfillmentReleaseRemainder($pdo,(int)$o['id'],$item,2,7,'TEST další nevydané kusy','test:laterremainder')['changed']);$b=\shopInventoryBalance($pdo,601);self::assertSame([4,0,1,3],[$b['physical_quantity'],$b['reserved_quantity'],$b['blocked_quantity'],$b['available_quantity']]);self::assertSame(3,(int)\shopInventoryReservation($pdo,$item)['released_quantity']);$resolution=\shopFulfillmentResolution($pdo,(int)$o['id']);self::assertTrue($resolution['settled']);self::assertStringContainsString('3 kusů nevydáno',\shopFulfillmentResolutionLabel($resolution));self::assertSame('processing',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());
 }

}
