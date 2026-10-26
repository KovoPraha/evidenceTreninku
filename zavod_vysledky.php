<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/race_results.php';
if(!isset($_SESSION['trener_id'])||!canAccess('sprava_zavodu')){http_response_code(403);exit('Správa výsledků je dostupná ve sportovní administrativě.');}
header('Cache-Control: no-store, private');
$raceId=(int)($_GET['id']??0);$actor=(int)$_SESSION['trener_id'];
try{raceResultsAssertAccess($pdo,$raceId,$actor,true);}catch(DomainException){http_response_code(404);exit('Závod není dostupný.');}
function rrH(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$state=raceResultsState($pdo,$raceId);$error='';$message='';$preview=null;
$latest=raceResultsRevision($pdo,$raceId,(int)$state['latest_revision_id']);
$editRows=$latest['rows']??[];
if(!$editRows)$editRows=raceResultsLegacyPreview($pdo,$raceId);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!csrf_verify($_POST['csrf_token']??''))throw new DomainException('Neplatný bezpečnostní token.');
        $action=(string)($_POST['action']??'');
        if($action==='preview'){
            if(($_POST['complete_form']??'')!=='1')throw new DomainException('Formulář nebyl přenesen celý. Pro více než 80 řádků použijte CSV.');
            if((int)($_POST['version']??-1)!==(int)$state['version'])throw new DomainException('Výsledky mezitím změnil jiný uživatel. Obnovte formulář.');
            if(count($editRows)>80&&($_POST['mode']??'manual')!=='csv')throw new DomainException('Více než 80 výsledků upravujte přes CSV, které obsahuje celou sadu.');
            $rows=($_POST['mode']??'manual')==='csv'?raceResultsParseCsv((string)($_POST['csv']??'')):(array)($_POST['rows']??[]);
            $rows=array_values(array_filter($rows,static fn($r)=>!is_array($r)||trim((string)($r['key']??'')).trim((string)($r['name']??''))!==''));
            $editRows=$rows;$preview=raceResultsNormalize($pdo,$raceId,$rows);
        }elseif($action==='restore'){
            $revision=raceResultsRevision($pdo,$raceId,(int)($_POST['revision_id']??0));
            if(!$revision)throw new DomainException('Revize neexistuje.');
            $editRows=$revision['rows'];$preview=raceResultsNormalize($pdo,$raceId,$editRows);$message='Historická revize je načtená do náhledu. Zveřejněný výsledek se nezměnil.';
        }elseif(in_array($action,['draft','published'],true)){
            if(($_POST['confirm']??'')!=='1')throw new DomainException('Potvrďte kontrolu konkrétního náhledu.');
            $rows=$action==='published'?($latest['rows']??[]):json_decode((string)($_POST['payload']??''),true,32,JSON_THROW_ON_ERROR);
            if(!is_array($rows))throw new InvalidArgumentException('Chybí náhled.');
            raceResultsWrite($pdo,$raceId,$actor,true,(int)($_POST['version']??-1),$action,$rows,(string)($_POST['reason']??''),(string)($_POST['request_key']??''));
            header('Location: zavod_vysledky.php?id='.$raceId.'&saved='.$action,true,303);exit;
        }else throw new DomainException('Neznámá akce.');
    }catch(Throwable $e){$error=$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'Zápis se nezdařil; žádná částečná změna nebyla uložena.';if($e instanceof PDOException)error_log('race results: database operation failed');}
}
$q=$pdo->prepare('SELECT id,version,kind,actor_id,reason,created_at FROM race_result_revisions WHERE race_id=? ORDER BY version DESC LIMIT 100');$q->execute([$raceId]);$history=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$pdo->prepare('SELECT popis FROM zavody WHERE id=?');$q->execute([$raceId]);$raceName=$q->fetchColumn();
$published=raceResultsRevision($pdo,$raceId,(int)$state['published_revision_id']);
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['saved']))$message=($_GET['saved']==='published'?'Výsledky byly zveřejněny v interním detailu závodu.':'Koncept byl uložen. Po kontrole jej můžete zveřejnit.');
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Výsledky závodu</title><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"></head><body>
<?php include __DIR__.'/hlavicka.php'; ?>
<main class="container py-4"><a href="zavod_detail.php?id=<?=$raceId?>">Zpět na závod</a><h1>Výsledky: <?=rrH($raceName)?></h1>
<p>Externí závodník se nestává členem oddílu. Každý účastník má stálý klíč; stejné jméno není důvodem ke sloučení. Zveřejnění se týká interního detailu závodu.</p>
<?php if(!$latest):?><p class="alert alert-info">Předvyplněné osoby, časy a pořadí jsou jen čtecí náhled historických přihlášení. Před uložením potvrďte stav, disciplínu a jízdu. Původní podklady a měření zůstávají v detailu závodu beze změn.</p><?php endif;?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=rrH($error)?></div><?php endif;?><?php if($message):?><div class="alert alert-success" role="status"><?=rrH($message)?></div><?php endif;?>
<p>Verze evidence: <?=(int)$state['version']?> · Zveřejněná verze: <?=$published?(int)$published['version']:'žádná'?></p>
<?php if($preview!==null):?><section class="card p-3 mb-3"><h2>Náhled před uložením</h2><p><?=count($preview)?> výsledků. Koncept nahradí předchozí pracovní seznam; zveřejněná revize zůstane zachována.</p>
<?php $displayRows=$preview;include __DIR__.'/includes/race_results_table.php';?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="draft"><input type="hidden" name="version" value="<?=(int)$state['version']?>"><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>"><input type="hidden" name="payload" value="<?=rrH(raceResultsJson($preview))?>"><label>Důvod uložení <input class="form-control" name="reason" required minlength="5" maxlength="500"></label><label class="d-block"><input type="checkbox" name="confirm" value="1" required> Zkontroloval/a jsem celý náhled.</label><button class="btn btn-primary">Uložit koncept</button></form></section><?php endif;?>
<?php if($latest):?><section class="card p-3 mb-3"><h2>Uložená pracovní verze <?=(int)$latest['version']?></h2><?php $displayRows=$latest['rows'];include __DIR__.'/includes/race_results_table.php';?>
<?php if($latest['kind']==='draft'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="published"><input type="hidden" name="version" value="<?=(int)$state['version']?>"><input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>"><label>Důvod zveřejnění <input class="form-control" name="reason" required minlength="5" maxlength="500"></label><label class="d-block"><input type="checkbox" name="confirm" value="1" required> Potvrzuji zveřejnění této pracovní verze.</label><button class="btn btn-success">Zveřejnit výsledky</button></form><?php endif;?></section><?php endif;?>
<details open><summary class="h2">Upravit výsledky</summary><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="preview"><input type="hidden" name="version" value="<?=(int)$state['version']?>"><label>Způsob zadání <select class="form-select" name="mode"><option value="manual">Ručně v tabulce</option><option value="csv">CSV z textového pole</option></select></label>
<p>Klíč vlastní osoby: person:ID (osobu nejprve přihlaste k závodu). Externí: ext:libovolny-staly-kod. Čas: MM:SS.mmm nebo HH:MM:SS.mmm. Další jízda používá stejný klíč účastníka.</p>
<div class="table-responsive"><table class="table"><thead><tr><?php $labels=['key'=>'Klíč','person_id'=>'ID osoby','name'=>'Jméno','club'=>'Klub','discipline'=>'Disciplína','heat'=>'Jízda','status'=>'Stav','time'=>'Čas','rank'=>'Pořadí'];foreach($labels as $label):?><th><?=rrH($label)?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach(array_merge(array_slice($editRows,0,80),array_fill(0,3,[])) as $i=>$row):?><tr><?php foreach($labels as $field=>$label):?><td><?php if($field==='status'):?><select aria-label="<?=$i+1?>: <?=rrH($label)?>" name="rows[<?=$i?>][status]" class="form-select"><?php foreach(sportsRaceResultStatuses() as $status):?><option value="<?=$status?>" <?=($row['status']??'entered')===$status?'selected':''?>><?=rrH(['entered'=>'Přihlášen','finished'=>'Dokončil','dns'=>'DNS','dnf'=>'DNF','dsq'=>'DSQ'][$status])?></option><?php endforeach;?></select><?php else:?><input aria-label="<?=$i+1?>: <?=rrH($label)?>" name="rows[<?=$i?>][<?=$field?>]" value="<?=rrH($row[$field]??'')?>" class="form-control" style="min-width:100px" maxlength="200"><?php endif;?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div>
<label class="d-block">CSV (středník, hlavička je povinná)<textarea name="csv" class="form-control" rows="5" maxlength="1000000"><?=rrH(raceResultsCsv($editRows))?></textarea></label><input type="hidden" name="complete_form" value="1"><button class="btn btn-primary my-3">Zkontrolovat náhled</button></form></details>
<h2>Historie revizí</h2><p>Oprava ani návrat nemažou historii. Návrat načte vybranou revizi do nového náhledu.</p><table class="table"><thead><tr><th>Verze</th><th>Stav</th><th>Kdy / kdo</th><th>Důvod</th><th></th></tr></thead><tbody><?php foreach($history as $revision):?><tr><td><?=(int)$revision['version']?></td><td><?=rrH($revision['kind']==='draft'?'Koncept':'Zveřejněno')?></td><td><?=rrH($revision['created_at'])?> / #<?=(int)$revision['actor_id']?></td><td><?=rrH($revision['reason'])?></td><td><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="restore"><input type="hidden" name="revision_id" value="<?=(int)$revision['id']?>"><button class="btn btn-outline-secondary btn-sm">Načíst do náhledu</button></form></td></tr><?php endforeach;?></tbody></table></main></body></html>
