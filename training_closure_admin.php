<?php
declare(strict_types=1);
require_once __DIR__.'/includes/session_security.php';app_session_start();
if(!isset($_SESSION['trener_id'])){header('Location: login.php');exit;}
require_once __DIR__.'/db.php';require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/training_closure.php';require_once __DIR__.'/includes/training_closure_form.php';require_once __DIR__.'/includes/ui_shell.php';
$actor=(int)$_SESSION['trener_id'];
try{trainingClosureAssertActor($pdo,$actor);}catch(RuntimeException $e){http_response_code(403);exit($e->getMessage());}
$error='';$success=(string)($_SESSION['training_closure_success']??'');unset($_SESSION['training_closure_success']);$trainingId=(int)($_POST['training_id']??$_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Neplatný bezpečnostní token.');}
    try{
        if(($_POST['action']??'')==='personal_excuse'){
            $result=trainingPersonalExcuseRecord($pdo,(int)($_POST['plan_id']??0),(int)($_POST['person_id']??0),str_replace('T',' ',(string)($_POST['received_at']??'')).':00',(string)($_POST['source']??''),(string)($_POST['reason']??''),$actor);
            $success=$result['eligible']?'Osobní omluva byla zapsána. Nárok vznikne až po uzavření skutečné lekce.':'Osobní omluva byla zapsána bez nároku: '.clubMakeupExcuseFailureMessage((string)$result['denial']);
        }else{
            $participants=is_array($_POST['participant_ids']??null)?$_POST['participant_ids']:[];$guest=[];
            foreach($participants as $id)$guest[(int)$id]=trim((string)($_POST['guest_reason']??''));
            $result=trainingClosureClose($pdo,$trainingId,(int)($_POST['version']??0),[
                'actual_started_at'=>str_replace('T',' ',(string)($_POST['actual_started_at']??'')).':00',
                'actual_ended_at'=>str_replace('T',' ',(string)($_POST['actual_ended_at']??'')).':00',
                'participant_ids'=>$participants,'coach_ids'=>is_array($_POST['coach_ids']??null)?$_POST['coach_ids']:[],
                'guest_reasons'=>$guest,'reason'=>(string)($_POST['reason']??''),'record_source'=>(string)($_POST['record_source']??'coach'),
                'confirmed'=>($_POST['confirm_action']??'')==='1','void'=>($_POST['action']??'')==='void',
            ],$actor);
            $success='Docházka byla uložena jako verze '.$result['version'].'.'.($result['review_required']?' Oprava zasahuje uzavřený podklad odměny; předejte hospodáři kontrolu rozdílu.':'');
        }
    }catch(Throwable $e){$expected=$e instanceof TrainingClosureException||$e instanceof ClubMakeupException||$e instanceof InvalidArgumentException||get_class($e)===RuntimeException::class;http_response_code($expected?422:500);$error=$expected?$e->getMessage():'Zápis se nepodařil.';}
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>$error==='','message'=>$error?:$success,'result'=>$error===''?($result??null):null],JSON_UNESCAPED_UNICODE);exit;}
    if($error===''){
        $_SESSION['training_closure_success']=$success;
        header('Location: training_closure_admin.php?'.http_build_query(['id'=>$trainingId,'plan_id'=>(int)($_POST['plan_id']??0)]),true,303);
        exit;
    }
}
$current=null;$training=null;$plan=null;$selectedPeople=[];$selectedCoaches=[];$history=[];
if($trainingId>0){
    try{$training=trainingClosureAssertTraining($pdo,$trainingId,$actor);}catch(RuntimeException $e){http_response_code(403);exit($e->getMessage());}
    $current=trainingClosureCurrent($pdo,$trainingId);
    $s=$pdo->prepare('SELECT * FROM planovane_treninky WHERE trenink_id=?');$s->execute([$trainingId]);$plan=$s->fetch(PDO::FETCH_ASSOC)?:null;
    foreach(['trenink_sportovec'=>'sportovec_id','trenink_trener'=>'trener_id'] as $table=>$column){$s=$pdo->prepare("SELECT $column FROM $table WHERE trenink_id=?");$s->execute([$trainingId]);if($column==='sportovec_id')$selectedPeople=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));else $selectedCoaches=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
    $s=$pdo->prepare('SELECT version,status,closed_at,closed_by,reason FROM training_closure_revisions WHERE training_id=? ORDER BY version DESC');$s->execute([$trainingId]);$history=$s->fetchAll(PDO::FETCH_ASSOC);
}
$people=$pdo->query('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE '.coachScopePersonPredicate($pdo).' ORDER BY s.prijmeni,s.jmeno')->fetchAll(PDO::FETCH_ASSOC);
$coaches=$pdo->query('SELECT id,jmeno FROM treneri WHERE aktivni=1 ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);
$s=$pdo->prepare("SELECT t.id,t.datum,t.napln,c.version,c.status FROM treninky t LEFT JOIN training_closures c ON c.training_id=t.id WHERE t.datum<=? AND ".coachScopeTrainingPredicate($pdo)." ORDER BY CASE WHEN c.current_revision_id IS NULL THEN 0 ELSE 1 END,t.datum DESC,t.id DESC LIMIT 100");$s->execute([date('Y-m-d')]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
$failedClosure=$error!=='' && ($_POST['action']??'')!=='personal_excuse' ? $_POST : null;
$form=trainingClosureFormState($failedClosure,$current,$selectedPeople,$selectedCoaches);
$selectedPeople=$form['participant_ids'];$selectedCoaches=$form['coach_ids'];
function tcH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Docházka a uzavření</title><?php appUiAssets(); ?></head><body>
<?php require __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><h1>Docházka a uzavření</h1><p>Potvrďte skutečně proběhlou lekci, účastníky a trenéry. Odpověď v rozvrhu ani předpokládaná soupiska nepotvrzuje skutečnou účast.</p>
<p><a href="planovac.php">Rozvrh a nezapsané lekce</a> · <a href="sprava_treninku.php">Evidence tréninků</a></p>
<?php if($error):?><div class="alert alert-danger"><?=tcH($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=tcH($success)?></div><?php endif;?>
<?php if($training):?><section class="card mb-4"><div class="card-body"><h2>Trénink #<?=$trainingId?> · <?=tcH($training['datum'])?></h2><p><?=tcH($training['napln'])?></p>
<p>Stav: <strong><?=!$current?'Rozepsaná docházka':($current['status']==='void'?'Zneplatněná docházka':'Uzavřená docházka, verze '.(int)$current['version'])?></strong>. Zápis z papíru uchová okamžik přepisu i skutečné konání.</p>
<?php if($plan):?><p>Plán #<?=(int)$plan['id']?> · <?=tcH($plan['nazev'])?> · <?=tcH($plan['cas_od'])?>–<?=tcH($plan['cas_do'])?></p><?php endif;?>
<?php if($selectedPeople):?><p>Ověření podkladů k datu lekce: <?php foreach($selectedPeople as $person):?><a class="me-2" href="medical_clearance_status.php?person_id=<?=$person?>&amp;on=<?=tcH($training['datum'])?>">Sportovec #<?=$person?></a><?php endforeach;?></p><?php endif;?>
<?php if($failedClosure!==null && $form['version']!==(int)($current['version']??0)):?><div class="alert alert-warning">Podklad mezitím změnil jiný pracovník. <a href="?id=<?=$trainingId?>">Načíst aktuální verzi</a> a zkontrolovat změny; původní návrh se nepřepíše automaticky.</div><?php endif;?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=tcH(csrf_token())?>"><input type="hidden" name="training_id" value="<?=$trainingId?>"><input type="hidden" name="version" value="<?=$form['version']?>">
<div class="row g-3"><label class="col-md-6">Skutečný začátek<input class="form-control" type="datetime-local" name="actual_started_at" required value="<?=tcH($form['actual_started_at'])?>"></label><label class="col-md-6">Skutečný konec<input class="form-control" type="datetime-local" name="actual_ended_at" required value="<?=tcH($form['actual_ended_at'])?>"></label>
<label class="col-md-6">Skutečně přítomní sportovci<select class="form-select" name="participant_ids[]" multiple size="9"><?php foreach($people as $p):?><option value="<?=(int)$p['id']?>" <?=in_array((int)$p['id'],$selectedPeople,true)?'selected':''?>><?=tcH($p['prijmeni'].' '.$p['jmeno'])?> (#<?=(int)$p['id']?>)</option><?php endforeach;?></select></label>
<label class="col-md-6">Skutečně přítomní trenéři<select class="form-select" name="coach_ids[]" multiple required size="9"><?php foreach($coaches as $c):?><option value="<?=(int)$c['id']?>" <?=in_array((int)$c['id'],$selectedCoaches,true)?'selected':''?>><?=tcH($c['jmeno'])?></option><?php endforeach;?></select></label>
<label class="col-md-6">Podklad<select name="record_source" class="form-select"><option value="coach" <?=$form['record_source']==='coach'?'selected':''?>>Zápis trenéra</option><option value="paper" <?=$form['record_source']==='paper'?'selected':''?>>Přepis papírové docházky</option></select></label><label class="col-md-6">Důvod účasti hostů mimo očekávanou soupisku<input class="form-control" name="guest_reason" minlength="10" maxlength="1000" value="<?=tcH($form['guest_reason'])?>"></label>
<label class="col-12">Důvod zápisu nebo opravy<textarea class="form-control" required minlength="10" maxlength="1000" name="reason"><?=tcH($form['reason'])?></textarea></label></div>
<label class="d-block my-3"><input type="checkbox" name="confirm_action" value="1" required <?=$form['confirm_action']?'checked':''?>> Potvrzuji skutečný konec, účastníky a trenéry. Lekce již skončila.</label>
<button class="btn btn-primary" name="action" value="close"><?=$current?'Uložit opravenou verzi':'Uzavřít docházku'?></button> <?php if($current):?><button class="btn btn-outline-danger" name="action" value="void">Zneplatnit lekci s uvedeným důvodem</button><?php endif;?></form>
<?php if($history):?><h3 class="mt-4">Historie uzavření</h3><ul><?php foreach($history as $h):?><li>Verze <?=(int)$h['version']?> · <?=tcH($h['closed_at'])?> · <?=tcH($h['status'])?> · <?=tcH($h['reason'])?></li><?php endforeach;?></ul><?php endif;?></div></section><?php endif;?>
<section class="card mb-4"><div class="card-body"><h2>Osobně přijatá omluva</h2><p>Zapište skutečný čas, kdy rodič či sportovec omluvu předal. Lhůta náhrady se posuzuje podle pravidel konkrétního programu; čas přepisu se nepřepisuje.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?=tcH(csrf_token())?>"><input type="hidden" name="action" value="personal_excuse"><input type="hidden" name="training_id" value="<?=$trainingId?>">
<div class="row g-3"><label class="col-md-3">Číslo lekce z rozvrhu<input class="form-control" type="number" min="1" required name="plan_id" value="<?=tcH($plan['id']??($_GET['plan_id']??''))?>"></label><label class="col-md-5">Sportovec<select class="form-select" required name="person_id"><option value="">Vyberte sportovce</option><?php foreach($people as $p):?><option value="<?=(int)$p['id']?>"><?=tcH($p['prijmeni'].' '.$p['jmeno'])?></option><?php endforeach;?></select></label><label class="col-md-4">Skutečné přijetí omluvy<input class="form-control" type="datetime-local" required name="received_at"></label><label class="col-md-3">Způsob přijetí<select class="form-select" name="source"><option value="in_person">Osobně</option><option value="phone">Telefonicky</option><option value="paper">Na papíře</option></select></label><label class="col-md-9">Důvod a podklad přepisu<input class="form-control" required minlength="10" maxlength="1000" name="reason"></label></div><button class="btn btn-outline-primary mt-3">Zapsat osobní omluvu</button></form></div></section>
<h2>Zapsané lekce k uzavření a opravě</h2><p>Neuzavřené lekce jsou první. Chybějící docházka neznamená absenci všech sportovců.</p><table class="table"><thead><tr><th>Datum</th><th>Trénink</th><th>Stav</th><th></th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=tcH($r['datum'])?></td><td><?=tcH($r['napln'])?></td><td><?=!$r['version']?'K uzavření':($r['status']==='void'?'Zneplatněno':'Uzavřeno, verze '.(int)$r['version'])?></td><td><a href="?id=<?=(int)$r['id']?>">Docházka #<?=(int)$r['id']?></a></td></tr><?php endforeach;?></tbody></table></main></body></html>
