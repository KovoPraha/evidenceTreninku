<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/person_lifecycle.php';
require_once __DIR__.'/includes/person_audit_timeline.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/ui_shell.php';
header('Cache-Control: no-store, private');
try{personLifecycleAssertActor($pdo);}catch(DomainException){http_response_code(403);exit('Přístup odepřen.');}
$id=(int)($_POST['person_id']??$_GET['id']??0);$error='';$model=null;
$intent=staffListText($_POST+$_GET,'intent');if(!isset(personLifecycleIntents()[$intent]))$intent='intake';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_verify(staffListText($_POST,'csrf_token'))){http_response_code(403);exit('Formulář vypršel.');}
    try{personLifecycleSave($pdo,$id,$_POST);header('Location: person_lifecycle_admin.php?'.http_build_query(['id'=>$id,'intent'=>$intent,'saved'=>1]),true,303);exit;}
    catch(DomainException|InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
}
if($id>0){try{$model=personLifecycleRead($pdo,$id,$_GET);}catch(InvalidArgumentException){http_response_code(404);exit('Osoba nebyla nalezena.');}}
$search=mb_substr(staffListText($_GET,'q'),0,100);$people=$search!==''?personAuditSearch($pdo,$search):[];
$owners=operationalCaseOwners($pdo,'registrar');
function plH(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vyřízení osoby</title><?php appUiAssets();?></head><body><?php require __DIR__.'/hlavicka.php';?><main class="container py-4">
<h1>Vyřízení osoby</h1><p>Přijetí, přerušení, odchod nebo návrat vyřiďte po jednotlivých agendách. Pracovní úkol nenahrazuje rozhodnutí o členství.</p>
<form method="get" class="mb-3"><label>Najít osobu <input name="q" maxlength="100" value="<?=plH($search)?>" required></label> <button class="btn btn-outline-primary">Hledat</button></form>
<?php foreach($people as $p):?><p><a href="?id=<?=(int)$p['id']?>"><?=plH($p['prijmeni'].' '.$p['jmeno'])?></a></p><?php endforeach;?>
<?php if($model):$person=$model['person'];$case=$model['case'];$form=$error!==''?$_POST:($case??[]);?>
<h2><?=plH($person['prijmeni'].' '.$person['jmeno'])?></h2><p><a href="sprava_sportovcu.php?edit_id=<?=$id?>">Upravit osobu a vazby</a> · <a href="person_audit_admin.php?sportovec_id=<?=$id?>">Úplná auditní osa</a></p>
<?php if($error!==''):?><p class="alert alert-danger"><?=plH($error)?></p><?php elseif(isset($_GET['saved'])):?><p class="alert alert-success">Pracovní krok a odpovědnost byly uloženy. Členství a navazující agendy se tím nezměnily.</p><?php endif;?>
<?php if($model['missing']):?><p class="alert alert-warning">Nelze ověřit: <?=plH(implode(', ',$model['missing']))?>. Nedostupný zdroj neznamená vyřízenou agendu.</p><?php endif;?>
<form method="get" class="mb-3"><input type="hidden" name="id" value="<?=$id?>"><label>Doporučené kroky pro <select name="intent"><?php foreach(personLifecycleIntents() as $key=>$label):?><option value="<?=$key?>" <?=$intent===$key?'selected':''?>><?=$label?></option><?php endforeach;?></select></label> <button class="btn btn-outline-primary">Zobrazit postup</button></form>
<p class="alert alert-info"><?=in_array($intent,['departure','pause'],true)?'Ověřte datum a podklad odchodu či přerušení. Samostatně posuďte pokračující soupisky, plánované účasti, předpisy a přístupy. Závazky ani historii nemažte.':'Ověřte totožnost a podklady, zaznamenejte výslovné rozhodnutí o členství a dohodněte zařazení. Při návratu použijte původní osobu; nevytvářejte novou identitu.'?></p>
<div class="list-group mb-4"><?php foreach($model['steps'] as $step):?><div class="list-group-item"><strong><?=plH($step['title'])?></strong> · <a href="<?=plH($step['href'])?>">Otevřít agendu</a><p class="mb-0"><?=plH($step['detail'])?></p></div><?php endforeach;?></div>
<h3 class="h5">Navazující zařazení a podklady</h3>
<p>Platnost zařazení neznamená potvrzenou budoucí účast. Konkrétní lekce a akce ověří vedoucí sportu; účast v placeném programu řešte samostatně s jeho obsluhou.</p>
<ul><?php foreach($model['rosters'] as $r):?><li><?=plH($r['name'].' · '.$r['valid_from'].' až '.($r['valid_to']??'bez určeného konce'))?> · <a href="sprava_sportovcu.php?edit_id=<?=$id?>#rosters-title">Posoudit zařazení</a></li><?php endforeach;?></ul>
<details class="mb-3"><summary>Plánované lekce do <?=plH($model['until'])?> (<?=count($model['trainings'])?>)</summary><ul><?php foreach($model['trainings'] as $t):?><li><?=plH($t['datum'].' · '.$t['nazev'].' · '.trainingRsvpLabel($t['response']??''))?></li><?php endforeach;?></ul><p>Jde o přípravu podle soupisky a rezervací; další termíny za uvedeným obdobím ověřte s vedoucím sportu.</p></details>
<details class="mb-3"><summary>Budoucí klubové akce (<?=count($model['activities'])?>)</summary><ul><?php foreach($model['activities'] as $a):?><li><?=plH($a['starts_at'].' · '.$a['name'].' · '.(['planned'=>'Plánovaná účast','confirmed'=>'Potvrzená účast','waitlisted'=>'Čekací listina'][$a['status']]??$a['status']))?></li><?php endforeach;?></ul><p>Změnu dohodněte s vedoucím konkrétní akce. Přehled neobsahuje nákupy ani rezervace bez vazby na klubový kalendář.</p></details>
<p>Členské předpisy čekající na úhradu: <?=count($model['charges'])?>. Jejich vypořádání potvrzuje hospodář; změna členství je nestornuje. Seznam nepotvrzuje bezdlužnost.</p>
<ul><?php foreach($model['paper'] as $r):?><li><a href="paper_registrations_admin.php?id=<?=(int)$r['id']?>"><?=plH(($r['kind']==='consent'?'Souhlas':'Přihláška').' · '.$r['version_label'])?></a> · <?=plH(['recorded'=>'K ověření','accepted'=>'Ověřeno','rejected'=>'Odmítnuto','revoked'=>'Odvoláno','superseded'=>'Nahrazeno'][$r['state']]??'Stav neznámý')?></li><?php endforeach;?></ul>
<section id="membership-history" class="my-4"><h3 class="h5">Historie zaznamenaných rozhodnutí o členství</h3><p>Aktuálně: <?=sportovecStatusBadge($person)?>. Čas zápisu není datem účinnosti. Z těchto údajů nelze sestavit potvrzený seznam členů k minulému datu.</p>
<table class="table"><tr><th>Zapsáno</th><th>Rozhodnutí</th><th>Důvod / pracovník</th></tr><?php foreach($model['history']['rows'] as $h):?><tr><td><?=plH($h['created_at'])?></td><td><?=plH($h['title'])?> · <?=isset($h['membership']['stav_clenstvi'])?sportovecStatusBadge($h['membership']):'Stav není doložen'?></td><td><?=plH($h['membership']['stav_duvod']??'')?> · <?=plH($h['jmeno'])?></td></tr><?php endforeach;?></table>
<p>Záznamů: <?=$model['history']['total']?>. Strana <?=$model['history']['page']?> z <?=$model['history']['pages']?>.</p><?php foreach(['Předchozí'=>-1,'Další'=>1] as $label=>$delta):$p=$model['history']['page']+$delta;if($p<1||$p>$model['history']['pages'])continue;?><a class="me-3" href="?<?=plH(http_build_query(['id'=>$id,'intent'=>$intent,'history_page'=>$p]))?>#membership-history"><?=$label?></a><?php endforeach;?></section>
<section id="followup" class="card card-body mb-4"><h3 class="h5">Odpovědnost a další krok</h3><p>Uzavření tohoto úkolu potvrzuje administrativní vyřízení. Neodpojí rodinu, neukončí soupisku a nezmění členství ani úhrady.</p>
<?php if($error!==''&&(int)($form['revision']??0)!==(int)($case['revision']??0)):?><p class="alert alert-warning">Úkol mezitím změnil jiný pracovník. Váš návrh zůstal zachován. <a href="?id=<?=$id?>#followup">Načíst aktuální úkol</a>.</p><?php endif;?>
<form method="post"><?=csrf_field()?><input type="hidden" name="person_id" value="<?=$id?>"><input type="hidden" name="intent" value="<?=$intent?>"><input type="hidden" name="revision" value="<?=(int)($form['revision']??0)?>">
<label class="d-block">Odpovídá <select class="form-select" name="owner_trainer_id" required><option value="">Vyberte registráře</option><?php foreach($owners as $o):?><option value="<?=(int)$o['id']?>" <?=(int)($form['owner_trainer_id']??0)===(int)$o['id']?'selected':''?>><?=plH($o['jmeno'])?></option><?php endforeach;?></select></label>
<label class="d-block">Další krok<input class="form-control" name="next_step" required maxlength="1000" value="<?=plH($form['next_step']??personLifecycleIntents()[$intent])?>"></label>
<label>Kontrola dne <input type="date" name="review_on" value="<?=plH($form['review_on']??date('Y-m-d'))?>" required></label>
<label>Stav úkolu <select name="status"><option value="open">Otevřený</option><option value="closed" <?=($form['status']??'')==='closed'?'selected':''?>>Vyřízený</option></select></label>
<label class="d-block">Důvod / podklad osobního vyřízení<input class="form-control" name="reason" required maxlength="1000" value="<?=plH($error!==''?($form['reason']??''):'')?>"></label>
<label class="d-block my-3"><input type="checkbox" name="confirmed" value="1" required> Návazné agendy posuzuji samostatně; tento zápis jejich stav nemění.</label><button class="btn btn-primary">Uložit pracovní krok</button></form>
<details class="mt-3"><summary>Historie vyřízení</summary><ul><?php foreach($model['events'] as $e):$after=json_decode($e['after_json'],true);?><li><?=plH($e['created_at'].' · '.$e['jmeno'].' · '.$e['reason'])?><p><?=plH(($after['next_step']??'').' · '.($after['review_on']??''))?></p></li><?php endforeach;?></ul></details></section>
<?php endif;?></main></body></html>
