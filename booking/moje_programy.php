<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/session_security.php';app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) { header('Location: prihlaseni.php?redirect=moje_programy.php');exit; }
require_once dirname(__DIR__) . '/db.php';require_once dirname(__DIR__) . '/csrf_helper.php';require_once dirname(__DIR__) . '/includes/club_program.php';
function clubProgramPageH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$accountId=(int)$_SESSION['verejny_uzivatel_id'];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??'')))$errors[]='Formulář vypršel. Obnovte stránku.';
    else try{$result=clubProgramActivateOrderItem($pdo,$accountId,(int)($_POST['order_item_id']??0));$_SESSION['club_program_success']=$result['created']?'Účast byla aktivována a dítě je v soupisce.':'Položku jsme ověřili. Aktuální stav účasti najdete níže.';header('Location: moje_programy.php',true,303);exit;}
    catch(InvalidArgumentException|ClubProgramException|ShopCheckoutException $exception){$errors[]=$exception->getMessage();}
    catch(Throwable $exception){error_log('booking/moje_programy.php: '.$exception->getMessage());$errors[]='Aktivace selhala bez částečného zápisu.';}
}
$success=(string)($_SESSION['club_program_success']??'');unset($_SESSION['club_program_success']);$items=clubProgramPendingOrderItems($pdo,$accountId);$enrollments=clubProgramEnrollmentsForAccount($pdo,$accountId);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Moje kroužky</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php appUiAssets(); ?>
</head>
<body class="bg-light">
<?php publicShellNav(); ?>
<main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between mb-3"><div><h1 class="h4 mb-0">Moje kroužky</h1><div class="text-muted">Zakoupená období a historie účastí vašich profilů.</div></div><div><a class="btn btn-outline-primary btn-sm" href="eshop.php">E-shop</a> <a class="btn btn-outline-secondary btn-sm" href="sportovni_prehled.php">Sportovní přehled</a></div></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=clubProgramPageH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=clubProgramPageH($success)?></div><?php endif;?>
<section class="card mb-4">
    <div class="card-header fw-semibold">Přihlášky a objednaná období</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Program</th><th scope="col">Sportovec</th><th scope="col">Objednávka a platba</th><th scope="col">Stav a další krok</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): $state = clubProgramAccountItemState($item); ?>
        <tr>
            <td><?=clubProgramPageH($item['program_name'].' / '.$item['offer_name'])?><div class="small text-muted"><?=clubProgramPageH($item['starts_on'].' – '.$item['ends_on'])?></div></td>
            <td><?=clubProgramPageH(trim($item['prijmeni'].' '.$item['jmeno']))?><?php if (empty($item['beneficiary_sportovec_id'])): ?><div class="small text-muted">Údaje z vaší přihlášky</div><?php endif; ?></td>
            <td><a href="objednavka.php?code=<?=rawurlencode((string)$item['public_code'])?>"><?=clubProgramPageH($item['public_code'])?></a><div class="small"><?=clubProgramPageH(clubProgramAccountPaymentLabel((string)$item['payment_status']))?></div></td>
            <td><span class="badge text-bg-<?=clubProgramPageH($state['tone'])?>"><?=clubProgramPageH($state['label'])?></span><div class="small my-1"><?=clubProgramPageH($state['detail'])?></div>
                <?php if ($state['can_activate']): ?><form method="post"><?=csrf_field()?><input type="hidden" name="order_item_id" value="<?=(int)$item['order_item_id']?>"><button class="btn btn-sm btn-primary">Aktivovat účast</button></form>
                <?php elseif ($state['code'] === 'email_pending'): ?><a href="znovu_overit_email.php?redirect=moje_programy.php">Získat nový ověřovací e-mail</a>
                <?php elseif (in_array($state['code'], ['identity_pending','request_closed'], true)): ?><a href="moje_osoby.php">Stav žádosti v Moje osoby</a><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?><tr><td colspan="4" class="text-muted">Zatím zde nemáte žádná objednaná období.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<section class="card">
    <div class="card-header fw-semibold">Účasti a zařazení do soupisek</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th scope="col">Sportovec</th><th scope="col">Program</th><th scope="col">Platnost účasti</th><th scope="col">Soupiska</th><th scope="col">Stav</th></tr></thead>
        <tbody>
        <?php foreach ($enrollments as $enrollment): ?>
        <tr>
            <td><?=clubProgramPageH($enrollment['prijmeni'].' '.$enrollment['jmeno'])?></td>
            <td><?=clubProgramPageH($enrollment['program_name'].' / '.$enrollment['offer_name'])?><div class="small"><code><?=clubProgramPageH($enrollment['public_code'])?></code></div></td>
            <td><?=clubProgramPageH($enrollment['valid_from'].' – '.$enrollment['valid_to'])?></td>
            <td><?=clubProgramPageH($enrollment['team_name'])?><div class="small text-muted"><?php if ($enrollment['roster_status'] !== null): ?><?=clubProgramPageH($enrollment['roster_status'] === 'active' ? 'Aktivní členství' : 'Členství ukončeno')?> · <?=clubProgramPageH($enrollment['roster_valid_from'].' – '.($enrollment['roster_valid_to'] ?? 'bez koncového data'))?><?php else: ?>Členství nenalezeno<?php endif; ?></div></td>
            <td><?=clubProgramPageH(clubProgramAccountEnrollmentLabel($enrollment))?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($enrollments === []): ?><tr><td colspan="5" class="text-muted">Zatím není aktivována žádná účast. Stav přihlášky a platby najdete výše.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<p class="mt-3"><a href="moje_osoby.php">Moje osoby a žádosti o propojení</a></p>
</main></body></html>
