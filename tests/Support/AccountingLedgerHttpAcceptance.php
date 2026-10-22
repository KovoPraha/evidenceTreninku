<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);require $root.'/config.php';
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086')throw new RuntimeException('Owned synthetic sandbox required.');
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Synthetic descriptor required.');
session_save_path($runtime.'/sessions');require_once $root.'/includes/auth_session.php';require_once $root.'/csrf_helper.php';require_once $root.'/includes/accounting_ledger.php';
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==33426)throw new RuntimeException('Wrong database port.');
$run='LOCALHOST NEPLATIT R13 '.bin2hex(random_bytes(5));$checks=[];
function alhCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);$GLOBALS['checks'][]=['scenario'=>$label,'status'=>'PASS'];}
function alhInsert(string $table,array $data):int{$q=$GLOBALS['pdo']->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')');$q->execute(array_values($data));return (int)$GLOBALS['pdo']->lastInsertId();}
function alhSession(int $trainer):array{session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($trainer,1);auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$trainer);app_session_mark_authenticated();$s=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $s;}
function alhRequest(array &$session,?array $post=null):array{
    $c=curl_init('http://127.0.0.1:18086/accounting_ledger_admin.php');curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$session):int{if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$m))$session['cookie']=$m[1];return strlen($line);}]);
    if($post!==null){$post+=['csrf_token'=>$session['csrf']];curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}$body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$error=curl_error($c);curl_close($c);if(!is_string($body)||$status===0||$status>=500)throw new RuntimeException('HTTP failure '.$status.' '.$error.' '.substr((string)$body,0,600));return ['status'=>$status,'body'=>$body];
}
foreach(['finance_manager','coach'] as $role){$id=alhInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);alhInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$role,'is_default'=>1]);$actors[$role]=$id;$sessions[$role]=alhSession($id);}
$finance=$sessions['finance_manager'];$coach=$sessions['coach'];$actor=$actors['finance_manager'];
$count=static fn(string $table):int=>(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();$accountsBefore=$count('accounting_accounts');$code='R13-'.bin2hex(random_bytes(6));
$base=['reason'=>$run,'confirmed'=>'1','request_key'=>bin2hex(random_bytes(16))];
$account=['action'=>'account','code'=>$code,'name'=>$run,'kind'=>'cash','effective_on'=>'2026-01-01','amount'=>'500,00','evidence'=>$run]+$base;
alhCheck(alhRequest($finance)['status']===200&&$count('accounting_accounts')===$accountsBefore,'Finance GET is read-only');
alhCheck(alhRequest($coach,$account)['status']===403&&$count('accounting_accounts')===$accountsBefore,'Coach direct account write denied');
alhCheck(alhRequest($finance,['csrf_token'=>'forged']+$account)['status']===403&&$count('accounting_accounts')===$accountsBefore,'Bad CSRF has no account write');
alhCheck(alhRequest($finance,['confirmed'=>'0']+$account)['status']===422&&$count('accounting_accounts')===$accountsBefore,'Account opening requires explicit confirmation');
alhCheck(alhRequest($finance,$account)['status']===302&&$count('accounting_accounts')===$accountsBefore+1,'Opening cash count recorded through actual HTTP');
$q=$pdo->prepare('SELECT id FROM accounting_accounts WHERE code=?');$q->execute([$code]);$accountId=(int)$q->fetchColumn();
$move=['action'=>'movement','account_id'=>$accountId,'kind'=>'income','amount'=>'100,01','effective_on'=>'2026-01-10','evidence'=>$run,'reference'=>'R13-'.bin2hex(random_bytes(8))]+$base;
$before=$count('accounting_movements');alhCheck(alhRequest($finance,['amount'=>'1e3']+$move)['status']===422&&$count('accounting_movements')===$before,'Scientific or malformed monetary input rejected');
alhCheck(alhRequest($finance,$move)['status']===302&&$count('accounting_movements')===$before+1,'Actual cash receipt recorded in minor units');
alhCheck(alhRequest($finance,$move)['status']===302&&$count('accounting_movements')===$before+1,'Repeated receipt POST is idempotent');
alhCheck(alhRequest($finance,['amount'=>'100,02']+$move)['status']===422&&$count('accounting_movements')===$before+1,'Same receipt request cannot overwrite amount');
$q=$pdo->prepare('SELECT * FROM accounting_movements WHERE request_key=?');$q->execute([$base['request_key']]);$movement=$q->fetch();alhCheck((int)$movement['amount_minor']===10001,'100.01 CZK stored exactly as 10001 minor units');
$rule=alhInsert('accounting_rules',['category_code'=>'R13-TEST','activity'=>'LOCALHOST','title'=>$run,'source_type'=>'movement','document_kind'=>'cash_receipt','valid_from'=>'2026-01-01','approved_by'=>$actor,'approval_evidence'=>$run]);
$classify=['action'=>'classify','movement_id'=>$movement['id'],'splits'=>$rule.';100,01','request_key'=>bin2hex(random_bytes(16))]+$base;$classBefore=$count('accounting_classifications');
alhCheck(alhRequest($finance,['splits'=>$rule.';100,00']+$classify)['status']===422&&$count('accounting_classifications')===$classBefore,'Accounting split mismatch prevents save');
alhCheck(alhRequest($finance,$classify)['status']===302&&$count('accounting_classifications')===$classBefore+1,'Approved accounting classification independent from allocation');
$closing=['action'=>'count','account_id'=>$accountId,'effective_on'=>'2026-01-10','evidence'=>$run,'amount'=>'600,00','close'=>'1','request_key'=>bin2hex(random_bytes(16))]+$base;$recBefore=$count('accounting_reconciliations');
alhCheck(alhRequest($finance,$closing)['status']===422&&$count('accounting_reconciliations')===$recBefore,'One-cent physical difference blocks account closure');
alhCheck(alhRequest($finance,['amount'=>'600,01']+$closing)['status']===302&&$count('accounting_reconciliations')===$recBefore+1,'Balanced physical count closes account through HTTP');
$late=['request_key'=>bin2hex(random_bytes(16)),'reference'=>'R13-late-'.bin2hex(random_bytes(8))]+$move;alhCheck(alhRequest($finance,$late)['status']===422&&$count('accounting_movements')===$before+1,'Closed cash evidence refuses late receipt');
alhCheck(alhRequest($finance,['request_key'=>bin2hex(random_bytes(16))]+$classify)['status']===422&&$count('accounting_classifications')===$classBefore+1,'Closed classification cannot be rewritten');
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'")->execute([$actor]);
alhCheck(alhRequest($finance,['effective_on'=>'2026-01-11']+$late)['status']===403&&$count('accounting_movements')===$before+1,'Revoked finance session cannot write money');
echo json_encode(['run'=>$run,'checks'=>count($checks),'results'=>$checks,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
