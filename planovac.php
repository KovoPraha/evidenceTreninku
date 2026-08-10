<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('planovac')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId = (int)$_SESSION['trener_id'];

// ── Storno / zrušení plánu ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'zrusit') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $zrId    = (int)($_POST['plan_id'] ?? 0);
        $zrSerie = !empty($_POST['zrusit_serii']);
        if ($zrSerie) {
            // Zjistit serie_id zrušeného plánu a zrušit celou sérii
            $stSerie = $pdo->prepare("SELECT serie_id, trener_id FROM planovane_treninky WHERE id=?");
            $stSerie->execute([$zrId]);
            $rowSerie = $stSerie->fetch(PDO::FETCH_ASSOC);
            if ($rowSerie && $rowSerie['serie_id'] && (roleAtLeast('hlavni') || (int)$rowSerie['trener_id'] === $trenerId)) {
                $pdo->prepare("UPDATE planovane_treninky SET stav='zruseny' WHERE serie_id=? AND stav='planovany'")
                    ->execute([$rowSerie['serie_id']]);
                $_SESSION['flash_success'] = 'Celá série plánovaných tréninků zrušena.';
            }
        } else {
            $cond   = roleAtLeast('hlavni') ? 'id=?' : 'id=? AND trener_id=?';
            $params = roleAtLeast('hlavni') ? [$zrId] : [$zrId, $trenerId];
            $pdo->prepare("UPDATE planovane_treninky SET stav='zruseny' WHERE {$cond} AND stav='planovany'")
                ->execute($params);
            $_SESSION['flash_success'] = 'Plánovaný trénink zrušen.';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

// ── Kopírování týdne ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kopirovat_tyden') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }

    $kopOd = $_POST['kopirovat_od'] ?? '';
    $kopDo = $_POST['kopirovat_do'] ?? '';
    $kopJenMoje = !empty($_POST['kopirovat_jen_moje']);
    $kopTrener  = (int)($_POST['kopirovat_trener_id'] ?? 0);
    $kopSkupina = (int)($_POST['kopirovat_skupina_id'] ?? 0);

    // Řadový trenér smí kopírovat jen vlastní plány (nesmí klonovat cizí týden)
    if (!roleAtLeast('hlavni')) {
        $kopJenMoje = true;
        $kopTrener  = 0;
    }

    // Načíst plány z daného týdne
    $kWhere  = ["pt.datum BETWEEN ? AND ?", "pt.stav = 'planovany'"];
    $kParams = [$kopOd, $kopDo];
    if ($kopJenMoje) {
        $kWhere[] = 'pt.trener_id = ?'; $kParams[] = $trenerId;
    } elseif ($kopTrener) {
        $kWhere[] = 'pt.trener_id = ?'; $kParams[] = $kopTrener;
    }
    if ($kopSkupina) { $kWhere[] = 'pt.skupina_id = ?'; $kParams[] = $kopSkupina; }

    $stKop = $pdo->prepare("
        SELECT pt.*
        FROM planovane_treninky pt
        WHERE " . implode(' AND ', $kWhere) . "
        ORDER BY pt.datum, pt.cas_od
    ");
    $stKop->execute($kParams);
    $zdrojePlany = $stKop->fetchAll(PDO::FETCH_ASSOC);

    $pocet = 0;
    $pdo->beginTransaction();
    try {
        $stInsert = $pdo->prepare("
            INSERT INTO planovane_treninky
                (trener_id, nazev, kategorie, skupina_id, podskupina_id,
                 datum, cas_od, cas_do, sportoviste_id, popis, misto, stav)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,'planovany')
        ");
        $stInsertPs = $pdo->prepare("
            INSERT IGNORE INTO planovane_treninky_podskupiny (plan_id, podskupina_id) VALUES (?,?)
        ");
        $stZdrojPs = $pdo->prepare("
            SELECT podskupina_id FROM planovane_treninky_podskupiny WHERE plan_id = ?
        ");

        foreach ($zdrojePlany as $zp) {
            $noveDatum = (new DateTime($zp['datum']))->modify('+7 days')->format('Y-m-d');
            $stInsert->execute([
                $zp['trener_id'], $zp['nazev'], $zp['kategorie'],
                $zp['skupina_id'], $zp['podskupina_id'],
                $noveDatum, $zp['cas_od'], $zp['cas_do'],
                $zp['sportoviste_id'], $zp['popis'], $zp['misto'],
            ]);
            $novyId = (int)$pdo->lastInsertId();
            $pocet++;

            // Kopírovat podskupiny z junction tabulky
            $stZdrojPs->execute([$zp['id']]);
            foreach ($stZdrojPs->fetchAll(PDO::FETCH_COLUMN) as $psId) {
                $stInsertPs->execute([$novyId, $psId]);
            }
        }
        $pdo->commit();

        $cilTyden = (new DateTime($kopOd))->modify('+7 days')->format('j. n.');
        $cilTydenDo = (new DateTime($kopDo))->modify('+7 days')->format('j. n. Y');
        $_SESSION['flash_success'] = "Zkopírováno {$pocet} trénink" . ($pocet === 1 ? '' : ($pocet < 5 ? 'y' : 'ů'))
            . " na týden {$cilTyden}–{$cilTydenDo}.";

        // Přesměrovat na cílový týden
        $cilDatum = (new DateTime($kopOd))->modify('+7 days')->format('Y-m-d');
        $redirectParams = http_build_query([
            'datum'      => $cilDatum,
            'jen_moje'   => $kopJenMoje ? '1' : '0',
            'trener_id'  => $kopTrener  ?: '',
            'skupina_id' => $kopSkupina ?: '',
        ]);
        header('Location: planovac.php?' . $redirectParams); exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Chyba při kopírování: ' . $e->getMessage();
        header('Location: ' . $_SERVER['REQUEST_URI']); exit;
    }
}

// ── Datum + týden ─────────────────────────────────────────────────────────────
$datumParam = $_GET['datum'] ?? date('Y-m-d');
try { $datumBase = new DateTime($datumParam); } catch (Exception $e) { $datumBase = new DateTime(); }
$dow    = (int)$datumBase->format('N');
$monday = (clone $datumBase)->modify('-' . ($dow - 1) . ' days');
$sunday = (clone $monday)->modify('+6 days');

$dneTydne = [];
for ($i = 0; $i < 7; $i++) {
    $dneTydne[] = (clone $monday)->modify("+{$i} days");
}

$prevWeek = (clone $monday)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $monday)->modify('+7 days')->format('Y-m-d');
$today    = date('Y-m-d');

// ── Filtry ────────────────────────────────────────────────────────────────────
$filterSkupina = (int)($_GET['skupina_id'] ?? 0);
// Výchozí stav: zobrazovat jen moje tréninky (pokud URL neobsahuje jen_moje=0)
$filterJenMoje = isset($_GET['jen_moje']) ? ((int)$_GET['jen_moje'] === 1) : true;
$filterTrener  = (int)($_GET['trener_id'] ?? 0); // 0 = bez omezení (jen při jen_moje=0)

// ── Data ──────────────────────────────────────────────────────────────────────
$skupiny    = $pdo->query("SELECT id, nazev, hash FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
$skupinyIdx = array_column($skupiny, null, 'id'); // id => row
$treneri    = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
$sportovist = $pdo->query("SELECT id, nazev FROM sportovist WHERE aktivni=1")->fetchAll(PDO::FETCH_ASSOC);
$sportIdx   = array_column($sportovist, 'nazev', 'id');

// ── Plánované tréninky v týdnu ────────────────────────────────────────────────
$params = [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
$where  = ['pt.datum BETWEEN ? AND ?', "pt.stav != 'zruseny'"];
if ($filterSkupina) { $where[] = 'pt.skupina_id = ?'; $params[] = $filterSkupina; }
if ($filterJenMoje) {
    $where[] = 'pt.trener_id = ?'; $params[] = $trenerId;
} elseif ($filterTrener) {
    $where[] = 'pt.trener_id = ?'; $params[] = $filterTrener;
}

$stPlan = $pdo->prepare("
    SELECT pt.*, sk.nazev AS skupina_nazev,
           COALESCE(
               (SELECT GROUP_CONCAT(ps2.nazev ORDER BY ps2.poradi SEPARATOR ', ')
                FROM planovane_treninky_podskupiny ptp2
                JOIN podskupiny ps2 ON ps2.id = ptp2.podskupina_id
                WHERE ptp2.plan_id = pt.id),
               (SELECT ps3.nazev FROM podskupiny ps3 WHERE ps3.id = pt.podskupina_id)
           ) AS podskupiny_nazvy,
           t.jmeno AS trener_jmeno
    FROM planovane_treninky pt
    LEFT JOIN skupiny sk ON sk.id = pt.skupina_id
    LEFT JOIN treneri t  ON t.id  = pt.trener_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY pt.datum, pt.cas_od, pt.nazev
");
$stPlan->execute($params);
$plany = $stPlan->fetchAll(PDO::FETCH_ASSOC);

// Indexovat dle data
$planyDne = [];
foreach ($plany as $p) { $planyDne[$p['datum']][] = $p; }

// ── Stats (plánováno / evidováno / čeká) ─────────────────────────────────────
$statsWhere  = ['pt.datum BETWEEN ? AND ?'];
$statsParams = [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
if ($filterSkupina) { $statsWhere[] = 'pt.skupina_id = ?'; $statsParams[] = $filterSkupina; }
if ($filterJenMoje) {
    $statsWhere[] = 'pt.trener_id = ?'; $statsParams[] = $trenerId;
} elseif ($filterTrener) {
    $statsWhere[] = 'pt.trener_id = ?'; $statsParams[] = $filterTrener;
}
$stStats = $pdo->prepare("
    SELECT stav, COUNT(*) AS cnt FROM planovane_treninky pt
    WHERE " . implode(' AND ', $statsWhere) . " AND stav != 'zruseny'
    GROUP BY stav
");
$stStats->execute($statsParams);
$statsRaw = $stStats->fetchAll(PDO::FETCH_ASSOC);
$stats = ['planovany' => 0, 'evidovany' => 0];
foreach ($statsRaw as $row) { $stats[$row['stav']] = (int)$row['cnt']; }
$statsCelkem = $stats['planovany'] + $stats['evidovany'];

// ── Metadata kategorií ────────────────────────────────────────────────────────
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',      'color'=>'primary',  'icon'=>'bi-stopwatch'],
    'cyklokros' => ['label'=>'Cyklokros',  'color'=>'orange',   'icon'=>'bi-tornado'],
    'posilovna' => ['label'=>'Posilovna',  'color'=>'danger',   'icon'=>'bi-trophy'],
    'atletika'  => ['label'=>'Atletika',   'color'=>'info',     'icon'=>'bi-person-walking'],
    'cviceni'   => ['label'=>'Cvičení',    'color'=>'secondary','icon'=>'bi-heart-pulse'],
    'plavani'   => ['label'=>'Plavání',    'color'=>'teal',     'icon'=>'bi-water'],
];

$dayNames = ['Po','Út','St','Čt','Pá','So','Ne'];
$flashSuccess = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$flashError   = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);

// ── Nástěnka skupiny ──────────────────────────────────────────────────────────
$nastenkOznameni = [];
try {
    $nastenkIds = $filterSkupina
        ? [$filterSkupina]
        : array_column($skupiny, 'id');
    if (!empty($nastenkIds)) {
        $inN = implode(',', array_fill(0, count($nastenkIds), '?'));
        $stN = $pdo->prepare("
            SELECT DISTINCT o.id, o.nazev, o.obsah_html, o.datum, t.jmeno AS trener_jmeno
            FROM oznameni o
            JOIN oznameni_targets ot ON ot.oznameni_id = o.id
            LEFT JOIN treneri t ON t.id = o.vlozil_trener_id
            WHERE ot.target_type = 'skupina' AND ot.target_id IN ($inN)
            ORDER BY o.datum DESC, o.id DESC
            LIMIT 5
        ");
        $stN->execute($nastenkIds);
        $nastenkOznameni = $stN->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { $nastenkOznameni = []; }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plánovač tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .plan-card {
            border-left: 4px solid #6b7280;
            transition: box-shadow .15s;
        }
        .plan-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        .plan-card.evidovano { border-left-color: #16a34a; opacity: .85; }
        .plan-card.planovano { border-left-color: #3b82f6; }
        .plan-card.dragging  { opacity: .4; transform: rotate(1deg); }
        .drop-zone.drag-over > .day-header { background: #dbeafe !important; }
        [data-bs-theme="dark"] .drop-zone.drag-over > .day-header { background: #1e3a8a !important; }
        /* Přesun na dotykových zařízeních — drag & drop tam nefunguje */
        .touch-move-btn { display: none; }
        @media (pointer: coarse) {
            .touch-move-btn { display: inline-block; }
            .drag-handle { display: none !important; }
        }
        .plan-nazev-input { font-size:.88rem; font-weight:600; border:none; border-bottom:2px solid #3b82f6;
                            background:transparent; outline:none; width:100%; padding:0; }
        .badge-orange { background-color: #fd7e14 !important; color: #fff; }
        .badge-teal   { background-color: #20c997 !important; color: #fff; }
        .day-header {
            font-weight: 600; font-size: .85rem; padding: 6px 10px;
            border-radius: 6px; margin-bottom: 8px;
        }
        .day-header.today-header { background: #fffbeb; border: 1px solid #fde68a; }
        .day-header.past-header  { background: #f3f4f6; color: #9ca3af; }
        .day-header.future-header { background: #eff6ff; border: 1px solid #bfdbfe; }
        .empty-day { color: #d1d5db; font-size: .8rem; text-align:center; padding: 12px; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container-fluid mt-3 px-3" style="max-width:1100px">

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= h($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= h($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Hlavička -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h5 mb-0 me-2"><i class="bi bi-calendar3-week me-2 text-primary"></i>Plánovač tréninků</h1>
        <?php
        // Helper: sestaví URL s aktuálními filtry
        $qFilters = ($filterSkupina ? '&skupina_id='.$filterSkupina : '')
                  . '&jen_moje=' . ($filterJenMoje ? '1' : '0')
                  . (!$filterJenMoje && $filterTrener ? '&trener_id='.$filterTrener : '');
        ?>
        <div class="btn-group btn-group-sm">
            <a href="?datum=<?= $prevWeek . $qFilters ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <a href="?datum=<?= $today    . $qFilters ?>" class="btn btn-outline-secondary">Dnes</a>
            <a href="?datum=<?= $nextWeek . $qFilters ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        </div>
        <span class="text-muted small">
            <?= $monday->format('j. n.') ?> – <?= $sunday->format('j. n. Y') ?>
        </span>
        <div class="ms-auto d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalKopirovat"
                    title="Zkopírovat tento týden o 7 dní dopředu">
                <i class="bi bi-copy me-1"></i>Kopírovat týden
            </button>
            <a href="planovany_trenink_form.php?datum=<?= $today ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Nový trénink
            </a>
        </div>
    </div>

    <!-- Filtry -->
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center mb-3" id="filterForm">
        <input type="hidden" name="datum" value="<?= h($datumParam) ?>">

        <!-- Přepínač Moje / Vše -->
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="jen_moje" id="fMoje" value="1"
                   <?= $filterJenMoje ? 'checked' : '' ?> onchange="toggleTrenerFilter();this.form.submit()">
            <label class="btn btn-outline-primary" for="fMoje">
                <i class="bi bi-person-fill me-1"></i>Moje
            </label>
            <input type="radio" class="btn-check" name="jen_moje" id="fVse" value="0"
                   <?= !$filterJenMoje ? 'checked' : '' ?> onchange="toggleTrenerFilter();this.form.submit()">
            <label class="btn btn-outline-secondary" for="fVse">
                <i class="bi bi-people me-1"></i>Vše
            </label>
        </div>

        <!-- Trenér select — viditelný jen při "Vše" -->
        <select name="trener_id" id="trenerSelect"
                class="form-select form-select-sm <?= $filterJenMoje ? 'd-none' : '' ?>"
                style="width:auto" onchange="this.form.submit()">
            <option value="">— všichni trenéři —</option>
            <?php foreach ($treneri as $tr): ?>
                <option value="<?= $tr['id'] ?>"
                    <?= (!$filterJenMoje && $filterTrener == $tr['id']) ? 'selected' : '' ?>>
                    <?= h($tr['jmeno']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Skupina -->
        <select name="skupina_id" class="form-select form-select-sm" style="width:auto"
                onchange="this.form.submit()">
            <option value="">Všechny skupiny</option>
            <?php foreach ($skupiny as $sk): ?>
                <option value="<?= $sk['id'] ?>" <?= $filterSkupina == $sk['id'] ? 'selected' : '' ?>>
                    <?= h($sk['nazev']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($filterSkupina && !empty($skupinyIdx[$filterSkupina]['hash'])): ?>
            <a href="program_skupiny.php?hash=<?= urlencode($skupinyIdx[$filterSkupina]['hash']) ?>"
               target="_blank" class="btn btn-outline-info btn-sm" title="Veřejný program skupiny">
                <i class="bi bi-box-arrow-up-right me-1"></i>Sdílet program
            </a>
        <?php endif; ?>
    </form>

    <!-- ── Nástěnka skupiny ── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center"
             style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#nastenkaPannel">
            <span class="fw-semibold small">
                <i class="bi bi-megaphone me-2 text-primary"></i>Nástěnka skupiny
                <?php if (!empty($nastenkOznameni)): ?>
                    <span class="badge bg-primary ms-1"><?= count($nastenkOznameni) ?></span>
                <?php endif; ?>
            </span>
            <div class="d-flex gap-2 align-items-center">
                <?php if (canAccess('oznameni')): ?>
                <button type="button" class="btn btn-primary btn-sm py-0 px-2"
                        data-bs-toggle="modal" data-bs-target="#modalNoveOznameni"
                        onclick="event.stopPropagation()"
                        title="Nové oznámení skupině">
                    <i class="bi bi-plus-lg me-1"></i>Nové
                </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-muted small"></i>
            </div>
        </div>
        <div class="collapse show" id="nastenkaPannel">
            <div class="card-body py-2 px-3">
                <?php if (empty($nastenkOznameni)): ?>
                    <div class="text-muted small text-center py-2">
                        <i class="bi bi-info-circle me-1"></i>Žádná oznámení pro tuto skupinu.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                    <?php foreach ($nastenkOznameni as $oz): ?>
                        <div class="border-start border-primary ps-3 py-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-semibold small"><?= h($oz['nazev'] ?? 'Oznámení') ?></span>
                                <span class="text-muted" style="font-size:.72rem;white-space:nowrap">
                                    <?= h($oz['datum']) ?>
                                    <?php if ($oz['trener_jmeno']): ?>
                                        · <?= h($oz['trener_jmeno']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="text-muted small mt-1" style="font-size:.8rem">
                                <?= mb_strtok(strip_tags($oz['obsah_html']), "\n") ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="mt-2 text-end">
                        <a href="oznameni.php" class="small text-muted">
                            Všechna oznámení <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Varování na nezaevidované minulé tréninky -->
    <?php
    $wWhere  = ["pt.stav = 'planovany'", "pt.datum < ?"];
    $wParams = [$today];
    if ($filterJenMoje) { $wWhere[] = 'pt.trener_id = ?'; $wParams[] = $trenerId; }
    elseif ($filterTrener) { $wWhere[] = 'pt.trener_id = ?'; $wParams[] = $filterTrener; }
    if ($filterSkupina) { $wWhere[] = 'pt.skupina_id = ?'; $wParams[] = $filterSkupina; }
    $stWarn = $pdo->prepare("SELECT COUNT(*) FROM planovane_treninky pt WHERE " . implode(' AND ', $wWhere));
    $stWarn->execute($wParams);
    $pocetNezaevidovanych = (int)$stWarn->fetchColumn();
    ?>
    <?php if ($pocetNezaevidovanych > 0): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 py-2">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong><?= $pocetNezaevidovanych ?></strong>
            plánovan<?= $pocetNezaevidovanych === 1 ? 'ý trénink' : ($pocetNezaevidovanych < 5 ? 'é tréninky' : 'ých tréninků') ?>
            proběhl<?= $pocetNezaevidovanych === 1 ? '' : 'o' ?>, ale dosud nej<?= $pocetNezaevidovanych === 1 ? 'je' : 'jsou' ?> zaevidován<?= $pocetNezaevidovanych === 1 ? '' : 'y' ?>.
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?datum=<?= h(date('Y-m-d', strtotime('-7 days'))) ?>&<?= http_build_query(['jen_moje' => $filterJenMoje ? 1 : 0, 'trener_id' => $filterTrener, 'skupina_id' => $filterSkupina]) ?>"
               class="btn btn-warning btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Přejít na minulý týden
            </a>
            <?php if (roleAtLeast('hlavni')): ?>
            <a href="#"
               class="btn btn-outline-warning btn-sm disabled" aria-disabled="true"
               title="Zašle upomínkové emaily trenérům">
                <i class="bi bi-envelope me-1"></i>Zaslat upomínky
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats lišta -->
    <div class="row g-2 mb-4">
        <div class="col-auto">
            <div class="card border-0 shadow-sm text-center px-4 py-2">
                <div class="fs-4 fw-bold text-primary"><?= $statsCelkem ?></div>
                <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i>Celkem tento týden</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 shadow-sm text-center px-4 py-2">
                <div class="fs-4 fw-bold text-success"><?= $stats['evidovany'] ?></div>
                <div class="text-muted small"><i class="bi bi-check-circle me-1"></i>Evidováno</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 shadow-sm text-center px-4 py-2">
                <div class="fs-4 fw-bold text-warning"><?= $stats['planovany'] ?></div>
                <div class="text-muted small"><i class="bi bi-hourglass me-1"></i>Čeká na evidenci</div>
            </div>
        </div>
    </div>

    <!-- Dny -->
    <div class="row g-3">
    <?php foreach ($dneTydne as $i => $dt): ?>
        <?php
        $d   = $dt->format('Y-m-d');
        $dne = $planyDne[$d] ?? [];
        $isPast   = $d < $today;
        $isToday  = $d === $today;
        $headerCls = $isToday ? 'today-header' : ($isPast ? 'past-header' : 'future-header');
        ?>
        <div class="col-lg col-md-6 col-12 drop-zone" data-datum="<?= h($d) ?>">
            <div class="day-header <?= $headerCls ?>">
                <?= $dayNames[$i] ?> <?= $dt->format('j. n.') ?>
                <?php if ($isToday): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">Dnes</span><?php endif; ?>
                <?php if (!empty($dne)): ?>
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= count($dne) ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($dne)): ?>
                <div class="empty-day text-center py-3" style="font-size:.8rem;color:#d1d5db;border:2px dashed transparent;border-radius:.5rem">
                    <i class="bi bi-dash d-block fs-4 mb-1"></i>Žádné tréninky
                </div>
            <?php endif; ?>

            <?php foreach ($dne as $p): ?>
                <?php
                $isEvidovano = $p['stav'] === 'evidovany';
                $meta = $kategorieMeta[$p['kategorie'] ?? ''] ?? null;
                $cardCls = $isEvidovano ? 'evidovano' : 'planovano';
                $canDrag = !$isEvidovano && ($p['trener_id'] == $trenerId || roleAtLeast('hlavni'));
                $cas = '';
                if ($p['cas_od']) {
                    $cas = substr($p['cas_od'],0,5);
                    if ($p['cas_do']) $cas .= '–' . substr($p['cas_do'],0,5);
                }
                ?>
                <div class="card plan-card <?= $cardCls ?> mb-2 shadow-sm<?= $canDrag ? ' draggable-card' : '' ?>"
                     <?= $canDrag ? 'draggable="true"' : '' ?>
                     data-plan-id="<?= (int)$p['id'] ?>"
                     data-datum="<?= h($p['datum']) ?>">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <?php if ($canDrag): ?>
                            <div class="text-muted d-flex align-items-center me-1 drag-handle" style="cursor:grab;font-size:.85rem" title="Přetáhni na jiný den">
                                <i class="bi bi-grip-vertical"></i>
                            </div>
                            <?php endif; ?>
                            <div class="flex-grow-1" style="min-width:0">
                                <?php if ($meta): ?>
                                    <span class="badge badge-<?= $meta['color'] ?> bg-<?= $meta['color'] ?> me-1" style="font-size:.65rem">
                                        <i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($isEvidovano): ?>
                                    <span class="badge bg-success" style="font-size:.65rem">
                                        <i class="bi bi-check-lg me-1"></i>Evidováno
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($p['serie_id'])): ?>
                                    <span class="badge bg-light text-secondary border" style="font-size:.6rem" title="Součást opakující se série">
                                        <i class="bi bi-arrow-repeat me-1"></i>Série
                                    </span>
                                <?php endif; ?>
                                <div class="fw-semibold mt-1 plan-nazev-wrap" style="font-size:.88rem">
                                    <span class="plan-nazev" data-plan-id="<?= (int)$p['id'] ?>"
                                          style="cursor:<?= $canDrag ? 'text' : 'default' ?>"
                                          title="<?= $canDrag ? 'Dvojklik pro přejmenování' : '' ?>">
                                        <?= h($p['nazev']) ?>
                                    </span>
                                </div>
                                <div class="text-muted" style="font-size:.78rem">
                                    <i class="bi bi-people me-1"></i><?= h($p['skupina_nazev'] ?? '—') ?>
                                    <?php if (!empty($p['podskupiny_nazvy'])): ?>
                                        <span class="text-muted"> / <?= h($p['podskupiny_nazvy']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:.76rem">
                                    <?php if ($cas): ?><i class="bi bi-clock me-1"></i><?= h($cas) ?><?php endif; ?>
                                    <?php if ($p['sportoviste_id'] && isset($sportIdx[$p['sportoviste_id']])): ?>
                                        <?php if ($cas): ?> · <?php endif; ?>
                                        <i class="bi bi-building me-1"></i><?= h($sportIdx[$p['sportoviste_id']]) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:.72rem">
                                    <i class="bi bi-person me-1"></i><?= h($p['trener_jmeno']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Akce -->
                        <div class="d-flex gap-1 mt-2 flex-wrap">
                            <?php if ($isEvidovano && $p['trenink_id']): ?>
                                <a href="edit_trenink.php?id=<?= (int)$p['trenink_id'] ?>"
                                   class="btn btn-success btn-sm py-0 px-2" style="font-size:.75rem">
                                    <i class="bi bi-eye me-1"></i>Detail evidence
                                </a>
                            <?php elseif (!$isEvidovano): ?>
                                <a href="formular.php?plan_id=<?= (int)$p['id'] ?>"
                                   class="btn btn-primary btn-sm py-0 px-2" style="font-size:.75rem">
                                    <i class="bi bi-pencil-square me-1"></i>Zadat evidenci
                                </a>
                                <?php if ($p['trener_id'] == $trenerId || roleAtLeast('hlavni')): ?>
                                    <a href="planovany_trenink_form.php?id=<?= (int)$p['id'] ?>"
                                       class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.75rem"
                                       title="Upravit plán" aria-label="Upravit plán">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm py-0 px-2 touch-move-btn"
                                            style="font-size:.75rem"
                                            data-plan-id="<?= (int)$p['id'] ?>"
                                            data-nazev="<?= h($p['nazev']) ?>"
                                            title="Přesunout na jiný den" aria-label="Přesunout na jiný den">
                                        <i class="bi bi-arrows-move"></i>
                                    </button>
                                    <form method="post" class="d-inline"
                                          data-confirm="Opravdu zrušit tento trénink?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="zrusit">
                                        <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2"
                                                style="font-size:.75rem" title="Zrušit tento trénink">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </form>
                                    <?php if (!empty($p['serie_id'])): ?>
                                    <form method="post" class="d-inline"
                                          data-confirm="Zrušit CELOU sérii (všechny nadcházející tréninky v sérii)?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="zrusit">
                                        <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="zrusit_serii" value="1">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2"
                                                style="font-size:.75rem" title="Zrušit celou sérii">
                                            <i class="bi bi-arrow-repeat"></i><i class="bi bi-x-sm"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Přidat -->
            <?php if (!$isPast): ?>
                <a href="planovany_trenink_form.php?datum=<?= $d ?>"
                   class="btn btn-outline-secondary btn-sm w-100 mt-1" style="font-size:.75rem">
                    <i class="bi bi-plus"></i> Přidat
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<!-- ── Modal: Přesun plánu (dotyková zařízení) ── -->
<div class="modal fade" id="modalPresunPlan" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-arrows-move me-2 text-primary"></i>Přesunout: <span id="presunNazev"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zavřít"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2" id="presunDnyBtns"></div>
                <hr class="my-3">
                <label for="presunDatum" class="form-label small">Nebo jiné datum:</label>
                <div class="input-group input-group-sm">
                    <input type="date" id="presunDatum" class="form-control">
                    <button type="button" class="btn btn-primary" id="presunDatumBtn">Přesunout</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Nové oznámení ── -->
<div class="modal fade" id="modalNoveOznameni" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone me-2 text-primary"></i>Nové oznámení skupině</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label req">Titulek</label>
                    <input type="text" id="ozNazev" class="form-control" placeholder="Název oznámení">
                </div>
                <div class="mb-3">
                    <label class="form-label req">Text</label>
                    <textarea id="ozObsah" class="form-control" rows="4"
                              placeholder="Text oznámení pro skupinu…"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label req">Datum</label>
                    <input type="date" id="ozDatum" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label req">Skupiny</label>
                    <div id="ozSkupinyWrap" class="border rounded p-2" style="max-height:140px;overflow-y:auto">
                        <?php foreach ($skupiny as $sk): ?>
                        <div class="form-check">
                            <input class="form-check-input oz-skupina" type="checkbox"
                                   value="<?= $sk['id'] ?>" id="ozSk_<?= $sk['id'] ?>"
                                   <?= $filterSkupina == $sk['id'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="ozSk_<?= $sk['id'] ?>">
                                <?= h($sk['nazev']) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                <button type="button" class="btn btn-primary" id="btnOdeslatOznameni">
                    <i class="bi bi-send me-1"></i>Odeslat oznámení
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Kopírovat týden ── -->
<div class="modal fade" id="modalKopirovat" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-copy me-2 text-primary"></i>Kopírovat týden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formKopirovat">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="kopirovat_tyden">
                <input type="hidden" name="kopirovat_od" value="<?= h($monday->format('Y-m-d')) ?>">
                <input type="hidden" name="kopirovat_do" value="<?= h($sunday->format('Y-m-d')) ?>">
                <input type="hidden" name="kopirovat_jen_moje" value="<?= $filterJenMoje ? '1' : '0' ?>">
                <input type="hidden" name="kopirovat_trener_id" value="<?= $filterTrener ?>">
                <input type="hidden" name="kopirovat_skupina_id" value="<?= $filterSkupina ?>">
                <div class="modal-body">
                    <div class="alert alert-info border-0 py-2 mb-3">
                        <i class="bi bi-arrow-right me-1"></i>
                        Zkopíruje tréninky z týdne
                        <strong><?= $monday->format('j. n.') ?> – <?= $sunday->format('j. n. Y') ?></strong>
                        → o 7 dní dopředu
                        (<strong><?= (clone $monday)->modify('+7 days')->format('j. n.') ?> – <?= (clone $sunday)->modify('+7 days')->format('j. n. Y') ?></strong>).
                    </div>

                    <div id="kopirovatStats" class="mb-3">
                        <?php
                        // Počet plánů k okopírování (stejné filtry jako aktuální zobrazení)
                        $kStatsWhere  = ["datum BETWEEN ? AND ?", "stav = 'planovany'"];
                        $kStatsParams = [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
                        if ($filterJenMoje) { $kStatsWhere[] = 'trener_id = ?'; $kStatsParams[] = $trenerId; }
                        elseif ($filterTrener) { $kStatsWhere[] = 'trener_id = ?'; $kStatsParams[] = $filterTrener; }
                        if ($filterSkupina) { $kStatsWhere[] = 'skupina_id = ?'; $kStatsParams[] = $filterSkupina; }
                        $kCount = (int)$pdo->prepare("SELECT COUNT(*) FROM planovane_treninky WHERE " . implode(' AND ', $kStatsWhere))
                                            ->execute($kStatsParams) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
                        // Přímý count
                        $stKC = $pdo->prepare("SELECT COUNT(*) FROM planovane_treninky WHERE " . implode(' AND ', $kStatsWhere));
                        $stKC->execute($kStatsParams);
                        $kCount = (int)$stKC->fetchColumn();
                        ?>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-3 fw-bold text-primary"><?= $kCount ?></span>
                            <span class="text-muted">plánovaných tréninků bude zkopírováno</span>
                        </div>
                        <?php if ($filterJenMoje): ?>
                            <div class="text-muted small"><i class="bi bi-person me-1"></i>Pouze vaše tréninky</div>
                        <?php elseif ($filterTrener): ?>
                            <?php $trJm = ''; foreach($treneri as $tr) { if($tr['id'] == $filterTrener) $trJm = $tr['jmeno']; } ?>
                            <div class="text-muted small"><i class="bi bi-person me-1"></i>Trenér: <?= h($trJm) ?></div>
                        <?php else: ?>
                            <div class="text-muted small"><i class="bi bi-people me-1"></i>Všichni trenéři</div>
                        <?php endif; ?>
                        <?php if ($filterSkupina && isset($skupinyIdx[$filterSkupina])): ?>
                            <div class="text-muted small"><i class="bi bi-diagram-3 me-1"></i>Skupina: <?= h($skupinyIdx[$filterSkupina]['nazev']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($kCount === 0): ?>
                        <div class="alert alert-warning py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Tento týden nemá žádné plánované tréninky odpovídající aktuálnímu filtru.
                        </div>
                    <?php endif; ?>

                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>
                        Zkopírované tréninky budou mít stav <strong>Plánovaný</strong>.
                        Rezervace sportovišť se nekopírují — je nutné je vytvořit zvlášť.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary" <?= $kCount === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-copy me-1"></i>Zkopírovat <?= $kCount > 0 ? "({$kCount})" : '' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Drag & Drop plánů ─────────────────────────────────────────────────────
(function() {
    const CSRF = <?= json_encode(csrf_token()) ?>;
    let draggedCard = null;

    // Drag start
    document.addEventListener('dragstart', function(e) {
        const card = e.target.closest('.draggable-card');
        if (!card) return;
        draggedCard = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.planId);
    });
    document.addEventListener('dragend', function(e) {
        if (draggedCard) draggedCard.classList.remove('dragging');
        draggedCard = null;
        document.querySelectorAll('.drop-zone.drag-over').forEach(z => z.classList.remove('drag-over'));
    });

    // Drop zones
    document.addEventListener('dragover', function(e) {
        const zone = e.target.closest('.drop-zone');
        if (!zone || !draggedCard) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.drop-zone.drag-over').forEach(z => z.classList.remove('drag-over'));
        zone.classList.add('drag-over');
    });
    document.addEventListener('dragleave', function(e) {
        const zone = e.target.closest('.drop-zone');
        if (zone && !zone.contains(e.relatedTarget)) zone.classList.remove('drag-over');
    });
    // Sdílený přesun plánu — používá drop i dotykový modal
    function movePlan(card, noveDatum) {
        const planId = card.dataset.planId;
        fetch('ajax_update_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ akce: 'move', plan_id: planId, datum: noveDatum, csrf_token: CSRF })
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                showToast('Trénink přesunut na ' + noveDatum, 'success');
                const zone = document.querySelector('.drop-zone[data-datum="' + noveDatum + '"]');
                if (zone) {
                    // Přesunout kartu do nové zóny (před tlačítko "Přidat")
                    const addBtn = zone.querySelector('a[href*="planovany_trenink_form"]');
                    zone.insertBefore(card, addBtn || null);
                    card.dataset.datum = noveDatum;
                    updateEmptyDays();
                } else {
                    // Cílový den není v zobrazeném týdnu → obnovit stránku
                    window.location.reload();
                }
            } else {
                showToast(data.msg || 'Chyba při přesunu', 'danger');
            }
        })
        .catch(() => showToast('Chyba sítě', 'danger'));
    }

    document.addEventListener('drop', function(e) {
        const zone = e.target.closest('.drop-zone');
        if (!zone || !draggedCard) return;
        e.preventDefault();
        zone.classList.remove('drag-over');
        const noveDatum = zone.dataset.datum;
        if (noveDatum === draggedCard.dataset.datum) return; // nezměnilo se
        movePlan(draggedCard, noveDatum);
    });

    // ── Přesun na dotykových zařízeních — tlačítko + modal ──────────────
    (function () {
        const modalEl = document.getElementById('modalPresunPlan');
        if (!modalEl) return;
        let moveCard = null;
        const dayNamesCz = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.touch-move-btn');
            if (!btn) return;
            moveCard = btn.closest('.plan-card');
            document.getElementById('presunNazev').textContent = btn.dataset.nazev;

            // Nabídka dnů z viditelného týdne
            const cont = document.getElementById('presunDnyBtns');
            cont.innerHTML = '';
            document.querySelectorAll('.drop-zone').forEach(zone => {
                const d = zone.dataset.datum;
                if (!d) return;
                const dt = new Date(d + 'T00:00:00');
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-sm ' + (d === moveCard.dataset.datum ? 'btn-secondary' : 'btn-outline-primary');
                b.disabled = d === moveCard.dataset.datum;
                b.textContent = dayNamesCz[dt.getDay()] + ' ' + dt.getDate() + '. ' + (dt.getMonth() + 1) + '.';
                b.addEventListener('click', function () {
                    bootstrap.Modal.getInstance(modalEl).hide();
                    movePlan(moveCard, d);
                });
                cont.appendChild(b);
            });

            document.getElementById('presunDatum').value = '';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        document.getElementById('presunDatumBtn').addEventListener('click', function () {
            const d = document.getElementById('presunDatum').value;
            if (!d || !moveCard) return;
            bootstrap.Modal.getInstance(modalEl).hide();
            movePlan(moveCard, d);
        });
    })();

    function updateEmptyDays() {
        document.querySelectorAll('.drop-zone').forEach(zone => {
            const hasCards = zone.querySelectorAll('.plan-card').length > 0;
            const empty = zone.querySelector('.empty-day');
            if (empty) empty.style.display = hasCards ? 'none' : '';
        });
    }

    // ── Inline editace názvu (dvojklik) ─────────────────────────────────
    document.addEventListener('dblclick', function(e) {
        const span = e.target.closest('.plan-nazev');
        if (!span) return;
        const planId = span.dataset.planId;
        const current = span.textContent.trim();

        const input = document.createElement('input');
        input.type = 'text';
        input.value = current;
        input.className = 'plan-nazev-input';

        span.replaceWith(input);
        input.focus();
        input.select();

        function save() {
            const nazev = input.value.trim() || current;
            fetch('ajax_update_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ akce: 'rename', plan_id: planId, nazev: nazev, csrf_token: CSRF })
            })
            .then(r => r.json())
            .then(data => {
                const newSpan = document.createElement('span');
                newSpan.className = 'plan-nazev';
                newSpan.dataset.planId = planId;
                newSpan.style.cursor = 'text';
                newSpan.title = 'Dvojklik pro přejmenování';
                newSpan.textContent = data.ok ? nazev : current;
                input.replaceWith(newSpan);
                if (data.ok) showToast('Přejmenováno', 'success');
            })
            .catch(() => {
                const fallback = document.createElement('span');
                fallback.className = 'plan-nazev';
                fallback.dataset.planId = planId;
                fallback.textContent = current;
                input.replaceWith(fallback);
            });
        }

        input.addEventListener('blur', save);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { input.value = current; input.blur(); }
        });
    });
})();

function toggleTrenerFilter() {
    const moje = document.getElementById('fMoje');
    const sel  = document.getElementById('trenerSelect');
    if (!moje || !sel) return;
    sel.classList.toggle('d-none', moje.checked);
    if (moje.checked) sel.value = ''; // reset trainer when switching to "Moje"
}
// On load: ensure correct visibility if page was loaded without form submit
document.addEventListener('DOMContentLoaded', toggleTrenerFilter);

// ── Nové oznámení skupině ────────────────────────────────────────────────────
(function() {
    const btn = document.getElementById('btnOdeslatOznameni');
    if (!btn) return;
    const CSRF = <?= json_encode(csrf_token()) ?>;

    btn.addEventListener('click', function() {
        const nazev  = document.getElementById('ozNazev').value.trim();
        const obsah  = document.getElementById('ozObsah').value.trim();
        const datum  = document.getElementById('ozDatum').value;
        const skupinyEl = document.querySelectorAll('.oz-skupina:checked');
        const skupinyIds = Array.from(skupinyEl).map(el => el.value);

        if (!nazev)              { showToast('Zadejte titulek.', 'warning'); return; }
        if (!obsah)              { showToast('Zadejte text oznámení.', 'warning'); return; }
        if (!skupinyIds.length)  { showToast('Vyberte alespoň jednu skupinu.', 'warning'); return; }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Odesílám…';

        fetch('ajax_nova_oznameni.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: CSRF, nazev, obsah, datum, skupiny_ids: skupinyIds })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>Odeslat oznámení';
            if (data.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalNoveOznameni')).hide();
                showToast('Oznámení odesláno skupině.', 'success');
                // Reload nastěnky po krátké prodlevě
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.msg || 'Chyba při odesílání.', 'danger');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i>Odeslat oznámení';
            showToast('Chyba sítě.', 'danger');
        });
    });
})();
</script>
</body>
</html>
