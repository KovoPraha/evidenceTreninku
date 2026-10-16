<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
$familyId=(int)($_SESSION['verejny_uzivatel_id']??0);
$athleteId=(int)($_SESSION['sportovec_pristup_id']??0);
if ($familyId<1 && $athleteId<1) { header('Location: prihlaseni.php?redirect=nahrady.php');exit; }
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/club_makeup.php';
require_once dirname(__DIR__) . '/includes/ui_shell.php';
function makeupPageH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$actorType=$familyId>0?'account':'athlete';$actorId=$familyId>0?$familyId:$athleteId;
$people=$familyId>0?familyPortalAuthorizedPeople($pdo,$familyId):[];
if($athleteId>0 && $familyId<1){$identity=childAccessIdentity($pdo,$athleteId);if(!$identity){header('Location: sportovec_prihlaseni.php');exit;}$people=[$identity];}
$personId=(int)($_REQUEST['person_id']??($people[0]['sportovec_id']??0));
$person=null;foreach($people as $candidate){if((int)$candidate['sportovec_id']===$personId){$person=$candidate;break;}}
if(!$person){http_response_code(403);exit('K profilu nemáte oprávnění.');}
$error='';$success=(string)($_SESSION['makeup_flash']??'');unset($_SESSION['makeup_flash']);
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new InvalidArgumentException('Formulář vypršel.');
        $action=(string)($_POST['action']??'');
        if($action==='reserve'){
            clubMakeupReserve($pdo,$personId,(int)($_POST['credit_id']??0),(int)($_POST['plan_id']??0),$actorType,$actorId);
            $_SESSION['makeup_flash']='Náhradní trénink byl rezervován.';
        }elseif($action==='cancel'){
            clubMakeupCancelReservation($pdo,$personId,(int)($_POST['reservation_id']??0),$actorType,$actorId);
            $_SESSION['makeup_flash']='Rezervace byla zrušena a nárok vrácen.';
        }else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: nahrady.php?person_id='.$personId,true,303);exit;
    }catch(InvalidArgumentException|ClubMakeupException $exception){$error=$exception->getMessage();}
    catch(Throwable $exception){error_log('booking/nahrady.php: '.$exception->getMessage());$error='Rezervaci se nepodařilo bezpečně uložit.';}
}
if(clubMakeupReady($pdo)){
    try{clubMakeupSyncPersonCredits($pdo,$personId);}catch(Throwable $exception){error_log('booking/nahrady.php credit sync: '.$exception->getMessage());$error=$error?:'Potvrzené omluvy se nyní nepodařilo aktualizovat.';}
}
$credits=clubMakeupCreditsForPerson($pdo,$personId);$reservations=clubMakeupReservationsForPerson($pdo,$personId);$pendingExcuses=clubMakeupPendingExcusesForPerson($pdo,$personId);$deniedExcuses=clubMakeupDeniedExcusesForPerson($pdo,$personId);
$labels=['available'=>'K využití','carried_locked'=>'Čeká na zaplacení dalšího pololetí','reserved'=>'Rezervováno','used'=>'Využito','expired'=>'Propadlo','revoked'=>'Zrušeno'];
$counts=array_count_values(array_map(static fn(array $credit):string=>(string)$credit['display_status'],$credits));
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Náhradní lekce — KOVO Praha</title><?php appUiAssets();?></head><body class="bg-light"><?php publicShellNav();?><main class="container py-4" style="max-width:1000px"><a class="btn btn-sm btn-outline-secondary mb-3" href="<?= $familyId>0?'sportovni_prehled.php':'muj_sport.php' ?>">Zpět na sportovní přehled</a><h1 class="h4">Náhradní lekce</h1><p class="text-muted">Za včas omluvený a skutečně vynechaný zaplacený trénink kroužku může vzniknout jedna náhradní lekce. Trenér musí nejprve uzavřít skutečnou docházku.</p>
<?php if(count($people)>1):?><nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Sportovec"><?php foreach($people as $candidate):?><a class="btn btn-sm <?=((int)$candidate['sportovec_id']===$personId)?'btn-primary':'btn-outline-primary'?>" href="?person_id=<?=(int)$candidate['sportovec_id']?>"><?=makeupPageH($candidate['jmeno'].' '.$candidate['prijmeni'])?></a><?php endforeach;?></nav><?php endif;?><p class="fw-semibold">Profil: <?=makeupPageH($person['jmeno'].' '.$person['prijmeni'])?></p>
<div class="d-flex flex-wrap gap-2 mb-3"><span class="badge text-bg-warning">Čeká na potvrzení <?=count($pendingExcuses)?></span><span class="badge text-bg-success">K využití <?=(int)($counts['available']??0)?></span><span class="badge text-bg-secondary">Uzamčeno <?=(int)($counts['carried_locked']??0)?></span><span class="badge text-bg-primary">Rezervováno <?=(int)($counts['reserved']??0)?></span><span class="badge text-bg-light text-dark border">Využito <?=(int)($counts['used']??0)?></span></div>
<?php if($error!==''):?><div class="alert alert-danger"><?=makeupPageH($error)?></div><?php endif;?><?php if($success!==''):?><div class="alert alert-success"><?=makeupPageH($success)?></div><?php endif;?>
<?php if(!clubMakeupReady($pdo)):?><div class="alert alert-warning">Náhradní lekce zatím čekají na databázovou migraci.</div><?php elseif($credits===[]):?><div class="alert alert-info">Zatím nemáte žádný potvrzený nárok. Omluva se zobrazí trenérovi hned, nárok se připíše až po uzavření skutečné docházky.</div><?php else:?>
<section class="row g-3 mb-4"><?php foreach($credits as $credit):$status=(string)$credit['display_status'];$slots=$status==='available'?clubMakeupAvailableSlots($pdo,$personId,(int)$credit['id']):[];?><div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><div><strong><?=makeupPageH($credit['source_name'])?></strong><div class="small text-muted"><?=makeupPageH($credit['source_date'].' · '.$credit['offer_name'])?></div></div><span class="badge <?=($status==='available'?'text-bg-success':($status==='carried_locked'?'text-bg-warning':'text-bg-secondary'))?> align-self-start"><?=makeupPageH($labels[$status]??$status)?></span></div><div class="small mt-2">Platnost nároku do <?=makeupPageH($credit['expires_on'])?>.</div>
<?php if($status==='carried_locked'):?><p class="small mt-2 mb-0">Nárok se odemkne po zaplacení navazujícího pololetí. <a href="eshop.php">Otevřít e-shop</a></p><?php elseif($status==='available'):?><?php if($slots===[]):?><p class="small text-muted mt-2 mb-0">Pro tento nárok teď není volný věkově vhodný termín. Trenéři mohou náhradní místa vypsat později.</p><?php else:?><div class="mt-3"><div class="small fw-semibold mb-2">Volné termíny náhrad:</div><?php foreach($slots as $slot):?><form method="post" class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded p-2 mb-2"><?=csrf_field()?><input type="hidden" name="action" value="reserve"><input type="hidden" name="person_id" value="<?=$personId?>"><input type="hidden" name="credit_id" value="<?=(int)$credit['id']?>"><input type="hidden" name="plan_id" value="<?=(int)$slot['plan_id']?>"><span><?=makeupPageH($slot['datum'].' '.substr((string)$slot['cas_od'],0,5).' · '.$slot['nazev'])?> <span class="small text-muted">(volno <?=max(0,(int)$slot['capacity']-(int)$slot['booked'])?>)</span></span><button class="btn btn-sm btn-primary">Rezervovat</button></form><?php endforeach;?></div><?php endif;?><?php endif;?></div></div></div><?php endforeach;?></section>
<?php endif;?>
<?php if($pendingExcuses!==[]):?><section class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h5">Omluvené lekce čekající na potvrzení (<?=count($pendingExcuses)?>)</h2><p class="small text-muted">Nárok vznikne až po dokončení tréninku a zapsání skutečné docházky trenérem.</p><ul class="mb-0"><?php foreach($pendingExcuses as $pending):?><li><?=makeupPageH($pending['datum'].' · '.$pending['nazev'])?></li><?php endforeach;?></ul></div></section><?php endif;?>
<?php if($deniedExcuses!==[]):?><section class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h5">Omluvy bez nároku na náhradu</h2><p class="small text-muted">Omluva zůstává viditelná trenérovi. Důvod níže odpovídá rozhodnutí při jejím uložení; u starších omluv nemusí být uložen.</p><ul class="mb-0"><?php foreach($deniedExcuses as $denied):?><li class="mb-2"><?=makeupPageH($denied['datum'].' · '.$denied['nazev'])?> — <?=makeupPageH($denied['makeup_denial_reason']?clubMakeupExcuseFailureMessage((string)$denied['makeup_denial_reason']):'U starší omluvy není uložen konkrétní důvod; požádejte koordinátora o kontrolu.')?></li><?php endforeach;?></ul></div></section><?php endif;?>
<?php if($reservations!==[]):?><section class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Moje rezervované náhrady</h2><?php foreach($reservations as $reservation):?><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top py-2"><span><?=makeupPageH($reservation['datum'].' '.substr((string)$reservation['cas_od'],0,5).' · '.$reservation['nazev'])?></span><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="person_id" value="<?=$personId?>"><input type="hidden" name="reservation_id" value="<?=(int)$reservation['id']?>"><button class="btn btn-sm btn-outline-danger">Zrušit rezervaci</button></form></div><?php endforeach;?><p class="small text-muted mb-0">Pozdní zrušení už nárok nevrací automaticky; kontaktujte trenéra.</p></div></section><?php endif;?></main><?php publicShellFooter();?></body></html>
