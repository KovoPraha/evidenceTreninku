<?php
declare(strict_types=1);

require_once __DIR__.'/includes/localhost_acceptance_hub.php';
if(!localhostAcceptanceRequestIsAllowed($_SERVER,getenv('APP_HOST'))){http_response_code(404);header('Cache-Control: no-store');exit('Nenalezeno.');}
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/includes/training_roster_bridge.php';
if(!isset($_SESSION['trener_id'])){header('Location: login.php');exit;}
function a07h(mixed$value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

$errors=[];$comparison=null;$planId=(int)($_GET['plan_id']??0);$actorId=(int)$_SESSION['trener_id'];
try{
    if($planId<1){
        $sql="SELECT id FROM planovane_treninky WHERE nazev='LOCALHOST – trénink pro soupisky'";
        $params=[];if(!roleAtLeast('hlavni')){$sql.=' AND trener_id=?';$params[]=$actorId;}
        $sql.=" ORDER BY CASE stav WHEN 'planovany' THEN 0 ELSE 1 END,id DESC LIMIT 1";
        $s=$pdo->prepare($sql);$s->execute($params);$planId=(int)$s->fetchColumn();
    }
    if($planId<1)throw new TrainingRosterBridgeException('Chybí localhost plán A07. Obnovte demo data.');
    $comparison=trainingRosterBridgePlanAttendanceComparison($pdo,$planId);
    if(!roleAtLeast('hlavni')&&(int)$comparison['plan']['trener_id']!==$actorId)throw new TrainingRosterBridgeException('Cizí plán A07 nelze zobrazit.');
}catch(Throwable$exception){$errors[]=$exception->getMessage();}
$expectedIds=$comparison?array_fill_keys(array_map('intval',array_column($comparison['expected'],'id')),true):[];
$actualIds=$comparison?array_fill_keys(array_map('intval',array_column($comparison['actual'],'id')),true):[];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>A07 – trénink a docházka</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1150px"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h3 mb-1">A07 – soupiska, trénink a docházka</h1><p class="text-muted mb-0">Kontrola rozdílu mezi očekáváním ze soupisek a ručně potvrzenou docházkou v Evidenci.</p></div><a class="btn btn-outline-secondary" href="testovaci_scenare.php">Testovací scénáře</a></div>
<?php foreach($errors as$error):?><div class="alert alert-danger"><?=a07h($error)?></div><?php endforeach;?>
<?php if($comparison):$plan=$comparison['plan'];?><div class="card border-0 shadow-sm mb-3"><div class="card-body d-flex flex-wrap justify-content-between gap-3"><div><div class="small text-muted">Testovací plán</div><h2 class="h5 mb-1"><?=a07h($plan['nazev'])?></h2><div><?=a07h($plan['datum'])?> · <?=a07h(substr((string)$plan['cas_od'],0,5))?>–<?=a07h(substr((string)$plan['cas_do'],0,5))?></div></div><div class="text-end"><span class="badge <?=$plan['stav']==='evidovany'?'text-bg-success':'text-bg-warning'?> mb-2"><?=$plan['stav']==='evidovany'?'Zaevidováno':'Čeká na docházku'?></span><div><?php if($plan['stav']==='planovany'):?><a class="btn btn-primary" href="formular.php?plan_id=<?=(int)$plan['id']?>">Zadat skutečnou docházku</a><?php elseif((int)$plan['trenink_id']>0):?><a class="btn btn-outline-success" href="edit_trenink.php?id=<?=(int)$plan['trenink_id']?>">Otevřít evidenci</a><?php endif;?></div></div></div></div>
<div class="alert alert-info">Plánování vytvořilo pouze očekávání. Ve formuláři jsou očekávaní sportovci předvybraní, ale trenér je musí podle reality ponechat nebo odebrat. Teprve uložením vznikne skutečná docházka.</div>
<section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Cílové soupisky (historický snapshot)</div><div class="card-body d-flex flex-wrap gap-2"><?php foreach($comparison['teams']as$team):?><span class="badge text-bg-primary fs-6"><?=a07h($team['team_name_snapshot'])?> <span class="opacity-75"><?=a07h($team['team_code_snapshot'])?></span></span><?php endforeach;?><?php if($comparison['teams']===[]):?><span class="text-muted">Plán nemá přiřazenou soupisku.</span><?php endif;?></div></section>
<div class="row g-3"><div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Očekávaní ze soupisek (<?=count($comparison['expected'])?>)</div><ul class="list-group list-group-flush"><?php foreach($comparison['expected']as$person):$attended=isset($actualIds[(int)$person['id']]);?><li class="list-group-item d-flex justify-content-between"><span><?=a07h($person['label'])?></span><?php if($plan['stav']==='evidovany'):?><span class="badge <?=$attended?'text-bg-success':'text-bg-warning'?>"><?=$attended?'přítomen':'chyběl'?></span><?php else:?><span class="badge text-bg-light">čeká na evidenci</span><?php endif;?></li><?php endforeach;?><?php if($comparison['expected']===[]):?><li class="list-group-item text-muted">Snapshot neobsahuje žádného člena.</li><?php endif;?></ul></section></div>
<div class="col-lg-6"><section class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Skutečná docházka · <?=count($comparison['actual'])?></div><ul class="list-group list-group-flush"><?php foreach($comparison['actual']as$person):$expected=isset($expectedIds[(int)$person['id']]);?><li class="list-group-item d-flex justify-content-between"><span><?=a07h($person['label'])?></span><span class="badge <?=$expected?'text-bg-success':'text-bg-secondary'?>"><?=$expected?'očekávaný':'mimo soupisku'?></span></li><?php endforeach;?><?php if($comparison['actual']===[]):?><li class="list-group-item text-muted"><?=$plan['stav']==='planovany'?'Docházka ještě nebyla zadána.':'Nebyla zaznamenána žádná účast.'?></li><?php endif;?></ul></section></div></div>
<?php if($plan['stav']==='evidovany'):?><div class="alert alert-success mt-3 mb-0"><strong>A07 lze zkontrolovat:</strong> očekávaní a přítomní <?=count($comparison['attended_expected'])?> · chyběli <?=count($comparison['missing'])?> · mimo původní snapshot <?=count($comparison['unexpected'])?>. Skutečná účast je nyní dostupná také rodiči a sportovci.</div><?php endif;?>
<?php endif;?></main></body></html>
