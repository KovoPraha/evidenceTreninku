<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/accounting_export.php';
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';

/** Inject a real independently committed write between the money and allocation reads. */
final class StockSnapshotHookPdo extends PDO
{
    public ?Closure $betweenReads=null;
    public bool $fired=false;
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        if($this->betweenReads!==null && str_contains($query,'SELECT * FROM shop_stock_lots WHERE effective_on<=?')){
            $callback=$this->betweenReads;$this->betweenReads=null;$this->fired=true;$callback();
        }
        return parent::prepare($query,$options);
    }
}
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||(int)$descriptor['db_port']!==33426)throw new RuntimeException('Owned synthetic sandbox required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$admin->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Database identity mismatch.');
$database='evidence_b4_stock_rr_'.bin2hex(random_bytes(4));
$admin->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4');$admin->exec("GRANT ALL ON ".$database.".* TO 'b1_app'@'127.0.0.1'");
$dsn='mysql:host=127.0.0.1;port=33426;dbname='.$database.';charset=utf8mb4';
$reader=new StockSnapshotHookPdo($dsn,'b1_app','',$options);$writer=new PDO($dsn,'b1_app','',$options);
$reader->exec('SET SESSION innodb_lock_wait_timeout=8');$writer->exec('SET SESSION innodb_lock_wait_timeout=8');
$reader->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');
$reader->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40),PRIMARY KEY(trainer_id,position_code)) ENGINE=InnoDB');
$reader->exec('CREATE TABLE staff_superadmins(trainer_id INT PRIMARY KEY) ENGINE=InnoDB');
$reader->exec("INSERT INTO treneri VALUES(7,1),(8,1);INSERT INTO staff_user_positions VALUES(7,'finance_manager'),(8,'finance_manager')");
$reader->exec("CREATE TABLE shop_orders(id BIGINT UNSIGNED PRIMARY KEY,currency CHAR(3),status VARCHAR(30),payment_status VARCHAR(30)) ENGINE=InnoDB;INSERT INTO shop_orders VALUES(4,'CZK','completed','paid'),(5,'CZK','completed','paid')");
$reader->exec("ALTER TABLE shop_orders ADD public_code VARCHAR(40) NOT NULL DEFAULT 'LOCALHOST',ADD customer_name_snapshot VARCHAR(100) NOT NULL DEFAULT 'LOCALHOST',ADD total_minor BIGINT NOT NULL DEFAULT 0,ADD placed_at DATETIME NOT NULL DEFAULT '2026-01-01 00:00:00'");
$checks=[];
function asrCheck(bool $condition,string $label):void {if(!$condition)throw new RuntimeException($label);$GLOBALS['checks'][]=$label;}
$reader->exec("INSERT INTO staff_user_positions VALUES(8,'catalog_manager');CREATE TABLE shop_products(id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(100),offer_type VARCHAR(30));INSERT INTO shop_products VALUES(1,'LOCALHOST stock','goods');CREATE TABLE shop_variants(id BIGINT UNSIGNED PRIMARY KEY,product_id BIGINT UNSIGNED,sku VARCHAR(100),unit_code VARCHAR(20),stock_quantity_decimal VARCHAR(30));INSERT INTO shop_variants VALUES(1,1,'LOCALHOST-STOCK','piece',NULL);CREATE TABLE shop_order_items(id BIGINT UNSIGNED PRIMARY KEY,order_id BIGINT UNSIGNED,variant_id BIGINT UNSIGNED,quantity INT)");
foreach(['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments','20261022223000_accounting_exports','20261022230000_shop_inventory','20261022233000_shop_stock_documents'] as $id){$migration=require dirname(__DIR__,2).'/migrations/'.$id.'.php';$migration['up']($reader);asrCheck($migration['verify']($reader),'Migration verified: '.$id);}
$root=$runtime.'/stock-snapshot-proof-'.bin2hex(random_bytes(8));mkdir($root,0700);$oldRoot=getenv('APP_PRIVATE_STORAGE_ROOT');putenv('APP_PRIVATE_STORAGE_ROOT='.$root);$reason='LOCALHOST NEPLATIT stock snapshot proof';
shopInventoryConfigure($writer,8,'LOCALHOST warehouse','fifo',$reason,$reason,true);shopInventoryOpen($writer,8,1,['physical_quantity'=>2,'unit_cost_minor'=>100,'evidence'=>$reason,'effective_on'=>'2026-01-01'],$reason,true,'stock-open');
$receipt=static function(string $key)use($writer,$reason):void{shopInventoryReceive($writer,8,1,['quantity'=>1,'unit_cost_minor'=>200,'supplier'=>'LOCALHOST','supplier_reference'=>$key,'cost_evidence'=>$reason,'effective_on'=>'2026-01-02'],$reason,true,$key);};
try{
 $reader->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');$reader->betweenReads=static function()use($receipt):void{$receipt('stock-concurrent');};
 $batch=accountingExportCreate($reader,'2026-01-01','2026-01-31','stock-snapshot-first',7,$reason,true);$snapshot=json_decode($batch['snapshot_json'],true,512,JSON_THROW_ON_ERROR);asrCheck($reader->fired,'Writer committed receipt between movement and lot SELECTs');$b=$snapshot['stock_balances'][0]['closing'];asrCheck($b['physical']===2&&$b['value_minor']===200&&!$b['invalid'],'Snapshot contains matched original stock movements and FIFO lots');asrCheck(count($snapshot['stock_lots'])===1&&count($snapshot['stock_movements'])===1,'Late receipt absent from all stock snapshot sections');asrCheck((int)$writer->query('SELECT physical_quantity FROM shop_stock_balances')->fetchColumn()===3,'Independent connection proves late receipt committed');
 $reader->fired=false;$reader->betweenReads=static function()use($receipt):void{$receipt('stock-control');};$reader->beginTransaction();$control=accountingExportSnapshot($reader,'2026-01-01','2026-01-31');$reader->commit();asrCheck($reader->fired&&$control['stock_balances'][0]['closing']['invalid'],'READ COMMITTED negative control detects mixed movements and lots');
 $report=['status'=>'PASS','checks'=>count($checks),'results'=>$checks,'database'=>$database,'two_connections'=>true,'negative_control_mixed_stock_detected'=>true];file_put_contents($runtime.'/stock-export-snapshot-mariadb.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{if($reader->inTransaction())$reader->rollBack();putenv($oldRoot===false?'APP_PRIVATE_STORAGE_ROOT':'APP_PRIVATE_STORAGE_ROOT='.$oldRoot);}
