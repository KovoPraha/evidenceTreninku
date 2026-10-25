<?php
declare(strict_types=1);

// Deliberately CLI-only. All writes target the dedicated synthetic B1 instance.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = dirname(__DIR__, 2);
require $app . '/config.php';
if (!defined('DB_NAME') || DB_NAME !== 'evidence_b1'
    || DB_HOST !== '127.0.0.1;port=33426'
    || rtrim((string)APP_BASE_URL, '/') !== 'http://127.0.0.1:18086') {
    throw new RuntimeException('Refusing any environment other than the dedicated B1 sandbox.');
}
$sandbox = getenv('LOCALAPPDATA') . '/EvidencePavelB1/finance-b1-20261022';
if (!is_dir($sandbox . '/sessions')) throw new RuntimeException('Prepare the B1 sandbox first.');
session_save_path($sandbox . '/sessions');
require_once $app . '/includes/auth_session.php';
require_once $app . '/includes/private_storage.php';
require_once $app . '/csrf_helper.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$run = 'LOCALHOST NEPLATIT B1 ' . bin2hex(random_bytes(5));
$results = [];
function b1Check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $GLOBALS['results'][] = ['scenario' => $label, 'status' => 'PASS'];
}
function b1Insert(string $table, array $data): int {
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')');
    $stmt->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}
function b1Session(int $trainer): array {
    session_id('');
    app_session_start();
    $_SESSION = [];
    auth_session_bind_trainer($trainer, 1);
    auth_session_refresh_trainer_authorization($GLOBALS['pdo'], $trainer);
    app_session_mark_authenticated();
    $data = ['cookie' => session_name() . '=' . session_id(), 'csrf' => csrf_token()];
    session_write_close();
    return $data;
}
function b1Request(array $session, string $route, ?array $post = null): array {
    $curl = curl_init('http://127.0.0.1:18086/' . $route);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIE => $session['cookie'], CURLOPT_TIMEOUT => 15, CURLOPT_PROXY => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP]);
    if ($post !== null) {
        $post += ['csrf_token' => $session['csrf']];
        $multipart = count(array_filter($post, static fn($v) => $v instanceof CURLFile)) > 0;
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
    }
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    // The HTTP worker changes files in another process; do not reuse CLI stat cache.
    clearstatcache(true);
    if (!is_string($body) || $status >= 500 || $status === 0) {
        throw new RuntimeException('HTTP transport/server failure ' . $route . ': ' . $status . ' ' . $error);
    }
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
}
function b1Row(string $table, int $id): array {
    $st = $GLOBALS['pdo']->prepare('SELECT * FROM ' . $table . ' WHERE id=?'); $st->execute([$id]);
    return $st->fetch() ?: [];
}
function b1Same(string $table, int $id, callable $request, string $label): array {
    $before = b1Row($table, $id); $result = $request();
    b1Check($before === b1Row($table, $id), $label);
    return $result;
}
function b1JsonOk(array $result, string $label): void {
    b1Check(($result['json']['status'] ?? null) === 'ok', $label);
}
function b1ReceiptFiles(): array {
    clearstatcache(true);
    $root=privateStorageRoot().'/receipts';
    $snapshot=[];
    if (is_dir($root)) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isFile()) $snapshot[substr($entry->getPathname(),strlen($root))]=hash_file('sha256',$entry->getPathname());
        }
    }
    ksort($snapshot);
    return $snapshot;
}

