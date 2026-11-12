<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/club_makeup.php';
require_once __DIR__ . '/includes/operational_cases.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence')) {
    header('Location: login.php');
    exit;
}
staffRequireActivePosition('program_coordinator');
if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('Databázové připojení není dostupné.');
}
function makeupAdminH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function makeupAdminOptionalInt(mixed $value): ?int
{
    return trim((string) $value) === '' ? null : (int) $value;
}
$error = '';
$message = (string)($_SESSION['makeup_admin_flash'] ?? '');
unset($_SESSION['makeup_admin_flash']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('Formulář vypršel.');
        }
        $pdo->beginTransaction();
        operationalCaseActor($pdo, (int)$_SESSION['trener_id'], 'program_coordinator');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'policy') {
            clubMakeupPolicySave(
                $pdo,
                (int)$_SESSION['trener_id'],
                (int)($_POST['offer_id'] ?? 0),
                isset($_POST['enabled']),
                (int)($_POST['excuse_hours'] ?? 12),
                isset($_POST['apply_future'])
            );
            $_SESSION['makeup_admin_flash'] = 'Pravidlo omluv bylo uloženo.';
        } elseif ($action === 'period') {
            $next = (int)($_POST['next_offer_id'] ?? 0);
            clubMakeupOfferPeriodSave(
                $pdo,
                (int)$_SESSION['trener_id'],
                (int)($_POST['offer_id'] ?? 0),
                (string)($_POST['period_kind'] ?? ''),
                $next > 0 ? $next : null
            );
            $_SESSION['makeup_admin_flash'] = 'Vazba pololetí byla uložena.';
        } elseif ($action === 'route') {
            clubMakeupRouteSave(
                $pdo,
                (int)$_SESSION['trener_id'],
                (int)($_POST['source_offer_id'] ?? 0),
                (int)($_POST['target_offer_id'] ?? 0),
                isset($_POST['allowed'])
            );
            $_SESSION['makeup_admin_flash'] = 'Povolení cílové nabídky bylo uloženo.';
        } elseif ($action === 'auto_settings') {
            clubMakeupAutoSettingsSave(
                $pdo,
                (int) $_SESSION['trener_id'],
                isset($_POST['enabled']),
                (int) ($_POST['excuse_hours'] ?? 12),
                (int) ($_POST['credit_valid_days'] ?? 365),
                (int) ($_POST['max_credits'] ?? 0),
                (int) ($_POST['cancel_hours'] ?? 12),
                isset($_POST['apply_future'])
            );
            $_SESSION['makeup_admin_flash'] = 'Společná pravidla náhrad byla uložena.';
        } elseif ($action === 'auto_offer') {
            clubMakeupAutoOfferSave(
                $pdo,
                (int) $_SESSION['trener_id'],
                (int) ($_POST['offer_id'] ?? 0),
                isset($_POST['enabled']),
                makeupAdminOptionalInt($_POST['excuse_hours'] ?? ''),
                makeupAdminOptionalInt($_POST['credit_valid_days'] ?? ''),
                makeupAdminOptionalInt($_POST['max_credits'] ?? ''),
                makeupAdminOptionalInt($_POST['cancel_hours'] ?? '')
            );
            $_SESSION['makeup_admin_flash'] = 'Výjimka nabídky byla uložena.';
        } elseif ($action === 'auto_route') {
            $routeMode = (string) ($_POST['route_mode'] ?? '');
            if (!in_array($routeMode, ['allow', 'deny', 'inherit'], true)) {
                throw new InvalidArgumentException('Neplatný režim výjimky.');
            }
            clubMakeupAutoRouteExceptionSave(
                $pdo,
                (int) $_SESSION['trener_id'],
                (int) ($_POST['source_offer_id'] ?? 0),
                (int) ($_POST['target_offer_id'] ?? 0),
                $routeMode === 'inherit' ? null : $routeMode === 'allow'
            );
            $_SESSION['makeup_admin_flash'] = 'Výjimka směru byla uložena.';
        } elseif ($action === 'auto_plan') {
            clubMakeupAutoPlanExclusionSave(
                $pdo,
                (int) $_SESSION['trener_id'],
                (int) ($_POST['plan_id'] ?? 0),
                isset($_POST['excluded'])
            );
            $_SESSION['makeup_admin_flash'] = 'Výjimka termínu byla uložena.';
        } else {
            throw new InvalidArgumentException('Neznámá akce.');
        }
        $pdo->commit();
        header('Location: club_makeup_policies_admin.php', true, 303);
        exit;
    } catch (InvalidArgumentException|ClubMakeupException|DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        } $error = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        } error_log('club_makeup_policies_admin: '.$exception->getMessage());
        $error = 'Nastavení se nepodařilo bezpečně uložit.';
    }
}
$offers = [];
$policies = [];
$periods = [];
$autoSettings = clubMakeupAutoSettings($pdo);
$autoOffers = [];
if (clubMakeupReady($pdo)) {
    $offers = $pdo->query(('SELECT o.id,o.program_id,o.team_id,o.name,o.starts_on,o.ends_on,o.capacity,o.status, '
    . 'o.birth_year_from,o.birth_year_to,p.name AS program_name,t.name AS team_name '
    .
    'FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_teams t ON t.id=o.team_id ' .

    'ORDER BY o.starts_on DESC,p.name,o.starts_on,o.id'))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdo->query('SELECT p.*,m.offer_id FROM club_makeup_policies p JOIN club_makeup_offer_policies m ON m.policy_id=p.id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $policies[(int)$row['offer_id']] = $row;
    }
    foreach ($pdo->query('SELECT * FROM club_makeup_offer_periods')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $periods[(int)$row['offer_id']] = $row;
    }
    if ($autoSettings !== null) {
        foreach ($pdo->query('SELECT * FROM club_makeup_offer_auto')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $autoOffers[(int) $row['offer_id']] = $row;
        }
    }
}
$scopes = [];
foreach ($offers as $offer) {
    $key = (int)$offer['id'];
    $scopes[$key] = $offer;
}
$reviewCounts = ['unclassified' => 0];
foreach ($offers as $offer) {
    $reviewStatus = clubMakeupOfferReviewStatus($periods[(int)$offer['id']] ?? null);
    if (isset($reviewCounts[$reviewStatus])) {
        $reviewCounts[$reviewStatus]++;
    }
}
$reviewOnly = (string)($_GET['filter'] ?? '') === 'review';

