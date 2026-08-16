<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId  = (int)$_SESSION['trener_id'];
$isAdmin = canAccess('prehled_trenera');

// ── Filtry ──────────────────────────────────────────────────────────────────
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
[$year, $mon] = explode('-', $month);

$skupinaId = isset($_GET['skupina_id']) && ctype_digit((string)$_GET['skupina_id'])
    ? (int)$_GET['skupina_id']
    : 0;

// ── Skupiny pro filtr ────────────────────────────────────────────────────────
try {
    $skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $skupiny = [];
}

// ── Načtení tréninků ─────────────────────────────────────────────────────────
$sql = 'SELECT t.id, t.datum, t.delka, t.kategorie, t.napln, t.poznamka,
               (SELECT COUNT(*) FROM trenink_sportovec ts2 WHERE ts2.trenink_id = t.id) AS pocet_sportovcu,
               (SELECT COUNT(*) FROM mereni m WHERE m.trenink_id = t.id) AS pocet_mereni
        FROM trenink_trener tt
        JOIN treninky t ON tt.trenink_id = t.id';

$params = [':uid' => $userId, ':year' => (int)$year, ':mon' => (int)$mon];
$where  = ['tt.trener_id = :uid', 'YEAR(t.datum) = :year', 'MONTH(t.datum) = :mon'];

if ($skupinaId > 0) {
    $sql .= ' JOIN trenink_skupina ts ON ts.trenink_id = t.id';
    $where[]              = 'ts.skupina_id = :sid';
    $params[':sid']       = $skupinaId;
}

$sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY t.datum DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('prehled_trenera.php: ' . $e->getMessage());
    $treninky = [];
}

// ── Souhrn ───────────────────────────────────────────────────────────────────
$celkemHodin      = 0.0;
$celkemSportovcu  = 0;
foreach ($treninky as $t) {
    $celkemHodin     += (float)($t['delka'] ?? 0);
    $celkemSportovcu += (int)($t['pocet_sportovcu'] ?? 0);
}
$pocetTreninku   = count($treninky);
$prumernaDobra   = $pocetTreninku > 0 ? $celkemHodin / $pocetTreninku : 0;

// Unikátní sportovci (přes COUNT je záměrně součet, ale pro unikátní potřebujeme jiný dotaz)
$unikatniSportovci = 0;
if ($pocetTreninku > 0) {
    try {
        $ids = array_column($treninky, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        if ($skupinaId > 0) {
            $stmtUni = $pdo->prepare("SELECT COUNT(DISTINCT ts.sportovec_id) FROM trenink_sportovec ts WHERE ts.trenink_id IN ($in)");
        } else {
            $stmtUni = $pdo->prepare("SELECT COUNT(DISTINCT ts.sportovec_id) FROM trenink_sportovec ts WHERE ts.trenink_id IN ($in)");
        }
        $stmtUni->execute($ids);
        $unikatniSportovci = (int)$stmtUni->fetchColumn();
    } catch (PDOException $e) { /* ignore */ }
}

// ── Překlad dnů ──────────────────────────────────────────────────────────────
$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

// ── Kategorie — barvy a ikonky ───────────────────────────────────────────────
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',      'color'=>'primary',  'icon'=>'bi-stopwatch'],
    'cyklokros' => ['label'=>'Cyklokros',  'color'=>'orange',   'icon'=>'bi-snow'],
    'posilovna' => ['label'=>'Posilovna',  'color'=>'danger',   'icon'=>'bi-person-arms-up'],
    'atletika'  => ['label'=>'Atletika',   'color'=>'info',     'icon'=>'bi-lightning'],
    'cviceni'   => ['label'=>'Cvičení',    'color'=>'secondary','icon'=>'bi-heart-pulse'],
    'plavani'   => ['label'=>'Plavání',    'color'=>'cyan',     'icon'=>'bi-water'],
];

