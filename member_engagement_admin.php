<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/member_engagement.php';

if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
staffRequireActivePosition('registrar');
header('Cache-Control: no-store, private');

function meh(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$query = trim((string)($_GET['q'] ?? ''));
$levelFilter = (string)($_GET['level'] ?? '');
if (!isset(MEMBER_ENGAGEMENT_LEVELS[$levelFilter])) $levelFilter = '';
$parameters = [];
$where = "WHERE s.stav_clenstvi<>'archiv'";
if ($query !== '') {
    $where .= ' AND (s.jmeno LIKE ? OR s.prijmeni LIKE ? OR s.email LIKE ?)';
    $like = '%' . $query . '%';
    $parameters = [$like, $like, $like];
}
$statement = $pdo->prepare("SELECT s.id,s.jmeno,s.prijmeni,s.narozeni,s.email,s.stav_clenstvi FROM sportovci s $where ORDER BY s.prijmeni,s.jmeno,s.id LIMIT 1000");
$statement->execute($parameters);
$people = $statement->fetchAll(PDO::FETCH_ASSOC);
$levels = memberEngagementMap($pdo, array_map('intval', array_column($people, 'id')));
if ($levelFilter !== '') $people = array_values(array_filter($people, static fn(array $person): bool => ($levels[(int)$person['id']]['key'] ?? '') === $levelFilter));
$counts = array_fill_keys(array_keys(MEMBER_ENGAGEMENT_LEVELS), 0);
foreach ($people as $person) $counts[$levels[(int)$person['id']]['key']]++;
$customerOnly = memberEngagementCustomerOnlyCount($pdo);
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Úrovně zapojení</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"></head><body class="bg-light"><?php require __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1200px">
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><div><h1 class="h3 mb-1">Úrovně zapojení osob</h1><p class="text-muted mb-0">Barevná vrstva se počítá z právě platných programů a soupisek. Nelze ji ručně přepsat.</p></div><a class="btn btn-outline-secondary align-self-start" href="kis_rosters_admin.php">Týmy a soupisky</a></div>
<div class="row g-3 mb-3">
<?php foreach (MEMBER_ENGAGEMENT_LEVELS as $key => $definition): ?><div class="col-md-4"><div class="card border-<?=meh($definition['badge'])?> h-100"><div class="card-body"><span class="badge text-bg-<?=meh($definition['badge'])?>"><?=meh($definition['label'])?></span><div class="display-6 mt-2"><?=(int)$counts[$key]?></div></div></div></div><?php endforeach; ?>
</div>
<div class="alert alert-info">Samostatné zákaznické účty bez propojené sportovní osoby: <strong><?=$customerOnly?></strong>. Tyto účty mohou nakupovat zboží a nevytvářejí falešné sportovce.</div>
<form method="get" class="row g-2 mb-3"><div class="col-md-6"><label class="visually-hidden" for="engagement-search">Hledat</label><input id="engagement-search" class="form-control" name="q" value="<?=meh($query)?>" placeholder="Jméno nebo e-mail"></div><div class="col-md-4"><label class="visually-hidden" for="engagement-level">Úroveň</label><select id="engagement-level" class="form-select" name="level"><option value="">Všechny úrovně</option><?php foreach(MEMBER_ENGAGEMENT_LEVELS as$key=>$definition):?><option value="<?=meh($key)?>" <?=$levelFilter===$key?'selected':''?>><?=meh($definition['label'])?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filtrovat</button></div></form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Osoba</th><th>Úroveň</th><th>Proč</th><th>Členství</th></tr></thead><tbody>
<?php foreach($people as$person):$level=$levels[(int)$person['id']];?><tr><td><strong><?=meh($person['prijmeni'].' '.$person['jmeno'])?></strong><div class="small text-muted"><?=meh($person['email']?:'bez e-mailu')?> · <?=meh($person['narozeni'])?></div></td><td><span class="badge text-bg-<?=meh($level['badge'])?>"><?=meh($level['label'])?></span></td><td class="small"><?=meh(implode(' · ',$level['reasons']))?></td><td><?=meh($person['stav_clenstvi'])?></td></tr><?php endforeach;?>
<?php if($people===[]):?><tr><td colspan="4" class="text-center text-muted py-4">Filtru neodpovídá žádná osoba.</td></tr><?php endif;?>
</tbody></table></div></div>
</main></body></html>
