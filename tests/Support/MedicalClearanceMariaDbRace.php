<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/medical_clearance.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';$d=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($d['synthetic_only']??false)!==true||($d['external_sends_disabled']??false)!==true||(int)$d['db_port']!==33426)throw new RuntimeException('Synthetic environment required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);$identity=$root->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Sandbox database identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?$argv[2]:'evidence_medical_race_'.bin2hex(random_bytes(4));if(!preg_match('/^evidence_medical_race_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Invalid synthetic schema.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);$pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
function mcrPublish(string $path,array $data):void {$tmp=$path.'.'.getmypid().'.tmp';file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR));if(!rename($tmp,$path))throw new RuntimeException('Barrier publish failed.');}
function mcrWait(string $path):array {$end=microtime(true)+20;do{clearstatcache(true,$path);if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);usleep(20000);}while(microtime(true)<$end);throw new RuntimeException('Barrier timeout.');}
$_SESSION=['trener_id'=>1,'staff_positions'=>['registrar'],'staff_active_position'=>'registrar'];
if($worker){$mode=$argv[3];$path=$argv[4];mcrPublish($path.'.ready',['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
    try{medicalClearanceDecide($pdo,1,$mode==='verified'?1:2,$mode,'LOCALHOST concurrent document decision.');mcrPublish($path.'.result',['status'=>'ok']);}
    catch(DomainException $e){mcrPublish($path.'.result',['status'=>'denied','message'=>$e->getMessage()]);}exit;
}
$pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');$pdo->exec('INSERT INTO treneri VALUES(1,1)');
$pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(30)) ENGINE=InnoDB');$pdo->exec("INSERT INTO staff_user_positions VALUES(1,'registrar')");
$pdo->exec('CREATE TABLE staff_superadmins(trainer_id INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');$pdo->exec('INSERT INTO sportovci VALUES(10)');
$m=require dirname(__DIR__,2).'/migrations/20261023040000_medical_clearance.php';$m['up']($pdo);if(!$m['verify']($pdo))throw new RuntimeException('Migration failed.');
$policy=medicalClearancePolicyCreate($pdo,['purpose'=>'LOCALHOST test','legal_basis'=>'LOCALHOST explicit synthetic purpose','retention_rule'=>'LOCALHOST test only','activity_rule'=>'LOCALHOST human review','reminder_days'=>30,'confirmed'=>'1']);
$watch=medicalClearanceWatchCreate($pdo,['sportovec_id'=>10,'policy_id'=>$policy,'reason'=>'LOCALHOST concurrent proof']);
$id=medicalClearanceRecord($pdo,['watch_id'=>$watch,'issued_on'=>'2026-01-01','valid_until'=>'2030-01-01','original_location'=>'LOCALHOST paper evidence','reason'=>'LOCALHOST test evidence']);if($id!==1)throw new RuntimeException('Unexpected synthetic record ID.');
$results=[];$processes=[];try{foreach(['verified'=>2,'revoked'=>3] as $mode=>$expectedRevision){
    $pdo->beginTransaction();$pdo->query('SELECT id FROM medical_clearance_watches WHERE id='.$watch.' FOR UPDATE');$jobs=[];
    foreach([1,2] as $item){$path=$runtime.'/'.$db.'-'.$mode.'-'.$item;$proc=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$mode,$path,(string)$item],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes,dirname(__DIR__,2));if(!is_resource($proc))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);$processes[]=$proc;$ready=mcrWait($path.'.ready');$jobs[]=['path'=>$path,'proc'=>$proc,'connection'=>$ready['connection']];}
    $ids=array_column($jobs,'connection');$deadline=microtime(true)+12;$observed=false;do{$wait=$root->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id IN (".implode(',',$ids).')')->fetchColumn();if((int)$wait===2){$observed=true;break;}usleep(150000);}while(microtime(true)<$deadline);if(!$observed)throw new RuntimeException('Did not observe both real concurrent lock waits.');$pdo->commit();
    $out=[];foreach($jobs as $job){$out[]=mcrWait($job['path'].'.result');if(proc_close($job['proc'])!==0)throw new RuntimeException('Worker failed.');}
    $ok=count(array_filter($out,static fn($r)=>$r['status']==='ok'));if($ok!==1)throw new RuntimeException('Stale decision accepted under race.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM medical_clearance_events WHERE record_id=1 AND action=?');$q->execute([$mode]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Race duplicated decision audit.');
    $row=$pdo->query('SELECT state,revision FROM medical_clearance_records WHERE id=1')->fetch();if($row['state']!==$mode||(int)$row['revision']!==$expectedRevision)throw new RuntimeException('Wrong final document state.');
    $results[]=['scenario'=>$mode,'two_real_lock_waits'=>true,'results'=>$out,'one_decision'=>true,'revision'=>(int)$row['revision'],'status'=>'PASS'];
}}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($processes as $p)if(is_resource($p)){proc_terminate($p);proc_close($p);}}
echo json_encode(['database'=>$db,'checks'=>8,'results'=>$results,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
