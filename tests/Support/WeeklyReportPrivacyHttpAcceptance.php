<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$app=dirname(__DIR__,2);require$app.'/config.php';
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||APP_BASE_URL!=='http://127.0.0.1:18086'||trim((string)file_get_contents($runtime.'/owner.txt'))!==$app)throw new RuntimeException('Isolated sandbox only.');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity=$pdo->query('SELECT @@port port,@@datadir path')->fetch();if((int)$identity['port']!==33426||strtolower(str_replace('\\','/',$identity['path']))!==strtolower(str_replace('\\','/',$runtime.'/mysql/')))throw new RuntimeException('Wrong database identity.');
session_save_path($runtime.'/sessions');require_once$app.'/includes/auth_session.php';require_once$app.'/csrf_helper.php';require_once$app.'/report_tyden_lib.php';
$run='LOCALHOST PRIVATE REPORT '.bin2hex(random_bytes(5));$checks=[];
$assert=static function(bool$ok,string$label)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=$label;};
$insert=static function(string$table,array$row)use($pdo):int{$q=$pdo->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($row)).') VALUES('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));return(int)$pdo->lastInsertId();};
$actors=[];$sessions=[];
foreach(['sports_lead','coach','finance_manager']as$role){$id=$insert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);$insert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$role,'is_default'=>1]);$actors[$role]=$id;session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($id,1);auth_session_refresh_trainer_authorization($pdo,$id);app_session_mark_authenticated();$sessions[$role]=session_name().'='.session_id();session_write_close();}
$_SESSION=['trener_id'=>$actors['sports_lead'],'staff_positions'=>['sports_lead'],'staff_active_position'=>'sports_lead'];
$context=['skupina_id'=>0,'podskupina_id'=>0,'skupina_nazev'=>$run,'podskupina_nazev'=>null,'week_start'=>'2026-10-19','week_end'=>'2026-10-25','trainings'=>[],'person_ids'=>[],'trainersByT'=>[],'participantsByT'=>[],'measurementsByT'=>[]];
$saved=save_week_report($pdo,$context);$again=save_week_report($pdo,$context);
$assert($saved['id']!==$again['id']&&is_file($saved['pdf'])&&is_file($again['pdf']),'Repeated saves preserve separate original files');
$assert(str_starts_with(str_replace('\\','/',$saved['pdf']),str_replace('\\','/',$runtime.'/private/week-reports/')),'PDF stored outside web root');
$assert(str_starts_with((string)file_get_contents($saved['pdf']),'%PDF-'),'Real PDF generated');
$request=static function(string$route,string$cookie=''):array{$c=curl_init(APP_BASE_URL.'/'.$route);$headers=[];curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($c,string$line)use(&$headers):int{$headers[]=$line;return strlen($line);}]);$body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return[$status,(string)$body,implode('',$headers)];};
$route='weekly_report_file.php?id='.$saved['id'];
[$status,$body,$headers]=$request($route,$sessions['sports_lead']);$assert($status===200&&hash('sha256',$body)===hash_file('sha256',$saved['pdf']),'Authorized actual HTTP download matches PDF checksum');
$assert(str_contains(strtolower($headers),'content-disposition: attachment')&&str_contains(strtolower($headers),'no-store'),'Private attachment and no-store response');
foreach(['coach','finance_manager']as$role){[$status]=$request($route,$sessions[$role]);$assert($status===403,'Other active workspace cannot download: '.$role);}
[$status,$body]=$request($route);$assert(in_array($status,[302,303,403],true)&&!str_starts_with($body,'%PDF-'),'Anonymous download denied');
[$status,$body]=$request('reports/weekly/anything.pdf');$assert($status===404&&$body==="Not found.\n",'Legacy public report directory is denied by local router');
[$status]=$request('weekly_report_file.php?id=../config.php',$sessions['sports_lead']);$assert($status===403,'Traversal denied');
$pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$actors['sports_lead']]);[$status]=$request($route,$sessions['sports_lead']);$assert($status===403,'Revoked sports lead cannot reuse cached authenticated cookie');
echo json_encode(['status'=>'PASS','checks'=>count($checks),'results'=>$checks,'private_report_ids'=>[$saved['id'],$again['id']],'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
