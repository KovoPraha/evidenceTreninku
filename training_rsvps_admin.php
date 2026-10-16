<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/includes/funkce.php';
if (!canAccess('planovac')) { header('Location: index.php'); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/training_rsvp.php';
require_once __DIR__ . '/includes/club_makeup.php';

function trainingRsvpAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$planId = (int)($_GET['plan_id'] ?? 0);
$statement = $pdo->prepare(
    'SELECT p.id,p.trener_id,p.serie_id,p.nazev,p.datum,p.cas_od,p.cas_do,p.stav,t.jmeno AS trener_jmeno '
    . 'FROM planovane_treninky p LEFT JOIN treneri t ON t.id=p.trener_id WHERE p.id=?'
);
$statement->execute([$planId]);
$plan = $statement->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    http_response_code(404);
    exit('Plánovaný trénink nebyl nalezen.');
}
if (!roleAtLeast('hlavni') && (int)$plan['trener_id'] !== (int)$_SESSION['trener_id']) {
    http_response_code(403);
    exit('K přípravě tohoto tréninku nemáte oprávnění.');
}
$makeupError='';$makeupMessage=(string)($_SESSION['makeup_plan_flash']??'');unset($_SESSION['makeup_plan_flash']);
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token']??''))) throw new InvalidArgumentException('Formulář vypršel.');
        $action=(string)($_POST['action']??'');
        if ($action==='makeup_slot_save') {
            $until=(string)($_POST['series_until']??'');
            $planIds=[$planId];
            if ($until!=='' && (int)$plan['serie_id']>0 && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$until)===1 && $until>=(string)$plan['datum']) {
                $series=$pdo->prepare("SELECT id FROM planovane_treninky WHERE serie_id=? AND datum>=? AND datum<=? AND stav='planovany' ORDER BY datum,id");
                $series->execute([(int)$plan['serie_id'],(string)$plan['datum'],$until]);
                $planIds=array_map('intval',$series->fetchAll(PDO::FETCH_COLUMN));
            }
            $pdo->beginTransaction();
            try {
                foreach($planIds as $id) clubMakeupSlotSave($pdo,(int)$_SESSION['trener_id'],$id,(int)($_POST['offer_id']??0),(int)($_POST['capacity']??0),(int)($_POST['birth_year_from']??0),(int)($_POST['birth_year_to']??0),roleAtLeast('hlavni'));
                $pdo->commit();
            } catch(Throwable $exception) { if($pdo->inTransaction())$pdo->rollBack();throw $exception; }
            $_SESSION['makeup_plan_flash']='Náhradníci jsou povoleni na '.count($planIds).' termínu/termínech.';
        } elseif ($action==='makeup_slot_disable') {
            clubMakeupSlotDisable($pdo,(int)$_SESSION['trener_id'],$planId,roleAtLeast('hlavni'));
            $_SESSION['makeup_plan_flash']='Přijímání nových náhradníků bylo vypnuto; existující rezervace zůstávají platné.';
        } else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: training_rsvps_admin.php?plan_id='.$planId.'#nahradnici',true,303);exit;
    } catch (InvalidArgumentException|ClubMakeupException $exception) { $makeupError=$exception->getMessage(); }
    catch (Throwable $exception) { error_log('training_rsvps_admin makeup: '.$exception->getMessage());$makeupError='Nastavení náhradníků se nepodařilo uložit.'; }
}
$responses = trainingRsvpPlanOverview($pdo, $planId);
$summary = trainingRsvpPlanSummaries($pdo, [$planId])[$planId] ?? ['expected'=>0,'going'=>0,'not_going'=>0,'pending'=>0];
$makeupReservations=clubMakeupReservationsForPlan($pdo,$planId);
$makeupSlot=null;$makeupOffers=[];
if (clubMakeupReady($pdo)) {
    $slotQuery=$pdo->prepare('SELECT * FROM club_makeup_slots WHERE plan_id=?');$slotQuery->execute([$planId]);$makeupSlot=$slotQuery->fetch(PDO::FETCH_ASSOC)?:null;
    $offersQuery=$pdo->prepare("SELECT DISTINCT o.id,o.name,o.birth_year_from,o.birth_year_to FROM club_program_offers o JOIN training_roster_links l ON l.team_id=o.team_id AND l.plan_id=? JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id AND q.enabled=1 WHERE o.starts_on<=? AND o.ends_on>=? AND o.birth_year_from IS NOT NULL AND o.birth_year_to IS NOT NULL ORDER BY o.name,o.id");
    $offersQuery->execute([$planId,(string)$plan['datum'],(string)$plan['datum']]);$makeupOffers=$offersQuery->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odpovědi k tréninku</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1000px">
    <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-sm btn-outline-secondary" href="planovac.php?datum=<?= trainingRsvpAdminH($plan['datum']) ?>"><i class="bi bi-arrow-left me-1"></i>Zpět do plánovače</a><a class="btn btn-sm btn-outline-primary" href="formular.php?plan_id=<?= (int)$planId ?>">Zapsat skutečnou docházku</a></div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-1">Příprava na trénink · <?= trainingRsvpAdminH($plan['nazev']) ?></h1>
            <p class="text-muted mb-3"><?= trainingRsvpAdminH((new DateTimeImmutable((string)$plan['datum']))->format('j. n. Y')) ?><?= $plan['cas_od'] ? ' · ' . trainingRsvpAdminH(substr((string)$plan['cas_od'], 0, 5)) : '' ?><?= $plan['cas_do'] ? '–' . trainingRsvpAdminH(substr((string)$plan['cas_do'], 0, 5)) : '' ?> · trenér <?= trainingRsvpAdminH($plan['trener_jmeno'] ?: 'neuveden') ?></p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="badge text-bg-primary">Očekáváno <?= (int)$summary['expected'] ?></span>
                <span class="badge text-bg-success">Zúčastní se <?= (int)$summary['going'] ?></span>
                <span class="badge text-bg-secondary">Nezúčastní se <?= (int)$summary['not_going'] ?></span>
                <span class="badge text-bg-warning">Bez odpovědi <?= (int)$summary['pending'] ?></span>
            </div>
            <?php if ($responses === []): ?>
                <div class="alert alert-warning mb-0">Trénink nemá připojenou soupisku se sportovci. Odpovědi proto nelze sbírat.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>Sportovec</th><th>Soupiska</th><th>Odpověď</th><th>Zpráva pro trenéra</th><th>Změněno</th></tr></thead>
                    <tbody><?php foreach ($responses as $row): $response=(string)($row['response'] ?? ''); ?><tr>
                        <td><strong><?= trainingRsvpAdminH(trim((string)$row['prijmeni'] . ' ' . (string)$row['jmeno'])) ?></strong></td>
                        <td><?= trainingRsvpAdminH($row['team_name']) ?></td>
                        <td><span class="badge <?= $response === 'going' ? 'text-bg-success' : ($response === 'not_going' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= trainingRsvpAdminH(trainingRsvpLabel($response)) ?></span></td>
                        <td><?= trim((string)($row['member_note'] ?? '')) !== '' ? nl2br(trainingRsvpAdminH($row['member_note'])) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-muted small"><?= $row['responded_at'] ? trainingRsvpAdminH((new DateTimeImmutable((string)$row['responded_at']))->format('j. n. Y H:i')) : '—' ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0">Odpověď je orientační pro plánování. Skutečnou docházku zadává trenér samostatně v evidenci tréninku.</p>
        </div>
    </div>
    <section class="card border-0 shadow-sm mt-4" id="nahradnici"><div class="card-body"><h2 class="h5">Náhradníci na tomto termínu</h2>
        <?php if($makeupError!==''):?><div class="alert alert-danger"><?=trainingRsvpAdminH($makeupError)?></div><?php endif;?><?php if($makeupMessage!==''):?><div class="alert alert-success"><?=trainingRsvpAdminH($makeupMessage)?></div><?php endif;?>
        <?php if($makeupReservations===[]):?><p class="text-muted">Nikdo není přihlášený jako náhradník.</p><?php else:?><ul class="list-group mb-3"><?php foreach($makeupReservations as $reservation):?><li class="list-group-item"><?=trainingRsvpAdminH($reservation['prijmeni'].' '.$reservation['jmeno'])?> <span class="badge text-bg-info">náhradník</span></li><?php endforeach;?></ul><?php endif;?>
        <?php if($makeupOffers!==[] && $plan['stav']==='planovany'):?><form method="post" class="row g-2 align-items-end"><?=csrf_field()?><input type="hidden" name="action" value="makeup_slot_save"><div class="col-md-5"><label class="form-label">Kroužek a věkové rozmezí</label><select class="form-select" name="offer_id" required><?php foreach($makeupOffers as $offer):?><option value="<?=(int)$offer['id']?>" <?=((int)($makeupSlot['offer_id']??0)===(int)$offer['id'])?'selected':''?>><?=trainingRsvpAdminH($offer['name'].' · ročníky '.$offer['birth_year_from'].'–'.$offer['birth_year_to'])?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Míst pro náhradníky</label><input class="form-control" type="number" name="capacity" min="1" max="500" value="<?=(int)($makeupSlot['capacity']??2)?>" required></div><div class="col-md-2"><label class="form-label">Ročník od</label><input class="form-control" type="number" name="birth_year_from" min="1900" value="<?=(int)($makeupSlot['birth_year_from']??($makeupOffers[0]['birth_year_from']??0))?>" required></div><div class="col-md-2"><label class="form-label">Ročník do</label><input class="form-control" type="number" name="birth_year_to" min="1900" value="<?=(int)($makeupSlot['birth_year_to']??($makeupOffers[0]['birth_year_to']??0))?>" required></div><?php if((int)$plan['serie_id']>0):?><div class="col-md-3"><label class="form-label">Použít i na sérii do data (volitelné)</label><input class="form-control" type="date" name="series_until" min="<?=trainingRsvpAdminH($plan['datum'])?>"></div><?php endif;?><div class="col-12"><button class="btn btn-primary">Povolit / upravit náhradníky</button></div></form><?php elseif($plan['stav']==='planovany'):?>
            <div class="alert alert-info mb-0">
                <p class="mb-2">Pro tento termín není aktivní kroužek se strukturovaným věkovým rozmezím a zapnutou politikou náhrad. Pravidlo spravuje koordinátor programů a sportovišť.</p>
                <?php if (staffCanUsePosition('program_coordinator')): ?>
                    <form method="post" action="prepnout_pracovni_pozici.php" class="mb-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="position" value="program_coordinator">
                        <input type="hidden" name="next" value="club_makeup_policies_admin.php">
                        <input type="hidden" name="reason" value="Nastavení pravidel náhrad z přípravy tréninku">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Přepnout na koordinátora a otevřít pravidla náhrad</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Přepnutí změní vaši aktivní pracovní pozici. Pro návrat k přípravě tréninku znovu zvolte roli Trenér.</p>
                <?php else: ?>
                    <p class="mb-0">Požádejte koordinátora o zapnutí náhrad pro tento kroužek.</p>
                <?php endif; ?>
            </div>
        <?php endif;?>
        <?php if($makeupSlot && (int)$makeupSlot['accepting']===1):?><form method="post" class="mt-3"><?=csrf_field()?><input type="hidden" name="action" value="makeup_slot_disable"><button class="btn btn-outline-danger btn-sm">Nepřijímat další náhradníky</button><span class="small text-muted ms-2">Existující rezervace zůstanou platné.</span></form><?php elseif($makeupSlot):?><div class="alert alert-secondary mt-3 mb-0">Nové náhradníky nyní nepřijímáte. Formulářem výše můžete přijímání znovu zapnout.</div><?php endif;?>
    </div></section>
</main>
</body>
</html>