try {
    foreach (['uctenky','udalosti','vozidla','kreditni_obdobi','prehled_kreditu'] as $key) {
        $pdo->prepare('INSERT INTO opravneni(klic,nazev,min_role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE min_role=VALUES(min_role)')->execute([$key, 'LOCALHOST B1 '.$key, 'hlavni']);
    }
    $accounts = [];
    foreach (['finance_manager', 'coach', 'finance_manager'] as $i => $position) {
        $id = b1Insert('treneri', ['jmeno' => $run . ' ' . $i, 'email' => bin2hex(random_bytes(8)) . '@example.invalid',
            'heslo' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT), 'role' => 'admin', 'aktivni' => 1, 'session_version' => 1]);
        b1Insert('staff_user_positions', ['trainer_id' => $id, 'position_code' => $position, 'is_default' => 1]);
        $accounts[] = $id;
    }
    $finance = b1Session($accounts[0]); $coach = b1Session($accounts[1]); $revoked = b1Session($accounts[2]);
    $eventData = ['nazev'=>$run, 'datum_od'=>'2026-10-01', 'datum_do'=>'2026-10-31', 'typ'=>'jine', 'stav'=>'otevrena'];
    b1JsonOk(b1Request($finance, 'udalosti/uloz.php', $eventData), 'HTTP create open financial event');
    $st=$pdo->prepare('SELECT id FROM ucto_udalosti WHERE nazev=?'); $st->execute([$run]); $event=(int)$st->fetchColumn();
    $other=b1Insert('ucto_udalosti', ['nazev'=>$run.' second', 'datum_od'=>'2026-10-01','datum_do'=>'2026-10-31','typ'=>'jine','vytvoril_id'=>$accounts[0]]);
    $vehicle=b1Insert('ucto_vozidla',['znacka_model'=>$run]);
    $receiptData=['castka'=>'123.45','platba'=>'hotove_tym','kategorie'=>'ostatni','datum'=>'2026-10-02','udalost_id'=>$event,'poznamka'=>$run,'vozidlo_id'=>$vehicle];
    $png=$sandbox.'/receipt-http.png';
    file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
    b1JsonOk(b1Request($finance,'uctenky/uloz.php',$receiptData+['obrazek'=>new CURLFile($png,'image/png','LOCALHOST.png')]),'HTTP create receipt with real upload');
    $st=$pdo->prepare('SELECT id FROM ucto_uctenky WHERE poznamka=?');$st->execute([$run]);$receipt=(int)$st->fetchColumn();
    $key=b1Row('ucto_uctenky',$receipt)['obrazek_path'];$file=privateStorageResolve($key);
    b1Check(is_string($file)&&is_file($file),'Uploaded attachment exists in isolated private storage');
    $hash=hash_file('sha256',$file);
    b1JsonOk(b1Request($finance,'uctenky/uloz.php',array_replace($receiptData,['id'=>$receipt,'castka'=>'125.45'])),'HTTP edit receipt in open event');
    $disposableData=array_replace($receiptData,['poznamka'=>$run.' disposable']);
    b1JsonOk(b1Request($finance,'uctenky/uloz.php',$disposableData+['obrazek'=>new CURLFile($png,'image/png','delete.png')]),'Create disposable receipt for successful deletion');
    $st=$pdo->prepare('SELECT id,obrazek_path FROM ucto_uctenky WHERE poznamka=?');$st->execute([$disposableData['poznamka']]);$disposable=$st->fetch();
    $disposablePath=privateStorageResolve($disposable['obrazek_path']);
    $archives=glob(dirname($disposablePath).'/.deleted-*')?:[];
    b1Request($finance,'uctenky/smazat.php',['id'=>$disposable['id']]);
    $newArchives=array_values(array_diff(glob(dirname($disposablePath).'/.deleted-*')?:[],$archives));
    b1Check(b1Row('ucto_uctenky',(int)$disposable['id'])===[]&&!is_file($disposablePath),'Successful open receipt deletion removes live record/file');
    b1Check(count($newArchives)===1&&hash_file('sha256',$newArchives[0])===$hash,'Successful deletion preserves original bytes in hidden archive');
    // Force the real route to fail AFTER its file mutation, not just at validation.
    // Actor-scoped and confined to the dedicated sandbox; always remove the trigger.
    $trigger='b1_http_audit_failure_'.bin2hex(random_bytes(5));
    $pdo->exec('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON ucto_audit_log FOR EACH ROW BEGIN IF NEW.uzivatel_id='
        .$accounts[0]." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic B1 audit failure'; END IF; END");
    try {
        $filesBefore=b1ReceiptFiles();
        $res=b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/uloz.php',$receiptData+['id'=>$receipt,'obrazek'=>new CURLFile($png,'image/png','rollback.png')]),'Audit failure rolls back receipt replacement');
        b1Check(($res['json']['status']??'')==='error'&&is_file($file)&&hash_file('sha256',$file)===$hash,'Failed replacement restores original attachment bytes');
        b1Check(b1ReceiptFiles()===$filesBefore,'Failed replacement leaves no orphan or retired upload');
        b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/smazat.php',['id'=>$receipt]),'Audit failure rolls back receipt deletion');
        b1Check(is_file($file)&&hash_file('sha256',$file)===$hash,'Failed deletion restores original attachment bytes');
        b1Check(b1ReceiptFiles()===$filesBefore,'Failed deletion preserves complete file inventory');
    } finally {
        $pdo->exec('DROP TRIGGER '.$trigger);
    }
    $res=b1Same('ucto_uctenky',$receipt,fn()=>b1Request($coach,'uctenky/uloz.php',$receiptData+['id'=>$receipt]),'Coach cannot write through direct receipt URL');
    b1Check($res['status']===403,'Coach receipt request rejected by route permission');
    $res=b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/uloz.php',$receiptData+['id'=>$receipt,'csrf_token'=>'invalid']),'Receipt invalid CSRF preserves record');
    b1Check(str_contains($res['json']['message']??'','CSRF'),'Receipt explicitly rejects invalid CSRF');
    b1JsonOk(b1Request($finance,'udalosti/uzavrit.php',['id'=>$event]),'HTTP close financial event');
    $blocked=b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/uloz.php',$receiptData+['id'=>$receipt]),'Closed receipt edit blocked');
    b1Check(str_contains($blocked['json']['message']??'','uzavřen'),'Closed receipt explains state conflict');
    $count=(int)$pdo->query('SELECT COUNT(*) FROM ucto_uctenky')->fetchColumn();
    $blocked=b1Request($finance,'uctenky/uloz.php',$receiptData);
    b1Check(($blocked['json']['status']??'')==='error'&&$count===(int)$pdo->query('SELECT COUNT(*) FROM ucto_uctenky')->fetchColumn(),'Closed event refuses new receipt');
    b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/uloz.php',array_replace($receiptData,['id'=>$receipt,'udalost_id'=>$other])),'Move out of closed source blocked');
    $openReceipt=b1Insert('ucto_uctenky',['castka'=>50,'datum'=>'2026-10-01','udalost_id'=>$other,'platba'=>'hotove_tym','kategorie'=>'ostatni']);
    b1Same('ucto_uctenky',$openReceipt,fn()=>b1Request($finance,'uctenky/uloz.php',$receiptData+['id'=>$openReceipt]),'Move into closed target blocked');
    b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/smazat.php',['id'=>$receipt]),'Closed receipt deletion blocked');
    $filesBefore=b1ReceiptFiles();
    b1Same('ucto_uctenky',$receipt,fn()=>b1Request($finance,'uctenky/uloz.php',$receiptData+['id'=>$receipt,'obrazek'=>new CURLFile($png,'image/png','replacement.png')]),'Closed receipt file replacement blocked');
    b1Check(b1ReceiptFiles()===$filesBefore,'Closed receipt rejection leaves file inventory unchanged');
    b1Check(is_file($file)&&hash_file('sha256',$file)===$hash,'Rejected changes preserve attachment bytes');
    b1Same('ucto_udalosti',$event,fn()=>b1Request($finance,'udalosti/uloz.php',$eventData+['id'=>$event]),'Closed event cannot be edited or reopened');
    b1Same('ucto_udalosti',$event,fn()=>b1Request($finance,'udalosti/smazat.php',['id'=>$event]),'Closed event cannot be deleted');
    b1Same('ucto_udalosti',$event,fn()=>b1Request($finance,'udalosti/uzavrit.php',['id'=>$event]),'Repeated event close preserves row');
    b1Same('ucto_vozidla',$vehicle,fn()=>b1Request($finance,'vozidla/smazat.php',['id'=>$vehicle]),'Vehicle deletion cannot indirectly change closed receipt');
    b1Check((int)b1Row('ucto_uctenky',$receipt)['vozidlo_id']===$vehicle,'Closed receipt vehicle reference remains intact');
    $unusedVehicle=b1Insert('ucto_vozidla',['znacka_model'=>$run.' unused']);
    b1Request($finance,'vozidla/smazat.php',['id'=>$unusedVehicle]);
    b1Check(b1Row('ucto_vozidla',$unusedVehicle)===[],'Unused vehicle still deletable');

    $athlete=b1Insert('sportovci',['jmeno'=>'LOCALHOST','prijmeni'=>$run,'hash'=>bin2hex(random_bytes(20)),'narozeni'=>'2000-01-01','uci'=>0,'email'=>'b1@example.invalid']);
    $training=b1Insert('treninky',['datum'=>'2026-10-02','napln'=>$run]);
    b1Insert('trenink_sportovec',['trenink_id'=>$training,'sportovec_id'=>$athlete]);
    $periodData=['action'=>'save','sportovec_id'=>$athlete,'datum_od'=>'2026-10-01','datum_do'=>'','sazba_kc'=>'100'];
    $res=b1Request($finance,'sprava_sportovec_obdobi.php',$periodData);
    $st=$pdo->prepare('SELECT id FROM sportovec_obdobi WHERE sportovec_id=? ORDER BY id DESC LIMIT 1');$st->execute([$athlete]);$period=(int)$st->fetchColumn();
    b1Check($period===0&&str_contains($res['body'],'Výkazy a odměny'),'Legacy creation directs finance to canonical rewards without new period');
    $period=b1Insert('sportovec_obdobi',['sportovec_id'=>$athlete,'datum_od'=>'2026-10-01','datum_do'=>'2026-10-22','sazba_kc'=>120,'pocet_treninku'=>1,'castka_celkem'=>120]);
    b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'sprava_sportovec_obdobi.php',array_replace($periodData,['obdobi_id'=>$period,'sazba_kc'=>'999'])),'Closed reward edit rejected');
    b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'sprava_sportovec_obdobi.php',['action'=>'close','sportovec_id'=>$athlete,'obdobi_id'=>$period,'datum_do'=>'2026-12-31']),'Repeated reward close preserves snapshot');
    b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'prehled_kreditu.php',['action'=>'close','sportovec_id'=>$athlete,'obdobi_id'=>$period]),'Credit overview repeated close preserves snapshot');
    $pdo->prepare('UPDATE sportovec_obdobi SET vyplaceno=1 WHERE id=?')->execute([$period]);
    b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'sprava_sportovec_obdobi.php',$periodData+['obdobi_id'=>$period]),'Paid reward edit rejected');
    $legacy=b1Insert('sportovec_obdobi',['sportovec_id'=>$athlete,'datum_od'=>'2025-01-01','datum_do'=>'2025-12-31','sazba_kc'=>75]);
    b1Same('sportovec_obdobi',$legacy,fn()=>b1Request($finance,'prehled_kreditu.php',['action'=>'close','sportovec_id'=>$athlete,'obdobi_id'=>$legacy]),'Missing historical snapshot is not recomputed');
    $group=b1Insert('skupiny',['nazev'=>$run]);
    b1Insert('sportovec_skupina',['sportovec_id'=>$athlete,'skupina_id'=>$group]);
    $bulk=['ulozit_odmeny'=>1,'skupina_id'=>$group,'apply_all'=>'200'];
    b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'hromadne_odmeny.php',$bulk),'Bulk rate preserves closed and paid snapshot');
    $st=$pdo->prepare('SELECT id FROM sportovec_obdobi WHERE sportovec_id=? AND datum_do IS NULL ORDER BY id DESC LIMIT 1');$st->execute([$athlete]);$openPeriod=(int)$st->fetchColumn();
    b1Check($openPeriod===0,'Bulk legacy rates cannot create a competing reward workflow');
    $openPeriod=b1Insert('sportovec_obdobi',['sportovec_id'=>$athlete,'datum_od'=>'2026-10-23','sazba_kc'=>200]);
    $pdo->prepare('UPDATE sportovec_obdobi SET vyplaceno=1 WHERE id=?')->execute([$openPeriod]);
    b1Same('sportovec_obdobi',$openPeriod,fn()=>b1Request($finance,'hromadne_odmeny.php',array_replace($bulk,['apply_all'=>'300'])),'Bulk refuses inconsistent paid but open period');
    $page=b1Request($finance,'sprava_sportovec_obdobi.php?sportovec_id='.$athlete);
    b1Check($page['status']===200&&str_contains($page['body'],'Nevyřešeno: chybí souhrn'),'Historical snapshot issue visible in rendered page');
    foreach (['sprava_sportovec_obdobi.php?sportovec_id='.$athlete, 'prehled_kreditu.php', 'hromadne_odmeny.php?skupina_id='.$group] as $route) {
        $page=b1Request($finance,$route);
        $document=new DOMDocument();$previous=libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$page['body']);libxml_clear_errors();libxml_use_internal_errors($previous);
        $xpath=new DOMXPath($document);
        b1Check($page['status']===200&&$xpath->query('//div[@role="status"]//a[@href="club_rewards_admin.php"]')->length===1,'Legacy reward page links directly to canonical workflow: '.$route);
        b1Check($xpath->query('//div[contains(@class,"container py-4")]//form[translate(@method,"POST","post")="post"]//button[@type="submit" and not(@disabled)]')->length===0,'Legacy reward page offers no enabled obsolete submit: '.$route);
    }
    $res=b1Same('sportovec_obdobi',$period,fn()=>b1Request($coach,'sprava_sportovec_obdobi.php',$periodData+['obdobi_id'=>$period]),'Coach direct reward write denied');
    b1Check($res['status']===403,'Coach reward request rejected by route permission');
    $res=b1Same('sportovec_obdobi',$period,fn()=>b1Request($finance,'sprava_sportovec_obdobi.php',$periodData+['obdobi_id'=>$period,'csrf_token'=>'invalid']),'Reward invalid CSRF preserves row');
    b1Check(str_contains($res['body'],'Neplatný CSRF token'),'Reward explicitly rejects invalid CSRF');
    $pdo->prepare('UPDATE treneri SET aktivni=0 WHERE id=?')->execute([$accounts[2]]);
    $res=b1Same('ucto_uctenky',$openReceipt,fn()=>b1Request($revoked,'uctenky/uloz.php',array_replace($receiptData,['id'=>$openReceipt,'udalost_id'=>$other])),'Deactivated account cannot mutate receipt');
    b1Check($res['status']===303,'Deactivated account rejected by live authentication middleware');
    $pdo->prepare('DELETE FROM staff_user_positions WHERE trainer_id=?')->execute([$accounts[0]]);
    $res=b1Same('ucto_uctenky',$openReceipt,fn()=>b1Request($finance,'uctenky/uloz.php',array_replace($receiptData,['id'=>$openReceipt,'udalost_id'=>$other])),'Removed financial position blocks existing session');
    b1Check($res['status']===403,'Removed position rejected by central route guard');
    $st=$pdo->prepare('SELECT COUNT(*) FROM ucto_audit_log WHERE uzivatel_id=?');$st->execute([$accounts[0]]);
    b1Check((int)$st->fetchColumn()>=5,'Successful changes have audit evidence');
    $report=['status'=>'PASS','scenario_count'=>count($results),'scenarios'=>$results,
        'environment'=>'isolated MariaDB 127.0.0.1:33426 / evidence_b1; HTTP 127.0.0.1:18086',
        'scope'=>'Synthetic HTTP requests with real application session, route, CSRF and file handling; not human acceptance.'];
    file_put_contents($sandbox.'/http-acceptance.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
}
