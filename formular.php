<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

$currentTrener = (int)$_SESSION['trener_id'];
$isAdmin       = canAccess('formular');

// Načtení seznamů pro výběr
try {
    $skupiny     = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
    $tagy        = $pdo->query("SELECT id, nazev FROM tagy ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
    $trenereList = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('formular.php init queries: ' . $e->getMessage());
    $skupiny     = $skupiny     ?? [];
    $tagy        = $tagy        ?? [];
    $trenereList = $trenereList ?? [];
}

// Cviky pro posilovnu
try {
    $cviky = $pdo->query("SELECT id, nazev FROM cviky ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cviky = [];
}

// Segmenty pro kolo_krouzek / kolo_silnice
try {
    $segmenty = $pdo->query("SELECT id, nazev, kategorie FROM segmenty WHERE aktivni = 1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $segmenty = [];
}

// Sportoviště pro rezervaci (volitelná)
try {
    $sportovisteList = $pdo->query("SELECT id, nazev, max_kapacita FROM sportovist WHERE aktivni=1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sportovisteList = [];
}

// ── Předvyplnění z plánovaného tréninku (?plan_id=X) ─────────────────────
$planPrefill = null;
if (isset($_GET['plan_id']) && ctype_digit($_GET['plan_id'])) {
    $stPlan = $pdo->prepare("
        SELECT pt.*, sk.nazev AS skupina_nazev
        FROM planovane_treninky pt
        LEFT JOIN skupiny sk ON sk.id = pt.skupina_id
        WHERE pt.id = ? AND pt.stav = 'planovany'
    ");
    $stPlan->execute([(int)$_GET['plan_id']]);
    $planPrefill = $stPlan->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── Duplikovat trénink – předvyplnění z existujícího záznamu ──────────────
$duplikat = null;
if (isset($_GET['duplikat']) && ctype_digit($_GET['duplikat'])) {
    try {
        $did = (int)$_GET['duplikat'];

        $r1 = $pdo->prepare('SELECT skupina_id FROM trenink_skupina WHERE trenink_id = ? LIMIT 1');
        $r1->execute([$did]);
        $dupSkupina = (int)($r1->fetchColumn() ?: 0);

        $r2 = $pdo->prepare('SELECT podskupina_id FROM trenink_podskupina WHERE trenink_id = ?');
        $r2->execute([$did]);
        $dupPodskupiny = array_map('intval', $r2->fetchAll(PDO::FETCH_COLUMN));

        $r3 = $pdo->prepare(
            'SELECT sp.id, sp.jmeno, sp.prijmeni
             FROM trenink_sportovec ts
             JOIN sportovci sp ON sp.id = ts.sportovec_id
             WHERE ts.trenink_id = ?
             ORDER BY sp.prijmeni, sp.jmeno'
        );
        $r3->execute([$did]);
        $dupSportovci = $r3->fetchAll(PDO::FETCH_ASSOC);

        $duplikat = [
            'skupina_id' => $dupSkupina,
            'podskupiny' => $dupPodskupiny,
            'ucastnici'  => array_map(fn($sp) => [
                'id'    => (int)$sp['id'],
                'label' => trim(($sp['prijmeni'] ?? '') . ' ' . ($sp['jmeno'] ?? '')),
            ], $dupSportovci),
        ];
    } catch (PDOException $e) {
        error_log('formular.php duplikat: ' . $e->getMessage());
    }
}

// ── Datum – pro omezený výběr (ne-hlavní trenéři) ─────────────────────────
$dnesDate     = date('Y-m-d');
$vceraDate    = date('Y-m-d', strtotime('-1 day'));
$predvcirDate = date('Y-m-d', strtotime('-2 days'));

$czDaysMap = ['Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
              'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'];
function fmtCzDate(string $d, array $map): string {
    try {
        $dt = new DateTime($d);
        return ($map[$dt->format('l')] ?? '') . ' ' . $dt->format('j. n. Y');
    } catch (Exception $e) { return $d; }
}
function fmtDateLabel(string $d, string $dnes, string $vcera, string $predvcir, array $map): string {
    if ($d === $dnes)     return 'Dnes — '        . fmtCzDate($d, $map);
    if ($d === $vcera)    return 'Včera — '        . fmtCzDate($d, $map);
    if ($d === $predvcir) return 'Předevčírem — '  . fmtCzDate($d, $map);
    return fmtCzDate($d, $map);
}

// ── Načti povolené okno zadávání z nastaveni (jen pro ne-adminy) ──────────
$allowedFrom = $predvcirDate; // výchozí: 2 dny zpět
if (!$isAdmin) {
    try {
        $stmtN = $pdo->prepare("SELECT hodnota FROM nastaveni WHERE klic = 'zadavani_povoleno_od'");
        $stmtN->execute();
        $nasVal = $stmtN->fetchColumn();
        if ($nasVal !== false && ctype_digit((string)$nasVal) && (int)$nasVal >= 0) {
            $allowedFrom = date('Y-m-d', strtotime('-' . (int)$nasVal . ' days'));
        }
    } catch (PDOException $e) {
        error_log('formular.php nastaveni read: ' . $e->getMessage());
        // Tabulka ještě neexistuje – použij výchozí hodnotu
    }
}

// Generuj seznam dat (nejnovější první)
$dateOptions = [];
try {
    $dObj = new DateTime($allowedFrom);
    $dnes = new DateTime($dnesDate);
    while ($dObj <= $dnes) {
        $dateOptions[] = $dObj->format('Y-m-d');
        $dObj->modify('+1 day');
    }
    $dateOptions = array_reverse($dateOptions);
} catch (Exception $e) {
    $dateOptions = [$dnesDate, $vceraDate, $predvcirDate];
}

// Flash zprávy
$flashError   = $_SESSION['flash_error']   ?? null; unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $duplikat ? 'Duplikovat trénink' : 'Zadání tréninku' ?></title>
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

        /* Autocomplete dropdown */
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

        /* Zvýraznění předvyplněného formuláře */
        .duplikat-banner {
            background: linear-gradient(135deg, #0f5132 0%, #146c43 100%);
            border-radius: 10px;
            color: #fff;
            padding: .7rem 1rem;
            font-size: .9rem;
        }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4">

    <!-- Hero banner -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold fs-5">
                    <i class="bi bi-<?= $duplikat ? 'copy' : 'calendar-plus' ?> me-2 opacity-75"></i>
                    <?= $duplikat ? 'Duplikovat trénink' : 'Zadání tréninku' ?>
                </div>
                <div class="opacity-75 small">
                    <?= $duplikat
                        ? 'Zkontrolujte předvyplněné hodnoty a uložte nový trénink'
                        : 'Vyplňte níže a uložte nový trénink do systému' ?>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($duplikat): ?>
                <a href="duplikovat_trenink.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Vybrat jiný
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i>Rozcestník
                </a>
            </div>
        </div>
    </div>

    <?php if ($duplikat): ?>
    <div class="duplikat-banner mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-copy fs-5"></i>
        <span>Formulář byl předvyplněn ze vzorového tréninku — datum je nastaveno na <strong>dnes</strong>, účastníci a skupina jsou přeneseni.</span>
    </div>
    <?php endif; ?>

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

    <form id="treninkForm" action="ulozit_trenink.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if ($planPrefill): ?>
    <!-- Info lišta — evidence z plánu -->
    <div class="alert alert-primary d-flex align-items-start gap-2 mb-3">
        <i class="bi bi-calendar3-week fs-5 mt-1"></i>
        <div>
            <strong>Zadáváte evidenci pro plánovaný trénink:</strong>
            <?= h($planPrefill['nazev']) ?>
            <span class="text-muted ms-2 small"><?= h($planPrefill['datum']) ?>
            <?php if ($planPrefill['cas_od']): ?>
                <?= h(substr($planPrefill['cas_od'],0,5)) ?>–<?= h(substr($planPrefill['cas_do'] ?? '',0,5)) ?>
            <?php endif; ?>
            <?php if ($planPrefill['skupina_nazev']): ?>
                · <?= h($planPrefill['skupina_nazev']) ?>
            <?php endif; ?>
            </span><br>
            <span class="small text-muted">Předvyplněné hodnoty můžete libovolně upravit.</span>
        </div>
        <input type="hidden" name="plan_id" value="<?= (int)$planPrefill['id'] ?>">
    </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- ═══════════ LEVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-8">

            <!-- Základní informace -->
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
                            <?php if ($isAdmin): ?>
                                <input type="date" name="datum" id="datum" class="form-control"
                                       value="<?= $duplikat ? h($dnesDate) : '' ?>" required>
                                <div class="mini-muted mt-1">
                                    <i class="bi bi-unlock me-1"></i>Lze zadat libovolné datum.
                                </div>
                            <?php else: ?>
                                <select name="datum" id="datum" class="form-select" required>
                                    <?php foreach ($dateOptions as $opt): ?>
                                        <option value="<?= h($opt) ?>">
                                            <?= h(fmtDateLabel($opt, $dnesDate, $vceraDate, $predvcirDate, $czDaysMap)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (count($dateOptions) > 3): ?>
                                <div class="mini-muted mt-1">
                                    <i class="bi bi-calendar-range me-1 text-success"></i>
                                    Zadávání povoleno od <?= h(fmtCzDate($allowedFrom, $czDaysMap)) ?>.
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="delka" class="form-label fw-semibold req">
                                <i class="bi bi-clock me-1 text-primary"></i>Délka (h)
                            </label>
                            <input type="number" step="0.25" name="delka" id="delka"
                                   class="form-control" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="kategorie" class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>Kategorie
                            </label>
                            <select name="kategorie" id="kategorie" class="form-select">
                                <option value="">— vyber —</option>
                                <option value="silnice">Silnice</option>
                                <option value="mtb">MTB</option>
                                <option value="draha">Dráha</option>
                                <option value="cyklokros">Cyklokros</option>
                                <option value="posilovna">Posilovna</option>
                                <option value="atletika">Atletika</option>
                                <option value="cviceni">Cvičení</option>
                                <option value="plavani">Plavání</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skupiny -->
            <div class="card section-card mb-3">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-diagram-3"></i>Skupiny
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="skupina_id" class="form-label fw-semibold req">
                                <i class="bi bi-people me-1 text-info"></i>Skupina
                            </label>
                            <select name="skupina_id" id="skupina_id" class="form-select" required>
                                <option value="">— vyber —</option>
                                <?php foreach ($skupiny as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"
                                        <?= $duplikat && $duplikat['skupina_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                        <?= h($s['nazev']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="podskupina_id" class="form-label fw-semibold">
                                <i class="bi bi-diagram-2 me-1 text-info"></i>Podskupiny
                            </label>
                            <select name="podskupina_id[]" id="podskupina_id"
                                    class="form-select" multiple size="5">
                                <option value="">Vyberte nejprve skupinu</option>
                            </select>
                            <div class="mini-muted mt-1">Lze vybrat více podskupin.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popis tréninku -->
            <div class="card section-card mb-3">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-journal-text"></i>Popis tréninku
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="napln" class="form-label fw-semibold req">
                            <i class="bi bi-card-text me-1"></i>Náplň tréninku
                        </label>
                        <textarea name="napln" id="napln" rows="3" class="form-control"
                                  placeholder="Co bylo náplní tréninku?" required></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="poznamka" class="form-label fw-semibold">
                            <i class="bi bi-chat-left-text me-1"></i>Poznámka
                            <span class="badge bg-secondary fw-normal ms-1">neveřejné</span>
                        </label>
                        <textarea name="poznamka" id="poznamka" rows="2" class="form-control"
                                  placeholder="Interní poznámka (sportovci ji neuvidí)"></textarea>
                    </div>
                </div>
            </div>

            <!-- Měření -->
            <div class="card section-card mb-3">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-speedometer2"></i>Měření
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <div class="mini-muted mb-1">
                                <i class="bi bi-info-circle me-1"></i>
                                Každé měření patří jednomu sportovci. Vyberte sportovce ze seznamu (začněte psát jméno).
                            </div>
                            <div class="d-flex align-items-center gap-2 p-2 rounded"
                                 style="background:#e8f5e9; border:1px solid #c8e6c9;">
                                <i class="bi bi-clock-history text-success"></i>
                                <span class="small fw-semibold">Formát času: <code>mm:ss.xx</code></span>
                                <span class="text-muted small">např. <code>02:13.45</code> = 2 min 13,45 s</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnAddMereni">
                            <i class="bi bi-plus-lg me-1"></i>Přidat řádek
                        </button>
                    </div>

                    <input type="hidden" name="mereni_json" id="mereni_json" value="[]">
                    <div id="mereniRows"></div>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ═══════════ PRAVÝ SLOUPEC ═══════════ -->
        <div class="col-lg-4">

            <!-- 1. Účastníci -->
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
                    <input type="hidden" name="ucastnici" id="ucastnici" value="">
                    <div class="mini-muted mt-2">
                        <i class="bi bi-lightbulb me-1"></i>
                        Vybraní sportovci se zobrazí i v poli Sportovec u měření.
                    </div>
                </div>
            </div>

            <!-- 2. Tagy -->
            <div class="card section-card mb-3">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-tags"></i>Tagy
                </div>
                <div class="card-body">
                    <div class="position-relative">
                        <label for="tag_input" class="form-label fw-semibold">
                            Přidat tag
                        </label>
                        <input type="text" id="tag_input" class="form-control"
                               placeholder="Začni psát… (Enter = přidat)" autocomplete="off">
                        <div id="tag_suggestions"
                             class="list-group position-absolute suggest-box"></div>
                    </div>
                    <div id="tag_selected" class="mt-2 d-flex flex-wrap gap-1"></div>
                    <input type="hidden" name="tagy_json" id="tagy_json" value="[]">
                    <div class="mini-muted mt-2">
                        <i class="bi bi-plus-circle me-1"></i>Nový tag stačí napsat a stisknout Enter.
                    </div>
                </div>
            </div>

            <!-- 3. Trenéři -->
            <div class="card section-card mb-3">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-person-badge"></i>Trenéři
                </div>
                <div class="card-body">
                    <?php foreach ($trenereList as $tr):
                        $isCurrent = ((int)$tr['id'] === $currentTrener);
                        $checked   = $isCurrent ? 'checked' : '';
                        $disabled  = $isCurrent ? 'disabled' : '';
                    ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="trenere[]" value="<?= (int)$tr['id'] ?>"
                               id="tr_<?= (int)$tr['id'] ?>"
                               <?= $checked ?> <?= $disabled ?>>
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

            <!-- 4. Fotografie -->
            <div class="card section-card mb-3">
                <div class="card-header" style="background:#6f42c1;color:#fff;">
                    <i class="bi bi-image"></i>Fotografie
                </div>
                <div class="card-body">
                    <label class="form-label fw-semibold">Nahrát fotky (max. 5)</label>
                    <input type="file" name="obrazky[]" class="form-control"
                           accept="image/*" multiple>
                    <div class="mini-muted mt-1">
                        <i class="bi bi-info-circle me-1"></i>Formáty: JPG, PNG, WEBP
                    </div>
                </div>
            </div>

        </div><!-- /col-lg-4 -->

    </div><!-- /row -->

    <!-- 5. Rezervace sportoviště (volitelná, collapsible) -->
    <?php if (canAccess('rezervace_sportovist') && !empty($sportovisteList)): ?>
    <div class="card section-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"
             style="background:#0d6efd;color:#fff;cursor:pointer"
             data-bs-toggle="collapse" data-bs-target="#kolapsRezerv" aria-expanded="false">
            <span><i class="bi bi-building-check me-2"></i>Rezervace sportoviště
                <span class="badge bg-white text-primary ms-2 fw-normal" style="font-size:.75em">Volitelné</span>
            </span>
            <i class="bi bi-chevron-down"></i>
        </div>
        <div class="collapse" id="kolapsRezerv">
            <div class="card-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>Pokud trénink probíhá na konkrétním sportovišti, lze zde zablokovat kapacitu pro ostatní rezervace.
                </p>
                <div class="row g-3">
                    <div class="col-sm-5">
                        <label class="form-label">Sportoviště</label>
                        <select name="rez_sportoviste_id" id="rezSportovisteId" class="form-select">
                            <option value="">— nevybráno —</option>
                            <?php foreach ($sportovisteList as $sp): ?>
                                <option value="<?= (int)$sp['id'] ?>" data-max="<?= (int)$sp['max_kapacita'] ?>">
                                    <?= htmlspecialchars($sp['nazev'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Kapacita</label>
                        <select name="rez_kapacita" id="rezKapacita" class="form-select">
                            <?php for ($k = 1; $k <= 5; $k++): ?>
                                <option value="<?= $k ?>"><?= $k ?>/5</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Čas od</label>
                        <input type="time" name="rez_cas_od" id="rezCasOd" class="form-control">
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label">Čas do</label>
                        <input type="time" name="rez_cas_do" id="rezCasDo" class="form-control">
                    </div>
                    <div class="col-sm-1 d-flex align-items-end">
                        <button type="button" id="rezCheckBtn" class="btn btn-outline-secondary w-100"
                                title="Zkontrolovat dostupnost">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div id="rezDostupnost" class="mt-2 small"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Submit bar -->
    <div class="submit-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="text-muted small">
            <i class="bi bi-shield-check me-1 text-success"></i>
            Trénink bude uložen pouze po úplném vyplnění povinných polí.
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Zrušit
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-floppy me-1"></i>Uložit trénink
            </button>
        </div>
    </div>

    </form>
</div><!-- /container -->

<script>
// ── Globální předvyplnění z duplikat ─────────────────────────────────────
const DUPLIKAT = <?= json_encode($duplikat, JSON_UNESCAPED_UNICODE) ?>;
window.__duplikatPodskupiny = DUPLIKAT ? (DUPLIKAT.podskupiny || []) : [];

// ── Předvyplnění z plánovaného tréninku ──────────────────────────────────
const PLAN_PREFILL = <?= json_encode($planPrefill ? [
    'skupina_id'    => (int)($planPrefill['skupina_id'] ?? 0),
    'podskupina_id' => (int)($planPrefill['podskupina_id'] ?? 0),
    'datum'         => $planPrefill['datum'] ?? '',
    'kategorie'     => $planPrefill['kategorie'] ?? '',
    'nazev'         => $planPrefill['nazev'] ?? '',
] : null, JSON_UNESCAPED_UNICODE) ?>;
if (PLAN_PREFILL) {
    // Datum
    const datumSel = document.getElementById('datum') || document.querySelector('[name="datum"]');
    if (datumSel && PLAN_PREFILL.datum) {
        const opt = datumSel.querySelector?.('option[value="' + PLAN_PREFILL.datum + '"]');
        if (opt) opt.selected = true;
        else if (datumSel.tagName === 'INPUT') datumSel.value = PLAN_PREFILL.datum;
    }
    // Kategorie
    if (PLAN_PREFILL.kategorie) {
        const katSel = document.querySelector('[name="kategorie"]');
        if (katSel) katSel.value = PLAN_PREFILL.kategorie;
    }
    // Skupina — nastaví se přes existující DUPLIKAT mechanismus níže
    if (PLAN_PREFILL.skupina_id && !DUPLIKAT) {
        window.__planPodskupina = PLAN_PREFILL.podskupina_id;
        const skSel = document.getElementById('skupina_id');
        if (skSel) {
            skSel.value = PLAN_PREFILL.skupina_id;
            skSel.dispatchEvent(new Event('change'));
        }
    }
    // Náplň — předvyplnit z názvu plánu pokud je prázdná
    const naplnField = document.getElementById('napln') || document.querySelector('[name="napln"]');
    if (naplnField && !naplnField.value.trim() && PLAN_PREFILL.nazev) {
        naplnField.value = PLAN_PREFILL.nazev;
    }
}

// ── Podskupiny podle skupiny ─────────────────────────────────────────────
document.getElementById('skupina_id').addEventListener('change', function () {
    const sel = document.getElementById('podskupina_id');
    sel.innerHTML = '<option>Načítám…</option>';

    fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(this.value))
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
                    // Předvyber podskupiny z duplikátu nebo z plánu
                    if (window.__duplikatPodskupiny.includes(parseInt(p.id, 10))) {
                        opt.selected = true;
                    } else if (window.__planPodskupina && parseInt(p.id, 10) === window.__planPodskupina) {
                        opt.selected = true;
                    }
                    sel.appendChild(opt);
                });
            } else {
                sel.innerHTML = '<option>Žádné podskupiny</option>';
            }
        })
        .catch(err => {
            sel.innerHTML = '<option>Chyba načítání</option>';
            console.error('Podskupiny error:', err);
        });
});

// Pokud duplikát předvyplňuje skupinu, spusť načtení podskupin hned
if (DUPLIKAT && DUPLIKAT.skupina_id) {
    document.getElementById('skupina_id').dispatchEvent(new Event('change'));
}

// ── Účastníci: autocomplete multi-select ─────────────────────────────────
(() => {
    const input  = document.getElementById('ucastnici_input');
    const sug    = document.getElementById('ucastnici_suggestions');
    const wrap   = document.getElementById('ucastnici_selected');
    const hidden = document.getElementById('ucastnici');

    window.__ucastniciSelected = [];

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
            // mousedown + preventDefault: zabrání blur na inputu → dropdown zůstane otevřený do kliknutí
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

    syncHidden();

    // Předvyplnění z duplikátu
    if (DUPLIKAT && DUPLIKAT.ucastnici) {
        DUPLIKAT.ucastnici.forEach(sp => addParticipant(sp.id, sp.label));
    }
})();

// ── MĚŘENÍ: dynamické řádky + sportovec autocomplete ─────────────────────
(() => {
    const rowsWrap   = document.getElementById('mereniRows');
    const btnAdd     = document.getElementById('btnAddMereni');
    const hiddenJson = document.getElementById('mereni_json');
    const form       = document.getElementById('treninkForm');

    const CVIKY = <?= json_encode($cviky, JSON_UNESCAPED_UNICODE) ?>;
    const SEGMENTY = <?= json_encode($segmenty, JSON_UNESCAPED_UNICODE) ?>;

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
                    <input type="number" step="0.01" class="form-control form-control-sm js-vzdalenost" placeholder="km nebo m">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">
                        Čas
                        <span class="text-muted fw-normal" style="font-size:.78rem;">mm:ss.xx</span>
                    </label>
                    <input type="text" class="form-control form-control-sm js-cas" placeholder="02:13.45">
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
                    <input type="text" class="form-control form-control-sm js-cas" placeholder="02:13.45">
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
                // mousedown + preventDefault: blur na inputu nenastane → výběr je spolehlivý
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

            // Vždy nejdřív hledej v místně vybraných účastnících
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
        makeSportovecAutocomplete(row);
        row.querySelector('.js-typ').addEventListener('change', () => { renderFieldsByType(row); syncHidden(); });
        row.querySelector('.js-del').addEventListener('click', () => { row.remove(); syncHidden(); });
        row.addEventListener('input', () => syncHidden());
        row.addEventListener('change', () => syncHidden());
        syncHidden();
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

        // Bootstrap HTML5 validace (required pole, vč. napln)
        if (!form.checkValidity()) {
            valid = false;
        }

        // Vlastní validace měření — sportovec musí být vybrán
        Array.from(rowsWrap.querySelectorAll('.mereni-row')).forEach(row => {
            const typ    = row.querySelector('.js-typ').value;
            if (!typ) return;
            const spId   = row.querySelector('.js-sp-id').value;
            const spName = row.querySelector('.js-sp-name');
            if (!spId || spId === '0') {
                spName.classList.add('is-invalid');
                if (!spName.nextElementSibling?.classList.contains('invalid-feedback')) {
                    const msg = document.createElement('div');
                    msg.className = 'invalid-feedback';
                    msg.textContent = 'Vyberte sportovce ze seznamu (klikněte na pole a vyberte).';
                    spName.insertAdjacentElement('afterend', msg);
                }
                valid = false;
            } else {
                spName.classList.remove('is-invalid');
            }
        });
        if (!valid) {
            e.preventDefault();
            // Scroll k prvnímu nevalidnímu poli
            var firstInvalid = form.querySelector(':invalid, .is-invalid');
            if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    addRow(); // 1 prázdný řádek
})();

// ── TAGY: autocomplete + chipy ────────────────────────────────────────────
(() => {
    const TAGS  = <?= json_encode($tagy, JSON_UNESCAPED_UNICODE) ?>;
    const input = document.getElementById('tag_input');
    const sug   = document.getElementById('tag_suggestions');
    const wrap  = document.getElementById('tag_selected');
    const hid   = document.getElementById('tagy_json');
    if (!input || !sug || !wrap || !hid) return;

    let selected = [];

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, m =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }
    function sync() { hid.value = JSON.stringify(selected); }
    function close() { sug.innerHTML = ''; }

    function render() {
        wrap.innerHTML = '';
        selected.forEach((t, idx) => {
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.innerHTML = `${escapeHtml(t.name)} <button type="button" class="btn-close btn-close-white btn-sm ms-2" aria-label="Odstranit"></button>`;
            chip.querySelector('button').addEventListener('click', () => {
                selected.splice(idx, 1); render(); sync();
            });
            wrap.appendChild(chip);
        });
    }

    function existsById(id) { return selected.some(x => x.id !== null && x.id === id); }
    function existsByName(name) {
        const n = name.trim().toLowerCase();
        return selected.some(x => x.name.trim().toLowerCase() === n);
    }

    function addTag(tag) {
        if (!tag || !tag.name) return;
        if (tag.id !== null ? existsById(tag.id) : existsByName(tag.name)) return;
        selected.push({ id: tag.id, name: tag.name });
        render(); sync(); input.value = ''; close();
    }

    function renderList(items, q) {
        sug.innerHTML = '';
        items.forEach(it => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = it.name;
            a.addEventListener('click', () => addTag(it));
            sug.appendChild(a);
        });
        const qn = (q || '').trim();
        if (qn.length >= 1 && !existsByName(qn)) {
            const found = TAGS.some(t => String(t.nazev).trim().toLowerCase() === qn.toLowerCase());
            if (!found) {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action';
                a.innerHTML = `<span class="text-muted">Přidat nový tag:</span> <strong>${escapeHtml(qn)}</strong>`;
                a.addEventListener('click', () => addTag({ id: null, name: qn }));
                sug.appendChild(a);
            }
        }
    }

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        if (q.length < 1) { close(); return; }
        const items = (TAGS || [])
            .map(t => ({ id: parseInt(t.id, 10), name: t.nazev }))
            .filter(t => t.name && t.name.toLowerCase().includes(q) && !existsById(t.id));
        renderList(items.slice(0, 20), input.value);
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if (!q) return;
            const exact = (TAGS || []).find(t => String(t.nazev).trim().toLowerCase() === q.toLowerCase());
            if (exact) addTag({ id: parseInt(exact.id, 10), name: exact.nazev });
            else addTag({ id: null, name: q });
        } else if (e.key === 'Escape') { close(); }
    });

    document.addEventListener('click', e => {
        if (!sug.contains(e.target) && e.target !== input) close();
    });

    sync();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Upozornění na neuložené změny ────────────────────────────────────────
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

// ── Rezervace sportoviště — live dostupnost ──────────────────────────────
(function () {
    const sportSel = document.getElementById('rezSportovisteId');
    const casOd    = document.getElementById('rezCasOd');
    const casDo    = document.getElementById('rezCasDo');
    const kapSel   = document.getElementById('rezKapacita');
    const checkBtn = document.getElementById('rezCheckBtn');
    const outEl    = document.getElementById('rezDostupnost');
    const datumSel = document.getElementById('datum') || document.querySelector('[name="datum"]');

    if (!sportSel || !checkBtn) return;

    // Synchronizace výchozích časů z polí tréninku (cas_od, cas_do) pokud existují
    const tCasOd = document.querySelector('[name="cas_od"]');
    const tCasDo = document.querySelector('[name="cas_do"]');
    document.getElementById('kolapsRezerv')?.addEventListener('show.bs.collapse', () => {
        if (tCasOd?.value && !casOd.value) casOd.value = tCasOd.value;
        if (tCasDo?.value && !casDo.value) casDo.value = tCasDo.value;
    });

    async function checkDostupnost() {
        const sportId = sportSel.value;
        const datum   = datumSel?.value;
        const od      = casOd.value;
        const doo     = casDo.value;
        const kap     = kapSel?.value ?? 1;

        if (!sportId || !datum || !od || !doo) {
            outEl.innerHTML = '<span class="text-muted">Vyplňte sportoviště, datum a časy.</span>';
            return;
        }
        outEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Kontroluji…</span>';

        try {
            const url = `ajax_dostupnost_sportovist.php?sportoviste_id=${encodeURIComponent(sportId)}&datum=${encodeURIComponent(datum)}&cas_od=${encodeURIComponent(od)}&cas_do=${encodeURIComponent(doo)}`;
            const resp = await fetch(url);
            const data = await resp.json();
            const obsazeno  = data.obsazeno  ?? 0;
            const maxKap    = data.max       ?? 5;
            const volno     = maxKap - obsazeno;
            const pozadovana = parseInt(kap, 10);

            if (volno <= 0) {
                outEl.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Sportoviště plně obsazeno (${obsazeno}/${maxKap})</span>`;
            } else if (pozadovana > volno) {
                outEl.innerHTML = `<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Požadovaná kapacita ${pozadovana} přesahuje volné (${volno}/${maxKap})</span>`;
            } else {
                outEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>Dostupné — obsazeno ${obsazeno}/${maxKap}, zbývá ${volno} dílů</span>`;
            }
        } catch (e) {
            outEl.innerHTML = '<span class="text-warning">Nepodařilo se zkontrolovat dostupnost.</span>';
        }
    }

    checkBtn.addEventListener('click', checkDostupnost);
    [sportSel, casOd, casDo, kapSel].forEach(el => el?.addEventListener('change', checkDostupnost));
})();
</script>
</body>
</html>
