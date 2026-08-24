<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$treninkId = $_GET['id'] ?? null;
if (!$treninkId || !ctype_digit((string)$treninkId)) {
    die('Neplatné ID tréninku.');
}
$treninkId = (int)$treninkId;

$currentTrener = (int)$_SESSION['trener_id'];
// Cizí tréninky smí otevřít jen správce/admin; ostatní projdou ownership checkem níže.
$isAdmin = roleAtLeast('hlavni');

// Kontrola oprávnění: trenér přiřazený k tréninku nebo hlavní trenér
$stmtAuth = $pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id = :id AND trener_id = :uid LIMIT 1');
$stmtAuth->execute([
    ':id'  => $treninkId,
    ':uid' => $currentTrener,
]);
if (!$isAdmin && !$stmtAuth->fetchColumn()) {
    die('Nemáte oprávnění tento trénink upravit.');
}

// Načtení tréninku
$stmt = $pdo->prepare('SELECT * FROM treninky WHERE id = :id');
$stmt->execute([':id' => $treninkId]);
$trenink = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$trenink) {
    die('Trénink nenalezen.');
}

// Načtení referenčních dat (robustně: poradi může/neusí existovat)
try {
    $skupiny = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi, nazev')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $skupiny = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
}

$tagyAll = $pdo->query('SELECT id, nazev FROM tagy ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
$trenereList = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);

