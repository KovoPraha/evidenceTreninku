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
$run = 'LOCALHOST NEPLATIT MAKEUP '.bin2hex(random_bytes(5));
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
require_once $root.'/includes/club_makeup.php';
function mjFamilySession(int $id): array {
    session_id(''); app_session_start(); $_SESSION=[];
    auth_session_bind_public_user($id,1); app_session_mark_authenticated();
    $result=['cookie'=>session_name().'='.session_id(),'csrf'=>csrf_token()];
    session_write_close(); return $result;
}
function mjCredit(int $person, int $plan): array {
    $q=$GLOBALS['pdo']->prepare('SELECT * FROM club_makeup_credits WHERE sportovec_id=? AND source_plan_id=?');
    $q->execute([$person,$plan]); return $q->fetch()?:[];
}
$actor=dwInsert('treneri',['jmeno'=>$run,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'role'=>'admin','aktivni'=>1,'session_version'=>1]);
dwInsert('staff_user_positions',['trainer_id'=>$actor,'position_code'=>'sports_lead','is_default'=>1]);
$lead=dwSession($actor);
$season=dwInsert('club_seasons',['code'=>bin2hex(random_bytes(8)),'name'=>$run,'starts_on'=>'2020-01-01','ends_on'=>'2099-12-31','status'=>'active','created_by_trainer_id'=>$actor]);
$start=date('Y-m-d',strtotime('-1 month')); $end=date('Y-m-d',strtotime('+1 month'));
$past=date('Y-m-d',strtotime('-2 days')); $future=date('Y-m-d',strtotime('+3 days'));
$offers=[]; $teams=[];
foreach (['source','target'] as $kind) {
    $teams[$kind]=dwInsert('club_teams',['season_id'=>$season,'code'=>$kind,'name'=>$run.' '.$kind,'discipline'=>'cyklistika','age_label'=>'TEST','status'=>'active','created_by_trainer_id'=>$actor]);
    $program=dwInsert('club_programs',['code'=>bin2hex(random_bytes(8)),'name'=>$run.' '.$kind,'created_by_trainer_id'=>$actor]);
    $product=dwInsert('shop_products',['external_product_key'=>bin2hex(random_bytes(8)),'name'=>$run.' '.$kind,'offer_type'=>'program','catalog_status'=>'active']);
    $variant=dwInsert('shop_variants',['product_id'=>$product,'sku'=>bin2hex(random_bytes(8)),'attributes_json'=>'{}','price_mode'=>'fixed','amount_minor'=>10000,'currency'=>'CZK','stock_quantity_decimal'=>null,'unit_code'=>'person','visible'=>1,'catalog_status'=>'active']);
    $offer=dwInsert('club_program_offers',['program_id'=>$program,'season_id'=>$season,'team_id'=>$teams[$kind],'product_id'=>$product,'variant_id'=>$variant,'code'=>bin2hex(random_bytes(8)),'name'=>$run.' '.$kind,'starts_on'=>$start,'ends_on'=>$end,'status'=>'active','birth_year_from'=>2016,'birth_year_to'=>2018,'created_by_trainer_id'=>$actor]);
    $offers[$kind]=compact('offer','product','variant');
    clubMakeupPolicySave($pdo,$actor,$program,$season,$teams[$kind],true,12,false);
    clubMakeupOfferPeriodSave($pdo,$actor,$offer,'single',null);
}
$training=dwInsert('treninky',['datum'=>$past,'napln'=>$run,'delka'=>1,'kategorie'=>'draha']);
dwInsert('trenink_trener',['trenink_id'=>$training,'trener_id'=>$actor]);
$source=dwInsert('planovane_treninky',['trener_id'=>$actor,'datum'=>$past,'cas_od'=>'15:00','cas_do'=>'16:00','nazev'=>$run.' source','stav'=>'planovany','trenink_id'=>$training]);
$target=dwInsert('planovane_treninky',['trener_id'=>$actor,'datum'=>$future,'cas_od'=>'15:00','cas_do'=>'16:00','nazev'=>$run.' target']);
foreach (['source'=>$source,'target'=>$target] as $kind=>$plan) {
    dwInsert('training_roster_links',['plan_id'=>$plan,'team_id'=>$teams[$kind],'target_date'=>$kind==='source'?$past:$future,'team_code_snapshot'=>$kind,'team_name_snapshot'=>$run,'created_by_trainer_id'=>$actor]);
    clubMakeupSyncPlanRules($pdo,$plan);
}
// Preserve the policy snapshot that existed while the historical fixture was planned.
$pdo->prepare("UPDATE planovane_treninky SET stav='evidovany' WHERE id=?")->execute([$source]);
$people=[]; $parents=[]; $relations=[]; $family=[];
foreach ([1,2] as $n) {
    $person=dwInsert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' '.$n,'narozeni'=>'2017-01-02','stav_clenstvi'=>'aktivni','hash'=>bin2hex(random_bytes(32)),'uci'=>0,'email'=>'']);
    $parent=dwInsert('verejni_uzivatele',['jmeno'=>'LOCALHOST','prijmeni'=>$run.' parent'.$n,'email'=>bin2hex(random_bytes(8)).'@example.invalid','heslo_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'aktivni'=>1,'email_overeno'=>1]);
    $relations[$n]=dwInsert('account_person_roles',['account_id'=>$parent,'sportovec_id'=>$person,'relation_role'=>'guardian','status'=>'approved','valid_from'=>$start,'created_by_trainer_id'=>$actor,'approved_by_trainer_id'=>$actor,'decision_note'=>$run]);
    dwInsert('club_roster_members',['team_id'=>$teams['source'],'sportovec_id'=>$person,'status'=>'active','source'=>'manual','valid_from'=>$start,'created_by_trainer_id'=>$actor]);
    // Paid enrollment is a fixture precondition, not a new payment test or external transaction.
    $cart=dwInsert('shop_carts',['cart_key'=>bin2hex(random_bytes(16)),'account_id'=>$parent,'status'=>'converted']);
    $order=dwInsert('shop_orders',['public_code'=>'MJ'.bin2hex(random_bytes(8)),'account_id'=>$parent,'source_cart_id'=>$cart,'idempotency_key_hash'=>bin2hex(random_bytes(32)),'status'=>'paid','payment_status'=>'paid','fulfillment_method'=>'pickup','customer_name_snapshot'=>$run,'customer_email_snapshot'=>'test@example.invalid','subtotal_minor'=>10000,'total_minor'=>10000,'currency'=>'CZK','placed_at'=>$start.' 12:00:00']);
    $item=dwInsert('shop_order_items',['order_id'=>$order,'product_id'=>$offers['source']['product'],'variant_id'=>$offers['source']['variant'],'product_name_snapshot'=>$run,'sku_snapshot'=>$run,'attributes_json_snapshot'=>'{}','quantity'=>1,'unit_amount_minor'=>10000,'line_amount_minor'=>10000,'currency'=>'CZK']);
    dwInsert('club_program_enrollments',['offer_id'=>$offers['source']['offer'],'sportovec_id'=>$person,'account_id'=>$parent,'source_order_item_id'=>$item,'status'=>'active','valid_from'=>$start,'valid_to'=>$end,'activated_at'=>$start.' 12:00:00']);
    $people[$n]=$person; $parents[$n]=$parent; $family[$n]=mjFamilySession($parent);
}
$slot=['action'=>'makeup_slot_save','offer_id'=>$offers['target']['offer'],'capacity'=>1,'birth_year_from'=>2016,'birth_year_to'=>2018];
$route='training_rsvps_admin.php?plan_id='.$target;
dwCheck(dwRequest($lead,$route)['status']===200,'Sports lead opens actual replacement-slot form');
dwRequest($lead,$route,['csrf_token'=>'forged']+$slot);
dwCheck((int)$pdo->query('SELECT COUNT(*) FROM club_makeup_slots WHERE plan_id='.$target)->fetchColumn()===0,'Forged slot CSRF creates no slot');
dwCheck(dwRequest($lead,$route,$slot)['status']===303,'Actual HTTP form enables one replacement place');
foreach ($people as $n=>$person) {
    $excuse=['action'=>'personal_excuse','plan_id'=>$source,'person_id'=>$person,'received_at'=>date('Y-m-d',strtotime('-3 days')).'T08:00','source'=>'paper','reason'=>$run];
    dwCheck(dwRequest($lead,'training_closure_admin.php',$excuse)['status']===303,'Personal paper excuse recorded for child '.$n);
    dwCheck(mjCredit($person,$source)===[],'Draft attendance grants no replacement credit for child '.$n);
}
$close=['training_id'=>$training,'version'=>0,'action'=>'close','actual_started_at'=>$past.'T15:00','actual_ended_at'=>$past.'T16:00','participant_ids'=>[],'coach_ids'=>[$actor],'reason'=>$run,'record_source'=>'paper','confirm_action'=>'1'];
dwCheck(dwRequest($lead,'training_closure_admin.php',$close)['status']===303,'Closing actual source lesson issues eligible credits');
$credit1=mjCredit($people[1],$source); $credit2=mjCredit($people[2],$source);
dwCheck(($credit1['status']??'')==='available'&&($credit2['status']??'')==='available','Each timely absent paid child receives one available credit');
$reserve=['action'=>'reserve','person_id'=>$people[1],'credit_id'=>$credit1['id'],'plan_id'=>$target];
$familyRoute='booking/nahrady.php?person_id='.$people[1];
dwCheck(dwRequest($family[1],$familyRoute)['status']===200,'Parent can open own child replacement page');
dwCheck(dwRequest($family[2],$familyRoute)['status']===403,'Different parent cannot read foreign child');
dwCheck(dwRequest($family[2],'booking/nahrady.php',$reserve)['status']===403,'Different parent cannot reserve for foreign child');
dwRequest($family[1],'booking/nahrady.php',['csrf_token'=>'forged']+$reserve);
dwCheck(mjCredit($people[1],$source)['status']==='available','Forged family CSRF does not spend a credit');
dwCheck(dwRequest($family[1],'booking/nahrady.php',$reserve)['status']===303,'Parent reserves the replacement using HTTP');
$reservation=(int)$pdo->query('SELECT id FROM club_makeup_reservations WHERE credit_id='.(int)$credit1['id'].' ORDER BY id DESC LIMIT 1')->fetchColumn();
dwCheck(mjCredit($people[1],$source)['status']==='reserved','Reservation consumes availability but not actual attendance');
dwRequest($family[1],'booking/nahrady.php',$reserve);
dwCheck((int)$pdo->query('SELECT COUNT(*) FROM club_makeup_reservations WHERE credit_id='.(int)$credit1['id'])->fetchColumn()===1,'Double reservation request cannot spend credit twice');
$reserve2=['action'=>'reserve','person_id'=>$people[2],'credit_id'=>$credit2['id'],'plan_id'=>$target];
$full=dwRequest($family[2],'booking/nahrady.php',$reserve2);
dwCheck($full['status']===200&&str_contains($full['body'],'Kapacita náhradníků je obsazená.')&&mjCredit($people[2],$source)['status']==='available','Full replacement capacity rejects second child without spending credit');
$cancel=['action'=>'cancel','person_id'=>$people[1],'reservation_id'=>$reservation];
dwCheck(dwRequest($family[1],'booking/nahrady.php',$cancel)['status']===303,'Timely cancellation returns the credit');
dwCheck(mjCredit($people[1],$source)['status']==='available'&&$pdo->query('SELECT status FROM club_makeup_reservations WHERE id='.$reservation)->fetchColumn()==='cancelled','Cancellation retains history and restores availability');
dwCheck(dwRequest($family[2],'booking/nahrady.php',$reserve2)['status']===303,'Released place becomes available to second eligible child');
$pdo->prepare("UPDATE account_person_roles SET status='revoked' WHERE id=?")->execute([$relations[2]]);
dwCheck(dwRequest($family[2],'booking/nahrady.php?person_id='.$people[2])['status']===403,'Revoked guardian relation blocks existing parent session immediately');
$secondReservation=(int)$pdo->query('SELECT id FROM club_makeup_reservations WHERE credit_id='.(int)$credit2['id'].' ORDER BY id DESC LIMIT 1')->fetchColumn();
dwCheck(dwRequest($family[2],'booking/nahrady.php',['action'=>'cancel','person_id'=>$people[2],'reservation_id'=>$secondReservation])['status']===403,'Revoked guardian cannot cancel through a stale form');
dwCheck(mjCredit($people[2],$source)['status']==='reserved','Rejected stale request leaves reservation unchanged');
$report=['status'=>'PASS','run'=>$run,'checks'=>count($checks),'passed'=>$checks,'source_plan_id'=>$source,'target_plan_id'=>$target,'production_mutations'=>false,'external_sends'=>false];
file_put_contents($runtime.'/makeup-journey-http.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
