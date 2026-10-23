<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/accounting_ledger.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||(int)$descriptor['db_port']!==33426)throw new RuntimeException('Owned synthetic sandbox required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);$identity=$root->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Database identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?(string)$argv[2]:'evidence_r13_race_'.bin2hex(random_bytes(4));
if(!preg_match('/^evidence_r13_race_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Synthetic schema name required.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);$pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
function alrPublish(string $path,string $value):void{$tmp=$path.'.'.getmypid().'.tmp';if(file_put_contents($tmp,$value)!==strlen($value)||!rename($tmp,$path))throw new RuntimeException('Cannot publish barrier.');}
function alrWait(string $path):void{$deadline=microtime(true)+15;while(!is_file($path)){if(microtime(true)>$deadline)throw new RuntimeException('Worker timed out: '.$path);usleep(20000);clearstatcache(true,$path);}}
function alrCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
if($worker){
    $mode=$argv[3];$path=$argv[4];$variant=$argv[5];
    alrPublish($path.'.ready',(string)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
    try{
        if($mode==='source')$result=accountingLedgerReconcileSource($pdo,$variant==='a'?'fio_account_movements':'club_finance_receipts',1,1,'2026-10-01','LOCALHOST same verified bank statement',7,'LOCALHOST concurrent source reconcile.',true);
        else $result=accountingLedgerAllocate($pdo,1,'shop_order',$variant==='a'?10:11,7000,'race-allocation-'.$variant,7,'LOCALHOST concurrent allocation.',true);
        alrPublish($path.'.result',json_encode(['status'=>'ok','id'=>(int)$result['id']],JSON_THROW_ON_ERROR));
    }catch(AccountingLedgerException $e){alrPublish($path.'.result',json_encode(['status'=>'denied','message'=>$e->getMessage()],JSON_THROW_ON_ERROR));}
    exit;
}
$pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');$pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(60)) ENGINE=InnoDB');$pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT) ENGINE=InnoDB');
$pdo->exec('INSERT INTO treneri VALUES(7,1)');$pdo->exec("INSERT INTO staff_user_positions VALUES(7,'finance_manager')");
$pdo->exec('CREATE TABLE shop_orders(id INT PRIMARY KEY,currency CHAR(3)) ENGINE=InnoDB');$pdo->exec("INSERT INTO shop_orders VALUES(10,'CZK'),(11,'CZK')");
$pdo->exec('CREATE TABLE fio_account_movements(id INT PRIMARY KEY,fio_movement_id VARCHAR(100),amount_minor INT,currency CHAR(3),booked_on DATE) ENGINE=InnoDB');$pdo->exec('CREATE TABLE club_finance_receipts(id INT PRIMARY KEY,reference VARCHAR(100),amount_minor INT,currency CHAR(3),paid_on DATE) ENGINE=InnoDB');
$pdo->exec("INSERT INTO fio_account_movements VALUES(1,'LOCALHOST-BANK-RACE',10000,'CZK','2026-10-01')");$pdo->exec("INSERT INTO club_finance_receipts VALUES(1,'LOCALHOST-BANK-RACE',10000,'CZK','2026-10-01')");
$migration=require dirname(__DIR__,2).'/migrations/20261022220000_accounting_ledger.php';$migration['up']($pdo);
accountingLedgerCreateAccount($pdo,['code'=>'TEST-BANK','name'=>'LOCALHOST bank','kind'=>'bank','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>'LOCALHOST opening'],7,'LOCALHOST starting account.',true);
$processes=[];$checks=0;$observed=[];
try{
    foreach(['source','allocation'] as $mode){
        $pdo->beginTransaction();$pdo->exec('UPDATE accounting_ledger_mutex SET revision=revision+1 WHERE id=1');$jobs=[];
        foreach(['a','b'] as $variant){
            $path=$runtime.'/r13-'.$db.'-'.$mode.'-'.$variant;
            $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$mode,$path,$variant],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes);
            if(!is_resource($proc))throw new RuntimeException('Worker could not start.');fclose($pipes[0]);$processes[]=$proc;alrWait($path.'.ready');$connection=trim((string)file_get_contents($path.'.ready'));alrCheck(ctype_digit($connection)&&(int)$connection>0,'Complete connection ID required.');$jobs[]=[$proc,$path,(int)$connection];
        }
        foreach($jobs as [$_,$path,$connection]){
            $deadline=microtime(true)+6;$waiting=false;
            do{$q=$root->prepare('SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?');$q->execute([$connection]);$waiting=$q->fetchColumn()==='LOCK WAIT';if(!$waiting)usleep(150000);}while(!$waiting&&microtime(true)<$deadline);
            alrCheck($waiting,'Worker must actually wait for ledger mutex.');$checks++;$observed[]=$mode.'-'.$connection;
        }
        $pdo->commit();$results=[];
        foreach($jobs as [$proc,$path]){alrWait($path.'.result');alrCheck(proc_close($proc)===0,'Worker failed: '.file_get_contents($path.'.err'));$results[]=json_decode((string)file_get_contents($path.'.result'),true,512,JSON_THROW_ON_ERROR);}
        if($mode==='source'){
            alrCheck($results[0]['status']==='ok'&&$results[1]['status']==='ok'&&$results[0]['id']===$results[1]['id'],'Source alias race must resolve to one movement.');$checks++;
            alrCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_movements')->fetchColumn()===1,'No duplicated money.');$checks++;
            alrCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_movement_sources')->fetchColumn()===2,'Both original sources retained.');$checks++;
        }else{
            alrCheck(count(array_filter($results,static fn($r)=>$r['status']==='ok'))===1,'Only one oversized competing allocation may win.');$checks++;
            alrCheck((int)$pdo->query('SELECT SUM(amount_minor) FROM accounting_allocations')->fetchColumn()===7000,'Allocation race exceeded receipt.');$checks++;
        }
    }
    echo json_encode(['database'=>$db,'checks'=>$checks,'source_alias_race'=>'PASS','overallocation_race'=>'PASS','observed_lock_waits'=>count($observed),'money_movements'=>1],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($processes as $proc)if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);proc_close($proc);}}
