<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/person_audit_timeline.php';

if (!isset($_SESSION['trener_id']) || !roleAtLeast('admin')) {
    header('Location: login.php');
    exit;
}

function pah(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$sportovecId = max(0, (int)($_GET['sportovec_id'] ?? 0));
$page = max(1, min(100, (int)($_GET['page'] ?? 1)));
$pageSize = in_array((int)($_GET['page_size'] ?? 50), [25, 50, 100], true)
    ? (int)$_GET['page_size'] : 50;
$searchResults = $query !== '' ? personAuditSearch($pdo, $query) : [];
$timeline = null;
$error = '';
if ($sportovecId > 0) {
    try {
        $timeline = personAuditTimeline($pdo, $sportovecId, $page, $pageSize);
    } catch (PersonAuditTimelineException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('person_audit_admin.php: ' . $exception->getMessage());
        $error = 'Časovou osu se nepodařilo bezpečně načíst.';
    }
}

function paPageUrl(int $sportovecId, int $page, int $pageSize): string
{
    return '?' . http_build_query(['sportovec_id' => $sportovecId, 'page' => $page, 'page_size' => $pageSize]);
}
?>
<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auditní časová osa osoby</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1200px"><div class="mb-3"><h1 class="h4 mb-1">Auditní časová osa osoby</h1>
<p class="text-muted mb-0">Pouze čtení. Zobrazuje existující auditní záznamy; chybějící důvod ani aktér se nedovozuje.</p></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=pah($error)?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body"><form method="get" class="row g-2 align-items-end">
<div class="col-md-9"><label class="form-label" for="q">Sportovec: ID nebo část jména</label><input class="form-control" id="q" name="q" value="<?=pah($query)?>" maxlength="100" required></div>
<div class="col-md-3"><button class="btn btn-primary w-100">Vyhledat</button></div></form>
<?php if ($query !== ''): ?><div class="list-group mt-3"><?php if ($searchResults === []): ?><div class="list-group-item text-muted">Nenalezen žádný sportovec.</div><?php endif; ?>
<?php foreach ($searchResults as $person): ?><a class="list-group-item list-group-item-action" href="?sportovec_id=<?=(int)$person['id']?>">
<strong><?=pah($person['prijmeni'] . ' ' . $person['jmeno'])?></strong> <span class="text-muted">#<?=(int)$person['id']?> · <?=pah($person['narozeni'] ?: 'datum narození neuvedeno')?> · <?=pah($person['stav_clenstvi'])?></span></a><?php endforeach; ?></div><?php endif; ?>
</div></div>
<?php if ($timeline !== null): $person = $timeline['person']; ?><div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1"><?=pah($person['prijmeni'] . ' ' . $person['jmeno'])?> <span class="text-muted">#<?=(int)$person['id']?></span></h2>
<div class="small text-muted">Narození <?=pah($person['narozeni'] ?: 'neuvedeno')?> · členství <?=pah($person['stav_clenstvi'])?></div></div>
<form method="get"><input type="hidden" name="sportovec_id" value="<?=(int)$person['id']?>"><select class="form-select form-select-sm" name="page_size" onchange="this.form.submit()"><option value="25" <?=$pageSize===25?'selected':''?>>25 / strana</option><option value="50" <?=$pageSize===50?'selected':''?>>50 / strana</option><option value="100" <?=$pageSize===100?'selected':''?>>100 / strana</option></select></form></div>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Čas</th><th>Zdroj a akce</th><th>Aktér</th><th>Změna</th><th>Důvod</th><th></th></tr></thead><tbody>
<?php if ($timeline['events'] === []): ?><tr><td colspan="6" class="text-muted p-3">Pro tuto osobu nejsou v podporovaných auditních zdrojích záznamy.</td></tr><?php endif; ?>
<?php foreach ($timeline['events'] as $event): ?><tr><td class="text-nowrap"><?=pah($event['occurred_at'])?></td><td><span class="badge text-bg-secondary"><?=pah($event['source_label'])?></span><br><code><?=pah($event['action'])?></code></td>
<td><?=pah($event['actor_label'])?><br><small class="text-muted"><?=pah($event['actor_type'])?><?= $event['actor_id'] ? ' #' . (int)$event['actor_id'] : '' ?></small></td>
<td><?=pah($event['from_status'] ?? '—')?> → <?=pah($event['to_status'] ?? '—')?></td><td><?=pah($event['reason'] ?? 'Zdroj důvod neukládá.')?></td>
<td><a class="btn btn-outline-secondary btn-sm" href="<?=pah($event['source_link'])?>">Zdroj</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<nav class="d-flex justify-content-between mt-3"><div><?php if ($timeline['has_previous']): ?><a class="btn btn-outline-primary btn-sm" href="<?=pah(paPageUrl((int)$person['id'], $page - 1, $pageSize))?>">← Novější</a><?php endif; ?></div>
<div class="small text-muted py-1">Strana <?=$page?></div><div><?php if ($timeline['has_next']): ?><a class="btn btn-outline-primary btn-sm" href="<?=pah(paPageUrl((int)$person['id'], $page + 1, $pageSize))?>">Starší →</a><?php endif; ?></div></nav>
<?php endif; ?></main></body></html>
