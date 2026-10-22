<?php
declare(strict_types=1);
// Reversible local review accounts. There is deliberately no host/database override.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$app=dirname(__DIR__);require $app.'/config.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(DB_HOST!=='127.0.0.1;port=33426'||DB_NAME!=='evidence_b1'||rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086'||($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true||trim((string)file_get_contents($runtime.'/owner.txt'))!==$app)throw new RuntimeException('Only the owned isolated B1 environment is permitted.');
$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity=$p->query('SELECT @@port port,@@datadir path')->fetch();
if((int)$identity['port']!==33426||strtolower(rtrim(str_replace('\\','/',$identity['path']),'/'))!==strtolower(str_replace('\\','/',$runtime.'/mysql')))throw new RuntimeException('Unexpected database identity.');
require_once $app.'/includes/staff_workspaces.php';
$file=$runtime.'/local-review-access.json';
if(is_file($file)){
 $access=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
 foreach($access['accounts'] as $account){$q=$p->prepare('SELECT heslo FROM treneri WHERE id=? AND email=? AND aktivni=1');$q->execute([$account['id'],$account['email']]);$hash=$q->fetchColumn();if(!is_string($hash)||!password_verify($account['password'],$hash))throw new RuntimeException('Existing review access changed; refusing to reset it.');$q=$p->prepare('SELECT position_code FROM staff_user_positions WHERE trainer_id=?');$q->execute([$account['id']]);if(array_diff(staffPositionCodes(),$q->fetchAll(PDO::FETCH_COLUMN)))throw new RuntimeException('Review roles changed; refusing to restore removed permissions.');$q=$p->prepare('SELECT 1 FROM staff_superadmins WHERE trainer_id=?');$q->execute([$account['id']]);if(!$q->fetchColumn())throw new RuntimeException('Review administration permission changed.');}
}else{
 $access=['url'=>'http://127.0.0.1:18086/login.php','synthetic_only'=>true,'notice'=>'Pouze místní kontrola. Přepínejte pracovní pozice v horní navigaci. Druhý účet slouží k nezávislému schválení syntetického případu.','accounts'=>[]];
 $p->beginTransaction();try{
  foreach([1,2]as$n){$password=bin2hex(random_bytes(18)).'!aA9';$email='local-review-'.$n.'-'.bin2hex(random_bytes(6)).'@example.invalid';
   $p->prepare('INSERT INTO treneri(jmeno,email,heslo,role,aktivni,session_version) VALUES(?,?,?,\'admin\',1,1)')->execute(['LOCALHOST Kontrola '.$n,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$p->lastInsertId();
   foreach(staffPositionCodes()as$position)$p->prepare('INSERT INTO staff_user_positions(trainer_id,position_code,is_default) VALUES(?,?,?)')->execute([$id,$position,$position==='system_admin'?1:0]);
   $p->prepare('INSERT INTO staff_superadmins(trainer_id,reason) VALUES(?,?)')->execute([$id,'LOCALHOST synthetic review access; no production grants.']);
   $access['accounts'][]=['id'=>$id,'email'=>$email,'password'=>$password];
  }
  $handle=fopen($file,'x');if(!$handle)throw new RuntimeException('Review credential file already exists.');
  try{$json=json_encode($access,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(fwrite($handle,$json)!==strlen($json))throw new RuntimeException('Cannot save local credentials.');}finally{fclose($handle);}
  $p->commit();
 }catch(Throwable$e){if($p->inTransaction())$p->rollBack();throw$e;}
}
// Verify the actual login form without printing a password, cookie or response body.
$cookie='';$request=static function(?array $post=null)use(&$cookie):array{
 $c=curl_init('http://127.0.0.1:18086/login.php');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$cookie):int{if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$m))$cookie=$m[1];return strlen($line);}]);if($post!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return[$status,$body];
};
$checks=0;foreach($access['accounts']as$account){$cookie='';[$status,$body]=$request();if($status!==200||!is_string($body)||!preg_match('/name="csrf_token" value="([^"]+)"/',$body,$m))throw new RuntimeException('Login form unavailable.');[$status]=$request(['jmeno'=>$account['email'],'heslo'=>$account['password'],'csrf_token'=>html_entity_decode($m[1],ENT_QUOTES)]);if(!in_array($status,[302,303],true))throw new RuntimeException('Local review login failed.');$checks++;}
echo json_encode(['status'=>'PASS','login_form_checks'=>$checks,'credentials_file'=>$file,'production_mutations'=>false],JSON_THROW_ON_ERROR).PHP_EOL;
