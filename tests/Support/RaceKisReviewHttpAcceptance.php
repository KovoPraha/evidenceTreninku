<?php
declare(strict_types=1);
// Synthetic fixtures only. Never point this script at another environment.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root.'/config.php';
if (DB_NAME !== 'evidence_b1' || DB_HOST !== '127.0.0.1;port=33437' || APP_BASE_URL !== 'http://127.0.0.1:18097') {
    throw new RuntimeException('Owned B1 sandbox required.');
}
$runtime = getenv('LOCALAPPDATA').'/EvidencePavelB1/b04-b23-20261025';
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
if ((int)$identity['port'] !== 33437 || strtolower(str_replace('\\','/',$identity['path'])) !== strtolower(str_replace('\\','/',$runtime.'/mysql/'))) {
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
function dwSession(int $id, string $position = 'coach'): array {
    session_id(''); app_session_start(); $_SESSION = [];
    $v=$GLOBALS['pdo']->prepare('SELECT session_version FROM treneri WHERE id=?');$v->execute([$id]); auth_session_bind_trainer($id,(int)$v->fetchColumn());
    auth_session_refresh_trainer_authorization($GLOBALS['pdo'],$id);
    $_SESSION['staff_active_position']=$position; app_session_mark_authenticated();
    $result = ['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];
    session_write_close();
    return $result;
}
function dwRequest(array &$session, string $path, ?array $post = null): array {
    if (!preg_match('~^(?:booking/)?[a-z_]+\.php(?:\?.*)?$~D', $path)) throw new RuntimeException('Local PHP route required.');
    $location = '';
    $curl = curl_init('http://127.0.0.1:18097/'.$path);
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

$actor=(int)$pdo->query("SELECT id FROM treneri WHERE jmeno='localhost-admin'")->fetchColumn();
if(!$actor)throw new RuntimeException('Seeded synthetic admin required.');
$race=(int)$pdo->query("SELECT id FROM zavody WHERE popis='LOCALHOST B04 výsledky'")->fetchColumn();
if(!$race)throw new RuntimeException('Seeded synthetic race required.');
$before=$pdo->query('SELECT * FROM race_result_revisions ORDER BY id')->fetchAll();
$canonicalBefore=[];
foreach(['sportovci','club_roster_members','kis_review_plans','kis_review_events'] as $table)$canonicalBefore[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();
$outsider=dwInsert('treneri',['jmeno'=>$run.' coach','email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'trener','aktivni'=>1,'session_version'=>1]);
dwInsert('staff_user_positions',['trainer_id'=>$outsider,'position_code'=>'coach','is_default'=>1]);
$coach=dwSession($outsider);
foreach(['zavod_vysledky.php?id='.$race,'kis_review_admin.php?run_id=1'] as $path){
 foreach([null,['action'=>'apply','confirm'=>'1','fingerprint'=>str_repeat('a',64)]] as $post){
  $page=dwRequest($coach,$path,$post);dwCheck(in_array($page['status'],[403,303],true)&&!str_contains($page['body'],'name="payload"'),'Ordinary coach denied '.$path.($post?' POST':' GET'));
 }
}
$sports=dwSession($actor,'sports_lead');
$page=dwRequest($sports,'zavod_vysledky.php?id='.$race);
dwCheck($page['status']===200&&str_contains($page['body'],'Historie revizí'),'Sports lead can inspect result revisions');
$page=dwRequest($sports,'zavod_vysledky.php?id='.$race,['action'=>'published','csrf_token'=>'wrong','confirm'=>'1']);
dwCheck(str_contains($page['body'],'Neplatný bezpečnostní token.'),'Race CSRF rejected');
$page=dwRequest($sports,'zavod_vysledky.php?id='.$race,['action'=>'preview','version'=>-1,'complete_form'=>'1']);
dwCheck(str_contains($page['body'],'Obnovte formulář'),'Stale editing form rejected');
$page=dwRequest($sports,'zavod_vysledky.php?id='.$race,['action'=>'preview']);
dwCheck(str_contains($page['body'],'Formulář nebyl přenesen celý'),'Truncated result form rejected');
$page=dwRequest($sports,'zavod_vysledky.php?id=99999999');
dwCheck($page['status']===404,'Missing race is not exposed');
$registrar=dwSession($actor,'registrar');
$page=dwRequest($registrar,'kis_sync_center.php?run_id=1');
dwCheck($page['status']===200&&str_contains($page['body'],'kis_review_admin.php?run_id=1&amp;fingerprint='),'Handoff binds run and complete source fingerprint');
$page=dwRequest($registrar,'kis_review_admin.php?run_id=1',['action'=>'preview','csrf_token'=>'wrong']);
dwCheck(str_contains($page['body'],'Neplatný bezpečnostní token.'),'KIS CSRF rejected');
$page=dwRequest($registrar,'kis_review_admin.php?run_id=1',['action'=>'preview']);
dwCheck(str_contains($page['body'],'Formulář nebyl přenesen celý'),'Truncated KIS form rejected');
$page=dwRequest($registrar,'kis_review_admin.php?run_id=1&fingerprint='.str_repeat('0',64),['action'=>'preview']);
dwCheck(str_contains($page['body'],'Předaný běh se změnil'),'Wrong handoff fingerprint rejected before POST');
$page=dwRequest($registrar,'sync_evidence.php',['action'=>'execute']);
dwCheck(str_contains($page['body'],'Přímá synchronizace byla bezpečně vyřazena.'),'Legacy direct apply remains retired');
dwCheck($before===$pdo->query('SELECT * FROM race_result_revisions ORDER BY id')->fetchAll(),'Negative HTTP checks did not modify results');
foreach($canonicalBefore as $table=>$rows)dwCheck($rows===$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(),'Negative HTTP checks did not modify '.$table);
echo json_encode(['status'=>'PASS','checks'=>count($checks),'evidence'=>$checks,'production_writes'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;

