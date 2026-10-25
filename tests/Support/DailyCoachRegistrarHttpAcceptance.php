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
$run = 'LOCALHOST NEPLATIT DAILY '.bin2hex(random_bytes(5));
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
    if (!preg_match('~^[a-z_]+\.php(?:\?.*)?$~D', $path)) throw new RuntimeException('Local PHP route required.');
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
$counts=dwCounts();
$page=dwRequest($lead,'training_closure_admin.php?plan_id='.$plan.'&person_id='.$person);$dom=dwDom($page);
dwCheck($page['status']===200 && (int)$dom->evaluate('count(//a[@href="formular.php?plan_id='.$plan.'"])')===1,'Planned lesson links to its actual training form');
dwCheck(dwValue($dom,'plan_id')===(string)$plan && (int)$dom->evaluate('count(//select[@name="person_id"]/option[@selected and @value="'.$person.'"])')===1,'Personal excuse preserves named plan and selected person');
dwCheck(dwRequest($coach,'training_closure_admin.php?plan_id='.$plan)['status']===403,'Unassigned coach cannot open lesson via new context route');
$search=dwRequest($coach,'training_closure_admin.php?lesson_q='.rawurlencode($run).'&lesson_on=');
dwCheck((int)dwDom($search)->evaluate('count(//*[@id="lesson-work"]//ul/li)')===0,'Lesson search does not reveal another coach plan');
dwInsert('coach_scope_grants',['trainer_id'=>$actors['coach'],'scope_kind'=>'team','scope_id'=>$team,'valid_from'=>'2020-01-01 00:00:00','created_by_trainer_id'=>$actors['sports_lead'],'reason'=>$run]);
$ownPlan=dwInsert('planovane_treninky',['trener_id'=>$actors['coach'],'datum'=>'2026-01-03','cas_od'=>'15:00','cas_do'=>'16:00','nazev'=>$run.' own']);
dwInsert('training_roster_links',['plan_id'=>$ownPlan,'team_id'=>$team,'target_date'=>'2026-01-03','team_code_snapshot'=>'LOCALHOST','team_name_snapshot'=>$run,'created_by_trainer_id'=>$actors['sports_lead']]);
$own=dwRequest($coach,'training_closure_admin.php?plan_id='.$ownPlan.'&person_id='.$person);
dwCheck($own['status']===200 && dwValue(dwDom($own),'plan_id')===(string)$ownPlan && str_contains($own['body'],'href="moje_treninky.php"'),'Assigned coach can use own lesson workflow and return to own history');
$roster=dwRequest($lead,'training_rsvps_admin.php?plan_id='.$plan);
if($roster['status']!==200)throw new RuntimeException('RSVP route '.$roster['status'].' '.$roster['location']);
dwCheck(str_contains($roster['body'],'training_closure_admin.php?plan_id='.$plan.'&amp;person_id='.$person),'RSVP list offers person-specific excuse link');
$editor=dwRequest($registrar,'sprava_sportovcu.php?edit_id='.$person);$ed=dwDom($editor);
dwCheck($editor['status']===200 && (int)$ed->evaluate('count(//*[@aria-labelledby="person-workflow-title"]//a)')===7,'Registrar sees seven contextual person workflow steps');
dwCheck(str_contains($editor['body'],'medical_clearance_admin.php?person_id='.$person) && str_contains($editor['body'],'paper_registrations_admin.php?person_id='.$person),'Person checklist keeps selected person in linked agendas');
$medical=dwRequest($registrar,'medical_clearance_admin.php?person_id='.$person);$md=dwDom($medical);
dwCheck($medical['status']===200 && (int)$md->evaluate('count(//select[@name="sportovec_id"]/option[@value="'.$person.'" and @selected])')===1,'Medical context preselects person without creating a watch');
dwCheck(dwCounts()===$counts,'Read-only lesson and person workflows create no domain records');
$excuse=['action'=>'personal_excuse','training_id'=>0,'plan_id'=>$plan,'person_id'=>$person,'received_at'=>'2026-01-01T12:00','source'=>'phone','reason'=>$run];
$page=dwRequest($lead,'training_closure_admin.php',$excuse);
dwCheck($page['status']===303 && str_contains($page['location'],'plan_id='.$plan) && str_contains($page['location'],'person_id='.$person),'Personal excuse records and redirects with lesson and person context');
$q=$pdo->prepare('SELECT response FROM training_rsvps WHERE plan_id=? AND sportovec_id=?');$q->execute([$plan,$person]);
dwCheck($q->fetchColumn()==='not_going' && dwCounts()['training_closure_revisions']===$counts['training_closure_revisions'],'Personal excuse updates RSVP but never confirms actual attendance');
$form=dwRequest($lead,'formular.php?plan_id='.$plan);
dwCheck($form['status']===303 && str_starts_with($form['location'],'formular.php?'),'Actual training form issues a stable submission URL');
$form=dwRequest($lead,$form['location']);$fd=dwDom($form);$key=dwValue($fd,'submission_key');
dwCheck($form['status']===200 && strlen($key)===48,'Training form is usable from selected lesson');
$post=['submission_key'=>$key,'plan_id'=>$plan,'datum'=>'2026-01-02','delka'=>1,'kategorie'=>'draha','napln'=>$run,'ucastnici'=>'','trenere'=>[$actors['sports_lead']]];
$saved=dwRequest($lead,'ulozit_trenink.php',$post);
dwCheck($saved['status']===303 && preg_match('/^training_closure_admin\.php\?id=[1-9][0-9]*$/D',$saved['location'])===1,'Saving actual training leads directly to its closure');
$training=(int)$pdo->query('SELECT trenink_id FROM planovane_treninky WHERE id='.$plan)->fetchColumn();
$count=(int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn();$retry=dwRequest($lead,'ulozit_trenink.php',$post);
dwCheck($retry['location']===$saved['location'] && (int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn()===$count,'Duplicate training submission retains closure redirect and creates no second record');
dwCheck(dwRequest($finance,'ulozit_trenink.php',$post)['status']===403 && dwRequest($registrar,'formular.php?plan_id='.$plan)['status']===403,'New lesson delegation does not grant finance or registrar training writes');
dwCheck(dwRequest($lead,'ajax_sportovci.php?q='.rawurlencode($run))['status']===200 && dwRequest($lead,'ajax_dostupnost_sportovist.php')['status']!==403,'Sports lead can use athlete lookup and venue availability in training form');
$page=dwRequest($lead,'training_closure_admin.php?plan_id='.$plan);$dom=dwDom($page);
dwCheck(dwValue($dom,'training_id')===(string)$training && (int)$dom->evaluate('count(//*[@id="actual-attendance"])')===1,'Lesson route resolves actual training and exposes closure form');
// The overview is paginated across historical fixtures: locate our new source.
$found=false;
for($n=1;$n<=30;$n++){
    $overview=dwRequest($lead,'provozni_prehled_admin.php?page='.$n);
    if(str_contains($overview['body'],'id="case-training_attendance-'.$training.'"')){$found=true;break;}
    if(!str_contains($overview['body'],'Další stránka'))break;
}
dwCheck($found && str_contains($overview['body'],$run),'Sports lead sees pending attendance case with lesson context');
$case=['source_type'=>'training_attendance','source_id'=>$training,'revision'=>0,'owner_trainer_id'=>$actors['sports_lead'],'review_on'=>date('Y-m-d'),'status'=>'open','next_step'=>'LOCALHOST confirm actual attendance','reason'=>$run];
$assigned=dwRequest($lead,'provozni_prehled_admin.php',$case);
dwCheck(in_array($assigned['status'],[200,302,303],true) && (int)$pdo->query("SELECT COUNT(*) FROM operational_cases WHERE source_type='training_attendance' AND source_id=".$training)->fetchColumn()===1 && dwCounts()['training_closure_revisions']===$counts['training_closure_revisions'],'Assigning attendance case changes responsibility only');
$close=['training_id'=>$training,'version'=>0,'action'=>'close','actual_started_at'=>'2026-01-02T15:00','actual_ended_at'=>'2026-01-02T16:00','participant_ids'=>[],'coach_ids'=>[$actors['sports_lead']],'reason'=>$run,'record_source'=>'paper','confirm_action'=>1];
dwCheck(dwRequest($lead,'training_closure_admin.php',$close)['status']===303,'Actual attendance can be confirmed after recording lesson');
$stale=dwRequest($lead,'training_closure_admin.php',$close);$sd=dwDom($stale);
dwCheck($stale['status']===422 && dwValue($sd,'version')==='0' && str_contains($stale['body'],'Podklad mezitím změnil'),'Stale closure retains original revision and requires explicit reload');
$event=dwInsert('club_events',['code'=>bin2hex(random_bytes(8)),'event_type'=>'race','name'=>$run.' závod','description_plain'=>$run,'audience_label'=>'SYNTHETIC ONLY','capacity'=>10,'pricing_policy'=>'free','currency'=>'CZK','status'=>'draft','created_by_trainer_id'=>$actors['sports_lead'],'activity_kind'=>'race','planning_status'=>'completed']);
$route='sports_activity_admin.php?person_id='.$person.'&event_id='.$event;
$page=dwRequest($lead,$route);$dom=dwDom($page);
dwCheck(dwValue($dom,'expected_version')==='0' && (int)$dom->evaluate('count(//input[@name="expected_version" and @type="hidden"])')===1 && str_contains($page['body'],$run.' závod'),'Sports form selects named event and hides technical revision');
$participation=['action'=>'participation','person_id'=>$person,'event_id'=>$event,'expected_version'=>0,'stage'=>'started','actual_date'=>'2026-01-02','result_status'=>'DNF','result_value'=>'','result_unit'=>'','source_reference'=>$run.' origin','reason'=>$run,'confirmed'=>1,'request_key'=>dwValue($dom,'request_key')];
dwCheck(dwRequest($lead,'sports_activity_admin.php',$participation)['status']===303,'Named sports form records actual participation');
$page=dwRequest($lead,$route);$dom=dwDom($page);
dwCheck(dwValue($dom,'expected_version')==='1' && dwValue($dom,'actual_date')==='2026-01-02' && dwValue($dom,'source_reference')===$participation['source_reference'] && (int)$dom->evaluate('count(//select[@name="result_status"]/option[@selected and @value="DNF"])')===1,'Sports correction prefills current values and revision');
$bad=array_replace($participation,['request_key'=>'sport:'.bin2hex(random_bytes(20)),'source_reference'=>$run.' my stale draft']);
$page=dwRequest($lead,'sports_activity_admin.php',$bad);$dom=dwDom($page);
dwCheck($page['status']===422 && dwValue($dom,'expected_version')==='0' && dwValue($dom,'source_reference')===$bad['source_reference'] && str_contains($page['body'],'Záznam mezitím změnil'),'Concurrent sports change retains submitted revision and draft');
$correction=array_replace($participation,['expected_version'=>1,'result_status'=>'FINISHED','request_key'=>'sport:'.bin2hex(random_bytes(20))]);
dwCheck(dwRequest($lead,'sports_activity_admin.php',$correction)['status']===303,'Explicitly reloaded sports correction appends next revision');
$q=$pdo->prepare('SELECT COUNT(*) FROM sports_event_revisions r JOIN sports_event_participation p ON p.id=r.participation_id WHERE p.event_id=?');$q->execute([$event]);
dwCheck((int)$q->fetchColumn()===2,'Stale sports form did not create a revision or overwrite history');
dwCheck(dwRequest($registrar,$route)['status']===403 && dwRequest($coach,$route)['status']===403,'Named event route retains role and person scope restrictions');
foreach(['club_expenses_admin.php?case_q='.rawurlencode($run),'club_rewards_admin.php?reward_q='.rawurlencode($run)] as $path){
    $page=dwRequest($finance,$path);
    dwCheck($page['status']===200 && str_contains($page['body'],'Nalezeno:'),'Financial agenda renders filtered paginated read model: '.strtok($path,'?'));
}
$report=['run'=>$run,'checks'=>count($checks),'passed'=>$checks,'production_mutations'=>false,'external_sends'=>false];
file_put_contents($runtime.'/daily-coach-registrar-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
