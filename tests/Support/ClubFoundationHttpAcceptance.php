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
$run='LOCALHOST NEPLATIT R10 '.bin2hex(random_bytes(5));
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
    $cookie=$session['cookie'];$location='';
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$session['cookie'],CURLOPT_TIMEOUT=>15,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$cookie,&$location):int{if(preg_match('/^Set-Cookie:\s*(EVIDENCESESSID=[^;]+)/i',$line,$match))$cookie=$match[1];if(preg_match('/^Location:\s*(.+)/i',$line,$match))$location=trim($match[1]);return strlen($line);}]);
    if($post!==null){$post+=['csrf_token'=>$session['csrf']];curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,count(array_filter($post,static fn($v)=>$v instanceof CURLFile))>0?$post:http_build_query($post));}
    $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);clearstatcache(true);
    if(!is_string($body)||$status>=500||$status===0)throw new RuntimeException('HTTP failure '.$route.': '.$status.' '.$error);
    return ['status'=>$status,'body'=>$body,'cookie'=>$cookie,'location'=>$location];
}
function cfPaperCount(): int { return (int)$GLOBALS['pdo']->query('SELECT COUNT(*) FROM club_paper_records')->fetchColumn(); }
function cfPaperFiles(): array { $out=[];foreach(glob(privateStorageRoot().'/paper-documents/*')?:[] as $path)if(is_file($path))$out[basename($path)]=hash_file('sha256',$path);ksort($out);return $out; }
function cfPaperRow(int $id): array { $q=$GLOBALS['pdo']->prepare('SELECT * FROM club_paper_records WHERE id=?');$q->execute([$id]);return $q->fetch()?:[]; }

