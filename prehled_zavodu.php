<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$isAdmin = canAccess('sprava_zavodu');
$canAdd  = canAccess('formular_zavod');

// ── Filtry ────────────────────────────────────────────────────────────────
$groupId  = isset($_GET['skupina_id'])    ? (int)$_GET['skupina_id']    : 0;
$subId    = isset($_GET['podskupina_id']) ? (int)$_GET['podskupina_id'] : 0;
$filterKat = in_array($_GET['kategorie'] ?? '', ['silnice','draha','mtb']) ? $_GET['kategorie'] : '';
$allowed  = [1, 2, 6, 12];
$months   = isset($_GET['months']) && in_array((int)$_GET['months'], $allowed) ? (int)$_GET['months'] : 1;
$dateFrom = date('Y-m-d', strtotime("-{$months} months"));

// ── Pomocná data ──────────────────────────────────────────────────────────
$groups = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi')->fetchAll(PDO::FETCH_ASSOC);
$subs   = [];
if ($groupId) {
    $stmt = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi');
    $stmt->execute([$groupId]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

$kategorieMeta = [
    'silnice' => ['label'=>'Silnice', 'color'=>'success', 'icon'=>'bi-bicycle'],
    'draha'   => ['label'=>'Dráha',   'color'=>'primary', 'icon'=>'bi-stopwatch'],
    'mtb'     => ['label'=>'MTB',     'color'=>'warning',  'icon'=>'bi-tree'],
];

// ── Sestavení dotazu ──────────────────────────────────────────────────────
$conds  = ['z.datum >= ?'];
$params = [$dateFrom];
if ($subId)    { $conds[] = 'zp.podskupina_id = ?'; $params[] = $subId; }
elseif ($groupId) { $conds[] = 'zs.skupina_id = ?'; $params[] = $groupId; }
if ($filterKat)   { $conds[] = 'z.kategorie = ?'; $params[] = $filterKat; }
$where = 'WHERE ' . implode(' AND ', $conds);

$sql = "
    SELECT z.id, z.datum, z.kategorie, z.popis AS napln, z.poznamka,
           COUNT(DISTINCT zsp.sportovec_id) AS pocet_ucastniku,
           GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
    FROM zavody z
    LEFT JOIN zavod_trener zt  ON z.id = zt.zavod_id
    LEFT JOIN treneri tr       ON zt.trener_id = tr.id
    LEFT JOIN zavod_skupina zs ON z.id = zs.zavod_id
    LEFT JOIN zavod_podskupina zp ON z.id = zp.zavod_id
    LEFT JOIN zavod_sportovec zsp ON z.id = zsp.zavod_id
    $where
    GROUP BY z.id
    ORDER BY z.datum DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$zavody = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Statistiky ────────────────────────────────────────────────────────────
$totalZavodu    = count($zavody);
$uniqueTreneri  = [];
$nejblizsiDatum = null;
foreach ($zavody as $z) {
    foreach (array_filter(array_map('trim', explode(',', $z['trenere'] ?? ''))) as $jmeno) {
        $uniqueTreneri[$jmeno] = true;
    }
    if ($z['datum'] >= date('Y-m-d') && ($nejblizsiDatum === null || $z['datum'] < $nejblizsiDatum)) {
        $nejblizsiDatum = $z['datum'];
    }
}
$pocetTreneru = count($uniqueTreneri);
$datumOd = $totalZavodu > 0 ? end($zavody)['datum']   : null;
$datumDo = $totalZavodu > 0 ? reset($zavody)['datum'] : null;

// Barvy pro trenéry
$trenerColors   = ['primary','success','danger','warning','info','secondary','dark'];
$trenerColorMap = [];
$colorIdx = 0;
foreach (array_keys($uniqueTreneri) as $jmeno) {
    $trenerColorMap[$jmeno] = $trenerColors[$colorIdx++ % count($trenerColors)];
}

$monthLabels = [1=>'1 měsíc', 2=>'2 měsíce', 6=>'6 měsíců', 12=>'12 měsíců'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přehled závodů</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
        tr.table-warning td { background-color: #fff8e1 !important; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4 mb-5">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Přehled závodů</h1>
        <div class="d-flex gap-2">
            <?php if ($canAdd): ?>
            <a href="formular_zavod.php" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i>Nový závod
            </a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="sprava_zavodu.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i>Správa
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filtry ─────────────────────────────────────────────────────── -->
    <form method="GET" class="card p-3 mb-4 border-0 shadow-sm">
        <div class="row gy-2 gx-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-semibold"><i class="bi bi-people me-1"></i>Skupina</label>
                <select id="skupina" name="skupina_id" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= $groupId == $g['id'] ? 'selected' : '' ?>>
                            <?= h($g['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold"><i class="bi bi-diagram-3 me-1"></i>Podskupina</label>
                <select id="podskupina" name="podskupina_id" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($subs as $ps): ?>
                        <option value="<?= (int)$ps['id'] ?>" <?= $subId == $ps['id'] ? 'selected' : '' ?>>
                            <?= h($ps['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold"><i class="bi bi-bicycle me-1"></i>Kategorie</label>
                <select name="kategorie" class="form-select">
                    <option value="">-- vše --</option>
                    <?php foreach ($kategorieMeta as $val => $meta): ?>
                        <option value="<?= $val ?>" <?= $filterKat === $val ? 'selected' : '' ?>>
                            <?= $meta['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold"><i class="bi bi-calendar-range me-1"></i>Interval</label>
                <select name="months" class="form-select">
                    <?php foreach ($allowed as $m): ?>
                        <option value="<?= $m ?>" <?= $months == $m ? 'selected' : '' ?>><?= $monthLabels[$m] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrovat
                </button>
                <a class="btn btn-outline-secondary" href="prehled_zavodu.php" title="Zrušit filtr">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>

    <!-- ── Stats karty ───────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-warning"><?= $totalZavodu ?></div>
                    <div class="text-muted small"><i class="bi bi-trophy me-1"></i>Závodů celkem</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success"><?= $pocetTreneru ?></div>
                    <div class="text-muted small"><i class="bi bi-person-badge me-1"></i>Trenérů</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-info">
                        <?= $datumOd ? date('d.m.', strtotime($datumOd)) . ' – ' . date('d.m.Y', strtotime($datumDo)) : '—' ?>
                    </div>
                    <div class="text-muted small"><i class="bi bi-calendar-range me-1"></i>Rozsah dat</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-5 fw-bold text-warning">
                        <?= $nejblizsiDatum ? date('d.m.Y', strtotime($nejblizsiDatum)) : '—' ?>
                    </div>
                    <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i>Nejbližší závod</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($zavody)): ?>
        <div class="alert alert-info border-0 shadow-sm">
            <i class="bi bi-info-circle me-2"></i>Žádné závody pro zvolené filtry.
        </div>
    <?php else: ?>

    <div class="table-responsive shadow-sm rounded">
        <table class="table table-hover align-middle mb-0 bg-white">
            <thead class="table-dark">
                <tr>
                    <th style="width:110px;"><i class="bi bi-calendar3 me-1"></i>Datum</th>
                    <th style="width:90px;">Den</th>
                    <th style="width:110px;">Kategorie</th>
                    <th><i class="bi bi-card-text me-1"></i>Popis</th>
                    <th style="width:60px;" class="text-center">Účastníci</th>
                    <th><i class="bi bi-person-badge me-1"></i>Trenéři</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zavody as $z):
                $dt        = new DateTime($z['datum']);
                $dayName   = $czDays[$dt->format('l')] ?? '';
                $isWeekend = in_array($dt->format('N'), ['6','7']);
                $isFuture  = $z['datum'] >= date('Y-m-d');
                $kat       = $z['kategorie'] ?? 'silnice';
                $kmeta     = $kategorieMeta[$kat] ?? $kategorieMeta['silnice'];
                $treneriList = array_filter(array_map('trim', explode(',', $z['trenere'] ?? '')));
            ?>
            <tr class="<?= $isFuture ? 'table-warning' : '' ?>">
                <td class="fw-semibold"><?= date('d.m.Y', strtotime($z['datum'])) ?></td>
                <td class="<?= $isWeekend ? 'fw-bold text-danger' : 'text-muted' ?>"><?= h($dayName) ?></td>
                <td>
                    <span class="badge bg-<?= $kmeta['color'] ?>">
                        <i class="bi <?= $kmeta['icon'] ?> me-1"></i><?= $kmeta['label'] ?>
                    </span>
                </td>
                <td><?= nl2br(h($z['napln'])) ?></td>
                <td class="text-center">
                    <?php if ($z['pocet_ucastniku'] > 0): ?>
                        <span class="badge bg-secondary"><?= (int)$z['pocet_ucastniku'] ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach ($treneriList as $jmeno):
                        $color = $trenerColorMap[$jmeno] ?? 'secondary';
                    ?>
                        <span class="badge bg-<?= $color ?> me-1"><?= h($jmeno) ?></span>
                    <?php endforeach; ?>
                </td>
                <td class="text-end">
                    <a href="zavod_detail.php?id=<?= (int)$z['id'] ?>" class="btn btn-outline-warning btn-sm" title="Detail závodu">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="edit_zavod_form.php?id=<?= (int)$z['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Upravit" aria-label="Upravit závod">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pocetTreneru > 1): ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <small class="text-muted me-1"><i class="bi bi-palette me-1"></i>Trenéři:</small>
        <?php foreach ($trenerColorMap as $jmeno => $color): ?>
            <span class="badge bg-<?= $color ?>"><?= h($jmeno) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('skupina').addEventListener('change', function () {
    fetch('nacti_podskupiny.php?skupina_id=' + this.value)
        .then(r => r.json())
        .then(data => {
            let html = '<option value="">-- všechny --</option>';
            data.forEach(ps => {
                html += `<option value="${parseInt(ps.id)}">${ps.nazev.replace(/</g, '&lt;')}</option>`;
            });
            document.getElementById('podskupina').innerHTML = html;
        });
});
</script>
</body>
</html>
