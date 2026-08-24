<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/member_charge_read.php';
require_once __DIR__ . '/includes/member_charge_admin.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
staffRequireActivePosition('finance_manager');

function memberChargeAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function memberChargeAdminStatus(string $status): string
{
    return ['pending' => 'Čeká na úhradu', 'paid' => 'Uhrazeno', 'refund_required' => 'Čeká na vrácení', 'refunded' => 'Vráceno', 'cancelled' => 'Zrušeno'][$status] ?? $status;
}

function memberChargeAdminBadge(string $status): string
{
    return ['pending' => 'text-bg-warning', 'paid' => 'text-bg-success', 'refund_required' => 'text-bg-danger', 'refunded' => 'text-bg-info', 'cancelled' => 'text-bg-secondary'][$status] ?? 'text-bg-light';
}

function memberChargeAdminAmountMinor(string $value): int
{
    $value=str_replace([' ', ','],['','.'],trim($value));
    if(preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D',$value)!==1)throw new InvalidArgumentException('Částka musí být číslo s nejvýše dvěma desetinnými místy.');
    [$whole,$decimal]=array_pad(explode('.',$value,2),2,'');return((int)$whole*100)+(int)str_pad($decimal,2,'0');
}

$errors=[];$actorId=(int)$_SESSION['trener_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??'')))$errors[]='Formulář vypršel. Obnovte stránku.';
    else try{$action=(string)($_POST['action']??'');$_POST['amount_minor']=memberChargeAdminAmountMinor((string)($_POST['amount_czk']??'0'));
        if($action==='create'){$result=memberChargeAdminCreate($pdo,$actorId,$_POST,(string)($_POST['reason']??''),isset($_POST['confirmed']));$_SESSION['member_charge_flash']='Předpis '.$result['public_code'].' byl založen.';}
        elseif($action==='correct'){$result=memberChargeAdminCorrect($pdo,(int)($_POST['charge_id']??0),$actorId,$_POST,(string)($_POST['reason']??''),isset($_POST['confirmed']));$_SESSION['member_charge_flash']=$result['changed']?'Předpis byl opraven.':'Předpis se nezměnil.';}
        elseif($action==='cancel'){$result=memberChargeAdminCancel($pdo,(int)($_POST['charge_id']??0),$actorId,(string)($_POST['reason']??''),isset($_POST['confirmed']));$_SESSION['member_charge_flash']=$result['changed']?'Předpis byl zrušen.':'Předpis už byl zrušen.';}
        elseif($action==='confirm_paid'){$result=memberChargeAdminConfirmPaid($pdo,(int)($_POST['charge_id']??0),$actorId,(string)($_POST['paid_on']??''),(string)($_POST['reason']??''),isset($_POST['confirmed']));$_SESSION['member_charge_flash']=$result['changed']?'Úhrada byla potvrzena.':'Předpis už je uhrazen.';}
        elseif($action==='confirm_refund'){$result=memberChargeAdminConfirmRefund($pdo,(int)($_POST['charge_id']??0),$actorId,(string)($_POST['refund_reference']??''),(string)($_POST['reason']??''),isset($_POST['confirmed']));$_SESSION['member_charge_flash']=$result['changed']?'Vrácení platby bylo potvrzeno.':'Platba už je vrácená.';}
        else throw new InvalidArgumentException('Neznámá akce.');header('Location: member_charges_admin.php',true,303);exit;
    }catch(InvalidArgumentException|MemberChargeAdminException|ShopCheckoutException$exception){$errors[]=$exception->getMessage();}catch(Throwable$exception){error_log('member_charges_admin.php: '.$exception->getMessage());$errors[]='Operace selhala bez částečného zápisu.';}
}
$success=(string)($_SESSION['member_charge_flash']??'');unset($_SESSION['member_charge_flash']);

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$status = (string)($_GET['status'] ?? '');
if (!in_array($status, ['', 'pending', 'paid', 'refund_required', 'refunded', 'cancelled'], true)) {
    $status = '';
}
$rows = memberChargeAdminRows($pdo, $query, $status);
$people=$pdo->query("SELECT id,jmeno,prijmeni FROM sportovci WHERE stav_clenstvi<>'archiv' ORDER BY prijmeni,jmeno,id")->fetchAll(PDO::FETCH_ASSOC);
$payers=$pdo->query("SELECT id,jmeno,prijmeni,email FROM verejni_uzivatele WHERE aktivni=1 ORDER BY prijmeni,jmeno,id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Členské předpisy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><h1 class="h3 mb-1">Členské předpisy</h1><div class="text-muted">Založení, opravy neuhrazených předpisů, potvrzení úhrady a auditované zrušení.</div></div>
        <div class="d-flex gap-2"><a href="member_charge_reminders_admin.php" class="btn btn-outline-primary">Připomínky plateb</a><a href="pracovni_pozice.php" class="btn btn-outline-secondary">Finanční rozcestník</a></div>
    </div>
    <?php foreach($errors as$error):?><div class="alert alert-danger"><?=memberChargeAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=memberChargeAdminH($success)?></div><?php endif;?>
    <div class="card card-body border-0 shadow-sm mb-4"><h2 class="h5">Nový členský předpis</h2><form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="action" value="create"><input type="hidden" name="currency" value="CZK"><div class="col-md-4"><label class="form-label">Sportovec</label><select class="form-select" name="sportovec_id" required><option value="">Vyberte</option><?php foreach($people as$person):?><option value="<?=(int)$person['id']?>"><?=memberChargeAdminH($person['prijmeni'].' '.$person['jmeno'])?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Účet plátce (volitelné)</label><select class="form-select" name="payer_account_id"><option value="">Bez přiřazeného účtu</option><?php foreach($payers as$payer):?><option value="<?=(int)$payer['id']?>"><?=memberChargeAdminH($payer['prijmeni'].' '.$payer['jmeno'].' — '.$payer['email'])?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Název</label><input class="form-control" name="title_snapshot" maxlength="255" required value="Členský příspěvek"></div><div class="col-md-2"><label class="form-label">Částka Kč</label><input class="form-control" name="amount_czk" inputmode="decimal" required></div><div class="col-md-2"><label class="form-label">Splatnost</label><input class="form-control" type="date" name="due_on" required></div><div class="col-md-2"><label class="form-label">Období od</label><input class="form-control" type="date" name="period_from"></div><div class="col-md-2"><label class="form-label">Období do</label><input class="form-control" type="date" name="period_to"></div><div class="col-md-4"><label class="form-label">Auditovaný důvod</label><input class="form-control" name="reason" maxlength="1000" required value="Ruční založení členského předpisu."></div><div class="col-md-9 form-check ms-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirm-create-charge" required><label class="form-check-label" for="confirm-create-charge">Potvrzuji založení předpisu a platebních údajů</label></div><div class="col-md-2 ms-auto d-grid"><button class="btn btn-primary">Založit předpis</button></div></form></div>
    <form method="get" class="card card-body border-0 shadow-sm mb-4"><div class="row g-3 align-items-end">
        <div class="col-md-6"><label for="q" class="form-label">Hledat sportovce, kód nebo název</label><input id="q" name="q" class="form-control" value="<?= memberChargeAdminH($query) ?>"></div>
        <div class="col-md-3"><label for="status" class="form-label">Stav</label><select id="status" name="status" class="form-select"><option value="">Všechny</option><?php foreach (['pending', 'paid', 'refund_required', 'refunded', 'cancelled'] as $option): ?><option value="<?= $option ?>"<?= $status === $option ? ' selected' : '' ?>><?= memberChargeAdminH(memberChargeAdminStatus($option)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary">Filtrovat</button><a href="member_charges_admin.php" class="btn btn-outline-secondary">Zrušit filtr</a></div>
    </div></form>
    <?php $refundRows=array_values(array_filter($rows,static fn(array$row):bool=>(string)$row['status']==='refund_required'));if($refundRows!==[]):?>
    <section class="alert alert-danger border-3 shadow-sm mb-4"><h2 class="h5"><i class="bi bi-exclamation-octagon-fill me-2"></i>Platby čekající na vrácení</h2><p>Nejprve odešlete peníze v bance. Potom zde zapište bankovní referenci; bez ní systém vratku neuzavře.</p><?php foreach($refundRows as$row):?><form method="post" class="row g-2 align-items-end border-top pt-3 mt-3"><?=csrf_field()?><input type="hidden" name="action" value="confirm_refund"><input type="hidden" name="charge_id" value="<?=(int)$row['id']?>"><input type="hidden" name="amount_czk" value="<?=memberChargeAdminH(number_format((int)$row['amount_minor']/100,2,'.',''))?>"><div class="col-lg-3"><strong><?=memberChargeAdminH($row['prijmeni'].' '.$row['jmeno'])?></strong><div><?=memberChargeAdminH(number_format((int)$row['amount_minor']/100,2,',',' '))?> Kč</div></div><div class="col-lg-3"><label class="form-label small">Bankovní reference</label><input class="form-control" name="refund_reference" maxlength="255" required></div><div class="col-lg-3"><label class="form-label small">Důvod a poznámka</label><input class="form-control" name="reason" maxlength="1000" required></div><div class="col-lg-2 form-check"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="refund-<?=(int)$row['id']?>" required><label class="form-check-label" for="refund-<?=(int)$row['id']?>">Peníze odeslány</label></div><div class="col-lg-1 d-grid"><button class="btn btn-danger">Potvrdit</button></div></form><?php endforeach;?></section>
    <?php endif;?>
    <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong>Předpisy</strong><span class="text-muted small"><?= count($rows) ?> záznamů</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Sportovec</th><th>Předpis</th><th>Částka a splatnost</th><th>Stav</th><th>Poslední platba</th><th>Provozní akce</th></tr></thead><tbody>
        <?php if ($rows === []): ?><tr><td colspan="6" class="text-muted p-4">Filtru neodpovídá žádný předpis.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr>
            <td><strong><?= memberChargeAdminH($row['jmeno'] . ' ' . $row['prijmeni']) ?></strong><div class="small text-muted"><?= memberChargeAdminH($row['narozeni'] ?: 'datum narození neuvedeno') ?></div></td>
            <td><?= memberChargeAdminH($row['title_snapshot']) ?><div class="small text-muted"><code><?= memberChargeAdminH($row['public_code']) ?></code> · <?= memberChargeAdminH($row['source_system']) ?></div></td>
            <td><?= memberChargeAdminH(number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . $row['currency']) ?><div class="small text-muted">Splatnost <?= memberChargeAdminH($row['due_on'] ?: 'neuvedena') ?></div></td>
            <td><span class="badge <?= memberChargeAdminH(memberChargeAdminBadge((string)$row['status'])) ?>"><?= memberChargeAdminH(memberChargeAdminStatus((string)$row['status'])) ?></span></td>
            <td><?php if (!$row['payment_status']): ?><span class="text-muted">Bez platby</span><?php else: ?><span class="badge <?= memberChargeAdminH(memberChargeAdminBadge((string)$row['payment_status'])) ?>"><?= memberChargeAdminH(memberChargeAdminStatus((string)$row['payment_status'])) ?></span><div class="small text-muted"><?= memberChargeAdminH($row['payment_method'] ?: 'metoda neuvedena') ?><?php if ($row['variable_symbol']): ?> · VS <?= memberChargeAdminH($row['variable_symbol']) ?><?php endif; ?><?php if ($row['paid_at']): ?><br>Uhrazeno <?= memberChargeAdminH(substr((string)$row['paid_at'], 0, 10)) ?><?php endif; ?></div><?php endif; ?></td>
            <td style="min-width:330px"><?php if($row['status']==='pending'):?><details><summary class="btn btn-sm btn-outline-primary">Upravit</summary><form method="post" class="row g-1 mt-2"><?=csrf_field()?><input type="hidden" name="action" value="correct"><input type="hidden" name="charge_id" value="<?=(int)$row['id']?>"><input type="hidden" name="sportovec_id" value="<?=(int)$row['sportovec_id']?>"><input type="hidden" name="payer_account_id" value="<?=memberChargeAdminH($row['payer_account_id'])?>"><input type="hidden" name="currency" value="<?=memberChargeAdminH($row['currency'])?>"><input type="hidden" name="period_from" value="<?=memberChargeAdminH($row['period_from'])?>"><input type="hidden" name="period_to" value="<?=memberChargeAdminH($row['period_to'])?>"><div class="col-7"><input class="form-control form-control-sm" name="title_snapshot" value="<?=memberChargeAdminH($row['title_snapshot'])?>" required></div><div class="col-5"><input class="form-control form-control-sm" name="amount_czk" value="<?=memberChargeAdminH(number_format((int)$row['amount_minor']/100,2,'.',''))?>" required></div><div class="col-5"><input class="form-control form-control-sm" type="date" name="due_on" value="<?=memberChargeAdminH($row['due_on'])?>" required></div><div class="col-7"><input class="form-control form-control-sm" name="reason" required placeholder="Důvod opravy"></div><div class="col-8 form-check ms-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="correct-<?=(int)$row['id']?>" required><label class="form-check-label small" for="correct-<?=(int)$row['id']?>">Potvrdit opravu</label></div><div class="col-3 d-grid"><button class="btn btn-sm btn-primary">Uložit</button></div></form></details><form method="post" class="d-flex gap-1 mt-2"><?=csrf_field()?><input type="hidden" name="action" value="confirm_paid"><input type="hidden" name="charge_id" value="<?=(int)$row['id']?>"><input type="hidden" name="amount_czk" value="<?=memberChargeAdminH(number_format((int)$row['amount_minor']/100,2,'.',''))?>"><input class="form-control form-control-sm" type="date" name="paid_on" value="<?=date('Y-m-d')?>" required><input class="form-control form-control-sm" name="reason" required placeholder="Důvod potvrzení"><input type="checkbox" name="confirmed" value="1" title="Potvrdit" required><button class="btn btn-sm btn-success">Uhrazeno</button></form><form method="post" class="d-flex gap-1 mt-2"><?=csrf_field()?><input type="hidden" name="action" value="cancel"><input type="hidden" name="charge_id" value="<?=(int)$row['id']?>"><input type="hidden" name="amount_czk" value="<?=memberChargeAdminH(number_format((int)$row['amount_minor']/100,2,'.',''))?>"><input class="form-control form-control-sm" name="reason" required placeholder="Důvod zrušení"><input type="checkbox" name="confirmed" value="1" title="Potvrdit" required><button class="btn btn-sm btn-outline-danger">Zrušit</button></form><?php else:?><span class="text-muted small">Historie je neměnná.</span><?php endif;?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div></div>
</main>

</body></html>
