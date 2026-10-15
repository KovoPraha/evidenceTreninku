<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/member_fee_annual.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
staffRequireActivePosition('finance_manager');

function annualAdminH(mixed $value):string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function annualAdminMinor(string $value):int {
    $value=str_replace([' ', ','],['','.'],trim($value));
    if (preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D',$value)!==1) throw new InvalidArgumentException('Přijatá částka má neplatný formát.');
    [$whole,$decimal]=array_pad(explode('.',$value,2),2,'');return (int)$whole*100+(int)str_pad($decimal,2,'0');
}

$plans=$pdo->query("SELECT p.id,p.name,p.amount_minor,t.name AS team_name FROM member_fee_plans p JOIN club_teams t ON t.id=p.team_id WHERE p.status='active' ORDER BY p.name,p.id")->fetchAll(PDO::FETCH_ASSOC);
$planId=(int)($_POST['plan_id']??$_GET['plan_id']??0);
$period=(string)($_POST['period']??$_GET['period']??(new DateTimeImmutable('first day of this month'))->format('Y-m'));
$personId=(int)($_POST['sportovec_id']??0);
$discount=(int)($_POST['discount_basis_points']??0);
$members=[];$quote=null;$errors=[];$success=(string)($_SESSION['annual_success']??'');unset($_SESSION['annual_success']);
if ($planId>0) {
    try { [$from,$to]=memberFeePlanMonth($period);$members=memberFeePlanEligibleMembers($pdo,$planId,$from,$to); }
    catch (Throwable $e) { $errors[]='Zvolte platné období a plán.'; }
}
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else try {
        $action=(string)($_POST['action']??'');
        if ($action==='preview') $quote=memberFeeAnnualQuote($pdo,$planId,$personId,$period,$discount);
        elseif ($action==='confirm') {
            $result=memberFeeAnnualConfirm($pdo,$planId,$personId,$period,$discount,annualAdminMinor((string)($_POST['received_czk']??'')),(string)($_POST['bank_reference']??''),(string)($_POST['paid_on']??''),(int)$_SESSION['trener_id'],(string)($_POST['reason']??''),($_POST['confirmed']??'')==='1');
            $_SESSION['annual_success']='Roční platba #'.$result['receipt_id'].' přiřazena k 12 měsíčním předpisům. Celkem '.number_format($result['amount_minor']/100,2,',',' ').' Kč.';
            header('Location: member_fee_annual_admin.php?plan_id='.$planId.'&period='.rawurlencode($period),true,303);exit;
        } else throw new InvalidArgumentException('Neplatná akce.');
    } catch (InvalidArgumentException|MemberFeePlanException|MemberChargeAdminException|ClubFinanceException|PDOException $e) { $errors[]=$e->getMessage(); }
    catch (Throwable $e) { error_log('member_fee_annual_admin: '.$e->getMessage());$errors[]='Roční platbu se nepodařilo bezpečně zpracovat.'; }
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Roční úhrada příspěvků</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1000px"><h1 class="h3">Roční úhrada příspěvků</h1><p class="text-muted">Jedna ověřená bankovní platba se rozdělí mezi dvanáct samostatných měsíčních předpisů. Sleva snižuje cenu každého měsíce. Funkce sama nepotvrzuje ani nespouští Fio import.</p>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=annualAdminH($error)?></div><?php endforeach;?>
<?php if($success!==''):?><div class="alert alert-success"><?=annualAdminH($success)?></div><?php endif;?>
<form method="get" class="card card-body border-0 shadow-sm mb-3"><label class="form-label" for="annual-plan">Plán příspěvků</label><div class="d-flex gap-2"><select class="form-select" name="plan_id" id="annual-plan" required><option value="">Vyberte plán</option><?php foreach($plans as$plan):?><option value="<?=(int)$plan['id']?>" <?=$planId===(int)$plan['id']?'selected':''?>><?=annualAdminH($plan['team_name'].' · '.$plan['name'])?></option><?php endforeach;?></select><button class="btn btn-outline-primary">Načíst členy</button></div></form>
<?php if($planId>0):?><form method="post" class="card card-body border-0 shadow-sm mb-3 row g-3"><?=csrf_field()?><input type="hidden" name="action" value="preview"><input type="hidden" name="plan_id" value="<?=$planId?>">
<div class="col-md-5"><label class="form-label" for="annual-person">Sportovec</label><select class="form-select" name="sportovec_id" id="annual-person" required><option value="">Vyberte sportovce</option><?php foreach($members as$member):?><option value="<?=(int)$member['id']?>" <?=$personId===(int)$member['id']?'selected':''?>><?=annualAdminH($member['prijmeni'].' '.$member['jmeno'])?></option><?php endforeach;?></select></div>
<div class="col-md-3"><label class="form-label" for="annual-period">První měsíc</label><input class="form-control" id="annual-period" type="month" name="period" value="<?=annualAdminH($period)?>" required></div>
<div class="col-md-4"><label class="form-label" for="annual-discount">Roční sleva (%)</label><select class="form-select" id="annual-discount" name="discount_basis_points"><?php foreach([0,500,1000,1500,2000] as$bps):?><option value="<?=$bps?>" <?=$discount===$bps?'selected':''?>><?=annualAdminH(number_format($bps/100,0))?> %</option><?php endforeach;?></select></div>
<div class="col-12"><button class="btn btn-primary" <?=$members===[]?'disabled':''?>>Zobrazit výpočet a ověřit předpisy</button></div></form><?php endif;?>
<?php if($quote!==null):?><section class="card card-body border-primary shadow-sm"><h2 class="h5">Potvrzení ověřené úhrady</h2><p>Období <strong><?=annualAdminH($quote['periods'][0])?>–<?=annualAdminH($quote['periods'][11])?></strong>, každý měsíc <strong><?=annualAdminH(number_format($quote['monthly_minor']/100,2,',',' '))?> Kč</strong>, sleva celkem <?=annualAdminH(number_format($quote['discount_minor']/100,2,',',' '))?> Kč. Jedna přijatá platba musí být přesně <strong><?=annualAdminH(number_format($quote['total_minor']/100,2,',',' '))?> Kč</strong>.</p>
<form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="action" value="confirm"><input type="hidden" name="plan_id" value="<?=$planId?>"><input type="hidden" name="sportovec_id" value="<?=$personId?>"><input type="hidden" name="period" value="<?=annualAdminH($period)?>"><input type="hidden" name="discount_basis_points" value="<?=$discount?>">
<div class="col-md-4"><label class="form-label" for="annual-amount">Skutečně přijatá částka (Kč)</label><input class="form-control" id="annual-amount" name="received_czk" inputmode="decimal" value="<?=annualAdminH(number_format($quote['total_minor']/100,2,'.',''))?>" required></div>
<div class="col-md-4"><label class="form-label" for="annual-ref">Jedinečná bankovní reference</label><input class="form-control" id="annual-ref" name="bank_reference" maxlength="100" required></div>
<div class="col-md-4"><label class="form-label" for="annual-date">Datum připsání</label><input class="form-control" id="annual-date" type="date" name="paid_on" value="<?=(new DateTimeImmutable('today'))->format('Y-m-d')?>" required></div>
<div class="col-12"><label class="form-label" for="annual-reason">Auditovaný důvod</label><input class="form-control" id="annual-reason" name="reason" maxlength="1000" value="Ověřená hromadná úhrada dvanácti měsíčních předpisů." required></div>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" id="annual-confirm" name="confirmed" value="1" required><label class="form-check-label" for="annual-confirm">Ověřil/a jsem připsání celé částky na účet klubu a správnou bankovní referenci</label></div>
<div class="col-12"><button class="btn btn-danger">Zapsat jednu platbu a uhradit 12 předpisů</button></div></form></section><?php endif;?>
<p class="mt-3"><a href="member_fee_plans_admin.php">Zpět na měsíční plány</a></p></main></body></html>
