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
$identity=$pdo->query('SELECT @@datadir')->fetchColumn();if(strtolower(str_replace('\\','/',$identity))!==strtolower(str_replace('\\','/',$sandbox.'/mysql/')))throw new RuntimeException('Wrong datadir.');
$descriptor=json_decode(file_get_contents($sandbox.'/environment.json'),true,512,JSON_THROW_ON_ERROR);if(($descriptor['synthetic_only']??false)!==true||($descriptor['external_sends_disabled']??false)!==true)throw new RuntimeException('Synthetic descriptor required.');
$run='LOCALHOST NEPLATIT R31-R32 '.bin2hex(random_bytes(5));
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

require_once $app.'/includes/club_expenses.php';
try {
 $sessions=[];$actors=[];foreach(['finance_one'=>'finance_manager','finance_two'=>'finance_manager','coach'=>'coach'] as $key=>$position){$id=cfInsert('treneri',['jmeno'=>$run.' '.$key,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);cfInsert('staff_user_positions',['trainer_id'=>$id,'position_code'=>$position,'is_default'=>1]);$sessions[$key]=cfSession($id);$actors[$key]=$id;}
 $f=$sessions['finance_one'];$g=$sessions['finance_two'];$common=['reason'=>$run,'confirmed'=>'1'];$route='club_expenses_admin.php';
 cfCheck(cfRequest($f,$route)['status']===200,'Finance dashboard loads');cfCheck(cfRequest($sessions['coach'],$route)['status']===403,'Coach cannot read global financial evidence');cfCheck(cfRequest($f,$route,['action'=>'case','csrf_token'=>'forged']+$common)['status']===403,'Forged CSRF rejected');
 $case=['action'=>'case','title'=>$run,'leader_trainer_id'=>$actors['coach'],'due_on'=>date('Y-m-d'),'evidence'=>$run]+$common;cfCheck(cfRequest($f,$route,$case)['status']===303,'Case created through HTTP');$q=$pdo->prepare('SELECT id FROM club_expense_cases WHERE title=? ORDER BY id DESC');$q->execute([$run]);$caseId=(int)$q->fetchColumn();
 $claim=['action'=>'claim','request_key'=>$run.'-claim','case_id'=>$caseId,'supplier'=>$run,'document_number'=>$run.'-doc','document_on'=>date('Y-m-d'),'due_on'=>date('Y-m-d'),'amount'=>'1200','original_currency'=>'CZK','funding_source'=>'personal','payer_kind'=>'trainer','payer_id'=>$actors['coach'],'payer_label'=>$run.' coach','creditor_kind'=>'trainer','creditor_id'=>$actors['coach'],'creditor_label'=>$run.' coach','payee_evidence'=>$run.' signed cash','purpose'=>$run.' transport','document_evidence'=>$run.' paper A','items'=>'1000;ano;'.$caseId.';club;Transport'."\n".'200;ne;'.$caseId.';personal;Personal part']+$common;
 cfCheck(cfRequest($f,$route,$claim)['status']===303,'Expense split accepted 1000 of 1200 through HTTP');$q=$pdo->prepare('SELECT * FROM club_expense_claims WHERE request_key=?');$q->execute([$claim['request_key']]);$c=$q->fetch();$root=(int)$c['root_id'];
 cfCheck(cfRequest($f,$route,$claim)['status']===303,'Identical claim retry idempotent');cfCheck(cfRequest($f,$route,['action'=>'claim_state','id'=>$c['id'],'transition'=>'submit']+$common)['status']===303,'Expense submitted');cfCheck(cfRequest($f,$route,['action'=>'claim_state','id'=>$c['id'],'transition'=>'approve']+$common)['status']===422,'Self approval rejected');cfCheck(cfRequest($g,$route,['action'=>'claim_state','id'=>$c['id'],'transition'=>'approve']+$common)['status']===303,'Independent approval accepted');
 $account=accountingLedgerCreateAccount($pdo,['code'=>'R31-'.bin2hex(random_bytes(5)),'name'=>$run,'kind'=>'cash','opened_on'=>date('Y-m-d'),'opening_minor'=>1000000,'evidence'=>$run],$actors['finance_one'],$run,true);
 $money=function(string $amount,string $key)use($f,$account,$common,$run,$pdo):int{$request=$run.'-'.$key;$r=cfRequest($f,'accounting_ledger_admin.php',['action'=>'movement','account_id'=>$account['id'],'kind'=>str_starts_with($amount,'-')?'expense':'income','amount'=>$amount,'effective_on'=>date('Y-m-d'),'evidence'=>$run.' actual receipt','reference'=>$request,'request_key'=>$request]+$common);cfCheck($r['status']===302,'Actual ledger '.$key.' through HTTP');$q=$pdo->prepare('SELECT id FROM accounting_movements WHERE request_key=?');$q->execute([$request]);return(int)$q->fetchColumn();};
 $m=$money('-600','pay');$alloc=['action'=>'allocate','case_type'=>'expense_claim','id'=>$root,'movement_id'=>$m,'amount'=>'600','effective_on'=>date('Y-m-d'),'request_key'=>$run.'-alloc']+$common;cfCheck(cfRequest($f,$route,$alloc)['status']===303,'Partial payment allocation through HTTP');cfCheck(clubExpenseClaimBalance($pdo,$root)['remaining_minor']===40000,'Partial payment leaves 400 CZK');cfCheck(cfRequest($f,$route,$alloc)['status']===303,'Repeated payment allocation creates no duplicate');
 $correction=array_replace($claim,['previous_id'=>$c['id'],'request_key'=>$run.'-v2','items'=>'400;ano;'.$caseId.';club;Corrected scope'."\n".'800;ne;'.$caseId.';personal;Rejected scope']);cfCheck(cfRequest($f,$route,$correction)['status']===303,'Downward correction creates new immutable version');$q=$pdo->prepare('SELECT id FROM club_expense_claims WHERE request_key=?');$q->execute([$correction['request_key']]);$v=(int)$q->fetchColumn();cfCheck(cfRequest($f,$route,['action'=>'claim_state','id'=>$v,'transition'=>'submit']+$common)['status']===303,'Correction submitted');cfCheck(cfRequest($g,$route,['action'=>'claim_state','id'=>$v,'transition'=>'approve']+$common)['status']===303,'Correction independently approved');cfCheck(clubExpenseClaimBalance($pdo,$root)['remaining_minor']===-20000,'Lower approved amount creates real receivable of 200 CZK');
 cfCheck(cfRequest($f,$route,['action'=>'close_case','id'=>$caseId]+$common)['status']===422,'Unreturned expense overpayment prevents case closure');$back=$money('200','return');cfCheck(cfRequest($f,$route,array_replace($alloc,['case_type'=>'expense_claim_return','movement_id'=>$back,'amount'=>'200','request_key'=>$run.'-refund']))['status']===303,'Actual income settles expense overpayment');cfCheck(clubExpenseClaimBalance($pdo,$root)['remaining_minor']===0,'Receivable clears only after actual income');
 $advance=['action'=>'advance','case_id'=>$caseId,'recipient_kind'=>'trainer','recipient_id'=>$actors['coach'],'recipient_label'=>$run.' coach','payee_evidence'=>$run.' handoff','amount'=>'5000','due_on'=>date('Y-m-d'),'purpose'=>$run.' camp','request_key'=>$run.'-advance']+$common;cfCheck(cfRequest($f,$route,$advance)['status']===303,'Advance requested through HTTP');$q=$pdo->prepare('SELECT id FROM club_expense_advances WHERE request_key=?');$q->execute([$advance['request_key']]);$a=(int)$q->fetchColumn();cfCheck(cfRequest($g,$route,['action'=>'advance_state','id'=>$a,'transition'=>'approve']+$common)['status']===303,'Advance independently approved');cfCheck(clubExpenseAdvanceBalance($pdo,$a)['out_minor']===0,'Approval alone creates no money');$out=$money('-5000','advance-out');cfCheck(cfRequest($f,$route,array_replace($alloc,['case_type'=>'expense_advance_out','id'=>$a,'movement_id'=>$out,'amount'=>'5000','request_key'=>$run.'-handoff']))['status']===303,'Actual advance handoff linked once');
 $spent=array_replace($claim,['request_key'=>$run.'-spent','document_number'=>$run.'-receipt','amount'=>'4200','funding_source'=>'advance','items'=>'4200;ano;'.$caseId.';club;Camp costs']);cfCheck(cfRequest($f,$route,$spent)['status']===303,'Advance expense recorded');$q=$pdo->prepare('SELECT id FROM club_expense_claims WHERE request_key=?');$q->execute([$spent['request_key']]);$c2=(int)$q->fetchColumn();cfCheck(cfRequest($f,$route,['action'=>'claim_state','id'=>$c2,'transition'=>'submit']+$common)['status']===303,'Advance expense submitted');cfCheck(cfRequest($g,$route,['action'=>'claim_state','id'=>$c2,'transition'=>'approve']+$common)['status']===303,'Advance expense approved');cfCheck(cfRequest($f,$route,['action'=>'consume','id'=>$c2,'advance_id'=>$a,'amount'=>'4200','request_key'=>$run.'-consume','effective_on'=>date('Y-m-d')]+$common)['status']===303,'Actual approved receipt consumes correct advance');cfCheck(clubExpenseAdvanceBalance($pdo,$a)['balance_minor']===80000,'Advance leaves 800 CZK to return');cfCheck(cfRequest($f,$route,['action'=>'advance_state','id'=>$a,'transition'=>'close']+$common)['status']===422,'Unsettled advance cannot close');foreach(['500','300'] as $n){$m=$money($n,'advance-return-'.$n);cfCheck(cfRequest($f,$route,array_replace($alloc,['case_type'=>'expense_advance_in','id'=>$a,'movement_id'=>$m,'amount'=>$n,'request_key'=>$run.'-advance-return-'.$n]))['status']===303,'Advance actual return '.$n.' linked');}
 cfCheck(cfRequest($f,$route,['action'=>'advance_state','id'=>$a,'transition'=>'close']+$common)['status']===303,'Zero balance advance closes');cfCheck(cfRequest($f,$route,['action'=>'close_case','id'=>$caseId]+$common)['status']===303,'Fully settled financial case closes');cfCheck(cfRequest($f,$route,array_replace($claim,['request_key'=>$run.'-late','document_number'=>'late']))['status']===422,'Late addition to closed case rejected');
 $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$actors['finance_one']]);cfCheck(cfRequest($f,$route)['status']===403,'Revoked finance role fails on next HTTP request');
 $report=['status'=>'PASS','scenarios'=>count($results),'checks'=>$results,'production_mutations'=>false];file_put_contents($sandbox.'/r31-r32-expenses-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
