<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmtDate(?string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts ? date('d.m.Y', $ts) : h($d);
}

function renderMereniData(array $mz): string {
    $typ   = $mz['typ'] ?? '';
    $parts = [];
    if ($typ === 'kolo' || $typ === 'beh') {
        if (!empty($mz['vzdalenost']) && $mz['vzdalenost'] !== '0') $parts[] = '<strong>' . h($mz['vzdalenost']) . '</strong> km';
        if (!empty($mz['cas']))    $parts[] = '⏱ ' . h($mz['cas']);
        if (!empty($mz['prevod']) && $typ === 'kolo') $parts[] = 'převod ' . h($mz['prevod']);
    } elseif ($typ === 'posilovna') {
        $cvik = $mz['cvik_nazev'] ?: ($mz['cvik_id'] ? '#' . (int)$mz['cvik_id'] : '—');
        $parts[] = '<strong>' . h($cvik) . '</strong>';
        if (!empty($mz['vaha']) && $mz['vaha'] !== '0') $parts[] = h($mz['vaha']) . ' kg';
        if (!empty($mz['opakovani'])) $parts[] = (int)$mz['opakovani'] . '×';
        if (!empty($mz['rpe']))       $parts[] = 'RPE ' . h($mz['rpe']);
    } elseif ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
        $segment = $mz['segment_nazev'] ?? ($mz['segment_id'] ? '#' . (int)$mz['segment_id'] : '—');
        $parts[] = '<strong>' . h($segment) . '</strong>';
        if (!empty($mz['cas'])) $parts[] = '⏱ ' . h($mz['cas']);
    } else {
        return '<span class="text-muted">—</span>';
    }
    return implode(' · ', $parts) ?: '<span class="text-muted">—</span>';
}

$errors  = [];
$success = false;

