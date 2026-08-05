<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('sprava_zavodu')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$currentTrener = (int)$_SESSION['trener_id'];

// ── Validace ID ──────────────────────────────────────────────────────────────
$zavodId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($zavodId <= 0) {
    header('Location: sprava_zavodu.php');
    exit;
}

// ── Načtení existujících dat ─────────────────────────────────────────────────
$zavod = null;
try {
    $stmt = $pdo->prepare("SELECT z.* FROM zavody z WHERE z.id = ?");
    $stmt->execute([$zavodId]);
    $zavod = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('edit_zavod_form.php fetch zavod: ' . $e->getMessage());
}
if (!$zavod) {
    header('Location: sprava_zavodu.php');
    exit;
}

try {
    $stmtET = $pdo->prepare("SELECT trener_id FROM zavod_trener WHERE zavod_id = ?");
    $stmtET->execute([$zavodId]);
    $existingTrenere = $stmtET->fetchAll(PDO::FETCH_COLUMN);
    $existingTrenere = array_map('intval', $existingTrenere);
} catch (PDOException $e) {
    $existingTrenere = [];
}

try {
    $stmtSk = $pdo->prepare("SELECT skupina_id FROM zavod_skupina WHERE zavod_id = ? LIMIT 1");
    $stmtSk->execute([$zavodId]);
    $existingSkupina = (int)($stmtSk->fetchColumn() ?: 0);
} catch (PDOException $e) {
    $existingSkupina = 0;
}

