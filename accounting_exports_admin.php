<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/accounting_export.php';
require_once __DIR__.'/includes/financial_decisions.php';
staffRequireActivePosition('finance_manager');
$actor=(int)$_SESSION['trener_id'];accountingExportActor($pdo,$actor);header('Cache-Control: private, no-store');$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new DomainException('Formulář vypršel. Obnovte stránku.');
        if(($_POST['action']??'')==='handover'){
            $id=(int)($_POST['batch_id']??0);accountingExportHandover($pdo,$actor,$id,(string)($_POST['handover_action']??''),(int)($_POST['expected_event']??0),(string)($_POST['reason']??''),($_POST['confirm_action']??'')==='1');$row=['id'=>$id];
        }else $row=accountingExportCreate($pdo,(string)($_POST['period_from']??''),(string)($_POST['period_to']??''),(string)($_POST['request_key']??''),$actor,(string)($_POST['reason']??''),($_POST['confirm_action']??'')==='1');
        header('Location: accounting_exports_admin.php?id='.(int)$row['id'],true,303);exit;
    }catch(DomainException|InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('accounting export: '.$e->getMessage());$error='Export se nepodařilo vytvořit. Nevznikla hotová revize; opakujte po odstranění příčiny.';}
}
$rows=$pdo->query('SELECT id,period_from,period_to,revision,status,created_at FROM accounting_export_batches ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$selected=null;$snapshot=null;
if((int)($_GET['id']??0)>0){try{$selected=accountingExportGet($pdo,(int)$_GET['id']);$snapshot=json_decode($selected['snapshot_json'],true,512,JSON_THROW_ON_ERROR);}catch(DomainException $e){$error=$e->getMessage();}}
function accountingExportH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$handover=[];$lastHandover=null;$handoverLabels=['handed_over'=>'Předáno účetní','returned'=>'Vráceno k doplnění','accepted'=>'Převzato účetní'];
if($selected){$q=$pdo->prepare("SELECT e.*,t.jmeno FROM accounting_export_events e JOIN treneri t ON t.id=e.actor_trainer_id WHERE batch_id=? AND action IN ('handed_over','returned','accepted') ORDER BY e.id DESC");$q->execute([(int)$selected['id']]);$handover=$q->fetchAll(PDO::FETCH_ASSOC);$lastHandover=$handover[0]??null;}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Účetní podklady</title><?php require_once __DIR__.'/includes/ui_shell.php'; appUiAssets(); ?></head><body class="bg-light">
<?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1>Účetní podklady</h1>
<p>Připravte neměnnou revizi XLSX a souvisejících PDF. Nová revize stejného období nahrazuje předchozí; původní archiv zůstává dostupný.</p>
<div class="alert alert-warning">Balík zahrnuje peníze, doklady, sklad, výdaje, zálohy, odměny a rozhodnutí. <strong>NEÚPLNÝ</strong> znamená, že zbývá odsouhlasení zdrojů, otevíracích stavů a metodiky oddílu nebo některá uvedená kontrola. Stažení archivu není předání účetní ani provedená platba.</div>
<?php if($error!==''): ?><div class="alert alert-danger"><?= accountingExportH($error) ?></div><?php endif; ?>
<form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?= accountingExportH(csrf_token()) ?>"><input type="hidden" name="request_key" value="<?= accountingExportH($_POST['request_key']??bin2hex(random_bytes(20))) ?>">
<div class="row g-3"><label class="col-md-6">Od<input class="form-control" type="date" name="period_from" required value="<?= accountingExportH($_POST['period_from']??date('Y-m-01')) ?>"></label><label class="col-md-6">Do<input class="form-control" type="date" name="period_to" required value="<?= accountingExportH($_POST['period_to']??date('Y-m-t')) ?>"></label></div>
<label class="mt-3">Důvod / popis revize<textarea class="form-control" name="reason" required maxlength="1000"><?= accountingExportH($_POST['reason']??'') ?></textarea></label>
<label class="my-3"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji přípravu pracovních podkladů v uvedeném rozsahu.</label><button class="btn btn-primary align-self-start">Připravit archiv XLSX + PDF</button></form>
<?php if($snapshot): ?><section class="card card-body mb-4"><h2 class="h4">Revize <?= (int)$selected['revision'] ?>: <?= accountingExportH($selected['period_from'].' – '.$selected['period_to']) ?></h2><p><?= accountingExportH($snapshot['replacement_mode']) ?></p><p>Rozhodný okamžik: <?= accountingExportH($selected['snapshot_at']) ?>. Nepřevzaté zdroje: <?= count($snapshot['pending_sources']) ?>.</p>
<ul><?php foreach(array_merge($snapshot['limitations'],$snapshot['exceptions']) as $message): ?><li><?= accountingExportH($message) ?></li><?php endforeach; ?></ul>
<a class="btn btn-outline-primary align-self-start" href="accounting_export_download.php?id=<?= (int)$selected['id'] ?>">Stáhnout tuto revizi</a>
<h3 class="h5 mt-4">Předání této revize: <?=accountingExportH($handoverLabels[$lastHandover['action']??'']??'Dosud nezaznamenáno')?></h3><p>Zaznamenejte skutečný osobní nebo jinak doložený krok. Zde se nic neodesílá. Převzetí účetní nemění kontrolní označení historického archivu.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="handover"><input type="hidden" name="batch_id" value="<?=(int)$selected['id']?>"><input type="hidden" name="expected_event" value="<?=(int)($lastHandover['id']??0)?>"><label>Krok<select class="form-select" name="handover_action"><?php foreach((['new'=>['handed_over'],'handed_over'=>['returned','accepted'],'returned'=>['handed_over'],'accepted'=>['returned']][$lastHandover['action']??'new'])as$action):?><option value="<?=$action?>"><?=$handoverLabels[$action]?></option><?php endforeach;?></select></label><label>Komu, kdy, doklad o předání / důvod vrácení<textarea class="form-control" name="reason" maxlength="1000" required></textarea></label><label class="my-2"><input type="checkbox" name="confirm_action" value="1" required> Potvrzuji skutečně provedený krok.</label><br><button class="btn btn-outline-primary">Zapsat předání</button></form><ul class="mt-3"><?php foreach($handover as$event):?><li><?=accountingExportH($event['created_at'].' · '.$event['jmeno'].' · '.$handoverLabels[$event['action']].' · '.$event['reason'])?></li><?php endforeach;?></ul></section><?php endif; ?>
<h2 class="h4">Archiv revizí</h2><div class="table-responsive"><table class="table"><thead><tr><th>Období</th><th>Revize</th><th>Vytvořeno</th><th>Stav</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= accountingExportH($r['period_from'].' – '.$r['period_to']) ?></td><td><?= (int)$r['revision'] ?></td><td><?= accountingExportH($r['created_at']) ?></td><td>Neúplný pracovní balík</td><td><a href="accounting_exports_admin.php?id=<?= (int)$r['id'] ?>">Podrobnosti</a></td></tr><?php endforeach; ?></tbody></table></div>
</main></body></html>
