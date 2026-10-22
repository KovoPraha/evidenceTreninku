<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=dirname(__DIR__,2);
require $app.'/config.php';
if (!defined('DB_NAME') || DB_NAME!=='evidence_b1' || DB_HOST!=='127.0.0.1;port=33426' || rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086') throw new RuntimeException('Only the isolated synthetic B1 sandbox is allowed.');
$sandbox=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
if (!is_dir($sandbox.'/sessions')) throw new RuntimeException('Prepare the isolated sandbox first.');
session_save_path($sandbox.'/sessions');
require_once $app.'/includes/auth_session.php';
require_once $app.'/includes/private_storage.php';
require_once $app.'/includes/paper_registration.php';
require_once $app.'/includes/public_profile_token.php';
require_once $app.'/csrf_helper.php';
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ((int)$pdo->query('SELECT @@port')->fetchColumn()!==33426) throw new RuntimeException('Wrong server port.');
$run='LOCALHOST NEPLATIT R40-R44 '.bin2hex(random_bytes(5));
$results=[];
function cfCheck(bool $ok,string $label): void { if(!$ok)throw new RuntimeException('FAIL: '.$label); $GLOBALS['results'][]=['scenario'=>$label,'status'=>'PASS']; }
function cfInsert(string $table,array $values): int {
    $q=$GLOBALS['pdo']->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');
    $q->execute(array_values($values)); return (int)$GLOBALS['pdo']->lastInsertId();
}
function cfSession(int $trainer): array {
    session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($trainer,1);
    auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$trainer);app_session_mark_authenticated();
    $session=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $session;
}
function cfPublicSession(int $account): array {
    session_id('');app_session_start();$_SESSION=[];auth_session_bind_public_user($account,1);app_session_mark_authenticated();
    $session=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $session;
}
function cfMixedSession(int $trainer,int $account): array {
    session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($trainer,1);auth_session_bind_public_user($account,1);
    auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$trainer);app_session_mark_authenticated();
    $session=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $session;
}
function cfRequest(array $session,string $route,?array $post=null): array {
    $curl=curl_init('http://127.0.0.1:18086/'.$route);
    $cookie=$session['cookie'];
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>15,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$cookie):int{if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$match))$cookie=$match[1];return strlen($line);}]);
    if($post!==null){$post+=['csrf_token'=>$session['csrf']];curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,count(array_filter($post,static fn($v)=>$v instanceof CURLFile))>0?$post:http_build_query($post));}
    $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);clearstatcache(true);
    if(!is_string($body)||$status>=500||$status===0)throw new RuntimeException('HTTP failure '.$route.': '.$status.' '.$error);
    return ['status'=>$status,'body'=>$body,'cookie'=>$cookie];
}
function cfPaperCount(): int { return (int)$GLOBALS['pdo']->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn(); }
function cfPaperFiles(): array { $out=[];foreach(glob(privateStorageRoot().'/paper-documents/*')?:[] as $path)if(is_file($path))$out[basename($path)]=hash_file('sha256',$path);ksort($out);return $out; }
function cfPaperRow(int $id): array { $q=$GLOBALS['pdo']->prepare('SELECT * FROM club_paper_records WHERE id=?');$q->execute([$id]);return $q->fetch()?:[]; }