// ── AJAX: Vyhledávání sportovce ─────────────────────────────────────────
if (!empty($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $q   = trim($_GET['q'] ?? '');
    $gid = (isset($_GET['skupina_id']) && ctype_digit($_GET['skupina_id'])) ? (int)$_GET['skupina_id'] : 0;

    $where  = [];
    $params = [];

    if ($q !== '') {
        $where[]    = "(CONVERT(s.jmeno USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :q
                        OR CONVERT(s.prijmeni USING utf8mb4) COLLATE utf8mb4_general_ci LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    if ($gid > 0) {
        $where[]    = "g.id = :gid";
        $params[':gid'] = $gid;
    }

    $results = [];
    if ($where) {
        $sql = "
            SELECT DISTINCT s.id, s.jmeno, s.prijmeni, s.category
            FROM sportovci s
            LEFT JOIN sportovec_podskupina sp ON sp.sportovec_id = s.id
            LEFT JOIN podskupiny p ON p.id = sp.podskupina_id
            LEFT JOIN skupiny g ON g.id = p.skupina_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.prijmeni, s.jmeno
            LIMIT 30
        ";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
    echo json_encode($results);
    exit;
}

// ── AJAX: Uložení profilu ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }
        die('Neplatný CSRF token.');
    }
    $sid    = (isset($_POST['sportovec_id']) && ctype_digit($_POST['sportovec_id'])) ? (int)$_POST['sportovec_id'] : 0;

    $uciid    = trim($_POST['uciid']    ?? '');
    $email    = trim($_POST['email']    ?? '');
    $kategorie = trim($_POST['kategorie'] ?? '');
    $narozeni = trim($_POST['narozeni'] ?? '');
    $oddil    = trim($_POST['oddil']    ?? '');

    if ($sid <= 0) {
        $errors[] = 'Neplatné ID sportovce.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail není ve správném formátu.';
    }
    if ($narozeni !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $narozeni)) {
        $narozeni = '';  // ignore invalid
    }

    if (empty($errors)) {
        try {
            $pdo->prepare("
                UPDATE sportovci
                SET uciid     = :uciid,
                    email     = :email,
                    category  = :kat,
                    narozeni  = :nar,
                    oddil     = :oddil
                WHERE id = :id LIMIT 1
            ")->execute([
                ':uciid'  => $uciid    ?: null,
                ':email'  => $email    ?: null,
                ':kat'    => $kategorie ?: null,
                ':nar'    => $narozeni ?: null,
                ':oddil'  => $oddil    ?: null,
                ':id'     => $sid,
            ]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
            header('Location: sportovec_detail.php?id=' . $sid . '&ok=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }
}

if (isset($_GET['ok']) && $_GET['ok'] === '1') $success = true;

// ── Vstupní data ────────────────────────────────────────────────────────
$skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

$filterSkupinaId    = $_GET['skupina_id']    ?? '';
$filterPodskupinaId = $_GET['podskupina_id'] ?? '';
$filterJmeno        = trim($_GET['q']        ?? '');

if ($filterSkupinaId    !== '' && !ctype_digit($filterSkupinaId))    $filterSkupinaId    = '';
if ($filterPodskupinaId !== '' && !ctype_digit($filterPodskupinaId)) $filterPodskupinaId = '';

$podskupiny = [];
if ($filterSkupinaId !== '') {
    $stmt = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev");
    $stmt->execute([(int)$filterSkupinaId]);
    $podskupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$sportovecId = (isset($_GET['id']) && ctype_digit($_GET['id'])) ? (int)$_GET['id'] : null;

// Vyhledávání (bez AJAX)
$searchResults = [];
if ($sportovecId === null && (!empty($filterJmeno) || !empty($filterSkupinaId) || !empty($filterPodskupinaId))) {
    $where  = [];
    $params = [];

    if ($filterJmeno !== '') {
        $where[]      = "(CONVERT(s.jmeno USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_general_ci
                          OR CONVERT(s.prijmeni USING utf8mb4) COLLATE utf8mb4_general_ci LIKE CONVERT(:q USING utf8mb4) COLLATE utf8mb4_general_ci)";
        $params[':q'] = '%' . $filterJmeno . '%';
    }
    if ($filterSkupinaId !== '') { $where[] = "g.id = :gid"; $params[':gid'] = (int)$filterSkupinaId; }
    if ($filterPodskupinaId !== '') { $where[] = "p.id = :pid"; $params[':pid'] = (int)$filterPodskupinaId; }

    if ($where) {
        $sql  = "SELECT DISTINCT s.id, s.jmeno, s.prijmeni FROM sportovci s
                 LEFT JOIN sportovec_podskupina sp ON sp.sportovec_id = s.id
                 LEFT JOIN podskupiny p ON p.id = sp.podskupina_id
                 LEFT JOIN skupiny g ON g.id = p.skupina_id
                 WHERE " . implode(' AND ', $where) . " ORDER BY s.prijmeni, s.jmeno";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($searchResults) === 1) {
            $sportovecId = (int)$searchResults[0]['id'];
            header('Location: sportovec_detail.php?id=' . $sportovecId);
            exit;
        }
    }
}

// ── Detail sportovce ────────────────────────────────────────────────────
$sportovec      = null;
$treninky       = [];
$pocetTrObdobi  = 0;
$castkaObdobi   = 0.0;
$sazbaVObdobi   = 0.0;
$aktualniObdobi = null;
$testy          = [];
$souboryByTest  = [];
$mereniSportovce  = [];
$skupinyMembership = [];
$nadchazejiciTreninky = [];
$statCelkemTr     = 0;
$statCelkemMereni = 0;
$statPosledni     = null;

if ($sportovecId !== null) {
    $stmt = $pdo->prepare("SELECT * FROM sportovci WHERE id = ?");
    $stmt->execute([$sportovecId]);
    $sportovec = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sportovec) {

        // ── Tréninky (s délkou a počtem měření) ─────────────────────
        $stmtTr = $pdo->prepare("
            SELECT
                t.id, t.datum, t.delka, t.napln, t.poznamka, t.kategorie,
                (SELECT COUNT(*) FROM trenink_mereni tm2
                    JOIN mereni_zaznamy mz2 ON mz2.id = tm2.mereni_id
                    WHERE tm2.trenink_id = t.id AND mz2.sportovec_id = :sid2
                ) AS mereni_count
            FROM treninky t
            JOIN trenink_sportovec ts ON ts.trenink_id = t.id
            WHERE ts.sportovec_id = :sid
            ORDER BY t.datum DESC
        ");
        $stmtTr->execute([':sid' => $sportovecId, ':sid2' => $sportovecId]);
        $treninky = $stmtTr->fetchAll(PDO::FETCH_ASSOC);

        $statCelkemTr = count($treninky);
        $statPosledni = !empty($treninky) ? $treninky[0]['datum'] : null;

        // ── Kredit ──────────────────────────────────────────────────
        try {
            $stmtOpen = $pdo->prepare("
                SELECT * FROM sportovec_obdobi
                WHERE sportovec_id = ? AND (datum_do IS NULL OR datum_do = '0000-00-00')
                ORDER BY datum_od DESC LIMIT 1
            ");
            $stmtOpen->execute([$sportovecId]);
            $aktualniObdobi = $stmtOpen->fetch(PDO::FETCH_ASSOC);

            if ($aktualniObdobi) {
                $od = $aktualniObdobi['datum_od'];
                $sazbaVObdobi = isset($aktualniObdobi['sazba_kc']) ? (float)$aktualniObdobi['sazba_kc'] : 0.0;

                $stmtCount = $pdo->prepare("
                    SELECT COUNT(DISTINCT ts.trenink_id) AS pocet
                    FROM trenink_sportovec ts JOIN treninky t ON t.id = ts.trenink_id
                    WHERE ts.sportovec_id = :sid AND t.datum >= :od AND t.datum <= :do
                ");
                $stmtCount->execute([':sid' => $sportovecId, ':od' => $od, ':do' => date('Y-m-d')]);
                $pocetTrObdobi = (int)($stmtCount->fetchColumn() ?? 0);
                $castkaObdobi  = $pocetTrObdobi * $sazbaVObdobi;
            }
        } catch (PDOException $e) {}

        // ── Zátěžové testy ───────────────────────────────────────────
        try {
            $stmtT = $pdo->prepare("SELECT * FROM zatezove_testy WHERE sportovec_id = ? ORDER BY datum DESC, id DESC");
            $stmtT->execute([$sportovecId]);
            $testy = $stmtT->fetchAll(PDO::FETCH_ASSOC);

            $testIds = array_column($testy, 'id');
            if (!empty($testIds)) {
                $in = implode(',', array_fill(0, count($testIds), '?'));
                $stmtFiles = $pdo->prepare("SELECT * FROM zatezove_testy_soubory WHERE test_id IN ($in) ORDER BY id ASC");
                $stmtFiles->execute($testIds);
                while ($row = $stmtFiles->fetch(PDO::FETCH_ASSOC)) {
                    $souboryByTest[(int)$row['test_id']][] = $row;
                }
            }
        } catch (PDOException $e) {}

        // ── Nadcházející plánované tréninky ze skupin sportovce ──────
        try {
            $stmtNT = $pdo->prepare("
                SELECT pt.*, sk.nazev AS skupina_nazev, ps.nazev AS podskupina_nazev,
                       sp.nazev AS sport_nazev, t.jmeno AS trener_jmeno
                FROM planovane_treninky pt
                JOIN skupiny sk ON sk.id = pt.skupina_id
                LEFT JOIN podskupiny ps ON ps.id = pt.podskupina_id
                LEFT JOIN sportovist sp ON sp.id = pt.sportoviste_id
                LEFT JOIN treneri t ON t.id = pt.trener_id
                WHERE pt.datum >= CURDATE()
                  AND pt.stav = 'planovany'
                  AND pt.skupina_id IN (
                      SELECT skupina_id FROM sportovec_skupina WHERE sportovec_id = ?
                  )
                ORDER BY pt.datum, pt.cas_od
                LIMIT 30
            ");
            $stmtNT->execute([$sportovecId]);
            $nadchazejiciTreninky = $stmtNT->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $nadchazejiciTreninky = [];
        }

        // ── Všechna měření sportovce ────────────────────────────────
        try {
            $stmtM = $pdo->prepare("
                SELECT
                    t.id AS trenink_id, t.datum AS trenink_datum, tm.poradi,
                    mz.id AS mereni_id, mz.typ,
                    mz.vzdalenost, mz.cas, mz.prevod,
                    mz.cvik_id, mz.segment_id, mz.vaha, mz.opakovani, mz.rpe,
                    mz.poznamka AS mereni_poznamka,
                    c.nazev AS cvik_nazev,
                    seg.nazev AS segment_nazev
                FROM trenink_mereni tm
                JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
                JOIN treninky t ON t.id = tm.trenink_id
                LEFT JOIN cviky c ON c.id = mz.cvik_id
                LEFT JOIN segmenty seg ON seg.id = mz.segment_id
                WHERE mz.sportovec_id = :sid
                ORDER BY t.datum DESC, t.id DESC, tm.poradi ASC, mz.id ASC
            ");
            $stmtM->execute([':sid' => $sportovecId]);
            $mereniSportovce  = $stmtM->fetchAll(PDO::FETCH_ASSOC);
            $statCelkemMereni = count($mereniSportovce);
        } catch (PDOException $e) {}

        // ── Závody sportovce ─────────────────────────────────────────
        $zavodySpotovce = [];
        $statCelkemZavodu = 0;
        try {
            $stmtZ = $pdo->prepare("
                SELECT z.id, z.datum, z.kategorie, z.popis, zsp.poradi, zsp.cas, zsp.body,
                       GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
                FROM zavod_sportovec zsp
                JOIN zavody z ON zsp.zavod_id = z.id
                LEFT JOIN zavod_trener zt ON z.id = zt.zavod_id
                LEFT JOIN treneri tr ON zt.trener_id = tr.id
                WHERE zsp.sportovec_id = :sid
                GROUP BY z.id
                ORDER BY z.datum DESC
            ");
            $stmtZ->execute([':sid' => $sportovecId]);
            $zavodySpotovce   = $stmtZ->fetchAll(PDO::FETCH_ASSOC);
            $statCelkemZavodu = count($zavodySpotovce);
        } catch (PDOException $e) {}

        // ── Skupiny / podskupiny ─────────────────────────────────────
        try {
            $stmtG = $pdo->prepare("
                SELECT g.id AS skupina_id, g.nazev AS skupina_nazev,
                       p.id AS podskupina_id, p.nazev AS podskupina_nazev
                FROM sportovec_podskupina sp
                JOIN podskupiny p ON p.id = sp.podskupina_id
                JOIN skupiny g ON g.id = p.skupina_id
                WHERE sp.sportovec_id = :sid
                ORDER BY g.nazev, p.nazev
            ");
            $stmtG->execute([':sid' => $sportovecId]);
            $skupinyMembership = $stmtG->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}
    }
}

// Unified category value (column may be named 'category' or 'kategorie')
$katValue = $sportovec ? ($sportovec['category'] ?? $sportovec['kategorie'] ?? '') : '';
$narozeniValue = $sportovec ? ($sportovec['narozeni'] ?? '') : '';
if ($narozeniValue === '0000-00-00') $narozeniValue = '';

// Kategorie tréninků meta
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',      'color'=>'primary',  'icon'=>'bi-stopwatch'],
    'cyklokros' => ['label'=>'Cyklokros',  'color'=>'orange',   'icon'=>'bi-signpost-split'],
    'posilovna' => ['label'=>'Posilovna',  'color'=>'danger',   'icon'=>'bi-heart-pulse'],
    'atletika'  => ['label'=>'Atletika',   'color'=>'info',     'icon'=>'bi-person-walking'],
    'cviceni'   => ['label'=>'Cvičení',    'color'=>'secondary','icon'=>'bi-activity'],
    'plavani'   => ['label'=>'Plavání',    'color'=>'teal',     'icon'=>'bi-water'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail sportovce<?= $sportovec ? (' – ' . h(($sportovec['jmeno'] ?? '') . ' ' . ($sportovec['prijmeni'] ?? ''))) : '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .thumb-test { height: 72px; object-fit: cover; border-radius: .4rem; margin: 2px; }
        .stat-card  { border-left: 4px solid; border-radius: .5rem; padding: .9rem 1.2rem; background: #fff; }
        .stat-card.primary  { border-color: #1a56db; }
        .stat-card.success  { border-color: #0e9f6e; }
        .stat-card.warning  { border-color: #f59e0b; }
        .stat-card.info     { border-color: #0ea5e9; }
        .search-result-item:hover { background: #f0f4ff; cursor: pointer; }
        .mereni-typ-kolo           { background: #1a56db !important; }
        .mereni-typ-kolo_krouzek   { background: #7c3aed !important; }
        .mereni-typ-kolo_silnice   { background: #dc2626 !important; }
        .mereni-typ-kolo_mtb       { background: #f59e0b !important; }
        .mereni-typ-beh            { background: #0e9f6e !important; }
        .mereni-typ-posilovna      { background: #f59e0b !important; color: #1f2937 !important; }
        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
        .profile-save-spinner { display: none; }
        #saveProfileBtn.loading .profile-save-spinner { display: inline-block; }
        #saveProfileBtn.loading .profile-save-label  { display: none; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 pb-5">

    <!-- PAGE TITLE + BACK LINK -->
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <h1 class="mb-0">
            <i class="bi bi-person-lines-fill me-2 text-primary"></i>Detail sportovce
        </h1>
        <?php if ($sportovecId && !empty($sportovec['hash'])): ?>
            <a class="btn btn-outline-secondary btn-sm ms-auto"
               href="sportovec_treninky.php?hash=<?= urlencode($sportovec['hash']) ?>"
               target="_blank">
               <i class="bi bi-box-arrow-up-right me-1"></i>Veřejná karta
            </a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <script>document.addEventListener('DOMContentLoaded', function() { showToast('Profil uložen.', 'success'); });</script>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <script>document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($errors as $e): ?>
            showToast(<?= json_encode(h($e),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, 'danger');
            <?php endforeach; ?>
        });</script>
    <?php endif; ?>

    <!-- VYHLEDÁVÁNÍ ────────────────────────────────────────────── -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><i class="bi bi-search me-2"></i>Vyhledání sportovce</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Jméno / příjmení</label>
                    <input type="text" id="searchInput" class="form-control"
                           value="<?= h($filterJmeno) ?>"
                           placeholder="Začni psát…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Skupina</label>
                    <select id="filterSkupina" name="skupina_id" class="form-select">
                        <option value="">-- všechny --</option>
                        <?php foreach ($skupiny as $sk): ?>
                            <option value="<?= (int)$sk['id'] ?>" <?= ($filterSkupinaId == $sk['id']) ? 'selected' : '' ?>>
                                <?= h($sk['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Live search results -->
            <div id="searchResults" class="mt-2"></div>

            <!-- Fallback static results -->
            <?php if ($sportovecId === null && !empty($searchResults)): ?>
                <hr>
                <ul class="list-group">
                    <?php foreach ($searchResults as $r): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= h(($r['prijmeni'] ?? '') . ' ' . ($r['jmeno'] ?? '')) ?></span>
                            <a class="btn btn-sm btn-outline-primary" href="sportovec_detail.php?id=<?= (int)$r['id'] ?>">
                                Otevřít
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ($sportovecId === null && $_GET): ?>
                <div class="alert alert-warning mt-2 mb-0">Nebyl nalezen žádný sportovec.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sportovecId === null): ?>
        <!-- jen vyhledávání -->

    <?php elseif (!$sportovec): ?>
        <div class="alert alert-danger">Sportovec nebyl nalezen.</div>

    <?php else:
        $jmeno   = h(($sportovec['jmeno'] ?? '') . ' ' . ($sportovec['prijmeni'] ?? ''));
    ?>

    <!-- STATISTIKY ─────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card primary">
                <div class="text-muted small">Tréninků celkem</div>
                <div class="fs-3 fw-bold text-primary"><?= (int)$statCelkemTr ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card success">
                <div class="text-muted small">Měření celkem</div>
                <div class="fs-3 fw-bold text-success"><?= (int)$statCelkemMereni ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card warning">
                <div class="text-muted small">Tréninků v období</div>
                <div class="fs-3 fw-bold" style="color:#f59e0b;"><?= $aktualniObdobi ? (int)$pocetTrObdobi : '—' ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card info">
                <div class="text-muted small">Poslední trénink</div>
                <div class="fw-semibold" style="color:#0ea5e9;"><?= $statPosledni ? fmtDate($statPosledni) : '—' ?></div>
            </div>
        </div>
    </div>

    <!-- TABS ───────────────────────────────────────────────────── -->
    <ul class="nav nav-tabs" id="detailTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-profil">
                <i class="bi bi-person-badge me-1"></i>Profil
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-treninky">
                <i class="bi bi-journal-text me-1"></i>Tréninky
                <span class="badge bg-secondary ms-1"><?= (int)$statCelkemTr ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mereni">
                <i class="bi bi-speedometer2 me-1"></i>Měření
                <span class="badge bg-secondary ms-1"><?= (int)$statCelkemMereni ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-zavody">
                <i class="bi bi-trophy me-1"></i>Závody
                <span class="badge bg-secondary ms-1"><?= (int)$statCelkemZavodu ?></span>
            </button>
        </li>
        <?php if (!empty($testy)): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-testy">
                <i class="bi bi-clipboard2-pulse me-1"></i>Zátěžové testy
                <span class="badge bg-secondary ms-1"><?= count($testy) ?></span>
            </button>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-plan">
                <i class="bi bi-calendar3-week me-1"></i>Nadcházející
                <?php if (!empty($nadchazejiciTreninky)): ?>
                    <span class="badge bg-primary ms-1"><?= count($nadchazejiciTreninky) ?></span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white p-4 shadow-sm mb-4" id="detailTabsContent">

        <!-- ═══ PROFIL ══════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-profil">

            <h5 class="mb-3"><?= $jmeno ?></h5>

            <!-- Skupiny / podskupiny -->
            <?php if (!empty($skupinyMembership)): ?>
            <div class="mb-4">
                <div class="fw-semibold text-muted small mb-2">TRÉNINKOVÉ SKUPINY</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php
                    $grouped = [];
                    foreach ($skupinyMembership as $row) {
                        $grouped[$row['skupina_nazev']][] = $row['podskupina_nazev'];
                    }
                    foreach ($grouped as $sk => $ps):
                    ?>
                        <span class="badge bg-primary fs-6 fw-normal px-3 py-2">
                            <?= h($sk) ?>
                            <span class="text-white-50 ms-1">›</span>
                            <?= h(implode(', ', $ps)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Editace profilu -->
            <form id="profileForm" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action"       value="save_profile">
                <input type="hidden" name="sportovec_id" value="<?= (int)$sportovecId ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">UCI ID</label>
                        <input type="text" name="uciid" class="form-control"
                               value="<?= h($sportovec['uciid'] ?? '') ?>"
                               placeholder="napr. 10004832198">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= h($sportovec['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kategorie</label>
                        <input type="text" name="kategorie" class="form-control"
                               value="<?= h($katValue) ?>"
                               placeholder="napr. Elite, U23…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Datum narození</label>
                        <input type="date" name="narozeni" class="form-control"
                               value="<?= h($narozeniValue) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Oddíl / tým</label>
                        <input type="text" name="oddil" class="form-control"
                               value="<?= h($sportovec['oddil'] ?? '') ?>">
                    </div>

                    <div class="col-12 d-flex gap-2 align-items-center flex-wrap">
                        <button class="btn btn-primary" id="saveProfileBtn" type="submit">
                            <span class="profile-save-label">
                                <i class="bi bi-floppy me-1"></i>Uložit profil
                            </span>
                            <span class="profile-save-spinner spinner-border spinner-border-sm" role="status"></span>
                        </button>
                        <span id="saveProfileStatus" class="text-success small" style="display:none;">
                            <i class="bi bi-check-circle me-1"></i>Uloženo
                        </span>

                        <?php if (!empty($sportovec['hash'])): ?>
                        <a class="btn btn-outline-secondary ms-auto"
                           href="sportovec_treninky.php?hash=<?= urlencode($sportovec['hash']) ?>"
                           target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Veřejná karta
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($sportovec['hash'])): ?>
                    <div class="col-12 text-muted small">
                        Odkaz pro sportovce:
                        <code>sportovec_treninky.php?hash=<?= h($sportovec['hash']) ?></code>
                    </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Kredit -->
            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-coin me-1 text-warning"></i>Aktuální kredit</h6>
            <?php if ($aktualniObdobi): ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Období od</div>
                        <div class="fw-semibold"><?= fmtDate($aktualniObdobi['datum_od']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Tréninků v období</div>
                        <div class="fw-semibold"><?= (int)$pocetTrObdobi ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Sazba / trénink</div>
                        <div class="fw-semibold"><?= number_format($sazbaVObdobi, 2, ',', ' ') ?> Kč</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Kredit</div>
                        <div class="fw-bold fs-5 text-success"><?= number_format($castkaObdobi, 2, ',', ' ') ?> Kč</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    Není otevřené období v <code>sportovec_obdobi</code>. Kredit se nepočítá.
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ TRÉNINKY ════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-treninky">
            <?php if (empty($treninky)): ?>
                <div class="alert alert-info">Sportovec nemá žádné evidované tréninky.</div>
            <?php else: ?>
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <span class="badge bg-light text-dark border fs-6"><?= (int)$statCelkemTr ?> tréninků</span>
                    <input type="text" id="trFilterInput" class="form-control form-control-sm ms-auto"
                           style="max-width:220px;" placeholder="Filtr (datum, náplň…)">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="treninkyTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:110px;">Datum</th>
                                <th style="width:65px;">Délka</th>
                                <th style="width:85px;">Kategorie</th>
                                <th>Náplň</th>
                                <th style="width:80px;" class="text-center">Měření</th>
                                <th style="width:90px;" class="text-center">Poznámka</th>
                                <th style="width:90px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($treninky as $tr):
                            $trKat = $tr['kategorie'] ?? '';
                            $trKm  = $kategorieMeta[$trKat] ?? null;
                        ?>
                            <tr>
                                <td class="fw-semibold"><?= fmtDate($tr['datum']) ?></td>
                                <td class="text-muted">
                                    <?= !empty($tr['delka']) ? h($tr['delka']) . ' h' : '—' ?>
                                </td>
                                <td>
                                    <?php if ($trKm): ?>
                                        <span class="badge bg-<?= $trKm['color'] ?>" title="<?= $trKm['label'] ?>"><i class="bi <?= $trKm['icon'] ?>"></i></span>
                                    <?php elseif ($trKat): ?>
                                        <span class="badge bg-secondary"><?= h($trKat) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= h(mb_strimwidth($tr['napln'] ?? '', 0, 70, '…', 'UTF-8')) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$tr['mereni_count'] > 0): ?>
                                        <span class="badge bg-info text-dark"><?= (int)$tr['mereni_count'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($tr['poznamka'])): ?>
                                        <span class="badge bg-light text-dark border" title="<?= h($tr['poznamka']) ?>">
                                            <i class="bi bi-chat-left-text"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_trenink.php?id=<?= (int)$tr['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ MĚŘENÍ ══════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-mereni">
            <?php if (empty($mereniSportovce)): ?>
                <div class="alert alert-info">Sportovec nemá žádná evidovaná měření.</div>
            <?php else: ?>
                <!-- Typ filter -->
                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button class="btn btn-sm btn-outline-secondary active mereni-filter-btn" data-typ="">
                        Vše (<?= count($mereniSportovce) ?>)
                    </button>
                    <?php
                    $typCounts = array_count_values(array_column($mereniSportovce, 'typ'));
                    $typLabels = ['kolo' => '🚴 Kolo', 'kolo_krouzek' => '🔄 Kroužek', 'kolo_silnice' => '🛣️ Silnice', 'kolo_mtb' => '🌲 MTB', 'beh' => '🏃 Běh', 'posilovna' => '💪 Posilovna'];
                    foreach ($typCounts as $typ => $cnt):
                    ?>
                    <button class="btn btn-sm btn-outline-secondary mereni-filter-btn" data-typ="<?= h($typ) ?>">
                        <?= $typLabels[$typ] ?? h($typ) ?> (<?= (int)$cnt ?>)
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="mereniTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:105px;">Datum</th>
                                <th style="width:90px;">Typ</th>
                                <th>Hodnoty</th>
                                <th style="width:220px;">Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mereniSportovce as $mz): ?>
                            <tr data-typ="<?= h($mz['typ'] ?? '') ?>">
                                <td class="text-muted small"><?= fmtDate($mz['trenink_datum'] ?? '') ?></td>
                                <td>
                                    <?php
                                    $typBadge = ['kolo' => 'mereni-typ-kolo', 'kolo_krouzek' => 'mereni-typ-kolo_krouzek', 'kolo_silnice' => 'mereni-typ-kolo_silnice', 'kolo_mtb' => 'mereni-typ-kolo_mtb', 'beh' => 'mereni-typ-beh', 'posilovna' => 'mereni-typ-posilovna'];
                                    $cls = $typBadge[$mz['typ'] ?? ''] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $cls ?>"><?= h($mz['typ'] ?? '') ?></span>
                                </td>
                                <td><?= renderMereniData($mz) ?></td>
                                <td class="text-muted small"><?= nl2br(h($mz['mereni_poznamka'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ ZÁVODY ═════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-zavody">
            <?php
            $zavKatMeta = [
                'silnice' => ['label'=>'Silnice', 'color'=>'success', 'icon'=>'bi-bicycle'],
                'draha'   => ['label'=>'Dráha',   'color'=>'primary', 'icon'=>'bi-stopwatch'],
                'mtb'     => ['label'=>'MTB',      'color'=>'warning', 'icon'=>'bi-tree'],
            ];
            if (empty($zavodySpotovce)): ?>
                <div class="alert alert-info">Sportovec nemá žádné evidované závody.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Datum</th>
                                <th>Kategorie</th>
                                <th>Závod</th>
                                <th>Pořadí</th>
                                <th>Čas</th>
                                <th>Body</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($zavodySpotovce as $zav):
                            $zkat = $zav['kategorie'] ?? 'silnice';
                            $zmeta = $zavKatMeta[$zkat] ?? $zavKatMeta['silnice'];
                        ?>
                            <tr>
                                <td class="text-nowrap"><?= h(date('d.m.Y', strtotime($zav['datum']))) ?></td>
                                <td>
                                    <span class="badge bg-<?= $zmeta['color'] ?>">
                                        <i class="bi <?= $zmeta['icon'] ?> me-1"></i><?= $zmeta['label'] ?>
                                    </span>
                                </td>
                                <td><?= h($zav['popis']) ?></td>
                                <td><?= $zav['poradi'] !== null ? '<strong>'.h($zav['poradi']).'.</strong>' : '<span class="text-muted">—</span>' ?></td>
                                <td><?= h($zav['cas'] ?? '—') ?: '—' ?></td>
                                <td><?= $zav['body'] !== null ? h($zav['body']) : '—' ?></td>
                                <td>
                                    <a href="zavod_detail.php?id=<?= (int)$zav['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Detail závodu">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ ZÁTĚŽOVÉ TESTY ══════════════════════════════════════ -->
        <?php if (!empty($testy)): ?>
        <div class="tab-pane fade" id="tab-testy">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Datum</th>
                            <th>Věk</th>
                            <th>Váha</th>
                            <th>Výška</th>
                            <th>Popis pro sportovce</th>
                            <th>Interní popis</th>
                            <th>Soubory</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($testy as $t):
                        $tid   = (int)$t['id'];
                        $files = $souboryByTest[$tid] ?? [];
                        $popisSport   = $t['popis_sportovec'] ?? '';
                        $popisInterni = $t['popis_interni'] ?? $t['popis_trener'] ?? ($t['popis'] ?? '');
                    ?>
                        <tr>
                            <td><?= fmtDate($t['datum']) ?></td>
                            <td><?= h($t['vek'] ?? '') ?></td>
                            <td><?= !empty($t['vaha_kg']) ? h($t['vaha_kg']) . ' kg' : '—' ?></td>
                            <td><?= !empty($t['vyska_cm']) ? h($t['vyska_cm']) . ' cm' : '—' ?></td>
                            <td class="small"><?= nl2br(h($popisSport)) ?></td>
                            <td class="small text-muted"><?= nl2br(h($popisInterni)) ?></td>
                            <td>
                                <?php if (!empty($files)): ?>
                                    <?php foreach ($files as $f):
                                        $path  = $f['cesta'] ?? '';
                                        $nazev = $f['nazev'] ?? basename($path);
                                        $downloadUrl = 'private_download.php?kind=stress&amp;id=' . (int)$f['id'];
                                        $isImg = in_array($f['typ'] ?? '', ['public_img','internal_img','img'], true);
                                    ?>
                                        <?php if ($path): ?>
                                            <?php if ($isImg): ?>
                                                <a href="<?= $downloadUrl ?>" target="_blank" rel="noopener">
                                                    <img src="<?= $downloadUrl ?>" alt="<?= h($nazev) ?>" class="thumb-test">
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $downloadUrl ?>" target="_blank" rel="noopener">📎 <?= h($nazev) ?></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ NADCHÁZEJÍCÍ TRÉNINKY ═══════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-plan">
            <?php
            $planKatMeta = [
                'silnice'   => ['label'=>'Silnice',   'color'=>'success', 'icon'=>'bi-bicycle'],
                'mtb'       => ['label'=>'MTB',        'color'=>'warning', 'icon'=>'bi-tree'],
                'draha'     => ['label'=>'Dráha',      'color'=>'primary', 'icon'=>'bi-stopwatch'],
                'cyklokros' => ['label'=>'Cyklokros',  'color'=>'orange',  'icon'=>'bi-tornado'],
                'posilovna' => ['label'=>'Posilovna',  'color'=>'danger',  'icon'=>'bi-trophy'],
                'atletika'  => ['label'=>'Atletika',   'color'=>'info',    'icon'=>'bi-person-walking'],
                'cviceni'   => ['label'=>'Cvičení',    'color'=>'secondary','icon'=>'bi-heart-pulse'],
                'plavani'   => ['label'=>'Plavání',    'color'=>'teal',    'icon'=>'bi-water'],
            ];
            ?>
            <?php if (empty($nadchazejiciTreninky)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-calendar3 me-2"></i>Žádné plánované tréninky v nadcházejících 30 záznamech pro skupiny tohoto sportovce.
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Zobrazeny nadcházející plánované tréninky ze skupin, do nichž je sportovec zařazen (max. 30).
                    <a href="planovac.php" class="ms-2"><i class="bi bi-calendar3-week me-1"></i>Otevřít plánovač</a>
                </p>
                <?php
                // Seskupit tréninky dle data
                $planByDate = [];
                foreach ($nadchazejiciTreninky as $pt) {
                    $planByDate[$pt['datum']][] = $pt;
                }
                ?>
                <?php foreach ($planByDate as $datum => $dayPlans): ?>
                    <?php
                    $ts = strtotime($datum);
                    $denCz = ['Ne','Po','Út','St','Čt','Pá','So'];
                    $denLabel = $denCz[date('w', $ts)] . ' ' . date('j. n. Y', $ts);
                    $isToday = ($datum === date('Y-m-d'));
                    ?>
                    <div class="mb-3">
                        <div class="fw-semibold text-muted small mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-calendar-date"></i>
                            <?= h($denLabel) ?>
                            <?php if ($isToday): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.65rem">Dnes</span>
                            <?php endif; ?>
                        </div>
                        <?php foreach ($dayPlans as $pt): ?>
                            <?php
                            $meta = $planKatMeta[$pt['kategorie'] ?? ''] ?? null;
                            $cas = '';
                            if (!empty($pt['cas_od'])) {
                                $cas = substr($pt['cas_od'], 0, 5);
                                if (!empty($pt['cas_do'])) $cas .= '–' . substr($pt['cas_do'], 0, 5);
                            }
                            ?>
                            <div class="card border-start border-4 border-primary shadow-sm mb-2" style="border-radius:.5rem">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                        <div class="flex-grow-1" style="min-width:0">
                                            <?php if ($meta): ?>
                                                <span class="badge bg-<?= $meta['color'] ?> me-1" style="font-size:.65rem">
                                                    <i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
                                                </span>
                                            <?php endif; ?>
                                            <strong><?= h($pt['nazev'] ?: 'Trénink') ?></strong>

                                            <div class="small text-muted mt-1 d-flex flex-wrap gap-2">
                                                <?php if ($cas): ?>
                                                    <span><i class="bi bi-clock me-1"></i><?= h($cas) ?></span>
                                                <?php endif; ?>
                                                <span><i class="bi bi-people me-1"></i><?= h($pt['skupina_nazev']) ?>
                                                    <?php if (!empty($pt['podskupina_nazev'])): ?>
                                                        / <?= h($pt['podskupina_nazev']) ?>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if (!empty($pt['sport_nazev'])): ?>
                                                    <span><i class="bi bi-geo-alt me-1"></i><?= h($pt['sport_nazev']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($pt['trener_jmeno'])): ?>
                                                    <span><i class="bi bi-person me-1"></i><?= h($pt['trener_jmeno']) ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($pt['popis'])): ?>
                                                <div class="small text-muted mt-1"><?= nl2br(h($pt['popis'])) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                                            <?php if (!empty($pt['trenink_id'])): ?>
                                                <a href="edit_trenink.php?id=<?= (int)$pt['trenink_id'] ?>"
                                                   class="btn btn-sm btn-outline-success" title="Detail evidence">
                                                    <i class="bi bi-check2-circle me-1"></i>Evidováno
                                                </a>
                                            <?php else: ?>
                                                <a href="formular.php?plan_id=<?= (int)$pt['id'] ?>"
                                                   class="btn btn-sm btn-outline-primary" title="Zadat evidenci z plánu">
                                                    <i class="bi bi-pencil-square me-1"></i>Zadat evidenci
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /tab-content -->

    <?php endif; // $sportovec ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    // ── Tab persistence přes URL hash ────────────────────────────────
    var hash = window.location.hash;
    if (hash) {
        var el = document.querySelector('#detailTabs button[data-bs-target="' + hash + '"]');
        if (el) bootstrap.Tab.getOrCreateInstance(el).show();
    }
    document.querySelectorAll('#detailTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            var target = e.target.getAttribute('data-bs-target');
            history.replaceState(null, '', target);
        });
    });

    // ── Live athlete search ─────────────────────────────────────────
    const searchInput  = document.getElementById('searchInput');
    const filterSkup   = document.getElementById('filterSkupina');
    const resultsWrap  = document.getElementById('searchResults');

    let searchTimer = null;

    function doSearch() {
        const q   = searchInput ? searchInput.value.trim() : '';
        const gid = filterSkup ? filterSkup.value : '';

        if (!q && !gid) { if (resultsWrap) resultsWrap.innerHTML = ''; return; }

        const url = 'sportovec_detail.php?ajax=search&q=' + encodeURIComponent(q) + '&skupina_id=' + encodeURIComponent(gid);

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!resultsWrap) return;
                if (!data || data.length === 0) {
                    resultsWrap.innerHTML = '<div class="alert alert-warning mt-2 mb-0">Žádný výsledek.</div>';
                    return;
                }
                let html = '<ul class="list-group mt-2">';
                data.forEach(r => {
                    html += `<li class="list-group-item list-group-item-action search-result-item d-flex justify-content-between align-items-center"
                                  onclick="location.href='sportovec_detail.php?id=${r.id}'">
                                <span><strong>${r.prijmeni || ''}</strong> ${r.jmeno || ''}</span>
                                <span class="text-muted small">${r.category || r.kategorie || ''}</span>
                             </li>`;
                });
                html += '</ul>';
                resultsWrap.innerHTML = html;
            })
            .catch(() => {});
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(doSearch, 300);
        });
    }
    if (filterSkup) filterSkup.addEventListener('change', doSearch);

    // ── AJAX profile save ───────────────────────────────────────────
    const profileForm = document.getElementById('profileForm');
    const saveBtn     = document.getElementById('saveProfileBtn');
    const saveStatus  = document.getElementById('saveProfileStatus');

    if (profileForm && saveBtn) {
        profileForm.addEventListener('submit', e => {
            e.preventDefault();
            saveBtn.classList.add('loading');
            saveBtn.disabled = true;
            if (saveStatus) saveStatus.style.display = 'none';

            fetch(profileForm.action || window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(profileForm)
            })
                .then(r => r.json())
                .then(data => {
                    saveBtn.classList.remove('loading');
                    saveBtn.disabled = false;
                    if (data.ok) {
                        if (saveStatus) { saveStatus.style.display = 'inline'; }
                        setTimeout(function() { if (saveStatus) saveStatus.style.display = 'none'; }, 3000);
                        if (typeof showToast === 'function') showToast('Profil uložen.', 'success');
                    } else {
                        var msgs = data.errors ? data.errors.join(', ') : 'Chyba při ukládání.';
                        if (typeof showToast === 'function') showToast(msgs, 'danger');
                        else alert(msgs);
                    }
                })
                .catch(function() {
                    saveBtn.classList.remove('loading');
                    saveBtn.disabled = false;
                    if (typeof showToast === 'function') showToast('Chyba sítě.', 'danger');
                    else alert('Chyba sítě.');
                });
        });
    }

    // ── Měření type filter ──────────────────────────────────────────
    document.querySelectorAll('.mereni-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mereni-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const typ  = btn.dataset.typ;
            document.querySelectorAll('#mereniTable tbody tr').forEach(row => {
                row.style.display = (!typ || row.dataset.typ === typ) ? '' : 'none';
            });
        });
    });

    // ── Tréninky quick filter ───────────────────────────────────────
    const trFilter = document.getElementById('trFilterInput');
    if (trFilter) {
        trFilter.addEventListener('input', () => {
            const q = trFilter.value.toLowerCase();
            document.querySelectorAll('#treninkyTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

})();
</script>
</body>
</html>
