<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/kis_current_rosters.php';
if(!isset($_SESSION['trener_id']) || !roleAtLeast('admin')){http_response_code(403);exit('Pouze pro administrátora.');}
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
function kcrH(mixed $value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$actorId=(int)$_SESSION['trener_id'];$error='';$preview=null;
$success=(string)($_SESSION['kis_current_roster_success']??'');unset($_SESSION['kis_current_roster_success']);
$run=$pdo->query('SELECT * FROM kis_current_roster_runs ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$reasons=[
    'historical_claim_review'=>'Historická vazba zatím není potvrzena',
    'historical_member_missing'=>'U potvrzené historické vazby chybí členství',
    'no_unique_target'=>'Ve stávajících soupiskách není jednoznačný protějšek',
    'target_unavailable'=>'Cílová soupiska není aktivní',
    'target_conflict'=>'Kód cílové soupisky patří jinému názvu',
    'person_archived'=>'Sportovec je archivován',
    'no_active_offer'=>'Cílová skupina nemá aktivní nabídku',
    'offer_age_conflict'=>'Nabídky skupiny mají různé ročníkové rozsahy',
    'birth_missing'=>'Chybí datum narození',
    'birth_out_of_range'=>'Ročník je mimo rozsah aktuální skupiny',
    'existing_inactive_member'=>'V aktuální soupisce již existuje ukončené členství',
];
if(($_GET['view']??'')==='protocol' && $run){
    $rows=$pdo->query('SELECT c.id,c.legacy_claim_id,c.sportovec_id,p.jmeno,p.prijmeni,p.narozeni,l.legacy_label,l.status AS legacy_status,c.suggested_team_id,c.target_team_id,c.member_id,c.reason_code,c.status,c.decision_note,c.decided_at FROM kis_current_roster_claims c JOIN kis_legacy_roster_claims l ON l.id=c.legacy_claim_id JOIN sportovci p ON p.id=c.sportovec_id ORDER BY c.id')->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="kis-soupisky-2026-27-protokol.json"');
    echo kisPersonJson(['run'=>['plan_fingerprint'=>$run['plan_fingerprint'],'summary'=>json_decode($run['summary_json'],true)],'claims'=>$rows]);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try {
        if(!csrf_verify((string)($_POST['csrf_token']??'')))throw new RuntimeException('Formulář vypršel. Obnovte stránku.');
        $action=(string)($_POST['action']??'');
        if($action==='preview'){
            if($run)throw new RuntimeException('Aktuální soupisky už byly podle tohoto archivu zpracovány.');
            $preview=kisCurrentRosterPreview($pdo);
            $_SESSION['kis_current_roster_preview']=['fingerprint'=>$preview['fingerprint'],'time'=>time()];
        }elseif($action==='apply'){
            if($run)throw new RuntimeException('Aktuální soupisky už byly podle tohoto archivu zpracovány.');
            $saved=$_SESSION['kis_current_roster_preview']??[];
            if(($_POST['confirmation']??'')!=='PREVEST DO 2026/27'
                ||(int)($saved['time']??0)<time()-1800
                ||!hash_equals((string)($saved['fingerprint']??''),(string)($_POST['fingerprint']??'')))
                throw new RuntimeException('Náhled vypršel nebo se změnil. Připravte nový.');
            ignore_user_abort(true);set_time_limit(900);
            $result=kisCurrentRosterApply($pdo,$actorId,(string)$saved['fingerprint']);
            unset($_SESSION['kis_current_roster_preview']);
            $_SESSION['kis_current_roster_success']='Do aktuálních soupisek zapsáno '.$result['matched'].' vazeb; '.$result['review'].' případů čeká na rozhodnutí.';
            header('Location: kis_current_rosters_admin.php');exit;
        }elseif($action==='decide'){
            if(!$run)throw new RuntimeException('Převod ještě nebyl proveden.');
            $target=trim((string)($_POST['target_team_id']??''));
            kisCurrentRosterDecide($pdo,(int)($_POST['claim_id']??0),$actorId,(string)($_POST['decision']??''),$target===''?null:(int)$target,(string)($_POST['note']??''));
            $_SESSION['kis_current_roster_success']='Rozhodnutí bylo uloženo.';
            header('Location: kis_current_rosters_admin.php?status=review');exit;
        }else throw new RuntimeException('Neznámá akce.');
    } catch(Throwable $e) {error_log('kis_current_rosters_admin: '.get_class($e));$error=$e instanceof PDOException?'Databázová operace selhala; transakce byla vrácena zpět.':$e->getMessage();}
}
$status=(string)($_GET['status']??'review');if(!in_array($status,['review','matched','approved','skipped','already_present'],true))$status='review';
$q=mb_substr(trim((string)($_GET['q']??'')),0,80);$counts=[];$rows=[];$teams=[];
$season=$pdo->prepare('SELECT id FROM club_seasons WHERE code=?');$season->execute([KIS_CURRENT_ROSTER_SEASON_CODE]);$seasonId=(int)$season->fetchColumn();
if($seasonId>0){$s=$pdo->prepare("SELECT id,name FROM club_teams WHERE season_id=? AND status='active' ORDER BY name,id");$s->execute([$seasonId]);$teams=$s->fetchAll(PDO::FETCH_ASSOC);}
if($run){
    $counts=$pdo->query('SELECT status,COUNT(*) FROM kis_current_roster_claims GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $sql='SELECT c.id,c.legacy_claim_id,c.sportovec_id,c.suggested_team_id,c.target_team_id,c.reason_code,c.status,c.decision_note,l.legacy_label,l.status AS legacy_status,p.jmeno,p.prijmeni,p.narozeni,t.name AS suggested_team_name FROM kis_current_roster_claims c JOIN kis_legacy_roster_claims l ON l.id=c.legacy_claim_id JOIN sportovci p ON p.id=c.sportovec_id LEFT JOIN club_teams t ON t.id=c.suggested_team_id WHERE c.status=?';$params=[$status];
    if($q!==''){$sql.=' AND (p.jmeno LIKE ? OR p.prijmeni LIKE ? OR l.legacy_label LIKE ?)';array_push($params,...array_fill(0,3,'%'.$q.'%'));}
    $sql.=' ORDER BY p.prijmeni,p.jmeno,c.id LIMIT 250';$s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KIS · Převod aktuálních soupisek</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light">
<?php include __DIR__.'/hlavicka.php';?>
<main class="container-fluid py-4" style="max-width:1500px"><div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h4">Převod do aktuálních soupisek · 2026/27</h1><p class="text-muted">Jednoznačné skupiny a platné ročníky se převedou jako aktivní ode dne převodu. Ostatní vazby čekají na rozhodnutí správce.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="kis_legacy_rosters_admin.php">Historické vazby</a><a class="btn btn-outline-secondary btn-sm" href="kis_rosters_admin.php">Soupisky</a></div></div>
<?php if($error):?><div class="alert alert-danger"><?=kcrH($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=kcrH($success)?></div><?php endif;?>
<?php if(!$run):?>
<div class="alert alert-info">Převod vytvoří aktivní členství v soupiskách 2026/27 a může ovlivnit tréninky a členské výhody. U historických záznamů bez odpovídající skupiny či ročníku nic nehádá.</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?=kcrH(csrf_token())?>"><input type="hidden" name="action" value="preview"><button class="btn btn-primary">Připravit kontrolní náhled</button></form>
<?php if($preview):?><div class="card my-3"><div class="card-body"><h2 class="h5">Kontrolní náhled</h2><p><strong><?=$preview['counts']['matched']?></strong> nových aktivních vazeb · <strong><?=$preview['counts']['already_present']?></strong> již aktivních · <strong><?=$preview['counts']['review']?></strong> k rozhodnutí. Datum aktivace: <?=kcrH($preview['valid_from'])?>.</p><p class="small">Otisk: <code><?=kcrH($preview['fingerprint'])?></code></p><details><summary>Zobrazit návrhy a sporné případy</summary><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Sportovec</th><th>Starý KIS</th><th>Návrh 2026/27</th><th>Stav</th></tr></thead><tbody><?php foreach($preview['claims'] as $claim):?><tr><td><?=kcrH($claim['name'])?> #<?=$claim['sportovec_id']?></td><td><?=kcrH($claim['legacy_label'])?></td><td><?=kcrH($claim['suggested_team_name']??'—')?></td><td><?=kcrH($claim['reason']!==''?($reasons[$claim['reason']]??$claim['reason']):$claim['status'])?></td></tr><?php endforeach;?></tbody></table></div></details><form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?=kcrH(csrf_token())?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="fingerprint" value="<?=kcrH($preview['fingerprint'])?>"><label class="form-label">Potvrďte převod opsáním <strong>PREVEST DO 2026/27</strong></label><input class="form-control mb-2" name="confirmation" required autocomplete="off"><button class="btn btn-danger">Zapsat aktivní členství a frontu rozhodnutí</button></form></div></div><?php endif;?>
<?php else:$summary=json_decode($run['summary_json'],true);?><div class="alert alert-info"><strong><?=kcrH($summary['matched'])?> automaticky zapsaných aktivních vazeb; <?=kcrH($summary['created_teams']??0)?> nových soupisek; <?=kcrH($summary['review'])?> původně k rozhodnutí.</strong> <a href="?view=protocol">Stáhnout úplný protokol JSON</a>.</div>
<div class="d-flex gap-2 flex-wrap mb-3"><?php foreach(['review'=>'K rozhodnutí','matched'=>'Automaticky přiřazeno','approved'=>'Ručně potvrzeno','skipped'=>'Ponecháno bez vazby','already_present'=>'Již na soupisce'] as $key=>$title):?><a class="btn btn-sm <?=$status===$key?'btn-primary':'btn-outline-primary'?>" href="?status=<?=kcrH($key)?>"><?=kcrH($title)?> (<?=(int)($counts[$key]??0)?>)</a><?php endforeach;?></div>
<form method="get" class="d-flex gap-2 mb-3"><input type="hidden" name="status" value="<?=kcrH($status)?>"><input name="q" class="form-control" value="<?=kcrH($q)?>" placeholder="Jméno nebo původní soupiska"><button class="btn btn-outline-primary">Hledat</button></form><p class="small text-muted">Zobrazeno nejvýše 250 řádků; celý soubor je v protokolu.</p>
<div class="table-responsive"><table class="table table-striped table-sm align-middle"><thead><tr><th>Sportovec</th><th>Starý KIS</th><th>Návrh 2026/27</th><th>Důvod</th><?php if($status==='review'):?><th>Rozhodnutí</th><?php endif;?></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=kcrH($row['prijmeni'].' '.$row['jmeno'])?> <small>#<?=(int)$row['sportovec_id']?></small><br><small><?=kcrH($row['narozeni']?:'Narození neuvedeno')?></small></td><td><?=kcrH($row['legacy_label'])?><?php if($row['legacy_status']==='review'):?><br><a href="kis_legacy_rosters_admin.php?status=review">Nejprve rozhodnout historickou vazbu</a><?php endif;?></td><td><?=kcrH($row['suggested_team_name']??'—')?></td><td><?=kcrH($reasons[$row['reason_code']]??$row['reason_code'])?><?php if($row['decision_note']):?><br><small><?=kcrH($row['decision_note'])?></small><?php endif;?></td><?php if($status==='review'):?><td><form method="post" class="d-flex gap-1 flex-wrap"><input type="hidden" name="csrf_token" value="<?=kcrH(csrf_token())?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="claim_id" value="<?=(int)$row['id']?>"><select class="form-select form-select-sm" name="target_team_id"><option value="">Vyberte aktuální soupisku</option><?php foreach($teams as $team):?><option value="<?=(int)$team['id']?>" <?=(int)$row['suggested_team_id']===(int)$team['id']?'selected':''?>><?=kcrH($team['name'])?></option><?php endforeach;?></select><input name="note" class="form-control form-control-sm" placeholder="Důvod rozhodnutí" required maxlength="1000"><button name="decision" value="approve" class="btn btn-success btn-sm">Přiřadit</button><button name="decision" value="skip" class="btn btn-outline-secondary btn-sm">Bez vazby</button></form></td><?php endif;?></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></main></body></html>