require_once $app.'/includes/club_cutover.php';
$descriptor=json_decode(file_get_contents($sandbox.'/environment.json'),true,512,JSON_THROW_ON_ERROR);if(empty($descriptor['synthetic_only'])||strtolower(str_replace('\\','/',rtrim((string)$pdo->query('SELECT @@datadir')->fetchColumn(),'/\\')))!==strtolower(str_replace('\\','/',$sandbox.'/mysql')))throw new RuntimeException('Unowned database.');
try{
 $sessions=[];$actors=[];foreach(['system_admin','finance_manager','coach']as$position){$actor=cfInsert('treneri',['jmeno'=>$run.' '.$position,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);cfInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>$position,'is_default'=>1]);$sessions[$position]=cfSession($actor);$actors[$position]=$actor;}
 $admin=$sessions['system_admin'];$actor=$actors['system_admin'];$count=(int)$pdo->query('SELECT COUNT(*) FROM club_cutover_runs')->fetchColumn();cfCheck(cfRequest($admin,'club_cutover_admin.php')['status']===200,'Technical preparation workspace available');foreach(['finance_manager','coach']as$role)cfCheck(cfRequest($sessions[$role],'club_cutover_admin.php')['status']===403,$role.' cannot access technical cutover');
 $create=['action'=>'create','title'=>$run,'scope_text'=>'LOCALHOST single group and isolated stock','release_reference'=>'LOCALHOST fixture','source_environment'=>'https://old.example.invalid','target_environment'=>'http://127.0.0.1:18086','owner_trainer_id'=>$actor,'confirmed'=>'1'];cfCheck(cfRequest($admin,'club_cutover_admin.php',$create+['csrf_token'=>'forged'])['status']===403,'Forged CSRF cannot create preparation');cfCheck((int)$pdo->query('SELECT COUNT(*) FROM club_cutover_runs')->fetchColumn()===$count,'Rejected form created no run');cfCheck(cfRequest($admin,'club_cutover_admin.php',$create)['status']===303,'Actual HTTP creates isolated preparation');$q=$pdo->prepare('SELECT * FROM club_cutover_runs WHERE title=? ORDER BY id DESC LIMIT 1');$q->execute([$run]);$r=$q->fetch();$id=(int)$r['id'];
 $source=['action'=>'entry','run_id'=>$id,'revision'=>0,'entry_key'=>'source:old','kind'=>'source','title'=>'LOCALHOST Evidence','owner_trainer_id'=>$actor,'evidence'=>$run,'origin'=>'https://old.example.invalid','history_mode'=>'active','shutdown_condition'=>'LOCALHOST future explicit service decision','reason'=>$run,'confirmed'=>'1'];cfCheck(cfRequest($admin,'club_cutover_admin.php',$source)['status']===303,'Source ownership declaration stored');cfCheck(cfRequest($admin,'club_cutover_admin.php',$source)['status']===422,'Stale registry revision rejected');
 $url=['action'=>'entry','run_id'=>$id,'revision'=>1,'entry_key'=>'url:old','kind'=>'url','title'=>'LOCALHOST public content','owner_trainer_id'=>$actor,'evidence'=>$run,'source_key'=>'source:old','old_url'=>'https://old.example.invalid/info','new_url'=>'http://127.0.0.1:18086/info','access_mode'=>'public','handling'=>'proposal only','reason'=>$run,'confirmed'=>'1'];cfCheck(cfRequest($admin,'club_cutover_admin.php',array_replace($url,['old_url'=>'https://old.example.invalid/info?token=secret']))['status']===422,'Secret export URL rejected before storage');cfCheck(cfRequest($admin,'club_cutover_admin.php',array_replace($url,['new_url'=>'https://attacker.invalid']))['status']===422,'Unowned destination rejected');cfCheck(cfRequest($admin,'club_cutover_admin.php',$url)['status']===303,'Declared owned content URL map stored without redirect activation');
 $account=accountingLedgerCreateAccount($pdo,['code'=>'CUTOVER-'.strtoupper(bin2hex(random_bytes(4))),'name'=>$run,'kind'=>'cash','currency'=>'CZK','opened_on'=>date('Y-m-d'),'opening_minor'=>0,'evidence'=>$run],$actors['finance_manager'],$run,true);
 $checkpoint=['action'=>'checkpoint','run_id'=>$id,'revision'=>2,'label'=>$run.' baseline','reason'=>$run,'confirmed'=>'1'];cfCheck(cfRequest($admin,'club_cutover_admin.php',$checkpoint)['status']===303,'Actual database checkpoint captured over HTTP');$q=$pdo->prepare('SELECT * FROM club_cutover_checkpoints WHERE run_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$before=$q->fetch();$snapshot=json_decode($before['snapshot_json'],true,512,JSON_THROW_ON_ERROR);cfCheck(count($snapshot['tables'])>=300,'Capture covers all explicitly owned tables excluding cutover metadata');foreach(['accounting_movements','payments','club_event_notifications','shop_stock_movements','training_closures']as$table)cfCheck(isset($snapshot['tables'][$table]),'Capture includes '.$table);cfCheck($snapshot['code']['git_head']!==null,'Actual local Git HEAD read without command execution');
 $move=accountingLedgerRecord($pdo,['account_id'=>(int)$account['id'],'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>date('Y-m-d'),'source_context'=>'LOCALHOST cutover','source_reference'=>$run,'request_key'=>'cutover-'.bin2hex(random_bytes(8)),'evidence'=>$run],$actors['finance_manager'],$run,true);
 $preview=cfRequest($admin,'club_cutover_admin.php?id='.$id.'&checkpoint='.$before['id'].'&gate=G3');cfCheck($preview['status']===200&&str_contains($preview['body'],'Návrat musí nejprve zachovat nové operace'),'New real synthetic movement blocks simple rollback in actual UI');
 $review=['action'=>'review','run_id'=>$id,'checkpoint_id'=>$before['id'],'gate'=>'G3','reason'=>$run.' preserve delta','confirmed'=>'1','production_authorized'=>'1'];cfCheck(cfRequest($admin,'club_cutover_admin.php',$review)['status']===303,'Immutable blocked gate and post-checkpoint delta saved');$q=$pdo->prepare('SELECT * FROM club_cutover_reviews WHERE run_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$saved=$q->fetch();$report=json_decode($saved['result_json'],true,512,JSON_THROW_ON_ERROR);cfCheck($report['production_authorized']===false&&$report['human_acceptance']==='unknown','Forged production approval input cannot grant authorization');cfCheck(in_array((string)$move['id'],$report['delta']['tables']['accounting_movements']['added'],true),'Exact new canonical movement identified in preserved delta');$q=$pdo->prepare('SELECT * FROM club_cutover_checkpoints WHERE id=?');$q->execute([$before['id']]);cfCheck($q->fetch()===$before,'Original checkpoint remains immutable');cfCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_movements WHERE id='.(int)$move['id'])->fetchColumn()===1,'Preparation never rolls back new movement');
 $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$actor]);cfCheck(cfRequest($admin,'club_cutover_admin.php?id='.$id)['status']===403,'Revoked technical role denied on next request');$result=['status'=>'PASS','scenarios'=>count($results),'owned_tables'=>count($snapshot['tables']),'checks'=>$results];file_put_contents($sandbox.'/r40-r44-http.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