// cviky – pokud existují
try {
    $cviky = $pdo->query('SELECT id, nazev FROM cviky ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cviky = [];
}

// segmenty pro kolo_krouzek / kolo_silnice
try {
    $segmenty = $pdo->query("SELECT id, nazev, kategorie FROM segmenty WHERE aktivni = 1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $segmenty = [];
}

// Skupina (single) – vezmeme první
$stmtSk = $pdo->prepare('SELECT skupina_id FROM trenink_skupina WHERE trenink_id = :id LIMIT 1');
$stmtSk->execute([':id' => $treninkId]);
$skupinaId = (int)($stmtSk->fetchColumn() ?: 0);

// Podskupiny (multi)
$stmtPs = $pdo->prepare('SELECT podskupina_id FROM trenink_podskupina WHERE trenink_id = :id');
$stmtPs->execute([':id' => $treninkId]);
$podskupinySelected = array_map('intval', $stmtPs->fetchAll(PDO::FETCH_COLUMN));

// Podskupiny pro vybranou skupinu (SERVER-SIDE)
$podskupinyForGroup = [];
if ($skupinaId > 0) {
    try {
        $stmtP = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev');
        $stmtP->execute([$skupinaId]);
        $podskupinyForGroup = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stmtP = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY nazev');
        $stmtP->execute([$skupinaId]);
        $podskupinyForGroup = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Trenéři vybraní
$stmtT = $pdo->prepare('SELECT trener_id FROM trenink_trener WHERE trenink_id = :id');
$stmtT->execute([':id' => $treninkId]);
$trenereSelected = array_map('intval', $stmtT->fetchAll(PDO::FETCH_COLUMN));

// Účastníci (chipy + hidden)
$stmtU = $pdo->prepare("
    SELECT s.id, CONCAT(s.prijmeni, ' ', s.jmeno) AS label
    FROM trenink_sportovec ts
    JOIN sportovci s ON s.id = ts.sportovec_id
    WHERE ts.trenink_id = ?
    ORDER BY s.prijmeni, s.jmeno
");
$stmtU->execute([$treninkId]);
$ucastniciSelected = $stmtU->fetchAll(PDO::FETCH_ASSOC);
$ucastniciHidden = implode(', ', array_map(fn($x) => ((int)$x['id']).':'.$x['label'], $ucastniciSelected));

// Tagy vybrané (chipy + hidden JSON)
$stmtTag = $pdo->prepare("
    SELECT t.id, t.nazev
    FROM trenink_tag tt
    JOIN tagy t ON t.id = tt.tag_id
    WHERE tt.trenink_id = ?
    ORDER BY t.nazev
");
$stmtTag->execute([$treninkId]);
$tagySelected = [];
foreach ($stmtTag->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $tagySelected[] = ['id' => (int)$t['id'], 'name' => (string)$t['nazev']];
}
$tagyHiddenJson = json_encode($tagySelected, JSON_UNESCAPED_UNICODE);

// Měření (z trenink_mereni + mereni_zaznamy)
$stmtM = $pdo->prepare("
    SELECT 
        mz.*,
        CONCAT(s.prijmeni, ' ', s.jmeno) AS sportovec_label
    FROM trenink_mereni tm
    JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
    LEFT JOIN sportovci s ON s.id = mz.sportovec_id
    WHERE tm.trenink_id = ?
    ORDER BY tm.poradi, tm.mereni_id
");
$stmtM->execute([$treninkId]);

$mereniRows = [];
foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $mereniRows[] = [
        'typ' => (string)($r['typ'] ?? ''),
        'sportovec_id' => (string)($r['sportovec_id'] ?? ''),
        'sportovec_label' => (string)($r['sportovec_label'] ?? ''),
        'vzdalenost' => ($r['vzdalenost'] ?? ''),
        'distance_unit' => ($r['distance_unit'] ?? ''),
        'cas' => ($r['cas'] ?? ''),
        'prevod' => ($r['prevod'] ?? ''),
        'cvik_id' => ($r['cvik_id'] ?? ''),
        'segment_id' => ($r['segment_id'] ?? ''),
        'vaha' => ($r['vaha'] ?? ''),
        'opakovani' => ($r['opakovani'] ?? ''),
        'rpe' => ($r['rpe'] ?? ''),
        'poznamka' => ($r['poznamka'] ?? ''),
    ];
}
$mereniHiddenJson = json_encode($mereniRows, JSON_UNESCAPED_UNICODE);

// Obrázky (json)
$existingImages = [];
if (!empty($trenink['obrazky'])) {
    $decoded = json_decode((string)$trenink['obrazky'], true);
    if (is_array($decoded)) $existingImages = $decoded;
}
$existingImagesJson = json_encode($existingImages, JSON_UNESCAPED_UNICODE);

// Kategorie (pokud existuje ve sloupci)
$kategorieVal = (string)($trenink['kategorie'] ?? '');

// Flash zprávy ze session
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$dupDatumStr = '';
if (!empty($trenink['datum'])) {
    $dtObj = new DateTime($trenink['datum']);
    $czDays2 = ['Monday'=>'Po','Tuesday'=>'Út','Wednesday'=>'St','Thursday'=>'Čt','Friday'=>'Pá','Saturday'=>'So','Sunday'=>'Ne'];
    $dupDatumStr = ($czDays2[$dtObj->format('l')] ?? '') . ' ' . $dtObj->format('j.n.Y');
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Úprava tréninku #<?= (int)$trenink['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .suggest-box { z-index: 1100; max-height: 220px; overflow-y: auto; width: 100%; }
        .chip { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .55rem; border-radius:999px; background:#0d6efd; color:#fff; font-size:.9rem; }
        .chip .btn-close { filter: invert(1); opacity: .9; }
        .mereni-row { border:1px solid #e6e6e6; border-radius:.75rem; padding:.75rem; background: #fff; }
        .mereni-row + .mereni-row { margin-top: .75rem; }
        .mini-muted { font-size:.85rem; color:#666; }
        .img-thumb { width:110px; height:80px; object-fit:cover; border-radius:.5rem; border:1px solid #ddd; }
        .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .section-card .card-header { border-radius: 12px 12px 0 0 !important; font-weight: 600; font-size: .95rem; padding: .65rem 1rem; }
        .submit-bar { position: sticky; bottom: 0; background: #fff; border-top: 2px solid #0d6efd; border-radius: 12px 12px 0 0;
                      padding: .75rem 1.2rem; box-shadow: 0 -4px 12px rgba(0,0,0,.1); z-index: 100; margin-top: 1rem; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">

    <!-- Edit banner -->
    <div class="card mb-3 border-0 shadow-sm" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); color:#fff;">
        <div class="card-body py-3 px-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h1 class="fw-semibold fs-5 mb-0"><i class="bi bi-pencil-square me-2"></i>Úprava tréninku #<?= (int)$trenink['id'] ?></h1>
                    <div class="opacity-75 small"><i class="bi bi-calendar3 me-1"></i><?= h($dupDatumStr) ?></div>
                </div>
                <a href="moje_treninky.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Zpět na tréninky
                </a>
            </div>
        </div>
    </div>

    <form id="treninkForm" method="POST" action="update_trenink.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$trenink['id'] ?>">
        <input type="hidden" name="existing_obrazky" id="existing_obrazky" value="<?= h($existingImagesJson) ?>">

    <div class="row g-3">

        <!-- ═══════════ LEVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-8">

            <!-- Základní informace -->
            <div class="card section-card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-1"></i>Základní informace
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="datum" class="form-label fw-semibold">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Datum
                                <i class="bi bi-lock-fill ms-1 text-muted" style="font-size:.75rem;" title="Datum nelze měnit"></i>
                            </label>
                            <input type="date" name="datum" id="datum" class="form-control bg-light"
                                   value="<?= h($trenink['datum'] ?? '') ?>" readonly>
                            <div class="mini-muted mt-1">
                                <i class="bi bi-lock me-1"></i>Datum tréninku nelze měnit.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="delka" class="form-label fw-semibold req">
                                <i class="bi bi-clock me-1 text-primary"></i>Délka (h)
                            </label>
                            <input type="number" step="0.25" name="delka" id="delka"
                                   class="form-control" value="<?= h($trenink['delka'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="kategorie" class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>Kategorie
                            </label>
                            <select name="kategorie" id="kategorie" class="form-select">
                                <option value="">— vyber —</option>
                                <?php
                                    $opts = ['silnice'=>'Silnice','mtb'=>'MTB','draha'=>'Dráha','cyklokros'=>'Cyklokros','posilovna'=>'Posilovna','atletika'=>'Atletika','cviceni'=>'Cvičení','plavani'=>'Plavání'];
                                    foreach ($opts as $k => $lbl):
                                        $sel = ($k === $kategorieVal) ? 'selected' : '';
                                ?>
                                    <option value="<?= h($k) ?>" <?= $sel ?>><?= h($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skupiny -->
            <div class="card section-card mb-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-diagram-3 me-1"></i>Skupiny
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="skupina_id" class="form-label fw-semibold req">
                                <i class="bi bi-people me-1 text-info"></i>Skupina
                            </label>
                            <select id="skupina_id" name="skupina_id" class="form-select" required>
                                <option value="">— vyber —</option>
                                <?php foreach ($skupiny as $sk): ?>
                                    <option value="<?= (int)$sk['id'] ?>" <?= ((int)$sk['id'] === (int)$skupinaId) ? 'selected' : '' ?>>
                                        <?= h($sk['nazev']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="podskupina_id" class="form-label fw-semibold">
                                <i class="bi bi-diagram-2 me-1 text-info"></i>Podskupiny
                            </label>
                            <select name="podskupina_id[]" id="podskupina_id" class="form-select" multiple size="5">
                                <?php if ($skupinaId <= 0): ?>
                                    <option value="">Nejprve vyber skupinu</option>
                                <?php elseif (empty($podskupinyForGroup)): ?>
                                    <option value="">Žádné podskupiny</option>
                                <?php else: ?>
                                    <?php foreach ($podskupinyForGroup as $ps): ?>
                                        <option value="<?= (int)$ps['id'] ?>" <?= in_array((int)$ps['id'], $podskupinySelected, true) ? 'selected' : '' ?>>
                                            <?= h($ps['nazev']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="mini-muted mt-1">Lze vybrat více podskupin.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popis tréninku -->
            <div class="card section-card mb-3">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-journal-text me-1"></i>Popis tréninku
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="napln" class="form-label fw-semibold req">
                            <i class="bi bi-card-text me-1"></i>Náplň tréninku
                        </label>
                        <textarea name="napln" id="napln" rows="3" class="form-control"
                                  placeholder="Co bylo náplní tréninku?" required><?= h($trenink['napln'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="poznamka" class="form-label fw-semibold">
                            <i class="bi bi-chat-left-text me-1"></i>Poznámka
                            <span class="badge bg-secondary fw-normal ms-1">neveřejné</span>
                        </label>
                        <textarea name="poznamka" id="poznamka" rows="2" class="form-control"
                                  placeholder="Interní poznámka (sportovci ji neuvidí)"><?= h($trenink['poznamka'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Měření -->
            <div class="card section-card mb-3">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-speedometer2 me-1"></i>Měření
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <div class="mini-muted mb-1">
                                <i class="bi bi-info-circle me-1"></i>
                                Každé měření patří jednomu sportovci. Vyberte sportovce ze seznamu.
                            </div>
                            <div class="d-flex align-items-center gap-2 p-2 rounded"
                                 style="background:#e8f5e9; border:1px solid #c8e6c9;">
                                <i class="bi bi-clock-history text-success"></i>
                                <span class="small fw-semibold">Čas: <code>MM:SS(.mmm)</code> nebo <code>HH:MM:SS(.mmm)</code></span>
                                <span class="text-muted small">Starší vzdálenost při úpravě potvrďte výběrem m nebo km.</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnAddMereni">
                            <i class="bi bi-plus-lg me-1"></i>Přidat řádek
                        </button>
                    </div>

                    <input type="hidden" name="mereni_json" id="mereni_json" value="<?= h($mereniHiddenJson) ?>">
                    <div id="mereniRows">
                        <?php if (empty($mereniRows)): ?>
                            <div class="alert alert-secondary mb-0">V tomto tréninku nejsou žádná měření.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ═══════════ PRAVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-4">

            <!-- 1. Účastníci -->
            <div class="card section-card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-people-fill me-1"></i>Účastníci
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <label for="ucastnici_input" class="form-label fw-semibold">Hledat sportovce</label>
                        <input type="text" id="ucastnici_input" class="form-control"
                               placeholder="Min. 2 znaky pro hledání…" autocomplete="off">
                        <div id="ucastnici_suggestions" class="list-group position-absolute suggest-box"></div>
                    </div>
                    <div id="ucastnici_selected" class="mt-2 d-flex flex-wrap gap-1">
                        <?php foreach ($ucastniciSelected as $u): ?>
                            <span class="chip" data-id="<?= (int)$u['id'] ?>">
                                <?= h($u['label']) ?>
                                <button type="button" class="btn-close btn-close-white btn-sm ms-2" aria-label="Odstranit"></button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="ucastnici" id="ucastnici" value="<?= h($ucastniciHidden) ?>">
                </div>
            </div>

            <!-- 2. Tagy -->
            <div class="card section-card mb-3">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-tags me-1"></i>Tagy
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <label for="tag_input" class="form-label fw-semibold">Přidat tag</label>
                        <input type="text" id="tag_input" class="form-control"
                               placeholder="Začni psát… (Enter = přidat)" autocomplete="off">
                        <div id="tag_suggestions" class="list-group position-absolute suggest-box"></div>
                    </div>
                    <div id="tag_selected" class="mt-2 d-flex flex-wrap gap-1">
                        <?php foreach ($tagySelected as $tg): ?>
                            <span class="chip" data-id="<?= (int)$tg['id'] ?>">
                                <?= h($tg['name']) ?>
                                <button type="button" class="btn-close btn-close-white btn-sm ms-2" aria-label="Odstranit"></button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="tagy_json" id="tagy_json" value="<?= h($tagyHiddenJson) ?>">
                    <div class="mini-muted mt-2">
                        <i class="bi bi-plus-circle me-1"></i>Nový tag stačí napsat a stisknout Enter.
                    </div>
                </div>
            </div>

            <!-- 3. Trenéři -->
            <div class="card section-card mb-3">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-person-badge me-1"></i>Trenéři
                </div>
                <div class="card-body">
                    <?php foreach ($trenereList as $tr):
                        $tid = (int)$tr['id'];
                        $isCurrent = ($tid === $currentTrener);
                        $checked = in_array($tid, $trenereSelected, true) ? 'checked' : '';
                        $disabled = (!$isAdmin && !$isCurrent) ? 'disabled' : '';
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="trenere[]" value="<?= $tid ?>"
                               id="tr_<?= $tid ?>" <?= $checked ?> <?= $disabled ?>>
                        <label class="form-check-label" for="tr_<?= $tid ?>">
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

            <!-- 4. Fotografie -->
            <div class="card section-card mb-3">
                <div class="card-header" style="background:#6f42c1;color:#fff;">
                    <i class="bi bi-image me-1"></i>Fotografie
                </div>
                <div class="card-body">
                    <?php if (!empty($existingImages)): ?>
                        <div class="mb-3">
                            <div class="form-label fw-semibold small">Aktuální fotografie</div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($existingImages as $p): ?>
                                    <div class="d-flex flex-column gap-1 align-items-center">
                                        <a href="<?= h($p) ?>" target="_blank">
                                            <img class="img-thumb" src="<?= h($p) ?>" alt="">
                                        </a>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_obrazky[]"
                                                   value="<?= h($p) ?>" id="rm_<?= md5((string)$p) ?>">
                                            <label class="form-check-label small text-danger" for="rm_<?= md5((string)$p) ?>">odebrat</label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <label class="form-label fw-semibold">Nahrát fotky (max. 5)</label>
                    <input type="file" name="obrazky[]" class="form-control" accept="image/*" multiple>
                    <div class="mini-muted mt-1">
                        <i class="bi bi-info-circle me-1"></i>Formáty: JPG, PNG, WEBP
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-4 -->

    </div><!-- /row -->

    <!-- Submit bar -->
    <div class="submit-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">
            <i class="bi bi-pencil-square me-1 text-primary"></i>
            Upravujete trénink ze dne <strong><?= h($dupDatumStr) ?></strong>
        </div>
        <div class="d-flex gap-2">
            <a href="moje_treninky.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Zrušit
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-floppy me-1"></i>Uložit změny
            </button>
        </div>
    </div>

    </form>
</div>

<script>
/* ---------------------------
   Podskupiny po změně skupiny
---------------------------- */
(function(){
    const selSk = document.getElementById('skupina_id');
    const selPs = document.getElementById('podskupina_id');
    if (!selSk || !selPs) return;

    selSk.addEventListener('change', function(){
        const skupinaId = this.value;
        if (!skupinaId) {
            selPs.innerHTML = '<option value="">Nejprve vyber skupinu</option>';
            return;
        }
        selPs.innerHTML = '<option>Načítám...</option>';

        fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(skupinaId))
            .then(r => r.json())
            .then(data => {
                selPs.innerHTML = '';
                const items = (data && data.items && Array.isArray(data.items)) ? data.items : [];
                if (!items.length) {
                    selPs.innerHTML = '<option value="">Žádné podskupiny</option>';
                    return;
                }
                items.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.nazev;
                    selPs.appendChild(opt);
                });
            })
            .catch(() => {
                selPs.innerHTML = '<option value="">Chyba načítání</option>';
            });
    });
})();

/* ---------------------------
   Účastníci: chipy + hidden
---------------------------- */
(function(){
    const input = document.getElementById('ucastnici_input');
    const sug = document.getElementById('ucastnici_suggestions');
    const wrap = document.getElementById('ucastnici_selected');
    const hidden = document.getElementById('ucastnici');
    if (!input || !sug || !wrap || !hidden) return;

    function syncHidden() {
        const chips = Array.from(wrap.querySelectorAll('.chip'));
        const parts = chips.map(ch => {
            const id = ch.getAttribute('data-id') || '';
            const label = (ch.firstChild ? ch.firstChild.textContent : '').trim();
            return id ? (id + ':' + label) : '';
        }).filter(Boolean);
        hidden.value = parts.join(', ');
    }

    // odstranit chip (server-side předvyplnění)
    wrap.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-close');
        if (!btn) return;
        const chip = btn.closest('.chip');
        if (chip) chip.remove();
        syncHidden();
    });

    let aborter = null;
    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (q.length < 2) { sug.innerHTML = ''; return; }

        if (aborter) aborter.abort();
        aborter = new AbortController();

        // POZOR: ajax_sportovci.php musí vracet [{id, jmeno}]
        fetch('ajax_sportovci.php?q=' + encodeURIComponent(q), {signal: aborter.signal})
            .then(r => r.json())
            .then(items => {
                sug.innerHTML = '';
                if (!Array.isArray(items) || !items.length) return;

                items.slice(0, 20).forEach(it => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action';
                    b.textContent = it.jmeno;
                    b.addEventListener('click', () => {
                        // už existuje?
                        if (wrap.querySelector('.chip[data-id="' + it.id + '"]')) {
                            input.value = '';
                            sug.innerHTML = '';
                            return;
                        }
                        const chip = document.createElement('span');
                        chip.className = 'chip';
                        chip.setAttribute('data-id', it.id);
                        chip.appendChild(document.createTextNode(String(it.jmeno) + ' '));
                        const close = document.createElement('button');
                        close.type = 'button';
                        close.className = 'btn-close btn-close-white btn-sm ms-2';
                        close.setAttribute('aria-label', 'Odstranit');
                        chip.appendChild(close);
                        wrap.appendChild(chip);
                        input.value = '';
                        sug.innerHTML = '';
                        syncHidden();
                    });
                    sug.appendChild(b);
                });
            })
            .catch(() => {});
    });

    document.addEventListener('click', (e) => {
        if (!sug.contains(e.target) && e.target !== input) sug.innerHTML = '';
    });

    syncHidden();
})();

/* ---------------------------
   TAGY: chipy + hidden JSON
---------------------------- */
(function(){
    const TAGS = <?= json_encode($tagyAll, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const input = document.getElementById('tag_input');
    const sug   = document.getElementById('tag_suggestions');
    const wrap  = document.getElementById('tag_selected');
    const hid   = document.getElementById('tagy_json');
    if (!input || !sug || !wrap || !hid) return;

    let selected = [];
    try { selected = JSON.parse(hid.value || '[]'); if (!Array.isArray(selected)) selected = []; } catch(e){ selected = []; }

    function render(){
        wrap.innerHTML = '';
        selected.forEach((t, idx) => {
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.setAttribute('data-id', t.id ?? '');
            chip.appendChild(document.createTextNode(String(t.name) + ' '));
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close btn-close-white btn-sm ms-2';
            close.setAttribute('aria-label', 'Odstranit');
            close.addEventListener('click', () => {
                selected.splice(idx, 1);
                sync();
                render();
            });
            chip.appendChild(close);
            wrap.appendChild(chip);
        });
    }
    function sync(){ hid.value = JSON.stringify(selected); }

    function existsName(n){
        const x = String(n).trim().toLowerCase();
        return selected.some(t => String(t.name).trim().toLowerCase() === x);
    }
    function existsId(id){
        return selected.some(t => String(t.id) === String(id));
    }

    function addTag(obj){
        if (!obj || !obj.name) return;
        if (obj.id != null && obj.id !== '' && existsId(obj.id)) return;
        if (existsName(obj.name)) return;
        selected.push({id: obj.id ?? null, name: obj.name});
        sync(); render();
        input.value = '';
        sug.innerHTML = '';
    }

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        if (q.length < 1) { sug.innerHTML = ''; return; }

        const items = (TAGS || [])
            .map(t => ({id: t.id, name: t.nazev}))
            .filter(t => t.name && t.name.toLowerCase().includes(q))
            .filter(t => !existsId(t.id));

        sug.innerHTML = '';
        items.slice(0, 20).forEach(it => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'list-group-item list-group-item-action';
            b.textContent = it.name;
            b.addEventListener('click', () => addTag(it));
            sug.appendChild(b);
        });

        // nabídka vytvoření nového
        const exact = (TAGS || []).some(t => String(t.nazev).trim().toLowerCase() === q);
        if (!exact && !existsName(input.value.trim())) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'list-group-item list-group-item-action';
            const hint = document.createElement('span');
            hint.className = 'text-muted';
            hint.textContent = 'Přidat nový tag: ';
            const name = document.createElement('strong');
            name.textContent = input.value.trim();
            b.append(hint, name);
            b.addEventListener('click', () => addTag({id: null, name: input.value.trim()}));
            sug.appendChild(b);
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if (!q) return;

            const exact = (TAGS || []).find(t => String(t.nazev).trim().toLowerCase() === q.toLowerCase());
            if (exact) addTag({id: exact.id, name: exact.nazev});
            else addTag({id: null, name: q});
        } else if (e.key === 'Escape') {
            sug.innerHTML = '';
        }
    });

    document.addEventListener('click', (e) => {
        if (!sug.contains(e.target) && e.target !== input) sug.innerHTML = '';
    });

    render(); sync();
})();

/* ---------------------------
   MĚŘENÍ: render řádků z hidden JSON + možnost přidat řádek
---------------------------- */
(function(){
    const CVIKY = <?= json_encode($cviky, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const SEGMENTY = <?= json_encode($segmenty, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

    const form = document.getElementById('treninkForm');
    const btnAdd = document.getElementById('btnAddMereni');
    const rowsWrap = document.getElementById('mereniRows');
    const hiddenJson = document.getElementById('mereni_json');

    if (!form || !btnAdd || !rowsWrap || !hiddenJson) return;

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

    function buildCvikSelect(selectedId) {
        if (!Array.isArray(CVIKY) || !CVIKY.length) {
            return `<input type="text" class="form-control js-cvik_text" placeholder="Cvik (tabulka cviky neexistuje)">`;
        }
        const opts = CVIKY.map(c => {
            const sel = (String(c.id) === String(selectedId)) ? 'selected' : '';
            return `<option value="${escapeHtml(c.id)}" ${sel}>${escapeHtml(c.nazev)}</option>`;
        }).join('');
        return `<select class="form-select js-cvik_id"><option value="">— vyber cvik —</option>${opts}</select>`;
    }

    function buildSegmentSelect(selectedId, kategorie) {
        const filtered = (SEGMENTY || []).filter(s => s.kategorie === kategorie);
        if (!filtered.length) {
            return `<input type="text" class="form-control js-segment_text" placeholder="Segment (žádné k dispozici)" disabled>`;
        }
        const opts = filtered.map(s => {
            const sel = (String(s.id) === String(selectedId)) ? 'selected' : '';
            return `<option value="${escapeHtml(s.id)}" ${sel}>${escapeHtml(s.nazev)}</option>`;
        }).join('');
        return `<select class="form-select js-segment_id"><option value="">— vyber segment —</option>${opts}</select>`;
    }

    function renderFields(row) {
        const typSel = row.querySelector('.js-typ');
        const fields = row.querySelector('.js-fields');
        const typ = typSel.value;

        fields.innerHTML = '';

        if (typ === 'kolo' || typ === 'beh') {
            fields.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label mb-1">Vzdálenost (m)</label>
                        <div class="input-group">
                            <input type="number" min="0.01" step="0.01" class="form-control js-vzdalenost" placeholder="Vzdálenost">
                            <select class="form-select js-distance-unit" aria-label="Jednotka vzdálenosti">
                                <option value="">jednotka</option><option value="m">m</option><option value="km">km</option>
                            </select>
                        </div>
                        <div class="invalid-feedback">Vyplňte vzdálenost i jednotku m nebo km.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Čas (mm:ss.xx)</label>
                        <input type="text" class="form-control js-cas" placeholder="02:13.45" pattern="(?:[0-9]{1,5}:[0-5][0-9](?:\.[0-9]{1,3})?|[0-9]{1,3}:[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,3})?)" title="MM:SS, MM:SS.mmm, HH:MM:SS nebo HH:MM:SS.mmm">
                    </div>
                    <div class="col-md-3 ${typ === 'kolo' ? '' : 'd-none'}">
                        <label class="form-label mb-1">Převod</label>
                        <input type="text" class="form-control js-prevod" placeholder="52×15">
                    </div>
                    <div class="col-md-3 ${typ === 'beh' ? '' : 'd-none'}">
                        <label class="form-label mb-1">Poznámka</label>
                        <input type="text" class="form-control js-poznamka" placeholder="vítr, povrch…">
                    </div>
                </div>
            `;
        } else if (typ === 'posilovna') {
            fields.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label mb-1">Cvik</label>
                        ${buildCvikSelect('')}
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Váha</label>
                        <input type="number" step="0.5" class="form-control js-vaha">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Opak.</label>
                        <input type="number" step="1" class="form-control js-opakovani">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">RPE</label>
                        <input type="number" min="1" max="10" step="0.5" class="form-control js-rpe" placeholder="7">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Pozn.</label>
                        <input type="text" class="form-control js-poznamka">
                    </div>
                </div>
            `;
        } else if (typ === 'kolo_krouzek' || typ === 'kolo_silnice' || typ === 'kolo_mtb') {
            const kat = typ === 'kolo_krouzek' ? 'krouzek' : typ === 'kolo_silnice' ? 'silnice' : 'mtb';
            fields.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label mb-1">Segment</label>
                        ${buildSegmentSelect('', kat)}
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">Čas (mm:ss.xx)</label>
                        <input type="text" class="form-control js-cas" placeholder="02:13.45" pattern="(?:[0-9]{1,5}:[0-5][0-9](?:\.[0-9]{1,3})?|[0-9]{1,3}:[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,3})?)" title="MM:SS, MM:SS.mmm, HH:MM:SS nebo HH:MM:SS.mmm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Poznámka</label>
                        <input type="text" class="form-control js-poznamka">
                    </div>
                </div>
            `;
        }
    }

    function addRow(prefill = null) {
        const row = document.createElement('div');
        row.className = 'mereni-row';

        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Typ</label>
                    <select class="form-select js-typ">
                        <option value="">— vyber —</option>
                        <option value="kolo">Kolo</option>
                        <option value="kolo_krouzek">Kolo - Kroužek</option>
                        <option value="kolo_silnice">Kolo - Silnice</option>
                        <option value="kolo_mtb">Kolo - MTB</option>
                        <option value="beh">Běh</option>
                        <option value="posilovna">Posilovna</option>
                    </select>
                </div>

                <div class="col-md-6 position-relative">
                    <label class="form-label mb-1">Sportovec</label>
                    <input type="text" class="form-control js-sp-name" placeholder="Začni psát jméno…" autocomplete="off">
                    <input type="hidden" class="js-sp-id" value="">
                    <div class="list-group position-absolute suggest-box js-sp-sug"></div>
                </div>

                <div class="col-md-3 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger js-remove">Odebrat</button>
                </div>
            </div>

            <div class="mt-2 js-fields"></div>
        `;

        const typSel = row.querySelector('.js-typ');
        const spName = row.querySelector('.js-sp-name');
        const spId = row.querySelector('.js-sp-id');
        const spSug = row.querySelector('.js-sp-sug');

        typSel.addEventListener('change', () => { renderFields(row); syncHidden(); });

        row.querySelector('.js-remove').addEventListener('click', () => {
            row.remove();
            syncHidden();
        });

        // autocomplete sportovec pro měření
        let aborter = null;
        spName.addEventListener('input', () => {
            const q = spName.value.trim();
            if (q.length < 2) { spSug.innerHTML = ''; return; }

            if (aborter) aborter.abort();
            aborter = new AbortController();

            fetch('ajax_sportovci.php?q=' + encodeURIComponent(q), {signal: aborter.signal})
                .then(r => r.json())
                .then(items => {
                    spSug.innerHTML = '';
                    if (!Array.isArray(items) || !items.length) return;

                    items.slice(0, 20).forEach(it => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action';
                        b.textContent = it.jmeno;
                        b.addEventListener('click', () => {
                            spId.value = it.id;
                            spName.value = it.jmeno;
                            spSug.innerHTML = '';
                            syncHidden();
                        });
                        spSug.appendChild(b);
                    });
                })
                .catch(() => {});
        });

        document.addEventListener('click', (e) => {
            if (!spSug.contains(e.target) && e.target !== spName) spSug.innerHTML = '';
        });

        // prefill
        if (prefill && typeof prefill === 'object') {
            if (prefill.typ) typSel.value = prefill.typ;
            renderFields(row);

            if (prefill.sportovec_id) spId.value = prefill.sportovec_id;
            if (prefill.sportovec_label) spName.value = prefill.sportovec_label;

            if (prefill.typ === 'kolo' || prefill.typ === 'beh') {
                row.querySelector('.js-vzdalenost')?.value = prefill.vzdalenost ?? '';
                if (row.querySelector('.js-distance-unit')) row.querySelector('.js-distance-unit').value = prefill.distance_unit ?? '';
                row.querySelector('.js-cas')?.value = prefill.cas ?? '';
                row.querySelector('.js-prevod')?.value = prefill.prevod ?? '';
                row.querySelector('.js-poznamka')?.value = prefill.poznamka ?? '';
            } else if (prefill.typ === 'posilovna') {
                const sel = row.querySelector('.js-cvik_id');
                if (sel && prefill.cvik_id != null) sel.value = String(prefill.cvik_id);
                row.querySelector('.js-vaha')?.value = prefill.vaha ?? '';
                row.querySelector('.js-opakovani')?.value = prefill.opakovani ?? '';
                row.querySelector('.js-rpe')?.value = prefill.rpe ?? '';
                row.querySelector('.js-poznamka')?.value = prefill.poznamka ?? '';
            } else if (prefill.typ === 'kolo_krouzek' || prefill.typ === 'kolo_silnice' || prefill.typ === 'kolo_mtb') {
                const segSel = row.querySelector('.js-segment_id');
                if (segSel && prefill.segment_id != null) segSel.value = String(prefill.segment_id);
                row.querySelector('.js-cas')?.value = prefill.cas ?? '';
                row.querySelector('.js-poznamka')?.value = prefill.poznamka ?? '';
            }
        } else {
            renderFields(row);
        }

        row.addEventListener('input', () => syncHidden());
        row.addEventListener('change', () => syncHidden());

        rowsWrap.appendChild(row);
        syncHidden();
    }

    function rowsToJson() {
        const rows = Array.from(rowsWrap.querySelectorAll('.mereni-row'));
        const out = [];

        rows.forEach(r => {
            const typ = r.querySelector('.js-typ').value;
            if (!typ) return;

            const sportovec_id = r.querySelector('.js-sp-id').value;
            const sportovec_label = r.querySelector('.js-sp-name').value.trim();

            const obj = { typ, sportovec_id: sportovec_id || null, sportovec_label };

            if (typ === 'kolo' || typ === 'beh') {
                obj.vzdalenost = (r.querySelector('.js-vzdalenost')?.value ?? '').trim();
                obj.distance_unit = (r.querySelector('.js-distance-unit')?.value ?? '').trim();
                obj.cas = (r.querySelector('.js-cas')?.value ?? '').trim();
                obj.prevod = (r.querySelector('.js-prevod')?.value ?? '').trim();
                obj.poznamka = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            } else if (typ === 'posilovna') {
                obj.cvik_id = (r.querySelector('.js-cvik_id')?.value ?? '').trim();
                obj.vaha = (r.querySelector('.js-vaha')?.value ?? '').trim();
                obj.opakovani = (r.querySelector('.js-opakovani')?.value ?? '').trim();
                obj.rpe = (r.querySelector('.js-rpe')?.value ?? '').trim();
                obj.poznamka = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            } else if (typ === 'kolo_krouzek' || typ === 'kolo_silnice' || typ === 'kolo_mtb') {
                obj.segment_id = (r.querySelector('.js-segment_id')?.value ?? '').trim();
                obj.cas = (r.querySelector('.js-cas')?.value ?? '').trim();
                obj.poznamka = (r.querySelector('.js-poznamka')?.value ?? '').trim();
            }

            out.push(obj);
        });

        return out;
    }

    function syncHidden() {
        hiddenJson.value = JSON.stringify(rowsToJson());
    }

    btnAdd.addEventListener('click', () => addRow());

    form.addEventListener('submit', (e) => {
        form.classList.add('was-validated');
        syncHidden();

        // Client-side validace řádků měření
        let valid = true;
        const rows = Array.from(rowsWrap.querySelectorAll('.mereni-row'));
        rows.forEach(row => {
            const typ = row.querySelector('.js-typ').value;
            if (!typ) return;

            const spId = row.querySelector('.js-sp-id').value;
            const spName = row.querySelector('.js-sp-name');
            if (!spId || spId === '0') {
                spName.classList.add('is-invalid');
                if (!spName.nextElementSibling || !spName.nextElementSibling.classList.contains('invalid-feedback')) {
                    const msg = document.createElement('div');
                    msg.className = 'invalid-feedback';
                    msg.textContent = 'Vyberte sportovce ze seznamu (začněte psát jméno).';
                    spName.insertAdjacentElement('afterend', msg);
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
            rowsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // init: render z hidden JSON
    let initRows = [];
    try { initRows = JSON.parse(hiddenJson.value || '[]'); } catch(e) { initRows = []; }

    if (Array.isArray(initRows) && initRows.length) {
        // smaž placeholder "V tomto tréninku nejsou..." když existuje
        const alert = rowsWrap.querySelector('.alert');
        if (alert) alert.remove();

        initRows.forEach(r => addRow(r));
    } else {
        // nech alert a nabídni přidání přes tlačítko
    }
})();
</script>

<script>
// Upozornění na neuložené změny
(() => {
    const form = document.getElementById('treninkForm');
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
