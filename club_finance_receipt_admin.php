<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/club_finance.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
staffRequireActivePosition('finance_manager');

function receiptAdminH(mixed $value):string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function receiptAdminMinor(string $value):int {
    $value=str_replace([' ', ','],['','.'],trim($value));
    if (preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D',$value)!==1) throw new InvalidArgumentException('Zadejte částku s nejvýše dvěma desetinnými místy.');
    [$whole,$decimal]=array_pad(explode('.',$value,2),2,'');return (int)$whole*100+(int)str_pad($decimal,2,'0');
}

$sportovecId=(int)($_POST['sportovec_id']??$_GET['sportovec_id']??0);
$person=null;$errors=[];$success=(string)($_SESSION['finance_receipt_success']??'');unset($_SESSION['finance_receipt_success']);
if ($sportovecId>0) {$s=$pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE id=?');$s->execute([$sportovecId]);$person=$s->fetch(PDO::FETCH_ASSOC);}
if (!$person) {
    if ($sportovecId>0) { http_response_code(404);exit('Sportovec nebyl nalezen.'); }
    $search=trim((string)($_GET['q']??''));$matches=[];
    if ($search!==''&&mb_strlen($search,'UTF-8')<=100) {
        $s=$pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE jmeno LIKE ? OR prijmeni LIKE ? ORDER BY prijmeni,jmeno,id LIMIT 30');
        $s->execute(['%'.$search.'%','%'.$search.'%']);$matches=$s->fetchAll(PDO::FETCH_ASSOC);
    }
    ?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ověřená platba sportovce</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?><main class="container py-4" style="max-width:800px"><h1 class="h3">Ověřená platba sportovce</h1><p>Najděte sportovce; platbu zapíšete až po ověření na bankovním výpisu.</p><form method="get" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="<?=receiptAdminH($search)?>" placeholder="Jméno nebo příjmení" maxlength="100" required><button class="btn btn-primary">Hledat</button></form><?php if($search!==''):?><ul class="list-group"><?php foreach($matches as$match):?><li class="list-group-item"><a href="?sportovec_id=<?=(int)$match['id']?>"><?=receiptAdminH($match['prijmeni'].' '.$match['jmeno'])?></a></li><?php endforeach;?></ul><?php if($matches===[]):?><div class="alert alert-info">Žádný sportovec nenalezen.</div><?php endif;?><?php endif;?></main></body></html><?php
    exit;
}
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else try {
        $result=clubFinanceRecordBankReceipt($pdo,$sportovecId,receiptAdminMinor((string)($_POST['amount_czk']??'')),(string)($_POST['bank_reference']??''),(string)($_POST['paid_on']??''),(int)$_SESSION['trener_id'],(string)($_POST['reason']??''),($_POST['confirmed']??'')==='1');
        $_SESSION['finance_receipt_success']='Platba #'.$result['receipt_id'].' uložena; přiřazeno '.number_format($result['allocated_minor']/100,2,',',' ').' Kč, nepřiřazený přeplatek '.number_format($result['credit_minor']/100,2,',',' ').' Kč.';
        header('Location: club_finance_receipt_admin.php?sportovec_id='.$sportovecId,true,303);exit;
    } catch (InvalidArgumentException|ClubFinanceException $e) { $errors[]=$e->getMessage(); }
    catch (Throwable $e) { error_log('club_finance_receipt_admin: '.$e->getMessage());$errors[]='Platbu se nepodařilo bezpečně uložit.'; }
}
$balance=clubFinanceBalanceForAthlete($pdo,$sportovecId);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Přijatá platba sportovce</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?>
<main class="container py-4" style="max-width:900px"><h1 class="h3">Přijatá platba: <?=receiptAdminH($person['jmeno'].' '.$person['prijmeni'])?></h1>
<p class="text-muted">Použijte pouze pro částku ověřenou na bankovním výpisu. Systém ji postupně přiřadí k nejstarším platným předpisům a bankovním objednávkám; zbytek zůstane jako přeplatek. Automatický Fio import zůstává oddělený a nic zde nepotvrzuje.</p>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=receiptAdminH($error)?></div><?php endforeach;?><?php if($success!==''):?><div class="alert alert-success"><?=receiptAdminH($success)?></div><?php endif;?>
<div class="alert alert-light border"><strong>Aktuálně k úhradě:</strong> <?=receiptAdminH(number_format($balance['open_minor']/100,2,',',' '))?> Kč · po lhůtě <?=receiptAdminH(number_format($balance['overdue_minor']/100,2,',',' '))?> Kč · přeplatek <?=receiptAdminH(number_format($balance['credit_minor']/100,2,',',' '))?> Kč</div>
<form method="post" class="card card-body border-0 shadow-sm row g-3"><?=csrf_field()?><input type="hidden" name="sportovec_id" value="<?=$sportovecId?>">
<div class="col-md-4"><label class="form-label" for="receipt-amount">Skutečně přijatá částka (Kč)</label><input class="form-control" id="receipt-amount" name="amount_czk" inputmode="decimal" required></div>
<div class="col-md-4"><label class="form-label" for="receipt-ref">Jedinečná bankovní reference</label><input class="form-control" id="receipt-ref" name="bank_reference" maxlength="100" required></div>
<div class="col-md-4"><label class="form-label" for="receipt-date">Datum připsání</label><input class="form-control" id="receipt-date" type="date" name="paid_on" value="<?=(new DateTimeImmutable('today'))->format('Y-m-d')?>" required></div>
<div class="col-12"><label class="form-label" for="receipt-reason">Auditovaný důvod</label><input class="form-control" id="receipt-reason" name="reason" maxlength="1000" required></div>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="receipt-confirm" required><label class="form-check-label" for="receipt-confirm">Potvrzuji přesnou shodu s připsanou bankovní platbou</label></div>
<div class="col-12"><button class="btn btn-danger">Zapsat a přiřadit platbu</button> <a class="btn btn-outline-secondary" href="sportovec_karta.php?sportovec_id=<?=$sportovecId?>">Zpět na kartu</a></div></form>
<h2 class="h5 mt-4">Otevřené položky</h2><ul class="list-group"><?php foreach($balance['items'] as$item):?><li class="list-group-item d-flex justify-content-between gap-2"><span><?=receiptAdminH($item['title'])?> · splatnost <?=receiptAdminH($item['due_on']??'neuvedena')?></span><strong><?=receiptAdminH(number_format($item['amount_minor']/100,2,',',' '))?> Kč</strong></li><?php endforeach;?></ul>
</main></body></html>
