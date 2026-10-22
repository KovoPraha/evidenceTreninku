<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/accounting_export.php';
staffRequireActivePosition('finance_manager');
$actor=(int)$_SESSION['trener_id'];$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new DomainException('Formulář vypršel. Obnovte stránku.');
        $row=accountingExportCreate($pdo,(string)($_POST['period_from']??''),(string)($_POST['period_to']??''),(string)($_POST['request_key']??''),$actor,(string)($_POST['reason']??''),($_POST['confirm_action']??'')==='1');
        header('Location: accounting_exports_admin.php?id='.(int)$row['id'],true,303);exit;
    }catch(DomainException|InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('accounting export: '.$e->getMessage());$error='Export se nepodařilo vytvořit. Nevznikla hotová revize; opakujte po odstranění příčiny.';}
}
$rows=$pdo->query('SELECT id,period_from,period_to,revision,status,created_at FROM accounting_export_batches ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$selected=null;$snapshot=null;
if((int)($_GET['id']??0)>0){try{$selected=accountingExportGet($pdo,(int)$_GET['id']);$snapshot=json_decode($selected['snapshot_json'],true,512,JSON_THROW_ON_ERROR);}catch(DomainException $e){$error=$e->getMessage();}}
function accountingExportH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Účetní podklady</title><?php require_once __DIR__.'/includes/ui_shell.php'; appUiAssets(); ?></head><body class="bg-light">
<?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1>Účetní podklady</h1>
<p>Připravte neměnnou revizi XLSX a souvisejících PDF. Nová revize stejného období nahrazuje předchozí; původní archiv zůstává dostupný.</p>
<div class="alert alert-warning">Pracovní rozsah: peněžní evidence a doklady. Úplné účetní předání čeká na zapojení skladu, výdajů, záloh a odměn a potvrzení účetní. Balík je označen <strong>NEÚPLNÝ</strong>.</div>
<?php if($error!==''): ?><div class="alert alert-danger"><?= accountingExportH($error) ?></div><?php endif; ?>
<form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?= accountingExportH(csrf_token()) ?>"><input type="hidden" name="request_key" value="<?= accountingExportH($_POST['request_key']??bin2hex(random_bytes(20))) ?>">
<div class="row g-3"><label class="col-md-6">Od<input class="form-control" type="date" name="period_from" required value="<?= accountingExportH($_POST['period_from']??date('Y-m-01')) ?>"></label><label class="col-md-6">Do<input class="form-control" type="date" name="period_to" required value="<?= accountingExportH($_POST['period_to']??date('Y-m-t')) ?>"></label></div>
<label class="mt-3">Důvod / popis revize<textarea class="form-control" name="reason" required maxlength="1000"><?= accountingExportH($_POST['reason']??'') ?></textarea></label>
<label class="my-3"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji přípravu pracovních podkladů v uvedeném rozsahu.</label><button class="btn btn-primary align-self-start">Připravit archiv XLSX + PDF</button></form>
<?php if($snapshot): ?><section class="card card-body mb-4"><h2 class="h4">Revize <?= (int)$selected['revision'] ?>: <?= accountingExportH($selected['period_from'].' – '.$selected['period_to']) ?></h2><p><?= accountingExportH($snapshot['replacement_mode']) ?></p><p>Rozhodný okamžik: <?= accountingExportH($selected['snapshot_at']) ?>. Nepřevzaté zdroje: <?= count($snapshot['pending_sources']) ?>.</p>
<ul><?php foreach(array_merge($snapshot['limitations'],$snapshot['exceptions']) as $message): ?><li><?= accountingExportH($message) ?></li><?php endforeach; ?></ul>
<a class="btn btn-outline-primary align-self-start" href="accounting_export_download.php?id=<?= (int)$selected['id'] ?>">Stáhnout tuto revizi</a></section><?php endif; ?>
<h2 class="h4">Archiv revizí</h2><div class="table-responsive"><table class="table"><thead><tr><th>Období</th><th>Revize</th><th>Vytvořeno</th><th>Stav</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= accountingExportH($r['period_from'].' – '.$r['period_to']) ?></td><td><?= (int)$r['revision'] ?></td><td><?= accountingExportH($r['created_at']) ?></td><td>Neúplný pracovní balík</td><td><a href="accounting_exports_admin.php?id=<?= (int)$r['id'] ?>">Podrobnosti</a></td></tr><?php endforeach; ?></tbody></table></div>
</main></body></html>
