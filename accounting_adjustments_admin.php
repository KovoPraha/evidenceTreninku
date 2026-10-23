<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/staff_workspaces.php';
require_once __DIR__.'/includes/accounting_adjustments.php';
if (!isset($_SESSION['trener_id'])) {header('Location: login.php');exit;}
staffRequireActivePosition('finance_manager');
function adjustmentH(mixed $value):string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function adjustmentAmount(mixed $value):int {
    $value=str_replace(',','.',trim((string)$value));
    if (!preg_match('/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D',$value)) throw new DomainException('Zadejte kladnou částku s nejvýše dvěma desetinnými místy.');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');return (int)$whole*100+(int)str_pad($fraction,2,'0');
}
$error='';$success='';$available=clubFinanceTableExists($pdo,'accounting_refund_decisions');
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!csrf_verify((string)($_POST['csrf_token']??''))) {http_response_code(403);$error='Formulář vypršel. Obnovte stránku.';}
    else try {
        if (!$available) throw new DomainException('Účetní migrace ještě není připravena.');
        $actor=(int)$_SESSION['trener_id'];$reason=(string)($_POST['reason']??'');$confirmed=($_POST['confirmed']??'')==='1';$key=(string)($_POST['request_key']??'');
        switch ($_POST['action']??'') {
            case 'decide':
                accountingRefundDecide($pdo,['original_movement_id'=>(int)($_POST['original_movement_id']??0),'original_document_id'=>(int)($_POST['original_document_id']??0),'correction_document_id'=>(int)($_POST['correction_document_id']??0),'amount_minor'=>adjustmentAmount($_POST['amount']??''),'decided_on'=>(string)($_POST['decided_on']??''),'request_key'=>$key],$actor,$reason,$confirmed);
                $success='Rozhodnutí zaznamenáno. Peníze zatím nebyly evidovány jako vyplacené.';break;
            case 'pay':
                accountingRefundPay($pdo,(int)($_POST['decision_id']??0),['account_id'=>(int)($_POST['account_id']??0),'amount_minor'=>adjustmentAmount($_POST['amount']??''),'effective_on'=>(string)($_POST['effective_on']??''),'source_reference'=>(string)($_POST['source_reference']??''),'evidence'=>(string)($_POST['evidence']??''),'request_key'=>$key],$actor,$reason,$confirmed);
                $success='Skutečně provedená výplata je zaznamenána v peněžních pohybech.';break;
            case 'cancel':accountingRefundCancel($pdo,(int)($_POST['decision_id']??0),$actor,$reason,$confirmed,$key);$success='Nevyplacené rozhodnutí bylo auditovaně zrušeno.';break;
            case 'physical':
                [$documentId,$lineKey]=array_pad(explode('|',(string)($_POST['document_line']??''),2),2,'');
                accountingReturnRecord($pdo,(int)$documentId,$lineKey,(int)($_POST['quantity']??0),(string)($_POST['state']??''),$actor,$reason,$confirmed,$key);$success='Stav fyzického vrácení zaznamenán. Skladové množství se tím nemění.';break;
            default:throw new DomainException('Neznámý úkon.');
        }
    } catch(DomainException|InvalidArgumentException $e) {http_response_code(422);$error=$e->getMessage();}
    catch(Throwable $e) {error_log('accounting_adjustments: '.$e->getMessage());http_response_code(422);$error='Úkon se nepodařil. Zkontrolujte účetní podklady; žádná částečná změna nebyla potvrzena.';}
}
$decisions=$available?$pdo->query("SELECT d.*,COALESCE((SELECT SUM(p.amount_minor) FROM accounting_refund_payments p WHERE p.decision_id=d.id),0) AS paid_minor,EXISTS(SELECT 1 FROM accounting_adjustment_events e WHERE e.decision_id=d.id AND e.event_type='decision_cancelled') AS cancelled FROM accounting_refund_decisions d ORDER BY d.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC):[];
$movements=$available?$pdo->query('SELECT id,amount_minor,currency,effective_on,source_reference FROM accounting_movements WHERE amount_minor>0 ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC):[];
$accounts=$available?$pdo->query('SELECT * FROM accounting_accounts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC):[];
$events=$available?$pdo->query('SELECT * FROM accounting_adjustment_events ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC):[];
$documents=$available?$pdo->query("SELECT id,number,total_minor,original_document_id,snapshot_json FROM accounting_documents WHERE status='issued' ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC):[];
function adjustmentFields():void {echo csrf_field();echo '<input type="hidden" name="request_key" value="'.bin2hex(random_bytes(16)).'">';}
function adjustmentConfirm():void {echo '<label class="form-label mt-2">Důvod <input class="form-control" name="reason" minlength="10" maxlength="1000" required></label><div class="form-check my-2"><input class="form-check-input" type="checkbox" name="confirmed" value="1" required><label class="form-check-label">Potvrzuji správnost úkonu a podkladů</label></div>';}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Opravy a vratky</title><?php require_once __DIR__.'/includes/ui_shell.php';appUiAssets();?></head><body><?php require __DIR__.'/hlavicka.php';?>
<main class="container py-4"><h1>Opravy a vratky</h1><p>Opravný doklad, rozhodnutí o vrácení, skutečná výplata a převzetí zboží mají samostatnou evidenci. Původní příjem a vydané doklady zůstávají zachovány.</p>
<p><a href="accounting_documents_admin.php">Doklady a položkové opravy</a> · <a href="accounting_ledger_admin.php">Peněžní pohyby a příděly</a></p>
<?php if (!$available):?><div class="alert alert-warning">Účetní migrace ještě není připravena.</div><?php endif;?>
<?php if ($error):?><div class="alert alert-danger" role="alert"><?=adjustmentH($error)?></div><?php endif;?>
<?php if ($success):?><div class="alert alert-success"><?=adjustmentH($success)?></div><?php endif;?>
<?php if ($available):?>
<section class="card card-body mb-4"><h2 class="h4">Rozhodnout o vratce nebo přeplatku</h2><p>Částku již přidělenou na úhradu nejprve doloženě uvolněte v přídělech. Uvolnění přídělu ani rozhodnutí o vratce nejsou peněžní výdaj.</p>
<form method="post"><?php adjustmentFields();?><input type="hidden" name="action" value="decide"><div class="row g-3">
<label class="col-md-6">Původní příjem<select class="form-select" name="original_movement_id" required><option value="">Vyberte doložený příjem</option><?php foreach ($movements as $m):$b=accountingAdjustmentBalance($pdo,(int)$m['id']);?><option value="<?=(int)$m['id']?>">#<?=(int)$m['id']?> · <?=adjustmentH($m['source_reference'])?> · volné <?=adjustmentH(number_format($b['available_minor']/100,2,',',' ').' '.$m['currency'])?></option><?php endforeach;?></select></label>
<label class="col-md-3">Částka (Kč)<input class="form-control" name="amount" inputmode="decimal" required></label><label class="col-md-3">Datum rozhodnutí<input class="form-control" type="date" name="decided_on" value="<?=date('Y-m-d')?>" required></label>
<label class="col-md-6">Původní doklad<select class="form-select" name="original_document_id"><option value="">Volný přeplatek bez dokladu</option><?php foreach ($documents as $doc):if((int)$doc['total_minor']<=0)continue;?><option value="<?=(int)$doc['id']?>"><?=adjustmentH($doc['number'].' · '.number_format((int)$doc['total_minor']/100,2,',',' ').' Kč')?></option><?php endforeach;?></select></label><label class="col-md-6">Vydaný opravný doklad<select class="form-select" name="correction_document_id"><option value="">Přeplatek bez opravy ceny</option><?php foreach ($documents as $doc):if(!(int)$doc['original_document_id'])continue;?><option value="<?=(int)$doc['id']?>"><?=adjustmentH($doc['number'].' · '.number_format((int)$doc['total_minor']/100,2,',',' ').' Kč')?></option><?php endforeach;?></select></label></div>
<?php adjustmentConfirm();?><button class="btn btn-primary">Zaznamenat rozhodnutí</button></form></section>
<section><h2 class="h4">Rozhodnuté vratky</h2><?php foreach ($decisions as $d):$remaining=(int)$d['amount_minor']-(int)$d['paid_minor'];?><article class="card card-body mb-3"><h3 class="h5">Vratka #<?=(int)$d['id']?> · <?=adjustmentH(number_format((int)$d['amount_minor']/100,2,',',' ').' '.$d['currency'])?></h3>
<p>Příjem #<?=(int)$d['original_movement_id']?> · rozhodnuto <?=adjustmentH($d['decided_on'])?> · vyplaceno <?=adjustmentH(number_format((int)$d['paid_minor']/100,2,',',' '))?> · <strong><?=$d['cancelled']?'Zrušeno':($remaining===0?'Vyplaceno':'Zbývá '.adjustmentH(number_format($remaining/100,2,',',' ')))?></strong><br><?=adjustmentH($d['reason'])?></p>
<?php if (!$d['cancelled'] && $remaining>0):?><details><summary>Zaznamenat již provedenou výplatu</summary><form method="post" class="mt-3"><?php adjustmentFields();?><input type="hidden" name="action" value="pay"><input type="hidden" name="decision_id" value="<?=(int)$d['id']?>"><div class="row g-3">
<label class="col-md-3">Částka (Kč)<input class="form-control" name="amount" value="<?=number_format($remaining/100,2,'.','')?>" required></label><label class="col-md-3">Skutečné datum<input class="form-control" type="date" name="effective_on" required></label>
<label class="col-md-6">Účet nebo pokladna<select class="form-select" name="account_id" required><?php foreach ($accounts as $a):?><option value="<?=(int)$a['id']?>">#<?=(int)$a['id']?> <?=adjustmentH($a['label']??$a['name']??$a['code']??'')?></option><?php endforeach;?></select></label>
<label class="col-md-6">Reference banky / pokladního dokladu<input class="form-control" name="source_reference" maxlength="160" required></label><label class="col-md-6">Doklad o skutečné výplatě<input class="form-control" name="evidence" maxlength="1000" required></label></div><?php adjustmentConfirm();?><button class="btn btn-success">Potvrdit skutečnou výplatu</button></form></details>
<?php if (!(int)$d['paid_minor']):?><details class="mt-3"><summary>Zrušit dosud nevyplacené rozhodnutí</summary><form method="post"><?php adjustmentFields();?><input type="hidden" name="action" value="cancel"><input type="hidden" name="decision_id" value="<?=(int)$d['id']?>"><?php adjustmentConfirm();?><button class="btn btn-outline-danger">Zrušit rozhodnutí</button></form></details><?php endif;?>
<?php endif;?></article><?php endforeach;?></section>
<section class="card card-body my-4"><h2 class="h4">Fyzické vrácení zboží</h2><p>Jde o aktuální stav celého uvedeného množství položky opravného dokladu. Každá změna zůstává v historii. Příjem do prodejného skladu se řeší samostatně.</p><form method="post"><?php adjustmentFields();?><input type="hidden" name="action" value="physical"><div class="row g-3">
<label class="col-md-6">Položka opravného dokladu<select class="form-select" name="document_line" required><option value="">Vyberte vracenou položku</option><?php foreach ($documents as $doc):if(!(int)$doc['original_document_id'])continue;$snapshot=json_decode($doc['snapshot_json'],true);foreach($snapshot['lines']??[] as $line):if((int)$line['quantity']<1)continue;?><option value="<?=adjustmentH($doc['id'].'|'.$line['line_key'])?>"><?=adjustmentH($doc['number'].' · '.($line['label']??'Položka').' · '.$line['quantity'].' ks')?></option><?php endforeach;endforeach;?></select></label><label class="col-md-2">Množství<input class="form-control" type="number" name="quantity" min="1" required></label>
<label class="col-md-4">Stav<select class="form-select" name="state"><option value="awaiting_return">Čeká na vrácení</option><option value="received_inspection">Převzato, čeká na kontrolu</option><option value="received_damaged">Převzato poškozené</option><option value="return_not_required">Vrácení se nevyžaduje</option></select></label></div><?php adjustmentConfirm();?><button class="btn btn-primary">Zapsat stav převzetí</button></form></section>
<h2 class="h4">Historie oprav a převzetí</h2><div class="table-responsive"><table class="table"><thead><tr><th>Čas</th><th>Doklad / vratka</th><th>Úkon</th><th>Položka</th><th>Množství</th><th>Důvod</th></tr></thead><tbody><?php $labels=['decision_cancelled'=>'Rozhodnutí zrušeno','awaiting_return'=>'Čeká na vrácení','received_inspection'=>'Převzato ke kontrole','received_damaged'=>'Převzato poškozené','return_not_required'=>'Vrácení se nevyžaduje'];foreach ($events as $e):?><tr><td><?=adjustmentH($e['recorded_at'])?></td><td><?=adjustmentH($e['document_id']?'Doklad #'.$e['document_id']:'Vratka #'.$e['decision_id'])?></td><td><?=adjustmentH($labels[$e['event_type']]??$e['event_type'])?></td><td><?=adjustmentH($e['line_key'])?></td><td><?=(int)$e['quantity']?></td><td><?=adjustmentH($e['reason'])?></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></main></body></html>
