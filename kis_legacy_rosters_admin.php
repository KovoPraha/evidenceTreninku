<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/kis_legacy_rosters.php';
if(!isset($_SESSION['trener_id']) || !roleAtLeast('admin')){http_response_code(403);exit('Pouze pro administrátora.');}
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
function klrH(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$actorId=(int)$_SESSION['trener_id'];$error='';$plan=null;$success=(string)($_SESSION['kis_legacy_roster_success']??'');unset($_SESSION['kis_legacy_roster_success']);
$run=$pdo->query('SELECT * FROM kis_legacy_roster_runs ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if(($_GET['view']??'')==='protocol' && $run){
    $rows=$pdo->query('SELECT c.id,c.sportovec_id,p.jmeno,p.prijmeni,p.narozeni,c.legacy_label,c.source_keys_json,c.reason_code,c.status,c.team_id,c.member_id,c.decision_note,c.decided_at FROM kis_legacy_roster_claims c JOIN sportovci p ON p.id=c.sportovec_id ORDER BY c.id')->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kis-soupisky-2025-26-protokol.json"');
    echo kisPersonJson(['run'=>['batch_fingerprint'=>$run['batch_fingerprint'],'plan_fingerprint'=>$run['plan_fingerprint'],'summary'=>json_decode($run['summary_json'],true)],'claims'=>$rows]);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new RuntimeException('Formulář vypršel. Obnovte stránku.');
        $action=(string)($_POST['action']??'');
        if($action==='preview'){
            if($run)throw new RuntimeException('Tento archiv soupisek byl již zpracován.');
            $plan=kisLegacyRosterPreview($pdo);
            $_SESSION['kis_legacy_roster_preview']=['fingerprint'=>$plan['fingerprint'],'time'=>time()];
        }elseif($action==='apply'){
            if($run)throw new RuntimeException('Tento archiv soupisek byl již zpracován.');
            $saved=$_SESSION['kis_legacy_roster_preview']??[];
            if(($_POST['confirmation']??'')!=='IMPORTOVAT SOUPISKY 2025/26'
                ||(int)($saved['time']??0)<time()-1800
                ||!hash_equals((string)($saved['fingerprint']??''),(string)($_POST['fingerprint']??'')))
                throw new RuntimeException('Náhled vypršel nebo se změnil. Připravte nový.');
            ignore_user_abort(true);set_time_limit(900);
            $result=kisLegacyRosterApply($pdo,$actorId,(string)$saved['fingerprint']);
            unset($_SESSION['kis_legacy_roster_preview']);
            $_SESSION['kis_legacy_roster_success']='Historické soupisky zapsány: '.$result['matched'].' jednoznačných vazeb; '.$result['review'].' případů čeká na rozhodnutí.';
            header('Location: kis_legacy_rosters_admin.php');exit;
        }elseif($action==='decide'){
            if(!$run)throw new RuntimeException('Import soupisek zatím nebyl proveden.');
            kisLegacyRosterDecide($pdo,(int)($_POST['claim_id']??0),$actorId,(string)($_POST['decision']??''),(string)($_POST['note']??''));
            $_SESSION['kis_legacy_roster_success']='Rozhodnutí bylo uloženo s auditní vazbou.';
            header('Location: kis_legacy_rosters_admin.php?status=review');exit;
        }else throw new RuntimeException('Neznámá akce.');
    }catch(Throwable $e){error_log('kis_legacy_rosters_admin: '.get_class($e));$error=$e instanceof PDOException?'Databázová operace selhala; transakce byla vrácena zpět.':$e->getMessage();}
}
$counts=[];$rows=[];$status=(string)($_GET['status']??'review');$q=mb_substr(trim((string)($_GET['q']??'')),0,80);
if($run){
    $counts=$pdo->query('SELECT status,COUNT(*) AS n FROM kis_legacy_roster_claims GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $allowed=['review','matched','approved','skipped'];if(!in_array($status,$allowed,true))$status='review';
    $sql='SELECT c.id,c.sportovec_id,c.team_id,c.legacy_label,c.source_keys_json,c.reason_code,c.status,c.member_id,c.decision_note,p.jmeno,p.prijmeni,p.narozeni FROM kis_legacy_roster_claims c JOIN sportovci p ON p.id=c.sportovec_id WHERE c.status=?';$params=[$status];
    if($q!==''){$sql.=' AND (p.jmeno LIKE ? OR p.prijmeni LIKE ? OR c.legacy_label LIKE ?)';array_push($params,...array_fill(0,3,'%'.$q.'%'));}
    $sql.=' ORDER BY p.prijmeni,p.jmeno,c.id LIMIT 250';$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$reasons=['pouze_trener'=>'Ve starém KIS veden jako trenér, ne sportovec','rozpor_sloucenych_aliasu'=>'Sloučené zdrojové účty mají rozdílné soupisky',
    'nezname_narozeni'=>'Chybí datum narození pro ročníkovou soupisku','rocnik_mimo_rozsah'=>'Rok narození je mimo ročníky v názvu soupisky'];
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Starý KIS · historické soupisky</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light">
<?php include __DIR__.'/hlavicka.php';?>
<main class="container-fluid py-4" style="max-width:1500px"><div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4">Soupisky ze starého KIS · 2025/26</h1><p class="text-muted">Doslovné historické soupisky ze schváleného šifrovaného archivu. Soupisky 2026/27 zůstávají bez automatického přiřazení.</p></div><a href="kis_rosters_admin.php" class="btn btn-outline-secondary">Správa soupisek</a></div>
<?php if($error):?><div class="alert alert-danger"><?=klrH($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=klrH($success)?></div><?php endif;?>
<?php if(!$run):?>
<div class="alert alert-info">Původní export označuje všechny nalezené soupisky sezonou 2025/26. Nová sezona 2026/27 je oddělená. Hranice 1. 9. 2025–31. 8. 2026 vyjadřují pouze pojmenované období; zdroj neobsahuje přesná data přihlášení ani ukončení jednotlivců. Historická členství proto budou uložena jako ukončená.</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?=klrH(csrf_token())?>"><input type="hidden" name="action" value="preview"><button class="btn btn-primary">Načíst kontrolní náhled z archivu</button></form>
<?php if($plan):?><div class="card my-3"><div class="card-body"><h2 class="h5">Kontrolní náhled</h2><p><?=count($plan['teams'])?> doslovných soupisek · <?=$plan['people_with_rosters']?> importovaných osob · <?=$plan['counts']['matched']?> jednoznačných vazeb · <?=$plan['counts']['review']?> k rozhodnutí.</p><p class="small">Otisk plánu: <code><?=klrH($plan['fingerprint'])?></code></p><details><summary>Zobrazit sporné případy</summary><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Osoba</th><th>Narození</th><th>Původní soupiska</th><th>Důvod</th></tr></thead><tbody><?php foreach($plan['claims'] as $claim):if($claim['reason']==='')continue;?><tr><td><?=klrH($claim['name'])?> #<?=(int)$claim['sportovec_id']?></td><td><?=klrH($claim['birth']?:'Neuvedeno')?></td><td><?=klrH($claim['label'])?></td><td><?=klrH($reasons[$claim['reason']]??$claim['reason'])?></td></tr><?php endforeach;?></tbody></table></div></details><form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?=klrH(csrf_token())?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="fingerprint" value="<?=klrH($plan['fingerprint'])?>"><label for="confirmation" class="form-label">Potvrďte přesný historický import opsáním <strong>IMPORTOVAT SOUPISKY 2025/26</strong></label><input id="confirmation" name="confirmation" class="form-control mb-2" required autocomplete="off"><button class="btn btn-danger">Zapsat jednoznačné vazby a frontu případů</button></form></div></div><?php endif;?>
<?php else:$summary=json_decode($run['summary_json'],true);?><div class="alert alert-info"><strong><?=count($counts)?> stavy; <?=$summary['teams']?> historických soupisek; <?=$summary['claims']?> zdrojových vazeb.</strong> Členství v sezoně 2026/27 tím nevzniká. <a href="?view=protocol">Stáhnout úplný protokol JSON</a>.</div>
<div class="d-flex gap-2 flex-wrap mb-3"><?php foreach(['review'=>'K rozhodnutí','matched'=>'Jednoznačně přiřazeno','approved'=>'Ručně potvrzeno','skipped'=>'Ponecháno bez vazby'] as $key=>$title):?><a class="btn btn-sm <?=$status===$key?'btn-primary':'btn-outline-primary'?>" href="?status=<?=klrH($key)?>"><?=klrH($title)?> (<?=(int)($counts[$key]??0)?>)</a><?php endforeach;?></div>
<form method="get" class="d-flex gap-2 mb-3"><input type="hidden" name="status" value="<?=klrH($status)?>"><input name="q" class="form-control" value="<?=klrH($q)?>" placeholder="Jméno nebo název soupisky"><button class="btn btn-outline-primary">Hledat</button></form><p class="small text-muted">Zobrazeno nejvýše 250 záznamů; úplný výčet je v protokolu.</p>
<div class="table-responsive"><table class="table table-striped table-sm align-middle"><thead><tr><th>Sportovec</th><th>Původní soupiska</th><th>Důvod kontroly</th><th>Zdrojové aliasy</th><?php if($status==='review'):?><th>Rozhodnutí</th><?php endif;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=klrH($row['jmeno'].' '.$row['prijmeni'])?> <small>#<?=(int)$row['sportovec_id']?></small><br><small><?=klrH($row['narozeni']?:'Datum narození neuvedeno')?></small></td><td><?=klrH($row['legacy_label'])?><br><small>Historický tým #<?=(int)$row['team_id']?></small></td><td><?=klrH($reasons[$row['reason_code']]??$row['reason_code'])?><?php if($row['decision_note']):?><br><small><?=klrH($row['decision_note'])?></small><?php endif;?></td><td><code><?=klrH(implode(', ',json_decode($row['source_keys_json'],true,8,JSON_THROW_ON_ERROR)))?></code></td><?php if($status==='review'):?><td><form method="post" class="d-flex gap-1 flex-wrap"><input type="hidden" name="csrf_token" value="<?=klrH(csrf_token())?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="claim_id" value="<?=(int)$row['id']?>"><input name="note" class="form-control form-control-sm" placeholder="Důvod rozhodnutí" required maxlength="1000"><button name="decision" value="approve" class="btn btn-success btn-sm">Potvrdit vazbu</button><button name="decision" value="skip" class="btn btn-outline-secondary btn-sm">Bez vazby</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></main></body></html>
