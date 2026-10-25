<?php
declare(strict_types=1);
// Synthetic fixtures only. Never point this script at another environment.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root.'/config.php';
if (DB_NAME !== 'evidence_b1' || DB_HOST !== '127.0.0.1;port=33436' || APP_BASE_URL !== 'http://127.0.0.1:18096') {
    throw new RuntimeException('Owned B1 sandbox required.');
}
$runtime = getenv('LOCALAPPDATA').'/EvidencePavelB1/browser-acceptance-20261025';
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
if ((int)$identity['port'] !== 33436 || strtolower(str_replace('\\','/',$identity['path'])) !== strtolower(str_replace('\\','/',$runtime.'/mysql-restored-20261025/'))) {
    throw new RuntimeException('Wrong database identity.');
}
$run = 'LOCALHOST NEPLATIT FINAL26 '.bin2hex(random_bytes(5));
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
    $curl = curl_init('http://127.0.0.1:18096/'.$path);
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
$actor=dwInsert('treneri',['jmeno'=>$run,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
dwInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>'coach','is_default'=>1]);
$session=dwSession($actor);
$venue=dwInsert('sportovist',['kod'=>bin2hex(random_bytes(6)),'nazev'=>$run,'je_verejne'=>1,'max_kapacita'=>5]);
$lesson=dwInsert('individualni_lekce',['trener_id'=>$actor,'sportoviste_id'=>$venue,'datum'=>date('Y-m-d',strtotime('+10 days')),'cas_od'=>'10:00','cas_do'=>'11:00','typ'=>'zluta','nazev'=>$run,'max_osob'=>1]);
$reservations=[];
for($i=0;$i<3;$i++){
 $user=dwInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' '.$i,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'email_overeno'=>1]);
 $reservations[]=dwInsert('verejne_rezervace',['lekce_id'=>$lesson,'uzivatel_id'=>$user,'stav'=>$i===0?'ceka':'cekaci_listina','slot_cas_od'=>'10:00','slot_cas_do'=>'11:00']);
}
$state=static function(int $id)use($pdo):string{$q=$pdo->prepare('SELECT stav FROM verejne_rezervace WHERE id=?');$q->execute([$id]);return(string)$q->fetchColumn();};
dwRequest($session,'individualni_lekce_sprava.php',['action'=>'potvrdit','rezervace_id'=>$reservations[1]]);
dwCheck($state($reservations[1])==='cekaci_listina','B06: crafted manual confirmation cannot bypass capacity/waitlist');
dwRequest($session,'individualni_lekce_sprava.php',['action'=>'potvrdit','rezervace_id'=>$reservations[0]]);
dwCheck($state($reservations[0])==='potvrzena','B06: pending reserved slot can be confirmed');
dwRequest($session,'individualni_lekce_sprava.php',['action'=>'potvrdit','rezervace_id'=>$reservations[0]]);
$q=$pdo->prepare("SELECT COUNT(*) FROM venue_operation_events WHERE target_type='lesson' AND target_id=? AND action='reservation_confirmed'");$q->execute([$lesson]);
dwCheck((int)$q->fetchColumn()===1,'A10: repeated confirmation creates one actor/time/reason audit event');
// Rejection of an independently pending synthetic lesson exercises queue promotion.
$pdo->prepare("UPDATE verejne_rezervace SET stav='ceka' WHERE id=?")->execute([$reservations[0]]);
dwRequest($session,'individualni_lekce_sprava.php',['action'=>'zamit','rezervace_id'=>$reservations[0]]);
dwCheck($state($reservations[0])==='zamitnuta'&&$state($reservations[1])==='ceka'&&$state($reservations[2])==='cekaci_listina','B06/B07: rejection promotes exactly first waiter at capacity one');
$q=$pdo->prepare("SELECT COUNT(*) FROM venue_operation_events WHERE target_id=? AND action='reservation_rejected'");$q->execute([$lesson]);dwCheck((int)$q->fetchColumn()===1,'A10: rejection is audited');
require_once $root.'/booking/waiting_list.php';
notifyWaitingList($pdo,$lesson,'10:00:00');
dwCheck($state($reservations[2])==='cekaci_listina','B07: repeated promotion does not overbook');
$pdo->prepare("UPDATE verejne_rezervace SET stav='zrusena' WHERE id=?")->execute([$reservations[1]]);
$pdo->prepare("UPDATE individualni_lekce SET typ='zelena' WHERE id=?")->execute([$lesson]);
$second=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$lock='rez_'.$lesson.'_1000';$second->prepare('SELECT GET_LOCK(?,0)')->execute([$lock]);
try{notifyWaitingList($pdo,$lesson,'10:00:00');dwCheck($state($reservations[2])==='cekaci_listina','B07: TIME seconds and form minutes share the same queue lock');}finally{$second->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
notifyWaitingList($pdo,$lesson,'10:00:00');
dwCheck($state($reservations[2])==='potvrzena','B07: green lesson promotes waiter directly to confirmed');
$pdo->prepare("UPDATE verejne_rezervace SET stav='cekaci_listina' WHERE id=?")->execute([$reservations[2]]);
$pdo->prepare("UPDATE individualni_lekce SET stav='zrusena' WHERE id=?")->execute([$lesson]);
notifyWaitingList($pdo,$lesson,'10:00:00');
dwCheck($state($reservations[2])==='cekaci_listina','B07: cancelled lesson never promotes a waiter');
$pdo->prepare("UPDATE verejne_rezervace SET stav='zrusena' WHERE lekce_id=?")->execute([$lesson]);
// Exact staff route delegation needed by the race form.
dwInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>'sports_lead','is_default'=>0]);
$lead=dwSession($actor);
dwRequest($lead,'pracovni_pozice.php',['position'=>'sports_lead']);
// Explicitly bind the synthetic session context; no browser or production session is reused.
session_id(substr($lead['cookie'],strlen(session_name())+1));app_session_start();$_SESSION['staff_active_position']='sports_lead';session_write_close();
$page=dwRequest($lead,'nacti_podskupiny.php?skupina_id=1');
dwCheck($page['status']===200 && is_array(json_decode($page['body'],true)),'B04: sports lead can load race subgroups');

