<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/member_payer_receipt.php';
if (!isset($_SESSION['trener_id'])||!roleAtLeast('admin')) { header('Location: login.php');exit; }

function mprH(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function mprMoney(int $minor): string { return number_format($minor/100,2,',',' ').' Kč'; }
function mprMinor(string $value): int
{
    $value=str_replace([' ',"\xc2\xa0",','],['','','.'],trim($value));
    if (preg_match('/^([0-9]{1,7})(?:\.([0-9]{1,2}))?$/D',$value,$match)!==1) throw new InvalidArgumentException('Částku zadejte v Kč, nejvýše se dvěma desetinnými místy.');
    return (int)$match[1]*100+(int)str_pad($match[2]??'',2,'0');
}
function mprAmounts(array $posted): array
{
    $amounts=[];
    foreach ($posted as $chargeId=>$value) {
        if (!is_scalar($value)) throw new InvalidArgumentException('Neplatná částka předpisu.');
        if (trim((string)$value)==='') continue;
        $amounts[$chargeId]=mprMinor((string)$value);
    }
    return $amounts;
}

$errors=[];$success='';$payerId=max(0,(int)($_GET['payer_id']??$_POST['payer_id']??0));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else try {
        $action=(string)($_POST['action']??'');
        $amounts=mprAmounts(is_array($_POST['allocation']??null)?$_POST['allocation']:[]);
        if ($action==='create') {
            $source=(string)($_POST['source']??'');$fioId=null;
            if ($source==='fio') {
                $fioId=(int)($_POST['fio_movement_id']??0);
                $query=$pdo->prepare('SELECT fio_movement_id,amount_minor,booked_on FROM fio_account_movements WHERE id=?');$query->execute([$fioId]);$movement=$query->fetch(PDO::FETCH_ASSOC);
                if (!$movement) throw new InvalidArgumentException('Vyberte bankovní pohyb Fio.');
                $reference=(string)$movement['fio_movement_id'];$amount=(int)$movement['amount_minor'];$paidOn=(string)$movement['booked_on'];
            } elseif ($source==='manual_bank') {
                $reference=(string)($_POST['reference']??'');$amount=mprMinor((string)($_POST['amount_czk']??''));$paidOn=(string)($_POST['paid_on']??'');
            } else throw new InvalidArgumentException('Vyberte zdroj bankovní platby.');
            $result=memberPayerReceiptCreate($pdo,$payerId,$fioId,$reference,$amount,$paidOn,$amounts,(int)$_SESSION['trener_id'],(string)($_POST['reason']??''),isset($_POST['confirmed']));
            $success='Platba #'.$result['receipt_id'].' uložena. Přiděleno '.mprMoney($result['allocated_minor']).', zbývá '.mprMoney($result['remaining_minor']).'.';
        } elseif ($action==='allocate') {
            $key=trim((string)($_POST['request_key']??''));
            $result=memberPayerReceiptAllocateRemaining($pdo,(int)($_POST['receipt_id']??0),$amounts,(int)$_SESSION['trener_id'],(string)($_POST['reason']??''),isset($_POST['confirmed']),$key===''?null:$key);
            $success='Doplněno přidělení '.mprMoney($result['allocated_minor']).'. Zbývá '.mprMoney($result['remaining_minor']).'.';
        } else throw new InvalidArgumentException('Neznámá akce.');
    } catch (InvalidArgumentException|MemberPayerReceiptException|ClubFinanceException|MemberChargeAdminException $e) {$errors[]=$e->getMessage();}
      catch (Throwable $e) {error_log('member_payer_receipts_admin.php: '.$e->getMessage());$errors[]='Operace selhala bez částečného zápisu.';}
}

