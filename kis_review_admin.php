<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/kis_review_handoff.php';
require_once __DIR__.'/includes/staff_login_return.php';
if (!isset($_SESSION['trener_id'])) {
    $loginReturn = staffLoginReturnTarget('kis_review_admin.php?' . http_build_query($_GET));
    header('Location: login.php?return_to=' . rawurlencode($loginReturn), true, 303);
    exit;
}
if (!canAccess('sync_evidence')) {
    $feedbackStatus = 403;
    $feedbackTitle = 'Řízené předání není v této pozici dostupné';
    $feedbackMessage = 'Řízené předání má na starosti Registrář členů a KIS. Vraťte se na pracovní rozcestník a zvolte tuto pozici, pokud ji máte přidělenou.';
    include __DIR__.'/includes/staff_page_feedback.php';
    exit;
}
header('Cache-Control: no-store, private');
function krH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$runId=(int)($_GET['run_id']??0);$planId=(int)($_GET['plan_id']??0);
$allowed=defined('JE_LOKALNE')&&JE_LOKALNE===true&&roleAtLeast('admin');$actor=(int)$_SESSION['trener_id'];
$error='';$message='';$source=null;$record=null;
try{
    if($planId>0){$record=kisReviewPlan($pdo,$planId);if((int)$record['run_id']!==$runId)throw new DomainException('Plán patří jinému běhu.');}
    // Rollback uses audited target snapshots and must remain possible after source loss.
    if(!$record||$record['status']==='preview'||($_POST['action']??'')==='preview'){
        $source=kisReviewSource($pdo,$runId);
        if(isset($_GET['fingerprint'])&&!hash_equals($source['fingerprint'],(string)$_GET['fingerprint']))throw new DomainException('Předaný běh se změnil. Obnovte náhled v KIS centru.');
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!csrf_verify($_POST['csrf_token']??''))throw new DomainException('Neplatný bezpečnostní token.');
        if(($_POST['action']??'')==='preview'){
            if(($_POST['complete_form']??'')!=='1')throw new DomainException('Formulář nebyl přenesen celý. Rozdělte import do menších běhů.');
            $new=kisReviewPreview($pdo,$runId,(string)($_POST['source_fingerprint']??''),(array)($_POST['choices']??[]),(string)($_POST['valid_from']??''),$actor,$allowed);
            header('Location: kis_review_admin.php?run_id='.$runId.'&plan_id='.$new['id'],true,303);exit;
        }
        if(!$record)throw new DomainException('Nejprve vytvořte náhled.');
        $action=(string)($_POST['action']??'');
        if(!in_array($action,['apply','rollback'],true))throw new DomainException('Neznámá akce.');
        $result=kisReviewExecute($pdo,$planId,(string)($_POST['fingerprint']??''),$actor,$allowed,($_POST['confirm']??'')==='1',(string)($_POST['reason']??''),$action==='rollback');
        $message=$result['idempotent']?'Akce již byla provedena; žádný další zápis nevznikl.':($action==='apply'?'Osoby a zvolené soupisky byly přeneseny v izolaci.':'Přenos byl vrácen. Nové osoby jsou archivované, přidaná členství zrušená; audit zůstal zachován.');
        $record=kisReviewPlan($pdo,$planId);
    }
}catch(Throwable $e){$error=$e instanceof DomainException||$e instanceof InvalidArgumentException||$e instanceof KisRosterException||$e instanceof KisSourceArchiveException?$e->getMessage():'Předání není dostupné nebo neprošlo kontrolou. Žádný částečný převod nebyl uložen.';}
$people=$pdo->query("SELECT id,jmeno,prijmeni,kis_external_id FROM sportovci WHERE stav_clenstvi<>'archiv' ORDER BY prijmeni,jmeno,id")->fetchAll(PDO::FETCH_ASSOC);
$teams=$pdo->query("SELECT id,name FROM club_teams WHERE status='active' AND team_kind='roster' ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Řízené předání KIS</title><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"></head><body><?php include __DIR__.'/hlavicka.php';?>
<main class="container py-4"><a href="kis_sync_center.php?run_id=<?=$runId?>">Zpět do KIS centra</a><h1>Řízené předání KIS #<?=$runId?></h1>
<p>Oddělený převod jména, příjmení, narození, stabilního KIS ID a výslovně vybraných soupisek. Účty, souhlasy, potvrzené platby a osoby mimo import zůstávají beze změn.</p>
<div class="alert alert-warning">Ostrý přechod je odložen: zdroj pravdy, rozsah exportů a termín dodá vlastník; finanční paritu potvrdí účetní. Zápis z této stránky je povolen jen v izolaci. Finance se z tohoto plánu nepřenášejí.</div>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=krH($error)?></div><?php endif;?><?php if($message):?><div class="alert alert-success" role="status"><?=krH($message)?></div><?php endif;?>
<?php if($record):?><section class="card p-3 mb-4"><h2>Plán #<?=(int)$record['id']?> · <?=krH(['preview'=>'Náhled','applied'=>'Přeneseno','rolled_back'=>'Vráceno'][$record['status']]??$record['status'])?></h2><p>Otisk plánu: <code><?=krH($record['fingerprint'])?></code></p><p>Členství od <?=krH($record['plan']['valid_from'])?>. Změna zdroje nebo cílové evidence vyžaduje nový náhled.</p>
<table class="table"><thead><tr><th>KIS ID</th><th>Cíl</th><th>Před → po</th><th>Přidané soupisky</th></tr></thead><tbody><?php foreach($record['plan']['items'] as $item):?><tr><td><?=krH($item['external_id'])?></td><td><?=krH($item['target']==='new'?'Nová osoba':'Osoba #'.$item['target'])?></td><td><?php foreach($item['fields'] as $field=>$value):if(str_ends_with($field,'_norm'))continue;?><div><?=krH(['jmeno'=>'Jméno','prijmeni'=>'Příjmení','narozeni'=>'Narození','kis_external_id'=>'KIS ID'][$field]??$field)?>: <?=krH($item['before'][$field]??'—')?> → <?=krH($value??'—')?></div><?php endforeach;?></td><td><?=krH(implode(', ',$item['teams']))?:'Bez nových členství'?></td></tr><?php endforeach;?></tbody></table>
<?php if($allowed&&in_array($record['status'],['preview','applied'],true)):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="<?=$record['status']==='preview'?'apply':'rollback'?>"><input type="hidden" name="fingerprint" value="<?=krH($record['fingerprint'])?>"><label>Důvod <input name="reason" class="form-control" minlength="5" maxlength="500" required></label><label class="d-block"><input type="checkbox" name="confirm" value="1" required> Potvrzuji tento plán a jeho rozsah v izolaci.</label><button class="btn btn-primary"><?=$record['status']==='preview'?'Provést v izolaci':'Vrátit převod'?></button></form><?php endif;?></section>
<?php $events=kisReviewRows($pdo,'kis_review_events','plan_id=?',[$planId]);?><h2>Audit převodu</h2><ul><?php foreach($events as $event):?><li><?=krH($event['created_at'].' · '.(['apply'=>'Přenos','rollback'=>'Vrácení'][$event['action']]??$event['action']).' · #'.$event['actor_id'].' · '.$event['reason'])?></li><?php endforeach;?></ul><?php endif;?>
<?php if($source&&$allowed&&(!$record||$record['status']==='preview')):?><h2>Nový náhled přiřazení</h2><p>Všechny tři zdroje jsou archivované a ověřené. Otisk: <code><?=krH($source['fingerprint'])?></code></p><form method="post" action="kis_review_admin.php?run_id=<?=$runId?>"><?=csrf_field()?><input type="hidden" name="action" value="preview"><input type="hidden" name="source_fingerprint" value="<?=krH($source['fingerprint'])?>"><label>Začátek nových členství <input type="date" class="form-control" name="valid_from" required></label>
<?php foreach($source['rows'] as $row):?><fieldset class="card p-3 my-3"><legend class="h5"><?=krH($row['prijmeni'].' '.$row['jmeno'].' · '.$row['kis_external_id'])?></legend><p>Zdrojové soupisky: <?=krH($row['kis_soupisky'])?></p><label>Cílová osoba pro <?=krH($row['kis_external_id'])?><select class="form-select" name="choices[<?=(int)$row['id']?>][target]" required><option value="">Vyberte podle stabilního ID</option><option value="new">Založit novou osobu</option><?php foreach($people as $p):?><option value="<?=(int)$p['id']?>"><?=krH('#'.$p['id'].' '.$p['prijmeni'].' '.$p['jmeno'].' · '.($p['kis_external_id']?:'bez KIS ID'))?></option><?php endforeach;?></select></label>
<p class="mt-2 mb-1">Výslovně přidat do soupisek (nezaškrtnuté se nemění):</p><?php foreach($teams as $team):?><label class="d-block"><input type="checkbox" name="choices[<?=(int)$row['id']?>][teams][]" value="<?=(int)$team['id']?>"> <?=krH($team['name'])?></label><?php endforeach;?></fieldset><?php endforeach;?>
<input type="hidden" name="complete_form" value="1"><button class="btn btn-primary">Připravit neměnný náhled</button></form><?php endif;?></main></body></html>
