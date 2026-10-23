<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);require $root.'/config.php';
if(DB_NAME!=='evidence_b1'||DB_HOST!=='127.0.0.1;port=33426'||rtrim((string)APP_BASE_URL,'/')!=='http://127.0.0.1:18086')throw new RuntimeException('Owned synthetic sandbox required.');
$runtime=getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor=json_decode((string)file_get_contents($runtime.'/environment.json'),true,512,JSON_THROW_ON_ERROR);
if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Synthetic-only descriptor required.');
session_save_path($runtime.'/sessions');
require_once $root.'/includes/auth_session.php';require_once $root.'/csrf_helper.php';require_once $root.'/includes/accounting_ledger.php';require_once $root.'/includes/accounting_adjustments.php';
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==33426)throw new RuntimeException('Wrong database port.');
if(!accountingLedgerTable($pdo,'accounting_refund_decisions'))throw new RuntimeException('Apply sandbox migrations first.');
$run='LOCALHOST NEPLATIT R17 '.bin2hex(random_bytes(5));$checks=[];
function ahCheck(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);$GLOBALS['checks'][]=['scenario'=>$label,'status'=>'PASS'];}
function ahInsert(string $table,array $data):int {$s=$GLOBALS['pdo']->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')');$s->execute(array_values($data));return (int)$GLOBALS['pdo']->lastInsertId();}
function ahSession(int $trainer):array {session_id('');app_session_start();$_SESSION=[];auth_session_bind_trainer($trainer,1);auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$trainer);app_session_mark_authenticated();$s=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $s;}
function ahRequest(array &$session,?array $post=null):array {
    $c=curl_init('http://127.0.0.1:18086/accounting_adjustments_admin.php');
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>15,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$session):int {if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$m))$session['cookie']=$m[1];return strlen($line);}]);
    if($post!==null){$post+=['csrf_token'=>$session['csrf']];curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post));}
    $body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$err=curl_error($c);curl_close($c);
    if(!is_string($body)||$status>=500||$status===0)throw new RuntimeException('HTTP failed '.$status.' '.$err);
    return ['status'=>$status,'body'=>$body];
}
foreach(['finance_manager','coach'] as $role){$t=ahInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);ahInsert('staff_user_positions',['trainer_id'=>$t,'position_code'=>$role,'is_default'=>1]);$actors[$role]=$t;$sessions[$role]=ahSession($t);}
$actor=$actors['finance_manager'];$session=$sessions['finance_manager'];$coach=$sessions['coach'];
$account=accountingLedgerCreateAccount($pdo,['code'=>'R17-'.bin2hex(random_bytes(6)),'name'=>$run,'kind'=>'bank','currency'=>'CZK','opened_on'=>'2026-01-01','opening_minor'=>0,'evidence'=>$run],$actor,$run,true);
$income=accountingLedgerRecord($pdo,['account_id'=>(int)$account['id'],'kind'=>'income','amount_minor'=>10000,'currency'=>'CZK','effective_on'=>'2026-01-10','source_context'=>'LOCALHOST-r17','source_reference'=>bin2hex(random_bytes(8)),'request_key'=>bin2hex(random_bytes(12)),'evidence'=>$run],$actor,$run,true);
$base=['original_movement_id'=>$income['id'],'amount'=>'40.00','decided_on'=>'2026-01-11','request_key'=>bin2hex(random_bytes(12)),'reason'=>$run,'confirmed'=>'1','action'=>'decide'];
$count=static fn():int=>(int)$pdo->query('SELECT COUNT(*) FROM accounting_refund_decisions')->fetchColumn();$before=$count();
ahCheck(ahRequest($session)['status']===200 && $count()===$before,'Finance GET renders and does not create decisions');
ahCheck(ahRequest($coach,$base)['status']===403 && $count()===$before,'Coach cannot view or mutate refunds');
ahCheck(ahRequest($session,['csrf_token'=>'forged']+$base)['status']===403 && $count()===$before,'Forged CSRF rejected without write');
ahCheck(ahRequest($session,['confirmed'=>'0']+$base)['status']===422 && $count()===$before,'Explicit confirmation required');
ahCheck(ahRequest($session,['decided_on'=>'2999-01-01']+$base)['status']===422 && $count()===$before,'Future decision date rejected');
$r=ahRequest($session,$base);ahCheck($r['status']===200 && str_contains($r['body'],'Rozhodnutí zaznamenáno') && $count()===$before+1,'Decision created through HTTP without money movement');
$s=$pdo->prepare('SELECT * FROM accounting_refund_decisions WHERE request_key=?');$s->execute([$base['request_key']]);$decision=$s->fetch();
ahCheck(accountingAdjustmentBalance($pdo,(int)$income['id'])['reserved_minor']===4000,'Decision reserves exactly 40 CZK');
ahCheck(ahRequest($session,$base)['status']===200 && $count()===$before+1,'Decision double submit is idempotent');
ahCheck(ahRequest($session,['amount'=>'45.00']+$base)['status']===422,'Replay with changed amount rejected');
$pay=['action'=>'pay','decision_id'=>$decision['id'],'account_id'=>$account['id'],'amount'=>'15.00','effective_on'=>'2026-01-12','source_reference'=>'R17-'.bin2hex(random_bytes(10)),'evidence'=>$run,'request_key'=>bin2hex(random_bytes(12)),'reason'=>$run,'confirmed'=>'1'];
ahCheck(ahRequest($session,$pay)['status']===200,'Partial actual payout through HTTP succeeds');
ahCheck(ahRequest($session,$pay)['status']===200 && accountingAdjustmentBalance($pdo,(int)$income['id'])['refunded_minor']===1500,'Payout replay creates no second outgoing movement');
ahCheck(ahRequest($session,['amount'=>'30.00','request_key'=>bin2hex(random_bytes(12)),'source_reference'=>'R17-over']+$pay)['status']===422,'Excess payout rejected');
ahCheck(ahRequest($session,['action'=>'cancel','decision_id'=>$decision['id'],'request_key'=>bin2hex(random_bytes(12)),'reason'=>$run,'confirmed'=>'1'])['status']===422,'Partially paid decision cannot be cancelled');
$pay2=['amount'=>'25.00','request_key'=>bin2hex(random_bytes(12)),'source_reference'=>'R17-'.bin2hex(random_bytes(10))]+$pay;
ahCheck(ahRequest($session,$pay2)['status']===200,'Second partial payout completes the decision');
$b=accountingAdjustmentBalance($pdo,(int)$income['id']);ahCheck($b['refunded_minor']===4000&&$b['reserved_minor']===0&&$b['available_minor']===6000,'Completed payout balances money, reservation and free amount');
$second=['amount'=>'10.00','request_key'=>bin2hex(random_bytes(12))]+$base;ahCheck(ahRequest($session,$second)['status']===200,'Independent overpayment decision created');$s->execute([$second['request_key']]);$decision2=$s->fetch();
ahCheck(ahRequest($session,['action'=>'cancel','decision_id'=>$decision2['id'],'request_key'=>bin2hex(random_bytes(12)),'reason'=>$run,'confirmed'=>'1'])['status']===200 && accountingAdjustmentBalance($pdo,(int)$income['id'])['available_minor']===6000,'Unpaid decision cancellation releases only reservation');
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'")->execute([$actor]);
ahCheck(ahRequest($session,['request_key'=>bin2hex(random_bytes(12))]+$base)['status']===403,'Revoked finance role rejected on next HTTP request');
echo json_encode(['run'=>$run,'checks'=>count($checks),'results'=>$checks,'production_mutations'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