$search=trim((string)($_GET['q']??''));$payers=[];
if ($search!=='') {
    $query=$pdo->prepare("SELECT a.id,a.jmeno,a.prijmeni,a.email,v.variable_symbol FROM member_payer_variable_symbols v JOIN verejni_uzivatele a ON a.id=v.payer_account_id WHERE CAST(a.id AS CHAR) LIKE ? OR v.variable_symbol LIKE ? OR a.email LIKE ? OR a.prijmeni LIKE ? ORDER BY a.prijmeni,a.jmeno,a.id LIMIT 40");
    $like='%'.mb_substr($search,0,80,'UTF-8').'%';$query->execute([$like,$like,$like,$like]);$payers=$query->fetchAll(PDO::FETCH_ASSOC);
}
$payer=null;$charges=[];$receipts=[];$fioMovements=[];$allocationRows=[];
if ($payerId>0) {
    $query=$pdo->prepare('SELECT a.id,a.jmeno,a.prijmeni,a.email,v.variable_symbol FROM member_payer_variable_symbols v JOIN verejni_uzivatele a ON a.id=v.payer_account_id WHERE a.id=?');$query->execute([$payerId]);$payer=$query->fetch(PDO::FETCH_ASSOC);
    if ($payer) {
        $charges=memberPayerReceiptOpenCharges($pdo,$payerId);$receipts=memberPayerReceiptRecent($pdo,$payerId);
        $query=$pdo->prepare("SELECT m.id,m.fio_movement_id,m.booked_on,m.amount_minor,m.match_status,m.match_reason FROM fio_account_movements m WHERE m.variable_symbol=? AND m.amount_minor>0 AND m.currency='CZK' AND m.match_status<>'confirmed' ORDER BY m.booked_on DESC,m.id DESC LIMIT 80");$query->execute([$payer['variable_symbol']]);$fioMovements=$query->fetchAll(PDO::FETCH_ASSOC);
        $receiptIds=array_map('intval',array_column($receipts,'id'));
        if ($receiptIds!==[]) {$marks=implode(',',array_fill(0,count($receiptIds),'?'));$query=$pdo->prepare("SELECT a.receipt_id,a.charge_id,a.amount_minor,c.public_code,s.jmeno,s.prijmeni FROM member_payer_receipt_allocations a JOIN club_member_charges c ON c.id=a.charge_id JOIN sportovci s ON s.id=c.sportovec_id WHERE a.receipt_id IN ($marks) ORDER BY a.receipt_id,a.id");$query->execute($receiptIds);foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$allocationRows[(int)$row['receipt_id']][]=$row;}
    }
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rodinné bankovní platby</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__.'/hlavicka.php';?>
<main class="container py-4" style="max-width:1300px"><div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h1 class="h3">Rozdělení rodinné platby</h1><p class="text-muted">Jeden stálý VS plátce může pokrýt předpisy více dětí. Každý bankovní pohyb se potvrzuje pouze jednou; nevyužitý zbytek zůstává u plátce k ručnímu přidělení.</p></div><a class="btn btn-outline-primary" href="eshop_fio_admin.php">Fio pohyby</a></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=mprH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=mprH($success)?></div><?php endif;?>
<form method="get" class="card card-body mb-3"><label class="form-label" for="payer-search">Najít plátce podle VS, e-mailu, příjmení nebo ID</label><div class="input-group"><input id="payer-search" class="form-control" name="q" value="<?=mprH($search)?>" required><button class="btn btn-outline-primary">Hledat</button></div></form>
<?php if($search!==''):?><div class="list-group mb-4"><?php foreach($payers as $row):?><a class="list-group-item list-group-item-action" href="?payer_id=<?=(int)$row['id']?>"><?=mprH($row['prijmeni'].' '.$row['jmeno'].' · '.$row['email'].' · VS '.$row['variable_symbol'])?></a><?php endforeach;?><?php if($payers===[]):?><div class="list-group-item text-muted">Žádný plátce se stálým VS nebyl nalezen.</div><?php endif;?></div><?php endif;?>
<?php if($payer):?><h2 class="h5"><?=mprH($payer['prijmeni'].' '.$payer['jmeno'])?> · VS <code><?=mprH($payer['variable_symbol'])?></code></h2>
<div class="row g-3 mb-4"><div class="col-lg-6"><section class="card h-100"><div class="card-body"><h3 class="h6">Nová přijatá platba</h3><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create"><input type="hidden" name="payer_id" value="<?=$payerId?>"><div class="mb-2"><label class="form-label">Zdroj</label><select class="form-select" name="source" id="mpr-source"><option value="fio">Pohyb Fio</option><option value="manual_bank">Ruční bankovní reference</option></select></div><div class="mb-2"><label class="form-label">Pohyb Fio</label><select class="form-select" name="fio_movement_id"><option value="">Vyberte pohyb</option><?php foreach($fioMovements as $movement):?><option value="<?=(int)$movement['id']?>"><?=mprH($movement['booked_on'].' · '.$movement['fio_movement_id'].' · '.mprMoney((int)$movement['amount_minor']).' · '.$movement['match_status'])?></option><?php endforeach;?></select></div><div class="row g-2 mb-2"><div class="col-md-5"><label class="form-label">Ruční reference</label><input class="form-control" name="reference" placeholder="ID bankovního výpisu"></div><div class="col-md-3"><label class="form-label">Částka Kč</label><input class="form-control" name="amount_czk" inputmode="decimal"></div><div class="col-md-4"><label class="form-label">Datum připsání</label><input class="form-control" type="date" name="paid_on"></div></div><p class="small text-muted">Ruční referenci použijte jen pro platbu, která ještě není ve Fio importu. Údaj se kontroluje proti všem ostatním evidovaným úhradám.</p><?php foreach($charges as $charge):?><div class="input-group input-group-sm mb-2"><span class="input-group-text" style="min-width:65%"><?=mprH($charge['prijmeni'].' '.$charge['jmeno'].' · '.$charge['public_code'])?> · zbývá <?=mprMoney((int)$charge['remaining_minor'])?></span><input class="form-control" name="allocation[<?=(int)$charge['id']?>]" inputmode="decimal" placeholder="Kč" aria-label="Přidělená částka Kč"></div><?php endforeach;?><div class="mb-2"><label class="form-label">Důvod / kontrola výpisu</label><input class="form-control" name="reason" minlength="5" maxlength="1000" value="Ověřeno proti bankovnímu výpisu a rozděleno na předpisy." required></div><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required><span class="form-check-label">Potvrzuji plátce, zdroj, částku a přidělení</span></label><button class="btn btn-primary" <?=$charges===[]?'disabled':''?>>Potvrdit a přidělit</button></form></div></section></div>
<div class="col-lg-6"><section class="card h-100"><div class="card-body"><h3 class="h6">Neuhrazené předpisy</h3><?php if($charges===[]):?><p class="text-muted">Plátce nemá otevřený členský předpis.</p><?php else:?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Dítě</th><th>Předpis</th><th>Splatnost</th><th>Zbývá</th></tr></thead><tbody><?php foreach($charges as $charge):?><tr><td><?=mprH($charge['prijmeni'].' '.$charge['jmeno'])?></td><td><?=mprH($charge['public_code'].' · '.$charge['title_snapshot'])?></td><td><?=mprH($charge['due_on'])?></td><td><?=mprMoney((int)$charge['remaining_minor'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></section></div></div>
<h3 class="h5">Přijaté platby plátce</h3><?php foreach($receipts as $receipt):$remaining=(int)$receipt['amount_minor']-(int)$receipt['allocated_minor'];?><section class="card mb-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>#<?=(int)$receipt['id']?> · <?=mprH($receipt['reference'])?></strong><div class="small text-muted"><?=mprH($receipt['paid_on'])?> · celkem <?=mprMoney((int)$receipt['amount_minor'])?> · přiděleno <?=mprMoney((int)$receipt['allocated_minor'])?> · zbývá <?=mprMoney($remaining)?></div></div><span class="badge text-bg-<?=$remaining>0?'warning':'success'?> align-self-start"><?=$remaining>0?'Zbývá rozdělit':'Rozděleno'?></span></div><?php if(!empty($allocationRows[(int)$receipt['id']])):?><ul class="small mt-2 mb-2"><?php foreach($allocationRows[(int)$receipt['id']] as $row):?><li><?=mprH($row['prijmeni'].' '.$row['jmeno'].' · '.$row['public_code'])?>: <?=mprMoney((int)$row['amount_minor'])?></li><?php endforeach;?></ul><?php endif;?><?php if($remaining>0&&$charges!==[]):?><details class="mt-2"><summary>Přidělit zbývající část</summary><form method="post" class="mt-2"><?=csrf_field()?><input type="hidden" name="action" value="allocate"><input type="hidden" name="payer_id" value="<?=$payerId?>"><input type="hidden" name="receipt_id" value="<?=(int)$receipt['id']?>"><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>"><?php foreach($charges as $charge):?><div class="input-group input-group-sm mb-2"><span class="input-group-text" style="min-width:65%"><?=mprH($charge['prijmeni'].' '.$charge['jmeno'].' · '.$charge['public_code'])?> · zbývá <?=mprMoney((int)$charge['remaining_minor'])?></span><input class="form-control" name="allocation[<?=(int)$charge['id']?>]" inputmode="decimal" placeholder="Kč" aria-label="Přidělená částka Kč"></div><?php endforeach;?><input class="form-control form-control-sm mb-2" name="reason" minlength="5" maxlength="1000" value="Doplnění přidělení ověřené rodinné platby." required><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required><span class="form-check-label">Potvrzuji nové přidělení</span></label><button class="btn btn-sm btn-outline-primary">Přidělit</button></form></details><?php endif;?></div></section><?php endforeach;?><?php if($receipts===[]):?><p class="text-muted">Dosud žádná rodinná platba.</p><?php endif;?>
<?php elseif($payerId>0):?><div class="alert alert-warning">Plátce se stálým VS nebyl nalezen.</div><?php endif;?></main></body></html>
