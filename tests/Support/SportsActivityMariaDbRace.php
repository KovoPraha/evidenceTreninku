<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/sports_activity.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';$d=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($d['synthetic_only']??false)!==true||($d['external_sends_disabled']??false)!==true||(int)$d['db_port']!==33426)throw new RuntimeException('Synthetic environment required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);$identity=$root->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Sandbox database identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?$argv[2]:'evidence_sports_race_'.bin2hex(random_bytes(4));if(!preg_match('/^evidence_sports_race_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Invalid synthetic schema.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);$pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
function sarPublish(string $path,array $data):void {$tmp=$path.'.'.getmypid().'.tmp';file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR));if(!rename($tmp,$path))throw new RuntimeException('Barrier publish failed.');}
function sarWait(string $path):array {$end=microtime(true)+20;do{clearstatcache(true,$path);if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);usleep(20000);}while(microtime(true)<$end);throw new RuntimeException('Barrier timeout.');}
$_SESSION=['trener_id'=>1,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];
if($worker){$mode=$argv[3];$path=$argv[4];$item=(int)$argv[5];sarPublish($path.'.ready',['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
    try{$r=sportsEventRecord($pdo,$mode==='conflict'?1:2,10,1,['stage'=>'started','actual_date'=>'2026-01-10','result_status'=>'DNF','reason'=>'LOCALHOST concurrent sports evidence.','source_reference'=>'LOCALHOST signed list','confirmed'=>true],0,$mode==='retry'?'same-sports-evidence':'distinct-sports-evidence-'.$item);sarPublish($path.'.result',['status'=>'ok','id'=>$r['id'],'version'=>$r['version']]);}
    catch(RuntimeException $e){sarPublish($path.'.result',['status'=>'denied','message'=>$e->getMessage()]);}exit;
}
$pdo->exec('CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');$pdo->exec('INSERT INTO treneri VALUES(1,1)');
$pdo->exec('CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(30)) ENGINE=InnoDB');$pdo->exec("INSERT INTO staff_user_positions VALUES(1,'sports_lead')");
$pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');$pdo->exec('INSERT INTO sportovci VALUES(10)');
$pdo->exec('CREATE TABLE club_events(id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(100),activity_kind VARCHAR(20),planning_status VARCHAR(20),legacy_race_id INT) ENGINE=InnoDB');$pdo->exec("INSERT INTO club_events VALUES(1,'LOCALHOST RACE 1','race','completed',NULL),(2,'LOCALHOST RACE 2','race','completed',NULL)");
$m=require dirname(__DIR__,2).'/migrations/20261023030000_sports_activity.php';$m['up']($pdo);if(!$m['verify']($pdo))throw new RuntimeException('Migration failed.');
$results=[];$processes=[];try{foreach(['conflict'=>1,'retry'=>2] as $mode=>$event){
    $pdo->beginTransaction();$pdo->query('SELECT id FROM club_events WHERE id='.$event.' FOR UPDATE');$jobs=[];
    foreach([1,2] as $item){$path=$runtime.'/'.$db.'-'.$mode.'-'.$item;$proc=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$mode,$path,(string)$item],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes,dirname(__DIR__,2));if(!is_resource($proc))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);$processes[]=$proc;$ready=sarWait($path.'.ready');$jobs[]=['path'=>$path,'proc'=>$proc,'connection'=>$ready['connection']];}
    $ids=array_column($jobs,'connection');$deadline=microtime(true)+12;$observed=false;do{$wait=$root->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id IN (".implode(',',$ids).')')->fetchColumn();if((int)$wait===2){$observed=true;break;}usleep(150000);}while(microtime(true)<$deadline);if(!$observed)throw new RuntimeException('Did not observe both real concurrent lock waits.');$pdo->commit();
    $out=[];foreach($jobs as $job){$out[]=sarWait($job['path'].'.result');if(proc_close($job['proc'])!==0)throw new RuntimeException('Worker failed.');}
    $ok=count(array_filter($out,static fn($r)=>$r['status']==='ok'));if($mode==='conflict'&&$ok!==1)throw new RuntimeException('Stale version accepted under race.');if($mode==='retry'&&($ok!==2||$out[0]['id']!==$out[1]['id']))throw new RuntimeException('Duplicate evidence under retry race.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM sports_event_revisions r JOIN sports_event_participation p ON p.id=r.participation_id WHERE p.event_id=?');$q->execute([$event]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Race created duplicate revision.');$results[]=['scenario'=>$mode,'two_real_lock_waits'=>true,'results'=>$out,'one_revision'=>true,'status'=>'PASS'];
}}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($processes as $p)if(is_resource($p)){proc_terminate($p);proc_close($p);}}
echo json_encode(['database'=>$db,'checks'=>6,'results'=>$results,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