// Planner endpoint contracts, using only newly owned plans.
$day=date('Y-m-d',strtotime('+40 days'));
$plan=dwInsert('planovane_treninky',['trener_id'=>$actor,'datum'=>$day,'cas_od'=>'08:00','cas_do'=>'09:00','nazev'=>$run]);
$pdo->prepare('UPDATE planovane_treninky SET serie_id=? WHERE id=?')->execute([$plan,$plan]);
$plan2=dwInsert('planovane_treninky',['trener_id'=>$actor,'datum'=>date('Y-m-d',strtotime($day.' +14 days')),'cas_od'=>'08:00','cas_do'=>'09:00','nazev'=>$run,'serie_id'=>$plan]);
$r=dwRequest($lead,'ajax_update_plan.php',['akce'=>'rename','plan_id'=>$plan,'nazev'=>$run.' renamed']);
dwCheck((json_decode($r['body'],true)['ok']??false)===true,'B05: rename endpoint persists title');
$newDay=date('Y-m-d',strtotime($day.' +1 day'));
$r=dwRequest($lead,'ajax_update_plan.php',['akce'=>'move','plan_id'=>$plan,'datum'=>$newDay]);
dwCheck((json_decode($r['body'],true)['ok']??false)===true,'B05: moving an untouched plan succeeds');
$reservation=dwInsert('rezervace_sportovist',['trener_id'=>$actor,'sportoviste_id'=>$venue,'datum'=>$newDay,'cas_od'=>'08:00','cas_do'=>'09:00','kapacita_dilu'=>1]);
$pdo->prepare('UPDATE planovane_treninky SET rezervace_id=?,sportoviste_id=? WHERE id=?')->execute([$reservation,$venue,$plan]);
$r=dwRequest($lead,'ajax_update_plan.php',['akce'=>'move','plan_id'=>$plan,'datum'=>$day]);
dwCheck((json_decode($r['body'],true)['ok']??true)===false,'B05: venue-linked plan cannot be moved independently');
dwRequest($lead,'planovac.php',['action'=>'kopirovat_tyden','kopirovat_od'=>$newDay,'kopirovat_do'=>$newDay,'kopirovat_jen_moje'=>1]);
$q=$pdo->prepare('SELECT id,rezervace_id,serie_id,stav FROM planovane_treninky WHERE trener_id=? AND datum=?');$q->execute([$actor,date('Y-m-d',strtotime($newDay.' +7 days'))]);$copies=$q->fetchAll();
dwCheck(count($copies)===1 && !$copies[0]['rezervace_id'] && !$copies[0]['serie_id'] && $copies[0]['stav']==='planovany','B05: copied plan has no venue reservation or series link');
dwRequest($lead,'planovac.php',['action'=>'zrusit','plan_id'=>$plan,'zrusit_serii'=>1,'cancel_reason'=>$run.' cleanup']);
$q=$pdo->prepare('SELECT stav FROM planovane_treninky WHERE id IN (?,?)');$q->execute([$plan,$plan2]);
dwCheck($q->fetchAll(PDO::FETCH_COLUMN)===['zruseny','zruseny'],'B05: series cancellation cancels both original plans');
$q=$pdo->prepare('SELECT stav FROM planovane_treninky WHERE id=?');$q->execute([(int)$copies[0]['id']]);dwCheck($q->fetchColumn()==='planovany','B05: cancelling original series preserves independent copy');
dwRequest($lead,'planovac.php',['action'=>'zrusit','plan_id'=>$copies[0]['id'],'cancel_reason'=>$run.' cleanup']);
// Revocation must take effect on the next request using the same existing session.
$pdo->prepare("DELETE FROM staff_user_positions WHERE trainer_id=? AND position_code='sports_lead'")->execute([$actor]);
$r=dwRequest($lead,'prehled_trenera.php');dwCheck($r['status']===403,'B28: existing staff session loses removed position immediately');
dwInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>'sports_lead','is_default'=>0]);
$pdo->prepare('UPDATE treneri SET aktivni=0 WHERE id=?')->execute([$actor]);
$r=dwRequest($lead,'planovac.php');dwCheck(in_array($r['status'],[302,303,401,403],true),'B28: deactivated synthetic coach session is rejected');
$pdo->prepare('UPDATE treneri SET aktivni=1 WHERE id=?')->execute([$actor]);