try {
    $trainers=[];$sessions=[];
    foreach(['registrar','coach','finance_manager','sports_lead','order_operator','system_admin'] as $position){
        $trainer=cfInsert('treneri',['jmeno'=>$run.' '.$position,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
        cfInsert('staff_user_positions',['trainer_id'=>$trainer,'position_code'=>$position,'is_default'=>1]);
        $trainers[$position]=$trainer;$sessions[$position]=cfSession($trainer);
    }
    $registrar=$sessions['registrar'];
    $person=cfInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run,'narozeni'=>'2015-01-02','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'','odmena_za_trenink'=>'9876.54']);
    $foreign=cfInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' other','narozeni'=>'2016-01-02','stav_clenstvi'=>'cekajici','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'','odmena_za_trenink'=>'9876.54']);
    $identityData=['action'=>'create','source_namespace'=>'LOCALHOST/http','source_id'=>'test-'.bin2hex(random_bytes(8)),'jmeno'=>'LOCALHOST','prijmeni'=>$run,'narozeni'=>'2015-01-02','evidence_ref'=>$run];
    $identityCount=(int)$pdo->query('SELECT COUNT(*) FROM person_identity_reviews')->fetchColumn();
    cfRequest($registrar,'identity_reviews_admin.php',$identityData+['csrf_token'=>'forged']);
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM person_identity_reviews')->fetchColumn()===$identityCount,'Identity creation rejects forged CSRF without writes');
    cfCheck(cfRequest($registrar,'identity_reviews_admin.php',$identityData)['status']===302,'Source identity case created through registrar HTTP form');
    $identityQuery=$pdo->prepare('SELECT * FROM person_identity_reviews WHERE source_namespace=? AND source_id=?');$identityQuery->execute([$identityData['source_namespace'],$identityData['source_id']]);$identity=$identityQuery->fetch();
    cfCheck($identity['status']==='review'&&$identity['sportovec_id']===null,'Exact candidate does not become verified identity automatically');
    $decision=['action'=>'decide','review_id'=>$identity['id'],'revision'=>1,'decision'=>'verified','sportovec_id'=>$person,'reason'=>'Ověřeno podle syntetického podkladu LOCALHOST.','evidence_ref'=>$run];
    cfCheck(cfRequest($registrar,'identity_reviews_admin.php',$decision)['status']===302,'Registrar explicitly verifies canonical identity link');
    $identityQuery->execute([$identityData['source_namespace'],$identityData['source_id']]);$verified=$identityQuery->fetch();
    cfRequest($registrar,'identity_reviews_admin.php',array_replace($decision,['decision'=>'excluded','sportovec_id'=>0]));
    $identityQuery->execute([$identityData['source_namespace'],$identityData['source_id']]);
    cfCheck($identityQuery->fetch()===$verified,'Stale identity decision cannot overwrite a newer verified mapping');
    cfRequest($registrar,'identity_reviews_admin.php',array_replace($identityData,['prijmeni'=>'Changed LOCALHOST source']));
    $identityQuery->execute([$identityData['source_namespace'],$identityData['source_id']]);
    cfCheck($identityQuery->fetch()===$verified,'Changed payload under same source ID cannot silently replace identity evidence');
    $card=cfRequest($registrar,'sportovec_karta.php?sportovec_id='.$person);
    preg_match('/name="membership_revision" value="([^"]+)"/',$card['body'],$match);
    cfCheck($card['status']===200&&isset($match[1]),'Person card provides optimistic membership revision');
    $memberData=['action'=>'set_status','stav_clenstvi'=>'aktivni','stav_duvod'=>'Přijat člen osobním rozhodnutím LOCALHOST.','membership_revision'=>html_entity_decode($match[1],ENT_QUOTES,'UTF-8')];
    cfRequest($registrar,'sportovec_karta.php?sportovec_id='.$person,$memberData);
    $memberQuery=$pdo->prepare('SELECT stav_clenstvi,stav_manualni FROM sportovci WHERE id=?');$memberQuery->execute([$person]);$membership=$memberQuery->fetch();
    cfCheck($membership['stav_clenstvi']==='aktivni'&&(int)$membership['stav_manualni']===1,'Explicit membership accepted independently from source mapping');
    cfRequest($registrar,'sportovec_karta.php?sportovec_id='.$person,array_replace($memberData,['stav_clenstvi'=>'archiv']));
    $memberQuery->execute([$person]);cfCheck($memberQuery->fetch()===$membership,'Stale membership form cannot overwrite accepted state');
    $data=['action'=>'record','sportovec_id'=>$person,'kind'=>'application','scope_type'=>'membership','document_title'=>$run,'signer_name'=>'LOCALHOST Rodič','signer_relation'=>'guardian','signed_on'=>'2020-01-02','original_location'=>'Složka LOCALHOST','reason'=>$run,'term_version_id'=>0];
    cfCheck(cfRequest($registrar,'paper_registrations_admin.php')['status']===200,'Registrar can open paper evidence administration');
    $before=cfPaperCount();
    cfCheck(cfRequest($registrar,'paper_registrations_admin.php',$data+['csrf_token'=>'forged'])['status']===403&&cfPaperCount()===$before,'Bad CSRF cannot create evidence');
    foreach(['coach','finance_manager'] as $role)cfCheck(cfRequest($sessions[$role],'paper_registrations_admin.php',$data)['status']===403&&cfPaperCount()===$before,'Direct evidence write forbidden for '.$role);
    $png=$sandbox.'/club-foundation-http.png';file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
    $posted=$data+['evidence'=>new CURLFile($png,'image/png','LOCALHOST-NEPLATIT.png')];
    cfCheck(cfRequest($registrar,'paper_registrations_admin.php',$posted)['status']===303&&cfPaperCount()===$before+1,'HTTP paper application with actual private upload succeeds');
    $q=$pdo->prepare('SELECT id FROM club_paper_records WHERE sportovec_id=? ORDER BY id DESC LIMIT 1');$q->execute([$person]);$id=(int)$q->fetchColumn();$original=cfPaperRow($id);
    $path=privateStorageResolve($original['storage_key']);
    cfCheck(is_string($path)&&is_file($path)&&hash_file('sha256',$path)===$original['file_sha256'],'Evidence bytes stored privately with verified fingerprint');
    cfCheck($original['version_label']==='verze neověřena'&&$original['signed_on']==='2020-01-02','Historical signature remains unknown version with actual date');
    $q=$pdo->prepare('SELECT COUNT(*) FROM account_person_roles WHERE sportovec_id=?');$q->execute([$person]);
    cfCheck((int)$q->fetchColumn()===0,'Paper guardian declaration creates no account access');
    cfCheck(cfRequest($registrar,'paper_registration_file.php?id='.$id)['body']===file_get_contents($png),'Registrar downloads the exact private evidence bytes');
    foreach(['coach','finance_manager'] as $role)cfCheck(cfRequest($sessions[$role],'paper_registration_file.php?id='.$id)['status']===403,'Private file denied to '.$role);
    cfCheck(cfRequest(['cookie'=>'','csrf'=>''],'paper_registration_file.php?id='.$id)['status']===403,'Anonymous known file ID does not grant access');
    cfCheck(cfRequest($registrar,'paper_registration_file.php?id=999999999')['status']===404,'Forged evidence ID yields no file');
    $caseData=['source_type'=>'paper_record','source_id'=>$id,'owner_trainer_id'=>$trainers['registrar'],'next_step'=>'Dohledat původní verzi podkladu LOCALHOST','review_on'=>date('Y-m-d'),'status'=>'open','revision'=>0,'reason'=>$run];
    $caseCount=(int)$pdo->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn();
    $caseEventCount=(int)$pdo->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn();
    $overview=cfRequest($registrar,'provozni_prehled_admin.php');
    cfCheck($overview['status']===200&&(int)$pdo->query('SELECT COUNT(*) FROM operational_cases')->fetchColumn()===$caseCount&&(int)$pdo->query('SELECT COUNT(*) FROM operational_case_events')->fetchColumn()===$caseEventCount,'Reading unresolved overview creates no case or audit writes');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',$caseData+['csrf_token'=>'forged'])['status']===403,'Operational assignment rejects invalid CSRF');
    cfCheck(cfRequest($sessions['finance_manager'],'provozni_prehled_admin.php',$caseData)['status']===409,'Finance cannot assign registrar paper cases through direct URL');
    $financeCases=cfRequest($sessions['finance_manager'],'provozni_prehled_admin.php');
    cfCheck($financeCases['status']===200&&!str_contains($financeCases['body'],'case-paper_record-'.$id),'Finance overview does not expose registrar paper cases');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',array_replace($caseData,['source_id'=>999999999]))['status']===409,'Forged source ID cannot create operational case');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',array_replace($caseData,['owner_trainer_id'=>$trainers['finance_manager']]))['status']===409,'Case cannot be assigned to staff without required registrar position');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',$caseData)['status']===303,'Operational owner, next step and date assigned through HTTP');
    $caseQuery=$pdo->prepare("SELECT * FROM operational_cases WHERE source_type='paper_record' AND source_id=?");$caseQuery->execute([$id]);$case=$caseQuery->fetch();
    cfCheck((int)$case['revision']===1&&$case['next_step']===$caseData['next_step'],'Initial case revision and concrete next step persisted');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',$caseData)['status']===409,'Stale parallel operational form rejected without overwriting assignment');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',array_replace($caseData,['status'=>'closed','revision'=>1]))['status']===409,'Unresolved source cannot be bypassed by closing operational task');
    cfCheck(cfRequest($registrar,'paper_registrations_admin.php',['action'=>'accepted','id'=>$id,'reason'=>$run])['status']===303,'Registrar accepts paper evidence explicitly');
    cfCheck(cfPaperRow($id)===$original,'Acceptance preserves immutable original record');
    $files=cfPaperFiles();$count=cfPaperCount();
    $wrong=array_replace($posted,['sportovec_id'=>$foreign,'replaces_id'=>$id]);
    cfRequest($registrar,'paper_registrations_admin.php',$wrong);
    cfCheck(cfPaperCount()===$count&&cfPaperFiles()===$files,'Forged correction of another person makes no record or file change');
    $corrected=array_replace($posted,['replaces_id'=>$id,'signer_name'=>'LOCALHOST Opravený podpis']);
    cfCheck(cfRequest($registrar,'paper_registrations_admin.php',$corrected)['status']===303,'Correction creates a separate revision through HTTP');
    $q=$pdo->prepare('SELECT id FROM club_paper_records WHERE replaces_id=?');$q->execute([$id]);$revision=(int)$q->fetchColumn();
    cfCheck($revision>0&&cfPaperRow($id)===$original&&is_file($path),'Correction retains original record and evidence bytes');
    cfCheck(paperRegistrationGet($pdo,$id)['state']==='superseded'&&paperRegistrationGet($pdo,$revision)['state']==='recorded','Correction explicitly supersedes previous version and awaits review');
    cfCheck(cfRequest($registrar,'provozni_prehled_admin.php',array_replace($caseData,['status'=>'closed','revision'=>1,'next_step'=>'Podklad nahrazen doloženou opravou.']))['status']===303,'Resolved original source permits explicit closure of assigned task');
    $caseQuery->execute([$id]);$closed=$caseQuery->fetch();
    $q=$pdo->prepare('SELECT COUNT(*) FROM operational_case_events WHERE case_id=?');$q->execute([(int)$case['id']]);
    cfCheck($closed['status']==='closed'&&(int)$closed['revision']===2&&(int)$q->fetchColumn()===2,'Closing retains both assignment and closure revisions in audit');
    $closedView=cfRequest($registrar,'provozni_prehled_admin.php?closed=1');
    cfCheck($closedView['status']===200&&str_contains($closedView['body'],'case-paper_record-'.$id),'Closed cases remain available for subsequent review');
    $remapped=cfRequest($registrar,'identity_reviews_admin.php',array_replace($decision,['revision'=>2,'sportovec_id'=>$foreign,'reason'=>'Doložená oprava mapování identity LOCALHOST.']));
    cfCheck($remapped['status']===302,'Corrected identity mapping appends another explicit decision');
    $timeline=cfRequest($registrar,'person_audit_admin.php?sportovec_id='.$person);
    cfCheck($timeline['status']===200&&str_contains($timeline['body'],'Ověření identity')&&str_contains($timeline['body'],'Papírové podání')&&!str_contains($timeline['body'],'Časovou osu se nepodařilo'),'Original person retains identity decision history after mapping correction alongside paper history');
    cfRequest($registrar,'paper_registrations_admin.php',['action'=>'accepted','id'=>$revision,'reason'=>$run]);
    cfRequest($registrar,'paper_registrations_admin.php',['action'=>'revoked','id'=>$revision,'reason'=>$run]);
    $q=$pdo->prepare('SELECT COUNT(*) FROM club_paper_record_events WHERE record_id=?');$q->execute([$revision]);$events=(int)$q->fetchColumn();
    cfRequest($registrar,'paper_registrations_admin.php',['action'=>'accepted','id'=>$revision,'reason'=>$run]);$q->execute([$revision]);
    cfCheck(paperRegistrationGet($pdo,$revision)['state']==='revoked'&&(int)$q->fetchColumn()===$events,'Revoked evidence cannot be silently reaccepted');
    $trigger='cf_paper_failure_'.bin2hex(random_bytes(5));
    $pdo->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON club_paper_record_events FOR EACH ROW BEGIN IF NEW.actor_id='.$trainers['registrar']." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'; END IF; END");
    try {
        $files=cfPaperFiles();$count=cfPaperCount();cfRequest($registrar,'paper_registrations_admin.php',$posted);
        cfCheck(cfPaperCount()===$count&&cfPaperFiles()===$files,'Actual upload is removed and database rolled back when audit fails');
    } finally { $pdo->exec('DROP TRIGGER '.$trigger); }
    $publicAccounts=[];$publicSessions=[];
    foreach([1,1,0] as $verifiedEmail){
        $account=cfInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>'NEPLATIT Rodina','email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'aktivni'=>1,'email_overeno'=>$verifiedEmail,'session_version'=>1]);
        $publicAccounts[]=$account;$publicSessions[]=cfPublicSession($account);
    }
    foreach([[$publicAccounts[0],$person],[$publicAccounts[0],$foreign],[$publicAccounts[1],$person],[$publicAccounts[2],$person]] as [$account,$child]) {
        $response=cfRequest($registrar,'eshop_identity_admin.php',['action'=>'approve','account_id'=>$account,'sportovec_id'=>$child,'relation_role'=>'guardian','note'=>'Vztah osobně doložen syntetickým dokladem LOCALHOST.']);
        cfCheck(in_array($response['status'],[302,303],true),'Explicit guardian relationship approved through registrar UI');
    }
    $family=cfRequest($publicSessions[0],'booking/sportovni_prehled.php');
    cfCheck($family['status']===200&&str_contains($family['body'],$run.' other')&&str_contains($family['body'],$run),'Verified parent sees both explicitly linked children');
    $legacyToken=public_profile_access_issue($pdo,$foreign,$trainers['registrar']);
    $legacyGrant=cfRequest(['cookie'=>'','csrf'=>''],'sportovec_treninky.php?hash='.$legacyToken['token']);
    $legacySession=['cookie'=>$legacyGrant['cookie'],'csrf'=>''];
    cfCheck($legacyGrant['status']===303&&cfRequest($legacySession,'sportovec_treninky.php')['status']===200,'Synthetic historical bearer link grants a profile session before revocation');
    $bearerProfile=cfRequest($legacySession,'sportovec_treninky.php');
    cfCheck(!str_contains($bearerProfile['body'],'Aktuální kredit')&&!str_contains($bearerProfile['body'],'9 876,54'),'Training-read bearer link does not expose financial rewards');
    $parentGrant=cfRequest($publicSessions[0],'sportovec_treninky.php?hash='.$legacyToken['token']);
    $publicSessions[0]['cookie']=$parentGrant['cookie'];
    $ownProfile=cfRequest($publicSessions[0],'sportovec_treninky.php');
    cfCheck($ownProfile['status']===200&&str_contains($ownProfile['body'],'9 876,54'),'Verified currently authorized parent retains own child reward visibility');
    $q=$pdo->prepare('SELECT id FROM account_person_roles WHERE account_id=? AND sportovec_id=?');$q->execute([$publicAccounts[0],$foreign]);$relation=(int)$q->fetchColumn();
    cfRequest($registrar,'eshop_identity_admin.php',['action'=>'revoke','relation_id'=>$relation,'note'=>'Odvolání vztahu doložené osobně LOCALHOST.']);
    cfCheck(cfRequest($legacySession,'ajax_sportovec_treninky.php')['status']===403,'Revoking guardian also invalidates an already-open legacy bearer profile session');
    cfCheck(cfRequest(['cookie'=>'','csrf'=>''],'sportovec_treninky.php?hash='.$legacyToken['token'])['status']===404,'Previously issued legacy link cannot be reopened after guardian revocation');
    $q=$pdo->prepare('SELECT hash FROM sportovci WHERE id=?');$q->execute([$foreign]);
    cfCheck(cfRequest(['cookie'=>'','csrf'=>''],'sportovec_treninky.php?hash='.$q->fetchColumn())['status']===404,'Raw historical person hash never bypasses revoked token validation');
    $family=cfRequest($publicSessions[0],'booking/sportovni_prehled.php');
    cfCheck($family['status']===200&&!str_contains($family['body'],$run.' other')&&str_contains($family['body'],$run),'Revoked relationship disappears from existing family session immediately');
    $otherParent=cfRequest($publicSessions[1],'booking/sportovni_prehled.php');
    cfCheck(str_contains($otherParent['body'],$run)&&!str_contains($otherParent['body'],$run.' other'),'Second parent retains own authorized child only');
    $unverified=cfRequest($publicSessions[2],'booking/sportovni_prehled.php');
    cfCheck($unverified['status']===200&&!str_contains($unverified['body'],$run),'Unverified email does not expose child despite approved relationship');
    cfCheck(cfRequest($publicSessions[0],'paper_registration_file.php?id='.$id)['status']===403,'Parent with revoked relationship cannot fetch private paper evidence by known URL');
    $coach=$sessions['coach'];$sportsLead=$sessions['sports_lead'];
    $tokenCount=(int)$pdo->query('SELECT COUNT(*) FROM public_profile_access_tokens')->fetchColumn();
    $queueCount=(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn();
    $legacyMail=cfRequest($sportsLead,'odeslat_emaily.php');
    cfCheck($legacyMail['status']===200&&str_contains($legacyMail['body'],'bylo ukončeno'),'Legacy contact-email profile route explains replacement workflow');
    cfCheck(cfRequest($sportsLead,'odeslat_emaily.php',['sportovec_id'=>$person,'email'=>'nobody@example.invalid'])['status']===410&&(int)$pdo->query('SELECT COUNT(*) FROM public_profile_access_tokens')->fetchColumn()===$tokenCount&&(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn()===$queueCount,'Legacy profile-email POST creates neither token nor queued email');
    $group=cfInsert('skupiny',['nazev'=>$run,'hash'=>bin2hex(random_bytes(32))]);
    $subgroup=cfInsert('podskupiny',['nazev'=>$run,'hash'=>bin2hex(random_bytes(32)),'skupina_id'=>$group]);
    cfInsert('sportovec_podskupina',['sportovec_id'=>$person,'podskupina_id'=>$subgroup]);
    $unassigned=cfRequest($coach,'sportovec_detail.php?id='.$person);
    cfCheck($unassigned['status']===403,'Coach cannot open an unassigned athlete by direct ID');
    $grantData=['action'=>'grant','trainer_id'=>$trainers['coach'],'scope_kind'=>'subgroup','scope_id'=>$subgroup,'valid_from'=>'2020-01-01T00:00','valid_to'=>'','reason'=>'LOCALHOST výslovné přidělení testovacích svěřenců.'];
    $grantCount=(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn();
    cfCheck(cfRequest($coach,'coach_scope_admin.php',$grantData)['status']===403&&(int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn()===$grantCount,'Coach cannot assign their own athlete scope');
    cfRequest($sportsLead,'coach_scope_admin.php',$grantData+['csrf_token'=>'forged']);
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM coach_scope_grants')->fetchColumn()===$grantCount,'Forged CSRF cannot create staff scope grant');
    cfCheck(cfRequest($sportsLead,'coach_scope_admin.php',$grantData)['status']===302,'Sports lead explicitly grants scoped athlete access');
    $q=$pdo->prepare('SELECT id FROM coach_scope_grants WHERE trainer_id=? AND scope_id=? ORDER BY id DESC LIMIT 1');$q->execute([$trainers['coach'],$subgroup]);$grant=(int)$q->fetchColumn();
    cfCheck(cfRequest($coach,'sportovec_detail.php?id='.$person)['status']===200,'Coach can read explicitly assigned athlete');
    cfCheck(cfRequest($coach,'sportovec_detail.php?id='.$foreign)['status']===403,'Assigned scope does not expose unrelated athlete');
    $search=cfRequest($coach,'ajax_sportovci.php?q='.rawurlencode($run));$athletes=json_decode($search['body'],true);
    cfCheck($search['status']===200&&is_array($athletes)&&array_map('intval',array_column($athletes,'id'))===[$person],'Athlete AJAX search returns only current coach scope');
    foreach(['ajax_global_search.php?q=LOCALHOST','search_sportovci.php?q=LOCALHOST','export_xls.php','uctenky/seznam.php','prehled_skupiny.php?hash=known','prehled_skupina.php?hash=known','prehled_treninku_skupiny_kalendar.php'] as $forbidden)cfCheck(cfRequest($coach,$forbidden)['status']===403,'Coach cannot open privileged endpoint '.$forbidden);
    $trainingBefore=(int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn();
    $trainingForm=cfRequest($coach,'formular.php');$coach['cookie']=$trainingForm['cookie'];
    if($trainingForm['status']!==303||!str_starts_with($trainingForm['location'],'formular.php?submission_key='))throw new RuntimeException('Missing stable training form URL');
    $trainingForm=cfRequest($coach,$trainingForm['location']);$coach['cookie']=$trainingForm['cookie'];
    if(!preg_match('/name="submission_key" value="([a-f0-9]{48})"/',$trainingForm['body'],$trainingKey))throw new RuntimeException('Missing training submission key');
    cfCheck(cfRequest($coach,'ulozit_trenink.php',['submission_key'=>$trainingKey[1],'datum'=>date('Y-m-d'),'delka'=>60,'napln'=>$run,'ucastnici'=>$foreign.':LOCALHOST foreign','podskupiny'=>[$subgroup],'trenere'=>[$trainers['coach']]])['status']===403&&(int)$pdo->query('SELECT COUNT(*) FROM treninky')->fetchColumn()===$trainingBefore,'Forged training attendance cannot assign arbitrary athlete or grant scope');
    $season=cfInsert('club_seasons',['code'=>'LOCALHOST-'.bin2hex(random_bytes(6)),'name'=>$run,'starts_on'=>'2020-01-01','ends_on'=>'2099-12-31','status'=>'active','created_by_trainer_id'=>$trainers['sports_lead']]);
    $ownTeam=cfInsert('club_teams',['season_id'=>$season,'code'=>'LOCALHOST-own','name'=>$run.' own team','discipline'=>'cyklistika','age_label'=>'LOCALHOST','status'=>'active','created_by_trainer_id'=>$trainers['sports_lead']]);
    $foreignTeam=cfInsert('club_teams',['season_id'=>$season,'code'=>'LOCALHOST-foreign','name'=>$run.' foreign team','discipline'=>'cyklistika','age_label'=>'LOCALHOST','status'=>'active','created_by_trainer_id'=>$trainers['sports_lead']]);
    $planData=['nazev'=>$run.' planned','datum'=>date('Y-m-d'),'cas_od'=>'16:00','cas_do'=>'17:00','kategorie'=>'silnice','roster_mode'=>'teams','team_ids'=>[$foreignTeam],'opakovani_typ'=>'none'];
    $plansBefore=(int)$pdo->query('SELECT COUNT(*) FROM planovane_treninky')->fetchColumn();
    cfRequest($coach,'planovany_trenink_form.php',$planData);
    cfCheck((int)$pdo->query('SELECT COUNT(*) FROM planovane_treninky')->fetchColumn()===$plansBefore,'Coach cannot create a plan for a team without explicit team grant');
    $foreignPlan=cfInsert('planovane_treninky',['trener_id'=>$trainers['coach'],'nazev'=>$run.' old foreign plan','datum'=>date('Y-m-d'),'cas_od'=>'16:00','cas_do'=>'17:00','stav'=>'planovany']);
    cfInsert('training_roster_links',['plan_id'=>$foreignPlan,'team_id'=>$foreignTeam,'target_date'=>date('Y-m-d'),'team_code_snapshot'=>'LOCALHOST-foreign','team_name_snapshot'=>$run.' foreign team','created_by_trainer_id'=>$trainers['sports_lead']]);
    foreach(['training_rsvps_admin.php?plan_id='.$foreignPlan,'planovany_trenink_form.php?id='.$foreignPlan,'formular.php?plan_id='.$foreignPlan] as $planRoute)cfCheck(cfRequest($coach,$planRoute)['status']===403,'Historical plan ownership does not bypass current scope on '.$planRoute);
    cfCheck(cfRequest($sportsLead,'coach_scope_admin.php',array_replace($grantData,['scope_kind'=>'team','scope_id'=>$ownTeam]))['status']===302,'Sports lead grants explicit team planning scope');
    cfCheck(cfRequest($coach,'planovany_trenink_form.php',array_replace($planData,['team_ids'=>[$ownTeam]]))['status']===302,'Coach can create plan for explicitly granted own team');
    $q=$pdo->prepare('SELECT id FROM planovane_treninky WHERE trener_id=? AND nazev=? ORDER BY id DESC LIMIT 1');$q->execute([$trainers['coach'],$planData['nazev']]);$ownPlan=(int)$q->fetchColumn();
    cfCheck($ownPlan>0&&cfRequest($coach,'training_rsvps_admin.php?plan_id='.$ownPlan)['status']===200,'Granted own plan attendance administration remains usable');
    $test=cfInsert('zatezove_testy',['sportovec_id'=>$person,'datum'=>date('Y-m-d'),'popis_interni'=>$run]);
    $stressCopy=$sandbox.'/club-foundation-stress.png';copy($png,$stressCopy);$stressKey=privateStorageStore($stressCopy,PRIVATE_STORAGE_STRESS_TESTS,false);
    $stressFile=cfInsert('zatezove_testy_soubory',['test_id'=>$test,'typ'=>'internal_img','nazev'=>'LOCALHOST NEPLATIT','cesta'=>$stressKey]);
    cfCheck(cfRequest($coach,'private_download.php?kind=stress&id='.$stressFile)['body']===file_get_contents($png),'Assigned coach reads authorized private sports attachment');
    $coachProfile=cfRequest($coach,'sportovec_treninky.php?id='.$person);
    cfCheck($coachProfile['status']===200,'Assigned coach obtains scoped training profile session');
    cfCheck(!str_contains($coachProfile['body'],'Aktuální kredit')&&!str_contains($coachProfile['body'],'9 876,54'),'Staff-only coach scope does not grant athlete financial reward access');
    cfCheck(cfRequest($sportsLead,'coach_scope_admin.php',['action'=>'revoke','grant_id'=>$grant,'reason'=>'LOCALHOST ukončení svěřeného rozsahu trenéra.'])['status']===302,'Sports lead revokes coach scope through standard UI');
    cfCheck(cfRequest($coach,'sportovec_detail.php?id='.$person)['status']===403,'Existing coach session loses athlete profile after scope revocation');
    cfCheck(cfRequest($coach,'private_download.php?kind=stress&id='.$stressFile)['status']===403,'Existing coach session loses private attachment after scope revocation');
    cfCheck(cfRequest($coach,'ajax_sportovec_treninky.php')['status']===403,'Cached training profile session is revalidated after scope revocation');
    $search=cfRequest($coach,'ajax_sportovci.php?q='.rawurlencode($run));
    cfCheck(json_decode($search['body'],true)===[],'Revoked scope disappears from AJAX athlete list');
    $mixed=cfMixedSession($trainers['coach'],$publicAccounts[1]);
    $pdo->prepare('UPDATE treneri SET aktivni=0,session_version=session_version+1 WHERE id=?')->execute([$trainers['coach']]);
    $ownFamily=cfRequest($mixed,'booking/sportovni_prehled.php');
    if($ownFamily['status']===303){$mixed['cookie']=$ownFamily['cookie'];$ownFamily=cfRequest($mixed,'booking/sportovni_prehled.php');}
    cfCheck($ownFamily['status']===200&&str_contains($ownFamily['body'],$run),'Revoking staff identity retains separately authorized private parent access');
    cfCheck(cfRequest($mixed,'sportovec_detail.php?id='.$person)['status']!==200,'Offboarded worker cannot regain staff profile from mixed parent session');
    $operator=$sessions['order_operator'];
    $notification=cfInsert('club_event_notifications',['notification_type'=>'booking_rsvp_confirmed','recipient_email'=>'nobody@example.invalid','recipient_name'=>'LOCALHOST NEPLATIT','subject_plain'=>$run,'body_plain'=>'LOCALHOST NEPLATIT synthetic manual contact fixture','status'=>'failed','attempts'=>2,'last_error'=>'manual_contact_required: LOCALHOST synthetic unresolved personal delivery']);
    $notificationQuery=$pdo->prepare('SELECT * FROM club_event_notifications WHERE id=?');$notificationQuery->execute([$notification]);$notificationBefore=$notificationQuery->fetch();
    $notificationEvents=$pdo->prepare('SELECT COUNT(*) FROM club_event_notification_events WHERE notification_id=?');
    $manual=['action'=>'manual_resolution','notification_id'=>$notification,'reason'=>'LOCALHOST osobně předáno oprávněnému rodiči po ověření totožnosti.','confirm_retry'=>'1'];
    foreach([['csrf_token'=>'forged'],['confirm_retry'=>''],['reason'=>'']] as $bad){
        cfRequest($operator,'eshop_notifications_admin.php',array_replace($manual,$bad));$notificationEvents->execute([$notification]);
        cfCheck((int)$notificationEvents->fetchColumn()===0,'Manual contact resolution requires valid CSRF, explicit confirmation and reason: '.array_key_first($bad));
    }
    cfCheck(cfRequest($registrar,'eshop_notifications_admin.php',$manual)['status']===403,'Registrar cannot resolve order-operator notification through direct endpoint');
    $notificationTask=['source_type'=>'notification','source_id'=>$notification,'owner_trainer_id'=>$trainers['order_operator'],'next_step'=>'Ověřit a osobně předat informaci LOCALHOST.','review_on'=>date('Y-m-d'),'status'=>'open','revision'=>0,'reason'=>$run];
    cfCheck(cfRequest($operator,'provozni_prehled_admin.php',$notificationTask)['status']===303,'Failed manual-contact notification receives responsible operator task');
    cfCheck(cfRequest($operator,'provozni_prehled_admin.php',array_replace($notificationTask,['status'=>'closed','revision'=>1]))['status']===409,'Operator cannot close failed-contact task before recording actual resolution');
    cfCheck(cfRequest($operator,'eshop_notifications_admin.php',$manual)['status']===303,'Operator records explicit manual contact outcome via HTTP');
    $notificationQuery->execute([$notification]);$notificationAfter=$notificationQuery->fetch();
    cfCheck($notificationAfter===$notificationBefore&&$notificationAfter['status']==='failed'&&$notificationAfter['sent_at']===null,'Manual resolution preserves failed delivery and does not pretend an email was sent');
    $q=$pdo->prepare('SELECT action,actor_trainer_id,attempts_before,reason FROM club_event_notification_events WHERE notification_id=? ORDER BY id DESC LIMIT 1');$q->execute([$notification]);$resolution=$q->fetch();
    cfCheck($resolution['action']==='manual_contact_resolved'&&(int)$resolution['actor_trainer_id']===$trainers['order_operator']&&(int)$resolution['attempts_before']===2&&$resolution['reason']===$manual['reason'],'Manual resolution audit records responsible actor, attempt and actual explanation');
    cfRequest($operator,'eshop_notifications_admin.php',$manual);$notificationEvents->execute([$notification]);
    cfCheck((int)$notificationEvents->fetchColumn()===1,'Repeated manual resolution does not duplicate audit event');
    cfCheck(cfRequest($operator,'provozni_prehled_admin.php',array_replace($notificationTask,['status'=>'closed','revision'=>1]))['status']===303,'Operator can close task only after matching manual resolution event');
    $q=$pdo->prepare("SELECT status FROM operational_cases WHERE source_type='notification' AND source_id=?");$q->execute([$notification]);
    cfCheck($q->fetchColumn()==='closed','Resolved notification case closure is persisted');
    cfInsert('operational_job_runs',['job_key'=>'transactional_notifications','started_at'=>gmdate('Y-m-d H:i:s',time()-7),'finished_at'=>gmdate('Y-m-d H:i:s'),'status'=>'failed','summary_code'=>'LOCALHOST_http_render_fixture']);
    $log=$sandbox.'/php-error.log';clearstatcache(true);$logOffset=is_file($log)?filesize($log):0;
    foreach(['provozni_prehled_admin.php','diagnostika_site_admin.php'] as $route){
        $diagnostics=cfRequest($sessions['system_admin'],$route);
        cfCheck($diagnostics['status']===200&&str_contains($diagnostics['body'],'Poslední běh: 7 s')&&str_contains($diagnostics['body'],'Chybí provozní nastavení'),'System administrator sees explicit job status and duration on '.$route);
    }
    $freshLog=is_file($log)?substr((string)file_get_contents($log),(int)$logOffset):'';
    cfCheck(!preg_match('/PHP (Warning|Notice|Fatal|Parse)/',$freshLog),'Operational overview and diagnostics emit no PHP warnings or fatal errors');
    $pdo->prepare('UPDATE treneri SET aktivni=0,session_version=session_version+1 WHERE id=?')->execute([$trainers['registrar']]);
    cfCheck(cfRequest($registrar,'paper_registration_file.php?id='.$id)['status']!==200,'Offboarded registrar loses already-known private file access');
    file_put_contents($sandbox.'/club-foundation-http-acceptance.json',json_encode(['status'=>'PASS','count'=>count($results),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    echo json_encode(['status'=>'PASS','count'=>count($results)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {
    file_put_contents($sandbox.'/club-foundation-http-acceptance.json',json_encode(['status'=>'FAIL','error'=>$e->getMessage(),'results'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);
}
