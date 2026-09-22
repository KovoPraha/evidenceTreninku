<?php
declare(strict_types=1);

function kisUatRequire(string $relativePath): void
{
    $root = realpath((string)getenv('APP_ROOT')) ?: dirname(__DIR__);
    $path = $root . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) throw new RuntimeException('Chybí knihovna UAT: ' . $relativePath);
    require_once $path;
}

kisUatRequire('includes/password_security.php');
kisUatRequire('includes/account_person_role.php');
kisUatRequire('includes/child_access.php');
kisUatRequire('includes/shop_manual_catalog.php');
kisUatRequire('includes/club_program.php');
kisUatRequire('includes/club_program_terms.php');
kisUatRequire('includes/club_calendar.php');

const KIS_UAT_PARENT_EMAILS = ['tester.karel@velocota.com', 'tester.petra@velocota.com'];
const KIS_UAT_CHILD_LOGINS = ['tester.ema', 'tester.adam'];

/** @param array<string,mixed> $settings @return array{password:string,window_end:DateTimeImmutable} */
function kisUatValidateSettings(array $settings, ?DateTimeImmutable $now = null): array
{
    $password = (string)($settings['password'] ?? '');
    passwordPolicyValidate($password);
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    try {
        $windowEnd = new DateTimeImmutable((string)($settings['window_end'] ?? ''), new DateTimeZone('Europe/Prague'));
    } catch (Throwable) {
        throw new InvalidArgumentException('Konec UAT okna není platný.');
    }
    if ($windowEnd <= $now || $windowEnd > $now->modify('+31 days')) {
        throw new InvalidArgumentException('UAT okno musí končit během následujících 31 dnů.');
    }
    return ['password'=>$password, 'window_end'=>$windowEnd];
}

function kisUatAdminId(PDO $pdo): int
{
    $statement = $pdo->prepare("SELECT id FROM treneri WHERE aktivni=1 AND LOWER(email) IN ('kis-superadmin-test@velocota.com','tester.spravce@velocota.com') ORDER BY id LIMIT 1");
    $statement->execute();
    $id = (int)$statement->fetchColumn();
    if ($id < 1) throw new RuntimeException('Vyhrazený testovací správce nebyl nalezen.');
    return $id;
}

