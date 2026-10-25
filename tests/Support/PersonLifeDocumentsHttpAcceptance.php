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
$run = 'LOCALHOST NEPLATIT LIFE '.bin2hex(random_bytes(5));
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
    if (!preg_match('~^(?:booking/)?[a-z_]+\.php(?:\?.*)?$~D', $path)) throw new RuntimeException('Local PHP route required.');
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
        curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));
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
foreach (['sports_lead','coach','registrar','finance_manager'] as $role) {
    $actors[$role]=dwInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
    dwInsert('staff_user_positions',['trainer_id'=>$actors[$role],'position_code'=>$role,'is_default'=>1]);
    $sessions[$role]=dwSession($actors[$role]);
}
$lead=&$sessions['sports_lead']; $coach=&$sessions['coach']; $registrar=&$sessions['registrar']; $finance=&$sessions['finance_manager'];
$person=dwInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run,'narozeni'=>'2015-01-02','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);
$season=dwInsert('club_seasons',['code'=>bin2hex(random_bytes(8)),'name'=>$run,'starts_on'=>'2020-01-01','ends_on'=>'2099-12-31','status'=>'active','created_by_trainer_id'=>$actors['sports_lead']]);
$team=dwInsert('club_teams',['season_id'=>$season,'code'=>'LOCALHOST','name'=>$run,'discipline'=>'cyklistika','age_label'=>'LOCALHOST','status'=>'active','created_by_trainer_id'=>$actors['sports_lead']]);
dwInsert('club_roster_members',['team_id'=>$team,'sportovec_id'=>$person,'status'=>'active','source'=>'manual','valid_from'=>'2020-01-01','created_by_trainer_id'=>$actors['sports_lead']]);
$plan=dwInsert('planovane_treninky',['trener_id'=>$actors['sports_lead'],'datum'=>'2026-01-02','cas_od'=>'15:00','cas_do'=>'16:00','nazev'=>$run]);
dwInsert('training_roster_links',['plan_id'=>$plan,'team_id'=>$team,'target_date'=>'2026-01-02','team_code_snapshot'=>'LOCALHOST','team_name_snapshot'=>$run,'created_by_trainer_id'=>$actors['sports_lead']]);
$today=date('Y-m-d');$next=date('Y-m-d',strtotime('+5 days'));
$pdo->prepare('UPDATE planovane_treninky SET datum=? WHERE id=?')->execute([$next,$plan]);
$pdo->prepare('UPDATE training_roster_links SET target_date=? WHERE plan_id=?')->execute([$next,$plan]);
dwInsert('coach_scope_grants',['trainer_id'=>$actors['coach'],'scope_kind'=>'team','scope_id'=>$team,'valid_from'=>'2020-01-01 00:00:00','created_by_trainer_id'=>$actors['sports_lead'],'reason'=>$run]);
$foreign=dwInsert('sportovci',['jmeno'=>'NEVER EXPOSE','prijmeni'=>$run.' FOREIGN','narozeni'=>'2016-01-01','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);
$event=dwInsert('club_events',['code'=>bin2hex(random_bytes(8)),'event_type'=>'race','name'=>$run.' FUTURE EVENT','description_plain'=>$run,'audience_label'=>'SYNTHETIC ONLY','capacity'=>10,'pricing_policy'=>'free','currency'=>'CZK','status'=>'draft','created_by_trainer_id'=>$actors['sports_lead'],'activity_kind'=>'race','planning_status'=>'planned']);
dwInsert('club_event_sessions',['event_id'=>$event,'starts_at'=>$next.' 10:00:00','ends_at'=>$next.' 12:00:00','location'=>'LOCALHOST','status'=>'scheduled']);
dwInsert('club_event_planned_participants',['event_id'=>$event,'sportovec_id'=>$person,'status'=>'planned','created_by_trainer_id'=>$actors['sports_lead']]);
function plFingerprint(int $person):string{
    $p=$GLOBALS['pdo'];$rows=[];
    foreach(['sportovci'=>'id','account_person_roles'=>'sportovec_id','club_roster_members'=>'sportovec_id','club_member_charges'=>'sportovec_id','club_paper_records'=>'sportovec_id','medical_clearance_watches'=>'sportovec_id'] as $table=>$column){$q=$p->prepare('SELECT * FROM '.$table.' WHERE '.$column.'=? ORDER BY id');$q->execute([$person]);$rows[$table]=$q->fetchAll();}
    return hash('sha256',json_encode($rows));
}
$fingerprint=plFingerprint($person);$cases=(int)$pdo->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn();
$route='person_lifecycle_admin.php?id='.$person.'&intent=departure';
$view=dwRequest($registrar,$route);
dwCheck($view['status']===200 && str_contains($view['body'],'Čas zápisu není datem účinnosti') && str_contains($view['body'],$run),'Lifecycle view presents current dependencies and honest membership history');
dwCheck(str_contains($view['body'],$run.' FUTURE EVENT') && str_contains($view['body'],'Plánované lekce do'),'Lifecycle view includes upcoming calendar participation and bounded lesson horizon');
dwCheck(plFingerprint($person)===$fingerprint && (int)$pdo->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn()===$cases,'Opening lifecycle guide does not mutate domains or create a work item');
dwCheck(dwRequest($coach,$route)['status']===403 && dwRequest($finance,$route)['status']===403,'Lifecycle guide remains registrar-only');
$input=['person_id'=>$person,'intent'=>'departure','revision'=>0,'owner_trainer_id'=>$actors['registrar'],'next_step'=>$run.' vyřídit osobní odchod','review_on'=>$today,'status'=>'open','reason'=>$run.' převzetí podkladů','confirmed'=>1];
dwCheck(dwRequest($registrar,'person_lifecycle_admin.php',['csrf_token'=>'forged']+$input)['status']===403,'Invalid CSRF cannot create person work item');
dwCheck(dwRequest($registrar,'person_lifecycle_admin.php',['confirmed'=>0]+$input)['status']===422,'Work item requires explicit confirmation of separate domain decisions');
dwCheck(dwRequest($registrar,'person_lifecycle_admin.php',$input)['status']===303,'Registrar assigns reasoned person follow-up with review date');
$stale=dwRequest($registrar,'person_lifecycle_admin.php',['next_step'=>$run.' stale draft']+$input);
dwCheck($stale['status']===422 && dwValue(dwDom($stale),'revision')==='0' && str_contains($stale['body'],'stale draft'),'Concurrent follow-up preserves submitted revision and draft');
$closed=dwRequest($registrar,'person_lifecycle_admin.php',['status'=>'closed','revision'=>1]+$input);
dwCheck($closed['status']===303 && plFingerprint($person)===$fingerprint,'Closing person work item leaves membership, access, roster and obligations unchanged');
$view=dwRequest($registrar,$route);
dwCheck(str_contains($view['body'],'převzetí podkladů'),'Person follow-up shows retained decision history');
// Existing canonical membership decision remains a separate explicit action.
$card=dwRequest($registrar,'sportovec_karta.php?sportovec_id='.$person);
$member=['action'=>'set_status','stav_clenstvi'=>'neaktivni','stav_duvod'=>$run.' výslovné doložené rozhodnutí','membership_revision'=>dwValue(dwDom($card),'membership_revision')];
dwCheck(dwRequest($registrar,'sportovec_karta.php?sportovec_id='.$person,$member)['status']===302,'Membership changes only through its existing canonical form');
$view=dwRequest($registrar,$route);
dwCheck(str_contains($view['body'],$member['stav_duvod']) && str_contains($view['body'],'Neaktivní'),'New lifecycle view reflects actual recorded membership decision');
dwCheck((int)$pdo->query('SELECT COUNT(*) FROM club_roster_members WHERE sportovec_id='.$person." AND status='active'")->fetchColumn()===1,'Membership decision does not silently remove roster');
// Synthetic health metadata: private location must never reach coach or family views.
$policy=dwInsert('medical_clearance_policies',['purpose'=>$run,'legal_basis'=>'LOCALHOST approved fixture','retention_rule'=>'LOCALHOST fixture only','activity_rule'=>'LOCALHOST explicit staff review','reminder_days'=>30,'approved_by'=>$actors['registrar']]);
$watch=dwInsert('medical_clearance_watches',['sportovec_id'=>$person,'purpose'=>$run.' platnost','policy_id'=>$policy,'next_review_on'=>$today,'created_by'=>$actors['registrar']]);
$record=dwInsert('medical_clearance_records',['watch_id'=>$watch,'issued_on'=>'2020-01-01','valid_until'=>date('Y-m-d',strtotime('+10 days')),'original_location'=>'PRIVATE SECRET CABINET','state'=>'verified','recorded_by'=>$actors['registrar']]);
$foreignWatch=dwInsert('medical_clearance_watches',['sportovec_id'=>$foreign,'purpose'=>$run.' secret foreign purpose','policy_id'=>$policy,'next_review_on'=>$today,'created_by'=>$actors['registrar']]);
$groupRoute='medical_activity_admin.php?team_id='.$team.'&on='.$today;
$view=dwRequest($coach,$groupRoute);
dwCheck($view['status']===200 && str_contains($view['body'],'Končí platnost') && !str_contains($view['body'],'PRIVATE SECRET') && !str_contains($view['body'],'NEVER EXPOSE'),'Coach sees scoped expiry warning without private evidence or foreign persons');
$later=dwRequest($coach,'medical_activity_admin.php?team_id='.$team.'&on='.date('Y-m-d',strtotime('+12 days')).'&state=expired');
dwCheck($later['status']===200 && str_contains($later['body'],'Platnost skončila'),'Health evaluation uses chosen activity date');
dwCheck(dwRequest($coach,'medical_activity_admin.php?team_id='.$team.'&on=2026-02-31')['status']===422,'Invalid activity date is rejected');
dwCheck(dwRequest($finance,$groupRoute)['status']===403 && dwRequest($coach,'medical_clearance_file.php?id='.$record)['status']===403,'Health overview does not grant finance or coach private medical access');
dwCheck(dwRequest($coach,'medical_activity_admin.php?plan_id='.$plan)['status']===403,'A scoped person does not authorize another coach lesson');
$lesson=dwRequest($lead,'medical_activity_admin.php?plan_id='.$plan.'&on=2000-01-01');
dwCheck($lesson['status']===200 && str_contains($lesson['body'],$next) && str_contains($lesson['body'],'Končí platnost'),'Lesson health context uses actual plan date, ignoring a forged date override');
$pdo->prepare('UPDATE planovane_treninky SET trener_id=? WHERE id=?')->execute([$actors['coach'],$plan]);
dwCheck(dwRequest($coach,'medical_activity_admin.php?plan_id='.$plan)['status']===200,'Assigned coach can inspect health status for owned lesson');
dwCheck(dwRequest($registrar,$groupRoute)['status']===200,'Registrar can inspect group health and reach existing evidence administration');
function plPublicSession(int $account):array{
    session_id('');app_session_start();$_SESSION=[];auth_session_bind_public_user($account,1);app_session_mark_authenticated();$s=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();return $s;
}
foreach(['guardian','self'] as $role){
    $account=dwInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'aktivni'=>1,'email_overeno'=>1,'session_version'=>1]);
    $relation=dwInsert('account_person_roles',['account_id'=>$account,'sportovec_id'=>$person,'relation_role'=>$role,'status'=>'approved','source'=>'admin','valid_from'=>'2020-01-01 00:00:00','created_by_trainer_id'=>$actors['registrar'],'approved_by_trainer_id'=>$actors['registrar'],'decision_note'=>$run]);
    $public=plPublicSession($account);$before=plFingerprint($person);
    $view=dwRequest($public,'booking/sportovni_prehled.php');$dom=dwDom($view);
    $tasks=(string)$dom->evaluate('string(//*[@id="family-tasks"])');
    dwCheck($view['status']===200 && str_contains($tasks,'Končí platnost') && str_contains($tasks,'potvrdit trénink') && !str_contains($tasks,'NEVER EXPOSE') && !str_contains($view['body'],'PRIVATE SECRET'),'Authorized '.$role.' sees actionable tasks for own person only');
    dwCheck(!str_contains($view['body'],'Produkční e-mailový transport zatím není aktivní') && str_contains($view['body'],'E-mailové odesílání je v tomto prostředí blokované'),'Delivery notice reflects sandbox mail block, not a hardcoded production claim');
    dwCheck(plFingerprint($person)===$before,'Family '.$role.' task read changes no person dependencies');
    $pdo->prepare("UPDATE account_person_roles SET status='revoked' WHERE id=?")->execute([$relation]);
    $view=dwRequest($public,'booking/sportovni_prehled.php');$tasks=(string)dwDom($view)->evaluate('string(//*[@id="family-tasks"])');
    dwCheck(!str_contains($tasks,$run.' platnost') && !str_contains($tasks,'potvrdit trénink'),'Revoked '.$role.' relation removes person tasks on next request');
}
// Revocation of a staff grant must affect even a known group URL.
$pdo->prepare('DELETE FROM coach_scope_grants WHERE trainer_id=? AND scope_kind=? AND scope_id=?')->execute([$actors['coach'],'team',$team]);
dwCheck(dwRequest($coach,$groupRoute)['status']===403,'Revoked coach scope denies previously accessible health context');
$report=['run'=>$run,'checks'=>count($checks),'passed'=>$checks,'production_mutations'=>false,'external_sends'=>false];
file_put_contents($runtime.'/person-life-documents-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
