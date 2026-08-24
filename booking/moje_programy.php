<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/session_security.php';app_session_start();
if (!isset($_SESSION['verejny_uzivatel_id'])) { header('Location: prihlaseni.php?redirect=moje_programy.php');exit; }
require_once dirname(__DIR__) . '/db.php';require_once dirname(__DIR__) . '/csrf_helper.php';require_once dirname(__DIR__) . '/includes/club_program.php';
function clubProgramPageH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$accountId=(int)$_SESSION['verejny_uzivatel_id'];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??'')))$errors[]='Formulář vypršel. Obnovte stránku.';
    else try{$result=clubProgramActivateOrderItem($pdo,$accountId,(int)($_POST['order_item_id']??0));$_SESSION['club_program_success']=$result['created']?'Účast byla aktivována a dítě je v soupisce.':'Tato účast už byla aktivována.';header('Location: moje_programy.php',true,303);exit;}
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
<section class="card mb-4"><div class="card-header fw-semibold">Zakoupené položky k aktivaci</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Program</th><th>Dítě</th><th>Objednávka</th><th>Platba</th><th></th></tr></thead><tbody><?php foreach($items as$item):?><tr><td><?=clubProgramPageH($item['program_name'].' / '.$item['offer_name'])?><div class="small text-muted"><?=clubProgramPageH($item['starts_on'].' – '.$item['ends_on'])?></div></td><td><?=clubProgramPageH($item['prijmeni'].' '.$item['jmeno'])?></td><td><code><?=clubProgramPageH($item['public_code'])?></code></td><td><?=clubProgramPageH($item['payment_status'])?></td><td><?php if($item['enrollment_id']):?><span class="badge text-bg-success">aktivováno</span><?php else:?><form method="post"><?=csrf_field()?><input type="hidden" name="order_item_id" value="<?=(int)$item['order_item_id']?>"><button class="btn btn-sm btn-primary">Aktivovat</button></form><?php endif;?></td></tr><?php endforeach;?><?php if($items===[]):?><tr><td colspan="5" class="text-muted">Žádná zakoupená období čekající na aktivaci.</td></tr><?php endif;?></tbody></table></div></section>
<section class="card"><div class="card-header fw-semibold">Historie účastí</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Dítě</th><th>Program</th><th>Období</th><th>Soupiska</th><th>Stav</th></tr></thead><tbody><?php foreach($enrollments as$enrollment):?><tr><td><?=clubProgramPageH($enrollment['prijmeni'].' '.$enrollment['jmeno'])?></td><td><?=clubProgramPageH($enrollment['program_name'].' / '.$enrollment['offer_name'])?><div class="small"><code><?=clubProgramPageH($enrollment['public_code'])?></code></div></td><td><?=clubProgramPageH($enrollment['valid_from'].' – '.$enrollment['valid_to'])?></td><td><?=clubProgramPageH($enrollment['team_name'])?></td><td><?=clubProgramPageH($enrollment['status'])?></td></tr><?php endforeach;?><?php if($enrollments===[]):?><tr><td colspan="5" class="text-muted">Zatím není aktivována žádná účast.</td></tr><?php endif;?></tbody></table></div></section></main></body></html>
