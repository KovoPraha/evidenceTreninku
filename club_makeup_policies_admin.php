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
if (clubMakeupReady($pdo)) {
    $offers = $pdo->query(('SELECT o.id,o.program_id,o.team_id,o.name,o.starts_on,o.ends_on,p.name AS program_name,t.name AS team_name '
    .
    'FROM club_program_offers o JOIN club_programs p ON p.id=o.program_id JOIN club_teams t ON t.id=o.team_id ' .

    'ORDER BY o.starts_on DESC,p.name,o.starts_on,o.id'))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdo->query('SELECT p.*,m.offer_id FROM club_makeup_policies p JOIN club_makeup_offer_policies m ON m.policy_id=p.id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $policies[(int)$row['offer_id']] = $row;
    }
    foreach ($pdo->query('SELECT * FROM club_makeup_offer_periods')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $periods[(int)$row['offer_id']] = $row;
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
echo('<p class="text-muted">Každá nabídka má vlastní pravidlo. Náhrady mezi různými nabídkami povolíte výslovně ' .

    'níže. Náhradní termíny zapíná trenér jednotlivě v přípravě tréninku.</p>
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
<?php
endif;
?></main></body></html>
