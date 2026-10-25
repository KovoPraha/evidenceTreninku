<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/accounting_export.php';
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';

/** Inject a real independently committed write between the money and allocation reads. */
final class AccountingSnapshotHookPdo extends PDO
{
    public ?Closure $betweenReads=null;
    public bool $fired=false;
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        if($this->betweenReads!==null && str_contains($query,'FROM accounting_allocations WHERE effective_on<=?')){
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
$database='evidence_b3_export_rr_'.bin2hex(random_bytes(4));
$admin->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4');$admin->exec("GRANT ALL ON ".$database.".* TO 'b1_app'@'127.0.0.1'");
$dsn='mysql:host=127.0.0.1;port=33426;dbname='.$database.';charset=utf8mb4';
$reader=new AccountingSnapshotHookPdo($dsn,'b1_app','',$options);$writer=new PDO($dsn,'b1_app','',$options);
$reader->exec('SET SESSION innodb_lock_wait_timeout=8');$writer->exec('SET SESSION innodb_lock_wait_timeout=8');
$reader->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');
// Match the production role lookup keys. A heap fixture locks both actors on scan,
// preventing the independent commit that this snapshot test must actually exercise.
$reader->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40),PRIMARY KEY(trainer_id,position_code)) ENGINE=InnoDB');
$reader->exec('CREATE TABLE staff_superadmins(trainer_id INT PRIMARY KEY) ENGINE=InnoDB');
$reader->exec("INSERT INTO treneri VALUES(7,1),(8,1);INSERT INTO staff_user_positions VALUES(7,'finance_manager'),(8,'finance_manager')");
$reader->exec("CREATE TABLE shop_orders(id INT PRIMARY KEY,currency CHAR(3)) ENGINE=InnoDB;INSERT INTO shop_orders VALUES(4,'CZK'),(5,'CZK')");
$reader->exec("ALTER TABLE shop_orders ADD public_code VARCHAR(40) NOT NULL DEFAULT 'LOCALHOST',ADD customer_name_snapshot VARCHAR(100) NOT NULL DEFAULT 'LOCALHOST',ADD total_minor BIGINT NOT NULL DEFAULT 0,ADD status VARCHAR(30) NOT NULL DEFAULT 'completed',ADD payment_status VARCHAR(30) NOT NULL DEFAULT 'paid',ADD placed_at DATETIME NOT NULL DEFAULT '2026-01-01 00:00:00'");
$checks=[];
function asrCheck(bool $condition,string $label):void {if(!$condition)throw new RuntimeException($label);$GLOBALS['checks'][]=$label;}
foreach(['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments','20261022223000_accounting_exports'] as $id){$migration=require dirname(__DIR__,2).'/migrations/'.$id.'.php';$migration['up']($reader);asrCheck($migration['verify']($reader),'Migration verified: '.$id);}
$root=$runtime.'/snapshot-proof-'.bin2hex(random_bytes(8));if(!mkdir($root,0700))throw new RuntimeException('Private proof directory failed.');$oldRoot=getenv('APP_PRIVATE_STORAGE_ROOT');putenv('APP_PRIVATE_STORAGE_ROOT='.$root);
$reason='LOCALHOST NEPLATIT export snapshot proof';
accountingLedgerCreateAccount($writer,['code'=>'LOCALHOST','name'=>$reason,'kind'=>'bank','currency'=>'CZK','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>$reason],8,$reason,true);
$income=accountingLedgerRecord($writer,['account_id'=>1,'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'synthetic','source_reference'=>'snapshot-income','request_key'=>'snapshot-income','case_type'=>'shop_order','case_id'=>4,'evidence'=>$reason],8,$reason,true);
$allocation=accountingLedgerAllocate($writer,(int)$income['id'],'shop_order',4,10000,'snapshot-allocated',8,$reason,true,null,'goods','club','2026-01-10');
$commitRefund=static function(int $movement,int $allocation,int $caseId,int $amount,string $key)use($writer,$reason):void{
    $writer->beginTransaction();
    try {
        accountingLedgerAllocate($writer,$movement,'shop_order',$caseId,-$amount,$key.'-release',8,$reason,true,$allocation,'goods','club','2026-01-11');
        $decision=accountingRefundDecide($writer,['original_movement_id'=>$movement,'amount_minor'=>$amount,'decided_on'=>'2026-01-11','request_key'=>$key.'-decision'],8,$reason,true);
        accountingRefundPay($writer,(int)$decision['id'],['account_id'=>1,'amount_minor'=>$amount,'effective_on'=>'2026-01-12','source_reference'=>$key.'-refund','request_key'=>$key.'-refund','evidence'=>$reason],8,$reason,true);
        $writer->commit();
    }catch(Throwable $e){if($writer->inTransaction())$writer->rollBack();throw $e;}
};
try {
    $reader->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    asrCheck($reader->query('SELECT @@session.tx_isolation')->fetchColumn()==='READ-COMMITTED','Reader session starts in READ COMMITTED');
    asrCheck((int)$reader->query('SELECT CONNECTION_ID()')->fetchColumn()!==(int)$writer->query('SELECT CONNECTION_ID()')->fetchColumn(),'Reader and writer use distinct MariaDB connections');
    $reader->betweenReads=static function()use($commitRefund,$income,$allocation):void{$commitRefund((int)$income['id'],(int)$allocation['id'],4,4000,'snapshot-late');};
    $batch=accountingExportCreate($reader,'2026-01-01','2026-01-31','snapshot-export-one',7,$reason,true);
    asrCheck($reader->fired,'Independent writer committed between money and allocation SELECTs');
    $snapshot=json_decode($batch['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    asrCheck(count($snapshot['movements'])===1,'Export excludes money committed after its snapshot');
    asrCheck(count($snapshot['allocations'])===1&&(int)$snapshot['allocations'][0]['amount_minor']===10000,'Export excludes the matching late allocation release');
    asrCheck($snapshot['refund_decisions']===[]&&$snapshot['refund_payments']===[],'Export excludes the matching late decision and refund payment');
    asrCheck((int)$writer->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn()===2&&(int)$writer->query('SELECT COUNT(*) FROM accounting_allocations')->fetchColumn()===2&&(int)$writer->query('SELECT COUNT(*) FROM accounting_refund_payments')->fetchColumn()===1,'Independent connection proves all excluded rows really committed');
    asrCheck($reader->query('SELECT @@session.tx_isolation')->fetchColumn()==='READ-COMMITTED','Exporter changes isolation only for its own transaction');
    $download=accountingExportDownload($reader,(int)$batch['id'],7);$zip=new ZipArchive();asrCheck($zip->open($download['path'])===true,'Frozen export archive opens');
    $archived=json_decode($zip->getFromName('data.json'),true,512,JSON_THROW_ON_ERROR);$zip->close();
    asrCheck($archived===$snapshot,'Archive retains the same consistent snapshot');

    // Negative control proves the interleaving would expose a real mixed snapshot without the isolation override.
    $second=accountingLedgerRecord($writer,['account_id'=>1,'kind'=>'income','amount_minor'=>20000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'synthetic','source_reference'=>'control-income','request_key'=>'control-income','case_type'=>'shop_order','case_id'=>5,'evidence'=>$reason],8,$reason,true);
    $secondAllocation=accountingLedgerAllocate($writer,(int)$second['id'],'shop_order',5,20000,'control-allocated',8,$reason,true,null,'goods','club','2026-01-10');
    $reader->fired=false;$reader->betweenReads=static function()use($commitRefund,$second,$secondAllocation):void{$commitRefund((int)$second['id'],(int)$secondAllocation['id'],5,1000,'control-late');};
    $reader->beginTransaction();$control=accountingExportSnapshot($reader,'2026-01-01','2026-01-31');$reader->commit();
    $movementIds=array_map('intval',array_column($control['movements'],'id'));$lateIds=array_values(array_diff(array_map('intval',array_column($control['refund_payments'],'movement_id')),$movementIds));
    asrCheck($reader->fired&&count($lateIds)===1,'READ COMMITTED negative control exposes a refund payment absent from the earlier money read');
    $report=['status'=>'PASS','database'=>$database,'checks'=>count($checks),'results'=>$checks,'two_connections'=>true,'session_isolation'=>'READ-COMMITTED','export_snapshot_consistent'=>true,'negative_control_mixed_snapshot_detected'=>true,'private_proof_root'=>$root];
    file_put_contents($runtime.'/accounting-export-snapshot-mariadb.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {if($reader->inTransaction())$reader->rollBack();if($writer->inTransaction())$writer->rollBack();putenv($oldRoot===false?'APP_PRIVATE_STORAGE_ROOT':'APP_PRIVATE_STORAGE_ROOT='.$oldRoot);}
