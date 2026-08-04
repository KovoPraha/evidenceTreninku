<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('planovac')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/training_roster_bridge.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId = (int)$_SESSION['trener_id'];
$errors   = [];
$editId   = (int)($_GET['id'] ?? 0);  // >0 = editace existujícího

// ── Načtení pro editaci ───────────────────────────────────────────────────────
$existujici = null;
if ($editId) {
    $stE = $pdo->prepare("SELECT * FROM planovane_treninky WHERE id=? AND stav != 'zruseny'");
    $stE->execute([$editId]);
    $existujici = $stE->fetch(PDO::FETCH_ASSOC);
    if (!$existujici || (!roleAtLeast('hlavni') && (int)$existujici['trener_id'] !== $trenerId)) {
        header('Location: planovac.php'); exit;
    }
}

// ── Vybrané podskupiny (editace / fallback) ───────────────────────────────────
$selectedPodskupiny = [];
$selectedTeamIds = [];
if ($editId && $existujici) {
    $stPs = $pdo->prepare("SELECT podskupina_id FROM planovane_treninky_podskupiny WHERE plan_id=?");
    $stPs->execute([$editId]);
    $selectedPodskupiny = array_column($stPs->fetchAll(PDO::FETCH_ASSOC), 'podskupina_id');
    // Fallback na starý jednopodskupinový sloupec
    if (empty($selectedPodskupiny) && !empty($existujici['podskupina_id'])) {
        $selectedPodskupiny = [(int)$existujici['podskupina_id']];
    }
    $selectedTeamIds = trainingRosterBridgePlanTeamIds($pdo, $editId);
}

