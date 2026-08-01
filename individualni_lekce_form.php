<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('individualni_lekce')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId = (int)$_SESSION['trener_id'];
$errors   = [];

// ── Předvyplnění z kopie (?kopie_id=X) ───────────────────────────────────────
$kopie = null;
$kopieId = (int)($_GET['kopie_id'] ?? 0);
if ($kopieId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stK = $pdo->prepare("SELECT * FROM individualni_lekce WHERE id=? AND trener_id=?");
    $stK->execute([$kopieId, $trenerId]);
    $kopie = $stK->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($kopie) {
        // Datum posunout o +7 dní
        $kopie['datum'] = (new DateTime($kopie['datum']))->modify('+7 days')->format('Y-m-d');
    }
}

// Výchozí hodnoty (kopie nebo prázdné)
$def = [
    'sportoviste_id' => $kopie['sportoviste_id'] ?? '',
    'nazev'          => $kopie['nazev']          ?? '',
    'datum'          => $kopie['datum']           ?? date('Y-m-d'),
    'cas_od'         => $kopie['cas_od']          ?? '10:00',
    'cas_do'         => $kopie['cas_do']          ?? '18:00',
    'typ'            => $kopie['typ']             ?? 'zelena',
    'slot_delka_min' => $kopie['slot_delka_min']  ?? 60,
    'cena_kc'        => $kopie['cena_kc']         ?? '0',
    'max_osob'       => $kopie['max_osob']        ?? '1',
    'popis'          => $kopie['popis']           ?? '',
    'vyjimka_3_dny'  => $kopie['vyjimka_3_dny']  ?? 0,
];

