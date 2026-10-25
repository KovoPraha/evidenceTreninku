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

$actor=dwInsert('treneri',['jmeno'=>$run.' owner <actor>','email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'trener','aktivni'=>1,'session_version'=>1]);
dwInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>'coach','is_default'=>1]);
$session=dwSession($actor);
$venue=dwInsert('sportovist',['kod'=>bin2hex(random_bytes(6)),'nazev'=>$run,'je_verejne'=>1,'max_kapacita'=>5]);
$lesson=dwInsert('individualni_lekce',['trener_id'=>$actor,'sportoviste_id'=>$venue,'datum'=>date('Y-m-d',strtotime('+10 days')),'cas_od'=>'10:00','cas_do'=>'11:00','typ'=>'zluta','nazev'=>$run.' <lesson>','max_osob'=>1]);
dwInsert('venue_operation_events',['target_type'=>'lesson','target_id'=>$lesson,'actor_trainer_id'=>$actor,'action'=>'reservation_confirmed','reason'=>$run.' <script>alert(1)</script>','payload_json'=>'{"private":"DO_NOT_EXPOSE_PAYLOAD"}']);
$before=$pdo->query('SELECT * FROM venue_operation_events ORDER BY id')->fetchAll();
$page=dwRequest($session,'individualni_lekce_sprava.php?history_lesson_id='.$lesson);
dwCheck($page['status']===200,'Owner can view lesson history');
dwCheck(str_contains($page['body'],'&lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($page['body'],'<script>alert(1)</script>'),'Reason is escaped');
dwCheck(str_contains($page['body'],'&lt;actor&gt;')&&str_contains($page['body'],'&lt;lesson&gt;'),'Actor and lesson names are escaped');
dwCheck(!str_contains($page['body'],'DO_NOT_EXPOSE_PAYLOAD'),'Private payload is omitted');
dwCheck($before===$pdo->query('SELECT * FROM venue_operation_events ORDER BY id')->fetchAll(),'Reading history does not write events');
$other=dwInsert('treneri',['jmeno'=>$run.' outsider','email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'trener','aktivni'=>1,'session_version'=>1]);
dwInsert('staff_user_positions',['trainer_id'=>$other,'position_code'=>'coach','is_default'=>1]);
$outsider=dwSession($other);
foreach(['','&vse=1'] as $suffix){$page=dwRequest($outsider,'individualni_lekce_sprava.php?history_lesson_id='.$lesson.$suffix);dwCheck($page['status']===404&&!str_contains($page['body'],$run),'Foreign coach cannot inspect guessed history '.$suffix);}
echo json_encode(['status'=>'PASS','checks'=>count($checks),'evidence'=>$checks,'production_writes'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
