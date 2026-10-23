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
$run='LOCALHOST NEPLATIT R18-R24 '.bin2hex(random_bytes(5));
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

require_once $app.'/includes/shop_manual_catalog.php';
require_once $app.'/includes/accounting_export.php';
require_once $app.'/includes/shop_stock_documents.php';
try {
 $sessions=[];$actors=[];
 foreach(['catalog_manager','finance_manager','coach'] as $position){$id=cfInsert('treneri',['jmeno'=>$run.' '.$position,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);cfInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$position,'is_default'=>1]);$sessions[$position]=cfSession($id);$actors[$position]=$id;}
 $catalog=$sessions['catalog_manager'];$finance=$sessions['finance_manager'];$actor=$actors['catalog_manager'];
 $made=shopManualCatalogCreate($pdo,$actor,['name'=>$run.' Dres Žluťoučký','offer_type'=>'goods','item_type'=>'product','visibility'=>'visible'],['sku'=>'KP-LOCALHOST-'.strtoupper(bin2hex(random_bytes(5))),'amount_minor'=>10000,'unit_code'=>'piece','visible'=>0],$run,true);
 $movement=shopInventoryOpen($pdo,$actor,(int)$made['variant_id'],['physical_quantity'=>2,'blocked_quantity'=>0,'unit_cost_minor'=>null,'effective_on'=>'2025-09-30','evidence'=>$run],$run,true,'pdf-open-'.bin2hex(random_bytes(8)));$id=(int)$movement['id'];
 $post=['id'=>$id,'reason'=>$run,'confirmed'=>'1'];
 cfCheck(cfRequest($catalog,'shop_stock_document.php?id='.$id)['status']===200,'Stock slip preview is accessible');
 $count=(int)$pdo->query('SELECT COUNT(*) FROM shop_stock_documents')->fetchColumn();cfRequest($catalog,'shop_stock_document.php',$post+['csrf_token'=>'forged']);cfCheck($count===(int)$pdo->query('SELECT COUNT(*) FROM shop_stock_documents')->fetchColumn(),'Forged CSRF archives no PDF');
 cfRequest($catalog,'shop_stock_document.php',array_replace($post,['confirmed'=>'0']));cfCheck($count===(int)$pdo->query('SELECT COUNT(*) FROM shop_stock_documents')->fetchColumn(),'PDF requires explicit confirmation');
 cfCheck(cfRequest($catalog,'shop_stock_document.php',$post)['status']===303,'POST archives actual immutable PDF');
 $download=cfRequest($catalog,'shop_stock_document.php?id='.$id.'&download=1');cfCheck($download['status']===200&&str_starts_with($download['body'],'%PDF-'),'Actual HTTP downloads PDF');file_put_contents($sandbox.'/r18-r24-stock-sample.pdf',$download['body']);
 cfCheck(cfRequest($sessions['coach'],'shop_stock_document.php?id='.$id.'&download=1')['status']===403,'Coach cannot download stock PDF');
 $exportPost=['period_from'=>'2025-09-01','period_to'=>'2025-09-30','request_key'=>'stock-export-'.bin2hex(random_bytes(8)),'reason'=>$run,'confirm_action'=>'1'];
 cfCheck(cfRequest($finance,'accounting_exports_admin.php',$exportPost)['status']===303,'Finance creates historical stock export over HTTP');
 $q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE request_key=?');$q->execute([$exportPost['request_key']]);$first=$q->fetch();$snapshot=json_decode($first['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$stock=array_column($snapshot['stock_balances'],null,'id');cfCheck($stock[$made['variant_id']]['closing']['physical']===2&&$stock[$made['variant_id']]['closing']['value_minor']===null,'Historical export retains unknown acquisition cost');
 $zipResponse=cfRequest($finance,'accounting_export_download.php?id='.$first['id']);file_put_contents($sandbox.'/r18-r24-stock-export.zip',$zipResponse['body']);$z=new ZipArchive();cfCheck($z->open($sandbox.'/r18-r24-stock-export.zip')===true,'Export ZIP opens');cfCheck($z->getFromName('sklad/SK-'.$id.'.pdf')===$download['body'],'Export bundles exact archived stock slip');file_put_contents($sandbox.'/r18-r24-stock-export.xlsx',$z->getFromName('ucetni-podklady.xlsx'));$z->close();
 $q=$pdo->prepare('SELECT id FROM shop_stock_lots WHERE source_movement_id=?');$q->execute([$id]);$lot=(int)$q->fetchColumn();shopInventoryCorrectCost($pdo,$actors['finance_manager'],$lot,12345,'2025-09-30',$run,$run,true,'stock-cost-'.bin2hex(random_bytes(8)));
 $exportPost['request_key']='stock-export-'.bin2hex(random_bytes(8));cfCheck(cfRequest($finance,'accounting_exports_admin.php',$exportPost)['status']===303,'Audited backdated price produces replacement export revision');
 $q=$pdo->prepare('SELECT * FROM accounting_export_batches WHERE request_key=?');$q->execute([$exportPost['request_key']]);$second=$q->fetch();$snapshot=json_decode($second['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$stock=array_column($snapshot['stock_balances'],null,'id');cfCheck($stock[$made['variant_id']]['closing']['value_minor']===24690,'Replacement snapshot values original physical stock exactly');cfCheck(in_array('stock_cost_revisions',array_column($snapshot['changes'],'section'),true),'Revision explicitly lists acquisition cost correction');
 cfCheck(cfRequest($finance,'accounting_export_download.php?id='.$first['id'])['body']===$zipResponse['body'],'Old export bytes remain unchanged after cost revision');cfCheck(cfRequest($catalog,'shop_stock_document.php?id='.$id.'&download=1')['body']===$download['body'],'Original physical PDF bytes remain unchanged');
 cfRequest($catalog,'shop_stock_document.php',$post);cfCheck($count+1===(int)$pdo->query('SELECT COUNT(*) FROM shop_stock_documents')->fetchColumn(),'Double PDF submission creates no second document');
 $report=['status'=>'PASS','scenarios'=>count($results),'checks'=>$results,'pdf_sample'=>$sandbox.'/r18-r24-stock-sample.pdf'];file_put_contents($sandbox.'/r18-r24-stock-export-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
