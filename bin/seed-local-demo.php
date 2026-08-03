<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);$host=getenv('APP_HOST');
if(!is_string($host)||!in_array(strtolower($host),['localhost','127.0.0.1'],true)){fwrite(STDERR,"Pouziti: APP_HOST=localhost php bin/seed-local-demo.php\n");exit(64);}
$_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;

try{
    require_once $root.'/config.php';
    if(!defined('JE_LOKALNE')||JE_LOKALNE!==true)throw new RuntimeException('local_demo_refuses_non_local_environment');
    foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS']as$constant)if(!defined($constant))throw new RuntimeException('missing_database_configuration');
    require_once $root.'/includes/shop_catalog_review.php';
    require_once $root.'/includes/shop_catalog_promotion.php';
    require_once $root.'/includes/shop_catalog_publication.php';
    require_once $root.'/includes/account_person_role.php';
    require_once $root.'/includes/shop_coupon.php';
    require_once $root.'/includes/club_event_registration.php';
    require_once $root.'/includes/kis_roster.php';
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $adminLogin='localhost-admin';$adminPassword='LocalhostAdmin123!';$admin=$pdo->prepare('SELECT id FROM treneri WHERE jmeno=? ORDER BY id LIMIT 1');$admin->execute([$adminLogin]);$actorId=(int)$admin->fetchColumn();
    if($actorId<1){$pdo->prepare("INSERT INTO treneri(jmeno,email,heslo,role,aktivni) VALUES (?,?,?,'admin',1)")->execute([$adminLogin,'admin@localhost.test',password_hash($adminPassword,PASSWORD_DEFAULT)]);$actorId=(int)$pdo->lastInsertId();}
    else{$pdo->prepare("UPDATE treneri SET email=?,heslo=?,role='admin',aktivni=1 WHERE id=?")->execute(['admin@localhost.test',password_hash($adminPassword,PASSWORD_DEFAULT),$actorId]);}

    $email='rodic@localhost.test';$password='Localhost123!';
    $account=$pdo->prepare('SELECT id FROM verejni_uzivatele WHERE email=?');$account->execute([$email]);$accountId=(int)$account->fetchColumn();
    if($accountId<1){
        $insert=$pdo->prepare('INSERT INTO verejni_uzivatele(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni) VALUES (?,?,?,?,1,1)');
        $insert->execute(['Testovací','Rodič',$email,password_hash($password,PASSWORD_DEFAULT)]);$accountId=(int)$pdo->lastInsertId();
    }else{
        $pdo->prepare('UPDATE verejni_uzivatele SET jmeno=?,prijmeni=?,heslo_hash=?,email_overeno=1,aktivni=1 WHERE id=?')->execute(['Testovací','Rodič',password_hash($password,PASSWORD_DEFAULT),$accountId]);
    }
    $people=$pdo->query("SELECT id FROM sportovci ORDER BY CASE stav_clenstvi WHEN 'aktivni' THEN 0 ELSE 1 END,id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    foreach($people as$personId)accountPersonRoleApprove($pdo,$accountId,(int)$personId,'guardian',$actorId,'Localhost demo vazba rodič–dítě.');

    $run=$pdo->query("SELECT * FROM shop_catalog_import_runs WHERE status IN ('pending_review','ready_for_promotion','promoted') ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$run)throw new RuntimeException('local_demo_requires_staged_shoptet_import');$runId=(int)$run['id'];
    if($run['status']!=='promoted'){
        $demoCandidate=$pdo->prepare('SELECT id FROM shop_catalog_product_candidates WHERE run_id=? AND external_product_key=?');$demoCandidate->execute([$runId,'local-demo:club-event']);$demoCandidateId=(int)$demoCandidate->fetchColumn();
        if($demoCandidateId<1){
            $productPayload=['short_description'=>'Bezplatný testovací kroužek pouze pro localhost.','description_html_untrusted'=>null,'default_category_path'=>'LOCALHOST','visibility'=>'visible','item_type'=>'product','additional_category_paths'=>[],'images'=>[]];
            $insert=$pdo->prepare("INSERT INTO shop_catalog_product_candidates(run_id,external_product_key,source_pair_code,name,offer_type,classification_confidence,needs_manual_review,payload_json,review_status) VALUES (?,?,?,'LOCALHOST – bezplatný kroužek','club_event','high',0,?,'auto_classified')");
            $insert->execute([$runId,'local-demo:club-event','LOCAL-DEMO-CLUB',json_encode($productPayload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$demoCandidateId=(int)$pdo->lastInsertId();
            $variantPayload=['ean'=>null,'attributes'=>['Varianta'=>'Testovací'],'price'=>['compare_at_amount_minor'=>null,'includes_vat'=>false,'vat_rate_basis_points'=>null],'stock'=>['availability_in_stock'=>'Dostupné','availability_out_of_stock'=>'Nedostupné'],'unit'=>['code'=>'person'],'fulfillment'=>['free_shipping'=>true,'free_billing'=>true],'visible'=>true];
            $pdo->prepare("INSERT INTO shop_catalog_variant_candidates(run_id,product_candidate_id,sku,price_mode,amount_minor,currency,stock_quantity_decimal,payload_json) VALUES (?,?,?,'free',0,'CZK',NULL,?)")->execute([$runId,$demoCandidateId,'LOCAL-DEMO-CLUB-FREE',json_encode($variantPayload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $pdo->prepare('UPDATE shop_catalog_import_runs SET product_count=product_count+1,variant_count=variant_count+1 WHERE id=?')->execute([$runId]);
        }
        $pending=$pdo->prepare("SELECT id FROM shop_catalog_product_candidates WHERE run_id=? AND review_status='pending'");$pending->execute([$runId]);
        foreach($pending->fetchAll(PDO::FETCH_COLUMN)as$candidateId)shopCatalogReviewProduct($pdo,$runId,(int)$candidateId,$actorId,'exclude',null,'Localhost demo: neklasifikovaný produkt zůstává bezpečně vyřazen.');
        shopCatalogPromote($pdo,$runId,$actorId,true);
    }

    $goods=$pdo->query("SELECT p.id,p.name FROM shop_products p JOIN shop_variants v ON v.product_id=p.id WHERE p.offer_type='goods' AND (v.visible=1 OR v.visible IS NULL) AND v.price_mode='fixed' AND v.amount_minor>0 AND (v.stock_quantity_decimal IS NULL OR v.stock_quantity_decimal>0) ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!$goods)throw new RuntimeException('local_demo_goods_missing');
    shopCatalogPublicationActivate($pdo,(int)$goods['id'],$actorId,(string)$goods['name'],'Testovací produkt na localhostu. Objednávka ani QR nejsou určeny ke skutečné platbě.','Localhost demo aktivace.',true);
    $pdo->prepare("UPDATE shop_variants SET visible=0,catalog_status='inactive' WHERE product_id=? AND stock_quantity_decimal IS NOT NULL AND stock_quantity_decimal<=0")->execute([(int)$goods['id']]);

    $coupon=$pdo->prepare('SELECT id FROM shop_coupons WHERE code=?');$coupon->execute(['LOCAL10']);
    if(!$coupon->fetchColumn())shopCouponAdminCreate($pdo,$actorId,'LOCAL10','percentage',1000,0,50000,100,'','','Localhost demo kupón 10 %.',true);

    $event=$pdo->prepare('SELECT id,status FROM club_events WHERE code=?');$event->execute(['LOCALHOST-KROUZEK']);$event=$event->fetch(PDO::FETCH_ASSOC);
    if(!$event){
        $now=new DateTimeImmutable('now');$regEnd=$now->modify('+6 days');$deadline=$now->modify('+7 days');$start=$now->modify('+14 days')->setTime(16,0);$end=$start->modify('+90 minutes');
        $created=clubEventCreateDraft($pdo,$actorId,['code'=>'LOCALHOST-KROUZEK','event_type'=>'club_event','name'=>'LOCALHOST – bezplatný testovací kroužek','description_plain'=>'Bezplatná demonstrační akce pro test rodiče a dítěte.','audience_label'=>'Testovací děti','min_age'=>'','max_age'=>'','capacity'=>2,'pricing_policy'=>'free','currency'=>'CZK','registration_starts_at'=>$now->modify('-1 day')->format('Y-m-d\TH:i'),'registration_ends_at'=>$regEnd->format('Y-m-d\TH:i')]);
        $eventId=(int)$created['id'];clubEventAddSession($pdo,$eventId,$actorId,$start->format('Y-m-d\TH:i'),$end->format('Y-m-d\TH:i'),'Velodrom – LOCALHOST TEST',2);
        $clubProduct=(int)$pdo->query("SELECT id FROM shop_products WHERE external_product_key='local-demo:club-event'")->fetchColumn();
        clubEventLinkProduct($pdo,$eventId,$clubProduct,$actorId,'Localhost demo vazba produktu a kroužku.');
        clubEventConfigureRegistrationTerms($pdo,$eventId,$actorId,'local-v1','Souhlasím s účastí v testovacím kroužku.','Přihlášku lze v testu zrušit do uvedeného termínu.',$deadline->format('Y-m-d\TH:i'),true);
        clubEventOpenFreeRegistration($pdo,$eventId,$actorId,'Otevření localhost demo registrace.',true);
    }

    $schoolSeason=kisRosterCreateSeason($pdo,$actorId,'SCHOOL-2026-27','LOCALHOST školní rok 2026/27','2026-09-01','2027-08-31','school_year');
    $raceSeason=kisRosterCreateSeason($pdo,$actorId,'RACE-2026','LOCALHOST závodní rok 2026','2026-01-01','2026-12-31','calendar_year');
    $nextRaceSeason=kisRosterCreateSeason($pdo,$actorId,'RACE-2027','LOCALHOST závodní rok 2027','2027-01-01','2027-12-31','calendar_year');
    $hobbySeries=kisRosterCreateSeries($pdo,$actorId,'LOCAL-KROUZEK','LOCALHOST Kroužek','hobby','school_year','renewal_required');
    $u17Series=kisRosterCreateSeries($pdo,$actorId,'LOCAL-U17','LOCALHOST U17','age','calendar_year','manual',null,15,16);
    $u15Series=kisRosterCreateSeries($pdo,$actorId,'LOCAL-U15','LOCALHOST U15','age','calendar_year','age_progression',(int)$u17Series['id'],13,14);
    $trackSeries=kisRosterCreateSeries($pdo,$actorId,'LOCAL-DRAHA','LOCALHOST Dráhová cyklistika','discipline','calendar_year','carry_forward');
    $hobbyTeam=kisRosterCreateSeriesTeam($pdo,(int)$hobbySeries['id'],(int)$schoolSeason['id'],$actorId,'LOCAL-KROUZEK-2026','LOCALHOST Kroužek 2026/27','Všeobecná příprava','Kroužek','Localhost školní soupiska.');
    $team=kisRosterCreateSeriesTeam($pdo,(int)$u15Series['id'],(int)$raceSeason['id'],$actorId,'LOCAL-U15-2026','LOCALHOST U15 2026','Závodní cyklistika','U15','Localhost věková soupiska.');
    $trackTeam=kisRosterCreateSeriesTeam($pdo,(int)$trackSeries['id'],(int)$raceSeason['id'],$actorId,'LOCAL-DRAHA-2026','LOCALHOST Dráha 2026','Dráha','Bez věkového přesunu','Localhost disciplínová soupiska.');
    kisRosterCreateSeriesTeam($pdo,(int)$u17Series['id'],(int)$nextRaceSeason['id'],$actorId,'LOCAL-U17-2027','LOCALHOST U17 2027','Závodní cyklistika','U17','Cíl localhost věkového preview.');
    kisRosterCreateSeriesTeam($pdo,(int)$trackSeries['id'],(int)$nextRaceSeason['id'],$actorId,'LOCAL-DRAHA-2027','LOCALHOST Dráha 2027','Dráha','Bez věkového přesunu','Cíl localhost carry-forward preview.');
    if(isset($people[0])){kisRosterAddMember($pdo,(int)$team['id'],(int)$people[0],$actorId,'manual','2026-01-01','Localhost věkové zařazení.');kisRosterAddMember($pdo,(int)$trackTeam['id'],(int)$people[0],$actorId,'manual','2026-01-01','Localhost disciplínové zařazení.');}
    if(isset($people[1]))kisRosterAddMember($pdo,(int)$hobbyTeam['id'],(int)$people[1],$actorId,'manual','2026-09-01','Localhost kroužkové zařazení.');

    echo json_encode(['ok'=>true,'customer_login_url'=>'http://localhost/evidencePavel/booking/prihlaseni.php','customer_email'=>$email,'customer_password'=>$password,'admin_login_url'=>'http://localhost/evidencePavel/login.php','admin_login'=>$adminLogin,'admin_password'=>$adminPassword,'coupon'=>'LOCAL10','account_id'=>$accountId,'linked_people'=>array_map('intval',$people),'published_product'=>(string)$goods['name'],'kis_demo_team'=>(string)$team['name'],'notice'=>'LOCALHOST TEST - NEPLATIT'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $exception){error_log('seed-local-demo.php: '.$exception->getMessage());fwrite(STDERR,"Localhost demo seed selhal: ".$exception->getMessage()."\n");exit(1);}
