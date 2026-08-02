<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('zavod_detail')) { header('Location: index.php'); exit; }
require_once 'db.php';

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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
    } elseif (in_array($typ, ['kolo_krouzek', 'kolo_silnice', 'kolo_mtb'])) {
        $seg = $mz['segment_nazev'] ?? ($mz['segment_id'] ? '#' . (int)$mz['segment_id'] : '—');
        $parts[] = '<strong>' . h($seg) . '</strong>';
        if (!empty($mz['cas'])) $parts[] = '⏱ ' . h($mz['cas']);
    } else {
        return '<span class="text-muted">—</span>';
    }
    return implode(' · ', $parts) ?: '<span class="text-muted">—</span>';
}

// ── Získání ID závodu ──────────────────────────────────────────────────────
$zavodId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($zavodId <= 0) {
    header('Location: prehled_zavodu.php');
    exit;
}

// ── 1) Načtení závodu ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT z.*,
           GROUP_CONCAT(DISTINCT tr.jmeno SEPARATOR ', ') AS trenere
    FROM zavody z
    LEFT JOIN zavod_trener zt ON z.id = zt.zavod_id
    LEFT JOIN treneri tr      ON zt.trener_id = tr.id
    WHERE z.id = ?
    GROUP BY z.id
");
$stmt->execute([$zavodId]);
$zavod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$zavod) {
    header('Location: prehled_zavodu.php');
    exit;
}

// ── 2) Účastníci / výsledky ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT zsp.*,
           COALESCE(CONCAT(sp.prijmeni, ' ', sp.jmeno), zsp.jmeno_ext) AS zobrazit_jmeno,
           sp.hash AS sp_hash
    FROM zavod_sportovec zsp
    LEFT JOIN sportovci sp ON sp.id = zsp.sportovec_id
    WHERE zsp.zavod_id = ?
    ORDER BY zsp.poradi IS NULL, zsp.poradi ASC, zobrazit_jmeno ASC
