<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/accounting_stock_export.php';
require_once dirname(__DIR__,2).'/includes/shop_stock_documents.php';
final class AccountingStockExportTest extends TestCase
{
 private function db():PDO{
  $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT);CREATE TABLE staff_user_positions(trainer_id INT,position_code TEXT);CREATE TABLE staff_superadmins(trainer_id INT);INSERT INTO treneri VALUES(1,1),(2,1);INSERT INTO staff_user_positions VALUES(1,'catalog_manager');CREATE TABLE shop_products(id INT PRIMARY KEY,name TEXT,offer_type TEXT);INSERT INTO shop_products VALUES(1,'Dres Žluťoučký','goods');CREATE TABLE shop_variants(id INT PRIMARY KEY,product_id INT,sku TEXT,unit_code TEXT);INSERT INTO shop_variants VALUES(1,1,'0000123','piece'),(2,1,'=1+1','piece');CREATE TABLE shop_orders(id INT PRIMARY KEY);CREATE TABLE shop_order_items(id INT PRIMARY KEY);");
  foreach(['20261022230000_shop_inventory','20261022233000_shop_stock_documents'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);}
  $p->exec("INSERT INTO shop_stock_balances(variant_id,mode,physical_quantity,reserved_quantity,blocked_quantity,actor_trainer_id) VALUES(1,'tracked',999,999,999,1)");
  $move=$p->prepare('INSERT INTO shop_stock_movements(id,variant_id,kind,physical_delta,reserved_delta,blocked_delta,physical_after,reserved_after,blocked_after,cost_minor,request_key,fingerprint,actor_trainer_id,reason,meta_json,effective_on) VALUES(?,1,?,?,?,?,?,?,?,NULL,?,?,1,?, ?,?)');
  $move->execute([1,'opening',10,0,0,10,0,0,'open','a','LOCALHOST verified opening','{"SKU":"0000123","name":"Dres Žluťoučký"}','2025-09-30']);
  $move->execute([2,'reserve',0,3,0,10,3,0,'reserve','b','LOCALHOST reservation','{}','2025-10-01']);
  $move->execute([3,'issue',-2,-2,0,8,1,0,'issue','c','LOCALHOST actual handoff','{}','2025-10-02']);
  $move->execute([4,'return',1,0,1,9,1,1,'return','d','LOCALHOST physical return','{}','2025-10-04']);
  $p->exec("INSERT INTO shop_stock_lots(id,variant_id,source_movement_id,original_quantity,remaining_quantity,blocked_quantity,unit_cost_minor,effective_on) VALUES(1,1,1,10,8,0,100,'2025-09-30'),(2,1,4,1,1,1,NULL,'2025-10-04');INSERT INTO shop_stock_lot_uses(movement_id,lot_id,quantity,unit_cost_minor) VALUES(3,1,2,NULL);INSERT INTO shop_stock_cost_revisions(lot_id,previous_unit_cost_minor,unit_cost_minor,remaining_quantity,issued_quantity,evidence,reason,actor_trainer_id,request_key,fingerprint,effective_on) VALUES(1,NULL,100,8,2,'LOCALHOST late invoice','LOCALHOST documented price',1,'price','p','2025-10-03')");return $p;
 }
 public function testHistoricalProjectionIgnoresCurrentCacheAndFutureEvents():void{
  $p=$this->db();$early=\accountingStockSnapshot($p,'2025-10-01','2025-10-02');$b=$early['stock_balances'][0];self::assertSame(10,$b['opening']['physical']);self::assertSame(8,$b['closing']['physical']);self::assertSame(1,$b['closing']['reserved']);self::assertSame(7,$b['closing']['available']);self::assertNull($b['closing']['value_minor']);self::assertSame(8,$b['closing']['unknown_cost_quantity']);self::assertCount(3,$early['stock_movements']);
  $priced=\accountingStockSnapshot($p,'2025-10-01','2025-10-03');self::assertSame(800,$priced['stock_balances'][0]['closing']['value_minor']);self::assertNull($priced['stock_balances'][0]['opening']['value_minor']);self::assertCount(1,$priced['stock_cost_revisions']);
  $returned=\accountingStockSnapshot($p,'2025-10-01','2025-10-31');self::assertSame(9,$returned['stock_balances'][0]['closing']['physical']);self::assertSame(1,$returned['stock_balances'][0]['closing']['blocked']);self::assertSame(7,$returned['stock_balances'][0]['closing']['available']);self::assertNull($returned['stock_balances'][0]['closing']['value_minor']);self::assertNull($returned['stock_balances'][1]['closing']['physical']);
  $before=\accountingStockSnapshot($p,'2025-09-01','2025-09-30');self::assertNull($before['stock_balances'][0]['opening']['physical']);self::assertSame(10,$before['stock_balances'][0]['closing']['physical']);
 }
 public function testStockPdfIsPrivateImmutableAndRejectsTamperingAndRevokedRole():void{
  $p=$this->db();$old=getenv('APP_PRIVATE_STORAGE_ROOT');$root=sys_get_temp_dir().'/stock-doc-test-'.bin2hex(random_bytes(8));mkdir($root);putenv('APP_PRIVATE_STORAGE_ROOT='.$root);
  try{$r=\shopStockDocumentCreate($p,1,1,'LOCALHOST archive original movement.',true);$path=\shopStockDocumentDownload($p,1,1);$bytes=file_get_contents($path);self::assertStringStartsWith('%PDF-',$bytes);self::assertSame(hash('sha256',$bytes),$r['pdf_sha256']);
   $p->exec("UPDATE shop_products SET name='Changed';UPDATE shop_stock_lots SET unit_cost_minor=999");$again=\shopStockDocumentCreate($p,1,1,'LOCALHOST repeated archive request.',true);self::assertSame($r,$again);self::assertSame($bytes,file_get_contents(\shopStockDocumentDownload($p,1,1)));
   $p->exec("DELETE FROM staff_user_positions WHERE trainer_id=1");try{\shopStockDocumentDownload($p,1,1);self::fail('Revoked role');}catch(\DomainException){self::assertTrue(true);}$p->exec("INSERT INTO staff_user_positions VALUES(1,'catalog_manager')");file_put_contents($path,'tampered');$this->expectException(\DomainException::class);\shopStockDocumentDownload($p,1,1);
  }finally{putenv($old===false?'APP_PRIVATE_STORAGE_ROOT':'APP_PRIVATE_STORAGE_ROOT='.$old);foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);}
 }
}
