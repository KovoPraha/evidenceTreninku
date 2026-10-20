<?php
declare(strict_types=1);
require_once __DIR__.'/includes/init.php';
require_once __DIR__.'/csrf_helper.php';
require_once __DIR__.'/includes/roster_hierarchy.php';
require_once __DIR__.'/includes/roster_legacy_mapping_preview.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence') || !roleAtLeast('admin')) { header('Location: login.php'); exit; }
function krha(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function krhPeople(PDO $pdo, array $ids): array {
    $ids = array_slice(array_values(array_unique(array_map('intval',$ids))),0,100);
    if (!$ids) return [];
    $stmt = $pdo->prepare('SELECT jmeno,prijmeni FROM sportovci WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY prijmeni,jmeno,id');
    $stmt->execute($ids);
    return array_map(static fn(array $row): string => $row['prijmeni'].' '.$row['jmeno'],$stmt->fetchAll(PDO::FETCH_ASSOC));
}
$actorId = (int)$_SESSION['trener_id'];
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$errors = [];
$notice = (string)($_SESSION['roster_hierarchy_notice'] ?? '');
unset($_SESSION['roster_hierarchy_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) throw new KisRosterException('Formulář vypršel. Obnovte stránku.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $created = kisRosterCreateStandingTeam($pdo,$actorId,(string)($_POST['code'] ?? ''),(string)($_POST['name'] ?? ''),(string)($_POST['kind'] ?? ''),(string)($_POST['reason'] ?? ''));
            $_SESSION['roster_hierarchy_notice'] = 'Založeno: '.$created['name'].'.';
        } elseif ($action === 'attach') {
            $result = kisRosterAttachChildren($pdo,(int)($_POST['parent_team_id'] ?? 0),(array)($_POST['child_ids'] ?? []),(string)($_POST['valid_from'] ?? ''),$actorId,(string)($_POST['reason'] ?? ''),(string)($_POST['fingerprint'] ?? ''));
            $_SESSION['roster_hierarchy_notice'] = 'Uloženo '.$result['created_count'].' nových vazeb. Členství sportovců nebylo kopírováno.';
        } elseif ($action === 'end') {
            $result = kisRosterEndHierarchyEdge($pdo,(int)($_POST['edge_id'] ?? 0),$actorId,(string)($_POST['valid_to'] ?? ''),(string)($_POST['reason'] ?? ''),(string)($_POST['fingerprint'] ?? ''));
            $_SESSION['roster_hierarchy_notice'] = $result['changed'] ? 'Vazba byla ukončena.' : 'Vazba již byla ukončena.';
        } else throw new InvalidArgumentException('Neznámá akce.');
        header('Location: kis_roster_hierarchy_admin.php'); exit;
    } catch (InvalidArgumentException|KisRosterException $error) { $errors[] = $error->getMessage(); }
    catch (Throwable $error) { error_log('kis_roster_hierarchy_admin: '.$error->getMessage()); $errors[] = 'Změnu se nepodařilo uložit.'; }
}
$teams = $pdo->query("SELECT id,code,name,team_kind,season_id,status FROM club_teams WHERE status='active' ORDER BY team_kind,name,id")->fetchAll(PDO::FETCH_ASSOC);
$groups = array_values(array_filter($teams,static fn(array $team): bool => $team['team_kind']==='group'));
$parentId = (int)($_GET['parent_team_id'] ?? 0);
$childIds = (array)($_GET['child_ids'] ?? []);
$validFrom = (string)($_GET['valid_from'] ?? $today);
$preview = null;
$previewPeople = [];
$overlapPeople = [];
if ($parentId > 0 && $childIds) {
    try {
        $preview = kisRosterPreviewHierarchy($pdo,$parentId,$childIds,$validFrom);
        $previewPeople = krhPeople($pdo,$preview['after_member_ids']);
        $overlapPeople = krhPeople($pdo,$preview['overlap_member_ids']);
    }
    catch (InvalidArgumentException|KisRosterException $error) { $errors[] = $error->getMessage(); }
}
$edges = $pdo->query('SELECT e.*,p.name parent_name,c.name child_name FROM club_team_edges e JOIN club_teams p ON p.id=e.parent_team_id JOIN club_teams c ON c.id=e.child_team_id ORDER BY e.id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
$attached = [];
foreach ($pdo->query("SELECT DISTINCT child_team_id FROM club_team_edges WHERE valid_from<='$today' AND (valid_to IS NULL OR valid_to>='$today')")->fetchAll(PDO::FETCH_COLUMN) as $childId) $attached[(int)$childId] = true;
$unassigned = array_values(array_filter($teams,static fn(array $team): bool => $team['team_kind']==='roster' && !isset($attached[(int)$team['id']])));
$endPreview = null;
$endEdgeId = (int)($_GET['end_edge_id'] ?? 0);
$endValidTo = (string)($_GET['end_valid_to'] ?? $today);
if ($endEdgeId > 0) {
    try { $endPreview = kisRosterPreviewEdgeEnd($pdo,$endEdgeId,$endValidTo); }
    catch (InvalidArgumentException|KisRosterException $error) { $errors[] = $error->getMessage(); }
}
$legacyMap = ['rows'=>[],'counts'=>['exact'=>0,'ambiguous'=>0,'none'=>0]];
try { $legacyMap = kisRosterLegacyMappingPreview($pdo,$today); }
catch (Throwable $error) { error_log('kis_roster_hierarchy_admin legacy mapping: '.$error->getMessage()); $errors[] = 'Přehled starých skupin se nepodařilo načíst.'; }
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Skupiny a soupisky</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php include __DIR__.'/hlavicka.php'; ?><main class="container py-4"><div class="d-flex justify-content-between align-items-center"><h1 class="h4">Skupiny a soupisky</h1><a href="kis_rosters_admin.php" class="btn btn-outline-secondary btn-sm">Historická správa soupisek</a></div><p>Skupina sdružuje soupisky bez kopírování členů. Vazby i členství mají datum platnosti; dřívější složení lze dohledat k danému dni.</p>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?=krha($error)?></div><?php endforeach; ?><?php if ($notice!==''): ?><div class="alert alert-success"><?=krha($notice)?></div><?php endif; ?>
<div class="row g-3"><section class="col-lg-5"><div class="card"><div class="card-header">Nová trvalá skupina nebo soupiska</div><div class="card-body"><form method="post" class="vstack gap-2"><input type="hidden" name="csrf_token" value="<?=krha(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Typ<select class="form-select" name="kind"><option value="group">Skupina</option><option value="roster">Soupiska</option></select></label><label>Kód<input class="form-control" name="code" required maxlength="48"></label><label>Název<input class="form-control" name="name" required maxlength="160"></label><label>Důvod<input class="form-control" name="reason" required maxlength="1000"></label><button class="btn btn-primary">Založit</button></form></div></div></section>
<section class="col-lg-7"><div class="card"><div class="card-header">Zařadit existující soupisky do skupiny</div><div class="card-body"><form method="get" class="vstack gap-2"><label>Skupina<select class="form-select" name="parent_team_id" required><option value="">Vyberte</option><?php foreach ($groups as $group): ?><option value="<?=(int)$group['id']?>" <?=$parentId===(int)$group['id']?'selected':''?>><?=krha($group['name'])?></option><?php endforeach; ?></select></label><label>Platí od<input type="date" class="form-control" name="valid_from" value="<?=krha($validFrom)?>" required></label><label>Podřízené skupiny nebo soupisky <span class="small text-muted">(lze vybrat více)</span><select class="form-select" name="child_ids[]" multiple size="12" required><?php foreach ($teams as $team): if ((int)$team['id']===$parentId) continue; ?><option value="<?=(int)$team['id']?>" <?=in_array((string)$team['id'],array_map('strval',$childIds),true)?'selected':''?>><?=krha(($team['team_kind']==='group'?'Skupina: ':'Soupiska: ').$team['name'])?><?=isset($attached[(int)$team['id']])?' · již přiřazeno':''?></option><?php endforeach; ?></select></label><button class="btn btn-outline-primary">Zobrazit náhled</button></form>
<?php if ($preview): ?>
<hr><h2 class="h6">Náhled: <?=krha($preview['parent']['name'])?></h2>
<p>Členové skupiny k <?=krha($preview['date'])?>: <strong><?=$preview['before_count']?></strong>; po přiřazení: <strong><?=$preview['after_count']?></strong>; překryvy: <strong><?=$preview['overlap_count']?></strong>. Člověk v několika soupiskách se počítá jednou.</p>
<ul><?php foreach ($preview['children'] as $child): ?><li><?=krha($child['name'])?> · <?=$child['member_count']?> členů</li><?php endforeach; ?></ul>
<details><summary>Členové po přiřazení (<?=count($preview['after_member_ids'])?>)</summary><p><?=krha(implode(', ',$previewPeople))?><?=count($preview['after_member_ids'])>100?' …':''?></p></details>
<?php if ($overlapPeople): ?><details><summary>Překryvy (<?=count($preview['overlap_member_ids'])?>)</summary><p><?=krha(implode(', ',$overlapPeople))?><?=count($preview['overlap_member_ids'])>100?' …':''?></p></details><?php endif; ?>
<h3 class="h6 mt-3">Dopad na budoucí tréninky a akce</h3>
<ul><?php foreach ($preview['impact']['plans'] as $plan): ?><li>Trénink <?=krha($plan['date'].' · '.$plan['name'])?> · odpovědi <?=$plan['rsvp_count']?>, náhrady <?=$plan['makeup_count']?></li><?php endforeach; ?><?php foreach ($preview['impact']['events'] as $event): ?><li>Akce <?=krha($event['date'].' · '.$event['name'])?> · přihlášky <?=$event['registration_count']?></li><?php endforeach; ?></ul>
<?php if ($preview['impact']['blocked']): ?><div class="alert alert-warning">Změna zasahuje termín s odpověďmi nebo přihláškami. Upravte jeho cíl samostatně; tato vazba zatím nejde uložit.</div><?php endif; ?>
<?php if (!$preview['impact']['blocked']): ?><form method="post" class="vstack gap-2"><input type="hidden" name="csrf_token" value="<?=krha(csrf_token())?>"><input type="hidden" name="action" value="attach"><input type="hidden" name="parent_team_id" value="<?=$parentId?>"><input type="hidden" name="valid_from" value="<?=krha($preview['date'])?>"><input type="hidden" name="fingerprint" value="<?=krha($preview['fingerprint'])?>"><?php foreach ($preview['children'] as $child): ?><input type="hidden" name="child_ids[]" value="<?=$child['id']?>"><?php endforeach; ?><label>Důvod změny<input class="form-control" name="reason" maxlength="1000" required></label><button class="btn btn-success">Potvrdit přiřazení</button></form><?php endif; ?><?php endif; ?></div></div></section></div>
<?php if ($endPreview): ?>
<section class="card mt-3"><div class="card-header">Náhled ukončení vazby #<?=(int)$endPreview['edge']['id']?></div><div class="card-body">
<p>Vazba skončí <?=krha($endPreview['valid_to'])?>. Dopad na budoucí termíny od následujícího dne:</p>
<ul><?php foreach ($endPreview['impact']['plans'] as $plan): ?><li>Trénink <?=krha($plan['date'].' · '.$plan['name'])?> · odpovědi <?=$plan['rsvp_count']?>, náhrady <?=$plan['makeup_count']?></li><?php endforeach; ?><?php foreach ($endPreview['impact']['events'] as $event): ?><li>Akce <?=krha($event['date'].' · '.$event['name'])?> · přihlášky <?=$event['registration_count']?></li><?php endforeach; ?></ul>
<?php if ($endPreview['impact']['blocked']): ?><div class="alert alert-warning">Ukončení zasahuje termín s odpověďmi nebo přihláškami. Nejprve upravte cíl dotčeného termínu samostatně.</div><?php else: ?>
<form method="post" class="vstack gap-2"><input type="hidden" name="csrf_token" value="<?=krha(csrf_token())?>"><input type="hidden" name="action" value="end"><input type="hidden" name="edge_id" value="<?=(int)$endPreview['edge']['id']?>"><input type="hidden" name="valid_to" value="<?=krha($endPreview['valid_to'])?>"><input type="hidden" name="fingerprint" value="<?=krha($endPreview['fingerprint'])?>"><label>Důvod ukončení<input class="form-control" name="reason" required maxlength="1000"></label><button class="btn btn-outline-danger">Potvrdit ukončení vazby</button></form>
<?php endif; ?></div></section><?php endif; ?>
<div class="row g-3 mt-1"><section class="col-lg-5"><div class="card"><div class="card-header">Soupisky bez skupiny dnes (<?=count($unassigned)?>)</div><div class="card-body"><ul class="mb-0"><?php foreach ($unassigned as $team): ?><li><?=krha($team['name'])?> <small class="text-muted">#<?=(int)$team['id']?></small></li><?php endforeach; ?></ul></div></div></section><section class="col-lg-7"><div class="card"><div class="card-header">Historie vazeb</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Skupina</th><th>Soupiska</th><th>Platnost</th><th></th></tr></thead><tbody><?php foreach ($edges as $edge): ?><tr><td><?=krha($edge['parent_name'])?></td><td><?=krha($edge['child_name'])?></td><td><?=krha($edge['valid_from'].' – '.($edge['valid_to']??'dosud'))?></td><td><?php if ($edge['valid_to']===null): ?><form method="get" class="d-flex gap-1"><input type="hidden" name="end_edge_id" value="<?=(int)$edge['id']?>"><input type="date" class="form-control form-control-sm" name="end_valid_to" value="<?=krha($today)?>" required aria-label="Konec vazby"><button class="btn btn-outline-danger btn-sm">Náhled ukončení</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></section></div><section class="card mt-3"><div class="card-header">Staré skupiny a podskupiny – návrhy mapování bez zápisu</div><div class="card-body">
<p class="small text-muted">ID staré evidence a ID KIS soupisky jsou z odlišných tabulek. Návrh „jednoznačná“ znamená pouze jediné přesné jméno po sjednocení velikosti písmen a mezer; při stejných názvech více zdrojů vyžaduje kontrolu.</p>
<p>Jednoznačné: <strong><?= $legacyMap['counts']['exact'] ?></strong> · Nejasné: <strong><?= $legacyMap['counts']['ambiguous'] ?></strong> · Bez vazby: <strong><?= $legacyMap['counts']['none'] ?></strong></p>
<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Starý typ / ID</th><th>Název</th><th>Návrh KIS</th><th>Stav</th><th>Budoucí plány</th><th>Historické plány</th><th>Historické účasti</th></tr></thead><tbody>
<?php foreach ($legacyMap['rows'] as $row): ?><tr><td><?= krha(($row['kind']==='group'?'Skupina':'Podskupina').' #'.$row['id']) ?></td><td><?= krha(($row['parent_name'] ? $row['parent_name'].' → ' : '').$row['name']) ?></td><td><?= krha($row['candidates'] ? implode(', ',array_map(static fn(array $candidate): string => '#'.$candidate['id'].' '.$candidate['name'],$row['candidates'])) : '—') ?></td><td><?= krha(['exact'=>'Jednoznačný návrh','ambiguous'=>'Nejasné','none'=>'Bez vazby'][$row['status']]) ?></td><td><?= (int)$row['future_plan_count'] ?></td><td><?= (int)$row['historical_plan_count'] ?></td><td><?= (int)$row['historical_attendance_count'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></section></main></body></html>