");
$stmt->execute([$zavodId]);
$ucastnici = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 3) Fotografie ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT soubor FROM zavod_fotka WHERE zavod_id = ?");
$stmt->execute([$zavodId]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 4) Importované soubory ─────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, soubor, typ FROM zavod_import WHERE zavod_id = ?");
$stmt->execute([$zavodId]);
$imports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 5) Měření ──────────────────────────────────────────────────────────────
$mereni = [];
try {
    $stmt = $pdo->prepare("
        SELECT mz.*,
               c.nazev   AS cvik_nazev,
               seg.nazev AS segment_nazev,
               CONCAT(sp.prijmeni, ' ', sp.jmeno) AS sp_jmeno
        FROM zavod_mereni zm
        JOIN mereni_zaznamy mz   ON mz.id = zm.mereni_id
        LEFT JOIN cviky c        ON c.id  = mz.cvik_id
        LEFT JOIN segmenty seg   ON seg.id = mz.segment_id
        LEFT JOIN sportovci sp   ON sp.id = mz.sportovec_id
        WHERE zm.zavod_id = ?
        ORDER BY zm.poradi
    ");
    $stmt->execute([$zavodId]);
    $mereni = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('zavod_detail mereni query failed: ' . $e->getMessage());
}

// ── Pomocné výpočty ────────────────────────────────────────────────────────
$pocetSVysledky = count(array_filter($ucastnici, fn($u) => $u['poradi'] !== null));

$kategorieMeta = [
    'silnice' => ['label' => 'Silnice', 'color' => 'success',  'icon' => 'bi-bicycle'],
    'draha'   => ['label' => 'Dráha',   'color' => 'primary',  'icon' => 'bi-stopwatch'],
    'mtb'     => ['label' => 'MTB',     'color' => 'warning',  'icon' => 'bi-tree'],
];

$katKey   = $zavod['kategorie'] ?? '';
$katMeta  = $kategorieMeta[$katKey] ?? ['label' => h($katKey), 'color' => 'secondary', 'icon' => 'bi-flag'];

$mereniTypMeta = [
    'kolo'         => ['label' => 'Kolo',     'color' => 'primary'],
    'beh'          => ['label' => 'Běh',      'color' => 'success'],
    'posilovna'    => ['label' => 'Posilovna','color' => 'danger'],
    'kolo_krouzek' => ['label' => 'Kroužek',  'color' => 'purple'],
    'kolo_silnice' => ['label' => 'Silnice',  'color' => 'danger'],
    'kolo_mtb'     => ['label' => 'MTB',      'color' => 'warning'],
];

$canEdit = canAccess('sprava_zavodu');

// Trenéři pro hero bary (split na badge)
$trenerList = [];
if (!empty($zavod['trenere'])) {
    $trenerList = array_map('trim', explode(', ', $zavod['trenere']));
    $trenerList = array_filter($trenerList);
}
$trenerColors = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($zavod['popis']) ?> — Detail závodu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ── Hero bar ───────────────────────────────────────────── */
        .hero-bar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 2rem 0 1.5rem;
        }
        .hero-bar .badge-category {
            font-size: 0.8rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: .4em .8em;
        }
        .hero-bar h1 {
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 700;
            margin-bottom: .4rem;
        }
        .hero-bar .meta-row {
            font-size: .9rem;
            opacity: .85;
        }

        /* ── Stats cards ────────────────────────────────────────── */
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
        }

        /* ── Photo grid ─────────────────────────────────────────── */
        .photo-thumb {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            border-radius: .5rem;
            cursor: pointer;
            transition: opacity .15s, transform .15s;
        }
        .photo-thumb:hover { opacity: .85; transform: scale(1.03); }

        /* ── Výsledková tabulka ─────────────────────────────────── */
        .result-gold   td:first-child { color: #c9a227; font-weight: 700; }
        .result-silver td:first-child { color: #a8a9ad; font-weight: 700; }
        .result-bronze td:first-child { color: #cd7f32; font-weight: 700; }

        /* ── Typ měření badge (nestandardní barvy) ──────────────── */
        .badge-purple { background-color: #6f42c1; color: #fff; }

        /* ── Popis / poznámka card ──────────────────────────────── */
        .popis-text { white-space: pre-wrap; word-break: break-word; }

        /* ── Print ──────────────────────────────────────────────── */
        @media print {
            .navbar, .hero-btns, .back-link, .btn { display: none !important; }
            .hero-bar { background: #fff !important; color: #000 !important; padding: .5rem 0; }
            .hero-bar .badge-category { border: 1px solid #999; color: #333 !important; }
            .card { break-inside: avoid; }
            .photo-thumb { max-height: 120px; }
        }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<!-- ═══════════════════════════════════════════════════════ HERO BAR ═══ -->
<div class="hero-bar no-print">
    <div class="container" style="max-width:1000px">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <!-- Left: meta + title -->
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-<?= h($katMeta['color']) ?> badge-category">
                        <i class="bi <?= h($katMeta['icon']) ?> me-1"></i><?= h($katMeta['label']) ?>
                    </span>
                    <span class="meta-row">
                        <i class="bi bi-calendar3 me-1"></i><?= fmtDate($zavod['datum']) ?>
                    </span>
                </div>
                <h1><?= h($zavod['popis']) ?></h1>
                <?php if (!empty($trenerList)): ?>
                <div class="d-flex flex-wrap gap-1 mt-1">
                    <?php foreach ($trenerList as $idx => $jmeno): ?>
                        <span class="badge bg-<?= $trenerColors[$idx % count($trenerColors)] ?> bg-opacity-75">
                            <i class="bi bi-person me-1"></i><?= h($jmeno) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- Right: action buttons -->
            <div class="hero-btns d-flex flex-wrap gap-2 align-items-start pt-1">
                <?php if ($canEdit): ?>
                <a href="edit_zavod_form.php?id=<?= $zavodId ?>" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-pencil me-1"></i>Upravit
                </a>
                <?php endif; ?>
                <a href="prehled_zavodu.php" class="btn btn-sm btn-secondary back-link">
                    <i class="bi bi-arrow-left me-1"></i>Zpět na přehled
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════ MAIN CONTENT ═══ -->
<div class="container mt-4 mb-5" style="max-width:1000px">

    <!-- ── Stats cards ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="stat-value text-primary"><?= count($ucastnici) ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-people me-1"></i>Závodníků</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="stat-value text-success"><?= $pocetSVysledky ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-trophy me-1"></i>S výsledky</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="stat-value text-info"><?= count($imports) ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-file-earmark me-1"></i>Souborů</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="stat-value text-warning"><?= count($mereni) ?></div>
                    <div class="text-muted small mt-1"><i class="bi bi-activity me-1"></i>Měření</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Odkaz na výsledky online ─────────────────────────────────── -->
    <?php if (!empty($zavod['url_vysledky'])): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-link-45deg fs-5 flex-shrink-0"></i>
        <div>
            <strong>Výsledky online:</strong>
            <a href="<?= h($zavod['url_vysledky']) ?>" target="_blank" rel="noopener"
               class="ms-2 alert-link text-break">
                <?= h($zavod['url_vysledky']) ?>
                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Two-column layout ─────────────────────────────────────────── -->
    <div class="row g-4">

        <!-- LEFT col ══════════════════════════════════════════════════ -->
        <div class="col-lg-8">

            <!-- Výsledková tabulka -->
            <?php if (!empty($ucastnici)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-list-ol text-primary fs-5"></i>
                    <h5 class="mb-0">Výsledková listina</h5>
                    <span class="badge bg-secondary ms-auto"><?= count($ucastnici) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:60px">Pořadí</th>
                                    <th>Jméno</th>
                                    <th>Klub</th>
                                    <th>Kategorie</th>
                                    <th>Čas</th>
                                    <th class="pe-3">Body</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ucastnici as $idx => $u):
                                $poradi = $u['poradi'];
                                $rowClass = '';
                                if ($poradi == 1) $rowClass = 'result-gold';
                                elseif ($poradi == 2) $rowClass = 'result-silver';
                                elseif ($poradi == 3) $rowClass = 'result-bronze';
                            ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="ps-3 fw-semibold">
                                        <?php if ($poradi == 1): ?>
                                            <i class="bi bi-trophy-fill text-warning me-1"></i>1
                                        <?php elseif ($poradi == 2): ?>
                                            <i class="bi bi-award-fill me-1" style="color:#a8a9ad"></i>2
                                        <?php elseif ($poradi == 3): ?>
                                            <i class="bi bi-award-fill me-1" style="color:#cd7f32"></i>3
                                        <?php else: ?>
                                            <?= $poradi !== null ? h($poradi) : '<span class="text-muted">—</span>' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['sportovec_id']) && !empty($u['sp_hash'])): ?>
                                            <a href="sportovec_detail.php?id=<?= (int)$u['sportovec_id'] ?>"
                                               class="text-decoration-none fw-semibold">
                                                <?= h($u['zobrazit_jmeno']) ?>
                                            </a>
                                        <?php elseif (!empty($u['jmeno_ext'])): ?>
                                            <span class="fw-semibold"><?= h($u['zobrazit_jmeno']) ?></span>
                                            <span class="badge bg-secondary bg-opacity-25 text-secondary ms-1 small">extern</span>
                                        <?php else: ?>
                                            <span class="fw-semibold"><?= h($u['zobrazit_jmeno']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= !empty($u['klub']) ? h($u['klub']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['kategorie_start'])): ?>
                                            <span class="badge bg-light text-dark border"><?= h($u['kategorie_start']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-monospace small">
                                        <?= !empty($u['cas']) ? h($u['cas']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td class="pe-3 fw-semibold">
                                        <?= ($u['body'] !== null && $u['body'] !== '') ? h($u['body']) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Měření výkonů -->
            <?php if (!empty($mereni)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-activity text-warning fs-5"></i>
                    <h5 class="mb-0">Měření výkonů</h5>
                    <span class="badge bg-secondary ms-auto"><?= count($mereni) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Sportovec</th>
                                    <th>Typ</th>
                                    <th class="pe-3">Data</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($mereni as $mz):
                                $typKey = $mz['typ'] ?? '';
                                $typMeta = $mereniTypMeta[$typKey] ?? ['label' => h($typKey), 'color' => 'secondary'];
                                $badgeClass = $typMeta['color'] === 'purple' ? 'badge-purple' : 'bg-' . $typMeta['color'];
                            ?>
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        <?= !empty($mz['sp_jmeno']) && trim($mz['sp_jmeno']) !== ' '
                                            ? h($mz['sp_jmeno'])
                                            : '<span class="text-muted small">—</span>' ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>"><?= h($typMeta['label']) ?></span>
                                    </td>
                                    <td class="pe-3 small">
                                        <?= renderMereniData($mz) ?>
                                        <?php if (!empty($mz['poznamka'])): ?>
                                            <div class="text-muted mt-1 fst-italic"><?= h($mz['poznamka']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Popis závodu -->
            <?php if (!empty($zavod['popis'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-file-text text-secondary fs-5"></i>
                    <h5 class="mb-0">Popis závodu</h5>
                </div>
                <div class="card-body">
                    <p class="popis-text mb-0"><?= h($zavod['popis']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Interní poznámka -->
            <?php if (!empty($zavod['poznamka'])): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-warning mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-lock text-warning fs-5"></i>
                    <h6 class="mb-0 text-muted">Interní poznámka</h6>
                </div>
                <div class="card-body py-2">
                    <p class="popis-text mb-0 text-secondary small"><?= h($zavod['poznamka']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Prázdný stav — žádné záznamy -->
            <?php if (empty($ucastnici) && empty($mereni) && empty($zavod['poznamka'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">Zatím nejsou žádné záznamy k tomuto závodu.</p>
                    <?php if ($canEdit): ?>
                        <a href="edit_zavod_form.php?id=<?= $zavodId ?>" class="btn btn-outline-primary btn-sm mt-3">
                            <i class="bi bi-pencil me-1"></i>Přidat záznamy
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /col-lg-8 -->

        <!-- RIGHT col ═════════════════════════════════════════════════ -->
        <div class="col-lg-4">

            <!-- Fotografie -->
            <?php if (!empty($photos)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-images text-info fs-5"></i>
                    <h5 class="mb-0">Fotografie</h5>
                    <span class="badge bg-secondary ms-auto"><?= count($photos) ?></span>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($photos as $photo): ?>
                        <div class="col-6">
                            <a href="nahrane_zavody/<?= h($photo['soubor']) ?>" target="_blank" rel="noopener"
                               title="Otevřít v novém okně">
                                <img src="nahrane_zavody/<?= h($photo['soubor']) ?>"
                                     alt="Foto závodu"
                                     class="photo-thumb shadow-sm"
                                     loading="lazy">
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Soubory s výsledky -->
            <?php if (!empty($imports)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-folder2-open text-success fs-5"></i>
                    <h5 class="mb-0">Soubory s výsledky</h5>
                    <span class="badge bg-secondary ms-auto"><?= count($imports) ?></span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($imports as $imp):
                            $ext = strtolower($imp['typ'] ?? pathinfo($imp['soubor'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['pdf'])) {
                                $fileIcon = 'bi-file-earmark-pdf-fill';
                                $fileColor = 'text-danger';
                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                $fileIcon = 'bi-file-earmark-spreadsheet-fill';
                                $fileColor = 'text-success';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $fileIcon = 'bi-file-earmark-word-fill';
                                $fileColor = 'text-primary';
                            } elseif (in_array($ext, ['csv'])) {
                                $fileIcon = 'bi-file-earmark-text-fill';
                                $fileColor = 'text-secondary';
                            } else {
                                $fileIcon = 'bi-file-earmark-fill';
                                $fileColor = 'text-muted';
                            }
                            $displayName = basename($imp['soubor']);
                        ?>
                        <li class="list-group-item d-flex align-items-center gap-2 px-3 py-2">
                            <i class="bi <?= $fileIcon ?> fs-5 <?= $fileColor ?> flex-shrink-0"></i>
                            <a href="download_import.php?id=<?= (int)$imp['id'] ?>"
                               class="text-decoration-none text-truncate flex-grow-1 small"
                               title="<?= h($displayName) ?>">
                                <?= h($displayName) ?>
                            </a>
                            <a href="download_import.php?id=<?= (int)$imp['id'] ?>"
                               class="btn btn-outline-secondary btn-sm py-0 px-1 flex-shrink-0"
                               title="Stáhnout">
                                <i class="bi bi-download"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info karta — základní údaje o závodu -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-info-circle text-primary fs-5"></i>
                    <h5 class="mb-0">Info</h5>
                </div>
                <div class="card-body py-2">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Datum</dt>
                        <dd class="col-7 mb-1 fw-semibold"><?= fmtDate($zavod['datum']) ?></dd>

                        <dt class="col-5 text-muted fw-normal">Kategorie</dt>
                        <dd class="col-7 mb-1">
                            <span class="badge bg-<?= h($katMeta['color']) ?>">
                                <i class="bi <?= h($katMeta['icon']) ?> me-1"></i><?= h($katMeta['label']) ?>
                            </span>
                        </dd>

                        <?php if (!empty($trenerList)): ?>
                        <dt class="col-5 text-muted fw-normal">Trenér(i)</dt>
                        <dd class="col-7 mb-1">
                            <?php foreach ($trenerList as $idx => $jmeno): ?>
                                <span class="badge bg-<?= $trenerColors[$idx % count($trenerColors)] ?> bg-opacity-75 me-1 mb-1">
                                    <?= h($jmeno) ?>
                                </span>
                            <?php endforeach; ?>
                        </dd>
                        <?php endif; ?>

                        <dt class="col-5 text-muted fw-normal">Závodníků</dt>
                        <dd class="col-7 mb-1 fw-semibold"><?= count($ucastnici) ?></dd>

                        <?php if ($pocetSVysledky > 0): ?>
                        <dt class="col-5 text-muted fw-normal">S výsledky</dt>
                        <dd class="col-7 mb-1 fw-semibold text-success"><?= $pocetSVysledky ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php if ($canEdit): ?>
                <div class="card-footer bg-white border-top text-end py-2">
                    <a href="edit_zavod_form.php?id=<?= $zavodId ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Upravit závod
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Back link (small screens, below sidebar) -->
            <div class="d-lg-none">
                <a href="prehled_zavodu.php" class="btn btn-outline-secondary w-100 back-link">
                    <i class="bi bi-arrow-left me-1"></i>Zpět na přehled závodů
                </a>
            </div>

        </div><!-- /col-lg-4 -->
    </div><!-- /row -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