?><?php
echo('<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" ' .
    'content="width=device-width,initial-scale=1"><title>Omluvy a náhradní lekce kroužků</title><link ' .

    'href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" ' .

    'integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" ' .
    'crossorigin="anonymous"></head><body class="bg-light">');
?><?php
include __DIR__.'/hlavicka.php';
?><main class="container py-4" style="max-width:1250px">
<a class="btn btn-sm btn-outline-secondary mb-3" href="club_programs_admin.php">Zpět na kroužky</a><h1 class="h4">Omluvy a náhradní lekce</h1>
<?php
echo('<p class="text-muted">Dosavadní ruční pravidla zůstávají zachována. Automatický režim lze zapnout centrálně; ' .

    'vhodné termíny pak nepotřebují zásah trenéra.</p>
');
?><?php
if ($error !== ''):
    ?><div class="alert alert-danger"><?=
    makeupAdminH($error)
    ?></div><?php
endif;
?><?php
if ($message
    !== ''):
    ?><div class="alert alert-success"><?=
    makeupAdminH($message)
    ?></div><?php
endif;
?>
<?php
if (!clubMakeupReady($pdo)):
    ?><div class="alert alert-warning">Funkce čeká na databázovou migraci.</div><?php else:
        ?>
<?php
        if ($autoSettings !== null):
            ?>
<section class="card card-body mt-4" id="automatika"><h2 class="h5">Společná pravidla automatických náhrad</h2>
<p class="small text-muted">Zapnutí zpřístupní vhodné plánované termíny zapojených kroužků.
Historické nároky a rezervace se nemění. Nabídky bez strukturovaného věku či pevné kapacity
zůstávají mimo automatiku.</p>
<form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="auto_settings">
<div class="col-12 form-check ms-2">
<input class="form-check-input" type="checkbox" name="enabled" id="auto-enabled" <?= (int) $autoSettings['enabled'] === 1 ? 'checked' : '' ?>>
<label class="form-check-label" for="auto-enabled">Zapnout automatické náhrady</label>
</div>
<?php
foreach ([
    'excuse_hours' => ['Omluva nejpozději (hodiny)', 0, 720],
    'credit_valid_days' => ['Platnost kreditu (dny)', 1, 730],
    'max_credits' => ['Maximum kreditů z nabídky na dítě (0 = bez limitu)', 0, 100],
    'cancel_hours' => ['Zrušení rezervace nejpozději (hodiny)', 0, 720],
] as $field => $definition):
    ?>
<div class="col-md-3">
<label class="form-label" for="auto-<?= makeupAdminH($field) ?>">
<?= makeupAdminH($definition[0]) ?>
</label>
<input class="form-control" type="number" id="auto-<?= makeupAdminH($field) ?>"
name="<?= makeupAdminH($field) ?>" min="<?= $definition[1] ?>" max="<?= $definition[2] ?>"
value="<?= (int) $autoSettings[$field] ?>" required>
</div>
<?php endforeach; ?>
<div class="col-12 form-check ms-2">
<input class="form-check-input" type="checkbox" name="apply_future" id="auto-future">
<label class="form-check-label" for="auto-future">Použít novou lhůtu omluv také na dosud neproběhlé plánované termíny</label>
</div>
<div class="col-12"><button class="btn btn-primary">Uložit společná pravidla</button></div></form></section>
<h2 class="h5 mt-4">Výjimky nabídek</h2>
<p class="small text-muted">Každá nabídka dědí společná pravidla, pokud není vyplněna výjimka. Stávající vypnuté pravidlo omluv je nutné zapnout zvlášť.</p>
<p class="small text-muted">Věkové hranice jsou součástí nabídky kroužku.
<?php if (roleAtLeast('admin')): ?>
<a href="club_program_offers_admin.php">Upravit nabídky a ročníky narození</a>.
<?php endif; ?>
</p>
<div class="row g-3"><?php foreach ($offers as $offer): $override = $autoOffers[(int) $offer['id']] ?? null; ?>
<div class="col-lg-6">
<form method="post" class="card card-body h-100">
<?= csrf_field() ?>
<input type="hidden" name="action" value="auto_offer">
<input type="hidden" name="offer_id" value="<?= (int) $offer['id'] ?>">
<strong>
<?= makeupAdminH($offer['name']) ?> #<?= (int) $offer['id'] ?>
</strong>
<span class="small text-muted">Ročníky
<?= makeupAdminH(($offer['birth_year_from'] ?? '—') . '–' . ($offer['birth_year_to'] ?? '—')) ?>
· kapacita <?= makeupAdminH($offer['capacity'] ?? 'neuvedena') ?>
</span>
<div class="form-check my-2">
<input class="form-check-input" type="checkbox" name="enabled" id="auto-offer-<?= (int) $offer['id'] ?>"
<?= !$override || (int) $override['enabled'] === 1 ? 'checked' : '' ?>>
<label class="form-check-label" for="auto-offer-<?= (int) $offer['id'] ?>">Zapojit do automatiky</label>
</div>
<?php foreach (['excuse_hours' => 'Omluva (hodiny)', 'credit_valid_days' => 'Platnost (dny)', 'max_credits' => 'Maximum kreditů', 'cancel_hours' => 'Zrušení (hodiny)'] as $field => $label): ?>
<label class="form-label small mb-1" for="offer-<?= makeupAdminH($field) ?>-<?= (int) $offer['id'] ?>">
<?= makeupAdminH($label) ?> — prázdné dědí společnou hodnotu</label>
<input class="form-control form-control-sm mb-2" type="number"
id="offer-<?= makeupAdminH($field) ?>-<?= (int) $offer['id'] ?>"
name="<?= makeupAdminH($field) ?>" value="<?= makeupAdminH($override[$field] ?? '') ?>">
<?php endforeach; ?><button class="btn btn-outline-primary mt-auto">Uložit výjimku</button></form></div><?php endforeach; ?></div>
<h2 class="h5 mt-5">Výjimky směru a termínu</h2>
<form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="auto_route">
<div class="col-md-4">
<label class="form-label">Zdroj</label>
<select class="form-select" name="source_offer_id" required>
<option value="">Vyberte</option>
<?php foreach ($offers as $offer): ?>
<option value="<?= (int) $offer['id'] ?>">
<?= makeupAdminH($offer['name']) ?> #<?= (int) $offer['id'] ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Cíl</label>
<select class="form-select" name="target_offer_id" required>
<option value="">Vyberte</option>
<?php foreach ($offers as $offer): ?>
<option value="<?= (int) $offer['id'] ?>">
<?= makeupAdminH($offer['name']) ?> #<?= (int) $offer['id'] ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Výjimka</label>
<select class="form-select" name="route_mode">
<option value="deny">Zakázat</option>
<option value="allow">Povolit</option>
<option value="inherit">Vrátit automatické pravidlo</option>
</select>
</div>
<div class="col-12">
<button class="btn btn-outline-primary">Uložit výjimku směru</button>
</div>
</form>
<form method="post" class="row g-2 align-items-end">
<?= csrf_field() ?>
<input type="hidden" name="action" value="auto_plan">
<div class="col-md-3">
<label class="form-label">ID plánovaného termínu</label>
<input class="form-control" type="number" min="1" name="plan_id" required>
</div>
<div class="col-md-4 form-check ms-2">
<input class="form-check-input" type="checkbox" name="excluded" id="auto-plan-excluded" checked>
<label class="form-check-label" for="auto-plan-excluded">Vyloučit z automatických náhrad</label>
</div>
<div class="col-12">
<button class="btn btn-outline-primary">Uložit výjimku termínu</button>
</div>
</form>
<?php
        $routeExceptions = $pdo->query('SELECT source_offer_id,target_offer_id,allowed '
            . 'FROM club_makeup_route_exceptions ORDER BY source_offer_id,target_offer_id')
            ->fetchAll(PDO::FETCH_ASSOC);
            $planExclusions = $pdo->query('SELECT x.plan_id,p.datum,p.nazev FROM club_makeup_plan_exclusions x '
                . 'JOIN planovane_treninky p ON p.id=x.plan_id ORDER BY p.datum,p.id')
                ->fetchAll(PDO::FETCH_ASSOC);
            $offerNames = array_column($offers, 'name', 'id');
            ?>
<div class="row g-3 mt-2">
<div class="col-lg-6"><h3 class="h6">Uložené výjimky směru</h3><ul>
<?php foreach ($routeExceptions as $route): ?>
<li><?= makeupAdminH(($offerNames[$route['source_offer_id']] ?? '') . ' → '
    . ($offerNames[$route['target_offer_id']] ?? '')) ?>:
<?= (int) $route['allowed'] === 1 ? 'povoleno' : 'zakázáno' ?></li>
<?php endforeach; ?>
</ul></div>
<div class="col-lg-6"><h3 class="h6">Vyloučené termíny</h3><ul>
<?php foreach ($planExclusions as $excluded): ?>
<li>#<?= (int) $excluded['plan_id'] ?> ·
<?= makeupAdminH($excluded['datum'] . ' ' . $excluded['nazev']) ?></li>
<?php endforeach; ?>
</ul></div>
</div>
<?php
        endif;
echo('<h2 class="h5 mt-4">Lhůta pro omluvu</h2><p class="small text-muted">Po lhůtě lze nepřítomnost stále oznámit ' .

    'trenérovi, ale nevznikne nárok na náhradu. Nová pravidla jsou bezpečně vypnutá, dokud je zde nezapnete.</p>
');
?><div class="row g-3"><?php
foreach ($scopes as $key => $scope):$policy = $policies[$key] ?? null;
    ?><div class="col-lg-6"><form method="post" class="card card-body h-100"><?=
    csrf_field()
    ?><input type="hidden" name="action" value="policy"><input type="hidden" name="offer_id" value="<?=
    (int)$scope['id']
    ?>"><strong><?=
    makeupAdminH($scope['program_name'].
        ' / '.$scope['name'])
    ?></strong><span class="small text-muted mb-2"><?=
    makeupAdminH($scope['starts_on'].
        ' – '.$scope['ends_on'].' / '.$scope['team_name'])
    ?></span><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="enabled" id="enable-<?=
    makeupAdminH($key)
    ?>" <?=
    ($policy
        && (int)$policy['enabled'] === 1) ? 'checked' : ''
    ?>><label class="form-check-label" for="enable-<?=
    makeupAdminH($key)
    ?>">Povolit náhradní lekce</label></div><label class="form-label" for="hours-<?=
    makeupAdminH($key)
    ?>">Omluva nejpozději X hodin před tréninkem</label><input class="form-control mb-2" type="number" min="0" max="720" name="excuse_hours" id="hours-<?=
    makeupAdminH($key)
    ?>" value="<?=
    (int)($policy['excuse_hours']
        ?? 12)
    ?>" required><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="apply_future" id="future-<?=
    makeupAdminH($key)
    ?>" checked><label class="form-check-label" for="future-<?=
    makeupAdminH($key)
    ?>">Použít také na vypsané budoucí termíny</label></div><button class="btn btn-success mt-auto">Uložit pravidlo</button></form></div><?php
endforeach;
?></div>
<h2 class="h5 mt-5">Povolené náhrady mezi nabídkami</h2>
<p>Vlastní nabídka je povolená vždy. Další vazba platí jen ve směru zdroj → cíl; věk, platnost kreditu a kapacita se kontrolují při rezervaci.</p>
<form method="post" class="row g-2"><?=
csrf_field()
?><input type="hidden" name="action" value="route">
<?php
foreach (['source_offer_id' => 'Zdroj nároku','target_offer_id' => 'Cílová nabídka náhrady'] as $field
    => $label):
    ?><div class="col-md-5"><label class="form-label" for="<?=
    makeupAdminH($field)
    ?>"><?=
    makeupAdminH($label)
    ?></label><select class="form-select" name="<?=
    makeupAdminH($field)
    ?>" id="<?=
    makeupAdminH($field)
    ?>" required><option value="">Vyberte nabídku</option><?php
foreach ($offers as $o):
    ?><option value="<?=
    (int)$o['id']
    ?>"><?=
    makeupAdminH($o['program_name'].' / '.$o['name'].
        ' · '.$o['starts_on'].' – '.$o['ends_on'].' #'.$o['id'])
    ?></option><?php
endforeach;
    ?></select></div><?php
endforeach;
?>
<?php
echo('<div class="col-12"><label><input type="checkbox" name="allowed" checked> Povolit (zrušení zaškrtnutí vazbu ' .

    'odebere)</label> <button class="btn btn-outline-primary">Uložit vazbu</button></div></form>
');
?><ul class="mt-3"><?php
$offerNames = array_column($offers, 'name', 'id');
foreach ($pdo->query('SELECT source_offer_id,target_offer_id FROM club_makeup_offer_routes ORDER BY source_offer_id,target_offer_id')->fetchAll(PDO::FETCH_ASSOC) as $route):
    ?><li><?=
    makeupAdminH(($offerNames[$route['source_offer_id']] ?? '').' #'.$route['source_offer_id'].
            ' → '.($offerNames[$route['target_offer_id']] ?? '').' #'.$route['target_offer_id'])
    ?></li><?php
endforeach;
?></ul>
<h2 class="h5 mt-5" id="obdobi">Celoroční a pololetní nabídky</h2>
<?php
echo('<p class="small text-muted">Nezařazená nabídka není automaticky jednorázová. Koordinátor musí potvrdit typ ' .

        'každé nabídky; u prvního pololetí nejprve zařadí druhé pololetí a pak vybere jeho konkrétní nabídku. ' .

        'Převedený nárok lze využít až po potvrzeném navazujícím nákupu. Typ se nikdy neodvozuje z názvu ani délky ' .

        'nabídky.</p>
');
?><div class="alert <?=
array_sum($reviewCounts) > 0 ? 'alert-warning' : 'alert-success'
?>">
    K ruční kontrole: <strong><?=
$reviewCounts['unclassified']
?> nezařazených nabídek</strong>.
    <?php
if (array_sum($reviewCounts) > 0):
    ?>Před ověřováním převodu kreditů zařazení potvrďte.<?php
endif;
?>
</div>
<nav class="d-flex gap-2 mb-3" aria-label="Filtr nabídek"><a class="btn btn-sm <?=
$reviewOnly ? 'btn-outline-primary' : 'btn-primary'
?>" href="?filter=all#obdobi">Všechny nabídky</a><a class="btn btn-sm <?=
$reviewOnly
        ? 'btn-primary' : 'btn-outline-primary'
?>" href="?filter=review#obdobi">Jen k prověření (<?=
array_sum($reviewCounts)
?>)</a></nav>
<?php
echo('<div class="table-responsive"><table class="table table-sm ' .
        'align-middle"><thead><tr><th>Nabídka</th><th>Období</th><th>Stav</th><th>Zařazení pro ' .
        'náhrady</th></tr></thead><tbody>
');
?><?php
foreach ($offers as $offer):$period = $periods[(int)$offer['id']] ?? null;
    $review = clubMakeupOfferReviewStatus($period);
    if ($reviewOnly && $review === 'classified') {
        continue;
    }
    ?><tr>
    <td><?=
    makeupAdminH($offer['program_name'].' / '.$offer['name'])
    ?><div class="small text-muted"><?=
    makeupAdminH($offer['team_name'])
    ?> · ID <?=
    (int)$offer['id']
    ?></div></td>
    <td><?=
    makeupAdminH($offer['starts_on'].' – '.$offer['ends_on'])
    ?></td>
    <td><?php
if ($review === 'unclassified'):
    ?><span class="badge text-bg-warning">Nezařazeno</span><?php else:
        ?><span class="badge text-bg-success">Potvrzeno</span><?php
    endif;
    ?></td>
    <td><form method="post" class="d-flex flex-wrap gap-2 align-items-center"><?=
    csrf_field()
    ?><input type="hidden" name="action" value="period"><input type="hidden" name="offer_id" value="<?=
    (int)$offer['id']
    ?>"><select class="form-select form-select-sm" name="period_kind" style="max-width:180px" aria-label="Druh období" required><option value="" <?=
    $period
                === null ? 'selected' : ''
    ?> disabled>Vyberte období</option><option value="single" <?=
    ($period['period_kind'] ?? '')
                === 'single' ? 'selected' : ''
    ?>>Jednorázové / jiné</option><option value="annual" <?=
    ($period['period_kind'] ?? '') ===
                'annual' ? 'selected' : ''
    ?>>Celoroční</option><option value="first" <?=
    ($period['period_kind'] ?? '') === 'first' ? 'selected'
                : ''
    ?>>První pololetí</option><option value="second" <?=
    ($period['period_kind'] ?? '') === 'second' ? 'selected' : ''
    ?><?php
    echo('>Druhé pololetí</option></select><select class="form-select form-select-sm" name="next_offer_id" ' .

                'style="max-width:260px" aria-label="Navazující nabídka"><option value="">Bez navazující nabídky</option>');
    ?><?php
foreach ($offers as $next):if ((int)$next['id'] === (int)$offer['id'] || (int)$next['program_id'] !== (int)$offer['program_id']
                || (int)$next['team_id'] !== (int)$offer['team_id'] || (string)$next['starts_on'] <= (string)$offer['ends_on']) {
    continue;
}
    ?><option value="<?=
    (int)$next['id']
    ?>" <?=
    ((int)($period['next_offer_id'] ?? 0) === (int)$next['id']) ? 'selected' :
                ''
    ?>><?=
    makeupAdminH($next['name'].' · ID '.$next['id'])
    ?></option><?php
endforeach;
    ?></select><button class="btn btn-outline-primary btn-sm">Uložit</button></form></td>
</tr><?php
endforeach;
?></tbody></table></div>
<?php if ($autoSettings !== null):
    $makeupCounts = $pdo->query('SELECT status,COUNT(*) AS total FROM club_makeup_credits GROUP BY status')
        ->fetchAll(PDO::FETCH_ASSOC);
    $upcoming = $pdo->prepare("SELECT DISTINCT p.id,p.nazev,p.datum,p.cas_od,p.stav FROM planovane_treninky p "
        . "JOIN club_program_offers o ON o.starts_on<=p.datum AND o.ends_on>=p.datum AND o.status='active' "
        . 'JOIN club_makeup_offer_policies m ON m.offer_id=o.id '
        . 'JOIN club_makeup_policies q ON q.id=m.policy_id AND q.enabled=1 '
        . "WHERE p.stav='planovany' AND p.datum>=? AND o.birth_year_from IS NOT NULL "
        . 'AND o.birth_year_to IS NOT NULL ORDER BY p.datum,p.cas_od,p.id LIMIT 40');
    $upcoming->execute([clubMakeupNow()->format('Y-m-d')]);
    $configEvents = $pdo->query('SELECT scope_type,scope_id,actor_trainer_id,created_at '
        . 'FROM club_makeup_config_events ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
    ?>
<h2 class="h5 mt-5">Přehled nároků a termínů</h2>
<p class="small text-muted">Počty zahrnují historické nároky. Termíny níže jsou kandidáti ke kontrole; skutečná volná místa se vyhodnocují pro konkrétní dítě při rezervaci.</p>
<div class="d-flex flex-wrap gap-2 mb-3">
<?php foreach ($makeupCounts as $count): ?>
<span class="badge text-bg-secondary">
<?= makeupAdminH($count['status']) ?>: <?= (int) $count['total'] ?>
</span>
<?php endforeach; ?>
</div>
<div class="table-responsive">
<table class="table table-sm">
<thead>
<tr>
<th>ID</th>
<th>Termín</th>
<th>Název</th>
</tr>
</thead>
<tbody>
<?php foreach ($upcoming->fetchAll(PDO::FETCH_ASSOC) as $candidate): ?>
<tr>
<td>
<?= (int) $candidate['id'] ?>
</td>
<td>
<?= makeupAdminH($candidate['datum'] . ' ' . substr((string) $candidate['cas_od'], 0, 5)) ?>
</td>
<td>
<?= makeupAdminH($candidate['nazev']) ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<h2 class="h5 mt-5">Poslední změny pravidel</h2>
<div class="table-responsive">
<table class="table table-sm">
<thead>
<tr>
<th>Čas</th>
<th>Oblast</th>
<th>ID</th>
<th>Změnil</th>
</tr>
</thead>
<tbody>
<?php foreach ($configEvents as $event): ?>
<tr>
<td>
<?= makeupAdminH($event['created_at']) ?>
</td>
<td>
<?= makeupAdminH($event['scope_type']) ?>
</td>
<td>
<?= (int) $event['scope_id'] ?>
</td>
<td>
<?= (int) $event['actor_trainer_id'] ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php
    $historyPage = max(1, min(100000, (int) ($_GET['history_page'] ?? 1)));
    $historyOffset = ($historyPage - 1) * 50;
    $historyQuery = $pdo->query('SELECT e.id,e.created_at,e.action,c.id AS credit_id,c.status AS credit_status, '
        . 'sp.jmeno,sp.prijmeni,src.datum AS source_date,src.nazev AS source_name, '
        . 'target.datum AS target_date,target.nazev AS target_name,r.status AS reservation_status '
        . 'FROM club_makeup_events e JOIN club_makeup_credits c ON c.id=e.credit_id '
        . 'JOIN sportovci sp ON sp.id=c.sportovec_id '
        . 'JOIN planovane_treninky src ON src.id=c.source_plan_id '
        . 'LEFT JOIN club_makeup_reservations r ON r.id=e.reservation_id '
        . 'LEFT JOIN planovane_treninky target ON target.id=r.target_plan_id '
        . 'ORDER BY e.id DESC LIMIT 51 OFFSET ' . $historyOffset);
    $historyRows = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
    $hasNextHistory = count($historyRows) > 50;
    $historyRows = array_slice($historyRows, 0, 50);
    ?>
<h2 class="h5 mt-5" id="historie">Historie nároků a rezervací</h2>
<p class="small text-muted">Úplný deník změn je stránkovaný od nejnovější události.
Stav nároku a rezervace v tabulce je jejich současný stav.</p>
<div class="table-responsive"><table class="table table-sm">
<thead><tr><th>Čas</th><th>Dítě</th><th>Událost</th><th>Zdroj</th><th>Cíl</th><th>Stav</th></tr></thead>
<tbody>
<?php foreach ($historyRows as $history): ?>
<tr>
<td><?= makeupAdminH($history['created_at']) ?></td>
<td><?= makeupAdminH($history['prijmeni'] . ' ' . $history['jmeno']) ?></td>
<td><?= makeupAdminH($history['action']) ?> #<?= (int) $history['credit_id'] ?></td>
<td><?= makeupAdminH($history['source_date'] . ' ' . $history['source_name']) ?></td>
<td><?= makeupAdminH(($history['target_date'] ?? '') . ' ' . ($history['target_name'] ?? '')) ?></td>
<td><?= makeupAdminH($history['credit_status']) ?> / <?= makeupAdminH($history['reservation_status'] ?? '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<nav class="d-flex gap-2" aria-label="Stránky historie náhrad">
<?php if ($historyPage > 1): ?>
<a class="btn btn-sm btn-outline-secondary" href="?history_page=<?= $historyPage - 1 ?>#historie">Novější</a>
<?php endif; ?>
<?php if ($hasNextHistory): ?>
<a class="btn btn-sm btn-outline-secondary" href="?history_page=<?= $historyPage + 1 ?>#historie">Starší</a>
<?php endif; ?>
</nav>
<?php endif; ?>
<?php
endif;
?></main></body></html>
