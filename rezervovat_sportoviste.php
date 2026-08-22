<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('rezervace_sportovist')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/venue_operations.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId    = (int)$_SESSION['trener_id'];
$errors      = [];
$kolizeLekce = []; // kolize s individuálními lekcemi (vyplněno při POST)
$editId=(int)($_GET['edit_id']??$_POST['edit_id']??0);
$manageAll=function_exists('staffActivePositionIs')&&staffActivePositionIs('program_coordinator');
$editRow=null;
if($editId>0){$sql='SELECT * FROM rezervace_sportovist WHERE id=? AND lekce_id IS NULL';if(!$manageAll)$sql.=' AND trener_id=?';$st=$pdo->prepare($sql);$st->execute($manageAll?[$editId]:[$editId,$trenerId]);$editRow=$st->fetch(PDO::FETCH_ASSOC)?:null;if(!$editRow){http_response_code(404);exit('Rezervace nebyla nalezena nebo ji nesmíte upravit.');}}

// ── POST: uložení ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $sportoviste = (int)($_POST['sportoviste_id'] ?? 0);
        $datum       = $_POST['datum'] ?? '';
        $casOd       = $_POST['cas_od'] ?? '';
        $casDo       = $_POST['cas_do'] ?? '';
        $kapacita    = max(1, min(5, (int)($_POST['kapacita_dilu'] ?? 1)));
        $poznamka    = trim($_POST['poznamka'] ?? '');

        if (!$sportoviste) $errors[] = 'Vyberte sportoviště.';
        if (!$datum)       $errors[] = 'Zadejte datum.';
        if (!$casOd)       $errors[] = 'Zadejte čas od.';
        if (!$casDo)       $errors[] = 'Zadejte čas do.';
        if ($casOd && $casDo && $casOd >= $casDo) $errors[] = 'Čas "od" musí být před časem "do".';

        $overrideLekce = !empty($_POST['override_lekce']);

        if (empty($errors)) {
            // Kontrola dostupné kapacity — lekce_id IS NULL (lekce neblokují sportoviště)
            $stmtKap = $pdo->prepare("
                SELECT COALESCE(SUM(kapacita_dilu), 0) AS obsazeno
                FROM rezervace_sportovist
                WHERE sportoviste_id = ? AND datum = ?
                  AND cas_od < ? AND cas_do > ?
                  AND lekce_id IS NULL
                  AND id <> ?
            ");
            $stmtKap->execute([$sportoviste, $datum, $casDo, $casOd, $editId]);
            $obsazeno = (int)$stmtKap->fetchColumn();

            $stSport = $pdo->prepare("SELECT max_kapacita FROM sportovist WHERE id=?");
            $stSport->execute([$sportoviste]);
            $maxKap = (int)($stSport->fetchColumn() ?: 5);

            if ($obsazeno + $kapacita > $maxKap) {
                $errors[] = "Sportoviště je v tomto čase obsazeno ({$obsazeno}/{$maxKap}). Zbývá " . ($maxKap - $obsazeno) . " díl(y).";
            }
        }

        // Kontrola překrývajících se individuálních lekcí (varování, ne blokování)
        $kolizeLekce = [];
        if (empty($errors)) {
            $stmtLekKol = $pdo->prepare("
                SELECT il.id, il.nazev, il.cas_od, il.cas_do, il.typ,
                       COUNT(vr.id) AS celkem_rez,
                       SUM(vr.stav IN ('ceka','potvrzena')) AS aktivni_rez
                FROM individualni_lekce il
                LEFT JOIN verejne_rezervace vr ON vr.lekce_id = il.id
                WHERE il.sportoviste_id = ? AND il.datum = ? AND il.stav = 'aktivni'
                  AND il.cas_od < ? AND il.cas_do > ?
                GROUP BY il.id
            ");
            $stmtLekKol->execute([$sportoviste, $datum, $casDo, $casOd]);
            $kolizeLekce = $stmtLekKol->fetchAll(PDO::FETCH_ASSOC);

            // Pokud existují kolize a trenér nezaškrtl override → přerušit a zobrazit varování
            if (!empty($kolizeLekce) && !$overrideLekce) {
                // Nastavit příznak — formulář zobrazí varování + checkbox
                $errors[] = '__lekce_kolize__'; // interní příznak, zobrazí se jinak
            }
        }

        if (empty($errors)) {
            $lockName = 'sportoviste_' . $sportoviste . '_' . $datum . '_' . preg_replace('/[^0-9]/', '', $casOd) . '_' . preg_replace('/[^0-9]/', '', $casDo);
            $gotLock = false;
            try {
                $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 5)");
                $lockStmt->execute([$lockName]);
                $gotLock = ((int)$lockStmt->fetchColumn() === 1);
                if (!$gotLock) {
                    throw new RuntimeException('Rezervaci se nepodarilo uzamknout, zkuste to prosim znovu.');
                }

                $pdo->beginTransaction();

                $stmtKap = $pdo->prepare("
                    SELECT COALESCE(SUM(kapacita_dilu), 0) AS obsazeno
                    FROM rezervace_sportovist
                    WHERE sportoviste_id = ? AND datum = ?
                      AND cas_od < ? AND cas_do > ?
                      AND lekce_id IS NULL
                      AND id <> ?
                ");
                $stmtKap->execute([$sportoviste, $datum, $casDo, $casOd, $editId]);
                $obsazeno = (int)$stmtKap->fetchColumn();

                $stSport = $pdo->prepare("SELECT max_kapacita FROM sportovist WHERE id=?");
                $stSport->execute([$sportoviste]);
                $maxKap = (int)($stSport->fetchColumn() ?: 5);

                if ($obsazeno + $kapacita > $maxKap) {
                    throw new RuntimeException("Sportoviste je v tomto case obsazeno ({$obsazeno}/{$maxKap}). Zbyva " . ($maxKap - $obsazeno) . " dil(y).");
                }

                if($editRow){
                    $pdo->prepare('UPDATE rezervace_sportovist SET sportoviste_id=?,datum=?,cas_od=?,cas_do=?,kapacita_dilu=?,poznamka=? WHERE id=?')->execute([$sportoviste,$datum,$casOd,$casDo,$kapacita,$poznamka?:null,$editId]);
                    $pdo->prepare('UPDATE planovane_treninky SET sportoviste_id=?,datum=?,cas_od=?,cas_do=? WHERE rezervace_id=?')->execute([$sportoviste,$datum,$casOd,$casDo,$editId]);
                    venueOperationAudit($pdo,'venue_reservation',$editId,$trenerId,'update',trim((string)($_POST['reason']??'')),['from'=>$editRow,'to'=>['sportoviste_id'=>$sportoviste,'datum'=>$datum,'cas_od'=>$casOd,'cas_do'=>$casDo,'kapacita_dilu'=>$kapacita,'poznamka'=>$poznamka]]);
                    $rezervaceId=$editId;
                }else{
                    $pdo->prepare("
                        INSERT INTO rezervace_sportovist
                            (sportoviste_id, trener_id, datum, cas_od, cas_do, kapacita_dilu, poznamka)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$sportoviste, $trenerId, $datum, $casOd, $casDo, $kapacita, $poznamka ?: null]);
                    $rezervaceId = (int)$pdo->lastInsertId();
                }

                // Volitelný plánovaný trénink
                if (!$editRow && !empty($_POST['vytvorit_plan'])) {
                    $planNazev    = trim($_POST['plan_nazev'] ?? '');
                    $planSkupina  = (int)($_POST['plan_skupina_id'] ?? 0);
                    $planPodskIds = array_values(array_filter(array_map('intval', $_POST['plan_podskupiny_ids'] ?? [])));
                    $planPodsk    = !empty($planPodskIds) ? $planPodskIds[0] : null;
                    $planKatEnum  = ['silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani'];
                    $planKat      = in_array($_POST['plan_kategorie'] ?? '', $planKatEnum, true)
                                    ? $_POST['plan_kategorie'] : null;

                    if ($planSkupina && $planNazev) {
                        $pdo->prepare("
                            INSERT INTO planovane_treninky
                                (trener_id, nazev, kategorie, skupina_id, podskupina_id,
                                 datum, cas_od, cas_do, sportoviste_id, rezervace_id)
                            VALUES (?,?,?,?,?,?,?,?,?,?)
                        ")->execute([
                            $trenerId, $planNazev ?: 'Trénink', $planKat,
                            $planSkupina, $planPodsk,
                            $datum, $casOd, $casDo, $sportoviste, $rezervaceId,
                        ]);
                        $planId = (int)$pdo->lastInsertId();
                        if (!empty($planPodskIds)) {
                            $stmtPs = $pdo->prepare("INSERT IGNORE INTO planovane_treninky_podskupiny (plan_id, podskupina_id) VALUES (?,?)");
                            foreach ($planPodskIds as $psId) { $stmtPs->execute([$planId, $psId]); }
                        }
                    }
                }

                $pdo->commit();
                if ($gotLock) {
                    $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                    $unlockStmt->execute([$lockName]);
                    $gotLock = false;
                }
                $_SESSION['flash_success'] = $editRow?'Rezervace sportoviště byla upravena a změna auditována.':'Rezervace sportoviště byla uložena.'
                    . (!empty($_POST['vytvorit_plan']) && !empty($planSkupina) && !empty($planNazev)
                        ? ' Plánovaný trénink vypsán.' : '');
                header('Location: kalendar_sportovist.php?datum=' . $datum);
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($gotLock) {
                    try {
                        $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                        $unlockStmt->execute([$lockName]);
                    } catch (Throwable $unlockError) {}
                }
                $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
            }
        }
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$sportovist = $pdo->query("SELECT id, nazev FROM sportovist WHERE aktivni=1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$skupiny    = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
$defaultDatum = $editRow['datum']??($_GET['datum'] ?? date('Y-m-d'));
$defaultSport = (int)($editRow['sportoviste_id']??($_GET['sportoviste_id'] ?? 0));
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rezervovat sportoviště</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4" style="max-width:1060px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-calendar-plus me-2 text-primary"></i><?= $editRow?'Upravit rezervaci sportoviště':'Nová rezervace sportoviště' ?></h1>
        <a href="kalendar_sportovist.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kalendář
        </a>
    </div>

    <?php
    // Zobrazit standardní chyby (kromě interního příznaku kolize lekcí)
    foreach ($errors as $e) {
        if ($e === '__lekce_kolize__') continue;
        echo '<div class="alert alert-danger">' . h($e) . '</div>';
    }
    ?>

    <div class="row g-3 align-items-start">
    <div class="col-md-7">
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" id="formRezervace">
                <?= csrf_field() ?>
                <?php if($editRow):?><input type="hidden" name="edit_id" value="<?=(int)$editRow['id']?>"><?php endif;?>

                <div class="mb-3">
                    <label class="form-label req" for="sportoviste_id">Sportoviště</label>
                    <select name="sportoviste_id" id="sportoviste_id" class="form-select" required>
                        <option value="">— vyberte —</option>
                        <?php foreach ($sportovist as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                <?= ($defaultSport === (int)$s['id'] || (isset($_POST['sportoviste_id']) && (int)$_POST['sportoviste_id'] === (int)$s['id'])) ? 'selected' : '' ?>>
                                <?= h($s['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label req" for="rezervace-datum">Datum</label>
                    <input type="date" name="datum" id="rezervace-datum" class="form-control"
                           value="<?= h($_POST['datum'] ?? $defaultDatum) ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label req" for="rezervace-cas-od">Čas od</label>
                        <input type="time" name="cas_od" id="rezervace-cas-od" class="form-control"
                               value="<?= h($_POST['cas_od'] ?? ($editRow['cas_od']??'07:00')) ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label req" for="rezervace-cas-do">Čas do</label>
                        <input type="time" name="cas_do" id="rezervace-cas-do" class="form-control"
                               value="<?= h($_POST['cas_do'] ?? ($editRow['cas_do']??'08:00')) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label req" for="kapacita">Kapacita <span id="kapLabel" class="text-primary fw-bold">1/5</span></label>
                    <input type="range" name="kapacita_dilu" id="kapacita" class="form-range"
                           min="1" max="5" value="<?= (int)($_POST['kapacita_dilu'] ?? ($editRow['kapacita_dilu']??1)) ?>">
                    <div class="d-flex justify-content-between text-muted small px-1">
                        <span>1/5 – malá část</span>
                        <span>5/5 – celé sportoviště</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="rezervace-poznamka">Poznámka</label>
                    <textarea name="poznamka" id="rezervace-poznamka" class="form-control" rows="2"><?= h($_POST['poznamka'] ?? ($editRow['poznamka']??'')) ?></textarea>
                </div>

                <?php if($editRow):?><div class="mb-3"><label class="form-label req" for="rezervace-reason">Důvod změny</label><input class="form-control" id="rezervace-reason" name="reason" maxlength="1000" required placeholder="Proč se rezervace přesouvá nebo mění"></div><?php endif;?>

                <!-- Volitelně: plánovaný trénink -->
                <?php if (!$editRow && canAccess('planovac')): ?>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="vytvorit_plan"
                               id="vytvoritPlan" value="1"
                               <?= !empty($_POST['vytvorit_plan']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="vytvoritPlan">
                            <i class="bi bi-calendar3-week me-1 text-primary"></i>
                            Zároveň vytvořit plánovaný trénink
                        </label>
                    </div>
                    <div id="planPanel" class="card card-body bg-light border p-3 <?= empty($_POST['vytvorit_plan']) ? 'd-none' : '' ?>">
                        <div class="row g-3">
                            <div class="col-sm-8">
                                <label class="form-label req" for="plan-nazev">Název tréninku</label>
                                <input type="text" name="plan_nazev" id="plan-nazev" class="form-control"
                                       placeholder="např. Intervalový trénink"
                                       value="<?= h($_POST['plan_nazev'] ?? '') ?>">
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label" for="plan-kategorie">Kategorie</label>
                                <select name="plan_kategorie" id="plan-kategorie" class="form-select">
                                    <option value="">— vyberte —</option>
                                    <?php foreach (['silnice'=>'Silnice','mtb'=>'MTB','draha'=>'Dráha',
                                        'cyklokros'=>'Cyklokros','posilovna'=>'Posilovna',
                                        'atletika'=>'Atletika','cviceni'=>'Cvičení','plavani'=>'Plavání'] as $k=>$v): ?>
                                        <option value="<?= $k ?>" <?= ($_POST['plan_kategorie'] ?? '') === $k ? 'selected' : '' ?>>
                                            <?= $v ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label req" for="planSkupinaId">Skupina</label>
                                <select name="plan_skupina_id" id="planSkupinaId" class="form-select">
                                    <option value="">— vyberte skupinu —</option>
                                    <?php foreach ($skupiny as $sk): ?>
                                        <option value="<?= $sk['id'] ?>"
                                            <?= ((int)($_POST['plan_skupina_id'] ?? 0) === (int)$sk['id']) ? 'selected' : '' ?>>
                                            <?= h($sk['nazev']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Podskupiny <span class="text-muted small">(žádná = celá skupina)</span></label>
                                <div id="planPodskupinaContainer" class="border rounded p-2 bg-white"
                                     style="min-height:40px;max-height:150px;overflow-y:auto">
                                    <span class="text-muted small" id="planPodskNapoveda">Nejprve vyberte skupinu.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Indikátor obsazenosti -->
                <div id="dostupnostPanel" class="alert alert-info d-none mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <span id="dostupnostText"></span>
                </div>

                <?php
                // Varování o kolizi s individuálními lekcemi — zobrazit jako override sekci
                $jeKolize = in_array('__lekce_kolize__', $errors, true);
                if ($jeKolize && !empty($kolizeLekce)):
                ?>
                <div class="alert alert-warning border-warning mb-3" id="lekcePriorityAlert">
                    <div class="fw-semibold mb-2">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Pozor: V tomto čase je vypsán<?= count($kolizeLekce) > 1 ? 'o' : '' ?>
                        <?= count($kolizeLekce) ?> individuální lekc<?= count($kolizeLekce) > 1 ? 'e' : 'e' ?> pro veřejnost!
                    </div>
                    <ul class="mb-3 small">
                        <?php foreach ($kolizeLekce as $kl): ?>
                            <li>
                                <strong><?= h($kl['nazev']) ?></strong>
                                — <?= h(substr($kl['cas_od'], 0, 5)) ?>–<?= h(substr($kl['cas_do'], 0, 5)) ?>
                                <?php if ((int)$kl['aktivni_rez'] > 0): ?>
                                    <span class="text-danger">
                                        (<i class="bi bi-person-check me-1"></i><?= (int)$kl['aktivni_rez'] ?> aktivní rezervac<?= (int)$kl['aktivni_rez'] === 1 ? 'e' : 'í' ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">(bez rezervace)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="override_lekce"
                               id="overrideLekce" value="1">
                        <label class="form-check-label fw-semibold" for="overrideLekce">
                            Rozumím — přesto chci rezervaci uložit (vypsané lekce mají prioritu)
                        </label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Uložit rezervaci
                    </button>
                    <a href="kalendar_sportovist.php" class="btn btn-outline-secondary">Zrušit</a>
                </div>
            </form>
        </div>
    </div>
    </div><!-- /col-md-7 -->

    <!-- ── Sidebar: rozvrh dne ── -->
    <div class="col-md-5">
        <button class="btn btn-outline-secondary btn-sm w-100 mb-2 d-md-none" type="button"
                data-bs-toggle="collapse" data-bs-target="#sidebarRozvrh">
            <i class="bi bi-clock-history me-1"></i>Zobrazit rozvrh dne
        </button>
        <div class="collapse d-md-block" id="sidebarRozvrh">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold small"><i class="bi bi-clock me-1 text-primary"></i>Rozvrh dne</span>
                    <span class="text-muted small" id="rozvrhLabel">—</span>
                </div>
                <div class="card-body p-2" style="overflow-y:auto;max-height:640px">
                    <div id="rozvrhContent">
                        <div class="text-muted small text-center py-4"><i class="bi bi-calendar3 me-1"></i>Vyberte sportoviště a datum.</div>
                    </div>
                </div>
                <div class="card-footer p-2">
                    <div class="d-flex gap-3 flex-wrap" style="font-size:.69rem;color:#6b7280">
                        <span><span style="display:inline-block;width:10px;height:10px;background:#3b82f6;border-radius:2px;vertical-align:middle"></span> Rezervace</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:#f0fdf4;border:1px dashed #16a34a;border-radius:2px;vertical-align:middle"></span> Lekce</span>
                        <span><span style="display:inline-block;width:10px;height:10px;background:rgba(59,130,246,.25);border:1px solid #3b82f6;border-radius:2px;vertical-align:middle"></span> Váš výběr</span>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /col-md-5 -->
    </div><!-- /row -->
</div>


<script>
(() => {
    const kapInput = document.getElementById('kapacita');
    const kapLabel = document.getElementById('kapLabel');
    kapInput.addEventListener('input', () => { kapLabel.textContent = kapInput.value + '/5'; });
    kapLabel.textContent = kapInput.value + '/5';

    // Kontrola obsazenosti při změně polí
    const sportSelect = document.getElementById('sportoviste_id');
    const datumInput  = document.querySelector('[name=datum]');
    const casOdInput  = document.querySelector('[name=cas_od]');
    const casDoInput  = document.querySelector('[name=cas_do]');
    const panel       = document.getElementById('dostupnostPanel');
    const panelText   = document.getElementById('dostupnostText');

    function checkAvailability() {
        const s = sportSelect.value, d = datumInput.value,
              od = casOdInput.value, doo = casDoInput.value;
        if (!s || !d || !od || !doo || od >= doo) { panel.classList.add('d-none'); return; }

        fetch(`ajax_dostupnost_sportovist.php?sportoviste_id=${s}&datum=${d}&cas_od=${od}&cas_do=${doo}`)
            .then(r => r.json())
            .then(data => {
                panel.classList.remove('d-none', 'alert-info', 'alert-warning', 'alert-danger');
                if (data.obsazeno === 0) {
                    panel.classList.add('alert-info');
                    panelText.textContent = `Sportoviště je volné (0/${data.max} obsazeno).`;
                } else if (data.obsazeno < data.max) {
                    panel.classList.add('alert-warning');
                    panelText.textContent = `Obsazeno ${data.obsazeno}/${data.max} — zbývá ${data.max - data.obsazeno} díl(y).`;
                } else {
                    panel.classList.add('alert-danger');
                    panelText.textContent = `Sportoviště je plně obsazeno (${data.obsazeno}/${data.max}).`;
                }
            })
            .catch(() => panel.classList.add('d-none'));
    }

    [sportSelect, datumInput, casOdInput, casDoInput].forEach(el => {
        el.addEventListener('change', checkAvailability);
    });
})();

// ── Sidebar: rozvrh dne ─────────────────────────────────────────────────────
(function () {
    const sportSel = document.getElementById('sportoviste_id');
    const datumInp = document.querySelector('[name=datum]');
    const casOdInp = document.querySelector('[name=cas_od]');
    const casDoInp = document.querySelector('[name=cas_do]');
    const content  = document.getElementById('rozvrhContent');
    const labelEl  = document.getElementById('rozvrhLabel');
    let timer = null;

    function fetchRozvrh() {
        const s  = sportSel?.value ?? '';
        const d  = datumInp?.value ?? '';
        const od = casOdInp?.value ?? '';
        const doo= casDoInp?.value ?? '';
        if (!s || !d) {
            content.innerHTML = '<div class="text-muted small text-center py-4"><i class="bi bi-calendar3 me-1"></i>Vyberte sportoviště a datum.</div>';
            if (labelEl) labelEl.textContent = '—';
            return;
        }
        if (labelEl) labelEl.textContent = d;
        let url = `ajax_denny_rozvrh.php?sportoviste_id=${encodeURIComponent(s)}&datum=${encodeURIComponent(d)}`;
        if (od && doo && od < doo) url += `&ghost_od=${encodeURIComponent(od)}&ghost_do=${encodeURIComponent(doo)}`;
        content.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
        fetch(url).then(r => r.text()).then(html => { content.innerHTML = html; })
                  .catch(() => { content.innerHTML = '<div class="text-danger small p-2">Chyba načítání.</div>'; });
    }
    function debounce(fn, ms) { clearTimeout(timer); timer = setTimeout(fn, ms); }

    sportSel?.addEventListener('change', () => debounce(fetchRozvrh, 0));
    datumInp?.addEventListener('change', () => debounce(fetchRozvrh, 0));
    casOdInp?.addEventListener('input',  () => debounce(fetchRozvrh, 350));
    casDoInp?.addEventListener('input',  () => debounce(fetchRozvrh, 350));
    casOdInp?.addEventListener('change', () => debounce(fetchRozvrh, 0));
    casDoInp?.addEventListener('change', () => debounce(fetchRozvrh, 0));

    fetchRozvrh(); // initial
})();

// Toggle plánovacího panelu
(function () {
    const chk   = document.getElementById('vytvoritPlan');
    const panel = document.getElementById('planPanel');
    if (!chk || !panel) return;
    chk.addEventListener('change', () => panel.classList.toggle('d-none', !chk.checked));

    // AJAX podskupiny pro panel plánu — checkbox výběr (více podskupin)
    const skupSel   = document.getElementById('planSkupinaId');
    const podskCont = document.getElementById('planPodskupinaContainer');
    const podskHint = document.getElementById('planPodskNapoveda');
    if (skupSel && podskCont) {
        skupSel.addEventListener('change', function () {
            podskCont.innerHTML = '<span class="text-muted small">Načítám…</span>';
            if (!this.value) {
                podskCont.innerHTML = '<span class="text-muted small" id="planPodskNapoveda">Nejprve vyberte skupinu.</span>';
                return;
            }
            fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(this.value))
                .then(r => r.json())
                .then(data => {
                    const items = data.items || [];
                    if (items.length === 0) {
                        podskCont.innerHTML = '<span class="text-muted small">Skupina nemá podskupiny.</span>';
                        return;
                    }
                    podskCont.innerHTML = '';
                    items.forEach(ps => {
                        const wrap  = document.createElement('div');
                        wrap.className = 'form-check';
                        const cb  = document.createElement('input');
                        cb.className = 'form-check-input';
                        cb.type = 'checkbox';
                        cb.name = 'plan_podskupiny_ids[]';
                        cb.value = ps.id;
                        cb.id = 'planPs_' + ps.id;
                        const lbl = document.createElement('label');
                        lbl.className = 'form-check-label small';
                        lbl.htmlFor = 'planPs_' + ps.id;
                        lbl.textContent = ps.nazev;
                        wrap.appendChild(cb);
                        wrap.appendChild(lbl);
                        podskCont.appendChild(wrap);
                    });
                })
                .catch(() => {
                    podskCont.innerHTML = '<span class="text-muted small text-danger">Chyba načítání.</span>';
                });
        });
    }
})();
</script>
</body>
</html>
