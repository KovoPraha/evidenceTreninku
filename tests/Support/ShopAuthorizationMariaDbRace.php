<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/includes/shop_returns.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$d=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($d['synthetic_only']??false)!==true||($d['external_sends_disabled']??false)!==true||(int)$d['db_port']!==33426)throw new RuntimeException('Synthetic sandbox required.');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$root=new PDO('mysql:host=127.0.0.1;port=33426;charset=utf8mb4','root','',$options);
$identity=$root->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Sandbox identity mismatch.');
$worker=($argv[1]??'')==='--worker';$db=$worker?$argv[2]:'evidence_shop_auth_'.bin2hex(random_bytes(4));
if(!preg_match('/^evidence_shop_auth_[a-f0-9]{8}$/D',$db))throw new RuntimeException('Invalid owned schema.');
if(!$worker){$root->exec('CREATE DATABASE '.$db.' CHARACTER SET utf8mb4');$root->exec("GRANT ALL ON ".$db.".* TO 'b1_app'@'127.0.0.1'");}
$pdo=new PDO('mysql:host=127.0.0.1;port=33426;dbname='.$db.';charset=utf8mb4','b1_app','',$options);$pdo->exec('SET SESSION innodb_lock_wait_timeout=12');
function sarPublish(string $path,array $value):void{$tmp=$path.'.'.getmypid().'.tmp';file_put_contents($tmp,json_encode($value,JSON_THROW_ON_ERROR));if(!rename($tmp,$path))throw new RuntimeException('Barrier publish failed.');}
function sarWait(string $path):array{$end=microtime(true)+15;do{clearstatcache(true,$path);if(is_file($path))return json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);usleep(20000);}while(microtime(true)<$end);throw new RuntimeException('Barrier timeout.');}
if($worker){
 if($pdo->query('SELECT marker FROM fixture_owner')->fetchColumn()!=='LOCALHOST NEPLATIT SHOP AUTH')throw new RuntimeException('Unowned fixture.');
 $path=$argv[3];$helper=$argv[4];$pdo->beginTransaction();
 // Establish the old repeatable-read view while revocation is uncommitted.
 $pdo->query('SELECT COUNT(*) FROM staff_user_positions')->fetchColumn();
 sarPublish($path.'.ready',['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
 try{if($helper==='inventory')shopInventoryActor($pdo,7,'order_operator');else shopReturnActor($pdo,7,'LOCALHOST actual role check.',true,'race-role-check');$pdo->exec("INSERT INTO authorized_writes(note) VALUES('UNEXPECTED obsolete authorization')");$pdo->commit();$result=['status'=>'allowed'];}
 catch(ShopInventoryException|DomainException $e){$pdo->rollBack();$result=['status'=>'denied','message'=>$e->getMessage()];}
 sarPublish($path.'.result',$result);exit;
}
$pdo->exec("CREATE TABLE fixture_owner(marker VARCHAR(80)); INSERT INTO fixture_owner VALUES('LOCALHOST NEPLATIT SHOP AUTH'); CREATE TABLE treneri(id INT PRIMARY KEY,aktivni INT) ENGINE=InnoDB; INSERT INTO treneri VALUES(7,1); CREATE TABLE staff_user_positions(trainer_id INT,position_code VARCHAR(40),PRIMARY KEY(trainer_id,position_code)) ENGINE=InnoDB; CREATE TABLE staff_superadmins(trainer_id INT PRIMARY KEY) ENGINE=InnoDB; CREATE TABLE authorized_writes(id INT AUTO_INCREMENT PRIMARY KEY,note VARCHAR(100)) ENGINE=InnoDB");
$results=[];$children=[];
try{foreach(['inventory','return']as$helper)foreach(['position','superadmin','inactive']as$mode){
 $pdo->exec('DELETE FROM staff_user_positions');$pdo->exec('DELETE FROM staff_superadmins');$pdo->exec('UPDATE treneri SET aktivni=1');
 if($mode==='superadmin')$pdo->exec('INSERT INTO staff_superadmins VALUES(7)');else$pdo->exec("INSERT INTO staff_user_positions VALUES(7,'order_operator')");
 $pdo->beginTransaction();if($mode==='position')$pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=7 AND position_code='order_operator'");elseif($mode==='superadmin')$pdo->exec('DELETE FROM staff_superadmins WHERE trainer_id=7');else$pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
 $path=$runtime.'/'.$db.'-'.$helper.'-'.$mode;
 $child=proc_open([PHP_BINARY,__FILE__,'--worker',$db,$path,$helper],[0=>['pipe','r'],1=>['file',$path.'.out','w'],2=>['file',$path.'.err','w']],$pipes);if(!is_resource($child))throw new RuntimeException('Worker start failed.');fclose($pipes[0]);$children[]=$child;
 $ready=sarWait($path.'.ready');$end=microtime(true)+7;$waiting=false;
 do{$q=$root->prepare('SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=?');$q->execute([$ready['connection']]);$waiting=$q->fetchColumn()==='LOCK WAIT';clearstatcache(true,$path.'.result');if($waiting||is_file($path.'.result'))break;usleep(150000);}while(microtime(true)<$end);
 $pdo->commit();$result=sarWait($path.'.result');if(proc_close($child)!==0)throw new RuntimeException('Worker failed.');
 $results[]=['helper'=>$helper,'revocation'=>$mode,'observed_lock_wait'=>$waiting,'result'=>$result['status'],'passed'=>$waiting&&$result['status']==='denied'];
 }
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($children as$child)if(is_resource($child)){proc_terminate($child);proc_close($child);}}
$writes=(int)$pdo->query('SELECT COUNT(*) FROM authorized_writes')->fetchColumn();$passed=$writes===0&&!in_array(false,array_column($results,'passed'),true);
$report=['database'=>$db,'checks'=>count($results),'results'=>$results,'unauthorized_writes'=>$writes,'passed'=>$passed,'production_mutations'=>false];file_put_contents($runtime.'/shop-authorization-race.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($passed?0:1);
