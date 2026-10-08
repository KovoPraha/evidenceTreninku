<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/staff_workspaces.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

$definitions = staffPositionDefinitions();
$activeCode = staffActivePosition();
if ($activeCode === '' || !isset($definitions[$activeCode])) {
    http_response_code(403);
    exit('Účet nemá přiřazenou pracovní pozici. Obraťte se na správce systému.');
}
$active = $definitions[$activeCode];
$isLocal=defined('JE_LOKALNE')&&JE_LOKALNE===true;$activeGroups=staffPositionPrimaryMenuGroups($activeCode,$isLocal);$advancedGroups=staffPositionAdvancedMenuGroups($activeCode,$isLocal);
$available = staffAvailablePositions();
$trainerName = trim((string)($_SESSION['trener_jmeno'] ?? ''));
if ($trainerName === '') {
    $statement = $pdo->prepare('SELECT jmeno FROM treneri WHERE id=?');
    $statement->execute([(int)$_SESSION['trener_id']]);
    $trainerName = (string)$statement->fetchColumn();
}
$vehicleConflictCount = 0;
$todayPlans = [];
$overduePlanCount = 0;
$pendingLessonCount = 0;
try {
    $vehicleConflictCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM club_event_vehicle_reservations a "
        . "JOIN club_event_vehicle_reservations b ON b.vehicle_id=a.vehicle_id AND b.id>a.id "
        . "AND b.status='active' AND b.starts_at<a.ends_at AND b.ends_at>a.starts_at "
        . "WHERE a.status='active' AND a.ends_at>=CURRENT_TIMESTAMP"
    )->fetchColumn();
} catch (Throwable $exception) {
    error_log('pracovni_pozice vehicle conflicts: ' . $exception->getMessage());
}
try {
    $statement = $pdo->prepare(
        "SELECT p.id,p.nazev,p.cas_od,p.cas_do,p.kategorie,g.nazev AS skupina,s.nazev AS sportoviste "
        . "FROM planovane_treninky p LEFT JOIN skupiny g ON g.id=p.skupina_id "
        . "LEFT JOIN sportovist s ON s.id=p.sportoviste_id "
        . "WHERE p.trener_id=? AND p.datum=CURRENT_DATE AND p.stav='planovany' ORDER BY COALESCE(p.cas_od,'23:59'),p.id"
    );
    $statement->execute([(int)$_SESSION['trener_id']]);
    $todayPlans = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM planovane_treninky WHERE trener_id=? AND stav='planovany' "
        . "AND datum<CURRENT_DATE AND datum>=DATE_SUB(CURRENT_DATE,INTERVAL 14 DAY)"
    );
    $statement->execute([(int)$_SESSION['trener_id']]);
    $overduePlanCount = (int)$statement->fetchColumn();
} catch (Throwable $exception) {
    error_log('pracovni_pozice training overview: ' . $exception->getMessage());
}
try {
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM verejne_rezervace vr JOIN individualni_lekce il ON il.id=vr.lekce_id "
        . "WHERE vr.stav='ceka' AND il.trener_id=?"
    );
    $statement->execute([(int)$_SESSION['trener_id']]);
    $pendingLessonCount = (int)$statement->fetchColumn();
} catch (Throwable $exception) {
    error_log('pracovni_pozice lesson overview: ' . $exception->getMessage());
}
function staffDashboardH(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= staffDashboardH($active['label']) ?> – pracovní rozcestník</title>
    <link href="<?=staffDashboardH(appUiUrl('assets/vendor/bootstrap/bootstrap.min.css'))?>" rel="stylesheet">
    <?php appUiAssets(); ?>
    <style>
        body{background:#f3f5f8}.workspace-hero{background:linear-gradient(135deg,#173b67,#156b63);color:#fff;border-radius:1rem}
        .workspace-link{display:block;height:100%;color:inherit;text-decoration:none}.workspace-link .card{height:100%;border:0;transition:transform .12s,box-shadow .12s}
        .workspace-link:hover .card{transform:translateY(-2px);box-shadow:0 .5rem 1.2rem rgba(0,0,0,.12)!important}
        .workspace-icon{width:2.5rem;height:2.5rem;display:grid;place-items:center;border-radius:.7rem;background:#e9f2ff;color:#0d6efd;font-size:1.2rem}
    </style>
</head>
<body>
<?php include __DIR__ . '/hlavicka.php'; ?>
<main class="container py-4" style="max-width:1240px">
    <section class="workspace-hero p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="small text-white-50 mb-1">Moje práce · <?= staffDashboardH($trainerName) ?></div>
                <h1 class="h2 mb-2"><i class="bi bi-<?= staffDashboardH($active['icon']) ?> me-2"></i><?= staffDashboardH($active['label']) ?></h1>
                <p class="mb-0 text-white-50"><?= staffDashboardH($active['description']) ?></p>
            </div>
            <?php if (staffIsSuperadmin()): ?>
                <span class="badge text-bg-warning text-dark fs-6 align-self-start align-self-lg-center"><i class="bi bi-shield-lock me-1"></i>Superadmin · aktivní kontext</span>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= staffDashboardH($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= staffDashboardH($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if ($vehicleConflictCount > 0): ?>
        <div class="alert alert-danger border-3 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><strong><i class="bi bi-exclamation-octagon-fill me-2"></i>Kolize klubových vozidel: <?= $vehicleConflictCount ?></strong><div class="small">Překryv může být domluvený mimo systém, ale musí být zkontrolován.</div></div>
            <a class="btn btn-danger" href="<?= staffDashboardH(appUiUrl('club_calendar.php')) ?>">Otevřít klubový kalendář</a>
        </div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="today-work-title">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"><strong id="today-work-title"><i class="bi bi-sun me-2 text-warning"></i>Dnes a vyžaduje pozornost</strong><span class="small text-muted"><?=staffDashboardH((new DateTimeImmutable('today'))->format('j. n. Y'))?></span></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-7"><h2 class="h6">Dnešní tréninky</h2>
                    <?php if($todayPlans===[]):?><p class="text-muted small">Na dnešek nemáte naplánovaný žádný trénink.</p><?php else:?><div class="list-group list-group-flush"><?php foreach($todayPlans as$plan):?><a class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3" href="<?=staffDashboardH(appUiUrl('formular.php?plan_id='.(int)$plan['id']))?>"><div><strong><?=staffDashboardH($plan['nazev'] ?: 'Trénink')?></strong><div class="small text-muted"><?=staffDashboardH($plan['skupina'] ?: 'Bez skupiny')?><?=trim((string)$plan['sportoviste'])!==''?' · '.staffDashboardH($plan['sportoviste']):''?></div></div><span class="text-nowrap"><?=staffDashboardH(substr((string)$plan['cas_od'],0,5))?><?=trim((string)$plan['cas_do'])!==''?'–'.staffDashboardH(substr((string)$plan['cas_do'],0,5)):''?> <i class="bi bi-chevron-right ms-1"></i></span></a><?php endforeach;?></div><?php endif;?>
                    <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-primary btn-sm" href="<?=staffDashboardH(appUiUrl('formular.php'))?>"><i class="bi bi-plus-circle me-1"></i>Zadat trénink</a><a class="btn btn-outline-primary btn-sm" href="<?=staffDashboardH(appUiUrl('planovac.php'))?>"><i class="bi bi-calendar-week me-1"></i>Otevřít plánovač</a></div>
                </div>
                <div class="col-lg-5"><h2 class="h6">Úkoly</h2><div class="list-group">
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?=staffDashboardH(appUiUrl('planovac.php'))?>"><span><i class="bi bi-exclamation-triangle me-2 <?=$overduePlanCount>0?'text-danger':'text-success'?>"></i>Proběhlé tréninky bez evidence</span><span class="badge <?=$overduePlanCount>0?'text-bg-danger':'text-bg-success'?>"><?=$overduePlanCount?></span></a>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?=staffDashboardH(appUiUrl('individualni_lekce_sprava.php'))?>"><span><i class="bi bi-hourglass-split me-2 <?=$pendingLessonCount>0?'text-warning':'text-success'?>"></i>Rezervace čekající na potvrzení</span><span class="badge <?=$pendingLessonCount>0?'text-bg-warning':'text-bg-success'?>"><?=$pendingLessonCount?></span></a>
                </div></div>
            </div>
        </div>
    </section>

    <?php if (count($available) > 1): ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
            <div class="me-lg-auto">
                <div class="fw-semibold">Přepnout pracovní pozici</div>
                <div class="small text-muted">Menu se neslučují. Po přepnutí uvidíte pouze vybranou agendu.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($available as $code): if ($code === $activeCode) continue; $position = $definitions[$code]; ?>
                <form method="post" action="prepnout_pracovni_pozici.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="position" value="<?= staffDashboardH($code) ?>">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-<?= staffDashboardH($position['icon']) ?> me-1"></i><?= staffDashboardH($position['label']) ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php foreach ($activeGroups as $group): ?>
    <section class="mb-4" aria-labelledby="group-<?= staffDashboardH(md5((string)$group['label'])) ?>">
        <h2 class="h5 mb-3" id="group-<?= staffDashboardH(md5((string)$group['label'])) ?>"><i class="bi bi-<?= staffDashboardH($group['icon']) ?> me-2 text-primary"></i><?= staffDashboardH($group['label']) ?></h2>
        <div class="row g-3">
            <?php foreach ($group['items'] as $item): ?>
            <div class="col-md-6 col-xl-3">
                <a class="workspace-link" href="<?= staffDashboardH(appUiUrl((string)$item['route'])) ?>">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="workspace-icon mb-3"><i class="bi bi-<?= staffDashboardH($item['icon']) ?>"></i></div>
                            <h3 class="h6 mb-1"><?= staffDashboardH($item['label']) ?></h3>
                            <p class="small text-muted mb-0"><?= staffDashboardH($item['description']) ?></p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if($advancedGroups!==[]):?>
    <details class="card border-0 shadow-sm mt-4 mb-3">
        <summary class="card-header bg-white fw-semibold py-3" style="cursor:pointer"><i class="bi bi-tools me-2 text-secondary"></i>Pokročilé a jednorázové nástroje</summary>
        <div class="card-body"><p class="small text-muted">Tyto nástroje nejsou součástí běžné práce. Použijte je pouze pro výjimečnou opravu, jednorázový převod dat nebo systémové nastavení.</p>
        <?php foreach($advancedGroups as$group):?><h2 class="h6 mt-3"><?=staffDashboardH($group['label'])?></h2><div class="d-flex flex-wrap gap-2"><?php foreach($group['items']as$item):?><a class="btn btn-sm btn-outline-secondary" href="<?=staffDashboardH(appUiUrl((string)$item['route']))?>"><i class="bi bi-<?=staffDashboardH($item['icon'])?> me-1"></i><?=staffDashboardH($item['label'])?></a><?php endforeach;?></div><?php endforeach;?>
        </div>
    </details>
    <?php endif;?>
</main>
</body>
</html>