// Demo parent/account fixtures are read-only here; cookie/session is newly owned.
$q=$pdo->query("SELECT id,session_version FROM verejni_uzivatele WHERE email='rodic@localhost.test' AND aktivni=1 AND email_overeno=1");$parent=$q->fetch();
if(!$parent)throw new RuntimeException('Owned demo parent fixture missing.');
session_id('');app_session_start();$_SESSION=[];auth_session_bind_public_user((int)$parent['id'],(int)$parent['session_version']);app_session_mark_authenticated();
$parentSession=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];session_write_close();
$catalog=dwRequest($parentSession,'booking/eshop.php');
$dom=dwDom($catalog);$card=$dom->query('//h3[normalize-space(.)="Fiktivní kniha o cyklistice"]/ancestor::*[contains(concat(" ",normalize-space(@class)," ")," card ")][1]')->item(0);
dwCheck($card!==null && str_contains($card->textContent,'441,00') && str_contains($card->textContent,'Klubové ceny pro váš účet'),'B12: eligible parent sees club price on the catalog card itself');
$anonymous=['cookie'=>'','csrf'=>''];$public=dwRequest($anonymous,'booking/eshop.php');$publicDom=dwDom($public);$publicCard=$publicDom->query('//h3[normalize-space(.)="Fiktivní kniha o cyklistice"]/ancestor::*[contains(concat(" ",normalize-space(@class)," ")," card ")][1]')->item(0);
dwCheck($publicCard!==null && str_contains($publicCard->textContent,'490,00') && !str_contains($publicCard->textContent,'Klubové ceny pro váš účet'),'B12: anonymous catalog retains public price');

echo json_encode(['status'=>'PASS','checks'=>count($checks),'evidence'=>$checks,'scope'=>'localhost synthetic MariaDB + HTTP; not browser UAT','production_writes'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
