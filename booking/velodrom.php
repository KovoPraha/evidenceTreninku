<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) {
    header('Location: prihlaseni.php?redirect=velodrom.php');
    exit;
}
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/csrf_helper.php';
require_once dirname(__DIR__) . '/includes/shop_checkout.php';

function publicVelodromeH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accountId = (int)$_SESSION['verejny_uzivatel_id'];
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Formulář vypršel. Obnovte stránku.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'reserve') {
                $result = publicVelodromeReserve(
                    $pdo,
                    (int)($_POST['lesson_id'] ?? 0),
                    $accountId,
                    (string)($_POST['note'] ?? '')
                );
                $success = $result['created']
                    ? ($result['status'] === 'potvrzena' ? 'Rezervace je potvrzena.' : 'Rezervace čeká na ruční potvrzení.')
                    : 'Tento termín už máte rezervovaný.';
            } elseif ($action === 'add_paid_to_cart') {
                $result = publicVelodromeShopAddToCart(
                    $pdo,
                    $accountId,
                    (int)($_POST['lesson_id'] ?? 0),
                    (string)($_POST['note'] ?? '')
                );
                $_SESSION['flash_shop'] = $result['created']
                    ? 'Placený termín velodromu byl vložen do košíku.'
                    : 'Placený termín už v košíku je; poznámka byla aktualizována.';
                header('Location: eshop.php', true, 303);
                exit;
            } elseif ($action === 'cancel') {
                $result = publicVelodromeCancel(
                    $pdo,
                    (int)($_POST['reservation_id'] ?? 0),
                    $accountId,
                    (string)($_POST['note'] ?? '')
                );
                $success = $result['changed'] ? 'Rezervace byla zrušena.' : 'Rezervace už byla zrušena.';
            } else {
                throw new InvalidArgumentException('Neplatná operace.');
            }
        } catch (InvalidArgumentException | PublicVelodromeException | PublicVelodromeShopException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}
$profile = publicProfileForAccount($pdo, $accountId);
$slots = publicVelodromeSlots($pdo);
$reservations = publicVelodromeReservationsForAccount($pdo, $accountId);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rezervace velodromu</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light">
<main class="container py-4" style="max-width:1000px"><div class="d-flex justify-content-between mb-3"><div><h1 class="h4 mb-1">Veřejné hodiny velodromu</h1><p class="text-muted mb-0">Rezervace konkrétního účastníka z vašeho veřejného profilu.</p></div><a class="btn btn-outline-secondary btn-sm align-self-start" href="verejny_profil.php">Můj profil</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=publicVelodromeH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=publicVelodromeH($success)?></div><?php endif;?>
<?php if(!$profile):?><div class="alert alert-warning">Před rezervací <a href="verejny_profil.php">dokončete svůj profil</a> včetně data narození.</div><?php endif;?>
<div class="row g-3 mb-4"><?php foreach($slots as $slot): $isPaid=(float)$slot['cena_kc']>0.0;?><div class="col-md-6"><section class="card shadow-sm border-0 h-100"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5"><?=publicVelodromeH($slot['nazev'])?></h2><span class="badge text-bg-<?=$slot['remaining_capacity']>0?'success':'secondary'?>"><?=$slot['remaining_capacity']?> / <?=$slot['effective_capacity']?> volno</span></div><p class="mb-1"><strong><?=publicVelodromeH($slot['datum'])?></strong>, <?=publicVelodromeH(substr((string)$slot['cas_od'],0,5))?>–<?=publicVelodromeH(substr((string)$slot['cas_do'],0,5))?></p><p class="small text-muted"><?=$slot['public_exclusive_booking']?'Výhradní rezervace celého velodromu':'Sdílená hodina'?> · <?=number_format((float)$slot['cena_kc'],0,',',' ')?> Kč<?=$isPaid?' · platba objednávkou/QR':''?></p><?php if($profile&&$slot['remaining_capacity']>0):?><form method="post" class="row g-2"><?=csrf_field()?><input type="hidden" name="action" value="<?=$isPaid?'add_paid_to_cart':'reserve'?>"><input type="hidden" name="lesson_id" value="<?=(int)$slot['id']?>"><div class="col-8"><input class="form-control form-control-sm" name="note" maxlength="1000" placeholder="Poznámka (nepovinná)"></div><div class="col-4 d-grid"><button class="btn btn-primary btn-sm"><?=$isPaid?'Do košíku':'Rezervovat'?></button></div></form><?php endif;?></div></section></div><?php endforeach;?><?php if($slots===[]):?><div class="col-12"><div class="alert alert-secondary">Není vypsána žádná veřejná hodina velodromu.</div></div><?php endif;?></div>
<section class="card shadow-sm border-0"><div class="card-header bg-white fw-semibold">Moje rezervace velodromu</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Termín</th><th>Účastník</th><th>Stav</th><th></th></tr></thead><tbody><?php foreach($reservations as $reservation):?><tr><td><?=publicVelodromeH($reservation['datum'].' '.substr((string)$reservation['cas_od'],0,5).'–'.substr((string)$reservation['cas_do'],0,5))?></td><td><?=publicVelodromeH($reservation['prijmeni'].' '.$reservation['jmeno'])?></td><td><?=publicVelodromeH($reservation['stav'])?><?=$reservation['zaplaceno']?' · zaplaceno':''?><?=$reservation['shop_order_id']?' · objednávka '.publicVelodromeH($reservation['shop_order_code']):''?></td><td><?php if(!$reservation['shop_order_id']&&in_array($reservation['stav'],['ceka','potvrzena'],true)):?><form method="post" class="d-flex gap-2"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="reservation_id" value="<?=(int)$reservation['id']?>"><input class="form-control form-control-sm" name="note" maxlength="1000" required placeholder="Důvod storna"><button class="btn btn-outline-danger btn-sm">Zrušit</button></form><?php elseif($reservation['shop_order_id']):?><a class="btn btn-outline-secondary btn-sm" href="objednavka.php?code=<?=rawurlencode((string)$reservation['shop_order_code'])?>">Objednávka</a><?php endif;?></td></tr><?php endforeach;?><?php if($reservations===[]):?><tr><td colspan="4" class="text-center text-muted py-3">Zatím nemáte rezervaci.</td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>
