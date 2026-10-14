<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_makeup.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence')) { header('Location: login.php'); exit; }
function makeupAdminH(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
$error='';$message=(string)($_SESSION['makeup_admin_flash']??'');unset($_SESSION['makeup_admin_flash']);
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token']??''))) throw new InvalidArgumentException('Formulář vypršel.');
        $action=(string)($_POST['action']??'');
        if ($action==='policy') {
            clubMakeupPolicySave($pdo,(int)$_SESSION['trener_id'],(int)($_POST['program_id']??0),(int)($_POST['season_id']??0),(int)($_POST['team_id']??0),isset($_POST['enabled']),(int)($_POST['excuse_hours']??12),isset($_POST['apply_future']));
            $_SESSION['makeup_admin_flash']='Pravidlo omluv bylo uloženo.';
        } elseif ($action==='period') {
            $next=(int)($_POST['next_offer_id']??0);
            clubMakeupOfferPeriodSave($pdo,(int)$_SESSION['trener_id'],(int)($_POST['offer_id']??0),(string)($_POST['period_kind']??''),$next>0?$next:null);
            $_SESSION['makeup_admin_flash']='Vazba pololetí byla uložena.';
        } else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: club_makeup_policies_admin.php',true,303);exit;
    } catch (InvalidArgumentException|ClubMakeupException $exception) { $error=$exception->getMessage(); }
    catch (Throwable $exception) { error_log('club_makeup_policies_admin: '.$exception->getMessage());$error='Nastavení se nepodařilo bezpečně uložit.'; }
}
$offers=[];$policies=[];$periods=[];
if (clubMakeupReady($pdo)) {
    $offers=$pdo->query('SELECT o.id,o.program_id,o.season_id,o.team_id,o.name,o.starts_on,o.ends_on,p.name AS program_name,s.name AS season_name,t.name AS team_name FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_seasons s ON s.id=o.season_id JOIN club_teams t ON t.id=o.team_id ORDER BY s.starts_on DESC,p.name,o.starts_on,o.id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdo->query('SELECT * FROM club_makeup_policies')->fetchAll(PDO::FETCH_ASSOC) as $row) $policies[$row['program_id'].'-'.$row['season_id'].'-'.$row['team_id']]=$row;
    foreach ($pdo->query('SELECT * FROM club_makeup_offer_periods')->fetchAll(PDO::FETCH_ASSOC) as $row) $periods[(int)$row['offer_id']]=$row;
}
$scopes=[];foreach($offers as $offer){$key=$offer['program_id'].'-'.$offer['season_id'].'-'.$offer['team_id'];$scopes[$key]=$offer;}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Omluvy a náhradní lekce kroužků</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__.'/hlavicka.php';?><main class="container py-4" style="max-width:1250px">
<a class="btn btn-sm btn-outline-secondary mb-3" href="club_programs_admin.php">Zpět na kroužky</a><h1 class="h4">Omluvy a náhradní lekce</h1>
<p class="text-muted">Pravidlo je společné pro celoroční a pololetní nabídky stejného kroužku a soupisky. Náhradní termíny zapíná trenér jednotlivě v přípravě tréninku.</p>
<?php if($error!==''):?><div class="alert alert-danger"><?=makeupAdminH($error)?></div><?php endif;?><?php if($message!==''):?><div class="alert alert-success"><?=makeupAdminH($message)?></div><?php endif;?>
<?php if(!clubMakeupReady($pdo)):?><div class="alert alert-warning">Funkce čeká na databázovou migraci.</div><?php else:?>
<h2 class="h5 mt-4">Lhůta pro omluvu</h2><p class="small text-muted">Po lhůtě lze nepřítomnost stále oznámit trenérovi, ale nevznikne nárok na náhradu. Nová pravidla jsou bezpečně vypnutá, dokud je zde nezapnete.</p>
<div class="row g-3"><?php foreach($scopes as $key=>$scope):$policy=$policies[$key]??null;?><div class="col-lg-6"><form method="post" class="card card-body h-100"><?=csrf_field()?><input type="hidden" name="action" value="policy"><input type="hidden" name="program_id" value="<?=(int)$scope['program_id']?>"><input type="hidden" name="season_id" value="<?=(int)$scope['season_id']?>"><input type="hidden" name="team_id" value="<?=(int)$scope['team_id']?>"><strong><?=makeupAdminH($scope['program_name'])?></strong><span class="small text-muted mb-2"><?=makeupAdminH($scope['season_name'].' / '.$scope['team_name'])?></span><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="enabled" id="enable-<?=makeupAdminH($key)?>" <?=($policy&&(int)$policy['enabled']===1)?'checked':''?>><label class="form-check-label" for="enable-<?=makeupAdminH($key)?>">Povolit náhradní lekce</label></div><label class="form-label" for="hours-<?=makeupAdminH($key)?>">Omluva nejpozději X hodin před tréninkem</label><input class="form-control mb-2" type="number" min="0" max="720" name="excuse_hours" id="hours-<?=makeupAdminH($key)?>" value="<?=(int)($policy['excuse_hours']??12)?>" required><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="apply_future" id="future-<?=makeupAdminH($key)?>" checked><label class="form-check-label" for="future-<?=makeupAdminH($key)?>">Použít také na vypsané budoucí termíny</label></div><button class="btn btn-primary mt-auto">Uložit pravidlo</button></form></div><?php endforeach;?></div>
<h2 class="h5 mt-5">Celoroční a pololetní nabídky</h2><p class="small text-muted">U prvního pololetí vyberte konkrétní navazující nabídku. Převedený nárok půjde využít až po jejím zaplacení; zaplacená celoroční varianta platná ve druhém pololetí podmínku splňuje také.</p>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Nabídka</th><th>Období</th><th>Zařazení pro náhrady</th></tr></thead><tbody><?php foreach($offers as $offer):$period=$periods[(int)$offer['id']]??null;?><tr><td><?=makeupAdminH($offer['program_name'].' / '.$offer['name'])?><div class="small text-muted"><?=makeupAdminH($offer['season_name'].' · '.$offer['team_name'])?></div></td><td><?=makeupAdminH($offer['starts_on'].' – '.$offer['ends_on'])?></td><td><form method="post" class="d-flex flex-wrap gap-2 align-items-center"><?=csrf_field()?><input type="hidden" name="action" value="period"><input type="hidden" name="offer_id" value="<?=(int)$offer['id']?>"><select class="form-select form-select-sm" name="period_kind" style="max-width:180px" aria-label="Druh období"><option value="single" <?=($period['period_kind']??'single')==='single'?'selected':''?>>Jednorázové / jiné</option><option value="annual" <?=($period['period_kind']??'')==='annual'?'selected':''?>>Celoroční</option><option value="first" <?=($period['period_kind']??'')==='first'?'selected':''?>>První pololetí</option><option value="second" <?=($period['period_kind']??'')==='second'?'selected':''?>>Druhé pololetí</option></select><select class="form-select form-select-sm" name="next_offer_id" style="max-width:260px" aria-label="Navazující nabídka"><option value="">Bez navazující nabídky</option><?php foreach($offers as $next):if((int)$next['id']===(int)$offer['id']||(int)$next['program_id']!==(int)$offer['program_id']||(int)$next['season_id']!==(int)$offer['season_id']||(int)$next['team_id']!==(int)$offer['team_id']||(string)$next['starts_on']<=(string)$offer['ends_on'])continue;?><option value="<?=(int)$next['id']?>" <?=((int)($period['next_offer_id']??0)===(int)$next['id'])?'selected':''?>><?=makeupAdminH($next['name'])?></option><?php endforeach;?></select><button class="btn btn-outline-primary btn-sm">Uložit</button></form></td></tr><?php endforeach;?></tbody></table></div>
<?php endif;?></main></body></html>
