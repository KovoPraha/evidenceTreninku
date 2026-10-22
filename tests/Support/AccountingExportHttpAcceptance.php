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
$run='LOCALHOST NEPLATIT R13-R17 '.bin2hex(random_bytes(5));
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

try {
    $sessions=[];
    foreach(['finance_manager','coach','registrar','order_operator','system_admin'] as $position){
        $id=cfInsert('treneri',['jmeno'=>$run.' '.$position,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
        cfInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$position,'is_default'=>1]);$sessions[$position]=cfSession($id);
    }
    $finance=$sessions['finance_manager'];$count=(int)$pdo->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn();
    cfCheck(cfRequest($finance,'accounting_exports_admin.php')['status']===200,'Finance can view export workspace');
    foreach(['coach','registrar','order_operator','system_admin'] as $position){cfCheck(cfRequest($sessions[$position],'accounting_exports_admin.php')['status']===403,$position.' cannot access accounting exports');cfCheck(cfRequest($sessions[$position],'accounting_export_download.php?id=1')['status']===403,$position.' cannot download accounting archive directly');}
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn()===$count,'GET and forbidden requests create no batch');
    $post=['period_from'=>date('Y-m-01'),'period_to'=>date('Y-m-d'),'request_key'=>'http-'.bin2hex(random_bytes(16)),'reason'=>$run,'confirm_action'=>'1'];
    cfRequest($finance,'accounting_exports_admin.php',$post+['csrf_token'=>'forged']);
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn()===$count,'Forged CSRF creates no batch');
    cfRequest($finance,'accounting_exports_admin.php',array_replace($post,['confirm_action'=>'0']));
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM accounting_export_batches')->fetchColumn()===$count,'Explicit confirmation is required');
    cfCheck(cfRequest($finance,'accounting_exports_admin.php',$post)['status']===303,'Actual finance POST creates immutable export');
    $q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE request_key=?');$q->execute([$post['request_key']]);$row=$q->fetch();cfCheck(is_array($row)&&$row['status']==='incomplete','Foundation archive cannot masquerade as complete club month');
    $download=cfRequest($finance,'accounting_export_download.php?id='.$row['id']);cfCheck($download['status']===200&&hash_equals($row['archive_sha256'],hash('sha256',$download['body'])),'Actual HTTP ZIP matches immutable stored digest');
    $zipPath=$sandbox.'/r13-r17-http-sample.zip';file_put_contents($zipPath,$download['body']);$zip=new ZipArchive();cfCheck($zip->open($zipPath)===true,'Download is a readable ZIP');
    $manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);$pdfCount=0;
    foreach($manifest['files'] as $entry){$bytes=$zip->getFromName($entry['path']);cfCheck(is_string($bytes)&&strlen($bytes)===$entry['bytes']&&hash_equals($entry['sha256'],hash('sha256',$bytes)),'Archived file integrity: '.$entry['path']);if(str_ends_with($entry['path'],'.pdf'))$pdfCount++;}
    file_put_contents($sandbox.'/r13-r17-http-sample.xlsx',$zip->getFromName('ucetni-podklady.xlsx'));$zip->close();
    $q->execute([$post['request_key']]);$original=$q->fetch();cfRequest($finance,'accounting_exports_admin.php',$post);$q->execute([$post['request_key']]);cfCheck($q->fetch()===$original,'Double submit preserves same export row and archive');
    $revision=array_replace($post,['request_key'=>'http-'.bin2hex(random_bytes(16)),'reason'=>$run.' náhradní revize']);cfCheck(cfRequest($finance,'accounting_exports_admin.php',$revision)['status']===303,'Replacement revision can be generated');
    $q->execute([$revision['request_key']]);$replacement=$q->fetch();cfCheck((int)$replacement['predecessor_id']===(int)$row['id']&&(int)$replacement['revision']===(int)$row['revision']+1,'Revision links its exact predecessor');
    cfCheck(cfRequest($finance,'accounting_export_download.php?id='.$row['id'])['body']===$download['body'],'Old revision download unchanged after replacement');
    $events=$pdo->prepare("SELECT COUNT(*) FROM accounting_export_events WHERE batch_id=? AND action='downloaded'");$events->execute([$row['id']]);cfCheck((int)$events->fetchColumn()===2,'Downloads are independently audited');
    $report=['status'=>'PASS','scenarios'=>count($results),'archived_pdfs'=>$pdfCount,'checks'=>$results];file_put_contents($sandbox.'/r13-r17-export-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