// ── Výchozí hodnoty ───────────────────────────────────────────────────────────
$def = [
    'nazev'         => $existujici['nazev']         ?? '',
    'kategorie'     => $existujici['kategorie']     ?? '',
    'skupina_id'    => $existujici['skupina_id']    ?? '',
    'datum'         => $existujici['datum']         ?? ($_GET['datum'] ?? date('Y-m-d')),
    'cas_od'        => $existujici['cas_od']        ?? '',
    'cas_do'        => $existujici['cas_do']        ?? '',
    'sportoviste_id'=> $existujici['sportoviste_id']?? '',
    'popis'         => $existujici['popis']         ?? '',
    'je_verejny'    => (int)($existujici['je_verejny'] ?? 0),
];

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $nazev         = trim($_POST['nazev'] ?? '');
        $kategorie     = $_POST['kategorie'] ?? '';
        $skupinaId     = (int)($_POST['skupina_id'] ?? 0);
        $podskupinyIds = array_values(array_filter(array_map('intval', $_POST['podskupiny_ids'] ?? [])));
        $teamIds        = array_values(array_unique(array_filter(array_map('intval', $_POST['team_ids'] ?? []))));
        $podskupinaId  = !empty($podskupinyIds) ? $podskupinyIds[0] : null; // legacy FK
        $datum         = trim($_POST['datum'] ?? '');
        $casOd         = trim($_POST['cas_od'] ?? '') ?: null;
        $casOd         = $casOd ? substr($casOd, 0, 5) : null;
        $casDo         = trim($_POST['cas_do'] ?? '') ?: null;
        $casDo         = $casDo ? substr($casDo, 0, 5) : null;
        $sportId       = (int)($_POST['sportoviste_id'] ?? 0) ?: null;
        $popis         = trim($_POST['popis'] ?? '') ?: null;
        $jeVerejny     = isset($_POST['je_verejny']) ? 1 : 0;
        $opTyp         = $_POST['opakovani_typ'] ?? 'none'; // none | pocet | datum

        $kategorieEnum = ['silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani'];
        $kategorie     = in_array($kategorie, $kategorieEnum, true) ? $kategorie : null;

        if ($nazev === '') $errors[] = 'Zadejte název tréninku.';
        if (!$skupinaId)   $errors[] = 'Vyberte skupinu.';
        if (!$datum)       $errors[] = 'Zadejte datum.';
        if ($casOd && $casDo && $casOd >= $casDo) $errors[] = 'Čas "od" musí být před časem "do".';

        if (empty($errors)) {
            if ($editId) {
                // Editace — jen aktuální záznam, ignoruje opakování
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("
                        UPDATE planovane_treninky SET
                            nazev=?, kategorie=?, skupina_id=?, podskupina_id=?,
                            datum=?, cas_od=?, cas_do=?, sportoviste_id=?, popis=?, je_verejny=?
                        WHERE id=?
                    ")->execute([$nazev, $kategorie, $skupinaId, $podskupinaId,
                                 $datum, $casOd, $casDo, $sportId, $popis, $jeVerejny, $editId]);
                    // Aktualizace podskupin v junction tabulce
                    $pdo->prepare("DELETE FROM planovane_treninky_podskupiny WHERE plan_id=?")->execute([$editId]);
                    if (!empty($podskupinyIds)) {
                        $stmtPs = $pdo->prepare("INSERT IGNORE INTO planovane_treninky_podskupiny (plan_id, podskupina_id) VALUES (?, ?)");
                        foreach ($podskupinyIds as $psId) { $stmtPs->execute([$editId, $psId]); }
                    }
                    trainingRosterBridgeReplacePlanTeams($pdo, $editId, $teamIds, $trenerId);
                    $pdo->commit();
                    $_SESSION['flash_success'] = 'Plánovaný trénink upraven.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
                }
            } else {
                // Nový — s případným opakováním
                $pdo->beginTransaction();
                try {
                    $stmt   = $pdo->prepare("
                        INSERT INTO planovane_treninky
                            (trener_id, nazev, kategorie, skupina_id, podskupina_id,
                             datum, cas_od, cas_do, sportoviste_id, popis, serie_id, je_verejny)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $stmtSerie = $pdo->prepare("UPDATE planovane_treninky SET serie_id=? WHERE id=?");
                    $stmtPs = !empty($podskupinyIds)
                        ? $pdo->prepare("INSERT IGNORE INTO planovane_treninky_podskupiny (plan_id, podskupina_id) VALUES (?, ?)")
                        : null;

                    // Výpočet dat opakování
                    $dtBase = new DateTime($datum);
                    $datumy = [$dtBase->format('Y-m-d')];
                    if ($opTyp === 'pocet') {
                        $pocet = max(1, min(52, (int)($_POST['opakovani_pocet'] ?? 1)));
                        for ($i = 1; $i <= $pocet; $i++) {
                            $datumy[] = (clone $dtBase)->modify("+{$i} weeks")->format('Y-m-d');
                        }
                    } elseif ($opTyp === 'datum') {
                        $doDatStr = trim($_POST['opakovani_do_data'] ?? '');
                        if ($doDatStr) {
                            try {
                                $dtLimit = new DateTime($doDatStr);
                                if ($dtLimit > $dtBase) {
                                    $dtCur = clone $dtBase;
                                    for ($i = 0; $i < 52; $i++) {
                                        $dtCur->modify('+1 week');
                                        if ($dtCur > $dtLimit) break;
                                        $datumy[] = $dtCur->format('Y-m-d');
                                    }
                                }
                            } catch (Exception $ex) { /* neplatné datum — ignorovat */ }
                        }
                    }

                    $jeSerie   = count($datumy) > 1;
                    $prveId    = null;
                    foreach ($datumy as $d) {
                        $stmt->execute([$trenerId, $nazev, $kategorie, $skupinaId, $podskupinaId,
                                        $d, $casOd, $casDo, $sportId, $popis, null, $jeVerejny]);
                        $planId = (int)$pdo->lastInsertId();
                        if ($prveId === null) $prveId = $planId;
                        if ($stmtPs) {
                            foreach ($podskupinyIds as $psId) { $stmtPs->execute([$planId, $psId]); }
                        }
                        trainingRosterBridgeReplacePlanTeams($pdo, $planId, $teamIds, $trenerId);
                    }
                    // Nastavit serie_id = ID prvního záznamu pro všechny instance série
                    if ($jeSerie && $prveId) {
                        $pdo->prepare("UPDATE planovane_treninky SET serie_id=? WHERE id >= ? AND trener_id=? AND nazev=? AND datum >= ?")
                            ->execute([$prveId, $prveId, $trenerId, $nazev, $datumy[0]]);
                    }

                    $pdo->commit();
                    $pocet = count($datumy);
                    $_SESSION['flash_success'] = $pocet > 1
                        ? "Vypsáno {$pocet} plánovaných tréninků jako opakující se série."
                        : 'Plánovaný trénink vypsán.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
                }
            }
            if (empty($errors)) {
                header('Location: planovac.php?datum=' . $datum);
                exit;
            }
        }

        // Zachovat hodnoty z POST pro opětovné zobrazení formuláře
        $selectedPodskupiny = $podskupinyIds ?? [];
        $selectedTeamIds = $teamIds ?? [];
        $def = compact('nazev','kategorie','skupinaId','datum','casOd','casDo','sportId','popis');
        $def['skupina_id']     = $skupinaId;
        $def['sportoviste_id'] = $sportId;
        $def['cas_od']         = $casOd ?? '';
        $def['cas_do']         = $casDo ?? '';
        $def['je_verejny']     = $jeVerejny ?? 0;
    }
}

