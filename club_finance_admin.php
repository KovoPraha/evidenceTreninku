<?php
declare(strict_types=1);

require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/club_finance.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
staffRequireActivePosition('finance_manager');

function financeAdminH(mixed $value):string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function financeAdminMinor(string $value):int {
    $value=str_replace([' ', ','],['','.'],trim($value));
    if (preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D',$value)!==1) throw new InvalidArgumentException('Částka musí být kladné číslo s nejvýše dvěma desetinnými místy.');
    [$whole,$decimal]=array_pad(explode('.',$value,2),2,'');
    return (int)$whole*100+(int)str_pad($decimal,2,'0');
}

$errors=[];$saved=false;
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) $errors[]='Formulář vypršel. Obnovte stránku.';
    else try {
        clubFinanceSaveSettings($pdo,(int)$_SESSION['trener_id'],[
            'enforcement_enabled'=>($_POST['enforcement_enabled']??'')==='1',
            'grace_days'=>(int)($_POST['grace_days']??-1),
            'registration_limit_minor'=>financeAdminMinor((string)($_POST['registration_limit_czk']??'')),
            'training_notice_minor'=>financeAdminMinor((string)($_POST['training_notice_czk']??'')),
        ],(string)($_POST['reason']??''),($_POST['confirmed']??'')==='1');
        $saved=true;
    } catch (InvalidArgumentException|ClubFinanceException $e) { $errors[]=$e->getMessage(); }
    catch (Throwable $e) { error_log('club_finance_admin: '.$e->getMessage());$errors[]='Nastavení se nepodařilo uložit.'; }
}
$settings=clubFinanceSettings($pdo);
$hasMigration=clubFinanceTableExists($pdo,'club_finance_settings');
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finanční podmínky přihlášek</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__.'/hlavicka.php'; ?>
<main class="container py-4" style="max-width:950px"><h1 class="h3">Finanční podmínky přihlášek</h1>
<p class="text-muted">Saldo zahrnuje platné členské předpisy, účasti na akcích a objednávky e-shopu přiřazené sportovci. Omezení se počítá až po uplynutí nastavené lhůty od splatnosti. Stornované a expirované objednávky se za dluh nepovažují.</p>
<?php if (!$hasMigration):?><div class="alert alert-danger">Databázová migrace ještě není aplikována. Omezení zůstává vypnuté.</div><?php endif;?>
<?php if ($saved):?><div class="alert alert-success">Nastavení bylo auditovaně uloženo.</div><?php endif;?>
<?php foreach ($errors as $error):?><div class="alert alert-danger"><?=financeAdminH($error)?></div><?php endforeach;?>
<div class="alert alert-warning">Před zapnutím porovnejte zobrazené zůstatky s potvrzenými platbami. Fio import sám platby nepotvrzuje a běžné nezaplacené objednávky mohou po splatnosti expirovat.</div>
<form method="post" class="card card-body border-0 shadow-sm row g-3"><?=csrf_field()?>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="enforcement_enabled" value="1" id="finance-enabled" <?=$settings['enforcement_enabled']?'checked':''?>><label class="form-check-label" for="finance-enabled">Zapnout omezení nových přihlášek</label></div>
<div class="col-md-4"><label class="form-label" for="finance-grace">Dní po splatnosti</label><input class="form-control" id="finance-grace" type="number" name="grace_days" min="0" max="365" value="<?=(int)$settings['grace_days']?>" required><div class="form-text">Při 14 dnech rozhoduje dluh až následující den.</div></div>
<div class="col-md-4"><label class="form-label" for="finance-limit">Zákaz nové přihlášky nad (Kč)</label><input class="form-control" id="finance-limit" name="registration_limit_czk" inputmode="decimal" value="<?=financeAdminH(number_format($settings['registration_limit_minor']/100,2,'.',''))?>" required></div>
<div class="col-md-4"><label class="form-label" for="finance-notice">Upozornění u tréninků od (Kč)</label><input class="form-control" id="finance-notice" name="training_notice_czk" inputmode="decimal" value="<?=financeAdminH(number_format($settings['training_notice_minor']/100,2,'.',''))?>" required><div class="form-text">Termíny zůstávají viditelné.</div></div>
<div class="col-12"><label class="form-label" for="finance-reason">Důvod změny</label><input class="form-control" id="finance-reason" name="reason" maxlength="1000" required></div>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" id="finance-confirm" required><label class="form-check-label" for="finance-confirm">Potvrzuji změnu pravidel a její dopad na nové přihlášky</label></div>
<div class="col-12"><button class="btn btn-primary" <?=$hasMigration?'':'disabled'?>>Uložit nastavení</button> <a class="btn btn-outline-secondary" href="member_fee_plans_admin.php">Měsíční příspěvky</a></div>
</form></main></body></html>
