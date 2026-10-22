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
$run='LOCALHOST NEPLATIT R35-R36 '.bin2hex(random_bytes(5));
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

require_once $app.'/includes/accounting_export.php';require_once $app.'/vendor/autoload.php';
$descriptor=json_decode(file_get_contents($sandbox.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(empty($descriptor['synthetic_only'])||strtolower(str_replace('\\','/',rtrim((string)$pdo->query('SELECT @@datadir')->fetchColumn(),'/\\')))!==strtolower(str_replace('\\','/',$sandbox.'/mysql')))throw new RuntimeException('Unowned database.');
try{
 $sessions=[];$actors=[];
 foreach(['finance_manager','coach','registrar']as$position){$id=cfInsert('treneri',['jmeno'=>$run.' '.$position,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);cfInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$position,'is_default'=>1]);$sessions[$position]=cfSession($id);$actors[$position]=$id;}
 $finance=$sessions['finance_manager'];$count=(int)$pdo->query('SELECT COUNT(*) FROM financial_decisions')->fetchColumn();
 cfCheck(cfRequest($finance,'financial_decisions_admin.php')['status']===200,'Finance decision workspace available');
 foreach(['coach','registrar']as$role){cfCheck(cfRequest($sessions[$role],'financial_decisions_admin.php')['status']===403,$role.' cannot read financial decisions');cfCheck(cfRequest($sessions[$role],'financial_decision_file.php?id=1')['status']===403,$role.' cannot read private decision directly');}
 $input=['title'=>$run,'kind'=>'rates','state'=>'confirmed','valid_from'=>date('Y-m-01'),'valid_to'=>date('Y-m-t'),'source_reference'=>$run.' skutečné testovací rozhodnutí','decision_text'=>'Žluťoučký test sazby; neplatné pro produkci.','original_location'=>$run.' šanon','reason'=>$run,'confirmed'=>'1'];
 cfCheck(cfRequest($finance,'financial_decisions_admin.php',$input+['csrf_token'=>'forged'])['status']===403,'Forged CSRF rejected');cfCheck((int)$pdo->query('SELECT COUNT(*) FROM financial_decisions')->fetchColumn()===$count,'Rejected write created no decision');
 cfCheck(cfRequest($finance,'financial_decisions_admin.php',array_replace($input,['confirmed'=>'']))['status']===422,'Explicit confirmation required');
 $pdf=new \Dompdf\Dompdf();$pdf->loadHtml('<html><meta charset="utf-8"><h1>LOCALHOST TEST</h1></html>');$pdf->render();$file=$sandbox.'/r35-decision-upload.pdf';file_put_contents($file,$pdf->output());
 $uploaded=$input+['evidence'=>new CURLFile($file,'application/pdf','decision.pdf')];cfCheck(cfRequest($finance,'financial_decisions_admin.php',$uploaded)['status']===303,'Actual multipart POST creates immutable decision with private proof');
 $q=$pdo->prepare('SELECT * FROM financial_decisions WHERE title=? ORDER BY id DESC LIMIT 1');$q->execute([$run]);$decision=$q->fetch();$old=$decision;
 $download=cfRequest($finance,'financial_decision_file.php?id='.$decision['id']);cfCheck($download['status']===200&&hash_equals($decision['file_sha256'],hash('sha256',$download['body'])),'Authorized proof download hash verified');
 cfCheck(cfRequest($finance,'financial_decisions_admin.php',array_replace($input,['replaces_id'=>$decision['id'],'decision_text'=>'Nová verze TEST']))['status']===303,'Correction creates replacement version');
 $q2=$pdo->prepare('SELECT * FROM financial_decisions WHERE id=?');$q2->execute([$old['id']]);cfCheck($q2->fetch()===$old,'Old decision and private attachment immutable');
 cfCheck(cfRequest($finance,'financial_decisions_admin.php',$input+['replaces_id'=>$old['id']])['status']===422,'Stale replacement rejected');
 $post=['period_from'=>date('Y-m-01'),'period_to'=>date('Y-m-t'),'request_key'=>'club-export-'.bin2hex(random_bytes(12)),'reason'=>$run,'confirm_action'=>'1'];
 cfCheck(cfRequest($finance,'accounting_exports_admin.php',$post)['status']===303,'Full club export created over HTTP');$q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE request_key=?');$q->execute([$post['request_key']]);$batch=$q->fetch();$snapshot=json_decode($batch['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
 cfCheck($batch['scope_code']==='r13-r36'&&$batch['status']==='incomplete','Full technical scope does not invent accountant approval');
 foreach(['club_expenses','club_advances','club_reward_claims','club_decisions','club_receivables']as$key)cfCheck(array_key_exists($key,$snapshot),'Snapshot contains '.$key);
 $archive=cfRequest($finance,'accounting_export_download.php?id='.$batch['id']);cfCheck(hash_equals($batch['archive_sha256'],hash('sha256',$archive['body'])),'ZIP download matches immutable stored hash');$zipfile=$sandbox.'/r35-r36-http-sample.zip';file_put_contents($zipfile,$archive['body']);$zip=new ZipArchive();cfCheck($zip->open($zipfile)===true,'ZIP readable');$manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);$integrity=0;foreach($manifest['files']as$entry){$bytes=$zip->getFromName($entry['path']);if(!is_string($bytes)||strlen($bytes)!==$entry['bytes']||!hash_equals($entry['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Broken archived file '.$entry['path']);$integrity++;}cfCheck($integrity>=4,'Every manifest entry hash verified');cfCheck(str_starts_with($zip->getFromName('klubove-vyporadani.pdf'),'%PDF-'),'Generated club obligation PDF included');cfCheck($zip->getFromName('rozhodnuti/R-'.$old['id'].'.pdf')===$download['body'],'Original decision PDF included unchanged');file_put_contents($sandbox.'/r35-r36-http-sample.pdf',$zip->getFromName('klubove-vyporadani.pdf'));$xlsx=$sandbox.'/r35-r36-http-sample.xlsx';file_put_contents($xlsx,$zip->getFromName('ucetni-podklady.xlsx'));$zip->close();$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($xlsx);cfCheck(count($book->getAllSheets())===10,'Ten-sheet accountant contract preserved');cfCheck($book->getSheetByName('Vydaje')->getCell('A1')->getValue()==='Druh','Expense sheet connected instead of placeholder');$book->disconnectWorksheets();
 $handover=['action'=>'handover','batch_id'=>$batch['id'],'expected_event'=>0,'handover_action'=>'handed_over','reason'=>$run.' osobní předání','confirm_action'=>'1'];cfCheck(cfRequest($finance,'accounting_exports_admin.php',$handover)['status']===303,'Personal handover recorded without external sending');
 $event=(int)$pdo->query("SELECT MAX(id) FROM accounting_export_events WHERE batch_id=".(int)$batch['id']." AND action='handed_over'")->fetchColumn();$bad=array_replace($handover,['expected_event'=>0,'handover_action'=>'accepted']);cfRequest($finance,'accounting_exports_admin.php',$bad);cfCheck((int)$pdo->query("SELECT COUNT(*) FROM accounting_export_events WHERE batch_id=".(int)$batch['id']." AND action='accepted'")->fetchColumn()===0,'Stale handover cannot accept batch');
 cfCheck(cfRequest($finance,'accounting_exports_admin.php',array_replace($handover,['expected_event'=>$event,'handover_action'=>'accepted']))['status']===303,'Actual accountant acceptance recorded separately');cfCheck(cfRequest($finance,'accounting_export_download.php?id='.$batch['id'])['body']===$archive['body'],'Handover does not rewrite historical ZIP');
 $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$actors['finance_manager']]);cfCheck(cfRequest($finance,'financial_decision_file.php?id='.$old['id'])['status']===403,'Revoked finance role loses proof access on next request');cfCheck(cfRequest($finance,'accounting_export_download.php?id='.$batch['id'])['status']===403,'Revoked finance role loses export access');
 $report=['status'=>'PASS','scenarios'=>count($results),'archive_files'=>$integrity,'checks'=>$results];file_put_contents($sandbox.'/r35-r36-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