// Pro export (jen trenéra)
$exportUrl = "export_xls.php?mesic={$month}";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přehled tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .badge-silnice   { background-color: #198754; color:#fff; }
        .badge-mtb       { background-color: #ffc107; color:#000; }
        .badge-draha     { background-color: #0d6efd; color:#fff; }
        .badge-cyklokros { background-color: #fd7e14; color:#fff; }
        .badge-posilovna { background-color: #dc3545; color:#fff; }
        .badge-atletika  { background-color: #0dcaf0; color:#000; }
        .badge-cviceni   { background-color: #6c757d; color:#fff; }
        .badge-plavani   { background-color: #087990; color:#fff; }

        .stat-card { border-radius: .75rem; }
        .napln-preview { max-width: 320px; white-space: pre-wrap; word-break: break-word; font-size: .85rem; }
        @media (max-width: 768px) {
            .table-responsive-stack td,
            .table-responsive-stack th { display: block; }
        }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container-fluid mt-4 mb-5 px-3 px-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-calendar-range me-2"></i>Přehled tréninků</h1>
        <a href="<?= h($exportUrl) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export XLSX
        </a>
    </div>

    <!-- Filtr -->
    <form method="GET" class="card shadow-sm mb-4 p-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="month" class="form-label small mb-1"><i class="bi bi-calendar3 me-1"></i>Měsíc</label>
                <input type="month" id="month" name="month"
                       value="<?= h($month) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-sm-auto" style="min-width:200px;">
                <label for="skupina_id" class="form-label small mb-1"><i class="bi bi-people me-1"></i>Skupina</label>
                <select id="skupina_id" name="skupina_id" class="form-select form-select-sm">
                    <option value="0">Všechny skupiny</option>
                    <?php foreach ($skupiny as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                            <?= $skupinaId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= h($s['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Zobrazit
                </button>
            </div>
        </div>
    </form>

    <?php if ($pocetTreninku > 0): ?>
    <!-- Souhrn stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-primary"><?= $pocetTreninku ?></div>
                    <div class="text-muted small"><i class="bi bi-calendar-check me-1"></i>Tréninků</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success"><?= number_format($celkemHodin, 1) ?></div>
                    <div class="text-muted small"><i class="bi bi-clock me-1"></i>Celkem hodin</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-warning"><?= number_format($prumernaDobra, 1) ?></div>
                    <div class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Průměrná délka (h)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-info"><?= $unikatniSportovci ?></div>
                    <div class="text-muted small"><i class="bi bi-people me-1"></i>Unikátních sportovců</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabulka tréninků -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Datum</th>
                            <th>Den</th>
                            <th>Kategorie</th>
                            <th>Délka (h)</th>
                            <th>Náplň</th>
                            <th>Poznámka</th>
                            <th class="text-center">Sportovci</th>
                            <th class="text-center">Měření</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($treninky as $t):
                        try { $dayEng = (new DateTime($t['datum']))->format('l'); }
                        catch (Exception $e) { $dayEng = ''; }
                        $dayCz   = $czDays[$dayEng] ?? $dayEng;
                        $kat     = (string)($t['kategorie'] ?? '');
                        $meta    = $kategorieMeta[$kat] ?? null;
                        $trId    = (int)$t['id'];
                    ?>
                        <tr>
                            <td class="ps-3 text-nowrap fw-semibold"><?= h($t['datum']) ?></td>
                            <td class="text-muted small"><?= h($dayCz) ?></td>
                            <td>
                                <?php if ($meta): ?>
                                    <span class="badge badge-<?= h($kat) ?>">
                                        <i class="bi <?= h($meta['icon']) ?> me-1"></i><?= h($meta['label']) ?>
                                    </span>
                                <?php elseif ($kat !== ''): ?>
                                    <span class="badge bg-light text-dark border"><?= h($kat) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($t['delka'])): ?>
                                    <span class="badge bg-secondary"><?= h($t['delka']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $napln = trim((string)($t['napln'] ?? ''));
                                if ($napln !== ''):
                                    $preview = mb_strlen($napln) > 120
                                        ? mb_substr($napln, 0, 120) . '…'
                                        : $napln;
                                ?>
                                    <span class="napln-preview" title="<?= h($napln) ?>"><?= h($preview) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $poz = trim((string)($t['poznamka'] ?? ''));
                                if ($poz !== ''):
                                    $pPrev = mb_strlen($poz) > 80 ? mb_substr($poz, 0, 80) . '…' : $poz;
                                ?>
                                    <span class="text-muted small" title="<?= h($poz) ?>"><?= h($pPrev) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$t['pocet_sportovcu'] > 0): ?>
                                    <span class="badge bg-success"><?= (int)$t['pocet_sportovcu'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$t['pocet_mereni'] > 0): ?>
                                    <span class="badge bg-info text-dark"><?= (int)$t['pocet_mereni'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end text-nowrap">
                                <a href="edit_trenink.php?id=<?= $trId ?>" class="btn btn-sm btn-outline-secondary" title="Upravit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legenda kategorií -->
    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
        <small class="text-muted me-1">Legenda:</small>
        <?php foreach ($kategorieMeta as $key => $m): ?>
            <span class="badge badge-<?= h($key) ?>">
                <i class="bi <?= h($m['icon']) ?> me-1"></i><?= h($m['label']) ?>
            </span>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>Pro zvolený měsíc<?= $skupinaId > 0 ? ' a skupinu' : '' ?> nejsou žádné tréninky.
        </div>
    <?php endif; ?>
</div>


</body>
</html>
