<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/training_closure.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||(int)$descriptor['db_port']!==33426)throw new RuntimeException('Synthetic environment required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$root->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Sandbox database identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?$argv[2]:'evidence_training_auth_'.bin2hex(random_bytes(4));
if(!preg_match('/^evidence_training_auth_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Invalid synthetic schema.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
$_SESSION=['trener_id'=>1,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];
function tcaPublish(string $path,array $data):void{$tmp=$path.'.'.getmypid().'.tmp';file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR));if(!rename($tmp,$path))throw new RuntimeException('Barrier publish failed.');}
function tcaWait(string $path):array{$end=microtime(true)+20;do{clearstatcache(true,$path);if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);usleep(20000);}while(microtime(true)<$end);throw new RuntimeException('Barrier timeout.');}
if($worker){$path=$argv[3];tcaPublish($path.'.ready',['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);try{
 $result=trainingClosureClose($pdo,100,0,['actual_started_at'=>'2026-01-02 15:00:00','actual_ended_at'=>'2026-01-02 16:00:00','participant_ids'=>[10],'coach_ids'=>[2],'reason'=>'LOCALHOST revoked role closure race.','confirmed'=>true],1);
 tcaPublish($path.'.result',['status'=>'ok','result'=>$result]);
}catch(Throwable $e){tcaPublish($path.'.result',['status'=>$e instanceof TrainingClosureException?'denied':'error','message'=>$e->getMessage()]);}exit;}
foreach([
 'treneri(id INT PRIMARY KEY,aktivni INT)',
 'staff_user_positions(trainer_id INT,position_code VARCHAR(40))',
 'staff_superadmins(trainer_id INT)',
 'sportovci(id INT PRIMARY KEY)',
 'treninky(id INT PRIMARY KEY,datum DATE,delka DECIMAL(5,2),napln TEXT,kategorie VARCHAR(20))',
 'trenink_trener(trenink_id INT,trener_id INT)',
 'trenink_sportovec(trenink_id INT,sportovec_id INT)',
 'planovane_treninky(id INT PRIMARY KEY,trenink_id INT,stav VARCHAR(20))',
]as$schema)$pdo->exec('CREATE TABLE '.$schema.' ENGINE=InnoDB');
$pdo->exec("INSERT INTO treneri VALUES(1,1),(2,1);INSERT INTO staff_user_positions VALUES(1,'sports_lead');INSERT INTO sportovci VALUES(10);INSERT INTO treninky VALUES(100,'2026-01-02',1,'LOCALHOST Session','draha');INSERT INTO trenink_trener VALUES(100,2);INSERT INTO trenink_sportovec VALUES(100,10)");
$migration=require dirname(__DIR__,2).'/migrations/20261023020000_training_closure.php';$migration['up']($pdo);
$results=[];
foreach(['role_revoked','account_deactivated','role_assignment_revoked','superadmin_revoked']as$scenario){
 $pdo->exec('DELETE FROM training_closures;DELETE FROM training_closure_revisions;UPDATE treneri SET aktivni=1 WHERE id=1');
 $pdo->exec("DELETE FROM staff_superadmins;DELETE FROM staff_user_positions;INSERT INTO staff_user_positions VALUES(1,'sports_lead')");
 if($scenario==='superadmin_revoked')$pdo->exec('DELETE FROM staff_user_positions;INSERT INTO staff_superadmins VALUES(1)');
 $pdo->beginTransaction();
 if(in_array($scenario,['role_revoked','account_deactivated'],true))$pdo->query('SELECT id FROM treneri WHERE id=1 FOR UPDATE')->fetchColumn();
 if($scenario==='superadmin_revoked')$pdo->exec('DELETE FROM staff_superadmins WHERE trainer_id=1');
 elseif($scenario==='account_deactivated')$pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=1');
 else$pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=1');
 $path=$runtime.'/'.$db.'-'.$scenario;
 $process=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$path],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes,dirname(__DIR__,2));
 if(!is_resource($process))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);
 try{$ready=tcaWait($path.'.ready');$deadline=microtime(true)+12;$observed=false;
  do{$q=$root->prepare("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id=?");$q->execute([$ready['connection']]);if((int)$q->fetchColumn()===1){$observed=true;break;}usleep(100000);}while(microtime(true)<$deadline);
  if(!$observed)throw new RuntimeException('Actual concurrent authorization lock wait not observed.');$pdo->commit();
  $result=tcaWait($path.'.result');if(proc_close($process)!==0)throw new RuntimeException('Worker failed.');
  $count=(int)$pdo->query('SELECT COUNT(*) FROM training_closure_revisions')->fetchColumn();
  $results[]=['scenario'=>$scenario,'real_lock_wait'=>true,'worker'=>$result,'revision_count'=>$count,'status'=>$result['status']==='denied'&&$count===0?'PASS':'FAIL'];
 }finally{if($pdo->inTransaction())$pdo->rollBack();if(is_resource($process)){proc_terminate($process);proc_close($process);}}
}
$report=['database'=>$db,'production_mutations'=>false,'checks'=>count($results),'results'=>$results];
file_put_contents($runtime.'/r26-authorization-race.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(count(array_filter($results,static fn($r)=>$r['status']!=='PASS'))>0?1:0);