/** @return array{id:int,created:bool} */
function kisUatUpsertParent(PDO $pdo, string $email, string $firstName, string $lastName, string $password): array
{
    $statement = $pdo->prepare('SELECT id,jmeno,prijmeni FROM verejni_uzivatele WHERE LOWER(email)=?');
    $statement->execute([$email]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($row) {
        if ((string)$row['jmeno'] !== $firstName || (string)$row['prijmeni'] !== $lastName) {
            throw new RuntimeException('E-mail testovacího rodiče už patří jiné identitě: ' . $email);
        }
        $pdo->prepare('UPDATE verejni_uzivatele SET heslo_hash=?,email_overeno=1,aktivni=1,verifikacni_token=NULL,verifikacni_token_expires_at=NULL,session_version=session_version+1 WHERE id=?')
            ->execute([$hash, (int)$row['id']]);
        return ['id'=>(int)$row['id'], 'created'=>false];
    }
    $pdo->prepare('INSERT INTO verejni_uzivatele(jmeno,prijmeni,email,heslo_hash,email_overeno,aktivni,session_version) VALUES(?,?,?,?,1,1,1)')
        ->execute([$firstName,$lastName,$email,$hash]);
    return ['id'=>(int)$pdo->lastInsertId(), 'created'=>true];
}

/** @return array{id:int,created:bool} */
function kisUatUpsertChild(PDO $pdo, string $firstName, string $birthDate): array
{
    $statement = $pdo->prepare("SELECT id,narozeni FROM sportovci WHERE LOWER(jmeno)=? AND LOWER(prijmeni)='tester' ORDER BY id");
    $statement->execute([mb_strtolower($firstName, 'UTF-8')]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 1) throw new RuntimeException('Testovací dítě není jednoznačné: ' . $firstName);
    if ($rows !== []) {
        if ((string)$rows[0]['narozeni'] !== $birthDate) throw new RuntimeException('Testovací dítě má jiné datum narození: ' . $firstName);
        return ['id'=>(int)$rows[0]['id'], 'created'=>false];
    }
    $pdo->prepare("INSERT INTO sportovci(jmeno,prijmeni,narozeni,email,telefon,hash,uci,stav_clenstvi) VALUES(?, 'Tester', ?, '', '', ?, 0, 'aktivni')")
        ->execute([$firstName,$birthDate,bin2hex(random_bytes(24))]);
    return ['id'=>(int)$pdo->lastInsertId(), 'created'=>true];
}

function kisUatUpsertChildAccess(PDO $pdo, int $sportovecId, string $login, string $password, int $actorId): int
{
    $statement = $pdo->prepare('SELECT id,login_key FROM child_access_accounts WHERE sportovec_id=?');
    $statement->execute([$sportovecId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $loginKey = childAccessNormalizeLogin($login);
    if (!$row) return (int)childAccessCreate($pdo,$sportovecId,$login,$password,$actorId,'Produkční UAT účet dočasný do konce testovacího okna.')['access_account_id'];
    if (!hash_equals($loginKey, (string)$row['login_key'])) throw new RuntimeException('Dítě už má jiný přihlašovací účet.');
    childAccessResetPassword($pdo,(int)$row['id'],$password,$actorId,'Obnovení dočasného produkčního UAT přístupu.');
    childAccessSetActive($pdo,(int)$row['id'],true,$actorId,'Aktivace dočasného produkčního UAT přístupu.');
    return (int)$row['id'];
}

/** @return array{product_id:int,variant_id:int} */
function kisUatProduct(PDO $pdo, int $actorId, string $sku, string $name, string $offerType, int $amountMinor): array
{
    $lookup = $pdo->prepare('SELECT v.id AS variant_id,p.id AS product_id,p.name,p.offer_type FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE v.sku=?');
    $lookup->execute([$sku]);
    $row = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if ((string)$row['name'] !== $name || (string)$row['offer_type'] !== $offerType) throw new RuntimeException('UAT SKU už patří jiné položce: ' . $sku);
        return ['product_id'=>(int)$row['product_id'],'variant_id'=>(int)$row['variant_id']];
    }
    $result = shopManualCatalogCreate($pdo,$actorId,[
        'name'=>$name,'short_description'=>'Dočasná položka pro produkční uživatelské testování. Nejde o běžnou nabídku.',
        'offer_type'=>$offerType,'visibility'=>'visible','item_type'=>$offerType==='goods'?'product':'service',
    ],[
        'sku'=>$sku,'attributes_json'=>'{}','amount_minor'=>$amountMinor,'compare_at_amount_minor'=>null,
        'includes_vat'=>1,'vat_rate_basis_points'=>0,'stock_quantity_decimal'=>'100','unit_code'=>'ks','visible'=>1,
    ],'Založení časově omezených produkčních UAT dat.',true);
    return ['product_id'=>(int)$result['product_id'],'variant_id'=>(int)$result['variant_id']];
}

/** @return array{season_id:int,team_id:int} */
function kisUatSeasonAndTeam(PDO $pdo, int $actorId, string $startsOn, string $endsOn): array
{
    $season = $pdo->prepare('SELECT id FROM club_seasons WHERE code=?');$season->execute(['TEST-UAT-2026']);$seasonId=(int)$season->fetchColumn();
    if ($seasonId<1) {$pdo->prepare("INSERT INTO club_seasons(code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES('TEST-UAT-2026','TEST - UAT období',?,?,'active',?)")->execute([$startsOn,$endsOn,$actorId]);$seasonId=(int)$pdo->lastInsertId();}
    $team=$pdo->prepare('SELECT id FROM club_teams WHERE season_id=? AND code=?');$team->execute([$seasonId,'TEST-UAT-DETI']);$teamId=(int)$team->fetchColumn();
    if($teamId<1){$pdo->prepare("INSERT INTO club_teams(season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(?,'TEST-UAT-DETI','TEST - UAT děti','všeobecná příprava','8–10 let','active',?)")->execute([$seasonId,$actorId]);$teamId=(int)$pdo->lastInsertId();}
    return ['season_id'=>$seasonId,'team_id'=>$teamId];
}

function kisUatPublish(PDO $pdo, int $actorId, int $productId, string $name): void
{
    shopCatalogPublicationActivate($pdo,$productId,$actorId,$name,'Dočasná produkční UAT nabídka. Po skončení testu bude deaktivována.','Schváleno výhradně pro časově omezené produkční UAT.',true);
}

function kisUatUpsertCalendarEvent(PDO $pdo, int $actorId, string $name, int $feeMinor, DateTimeImmutable $day): int
{
    $lookup=$pdo->prepare('SELECT id FROM club_events WHERE name=? ORDER BY id');$lookup->execute([$name]);$ids=array_map('intval',$lookup->fetchAll(PDO::FETCH_COLUMN));
    if(count($ids)>1)throw new RuntimeException('Duplicitní UAT akce: '.$name);
    $result=clubCalendarSaveEvent($pdo,$actorId,$ids[0]??0,[
        'name'=>$name,'activity_kind'=>$feeMinor>0?'camp':'other','planning_status'=>'confirmed','visibility'=>'public',
        'starts_at'=>$day->setTime(10,0)->format('Y-m-d\TH:i'),'ends_at'=>$day->setTime(15,0)->format('Y-m-d\TH:i'),
        'location'=>'Velodrom Třebešín','public_description_plain'=>'Dočasná akce pro produkční UAT.','internal_note'=>'TEST data; po UAT deaktivovat.',
        'capacity'=>20,'participant_fee_minor'=>$feeMinor,'fee_due_days'=>7,'team_ids'=>[],
    ]);
    clubCalendarSetRegistration($pdo,(int)$result['id'],$actorId,true);
    return (int)$result['id'];
}

function kisUatUpsertLesson(PDO $pdo, int $actorId, string $venueCode, string $name, DateTimeImmutable $day, string $context, float $price): int
{
    $venue=$pdo->prepare('SELECT id FROM sportovist WHERE kod=? AND je_verejne=1 AND aktivni=1');$venue->execute([$venueCode]);$venueId=(int)$venue->fetchColumn();
    if($venueId<1)throw new RuntimeException('Chybí veřejné sportoviště '.$venueCode.'.');
    $lookup=$pdo->prepare('SELECT id FROM individualni_lekce WHERE nazev=? ORDER BY id');$lookup->execute([$name]);$ids=array_map('intval',$lookup->fetchAll(PDO::FETCH_COLUMN));
    if(count($ids)>1)throw new RuntimeException('Duplicitní UAT termín: '.$name);
    $values=[$actorId,$venueId,$day->format('Y-m-d'),'16:00:00','17:00:00',60,'zelena',$name,'Dočasný termín pro produkční UAT.',$price,5,1,'aktivni',$context==='public_velodrome'?1:0,'bank_transfer',$context];
    if($ids===[]){$pdo->prepare('INSERT INTO individualni_lekce(trener_id,sportoviste_id,datum,cas_od,cas_do,slot_delka_min,typ,nazev,popis,cena_kc,max_osob,vyjimka_3_dny,stav,public_exclusive_booking,payment_method_policy,booking_context) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);return(int)$pdo->lastInsertId();}
    $values[]=$ids[0];$pdo->prepare('UPDATE individualni_lekce SET trener_id=?,sportoviste_id=?,datum=?,cas_od=?,cas_do=?,slot_delka_min=?,typ=?,nazev=?,popis=?,cena_kc=?,max_osob=?,vyjimka_3_dny=?,stav=?,public_exclusive_booking=?,payment_method_policy=?,booking_context=? WHERE id=?')->execute($values);return$ids[0];
}

/** @return array<string,mixed> */
function kisUatProvision(PDO $pdo, array $settings, ?DateTimeImmutable $now = null): array
{
    $validated=kisUatValidateSettings($settings,$now);$password=$validated['password'];$windowEnd=$validated['window_end'];
    $now=($now??new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->setTimezone(new DateTimeZone('Europe/Prague'));
    $actorId=kisUatAdminId($pdo);
    $karel=kisUatUpsertParent($pdo,KIS_UAT_PARENT_EMAILS[0],'Tester','Karel',$password);
    $petra=kisUatUpsertParent($pdo,KIS_UAT_PARENT_EMAILS[1],'Tester','Petra',$password);
    $ema=kisUatUpsertChild($pdo,'Ema','2018-04-12');$adam=kisUatUpsertChild($pdo,'Adam','2017-09-18');
    foreach([$karel['id'],$petra['id']]as$accountId)foreach([$ema['id'],$adam['id']]as$personId)accountPersonRoleApprove($pdo,$accountId,$personId,'guardian',$actorId,'Schválená vazba výhradně pro produkční UAT.');
    $childAccess=[kisUatUpsertChildAccess($pdo,$ema['id'],KIS_UAT_CHILD_LOGINS[0],$password,$actorId),kisUatUpsertChildAccess($pdo,$adam['id'],KIS_UAT_CHILD_LOGINS[1],$password,$actorId)];

    $goods=kisUatProduct($pdo,$actorId,'KP-TEST-UAT-LAHEV','TEST - Klubová láhev','goods',19000);
    kisUatPublish($pdo,$actorId,$goods['product_id'],'TEST - Klubová láhev');
    $programProduct=kisUatProduct($pdo,$actorId,'KP-TEST-UAT-KROUZEK','TEST - Cyklistický kroužek','program',10000);
    $starts=$now->format('Y-m-d');$ends=$now->modify('+60 days')->format('Y-m-d');$season=kisUatSeasonAndTeam($pdo,$actorId,$starts,$ends);
    foreach([$ema['id'],$adam['id']]as$personId){$s=$pdo->prepare('SELECT id FROM club_roster_members WHERE team_id=? AND sportovec_id=?');$s->execute([$season['team_id'],$personId]);if(!$s->fetchColumn())$pdo->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(?,?,'active','admin',?,NULL,?)")->execute([$season['team_id'],$personId,$starts,$actorId]);}
    $program=clubProgramCreate($pdo,$actorId,'TEST-UAT-KROUZEK','TEST - Cyklistický kroužek','Dočasný program pro produkční UAT.');
    $offer=clubProgramCreateOffer($pdo,$actorId,(int)$program['id'],$season['season_id'],$season['team_id'],$programProduct['product_id'],$programProduct['variant_id'],'TEST-UAT-KROUZEK-2026','TEST - Cyklistický kroužek',$starts,$ends,$now->modify('-1 hour')->format('Y-m-d H:i:s'),$windowEnd->format('Y-m-d H:i:s'),20,'active',2016,2019);
    clubProgramTermsConfigure($pdo,$actorId,'program',(int)$program['id'],'program_cancellation','TEST UAT: účast lze do konce testovacího okna zrušit; nejde o běžnou klubovou nabídku.',true);
    clubProgramTermsConfigure($pdo,$actorId,'program',(int)$program['id'],'program_consent','TEST UAT: zákonný zástupce souhlasí výhradně s provedením produkčního uživatelského testu.',true);
    kisUatPublish($pdo,$actorId,$programProduct['product_id'],'TEST - Cyklistický kroužek');

    $freeEvent=kisUatUpsertCalendarEvent($pdo,$actorId,'TEST - Rodinný nábor',0,$now->modify('+5 days'));
    $paidEvent=kisUatUpsertCalendarEvent($pdo,$actorId,'TEST - Příměstský den',5000,$now->modify('+6 days'));
    $velodrome=kisUatUpsertLesson($pdo,$actorId,'velodrom','TEST - Veřejný velodrom',$now->modify('+7 days'),'public_velodrome',250.00);
    $lesson=kisUatUpsertLesson($pdo,$actorId,'posilovna_horni','TEST - Individuální lekce',$now->modify('+8 days'),'individual_lesson',300.00);
    $training=$pdo->prepare("SELECT id FROM planovane_treninky WHERE nazev='TEST - Trénink nového dítěte' ORDER BY id");$training->execute();$trainingIds=array_map('intval',$training->fetchAll(PDO::FETCH_COLUMN));if(count($trainingIds)>1)throw new RuntimeException('Duplicitní UAT trénink.');
    if($trainingIds===[]){$pdo->prepare("INSERT INTO planovane_treninky(trener_id,datum,cas_od,cas_do,nazev,kategorie,popis,stav,je_verejny) VALUES(?,?,'17:00:00','18:00:00','TEST - Trénink nového dítěte','draha','Dočasný trénink pro produkční UAT.','planovany',1)")->execute([$actorId,$now->modify('+9 days')->format('Y-m-d')]);$trainingId=(int)$pdo->lastInsertId();}else{$trainingId=$trainingIds[0];$pdo->prepare("UPDATE planovane_treninky SET trener_id=?,datum=?,cas_od='17:00:00',cas_do='18:00:00',kategorie='draha',popis='Dočasný trénink pro produkční UAT.',stav='planovany',je_verejny=1 WHERE id=?")->execute([$actorId,$now->modify('+9 days')->format('Y-m-d'),$trainingId]);}

    return ['ok'=>true,'parents'=>[$karel['id'],$petra['id']],'children'=>[$ema['id'],$adam['id']],'child_access'=>$childAccess,'products'=>[$goods['product_id'],$programProduct['product_id']],'program_offer'=>(int)$offer['id'],'events'=>[$freeEvent,$paidEvent],'lessons'=>[$velodrome,$lesson],'training'=>$trainingId,'window_end'=>$windowEnd->format('Y-m-d H:i:s')];
}

function kisUatMain(): void
{
    if(PHP_SAPI!=='cli'||(string)getenv('KIS_UAT_PROVISION_CONFIRM')!=='VYTVORIT-UAT-DATA')throw new RuntimeException('Chybí výslovné potvrzení produkčního UAT.');
    $root=realpath((string)getenv('APP_ROOT'));$host=strtolower(trim((string)getenv('APP_HOST')));$file=realpath((string)getenv('KIS_UAT_SETTINGS_FILE'));
    if($root===false||$host!=='kis.kovopraha.cz'||$file===false||!is_file($root.'/config.php'))throw new RuntimeException('Produkční cíl nebo chráněné nastavení nejsou dostupné.');
    $settings=json_decode((string)file_get_contents($file),true,8,JSON_THROW_ON_ERROR);if(!is_array($settings))throw new RuntimeException('Nastavení UAT není platné.');
    $_SERVER['HTTP_HOST']=$host;$_SERVER['SERVER_NAME']=$host;require $root.'/config.php';if(!defined('JE_LOKALNE')||JE_LOKALNE!==false)throw new RuntimeException('Cíl není produkce.');
    $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(kisUatProvision($pdo,$settings),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){try{kisUatMain();}catch(Throwable $e){fwrite(STDERR,'Production UAT provisioning failed: '.$e->getMessage().PHP_EOL);exit(1);}}