// URL předvyplnění z kalendáře (?datum=X&sportoviste_id=Y) — jen při GET bez kopie
if (!$kopie && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!empty($_GET['datum']))          $def['datum']          = $_GET['datum'];
    if (!empty($_GET['sportoviste_id'])) $def['sportoviste_id'] = (int)$_GET['sportoviste_id'];
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $sportId       = (int)($_POST['sportoviste_id'] ?? 0);
        $datum         = trim($_POST['datum'] ?? '');
        $casOd         = trim($_POST['cas_od'] ?? '');
        $casDo         = trim($_POST['cas_do'] ?? '');
        $slotDelka     = max(15, min(240, (int)($_POST['slot_delka_min'] ?? 60)));
        $typ           = in_array($_POST['typ'] ?? '', ['zelena', 'zluta'], true) ? $_POST['typ'] : 'zelena';
        $nazev         = trim($_POST['nazev'] ?? '');
        $popis         = trim($_POST['popis'] ?? '');
        $cena          = max(0, (float)str_replace(',', '.', $_POST['cena_kc'] ?? '0'));
        $maxOsob        = max(1, (int)($_POST['max_osob'] ?? 1));
        $vyjimka3dny   = !empty($_POST['vyjimka_3_dny']) ? 1 : 0;
        $opakovaniTydnu = max(0, min(24, (int)($_POST['opakovani_tydnu'] ?? 0)));

        // Validace
        if (!$sportId) $errors[] = 'Vyberte sportoviště.';
        if (!$datum)   $errors[] = 'Zadejte datum.';
        if (!$casOd)   $errors[] = 'Zadejte čas od.';
        if (!$casDo)   $errors[] = 'Zadejte čas do.';
        if ($casOd && $casDo && $casOd >= $casDo) $errors[] = 'Čas "od" musí být před časem "do".';
        if ($nazev === '') $errors[] = 'Zadejte název lekce.';

        // Ověřit, že okno je dělitelné slotem
        if (empty($errors)) {
            $odMin  = (int)substr($casOd, 0, 2) * 60 + (int)substr($casOd, 3, 2);
            $doMin  = (int)substr($casDo, 0, 2) * 60 + (int)substr($casDo, 3, 2);
            $delka  = $doMin - $odMin;
            if ($delka < $slotDelka) {
                $errors[] = 'Délka okna (' . $delka . ' min) je kratší než délka slotu (' . $slotDelka . ' min).';
            }
        }

        if (empty($errors)) {
            // Vytvořit seznam datumů (první + opakování)
            $datumy = [];
            $dtBase = new DateTime($datum);
            $datumy[] = $dtBase->format('Y-m-d');
            for ($i = 1; $i <= $opakovaniTydnu; $i++) {
                $datumy[] = (clone $dtBase)->modify("+{$i} weeks")->format('Y-m-d');
            }

            $pdo->beginTransaction();
            try {
                $stmtLekce = $pdo->prepare("
                    INSERT INTO individualni_lekce
                        (trener_id, sportoviste_id, datum, cas_od, cas_do, slot_delka_min, typ, nazev, popis, cena_kc, max_osob, vyjimka_3_dny)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                foreach ($datumy as $d) {
                    $stmtLekce->execute([$trenerId, $sportId, $d, $casOd, $casDo,
                                         $slotDelka, $typ, $nazev, $popis ?: null, $cena, $maxOsob, $vyjimka3dny]);
                    // Poznámka: individuální lekce záměrně NEBLOKUJÍ rezervace_sportovist.
                    // Sportoviště se blokuje až při potvrzeném týmovém tréninku (přes formular.php).
                }

                $pdo->commit();
                $pocet = count($datumy);
                $_SESSION['flash_success'] = $pocet > 1
                    ? "Vypsáno {$pocet} lekcí (opakování každý týden)."
                    : 'Individuální lekce byla vypsána.';
                header('Location: individualni_lekce_sprava.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
            }
        }

        // Zachovat hodnoty z POST pro zobrazení
        $def = [
            'sportoviste_id' => $sportId,
            'nazev'          => $nazev,
            'datum'          => $datum,
            'cas_od'         => $casOd,
            'cas_do'         => $casDo,
            'typ'            => $typ,
            'slot_delka_min' => $slotDelka,
            'cena_kc'        => $cena,
            'max_osob'       => $maxOsob,
            'popis'          => $popis,
            'vyjimka_3_dny'  => $vyjimka3dny,
        ];
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$sportovist = $pdo->query("
    SELECT id, nazev FROM sportovist WHERE aktivni=1 AND je_verejne=1 ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $kopie ? 'Duplikovat lekci' : 'Nová individuální lekce' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4" style="max-width:1060px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="bi bi-<?= $kopie ? 'copy' : 'person-plus' ?> me-2 text-success"></i>
            <?= $kopie ? 'Duplikovat lekci' : 'Nová individuální lekce' ?>
        </h4>
        <a href="individualni_lekce_sprava.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Přehled lekcí
        </a>
    </div>

    <?php if ($kopie): ?>
    <div class="alert alert-info border-0 py-2 mb-3">
        <i class="bi bi-copy me-1"></i>Duplikuješ lekci <strong><?= h($kopie['nazev']) ?></strong> — datum posunut +7 dní. Upravte dle potřeby.
    </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="row g-3 align-items-start">
    <div class="col-md-7">
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" id="lekceForm">
                <?= csrf_field() ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label req">Název lekce</label>
                        <input type="text" name="nazev" class="form-control"
                               value="<?= h($def['nazev']) ?>"
                               placeholder="Individuální jízda na velodromu" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label req">Sportoviště</label>
                        <select name="sportoviste_id" class="form-select" required>
                            <option value="">— vyberte —</option>
                            <?php foreach ($sportovist as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                    <?= (int)$def['sportoviste_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= h($s['nazev']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label req">Datum</label>
                        <input type="date" name="datum" class="form-control"
                               value="<?= h($def['datum']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label req">Okno od</label>
                        <input type="time" name="cas_od" id="casOd" class="form-control"
                               value="<?= h(substr($def['cas_od'], 0, 5)) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label req">Okno do</label>
                        <input type="time" name="cas_do" id="casDo" class="form-control"
                               value="<?= h(substr($def['cas_do'], 0, 5)) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label req">Délka slotu</label>
                        <select name="slot_delka_min" id="slotDelka" class="form-select">
                            <?php foreach ([30=>'30 min',45=>'45 min',60=>'60 min',90=>'90 min',120=>'2 hodiny'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= (int)$def['slot_delka_min'] === $v ? 'selected' : '' ?>>
                                    <?= $l ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Náhled slotů -->
                <div id="slotPreview" class="mb-3 p-2 bg-light rounded border small text-muted">
                    Sloty se zobrazí po vyplnění časů.
                </div>

                <!-- Typ lekce -->
                <div class="mb-3">
                    <label class="form-label req">Typ lekce</label>
                    <div class="d-flex gap-3">
                        <div class="card flex-fill border-success <?= $def['typ'] === 'zelena' ? 'border-2' : '' ?>"
                             style="cursor:pointer" id="cardZelena" onclick="setTyp('zelena')">
                            <div class="card-body p-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="typ" id="typZelena"
                                           value="zelena" <?= $def['typ'] === 'zelena' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="typZelena">
                                        <span class="text-success fw-semibold"><i class="bi bi-circle-fill me-1"></i>Zelená</span><br>
                                        <small class="text-muted">Zákazník rezervuje přímo — dostaneš email.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card flex-fill border-warning <?= $def['typ'] === 'zluta' ? 'border-2' : '' ?>"
                             style="cursor:pointer" id="cardZluta" onclick="setTyp('zluta')">
                            <div class="card-body p-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="typ" id="typZluta"
                                           value="zluta" <?= $def['typ'] === 'zluta' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="typZluta">
                                        <span class="text-warning fw-semibold"><i class="bi bi-circle-fill me-1"></i>Žlutá</span><br>
                                        <small class="text-muted">Zákazník žádá — musíš potvrdit.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label req">Cena za slot (Kč)</label>
                        <input type="number" name="cena_kc" class="form-control"
                               value="<?= h($def['cena_kc']) ?>"
                               min="0" step="50" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max. osob na slot</label>
                        <input type="number" name="max_osob" class="form-control"
                               value="<?= h($def['max_osob']) ?>"
                               min="1" max="50">
                        <div class="form-text">Kolik zákazníků může rezervovat stejný slot</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Popis (viditelný zákazníkovi)</label>
                    <textarea name="popis" class="form-control" rows="2"><?= h($def['popis']) ?></textarea>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="vyjimka_3_dny"
                               id="vyjimka3dny" value="1"
                               <?= $def['vyjimka_3_dny'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="vyjimka3dny">
                            <i class="bi bi-unlock me-1 text-warning"></i>
                            <strong>Výjimka — přihlášení i méně než 3 dny předem</strong>
                        </label>
                    </div>
                    <div class="form-text text-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Standardně mohou zákazníci rezervovat min. 3 dny předem.
                        Zaškrtnutím zrušíte tento limit pro tuto lekci.
                    </div>
                </div>

                <!-- Opakování -->
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="opakovaniToggle"
                                   <?= (int)($_POST['opakovani_tydnu'] ?? 0) > 0 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="opakovaniToggle">
                                <i class="bi bi-arrow-repeat me-1 text-primary"></i>Opakovat každý týden
                            </label>
                        </div>
                        <div id="opakovaniPanel" class="<?= (int)($_POST['opakovani_tydnu'] ?? 0) > 0 ? '' : 'd-none' ?>">
                            <label class="form-label small">Počet opakování (týdnů)</label>
                            <select name="opakovani_tydnu" class="form-select form-select-sm w-auto">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= (int)($_POST['opakovani_tydnu'] ?? 0) === $i ? 'selected' : '' ?>>
                                        <?= $i ?>× (<?= $i+1 ?> termínů celkem)
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <div class="form-text mt-1">
                                Systém automaticky vytvoří záznamy pro každý další týden od zadaného datumu.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Vypsat lekci
                    </button>
                    <a href="individualni_lekce_sprava.php" class="btn btn-outline-secondary">Zrušit</a>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setTyp(typ) {
    document.getElementById('typZelena').checked = typ === 'zelena';
    document.getElementById('typZluta').checked  = typ === 'zluta';
    document.getElementById('cardZelena').classList.toggle('border-2', typ === 'zelena');
    document.getElementById('cardZluta').classList.toggle('border-2', typ === 'zluta');
}

// Náhled slotů
function updateSlotPreview() {
    const casOd = document.getElementById('casOd').value;
    const casDo = document.getElementById('casDo').value;
    const delka = parseInt(document.getElementById('slotDelka').value);
    const el    = document.getElementById('slotPreview');
    if (!casOd || !casDo || !delka) { el.textContent = 'Sloty se zobrazí po vyplnění časů.'; return; }

    const toMin = t => { const [h,m] = t.split(':').map(Number); return h*60+m; };
    const toStr = m => String(Math.floor(m/60)).padStart(2,'0') + ':' + String(m%60).padStart(2,'0');

    const odM = toMin(casOd), doM = toMin(casDo);
    if (doM <= odM) { el.textContent = 'Čas "do" musí být po čase "od".'; return; }
    if ((doM - odM) < delka) { el.innerHTML = '<span class="text-danger">Okno je kratší než délka slotu!</span>'; return; }

    const sloty = [];
    for (let s = odM; s + delka <= doM; s += delka) {
        sloty.push(toStr(s) + '–' + toStr(s + delka));
    }
    el.innerHTML = '<i class="bi bi-clock me-1"></i><strong>' + sloty.length + ' slotů:</strong> ' + sloty.join(', ');
}

document.getElementById('casOd').addEventListener('change', updateSlotPreview);
document.getElementById('casDo').addEventListener('change', updateSlotPreview);
document.getElementById('slotDelka').addEventListener('change', updateSlotPreview);

// Opakování toggle
document.getElementById('opakovaniToggle').addEventListener('change', function () {
    document.getElementById('opakovaniPanel').classList.toggle('d-none', !this.checked);
    if (!this.checked) {
        document.querySelector('[name=opakovani_tydnu]').value = '0';
    }
});
// init preview
updateSlotPreview();

// ── Sidebar: rozvrh dne ─────────────────────────────────────────────────────
(function () {
    const sportSel  = document.querySelector('[name=sportoviste_id]');
    const datumInp  = document.querySelector('[name=datum]');
    const casOdInp  = document.getElementById('casOd');
    const casDoInp  = document.getElementById('casDo');
    const content   = document.getElementById('rozvrhContent');
    const labelEl   = document.getElementById('rozvrhLabel');
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
</script>
</body>
</html>