try {
    $stmtPs = $pdo->prepare("SELECT podskupina_id FROM zavod_podskupina WHERE zavod_id = ?");
    $stmtPs->execute([$zavodId]);
    $existingPodskupiny = array_map('intval', $stmtPs->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    $existingPodskupiny = [];
}

try {
    $stmtU = $pdo->prepare(
        "SELECT zsp.sportovec_id, CONCAT(sp.prijmeni,' ',sp.jmeno) AS label
         FROM zavod_sportovec zsp
         JOIN sportovci sp ON sp.id = zsp.sportovec_id
         WHERE zsp.zavod_id = ? AND zsp.sportovec_id IS NOT NULL
         ORDER BY label"
    );
    $stmtU->execute([$zavodId]);
    $existingUcastnici = $stmtU->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $existingUcastnici = [];
}

try {
    $stmtM = $pdo->prepare(
        "SELECT mz.*
         FROM zavod_mereni zm
         JOIN mereni_zaznamy mz ON mz.id = zm.mereni_id
         WHERE zm.zavod_id = ?
         ORDER BY zm.poradi"
    );
    $stmtM->execute([$zavodId]);
    $existingMereni = $stmtM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $existingMereni = [];
}

// Sportovci jménopro mereni prefill
$mereniSportovciIds = array_filter(array_unique(array_column($existingMereni, 'sportovec_id')));
$mereniSportovciMap = [];
if (!empty($mereniSportovciIds)) {
    try {
        $in = implode(',', array_map('intval', $mereniSportovciIds));
        $rows = $pdo->query("SELECT id, CONCAT(prijmeni,' ',jmeno) AS label FROM sportovci WHERE id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $mereniSportovciMap[(int)$r['id']] = $r['label'];
        }
    } catch (PDOException $e) {}
}

try {
    $stmtF = $pdo->prepare("SELECT id, soubor FROM zavod_fotka WHERE zavod_id = ?");
    $stmtF->execute([$zavodId]);
    $existingFotky = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $existingFotky = [];
}

try {
    $stmtI = $pdo->prepare("SELECT id, soubor, typ FROM zavod_import WHERE zavod_id = ?");
    $stmtI->execute([$zavodId]);
    $existingImports = $stmtI->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $existingImports = [];
}

// ── Načtení seznamů ──────────────────────────────────────────────────────────
try {
    $skupiny     = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi")->fetchAll(PDO::FETCH_ASSOC);
    $trenereList = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('edit_zavod_form.php lists: ' . $e->getMessage());
    $skupiny     = $skupiny     ?? [];
    $trenereList = $trenereList ?? [];
}

try {
    $cviky = $pdo->query("SELECT id, nazev FROM cviky ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $cviky = []; }

try {
    $segmenty = $pdo->query("SELECT id, nazev, kategorie FROM segmenty WHERE aktivni = 1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $segmenty = []; }

// ── JS prefill data ──────────────────────────────────────────────────────────
$ucastniciPairs = [];
$ucastniciJS    = [];
foreach ($existingUcastnici as $u) {
    $sid   = (int)$u['sportovec_id'];
    $label = trim($u['label']);
    $ucastniciPairs[] = $sid . ':' . $label;
    $ucastniciJS[]    = ['id' => $sid, 'label' => $label];
}
$ucastniciHidden = implode(', ', $ucastniciPairs);

// Měření pro JS prefill — přidáme sportovec_label
$mereniInitData = [];
foreach ($existingMereni as $mz) {
    $sid   = $mz['sportovec_id'] ? (int)$mz['sportovec_id'] : null;
    $label = $sid ? ($mereniSportovciMap[$sid] ?? '') : '';
    $mereniInitData[] = [
        'typ'            => $mz['typ'] ?? '',
        'sportovec_id'   => $sid,
        'sportovec_label'=> $label,
        'vzdalenost'     => $mz['vzdalenost'] ?? '',
        'distance_unit'  => $mz['distance_unit'] ?? '',
        'cas'            => $mz['cas'] ?? '',
        'prevod'         => $mz['prevod'] ?? '',
        'cvik_id'        => $mz['cvik_id'] ?? '',
        'segment_id'     => $mz['segment_id'] ?? '',
        'vaha'           => $mz['vaha'] ?? '',
        'opakovani'      => $mz['opakovani'] ?? '',
        'rpe'            => $mz['rpe'] ?? '',
        'poznamka'       => $mz['poznamka'] ?? '',
    ];
}
$mereniInitJson = json_encode($mereniInitData, JSON_UNESCAPED_UNICODE);

$kategorieMeta = [
    'silnice' => ['label' => 'Silnice', 'color' => 'success',  'icon' => 'bi-bicycle'],
    'draha'   => ['label' => 'Dráha',   'color' => 'primary',  'icon' => 'bi-stopwatch'],
    'mtb'     => ['label' => 'MTB',     'color' => 'warning',  'icon' => 'bi-tree'],
];

$titleDate = $zavod['datum'] ? date('d.m.Y', strtotime($zavod['datum'])) : '';

// Flash zprávy
$flashError   = $_SESSION['flash_error']   ?? null; unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upravit závod — <?= h($titleDate) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }

        .section-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            font-size: .92rem;
            letter-spacing: .02em;
            padding: .6rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-card .card-body { padding: 1.1rem; }

        .hero-card {
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }

        .suggest-box {
            z-index: 1100;
            max-height: 220px;
            overflow-y: auto;
            width: 100%;
            top: 100%;
            left: 0;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            background: #0d6efd;
            color: #fff;
            font-size: .88rem;
        }
        .chip .btn-close { filter: invert(1); opacity: .9; }

        .mereni-row {
            border: 1px solid #e0e4ea;
            border-radius: 10px;
            padding: .85rem;
            background: #fff;
        }
        .mereni-row + .mereni-row { margin-top: .65rem; }

        .mini-muted { font-size: .84rem; color: #6c757d; }

        .submit-bar {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            background: #fff;
            padding: 1rem 1.25rem;
        }

        .existing-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .existing-file-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .4rem .6rem;
            border: 1px solid #e0e4ea;
            border-radius: 8px;
            background: #fff;
            margin-bottom: .4rem;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container py-4">

    <!-- Hero banner -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold fs-5">
                    <i class="bi bi-pencil-square me-2 opacity-75"></i>Úprava závodu
                </div>
                <div class="opacity-75 small">Upravujete záznam ze dne <?= h($titleDate) ?></div>
            </div>
            <div>
                <a href="zavod_detail.php?id=<?= $zavodId ?>" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Zpět na detail
                </a>
            </div>
        </div>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Chyba:</strong> <?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form id="zavodForm" action="update_zavod.php" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $zavodId ?>">
    <div class="row g-3">

        <!-- ═══════════ LEVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-8">

            <!-- SEKCE 1: Základní informace -->
            <div class="card section-card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i>Základní informace
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="datum" class="form-label fw-semibold req">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Datum
                            </label>
                            <input type="date" name="datum" id="datum" class="form-control"
                                   value="<?= h($zavod['datum']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="kategorie" class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>Kategorie
                            </label>
                            <select name="kategorie" id="kategorie" class="form-select">
                                <?php foreach ($kategorieMeta as $val => $meta): ?>
                                <option value="<?= h($val) ?>" <?= ($zavod['kategorie'] === $val ? 'selected' : '') ?>><?= h($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="popis" class="form-label fw-semibold req">
                            <i class="bi bi-card-text me-1 text-primary"></i>Popis závodu
                            <span class="badge bg-success fw-normal ms-1">veřejný</span>
                        </label>
                        <textarea name="popis" id="popis" rows="4" class="form-control"
                                  placeholder="Název závodu, místo konání, kategorie…" required><?= h($zavod['popis'] ?? '') ?></textarea>
                    </div>
                    <div class="mt-3">
                        <label for="poznamka" class="form-label fw-semibold">
                            <i class="bi bi-chat-left-text me-1 text-secondary"></i>Interní poznámka
                            <span class="badge bg-secondary fw-normal ms-1">neveřejné</span>
                        </label>
                        <textarea name="poznamka" id="poznamka" rows="2" class="form-control"
                                  placeholder="Poznámka pro trenéry"><?= h($zavod['poznamka'] ?? '') ?></textarea>
                        <div class="mini-muted mt-1">
                            <i class="bi bi-eye-slash me-1"></i>Nezobrazuje se na kartě sportovce
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="url_vysledky" class="form-label fw-semibold">
                            <i class="bi bi-link-45deg me-1 text-primary"></i>URL výsledků
                        </label>
                        <input type="url" name="url_vysledky" id="url_vysledky" class="form-control"
                               placeholder="https://…" value="<?= h($zavod['url_vysledky'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- SEKCE 2: Skupiny -->
            <div class="card section-card mb-3">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-diagram-3"></i>Skupiny
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="skupina_id" class="form-label fw-semibold">
                                <i class="bi bi-people me-1 text-success"></i>Skupina
                            </label>
                            <select name="skupina_id" id="skupina_id" class="form-select">
                                <option value="">-- vyberte --</option>
                                <?php foreach ($skupiny as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ($existingSkupina === (int)$s['id'] ? 'selected' : '') ?>><?= h($s['nazev']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="podskupinaSelect" class="form-label fw-semibold">
                                <i class="bi bi-diagram-2 me-1 text-success"></i>Podskupiny
                            </label>
                            <select name="podskupiny[]" id="podskupinaSelect"
                                    class="form-select" multiple size="5"
                                    <?= ($existingSkupina ? '' : 'disabled') ?>>
                                <?php if (!$existingSkupina): ?>
                                <option value="">Vyberte nejprve skupinu</option>
                                <?php endif; ?>
                            </select>
                            <div class="mini-muted mt-1">Lze vybrat více podskupin.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEKCE 3: Trenéři -->
            <div class="card section-card mb-3">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-person-badge"></i>Trenéři
                </div>
                <div class="card-body">
                    <?php foreach ($trenereList as $tr):
                        $isCurrent = ((int)$tr['id'] === $currentTrener);
                        $isChecked = $isCurrent || in_array((int)$tr['id'], $existingTrenere, true);
                        $disabled  = $isCurrent ? 'disabled' : '';
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="trenere[]" value="<?= (int)$tr['id'] ?>"
                               id="tr_<?= (int)$tr['id'] ?>"
                               <?= ($isChecked ? 'checked' : '') ?> <?= $disabled ?>>
                        <label class="form-check-label" for="tr_<?= (int)$tr['id'] ?>">
                            <?= h($tr['jmeno']) ?>
                            <?php if ($isCurrent): ?>
                                <span class="badge bg-primary fw-normal ms-1" style="font-size:.75rem;">já</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <div class="mini-muted mt-2">
                        <i class="bi bi-lock me-1"></i>Přihlášený trenér bude vždy uložen.
                    </div>
                </div>
            </div>

            <!-- SEKCE 4: Měření výkonů -->
            <div class="card section-card mb-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-speedometer2"></i>Měření výkonů
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <div class="mini-muted mb-1">
                                <i class="bi bi-info-circle me-1"></i>
                                Volitelné — každé měření patří jednomu sportovci (kolo, běh, posilovna, segmenty).
                            </div>
                            <div class="d-flex align-items-center gap-2 p-2 rounded"
                                 style="background:#e8f5e9; border:1px solid #c8e6c9;">
                                <i class="bi bi-clock-history text-success"></i>
                                <span class="small fw-semibold">Čas: <code>MM:SS(.mmm)</code> nebo <code>HH:MM:SS(.mmm)</code></span>
                                <span class="text-muted small">Starší vzdálenost při úpravě potvrďte výběrem m nebo km.</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-info" id="btnAddMereni">
                            <i class="bi bi-plus-lg me-1"></i>Přidat řádek měření
                        </button>
                    </div>
                    <input type="hidden" name="mereni_json" id="mereni_json" value="[]">
                    <div id="mereniRows"></div>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ═══════════ PRAVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-4">

            <!-- SEKCE A: Účastníci -->
            <div class="card section-card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-people-fill"></i>Účastníci
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <label for="ucastnici_input" class="form-label fw-semibold">
                            Hledat sportovce
                        </label>
                        <input type="text" id="ucastnici_input" class="form-control"
                               placeholder="Min. 2 znaky pro hledání…" autocomplete="off">
                        <div id="ucastnici_suggestions"
                             class="list-group position-absolute suggest-box"></div>
                    </div>
                    <div id="ucastnici_selected" class="mt-2 d-flex flex-wrap gap-1"></div>
                    <input type="hidden" name="ucastnici" id="ucastnici" value="<?= h($ucastniciHidden) ?>">
                    <div class="mini-muted mt-2">
                        <i class="bi bi-lightbulb me-1"></i>
                        Začněte psát příjmení nebo jméno sportovce
                    </div>
                </div>
            </div>

            <!-- SEKCE B: Soubory -->
            <div class="card section-card mb-3">
                <div class="card-header" style="background:#6f42c1;color:#fff;">
                    <i class="bi bi-paperclip"></i>Soubory
                </div>
                <div class="card-body">

                    <!-- Stávající fotky -->
                    <?php if (!empty($existingFotky)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">Stávající fotografie</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($existingFotky as $fotka): ?>
                            <div class="text-center">
                                <img src="nahrane_zavody/<?= h($fotka['soubor']) ?>"
                                     class="existing-thumb d-block mb-1"
                                     alt="Fotka závodu"
                                     onerror="this.src='';this.alt='chybí soubor'">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox"
                                           name="smazat_fotku[]"
                                           value="<?= (int)$fotka['id'] ?>"
                                           id="del_fotka_<?= (int)$fotka['id'] ?>">
                                    <label class="form-check-label small text-danger"
                                           for="del_fotka_<?= (int)$fotka['id'] ?>">Smazat</label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-image me-1"></i>Přidat fotografie
                        </label>
                        <input type="file" name="fotky[]" class="form-control"
                               accept="image/*" multiple>
                        <div class="mini-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i>Formáty: JPG, PNG, WEBP
                        </div>
                    </div>

                    <!-- Stávající importy -->
                    <?php if (!empty($existingImports)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">Stávající soubory výsledků</label>
                        <?php foreach ($existingImports as $imp): ?>
                        <div class="existing-file-item">
                            <i class="bi bi-file-earmark-spreadsheet text-success"></i>
                            <span class="small flex-grow-1 text-truncate" title="<?= h($imp['soubor']) ?>">
                                <?= h($imp['soubor']) ?>
                                <?php if (!empty($imp['typ'])): ?>
                                    <span class="badge bg-secondary fw-normal ms-1"><?= h(strtoupper($imp['typ'])) ?></span>
                                <?php endif; ?>
                            </span>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox"
                                       name="smazat_import[]"
                                       value="<?= (int)$imp['id'] ?>"
                                       id="del_import_<?= (int)$imp['id'] ?>">
                                <label class="form-check-label small text-danger"
                                       for="del_import_<?= (int)$imp['id'] ?>">Smazat</label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="form-label fw-semibold">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Přidat výsledky
                        </label>
                        <input type="file" name="vysledky[]" class="form-control"
                               accept=".pdf,.xls,.xlsx" multiple>
                        <div class="mini-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i>Formáty: PDF, XLS, XLSX
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEKCE C: Uložit -->
            <div class="card section-card mb-3">
                <div class="card-body">
                    <button type="submit" class="btn btn-warning btn-lg w-100">
                        <i class="bi bi-floppy me-2"></i>Uložit změny
                    </button>
                    <div class="mini-muted mt-2 text-center">
                        <i class="bi bi-shield-check me-1 text-success"></i>
                        Povinná pole musí být vyplněna
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-4 -->

    </div><!-- /row -->
    </form>
</div><!-- /container -->

<script>
// ── Předvyplnění účastníků z PHP ─────────────────────────────────────────────
window.__ucastniciSelected = <?= json_encode($ucastniciJS, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

// ── Předvyplnění skupiny + podskupiny z PHP ──────────────────────────────────
const __existingSkupina    = <?= (int)$existingSkupina ?>;
const __existingPodskupiny = <?= json_encode($existingPodskupiny, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

// ── Podskupiny podle skupiny ─────────────────────────────────────────────────
function loadPodskupiny(skupinaId, preselectIds, callback) {
    const sel = document.getElementById('podskupinaSelect');
    if (!skupinaId) {
        sel.innerHTML = '<option value="">Vyberte nejprve skupinu</option>';
        sel.disabled = true;
        if (callback) callback();
        return;
    }
    sel.innerHTML = '<option>Načítám…</option>';
    sel.disabled = true;

    fetch('nacti_podskupiny.php?skupina_id=' + encodeURIComponent(skupinaId))
        .then(async r => {
            const data = await r.json().catch(() => null);
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return data;
        })
        .then(data => {
            sel.innerHTML = '';
            const items = Array.isArray(data) ? data : (data.items || []);
            if (items.length) {
                items.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nazev;
                    if (preselectIds && preselectIds.includes(parseInt(p.id, 10))) {
                        opt.selected = true;
                    }
                    sel.appendChild(opt);
                });
                sel.disabled = false;
            } else {
                sel.innerHTML = '<option>Žádné podskupiny</option>';
            }
            if (callback) callback();
        })
        .catch(err => {
            sel.innerHTML = '<option>Chyba načítání</option>';
            console.error('Podskupiny error:', err);
            if (callback) callback();
        });
}

document.getElementById('skupina_id').addEventListener('change', function () {
    loadPodskupiny(this.value, [], null);
});

// ── Účastníci: autocomplete multi-select ─────────────────────────────────────
(() => {
    const input  = document.getElementById('ucastnici_input');
    const sug    = document.getElementById('ucastnici_suggestions');
    const wrap   = document.getElementById('ucastnici_selected');
    const hidden = document.getElementById('ucastnici');

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }
    function close() { sug.innerHTML = ''; }

    function syncHidden() {
        hidden.value = window.__ucastniciSelected.map(x => `${x.id}:${x.label}`).join(', ');
    }

    function render() {
        wrap.innerHTML = '';
        window.__ucastniciSelected.forEach((p, idx) => {
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.innerHTML = `${escapeHtml(p.label)} <button type="button" class="btn-close btn-close-white btn-sm ms-2" aria-label="Odstranit"></button>`;
            chip.querySelector('button').addEventListener('click', () => {
                window.__ucastniciSelected.splice(idx, 1);
                render(); syncHidden();
            });
            wrap.appendChild(chip);
        });
    }

    function addParticipant(id, label) {
        if (window.__ucastniciSelected.some(x => x.id === id)) return;
        window.__ucastniciSelected.push({ id, label });
        render(); syncHidden();
        input.value = ''; close();
    }

    function hint(html) {
        sug.innerHTML = `<div class="list-group-item text-muted small py-2">${html}</div>`;
    }

    function renderList(list) {
        sug.innerHTML = '';
        list.forEach(item => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = item.label;
            a.addEventListener('mousedown', e => {
                e.preventDefault();
                addParticipant(item.id, item.label);
            });
            sug.appendChild(a);
        });
    }

    let abortCtrl = null, lastQ = '';

    input.addEventListener('focus', () => {
        if (sug.innerHTML === '') {
            hint('<i class="bi bi-search me-1"></i>Začněte psát jméno sportovce (min. 2 znaky)…');
        }
    });

    input.addEventListener('blur', () => { setTimeout(() => close(), 150); });

    input.addEventListener('input', () => {
        const q = input.value.trim();
        lastQ = q;
        if (q.length < 1) { close(); return; }
        if (q.length < 2) {
            hint('<i class="bi bi-search me-1"></i>Napište ještě 1 znak…');
            return;
        }
        if (abortCtrl) abortCtrl.abort();
        abortCtrl = new AbortController();
        hint('<i class="bi bi-hourglass-split me-1"></i>Hledám…');
        fetch('ajax_sportovci.php?q=' + encodeURIComponent(q), { signal: abortCtrl.signal })
            .then(r => r.json())
            .then(data => {
                if (input.value.trim() !== lastQ) return;
                if (!Array.isArray(data) || data.length === 0) {
                    hint('<i class="bi bi-exclamation-circle me-1"></i>Nikdo nenalezen.');
                    return;
                }
                const list = data.map(s => ({
                    id: parseInt(s.id, 10),
                    label: ((s.prijmeni || '') + ' ' + (s.jmeno || '')).trim()
                }));
                renderList(list.slice(0, 20));
            })
            .catch(err => {
                if (err && err.name === 'AbortError') return;
                hint('<i class="bi bi-wifi-off me-1"></i>Chyba načítání — zkuste znovu.');
            });
    });

    document.addEventListener('mousedown', e => {
        if (!sug.contains(e.target) && e.target !== input) close();
    });

    // Render pre-populated chips on page load
    render();
    syncHidden();
})();

// ── MĚŘENÍ: dynamické řádky + sportovec autocomplete ─────────────────────────
(() => {
    const rowsWrap   = document.getElementById('mereniRows');
    const btnAdd     = document.getElementById('btnAddMereni');
    const hiddenJson = document.getElementById('mereni_json');
    const form       = document.getElementById('zavodForm');

    const CVIKY    = <?= json_encode($cviky, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const SEGMENTY = <?= json_encode($segmenty, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const MERENI_INIT = <?= $mereniInitJson ?>;

    function getSelectedUcastnici() {
        return Array.isArray(window.__ucastniciSelected) ? window.__ucastniciSelected : [];
    }

    function el(tag, attrs = {}, html = '') {
        const e = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => {
            if (k === 'class') e.className = v;
            else if (k === 'dataset') Object.entries(v).forEach(([dk, dv]) => e.dataset[dk] = dv);
            else e.setAttribute(k, v);
        });
        if (html) e.innerHTML = html;
        return e;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }

    function cvikySelectHtml() {
        if (!CVIKY || !CVIKY.length) {
            return `<input type="text" class="form-control form-control-sm js-cvik_text" placeholder="Cvik (text)">`;
        }
        let opts = `<option value="">-- vyber cvik --</option>`;
        CVIKY.forEach(c => { opts += `<option value="${c.id}">${escapeHtml(c.nazev)}</option>`; });
        return `<select class="form-select form-select-sm js-cvik_id">${opts}</select>`;
    }

    function segmentySelectHtml(kategorie) {
        const filtered = (SEGMENTY || []).filter(s => s.kategorie === kategorie);
        if (!filtered.length) {
            return `<input type="text" class="form-control form-control-sm js-segment_text" placeholder="Segment (žádné k dispozici)" disabled>`;
        }
        let opts = `<option value="">-- vyber segment --</option>`;
        filtered.forEach(s => { opts += `<option value="${s.id}">${escapeHtml(s.nazev)}</option>`; });
        return `<select class="form-select form-select-sm js-segment_id">${opts}</select>`;
    }

    function renderFieldsByType(row) {
        const type = row.querySelector('.js-typ').value;
        const box  = row.querySelector('.js-fields');
        box.innerHTML = '';

        if (type === 'kolo' || type === 'beh') {
            box.appendChild(el('div', { class: 'row g-2' }, `
                <div class="col-md-3">
                    <label class="form-label small mb-1">Vzdálenost</label>
                    <div class="input-group input-group-sm">
                        <input type="number" min="0.01" step="0.01" class="form-control js-vzdalenost" placeholder="Vzdálenost">
                        <select class="form-select js-distance-unit" aria-label="Jednotka vzdálenosti">
                            <option value="">jednotka</option><option value="m">m</option><option value="km">km</option>
                        </select>
                    </div>
                    <div class="invalid-feedback">Vyplňte vzdálenost i jednotku m nebo km.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">
                        Čas
                        <span class="text-muted fw-normal" style="font-size:.78rem;">mm:ss.xx</span>
                    </label>
                    <input type="text" class="form-control form-control-sm js-cas" placeholder="02:13.45" pattern="(?:[0-9]{1,5}:[0-5][0-9](?:\.[0-9]{1,3})?|[0-9]{1,3}:[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,3})?)" title="MM:SS, MM:SS.mmm, HH:MM:SS nebo HH:MM:SS.mmm">
                    <small class="text-muted" style="font-size:.76rem;">např. 2 min 13,45 s → 02:13.45</small>
                </div>
                ${type === 'kolo' ? `
                <div class="col-md-3">
                    <label class="form-label small mb-1">Převod <span class="text-muted fw-normal">(vol.)</span></label>
                    <input type="text" class="form-control form-control-sm js-prevod" placeholder="např. 50/15">
                </div>` : ''}
                <div class="${type === 'kolo' ? 'col-md-3' : 'col-md-6'}">
                    <label class="form-label small mb-1">Poznámka <span class="text-muted fw-normal">(vol.)</span></label>
                    <input type="text" class="form-control form-control-sm js-poznamka" placeholder="">
                </div>
            `));
        } else if (type === 'posilovna') {
            box.appendChild(el('div', { class: 'row g-2' }, `
                <div class="col-md-4">
                    <label class="form-label small mb-1">Cvik</label>
                    ${cvikySelectHtml()}
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Váha <span class="text-muted fw-normal">kg</span></label>
                    <input type="number" step="0.5" class="form-control form-control-sm js-vaha" placeholder="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Opak.</label>
                    <input type="number" class="form-control form-control-sm js-opakovani" placeholder="10">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">RPE <span class="text-muted fw-normal">1–10</span></label>
                    <input type="number" step="0.5" min="1" max="10" class="form-control form-control-sm js-rpe" placeholder="7">
                </div>
                <div class="col-md-12">
                    <label class="form-label small mb-1">Poznámka <span class="text-muted fw-normal">(vol.)</span></label>
                    <input type="text" class="form-control form-control-sm js-poznamka" placeholder="">
                </div>
            `));
        } else if (type === 'kolo_krouzek' || type === 'kolo_silnice' || type === 'kolo_mtb') {
            const kat = type === 'kolo_krouzek' ? 'krouzek' : type === 'kolo_silnice' ? 'silnice' : 'mtb';
            box.appendChild(el('div', { class: 'row g-2' }, `
                <div class="col-md-5">
                    <label class="form-label small mb-1">Segment</label>
                    ${segmentySelectHtml(kat)}
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">
                        Čas
                        <span class="text-muted fw-normal" style="font-size:.78rem;">mm:ss.xx</span>
                    </label>
                    <input type="text" class="form-control form-control-sm js-cas" placeholder="02:13.45" pattern="(?:[0-9]{1,5}:[0-5][0-9](?:\.[0-9]{1,3})?|[0-9]{1,3}:[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,3})?)" title="MM:SS, MM:SS.mmm, HH:MM:SS nebo HH:MM:SS.mmm">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Poznámka <span class="text-muted fw-normal">(vol.)</span></label>
                    <input type="text" class="form-control form-control-sm js-poznamka" placeholder="">
                </div>
            `));
        } else {
            box.innerHTML = `<div class="text-muted small p-1">Vyberte typ měření.</div>`;
        }
    }

    function makeSportovecAutocomplete(row) {
        const inp = row.querySelector('.js-sp-name');
        const sug = row.querySelector('.js-sp-sug');
        const hid = row.querySelector('.js-sp-id');
        let lastQ = '', abortCtrl = null;

        function close() { sug.innerHTML = ''; }

        function choose(id, label) {
            hid.value = String(id ?? '');
            inp.value = label ?? '';
            close();
            try { syncHidden(); } catch (e) {}
        }

        function hint(html) {
            sug.innerHTML = `<div class="list-group-item text-muted small py-2">${html}</div>`;
        }

        function renderList(list) {
            sug.innerHTML = '';
            (list || []).slice(0, 20).forEach(it => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'list-group-item list-group-item-action';
                b.textContent = it.label;
                b.addEventListener('mousedown', e => {
                    e.preventDefault();
                    choose(it.id, it.label);
                });
                sug.appendChild(b);
            });
        }

        inp.addEventListener('focus', () => {
            const q = inp.value.trim().toLowerCase();
            const local = getSelectedUcastnici()
                .map(x => ({ id: x.id, label: x.label }))
                .filter(it => !q || it.label.toLowerCase().includes(q));
            if (local.length) renderList(local);
            else hint('<i class="bi bi-search me-1"></i>Napište 2+ znaky nebo vyberte z účastníků nahoře.');
        });

        inp.addEventListener('input', () => {
            const q = inp.value.trim();
            lastQ = q;

            const local = getSelectedUcastnici()
                .map(x => ({ id: x.id, label: x.label }))
                .filter(it => !q || it.label.toLowerCase().includes(q.toLowerCase()));

            if (q.length < 2) {
                if (local.length) { renderList(local); return; }
                if (q.length < 1) { close(); return; }
                hint('<i class="bi bi-search me-1"></i>Napište ještě 1 znak…');
                return;
            }

            if (local.length) { renderList(local); return; }

            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            hint('<i class="bi bi-hourglass-split me-1"></i>Hledám…');
            fetch('ajax_sportovci.php?q=' + encodeURIComponent(q), { signal: abortCtrl.signal })
                .then(r => r.json())
                .then(data => {
                    if (inp.value.trim() !== lastQ) return;
                    if (!Array.isArray(data) || data.length === 0) {
                        hint('<i class="bi bi-exclamation-circle me-1"></i>Nikdo nenalezen.');
                        return;
                    }
                    const list = data.map(s => ({
                        id: parseInt(s.id, 10),
                        label: ((s.prijmeni || '') + ' ' + (s.jmeno || '')).trim()
                    }));
                    renderList(list);
                })
                .catch(err => {
                    if (err && err.name === 'AbortError') return;
                    hint('<i class="bi bi-wifi-off me-1"></i>Chyba načítání — zkuste znovu.');
                });
        });

        inp.addEventListener('blur', () => {
            setTimeout(() => {
                close();
                const idv = String(hid.value || '').trim();
                if (inp.value.trim() === '' || idv === '' || idv === '0') {
                    inp.value = ''; hid.value = '';
                    try { syncHidden(); } catch (e) {}
                }
            }, 150);
        });

        // Return choose function so caller can pre-set values
        return choose;
    }

    function addRow() {
        const row = el('div', { class: 'mereni-row position-relative' });
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="w-100 position-relative">
                    <label class="form-label small mb-1 fw-semibold">Sportovec</label>
                    <input type="text" class="form-control form-control-sm js-sp-name"
                           placeholder="Jméno (klikněte nebo napište 2+ znaky)…" autocomplete="off">
                    <input type="hidden" class="js-sp-id" value="">
                    <div class="list-group position-absolute js-sp-sug suggest-box"></div>
                </div>
                <div style="min-width:200px;">
                    <label class="form-label small mb-1 fw-semibold">Typ měření</label>
                    <select class="form-select form-select-sm js-typ">
                        <option value="">— vyber —</option>
                        <option value="kolo">Kolo</option>
                        <option value="kolo_krouzek">Kolo - Kroužek</option>
                        <option value="kolo_silnice">Kolo - Silnice</option>
                        <option value="kolo_mtb">Kolo - MTB</option>
                        <option value="beh">Běh</option>
                        <option value="posilovna">Posilovna</option>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-outline-danger js-del">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="js-fields mt-2"></div>
        `;

        rowsWrap.appendChild(row);
        const choose = makeSportovecAutocomplete(row);
        row.querySelector('.js-typ').addEventListener('change', () => { renderFieldsByType(row); syncHidden(); });
        row.querySelector('.js-del').addEventListener('click', () => { row.remove(); syncHidden(); });
        row.addEventListener('input', () => syncHidden());
        row.addEventListener('change', () => syncHidden());
        syncHidden();
        return { row, choose };
    }

    function rowsToJson() {
        const out = [];
        Array.from(rowsWrap.querySelectorAll('.mereni-row')).forEach(r => {
            const typ = r.querySelector('.js-typ').value;
            if (!typ) return;
            const obj = {
                typ,
                sportovec_id:    r.querySelector('.js-sp-id').value || null,
                sportovec_label: r.querySelector('.js-sp-name').value.trim(),
            };
            if (typ === 'kolo' || typ === 'beh') {
                obj.vzdalenost = (r.querySelector('.js-vzdalenost')?.value ?? '').trim();
                obj.distance_unit = (r.querySelector('.js-distance-unit')?.value ?? '').trim();
                obj.cas        = (r.querySelector('.js-cas')?.value ?? '').trim();
                obj.prevod     = (r.querySelector('.js-prevod')?.value ?? '').trim();
                obj.poznamka   = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            } else if (typ === 'posilovna') {
                obj.cvik_id   = (r.querySelector('.js-cvik_id')?.value ?? '').trim();
                obj.vaha      = (r.querySelector('.js-vaha')?.value ?? '').trim();
                obj.opakovani = (r.querySelector('.js-opakovani')?.value ?? '').trim();
                obj.rpe       = (r.querySelector('.js-rpe')?.value ?? '').trim();
                obj.poznamka  = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            } else if (typ === 'kolo_krouzek' || typ === 'kolo_silnice' || typ === 'kolo_mtb') {
                obj.segment_id = (r.querySelector('.js-segment_id')?.value ?? '').trim();
                obj.cas        = (r.querySelector('.js-cas')?.value ?? '').trim();
                obj.poznamka   = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            }
            out.push(obj);
        });
        return out;
    }

    function syncHidden() { hiddenJson.value = JSON.stringify(rowsToJson()); }

    btnAdd.addEventListener('click', () => addRow());

    form.addEventListener('submit', e => {
        form.classList.add('was-validated');
        syncHidden();
        let valid = true;

        if (!form.checkValidity()) {
            valid = false;
        }

        Array.from(rowsWrap.querySelectorAll('.mereni-row')).forEach(row => {
            const typ = row.querySelector('.js-typ').value;
            if (!typ) return;
            const spId   = row.querySelector('.js-sp-id').value;
            const spName = row.querySelector('.js-sp-name');
            if (!spId || spId === '0') {
                spName.classList.add('is-invalid');
                if (!spName.nextElementSibling?.nextElementSibling?.classList.contains('invalid-feedback')) {
                    const msg = document.createElement('div');
                    msg.className = 'invalid-feedback';
                    msg.textContent = 'Vyberte sportovce ze seznamu (klikněte na pole a vyberte).';
                    spName.parentElement.appendChild(msg);
                }
                valid = false;
            } else {
                spName.classList.remove('is-invalid');
            }
            if (typ === 'kolo' || typ === 'beh') {
                const distance = row.querySelector('.js-vzdalenost');
                const unit = row.querySelector('.js-distance-unit');
                const pairValid = Boolean(distance?.value) === Boolean(unit?.value);
                distance?.classList.toggle('is-invalid', !pairValid);
                unit?.classList.toggle('is-invalid', !pairValid);
                if (!pairValid) valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
            var firstInvalid = form.querySelector(':invalid, .is-invalid');
            if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // ── Prefill existujících měření ───────────────────────────────────────────
    function prefillMereni() {
        if (!MERENI_INIT || !MERENI_INIT.length) return;

        MERENI_INIT.forEach(m => {
            const { row, choose } = addRow();
            const typSel = row.querySelector('.js-typ');
            if (m.typ) {
                typSel.value = m.typ;
                renderFieldsByType(row);
            }

            // Sportovec
            if (m.sportovec_id && m.sportovec_label) {
                choose(m.sportovec_id, m.sportovec_label);
            }

            // Pole podle typu
            const typ = m.typ || '';
            if (typ === 'kolo' || typ === 'beh') {
                if (m.vzdalenost != null && m.vzdalenost !== '') {
                    const f = row.querySelector('.js-vzdalenost');
                    if (f) f.value = m.vzdalenost;
                }
                if (m.distance_unit) { const f = row.querySelector('.js-distance-unit'); if (f) f.value = m.distance_unit; }
                if (m.cas) { const f = row.querySelector('.js-cas'); if (f) f.value = m.cas; }
                if (m.prevod) { const f = row.querySelector('.js-prevod'); if (f) f.value = m.prevod; }
                if (m.poznamka) { const f = row.querySelector('.js-poznamka'); if (f) f.value = m.poznamka; }
            } else if (typ === 'posilovna') {
                if (m.cvik_id) {
                    const f = row.querySelector('.js-cvik_id');
                    if (f) f.value = String(m.cvik_id);
                }
                if (m.vaha != null && m.vaha !== '') { const f = row.querySelector('.js-vaha'); if (f) f.value = m.vaha; }
                if (m.opakovani) { const f = row.querySelector('.js-opakovani'); if (f) f.value = m.opakovani; }
                if (m.rpe) { const f = row.querySelector('.js-rpe'); if (f) f.value = m.rpe; }
                if (m.poznamka) { const f = row.querySelector('.js-poznamka'); if (f) f.value = m.poznamka; }
            } else if (typ === 'kolo_krouzek' || typ === 'kolo_silnice' || typ === 'kolo_mtb') {
                if (m.segment_id) {
                    const f = row.querySelector('.js-segment_id');
                    if (f) f.value = String(m.segment_id);
                }
                if (m.cas) { const f = row.querySelector('.js-cas'); if (f) f.value = m.cas; }
                if (m.poznamka) { const f = row.querySelector('.js-poznamka'); if (f) f.value = m.poznamka; }
            }

            syncHidden();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        prefillMereni();
        // Načtení podskupin pro existující skupinu
        if (__existingSkupina) {
            loadPodskupiny(__existingSkupina, __existingPodskupiny, null);
        }
    });
    // In case DOMContentLoaded already fired (script at bottom):
    if (document.readyState !== 'loading') {
        prefillMereni();
        if (__existingSkupina) {
            loadPodskupiny(__existingSkupina, __existingPodskupiny, null);
        }
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Upozornění na neuložené změny ─────────────────────────────────────────────
(() => {
    const form = document.getElementById('zavodForm');
    if (!form) return;
    let dirty = false, submitting = false;

    form.addEventListener('input',  () => { dirty = true; });
    form.addEventListener('change', () => { dirty = true; });
    form.addEventListener('submit', () => { submitting = true; });

    window.addEventListener('beforeunload', e => {
        if (dirty && !submitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
</body>
</html>
