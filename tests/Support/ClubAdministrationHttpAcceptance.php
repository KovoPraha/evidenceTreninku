<?php
declare(strict_types=1);
// Synthetic fixtures only. Never point this script at another environment.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root.'/config.php';
if (DB_NAME !== 'evidence_b1' || DB_HOST !== '127.0.0.1;port=33426' || APP_BASE_URL !== 'http://127.0.0.1:18086') {
    throw new RuntimeException('Owned B1 sandbox required.');
}
$runtime = getenv('LOCALAPPDATA').'/EvidencePavelB1/finance-b1-20261022';
$descriptor = json_decode((string)file_get_contents($runtime.'/environment.json'), true, 512, JSON_THROW_ON_ERROR);
if (($descriptor['synthetic_only'] ?? false) !== true || ($descriptor['external_sends_disabled'] ?? false) !== true) {
    throw new RuntimeException('Synthetic-only descriptor required.');
}
if (realpath((string)($descriptor['application_root'] ?? '')) !== realpath($root) || trim((string)file_get_contents($runtime.'/owner.txt')) !== $root) {
    throw new RuntimeException('Sandbox belongs to a different checkout.');
}
session_save_path($runtime.'/sessions');
require_once $root.'/includes/auth_session.php';
require_once $root.'/csrf_helper.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$identity = $pdo->query('SELECT @@port port,@@datadir path')->fetch();
if ((int)$identity['port'] !== 33426 || strtolower(str_replace('\\','/',$identity['path'])) !== strtolower(str_replace('\\','/',$runtime.'/mysql/'))) {
    throw new RuntimeException('Wrong database identity.');
}
$run = 'LOCALHOST NEPLATIT EVENT '.bin2hex(random_bytes(5));
$checks = [];
function dwCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    $GLOBALS['checks'][] = $label;
}
function dwInsert(string $table, array $data): int {
    $pdo = $GLOBALS['pdo'];
    $pdo->prepare('INSERT INTO '.$table.'('.implode(',',array_keys($data)).') VALUES('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}
function dwSession(int $id): array {
    session_id(''); app_session_start(); $_SESSION = [];
    auth_session_bind_trainer($id,1);
    auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$id);
    app_session_mark_authenticated();
    $result = ['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];
    session_write_close();
    return $result;
}
function dwRequest(array &$session, string $path, ?array $post = null): array {
    if (!preg_match('~^(?:(?:booking|uctenky)/)?[a-z_]+\.php(?:\?.*)?$~D', $path)) throw new RuntimeException('Local PHP route required.');
    $location = '';
    $curl = curl_init('http://127.0.0.1:18086/'.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>30,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_HEADERFUNCTION=>static function($c,string $line) use (&$session,&$location): int {
            if (preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$m)) $session['cookie']=$m[1];
            if (preg_match('/^Location:\s*(.+)/i',$line,$m)) $location=trim($m[1]);
            return strlen($line);
        }]);
    if ($post !== null) {
        $post += ['csrf_token'=>$session['csrf']];
        curl_setopt($curl,CURLOPT_POST,true);
        curl_setopt($curl,CURLOPT_POSTFIELDS,count(array_filter($post,static fn($v)=>$v instanceof CURLFile))?$post:http_build_query($post));
    }
    $body = curl_exec($curl); $status = (int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if (!is_string($body) || $status===0 || $status>=500) throw new RuntimeException('HTTP '.$status.' '.$path.' '.substr((string)$body,0,300));
    return compact('body','status','location');
}
function dwDom(array $response): DOMXPath {
    $old = libxml_use_internal_errors(true);
    $doc = new DOMDocument(); $doc->loadHTML('<?xml encoding="utf-8" ?>'.$response['body'],LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    return new DOMXPath($doc);
}
function dwValue(DOMXPath $dom,string $name): string {
    return (string)$dom->evaluate('string(//input[@name="'.$name.'"]/@value)');
}
function dwCounts(): array {
    $result=[];
    foreach (['sports_event_revisions','training_closure_revisions','training_personal_excuses','medical_clearance_watches','club_paper_records','club_roster_members'] as $table) {
        $result[$table]=(int)$GLOBALS['pdo']->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    }
    return $result;
}
function caForm(array $response,string $action):array {
    $dom=dwDom($response);$form=$dom->query('//form[input[@name="action" and @value="'.$action.'"]]')->item(0);
    if(!$form)throw new RuntimeException('Missing native form '.$action);$pairs=[];
    foreach($dom->query('.//input[@name] | .//select[@name] | .//textarea[@name]',$form) as $node){
        $name=$node->getAttribute('name');$type=$node->getAttribute('type');if(in_array($type,['checkbox','radio'],true)&&!$node->hasAttribute('checked'))continue;
        if($node->tagName==='select'){$selected=$dom->query('./option[@selected]',$node)->item(0)??$dom->query('./option',$node)->item(0);$value=$selected?$selected->getAttribute('value'):'';}
        else $value=$node->tagName==='textarea'?$node->textContent:$node->getAttribute('value');
        $pairs[]=urlencode($name).'='.urlencode($value);
    }parse_str(implode('&',$pairs),$post);return $post;
}
function caCount(string $table):int{return (int)$GLOBALS['pdo']->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
require_once $root.'/includes/club_expenses.php';
try{
foreach(['lead'=>'sports_lead','coach'=>'coach','foreign'=>'coach','finance'=>'finance_manager','approver'=>'finance_manager','registrar'=>'registrar'] as $name=>$role){
    $actors[$name]=dwInsert('treneri',['jmeno'=>$run.' '.$name,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
    dwInsert('staff_user_positions',['trainer_id'=>$actors[$name],'position_code'=>$role,'is_default'=>1]);$sessions[$name]=dwSession($actors[$name]);
}
$f=&$sessions['finance'];$g=&$sessions['approver'];$lead=&$sessions['lead'];$coach=&$sessions['coach'];$foreign=&$sessions['foreign'];$today=date('Y-m-d');$common=['confirmed'=>'1','reason'=>$run.' signed synthetic evidence'];
$event=dwInsert('club_events',['code'=>bin2hex(random_bytes(8)),'event_type'=>'race','name'=>$run.' závod','description_plain'=>$run,'audience_label'=>'SYNTHETIC ONLY','capacity'=>40,'pricing_policy'=>'free','currency'=>'CZK','status'=>'draft','created_by_trainer_id'=>$actors['lead'],'activity_kind'=>'race','planning_status'=>'planned']);
dwInsert('club_event_sessions',['event_id'=>$event,'starts_at'=>$today.' 10:00:00','ends_at'=>$today.' 12:00:00','location'=>'LOCALHOST','status'=>'scheduled']);
dwInsert('club_event_people',['event_id'=>$event,'trainer_id'=>$actors['coach'],'person_role'=>'coach','created_by_trainer_id'=>$actors['lead'],'external_name'=>'','external_contact'=>'PRIVATE CONTACT MUST NOT LEAK','note'=>'PRIVATE NOTE','visible_to_members'=>0]);
$season=dwInsert('club_seasons',['code'=>bin2hex(random_bytes(8)),'name'=>$run,'starts_on'=>'2020-01-01','ends_on'=>'2099-12-31','status'=>'active','created_by_trainer_id'=>$actors['lead']]);
$team=dwInsert('club_teams',['season_id'=>$season,'code'=>'LOCALHOST','name'=>$run,'discipline'=>'cyklistika','age_label'=>'LOCALHOST','status'=>'active','created_by_trainer_id'=>$actors['lead']]);
for($i=0;$i<27;$i++){
    $pid=dwInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' osoba '.sprintf('%02d',$i),'narozeni'=>'2015-01-02','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);$persons[]=$pid;
    dwInsert('club_event_planned_participants',['event_id'=>$event,'sportovec_id'=>$pid,'status'=>'planned','created_by_trainer_id'=>$actors['lead']]);
    if($i===0)dwInsert('club_roster_members',['team_id'=>$team,'sportovec_id'=>$pid,'status'=>'active','source'=>'manual','valid_from'=>'2020-01-01','created_by_trainer_id'=>$actors['lead']]);
}
dwInsert('coach_scope_grants',['trainer_id'=>$actors['coach'],'scope_kind'=>'team','scope_id'=>$team,'valid_from'=>'2020-01-01 00:00:00','created_by_trainer_id'=>$actors['lead'],'reason'=>$run]);
$eventRoute='club_event_work_admin.php?event_id='.$event;$expense='club_expenses_admin.php';$before=caCount('sports_event_revisions');
$r=dwRequest($lead,$eventRoute);dwCheck($r['status']===200&&str_contains($r['body'],'Nalezeno: 27')&&str_contains($r['body'],'Dosud nezapsaná'),'Event shows planned roster without converting nomination into actual start');
dwCheck(caCount('sports_event_revisions')===$before,'Read-only event overview creates no sport revision');
$r2=dwRequest($lead,$eventRoute.'&participant_page=2');dwCheck($r2['status']===200&&str_contains($r2['body'],'osoba 26')&&!str_contains($r2['body'],'osoba 00'),'Event participant paging reaches the complete roster');
$r=dwRequest($coach,$eventRoute);dwCheck($r['status']===200&&str_contains($r['body'],'osoba 00')&&!str_contains($r['body'],'osoba 01')&&!str_contains($r['body'],'PRIVATE CONTACT'),'Assigned coach sees only currently scoped persons and no private contact');
dwCheck(dwRequest($foreign,$eventRoute)['status']===403,'Unassigned coach cannot open a known event');
dwCheck(dwRequest($sessions['registrar'],$eventRoute)['status']===403,'Registrar gains no event operations privilege');
$r=dwRequest($lead,'club_event_work_admin.php?event_q='.urlencode($run).'&event_from='.$today.'&event_to='.$today);dwCheck(str_contains($r['body'],htmlspecialchars($run.' závod',ENT_QUOTES,'UTF-8')),'Event date search includes activities later on the last day');
$sport='sports_activity_admin.php?person_id='.$persons[0].'&event_id='.$event;$r=dwRequest($coach,$sport);$post=caForm($r,'participation');$post=array_replace($post,$common,['stage'=>'started','actual_date'=>$today,'result_status'=>'FINISHED','result_value'=>'7','result_unit'=>'pořadí','source_reference'=>$run]);
dwCheck(dwRequest($coach,$sport,$post)['status']===303,'Individual event start uses canonical native form with hidden revision');
dwCheck(str_contains(dwRequest($coach,$eventRoute)['body'],'Skutečně odstartoval'),'Event overview reflects recorded actual start');
dwCheck(dwRequest($coach,$sport,array_replace($post,['request_key'=>bin2hex(random_bytes(16)),'result_value'=>'8']))['status']===422,'Stale result cannot overwrite newer event revision');
$r=dwRequest($f,$expense.'?club_event_id='.$event);$case=caForm($r,'case');dwCheck($case['club_event_id']==$event&&$case['title']===$run.' závod'&&$case['legacy_event_id']==='','Financial case inherits calendar context without guessing legacy linkage');
$case=array_replace($case,$common,['leader_trainer_id'=>$actors['coach'],'due_on'=>$today,'evidence'=>$run.' explicit calendar link']);
dwCheck(dwRequest($f,$expense,array_replace($case,['csrf_token'=>'bad']))['status']===403,'Forged CSRF cannot create linked financial case');
$r=dwRequest($f,$expense,$case);dwCheck($r['status']===303&&str_contains($r['location'],'case_id='),'Case creation returns directly to the new detail');parse_str(parse_url($r['location'],PHP_URL_QUERY),$query);$caseId=(int)$query['case_id'];$caseRoute=$expense.'?case_id='.$caseId;
dwCheck(clubExpenseOne($pdo,'club_expense_cases',$caseId)['legacy_event_id']===null,'Calendar case does not create or attach a legacy event');
dwCheck(dwRequest($coach,$caseRoute)['status']===403,'Coach cannot read financial receipts or balances');
$r=dwRequest($f,$caseRoute);$form=caForm($r,'claim');dwCheck(isset($form['lines'])&&isset($form['payer_ref'])&&!isset($form['items'])&&$form['case_id']==$caseId,'Expense uses named counterparties and structured item table in selected case');
$file=$runtime.'/event-receipt.png';file_put_contents($file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jC3cAAAAASUVORK5CYII='));
$upload=['castka'=>'120','platba'=>'vlastni_hotovost','kategorie'=>'zavodni_oddil','vozidlo_id'=>'','udalost_id'=>'','poznamka'=>$run.' PRIVATE RECEIPT','nahrano_kym'=>$actors['coach'],'nahrano_jmenem'=>'','datum'=>$today.' 10:00:00','expense_case_id'=>$caseId,'obrazek'=>new CURLFile($file,'image/png','receipt.png')];
$r=dwRequest($f,'uctenky/formular.php?expense_case_id='.$caseId);dwCheck($r['status']===200&&dwValue(dwDom($r),'expense_case_id')===$caseId.'','Receipt upload retains case context');
$r=dwRequest($f,'uctenky/uloz.php',$upload);$json=json_decode($r['body'],true);if(($json['status']??'')!=='ok')throw new RuntimeException('Synthetic receipt: '.$r['body']);dwCheck(($json['status']??'')==='ok'&&str_starts_with($json['next']??'','../club_expenses_admin.php?'),'Private receipt upload returns a fixed local expense form');parse_str(parse_url($json['next'],PHP_URL_QUERY),$query);$receipt=(int)$query['receipt_id'];
$r=dwRequest($f,$caseRoute.'&receipt_id='.$receipt);$claim=caForm($r,'claim');dwCheck($claim['receipt_id']==$receipt&&$claim['amount']==='120.00','Uploaded receipt is preselected with original amount');
$claim=array_replace($claim,$common,['duplicate_review'=>$run.' repeated synthetic image intentionally verified','supplier'=>$run.' supplier','document_number'=>$run.' invoice','due_on'=>$today,'payer_ref'=>'trainer:'.$actors['coach'],'creditor_ref'=>'trainer:'.$actors['coach'],'payer_label'=>'FORGED NAME','creditor_label'=>'FORGED NAME','payee_evidence'=>$run.' signed cash','purpose'=>$run.' transport','lines'=>[['amount'=>'100','accepted'=>'1','case_id'=>$caseId,'activity'=>'oddíl','purpose'=>'Doprava'],['amount'=>'20','accepted'=>'0','case_id'=>$caseId,'activity'=>'osobní','purpose'=>'Soukromá část']]]);
$bad=array_replace($claim,['lines'=>[['amount'=>'119','accepted'=>'1','case_id'=>$caseId,'activity'=>'oddíl','purpose'=>'Doprava']]]);$count=caCount('club_expense_claims');$r=dwRequest($f,$caseRoute,$bad);dwCheck($r['status']===422&&caCount('club_expense_claims')===$count&&caForm($r,'claim')['lines'][0]['amount']==='119','Incorrect sum is rejected atomically and retains native form values');
$r=dwRequest($f,$caseRoute,$claim);dwCheck($r['status']===303,'Structured expense and private attachment are saved');$q=$pdo->prepare('SELECT * FROM club_expense_claims WHERE request_key=?');$q->execute([$claim['request_key']]);$c=$q->fetch();$claimId=(int)$c['id'];
dwCheck($c['payer_label']===$run.' coach'&&$c['creditor_label']===$run.' coach'&&(int)$c['accepted_minor']===10000&&strlen($c['attachment_sha256'])===64,'Selected identity is resolved server-side and attachment has immutable hash');
dwCheck(dwRequest($f,$caseRoute,$claim)['status']===303&&caCount('club_expense_claims')===$count+1,'Native expense retry creates no duplicate claim');
dwCheck(dwRequest($f,'private_download.php?kind=receipt&id='.$receipt)['status']===200&&dwRequest($coach,'private_download.php?kind=receipt&id='.$receipt)['status']===403,'Receipt download remains finance-only');
$detail=$caseRoute.'&claim_id='.$claimId;$r=dwRequest($f,$detail);dwCheck(str_contains($r['body'],'Co zbývá před uzavřením')&&str_contains($r['body'],'Soukromá část'),'Case and expense detail expose outstanding work and full split');
$state=caForm($r,'claim_state');$state=array_replace($state,$common,['transition'=>'submit']);dwCheck(dwRequest($f,$detail,$state)['status']===303,'Selected version can be submitted without typing its ID');
dwCheck(dwRequest($f,$detail,array_replace($state,['transition'=>'approve']))['status']===422,'Selected form preserves independent-approver requirement');
dwCheck(dwRequest($g,$detail,array_replace($state,['transition'=>'approve','csrf_token'=>$g['csrf']]))['status']===303,'Second finance manager approves the actual version');
$account=accountingLedgerCreateAccount($pdo,['code'=>'EV-'.bin2hex(random_bytes(6)),'name'=>$run,'kind'=>'cash','opened_on'=>$today,'opening_minor'=>100000,'evidence'=>$run],$actors['finance'],$run,true);
$m=accountingLedgerRecord($pdo,['account_id'=>$account['id'],'kind'=>'expense','amount_minor'=>-10000,'currency'=>'CZK','effective_on'=>$today,'source_context'=>'LOCALHOST','source_reference'=>$run.' actual payment','request_key'=>bin2hex(random_bytes(16)),'evidence'=>$run],$actors['finance'],$run,true);
$r=dwRequest($f,$detail);$allocate=caForm($r,'allocate');dwCheck($allocate['target_ref']==='claim:'.$claimId&&str_contains($r['body'],$run.' actual payment'),'Settlement selects named target and dated real movement');
$allocate=array_replace($allocate,$common,['movement_id'=>$m['id'],'amount'=>'100','effective_on'=>$today]);dwCheck(dwRequest($f,$detail,array_replace($allocate,['case_type'=>'expense_advance_out']))['status']===422,'Mismatched target kind cannot allocate expense to unrelated advance ID');
dwCheck(dwRequest($f,$detail,$allocate)['status']===303&&clubExpenseClaimBalance($pdo,$claimId)['remaining_minor']===0,'Canonical settlement links the real payment and clears liability');
$correction=caForm(dwRequest($f,$detail.'&correct=1'),'claim');dwCheck($correction['previous_id']==$claimId&&$correction['lines'][1]['amount']==='20.00','Correction prefills original split and preserves prior version reference');
$pdo->prepare('UPDATE treneri SET jmeno=? WHERE id=?')->execute([$run.' coach renamed',$actors['coach']]);$correction=array_replace($correction,$common,['purpose'=>$run.' corrected description']);dwCheck(dwRequest($f,$detail,$correction)['status']===303,'Correction retains historic counterparty snapshot after current name changes');
$q=$pdo->prepare('SELECT id FROM club_expense_claims WHERE request_key=?');$q->execute([$correction['request_key']]);$correctId=(int)$q->fetchColumn();dwRequest($f,$detail,['action'=>'claim_state','id'=>$correctId,'transition'=>'submit']+$common);dwRequest($g,$detail,['action'=>'claim_state','id'=>$correctId,'transition'=>'approve']+$common);
dwCheck(clubExpenseOne($pdo,'club_expense_claims',$claimId)['state']==='superseded'&&clubExpenseClaimBalance($pdo,$claimId)['remaining_minor']===0,'Correction history remains immutable while prior payment stays attached');
$r=dwRequest($coach,$eventRoute);dwCheck(str_contains($r['body'],$run.' závod')&&!str_contains($r['body'],'PRIVATE RECEIPT')&&!str_contains($r['body'],'actual payment')&&!str_contains($r['body'],'supplier'),'Event status exposes no receipt content, payee or payment reference to coach');
$advance=caForm(dwRequest($f,$caseRoute),'advance');$advance=array_replace($advance,$common,['recipient_ref'=>'trainer:'.$actors['coach'],'amount'=>'200','due_on'=>$today,'purpose'=>$run.' travel advance','payee_evidence'=>$run.' signed handoff']);
$r=dwRequest($f,$caseRoute,$advance);dwCheck($r['status']===303,'Named advance request returns to specific advance detail');parse_str(parse_url($r['location'],PHP_URL_QUERY),$query);$advanceId=(int)$query['advance_id'];$advanceRoute=$caseRoute.'&advance_id='.$advanceId;
$as=caForm(dwRequest($g,$advanceRoute),'advance_state');dwCheck(dwRequest($g,$advanceRoute,array_replace($as,$common,['transition'=>'approve']))['status']===303,'Independent approver selects actual advance from detail');
$out=accountingLedgerRecord($pdo,['account_id'=>$account['id'],'kind'=>'expense','amount_minor'=>-20000,'currency'=>'CZK','effective_on'=>$today,'source_context'=>'LOCALHOST','source_reference'=>$run.' advance out','request_key'=>bin2hex(random_bytes(16)),'evidence'=>$run],$actors['finance'],$run,true);
$aa=caForm(dwRequest($f,$advanceRoute),'allocate');dwCheck($aa['case_type']==='expense_advance_out'&&$aa['target_ref']==='advance:'.$advanceId,'Advance detail preselects handoff type without numeric target confusion');
$aa=array_replace($aa,$common,['movement_id'=>$out['id'],'amount'=>'200','effective_on'=>$today]);dwCheck(dwRequest($f,$advanceRoute,$aa)['status']===303,'Native advance selection records actual handoff');
$as=caForm(dwRequest($f,$advanceRoute),'advance_state');dwCheck(dwRequest($f,$advanceRoute,array_replace($as,$common,['transition'=>'close']))['status']===422,'Native advance closure refuses unreturned balance');
$closeBlocked=caForm(dwRequest($f,$caseRoute),'close_case');dwCheck(dwRequest($f,$caseRoute,array_replace($closeBlocked,$common))['status']===422,'Outstanding advance prevents whole event settlement');
$in=accountingLedgerRecord($pdo,['account_id'=>$account['id'],'kind'=>'income','amount_minor'=>20000,'currency'=>'CZK','effective_on'=>$today,'source_context'=>'LOCALHOST','source_reference'=>$run.' advance return','request_key'=>bin2hex(random_bytes(16)),'evidence'=>$run],$actors['finance'],$run,true);
$aa=array_replace($aa,['movement_id'=>$in['id'],'case_type'=>'expense_advance_in','request_key'=>bin2hex(random_bytes(16))]);dwCheck(dwRequest($f,$advanceRoute,$aa)['status']===303&&clubExpenseAdvanceBalance($pdo,$advanceId)['balance_minor']===0,'Actual return clears advance balance through named movement');
dwCheck(dwRequest($f,$advanceRoute,array_replace($as,$common,['transition'=>'close']))['status']===303,'Settled advance can close without changing actual money');
$close=caForm(dwRequest($f,$caseRoute),'close_case');dwCheck(dwRequest($f,$caseRoute,array_replace($close,$common))['status']===303,'Settled event case closes only by explicit canonical action');
$r=dwRequest($f,'uctenky/uloz.php',$upload);dwCheck(json_decode($r['body'],true)['status']==='error','Upload to closed case cannot bypass financial evidence lock');
dwCheck(dwRequest($f,$caseRoute,array_replace($claim,['request_key'=>bin2hex(random_bytes(16))]))['status']===422,'Structured form cannot append expense to a closed case');
$pdo->prepare('UPDATE coach_scope_grants SET revoked_at=CURRENT_TIMESTAMP WHERE trainer_id=?')->execute([$actors['coach']]);$r=dwRequest($coach,$eventRoute);dwCheck($r['status']===200&&!str_contains($r['body'],'osoba 00'),'Revoked person scope removes participant on next event read');
$pdo->prepare('DELETE FROM club_event_people WHERE event_id=? AND trainer_id=?')->execute([$event,$actors['coach']]);dwCheck(dwRequest($coach,$eventRoute)['status']===403,'Revoked event assignment removes event access');
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='finance_manager'")->execute([$actors['finance']]);dwCheck(dwRequest($f,$caseRoute)['status']===403,'Revoked financial position removes all detail access');
$report=['status'=>'PASS','run'=>$run,'scenarios'=>count($checks),'checks'=>$checks,'production_mutations'=>false,'browser_visual_uat'=>false];file_put_contents($runtime.'/club-administration-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);}
