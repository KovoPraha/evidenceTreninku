<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/coach_scope.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||(int)$descriptor['db_port']!==33426)throw new RuntimeException('Owned synthetic sandbox required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$admin=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$admin->query('SELECT @@port AS port,@@datadir AS data_directory')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['data_directory']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Database identity mismatch.');
$worker=($argv[1]??'')==='--worker';
if(!$worker){$admin->exec('CREATE DATABASE IF NOT EXISTS evidence_b2_coach_scope CHARACTER SET utf8mb4');$admin->exec("GRANT ALL ON evidence_b2_coach_scope.* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname=evidence_b2_coach_scope;charset=utf8mb4','b1_app','',$options);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=10');
function csrSession(string $role):void{$_SESSION=['trener_id'=>$role==='coach'?8:7,'staff_positions'=>[$role],'staff_active_position'=>$role];}
function csrPublish(string $path,string $value):void
{
    // Existence is the signal: publish only a complete payload, never an empty
    // file that the other process could mistake for connection ID zero.
    $temporary=$path.'.'.getmypid().'.tmp';
    if(file_put_contents($temporary,$value)!==strlen($value)||!rename($temporary,$path))throw new RuntimeException('Barrier publication failed.');
}
function csrWaitFile(string $path):void{$deadline=microtime(true)+12;while(!is_file($path)||filesize($path)===0){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timed out.');usleep(10000);clearstatcache(true,$path);}}
function csrCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
if($worker){
    $mode=$argv[2];$barrier=$argv[3];$grantId=(int)$argv[4];
    try{
        if($mode==='revoke'){
            csrSession('sports_lead');csrPublish($barrier.'.ready',(string)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
            coachScopeRevoke($pdo,$grantId,'LOCALHOST NEPLATIT souběžné ukončení přidělení.',7);
            csrPublish($barrier.'.result','REVOKED');
        }else{
            csrSession('coach');coachScopeAssertPeople($pdo,[1]);
            if($mode==='stale_write'){
                csrPublish($barrier.'.ready','PRECHECKED');csrWaitFile($barrier.'.go');
            }
            $pdo->beginTransaction();coachScopeAssertPeople($pdo,[1]);
            if($mode==='locked_write'){
                csrPublish($barrier.'.ready','LOCKED');csrWaitFile($barrier.'.go');
            }
            $pdo->exec("INSERT INTO r09_synthetic_writes(sportovec_id,note) VALUES(1,'LOCALHOST NEPLATIT measurement side effect')");
            $pdo->commit();csrPublish($barrier.'.result','COMMITTED');
        }
    }catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();if(!str_contains($e->getMessage(),'pracovním rozsahu'))throw $e;csrPublish($barrier.'.result','DENIED');}
    exit;
}

$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$owned=['r09_synthetic_marker','r09_synthetic_writes','treneri','staff_user_positions','staff_superadmins','sportovci','podskupiny','sportovec_podskupina','coach_scope_grants','coach_scope_events'];
if($tables&&(!in_array('r09_synthetic_marker',$tables,true)||array_diff($tables,$owned)))throw new RuntimeException('Refusing an unowned fixture.');
$pdo->exec('CREATE TABLE IF NOT EXISTS r09_synthetic_marker(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS staff_user_positions(trainer_id INT,position_code VARCHAR(64)) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS staff_superadmins(trainer_id INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS sportovci(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS podskupiny(id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS sportovec_podskupina(sportovec_id INT,podskupina_id INT) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE IF NOT EXISTS r09_synthetic_writes(id INT AUTO_INCREMENT PRIMARY KEY,sportovec_id INT,note VARCHAR(255)) ENGINE=InnoDB');
$migration=require dirname(__DIR__,2).'/migrations/20261022214000_coach_scope.php';$migration['up']($pdo);
foreach(['coach_scope_events','coach_scope_grants','r09_synthetic_writes','staff_user_positions','staff_superadmins','sportovec_podskupina','podskupiny','sportovci','treneri'] as $table)$pdo->exec('DELETE FROM '.$table);
$pdo->exec('INSERT INTO treneri VALUES(7,1),(8,1)');$pdo->exec("INSERT INTO staff_user_positions VALUES(7,'sports_lead'),(8,'coach')");
$pdo->exec('INSERT INTO sportovci VALUES(1)');$pdo->exec('INSERT INTO podskupiny VALUES(10)');$pdo->exec('INSERT INTO sportovec_podskupina VALUES(1,10)');
$children=[];
$start=static function(string $mode,string $path,int $id)use(&$children){
    $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$mode,$path,(string)$id],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes);
    if(!is_resource($proc))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);$children[]=$proc;return $proc;
};
$finish=static function($proc,string $path):string{csrWaitFile($path.'.result');$exit=proc_close($proc);csrCheck($exit===0,'Worker failed: '.file_get_contents($path.'.err'));return trim((string)file_get_contents($path.'.result'));};
$base=$runtime.'/r09-race-'.bin2hex(random_bytes(6));
try{
    csrSession('sports_lead');$id=coachScopeGrant($pdo,8,'subgroup',10,'2020-01-01 00:00:00',null,'LOCALHOST NEPLATIT explicit race fixture.',7);
    $path=$base.'-stale';$write=$start('stale_write',$path,$id);csrWaitFile($path.'.ready');
    coachScopeRevoke($pdo,$id,'LOCALHOST NEPLATIT odvolání po prvním ověření.',7);
    csrPublish($path.'.go','go');csrCheck($finish($write,$path)==='DENIED','Stale authorization must be rejected inside transaction.');
    csrCheck((int)$pdo->query('SELECT COUNT(*) FROM r09_synthetic_writes')->fetchColumn()===0,'Revocation-first race wrote a side effect.');
    $id=coachScopeGrant($pdo,8,'subgroup',10,'2020-01-01 00:00:00',null,'LOCALHOST NEPLATIT second race fixture.',7);
    $path=$base.'-locked';$write=$start('locked_write',$path,$id);csrWaitFile($path.'.ready');
    $revokePath=$base.'-revoke';$revoke=$start('revoke',$revokePath,$id);csrWaitFile($revokePath.'.ready');
    $connectionText=trim((string)file_get_contents($revokePath.'.ready'));
    csrCheck(ctype_digit($connectionText)&&(int)$connectionText>0,'Revoker must publish a complete positive connection ID.');
    $connection=(int)$connectionText;$deadline=microtime(true)+6;$waiting=false;$state=false;
    // Allow the InnoDB diagnostic snapshot to refresh between observations.
    do{$s=$admin->prepare('SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?');$s->execute([$connection]);$state=$s->fetchColumn();$waiting=$state==='LOCK WAIT';if(!$waiting)usleep(150000);}while(!$waiting&&microtime(true)<$deadline);
    csrCheck($waiting,'Revoke must actually wait on the trainer lock held by writer. Connection='.$connection.' state='.json_encode($state).' worker_error='.(string)file_get_contents($revokePath.'.err'));
    csrCheck(!is_file($revokePath.'.result'),'Revoke completed before authorized write released its lock.');
    csrPublish($path.'.go','go');csrCheck($finish($write,$path)==='COMMITTED','Write-first race must commit before revocation.');
    csrCheck($finish($revoke,$revokePath)==='REVOKED','Queued revoke must complete after write.');
    csrCheck((int)$pdo->query('SELECT COUNT(*) FROM r09_synthetic_writes')->fetchColumn()===1,'Expected exactly one authorized side effect.');
    csrSession('coach');csrCheck(!coachScopeCanViewPerson($pdo,1),'Next request must not retain revoked scope.');
    csrCheck((int)$pdo->query("SELECT COUNT(*) FROM coach_scope_events WHERE action='revoke'")->fetchColumn()===2,'Both revocations need audit events.');
    echo json_encode(['database'=>'evidence_b2_coach_scope','checks'=>9,'revoke_after_precheck'=>'PASS','write_lock_serializes_revoke'=>'PASS','observed_mariadb_lock_wait'=>true,'authorized_writes'=>1,'next_request_denied'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{foreach($children as $child)if(is_resource($child)){if(proc_get_status($child)['running'])proc_terminate($child);proc_close($child);}}