// ── Data pro selecty ─────────────────────────────────────────────────────────
$skupiny     = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
$sportovist  = $pdo->query("SELECT id, nazev FROM sportovist WHERE aktivni=1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$podskupiny  = [];
if (!empty($def['skupina_id'])) {
    $stPs = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE skupina_id=? ORDER BY poradi, nazev");
    $stPs->execute([$def['skupina_id']]);
    $podskupiny = $stPs->fetchAll(PDO::FETCH_ASSOC);
}
$eligibleTeams = [];
try {
    $eligibleTeams = trainingRosterBridgeEligibleTeams($pdo, (string)$def['datum']);
} catch (Throwable $exception) {
    error_log('planovany_trenink_form roster teams: ' . $exception->getMessage());
}

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
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $editId ? 'Upravit plánovaný trénink' : 'Nový plánovaný trénink' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4" style="max-width:640px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="bi bi-calendar-plus me-2 text-primary"></i>
            <?= $editId ? 'Upravit plánovaný trénink' : 'Nový plánovaný trénink' ?>
        </h4>
        <a href="planovac.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Plánovač
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="post" id="formPlan">
                <?= csrf_field() ?>

                <!-- Název + Kategorie -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-8">
                        <label class="form-label req">Název tréninku</label>
                        <input type="text" name="nazev" class="form-control" required
                               placeholder="např. Intervalový trénink na dráze"
                               value="<?= h($def['nazev']) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Kategorie</label>
                        <select name="kategorie" class="form-select">
                            <option value="">— vyberte —</option>
                            <?php foreach ($kategorieMeta as $key => $meta): ?>
                                <option value="<?= $key ?>" <?= ($def['kategorie'] === $key) ? 'selected' : '' ?>>
                                    <?= $meta['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Skupina + Podskupiny -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label req">Skupina</label>
                        <select name="skupina_id" id="skupinaId" class="form-select" required>
                            <option value="">— vyberte skupinu —</option>
                            <?php foreach ($skupiny as $sk): ?>
                                <option value="<?= $sk['id'] ?>"
                                    <?= ((string)$def['skupina_id'] === (string)$sk['id']) ? 'selected' : '' ?>>
                                    <?= h($sk['nazev']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Podskupiny <span class="text-muted small">(bez výběru = celá sk.)</span></label>
                        <div id="podskupinaContainer" class="border rounded p-2 bg-white"
                             style="min-height:40px;max-height:160px;overflow-y:auto">
                            <?php if (empty($podskupiny)): ?>
                                <span class="text-muted small">Nejprve vyberte skupinu</span>
                            <?php else: ?>
                                <?php foreach ($podskupiny as $ps): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="podskupiny_ids[]" value="<?= $ps['id'] ?>"
                                               id="ps_<?= $ps['id'] ?>"
                                               <?= in_array((int)$ps['id'], $selectedPodskupiny) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="ps_<?= $ps['id'] ?>">
                                            <?= h($ps['nazev']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- KIS soupisky pouze doplňují očekávané účastníky; legacy skupiny zůstávají zachované. -->
                <div class="mb-3">
                    <label class="form-label">KIS soupisky <span class="text-muted small">(volitelné)</span></label>
                    <div class="border rounded p-2 bg-white" style="max-height:180px;overflow-y:auto">
                        <?php if ($eligibleTeams === []): ?>
                            <span class="text-muted small">Pro datum tréninku není dostupná aktivní soupiska.</span>
                        <?php else: ?>
                            <?php foreach ($eligibleTeams as $team): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="team_ids[]"
                                           value="<?= (int)$team['id'] ?>" id="team_<?= (int)$team['id'] ?>"
                                           <?= in_array((int)$team['id'], $selectedTeamIds, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="team_<?= (int)$team['id'] ?>">
                                        <?= h($team['name']) ?> · <?= h($team['season_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">Členové soupisek se uloží jako očekávaní účastníci. Docházka vznikne až ručním uložením evidence tréninku.</div>
                </div>

                <!-- Datum + Časy -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <label class="form-label req">Datum</label>
                        <input type="date" name="datum" class="form-control" required
                               value="<?= h($def['datum']) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Čas od</label>
                        <input type="time" name="cas_od" class="form-control"
                               value="<?= h($def['cas_od']) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Čas do</label>
                        <input type="time" name="cas_do" class="form-control"
                               value="<?= h($def['cas_do']) ?>">
                    </div>
                </div>

                <!-- Sportoviště (informativní) -->
                <div class="mb-3">
                    <label class="form-label">Sportoviště <span class="text-muted small">(volitelné — jen informativní)</span></label>
                    <select name="sportoviste_id" class="form-select">
                        <option value="">— nevybráno —</option>
                        <?php foreach ($sportovist as $sp): ?>
                            <option value="<?= $sp['id'] ?>"
                                <?= ((string)$def['sportoviste_id'] === (string)$sp['id']) ? 'selected' : '' ?>>
                                <?= h($sp['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Popis -->
                <div class="mb-3">
                    <label class="form-label">Popis / Pokyny pro sportovce</label>
                    <textarea name="popis" class="form-control" rows="3"
                              placeholder="Struktura tréninku, co přinést, poznámky…"><?= h($def['popis']) ?></textarea>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="je_verejny" id="jeVerejny" value="1"
                           <?= !empty($def['je_verejny']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="jeVerejny">Zobrazit termín ve veřejném rozvrhu</label>
                    <div class="form-text">Veřejnost uvidí jen datum, čas, název, skupinu, kategorii a sportoviště. Neuvidí účastníky, docházku ani interní poznámky.</div>
                </div>

                <?php if (!$editId): ?>
                <!-- Opakování (jen při vytvoření) -->
                <div class="mb-4">
                    <label class="form-label fw-semibold">Opakování každý týden</label>
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="opakovani_typ"
                                   id="opNone" value="none"
                                   <?= ($_POST['opakovani_typ'] ?? 'none') === 'none' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="opNone">Bez opakování (jen jednou)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="opakovani_typ"
                                   id="opPocet" value="pocet"
                                   <?= ($_POST['opakovani_typ'] ?? '') === 'pocet' ? 'checked' : '' ?>>
                            <label class="form-check-label d-flex align-items-center gap-2 flex-wrap" for="opPocet">
                                Opakovat
                                <input type="number" name="opakovani_pocet" id="opakovaniPocet"
                                       class="form-control form-control-sm"
                                       style="width:72px" min="1" max="52"
                                       value="<?= max(1, (int)($_POST['opakovani_pocet'] ?? 4)) ?>"
                                       <?= ($_POST['opakovani_typ'] ?? 'none') !== 'pocet' ? 'disabled' : '' ?>>
                                krát
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="opakovani_typ"
                                   id="opDatum" value="datum"
                                   <?= ($_POST['opakovani_typ'] ?? '') === 'datum' ? 'checked' : '' ?>>
                            <label class="form-check-label d-flex align-items-center gap-2 flex-wrap" for="opDatum">
                                Do data
                                <input type="date" name="opakovani_do_data" id="opakovaniDoDatum"
                                       class="form-control form-control-sm"
                                       style="width:160px"
                                       value="<?= h($_POST['opakovani_do_data'] ?? '') ?>"
                                       <?= ($_POST['opakovani_typ'] ?? 'none') !== 'datum' ? 'disabled' : '' ?>>
                            </label>
                        </div>
                    </div>
                    <div id="opakovaniPreview" class="text-muted small mt-2 fst-italic"></div>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>
                        <?= $editId ? 'Uložit změny' : 'Vypsat trénink' ?>
                    </button>
                    <a href="planovac.php" class="btn btn-outline-secondary">Zrušit</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Podskupiny — checkbox loader ──────────────────────────────────────────────
function loadPodskupiny(skupinaId) {
    const container = document.getElementById('podskupinaContainer');
    if (!container) return;
    if (!skupinaId) {
        container.innerHTML = '<span class="text-muted small">Nejprve vyberte skupinu</span>';
        return;
    }
    container.innerHTML = '<span class="text-muted small"><i class="bi bi-hourglass-split me-1"></i>Načítám…</span>';
    fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(skupinaId))
        .then(r => r.json())
        .then(data => {
            const items = data.items || [];
            if (items.length === 0) {
                container.innerHTML = '<span class="text-muted small">Žádné podskupiny</span>';
                return;
            }
            container.innerHTML = '';
            items.forEach(ps => {
                const wrap  = document.createElement('div');
                wrap.className = 'form-check';
                const inp = document.createElement('input');
                inp.type = 'checkbox';
                inp.className = 'form-check-input';
                inp.name = 'podskupiny_ids[]';
                inp.value = ps.id;
                inp.id = 'ps_' + ps.id;
                const lbl = document.createElement('label');
                lbl.className = 'form-check-label small';
                lbl.htmlFor = 'ps_' + ps.id;
                lbl.textContent = ps.nazev;
                wrap.appendChild(inp);
                wrap.appendChild(lbl);
                container.appendChild(wrap);
            });
        })
        .catch(() => { container.innerHTML = '<span class="text-muted small text-danger">Chyba načítání</span>'; });
}

document.getElementById('skupinaId').addEventListener('change', function () {
    loadPodskupiny(this.value);
});

// ── Opakování — radio toggle + živý náhled ────────────────────────────────────
(function () {
    const radios   = document.querySelectorAll('input[name="opakovani_typ"]');
    const inpPocet = document.getElementById('opakovaniPocet');
    const inpDatum = document.getElementById('opakovaniDoDatum');
    const preview  = document.getElementById('opakovaniPreview');
    const datumInp = document.querySelector('[name=datum]');
    if (!radios.length) return;

    function getTyp() {
        for (const r of radios) { if (r.checked) return r.value; }
        return 'none';
    }

    function updateUI() {
        const typ = getTyp();
        if (inpPocet) inpPocet.disabled = (typ !== 'pocet');
        if (inpDatum) inpDatum.disabled = (typ !== 'datum');
        if (!preview) return;

        const datum = datumInp ? datumInp.value : '';
        if (typ === 'none' || !datum) { preview.textContent = ''; return; }
        const dtBase = new Date(datum + 'T00:00:00');
        if (isNaN(dtBase)) { preview.textContent = ''; return; }

        const dates = [new Date(dtBase)];
        if (typ === 'pocet') {
            const n = Math.max(1, Math.min(52, parseInt(inpPocet?.value || 1)));
            for (let i = 1; i <= n; i++) {
                const d = new Date(dtBase);
                d.setDate(d.getDate() + i * 7);
                dates.push(d);
            }
        } else if (typ === 'datum' && inpDatum?.value) {
            const limit = new Date(inpDatum.value + 'T00:00:00');
            if (!isNaN(limit)) {
                const cur = new Date(dtBase);
                for (let i = 0; i < 52; i++) {
                    cur.setDate(cur.getDate() + 7);
                    if (cur > limit) break;
                    dates.push(new Date(cur));
                }
            }
        }

        const fmt = d => d.toLocaleDateString('cs-CZ', {day:'numeric', month:'numeric', year:'numeric'});
        preview.textContent = dates.length === 1
            ? `Jeden trénink: ${fmt(dates[0])}`
            : `Celkem ${dates.length}× — od ${fmt(dates[0])} do ${fmt(dates[dates.length - 1])}`;
    }

    radios.forEach(r => r.addEventListener('change', updateUI));
    if (inpPocet) inpPocet.addEventListener('input', updateUI);
    if (inpDatum) inpDatum.addEventListener('input', updateUI);
    if (datumInp) datumInp.addEventListener('change', updateUI);
    updateUI();
})();
</script>
</body>
</html>
