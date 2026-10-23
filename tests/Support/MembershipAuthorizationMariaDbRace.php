<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/club_program_admission.php';
require_once dirname(__DIR__,2).'/includes/operational_cases.php';
require_once dirname(__DIR__,2).'/includes/sports_activity.php';
require_once dirname(__DIR__,2).'/includes/accounting_ledger.php';
require_once dirname(__DIR__,2).'/includes/accounting_adjustments.php';
require_once dirname(__DIR__,2).'/includes/accounting_export.php';
require_once dirname(__DIR__,2).'/includes/club_event_notification.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||(int)$descriptor['db_port']!==33426)throw new RuntimeException('Synthetic environment required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$root->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Sandbox database identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?$argv[2]:'evidence_membership_auth_'.bin2hex(random_bytes(4));
if(!preg_match('/^evidence_membership_auth_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Invalid synthetic schema.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=20');
function marPublish(string $path,array $data):void{$tmp=$path.'.'.getmypid().'.tmp';file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR));if(!rename($tmp,$path))throw new RuntimeException('Barrier publish failed.');}
function marWait(string $path):array{$end=microtime(true)+20;do{clearstatcache(true,$path);if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);usleep(20000);}while(microtime(true)<$end);throw new RuntimeException('Barrier timeout.');}
if($worker){$mode=$argv[3];$path=$argv[4];$_SESSION=['trener_id'=>1,'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];marPublish($path.'.ready',['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);try{
 if($mode==='notification'){$result=clubEventNotificationResolveManually($pdo,1,1,'LOCALHOST synthetic personal contact only',true);marPublish($path.'.result',['status'=>'ok','result'=>$result]);exit;}
 $pdo->beginTransaction();
 match($mode){
  'admission'=>clubAdmissionActor($pdo,1),
  'sports'=>sportsActivityAssertActor($pdo,1,0),
  'coach_manager'=>coachScopeAssertManager($pdo,1),
  'ledger'=>accountingLedgerActor($pdo,1),
  'adjustment'=>accountingAdjustmentActor($pdo,1,'LOCALHOST confirmed synthetic correction',true,'local-guard-race'),
  'export'=>accountingExportActor($pdo,1),
  default=>operationalCaseActor($pdo,1,'registrar'),
 };
 marPublish($path.'.authorized',['authorized'=>true]);marWait($path.'.release');
 $pdo->exec('INSERT INTO protected_writes VALUES(1)');$pdo->commit();
 marPublish($path.'.result',['status'=>'ok']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();marPublish($path.'.result',['status'=>$e instanceof DomainException||$e instanceof AccountingLedgerException||$e instanceof ClubEventNotificationException||get_class($e)===RuntimeException::class?'denied':'error','message'=>$e->getMessage()]);}exit;}
foreach(['treneri(id INT PRIMARY KEY,aktivni INT)','staff_user_positions(trainer_id INT,position_code VARCHAR(40))','staff_superadmins(trainer_id INT)','protected_writes(id INT PRIMARY KEY)','club_event_notifications(id INT PRIMARY KEY,status VARCHAR(30),attempts INT,last_error VARCHAR(255))','club_event_notification_events(id INT AUTO_INCREMENT PRIMARY KEY,notification_id INT,actor_trainer_id INT,action VARCHAR(50),from_status VARCHAR(30),attempts_before INT,reason TEXT)']as$schema)$pdo->exec('CREATE TABLE '.$schema.' ENGINE=InnoDB');
$pdo->exec("INSERT INTO club_event_notifications VALUES(1,'failed',1,'manual_contact_required: LOCALHOST synthetic only')");
$pdo->exec('INSERT INTO treneri VALUES(1,1)');$results=[];
foreach(['admission','operational','sports','coach_manager','ledger','adjustment','export','notification']as$mode)foreach(($mode==='sports'?['role']:['role','superadmin'])as$grant){
 $pdo->exec('DELETE FROM protected_writes;DELETE FROM club_event_notification_events;DELETE FROM staff_user_positions;DELETE FROM staff_superadmins');
 $role=match($mode){'sports','coach_manager'=>'sports_lead','ledger','adjustment','export'=>'finance_manager','notification'=>'order_operator',default=>'registrar'};
 if($grant==='role')$pdo->prepare('INSERT INTO staff_user_positions VALUES(1,?)')->execute([$role]);else$pdo->exec('INSERT INTO staff_superadmins VALUES(1)');
 $pdo->beginTransaction();$pdo->exec('DELETE FROM '.($grant==='role'?'staff_user_positions':'staff_superadmins').' WHERE trainer_id=1');
 if($mode==='notification')$pdo->query('SELECT id FROM club_event_notifications WHERE id=1 FOR UPDATE')->fetchColumn();
 $path=$runtime.'/'.$db.'-'.$mode.'-'.$grant;
 $process=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$mode,$path],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes,dirname(__DIR__,2));
 if(!is_resource($process))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);
 try{$ready=marWait($path.'.ready');$deadline=microtime(true)+12;$observed=false;$authorized=false;
  do{$q=$root->prepare("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id=?");$q->execute([$ready['connection']]);if((int)$q->fetchColumn()===1){$observed=true;break;}clearstatcache(true,$path.'.authorized');if(is_file($path.'.authorized')){$authorized=true;break;}usleep(100000);}while(microtime(true)<$deadline);
  if(!$observed&&!$authorized)throw new RuntimeException('Neither locked nor authorized worker observed.');$pdo->commit();marPublish($path.'.release',['revocation_committed'=>true]);
  $result=marWait($path.'.result');if(proc_close($process)!==0)throw new RuntimeException('Worker failed.');
  $count=(int)$pdo->query('SELECT COUNT(*) FROM '.($mode==='notification'?'club_event_notification_events':'protected_writes'))->fetchColumn();
  $results[]=['guard'=>$mode,'revoked_grant'=>$grant,'real_lock_wait'=>$observed,'authorized_before_revocation_commit'=>$authorized,'worker'=>$result,'post_revocation_write_count'=>$count,'status'=>$result['status']==='denied'&&$count===0?'PASS':'FAIL'];
 }finally{if($pdo->inTransaction())$pdo->rollBack();if(is_resource($process)){proc_terminate($process);proc_close($process);}}
}
$report=['database'=>$db,'production_mutations'=>false,'checks'=>count($results),'results'=>$results];
file_put_contents($runtime.'/membership-authorization-race.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(count(array_filter($results,static fn($r)=>$r['status']!=='PASS'))>0?1:0);
