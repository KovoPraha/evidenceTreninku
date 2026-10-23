<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';
require_once dirname(__DIR__,2).'/includes/accounting_ledger.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if (($descriptor['synthetic_only']??false)!==true || ($descriptor['external_sends_disabled']??false)!==true || (int)$descriptor['db_port']!==33426) throw new RuntimeException('Owned synthetic sandbox required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$admin->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if ((int)$identity['port']!==33426 || strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/'))) throw new RuntimeException('Database identity mismatch.');
$worker=($argv[1]??'')==='--worker';
$database=$worker?(string)$argv[2]:'evidence_b3_refund_'.bin2hex(random_bytes(4));
if (!preg_match('/^evidence_b3_refund_[a-f0-9]{8}$/D',$database)) throw new RuntimeException('Invalid synthetic schema.');
if (!$worker) {$admin->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4');$admin->exec("GRANT ALL ON ".$database.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$database.';charset=utf8mb4','b1_app','',$options);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=12');
function arPublish(string $path,string $content):void {$tmp=$path.'.'.getmypid().'.tmp';if(file_put_contents($tmp,$content)!==strlen($content)||!rename($tmp,$path))throw new RuntimeException('Failed barrier.');}
function arWait(string $path):void {$deadline=microtime(true)+15;while(!is_file($path)||filesize($path)===0){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timed out: '.$path);usleep(20000);clearstatcache(true,$path);}}
$checks=[];
function arCheck(bool $condition,string $label):void {if(!$condition)throw new RuntimeException($label);$GLOBALS['checks'][]=$label;}
function arDecide(PDO $pdo,int $amount,string $key):array {return accountingRefundDecide($pdo,['original_movement_id'=>1,'amount_minor'=>$amount,'decided_on'=>'2026-01-11','request_key'=>$key],7,'LOCALHOST NEPLATIT race decision.',true);}
function arPay(PDO $pdo,int $id,int $amount,string $key):array {return accountingRefundPay($pdo,$id,['account_id'=>1,'amount_minor'=>$amount,'effective_on'=>'2026-01-12','source_reference'=>$key,'evidence'=>'LOCALHOST NEPLATIT synthetic proof','request_key'=>$key],7,'LOCALHOST NEPLATIT actual payout proof.',true);}
if ($worker) {
    $mode=$argv[3];$path=$argv[4];$id=(int)$argv[5];
    arPublish($path.'.ready',(string)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
    try {if($mode==='decision')arDecide($pdo,7000,'decision-second');else arPay($pdo,$id,5000,'payout-second');arPublish($path.'.result','UNEXPECTED_COMMIT');}
    catch(DomainException|AccountingLedgerException $e) {arPublish($path.'.result','DENIED:'.$e->getMessage());}
    exit;
}
$pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT) ENGINE=InnoDB');
$pdo->exec("INSERT INTO treneri VALUES(7,1);INSERT INTO staff_user_positions VALUES(7,'finance_manager')");
foreach (['20261022220000_accounting_ledger','20261022221000_accounting_documents','20261022222000_accounting_adjustments'] as $id) {$migration=require dirname(__DIR__,2).'/migrations/'.$id.'.php';$migration['up']($pdo);arCheck($migration['verify']($pdo),'Migration verified: '.$id);}
accountingLedgerCreateAccount($pdo,['code'=>'LOCALHOST','name'=>'LOCALHOST NEPLATIT','kind'=>'bank','currency'=>'CZK','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'Synthetic opening'],7,'LOCALHOST NEPLATIT account.',true);
accountingLedgerRecord($pdo,['account_id'=>1,'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'synthetic','source_reference'=>'race-income','request_key'=>'race-income','evidence'=>'Synthetic income'],7,'LOCALHOST NEPLATIT income.',true);
$children=[];
$base=$runtime.'/r17-race-'.bin2hex(random_bytes(5));
$start=static function(string $mode,string $path,int $id)use(&$children,$database) {
    $p=proc_open([PHP_BINARY,__FILE__,'--worker',$database,$mode,$path,(string)$id],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Worker failed to start.');fclose($pipes[0]);$children[]=$p;return $p;
};
$assertWaiting=static function(string $path)use($admin):void {
    arWait($path.'.ready');$connectionText=trim((string)file_get_contents($path.'.ready'));arCheck(ctype_digit($connectionText)&&(int)$connectionText>0,'Worker connection ID published atomically');
    $deadline=microtime(true)+7;$waiting=false;
    do {$s=$admin->prepare('SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?');$s->execute([(int)$connectionText]);$waiting=$s->fetchColumn()==='LOCK WAIT';if(!$waiting)usleep(150000);}while(!$waiting&&microtime(true)<$deadline);
    arCheck($waiting,'Competing financial write actually waits on InnoDB mutex');arCheck(!is_file($path.'.result'),'No competing commit before releasing lock');
};
$finish=static function($child,string $path):void {arWait($path.'.result');$code=proc_close($child);arCheck($code===0,'Worker exited cleanly');arCheck(str_starts_with((string)file_get_contents($path.'.result'),'DENIED:'),'Competing excessive operation rejected after current balance reread');};
try {
    $pdo->beginTransaction();$d=arDecide($pdo,7000,'decision-first');$path=$base.'-decision';$child=$start('decision',$path,0);$assertWaiting($path);$pdo->commit();$finish($child,$path);
    arCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_refund_decisions')->fetchColumn()===1,'Exactly one competing decision reserved');
    arCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn()===1,'Decisions create no money');
    $pdo->beginTransaction();arPay($pdo,(int)$d['id'],5000,'payout-first');$path=$base.'-payment';$child=$start('payment',$path,(int)$d['id']);$assertWaiting($path);$pdo->commit();$finish($child,$path);
    arCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_refund_payments')->fetchColumn()===1,'Exactly one competing partial payout recorded');
    $balance=accountingAdjustmentBalance($pdo,1);arCheck($balance['refunded_minor']===5000&&$balance['reserved_minor']===2000&&$balance['available_minor']===3000,'Cash, remaining reservation and free amount reconcile');
    arCheck(accountingLedgerBalance($pdo,1,'2026-01-12')['closing_minor']===5000,'Actual bank balance includes a single outgoing movement');
    echo json_encode(['database'=>$database,'checks'=>count($checks),'results'=>$checks,'decision_race'=>'PASS','partial_payout_race'=>'PASS','observed_mariadb_lock_wait'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {if($pdo->inTransaction())$pdo->rollBack();foreach($children as $child)if(is_resource($child)){if(proc_get_status($child)['running'])proc_terminate($child);proc_close($child);}}
