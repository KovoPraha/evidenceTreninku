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
$run = 'LOCALHOST NEPLATIT SEASON '.bin2hex(random_bytes(5));
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
function soForm(array $response,string $kind):array {
    $dom=dwDom($response);$form=$dom->query('//form[input[@name="kind" and @value="'.$kind.'"]]')->item(0);if(!$form)throw new RuntimeException('Missing '.$kind.' form');
    $doc=$form->ownerDocument;return caForm(['body'=>$doc->saveHTML($form)],'entry');
}
function soFingerprint():string {
    $rows=[];foreach(['club_roster_members','club_team_edges','club_roster_events','club_roster_rollover_runs','roster_messages','roster_message_recipients','family_weekly_summaries','club_cutover_runs','club_cutover_entries'] as $table)$rows[$table]=$GLOBALS['pdo']->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();return hash('sha256',serialize($rows));
}
try {
foreach(['registrar','sports_lead','coach','system_admin','order_operator'] as $role){
    $actors[$role]=dwInsert('treneri',['jmeno'=>$run.' '.$role,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
    dwInsert('staff_user_positions',['trainer_id'=>$actors[$role],'position_code'=>$role,'is_default'=>1]);$sessions[$role]=dwSession($actors[$role]);
}
$r=&$sessions['registrar'];$lead=&$sessions['sports_lead'];$coach=&$sessions['coach'];$admin=&$sessions['system_admin'];$operator=&$sessions['order_operator'];
foreach(['root'=>'group','child'=>'roster'] as $name=>$kind)$teams[$name]=dwInsert('club_teams',['season_id'=>null,'code'=>bin2hex(random_bytes(8)),'name'=>$run.' '.$name,'discipline'=>'cyklistika','age_label'=>'LOCALHOST','team_kind'=>$kind,'status'=>'active','created_by_trainer_id'=>$actors['registrar']]);
dwInsert('club_team_edges',['parent_team_id'=>$teams['root'],'child_team_id'=>$teams['child'],'valid_from'=>'2000-01-01','actor_trainer_id'=>$actors['registrar'],'reason'=>$run]);
foreach(['recipient','manual'] as $name){$people[$name]=dwInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' '.$name,'narozeni'=>'2015-01-02','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);dwInsert('club_roster_members',['team_id'=>$teams['child'],'sportovec_id'=>$people[$name],'status'=>'active','source'=>'manual','valid_from'=>'2000-01-01','created_by_trainer_id'=>$actors['registrar']]);}
$account=dwInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' parent','email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'aktivni'=>1,'email_overeno'=>1,'session_version'=>1]);
$relation=dwInsert('account_person_roles',['account_id'=>$account,'sportovec_id'=>$people['recipient'],'relation_role'=>'guardian','status'=>'approved','valid_from'=>'2000-01-01','created_by_trainer_id'=>$actors['registrar'],'decision_note'=>$run]);
$today=date('Y-m-d');$plan=dwInsert('planovane_treninky',['trener_id'=>$actors['sports_lead'],'datum'=>$today,'cas_od'=>'15:00','cas_do'=>'16:00','nazev'=>$run.' lesson']);
dwInsert('training_roster_links',['plan_id'=>$plan,'team_id'=>$teams['child'],'target_date'=>$today,'team_code_snapshot'=>'LOCALHOST','team_name_snapshot'=>$run,'created_by_trainer_id'=>$actors['sports_lead']]);
$seasonRoute='season_preparation_admin.php?team_id='.$teams['root'];$before=soFingerprint();
$view=dwRequest($r,$seasonRoute);dwCheck($view['status']===200&&str_contains($view['body'],$run.' manual')&&str_contains($view['body'],'trvalá skupina'),'Registrar can prepare standing-group period with named descendants');
$view=dwRequest($lead,$seasonRoute);$dom=dwDom($view);dwCheck($view['status']===200&&$dom->query('//a[@href="training_closure_admin.php?plan_id='.$plan.'"]')->length===1,'Sports lead gets canonical lesson link');
dwCheck(dwRequest($lead,'training_closure_admin.php?plan_id='.$plan)['status']===200,'Linked lesson is accessible to sports lead');
dwCheck(dwRequest($coach,$seasonRoute)['status']===403,'Coach cannot inspect club-wide season guide');
dwCheck(dwRequest($r,$seasonRoute,['anything'=>'1'])['status']===405,'Season guide rejects writes');
dwCheck(dwRequest($r,$seasonRoute.'&on=2026-02-30')['status']===422,'Season guide rejects invalid dates');
$view=dwRequest($r,'roster_messages_admin.php');dwCheck(dwDom($view)->query('//option[@value="'.$teams['root'].'"]')->length===1,'Standing group selectable for communication');
$draft=caForm($view,'preview');$draft=array_replace($draft,['team_id'=>$teams['root'],'subject'=>$run.' message','body'=>'LOCALHOST NEODESÍLAT']);$view=dwRequest($r,'roster_messages_admin.php',$draft);
dwCheck(str_contains($view['body'],'Osoby k osobnímu kontaktu: 1')&&str_contains($view['body'],$run.' manual'),'Preview names person requiring personal contact');
$enqueue=caForm($view,'enqueue');dwCheck(str_contains($view['body'],'1 účtů'),'Preview has one verified guardian account');
dwCheck(soFingerprint()===$before,'Season and communication previews leave domain data unchanged');
$pdo->prepare("UPDATE account_person_roles SET status='revoked' WHERE id=?")->execute([$relation]);$count=caCount('roster_messages');$enqueue+=['confirmed'=>'1'];$view=dwRequest($r,'roster_messages_admin.php',$enqueue);
dwCheck(str_contains($view['body'],'od náhledu změnili')&&caCount('roster_messages')===$count,'Revoked relationship invalidates queued-message confirmation');
dwCheck(str_contains($view['body'],$run.' message')&&str_contains($view['body'],'LOCALHOST NEODESÍLAT'),'Failed confirmation preserves draft subject and body');
$pdo->prepare("UPDATE account_person_roles SET status='approved' WHERE id=?")->execute([$relation]);
$view=dwRequest($r,'roster_messages_admin.php',$draft);$enqueue=caForm($view,'enqueue');$enqueue+=['confirmed'=>'1'];$view=dwRequest($r,'roster_messages_admin.php',$enqueue);
dwCheck($view['status']===302&&caCount('roster_messages')===$count+1,'Confirmed synthetic message only enters local queue');
$message=(int)$pdo->query('SELECT MAX(id) FROM roster_messages')->fetchColumn();$q=$pdo->prepare('SELECT status,attempts,sent_at FROM roster_message_recipients WHERE message_id=?');$q->execute([$message]);$recipient=$q->fetch();dwCheck($recipient['status']==='pending'&&(int)$recipient['attempts']===0&&$recipient['sent_at']===null,'No sender or worker was invoked');
$pdo->prepare("UPDATE roster_message_recipients SET status='processing',claimed_at=?,last_error='PRIVATE TRANSPORT ERROR' WHERE message_id=?")->execute([date('Y-m-d H:i:s',time()-3600),$message]);
$before=soFingerprint();foreach(['registrar','system_admin','order_operator'] as $role){$view=dwRequest($sessions[$role],'club_communication_admin.php');dwCheck($view['status']===200&&str_contains($view['body'],'starší než 15 minut')&&!str_contains($view['body'],'PRIVATE TRANSPORT ERROR')&&!str_contains($view['body'],$run.' parent'),'Communication aggregate is private and honest for '.$role);}
dwCheck(soFingerprint()===$before,'Queue overview does not retry or mutate stale message');dwCheck(dwRequest($coach,'club_communication_admin.php')['status']===403,'Coach denied queue overview');dwCheck(dwRequest($admin,'roster_messages_admin.php')['status']===403,'System admin cannot read registrar message contents');dwCheck(dwRequest($r,'family_weekly_summaries_admin.php')['status']===403,'Registrar cannot read family-summary contents');
for($i=0;$i<61;$i++){
    dwInsert('roster_messages',['team_id'=>$teams['root'],'subject_plain'=>$run.' HISTORY '.$i,'body_plain'=>'LOCALHOST PRIVATE BODY','recipient_count'=>1,'preview_fingerprint'=>str_repeat('a',64),'status'=>'sent','created_by_trainer_id'=>$actors['registrar'],'reason'=>$run,'created_at'=>'2030-01-10 23:59:59']);
    $period=(new DateTimeImmutable('2030-01-01'))->modify('+'.$i.' days')->format('Y-m-d');dwInsert('family_weekly_summaries',['account_id'=>$account,'period_from'=>$period,'period_to'=>$period,'recipient_email'=>'synthetic@example.invalid','recipient_name'=>$run.' family','subject_plain'=>$run.' FAMILY '.$i,'body_plain'=>'LOCALHOST PRIVATE SUMMARY','status'=>'sent','created_at'=>'2030-01-10 23:59:59']);
}
$view=dwRequest($r,'roster_messages_admin.php?'.http_build_query(['queue_q'=>$run.' HISTORY','queue_page'=>3,'queue_to'=>'2030-01-10']));dwCheck(str_contains($view['body'],'Nalezeno: 61')&&str_contains($view['body'],'strana 3 z 3')&&str_contains($view['body'],$run.' HISTORY 0'),'Message history reaches oldest page and includes final-date evening');
$view=dwRequest($operator,'family_weekly_summaries_admin.php?'.http_build_query(['queue_q'=>$run.' FAMILY','queue_page'=>3,'queue_to'=>'2030-01-10','queue_state'=>'sent']));dwCheck(str_contains($view['body'],'Nalezeno: 61')&&str_contains($view['body'],'strana 3 z 3')&&!str_contains($view['body'],'LOCALHOST PRIVATE SUMMARY'),'Family queue supports history paging without displaying message body in list');
dwCheck(!str_contains($view['body'],'Produkční zprávy odesílá plánovaný worker.')&&str_contains($view['body'],'nejsou tímto přehledem ověřeny'),'Family page does not claim a verified production scheduler');
$view=dwRequest($admin,'club_cutover_admin.php');$form=caForm($view,'create');$form=array_replace($form,['title'=>$run.' transition','scope_text'=>'LOCALHOST nonshop only','release_reference'=>'LOCALHOST','source_environment'=>'LOCALHOST paper','target_environment'=>'http://localhost:18086','owner_trainer_id'=>$actors['system_admin'],'confirmed'=>'1']);$view=dwRequest($admin,'club_cutover_admin.php',$form);dwCheck($view['status']===303,'Native form creates only an isolated transition preparation');parse_str(parse_url($view['location'],PHP_URL_QUERY),$query);$transition=(int)$query['id'];$route='club_cutover_admin.php?id='.$transition;
$view=dwRequest($admin,$route);$form=soForm($view,'source');$form=array_replace($form,['entry_key'=>'source:paper','title'=>$run.' paper','owner_trainer_id'=>$actors['registrar'],'origin'=>'LOCALHOST paper','history_mode'=>'unknown','shutdown_condition'=>'No cutoff','evidence'=>'LOCALHOST paper only','reason'=>$run,'confirmed'=>'1']);$bad=array_replace($form,['csrf_token'=>'invalid']);$count=caCount('club_cutover_entries');dwCheck(dwRequest($admin,$route,$bad)['status']===403&&caCount('club_cutover_entries')===$count,'Cutover entry rejects invalid CSRF');dwCheck(dwRequest($admin,$route,$form)['status']===303,'Native source form appends source declaration');
$before=soFingerprint();$view=dwRequest($admin,$route.'&template=attendance');$form=soForm($view,'agenda');dwCheck($form['owner_trainer_id']===''&&$form['target_route']==='training_closure_admin.php'&&$form['mapping_status']==='proposed','Template prefills workflow without guessing owner or verification');dwCheck(soFingerprint()===$before,'Selecting transition template is read-only');
$form=array_replace($form,['source_key'=>'source:paper','owner_trainer_id'=>$actors['registrar'],'confirmed'=>'1','reason'=>$run]);dwCheck(dwRequest($admin,$route,$form)['status']===303,'Proposed map appends version through canonical registry');
$view=dwRequest($admin,$route.'&edit=agenda:attendance');$edit=soForm($view,'agenda');dwCheck($edit['title']==='Docházka a omluvenky'&&$edit['source_key']==='source:paper','Edit loads current named map and source without copying identifiers');
$edit=array_replace($edit,['source_screen'=>'LOCALHOST signed attendance book','reason'=>$run.' correction','confirmed'=>'1']);dwCheck(dwRequest($admin,$route,$edit)['status']===303,'Map correction appends a new version');
$stale=array_replace($edit,['source_screen'=>'LOCALHOST UNSAVED DRAFT','reason'=>$run.' stale']);$count=caCount('club_cutover_entries');$view=dwRequest($admin,$route,$stale);$kept=soForm($view,'agenda');dwCheck($view['status']===422&&caCount('club_cutover_entries')===$count&&$kept['source_screen']==='LOCALHOST UNSAVED DRAFT'&&$kept['revision']===$stale['revision'],'Stale edit preserves original revision and draft without overwriting current version');
dwCheck(dwRequest($admin,$route,array_replace($kept,['confirmed'=>'1']))['status']===422&&caCount('club_cutover_entries')===$count,'Submitting retained stale form cannot bypass concurrent-edit guard');
$view=dwRequest($admin,$route.'&edit=agenda:attendance');dwCheck(str_contains($view['body'],'2 verzí')&&str_contains($view['body'],'LOCALHOST signed attendance book'),'Map history shows both versions and correction');dwCheck(dwRequest($r,$route)['status']===403,'Registrar cannot modify transition registry');
$view=dwRequest($admin,$route.'&template=attendance');dwCheck(soForm($view,'agenda')['source_screen']==='LOCALHOST signed attendance book','Selecting an existing template keeps saved map instead of replacing it with proposal');
$before=soFingerprint();$view=dwRequest($operator,'family_weekly_summaries_admin.php?'.http_build_query(['queue_q'=>$run.' FAMILY','queue_page'=>3,'queue_state'=>'sent']));$link=dwDom($view)->evaluate('string(//a[contains(@href,"preview_id=")]/@href)');$view=dwRequest($operator,'family_weekly_summaries_admin.php'.$link);dwCheck(str_contains($view['body'],'LOCALHOST PRIVATE SUMMARY')&&str_contains($view['body'],'strana 3 z 3'),'Family-summary preview retains search state and historical page');dwCheck(soFingerprint()===$before,'Summary preview leaves queues and registry unchanged');
$view=dwRequest($admin,'club_cutover_admin.php?'.http_build_query(['runs_q'=>$run.' transition','runs_page'=>99]));dwCheck(str_contains($view['body'],'Nalezeno: 1')&&str_contains($view['body'],$run.' transition'),'Transition runs are searchable without latest-100 cutoff');
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='sports_lead'")->execute([$actors['sports_lead']]);dwCheck(dwRequest($lead,$seasonRoute)['status']===403,'Revoked sports-lead role immediately loses season guide');
$report=['status'=>'PASS','run'=>$run,'scenarios'=>count($checks),'checks'=>$checks,'production_mutations'=>false,'external_sends'=>false,'browser_visual_uat'=>false];file_put_contents($runtime.'/season-operations-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().' at '.$e->getFile().':'.$e->getLine().PHP_EOL);exit(1);}
