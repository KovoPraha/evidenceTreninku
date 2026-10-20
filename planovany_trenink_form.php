<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('planovac')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/training_roster_bridge.php';
require_once __DIR__ . '/includes/roster_target_selector.php';
require_once __DIR__ . '/includes/club_makeup.php';

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
$rosterMode = 'teams';
if ($editId && $existujici) {
    $stPs = $pdo->prepare("SELECT podskupina_id FROM planovane_treninky_podskupiny WHERE plan_id=?");
    $stPs->execute([$editId]);
    $selectedPodskupiny = array_column($stPs->fetchAll(PDO::FETCH_ASSOC), 'podskupina_id');
    // Fallback na starý jednopodskupinový sloupec
    if (empty($selectedPodskupiny) && !empty($existujici['podskupina_id'])) {
        $selectedPodskupiny = [(int)$existujici['podskupina_id']];
    }
    $selectedTeamIds = trainingRosterBridgePlanTeamIds($pdo, $editId);
    if ($selectedTeamIds === []) $rosterMode = 'internal';
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
        $teamIds        = array_values(array_unique(array_filter(array_map('intval', $_POST['team_ids'] ?? []))));
        $rosterMode     = (string)($_POST['roster_mode'] ?? 'teams');
        $preserveLegacy = $editId && $selectedTeamIds === [] && $teamIds === [];
        $skupinaId     = $preserveLegacy ? ($existujici['skupina_id'] ?? null) : null;
        $podskupinyIds = $preserveLegacy ? $selectedPodskupiny : [];
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
        if (!$datum)       $errors[] = 'Zadejte datum.';
        if ($casOd && $casDo && $casOd >= $casDo) $errors[] = 'Čas "od" musí být před časem "do".';
        if (!in_array($rosterMode, ['teams','internal'], true)) $errors[] = 'Vyberte, zda je trénink určen soupiskám, nebo je interní bez účastníků.';
        if ($rosterMode === 'teams' && $teamIds === []) $errors[] = 'Pro trénink určený sportovcům vyberte alespoň jednu KIS soupisku.';
        if ($rosterMode === 'internal') $teamIds = [];

        if (empty($errors)) {
            if ($editId) {
                // Editace — jen aktuální záznam, ignoruje opakování
                $pdo->beginTransaction();
                try {
                    $currentSql = 'SELECT datum,cas_od FROM planovane_treninky WHERE id=?';
                    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $currentSql .= ' FOR UPDATE';
                    $currentPlan = $pdo->prepare($currentSql);
                    $currentPlan->execute([$editId]);
                    $current = $currentPlan->fetch(PDO::FETCH_ASSOC);
                    if (!$current) throw new RuntimeException('Plánovaný trénink již neexistuje.');
                    $oldTeamIds = trainingRosterBridgePlanTeamIds($pdo, $editId);
                    sort($oldTeamIds, SORT_NUMERIC);
                    $newTeamIds = $teamIds;
                    sort($newTeamIds, SORT_NUMERIC);
                    $scheduleOrRosterChanged = (string)$current['datum'] !== $datum
                        || substr((string)($current['cas_od'] ?? ''), 0, 5) !== (string)($casOd ?? '')
                        || $oldTeamIds !== $newTeamIds;
                    if ($scheduleOrRosterChanged) {
                        $hasAnswers = false;
                        if (familyPortalTableExists($pdo, 'training_rsvps')) {
                            $answers = $pdo->prepare('SELECT 1 FROM training_rsvps WHERE plan_id=? LIMIT 1');
                            $answers->execute([$editId]);
                            $hasAnswers = (bool)$answers->fetchColumn();
                        }
                        $hasMakeup = false;
                        if (clubMakeupReady($pdo)) {
                            foreach (['club_makeup_slots','club_makeup_credits','club_makeup_reservations'] as $table) {
                                $column = $table === 'club_makeup_credits' ? 'source_plan_id' : ($table === 'club_makeup_reservations' ? 'target_plan_id' : 'plan_id');
                                $check = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column}=? LIMIT 1");
                                $check->execute([$editId]);
                                if ($check->fetchColumn()) { $hasMakeup = true; break; }
                            }
                        }
                        if ($hasAnswers || $hasMakeup) throw new RuntimeException('Termín nebo soupisku s odpověďmi či náhradami nelze změnit. Zrušte tento termín a vypište nový.');
                    }
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
                    if ((string)$current['datum'] !== $datum || $oldTeamIds !== $newTeamIds) {
                        trainingRosterBridgeReplacePlanTeams($pdo, $editId, $teamIds, $trenerId);
                    }
                    clubMakeupSyncPlanRules($pdo, $editId);
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
                        clubMakeupSyncPlanRules($pdo, $planId);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4" style="max-width:640px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-calendar-plus me-2 text-primary"></i>
            <?= $editId ? 'Upravit plánovaný trénink' : 'Nový plánovaný trénink' ?>
        </h1>
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

                <!-- Jediný kanonický výběr cílové skupiny či soupisky. -->
                <div class="mb-3">
                    <label class="form-label req">Komu je trénink určen</label>
                    <div class="border rounded p-3 bg-light mb-2" id="training-roster-mode">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="roster_mode" id="roster-mode-teams" value="teams" <?= $rosterMode === 'teams' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="roster-mode-teams"><strong>Sportovcům z vybraných skupin nebo soupisek</strong><br><span class="small text-muted">Trénink se jim zobrazí a mohou potvrdit účast.</span></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="roster_mode" id="roster-mode-internal" value="internal" <?= $rosterMode === 'internal' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="roster-mode-internal"><strong>Interní trénink bez účastníků</strong><br><span class="small text-muted">Trénink se nezobrazí žádnému sportovci.</span></label>
                        </div>
                    </div>
                    <div id="training-roster-options" data-roster-target-container style="max-height:300px;overflow-y:auto">
                        <?= rosterTargetSelectorHtml($pdo, (string)$def['datum'], $selectedTeamIds) ?>
                    </div>
                    <?php if ($editId && $selectedTeamIds === [] && !empty($existujici['skupina_id'])): ?>
                        <div class="alert alert-warning mt-2 mb-0">Tento historický plán používá původní skupinu. Pokud nové cílení nevyberete, stará vazba zůstane zachována.</div>
                    <?php endif; ?>
                    <div class="form-text">Členové vybraných skupin a soupisek trénink uvidí ve sportovním přehledu a mohou potvrdit „zúčastním se / nezúčastním se“. Skutečnou docházku po tréninku dál zapisuje trenér.</div>
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


<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>">
// ── Soupisky — režim je vždy viditelný a jednoznačný ─────────────────────────
(function () {
    const modes = Array.from(document.querySelectorAll('input[name="roster_mode"]'));
    const options = document.getElementById('training-roster-options');
    if (!modes.length || !options) return;
    const sync = () => {
        const internal = modes.some(input => input.checked && input.value === 'internal');
        options.querySelectorAll('input[name="team_ids[]"]').forEach(input => { input.disabled = internal; });
        options.classList.toggle('opacity-50', internal);
        options.setAttribute('aria-disabled', internal ? 'true' : 'false');
    };
    modes.forEach(input => input.addEventListener('change', sync));
    options.addEventListener('roster-targets-refreshed', sync);
    sync();
})();

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
<script nonce="<?=htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8')?>" src="assets/roster-target-selector.js"></script>
</body>
</html>
