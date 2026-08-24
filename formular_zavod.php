<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('formular_zavod')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$currentTrener = (int)$_SESSION['trener_id'];

// Načtení skupin a trenérů
try {
    $skupiny     = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi")->fetchAll(PDO::FETCH_ASSOC);
    $trenereList = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('formular_zavod.php init queries: ' . $e->getMessage());
    $skupiny     = $skupiny     ?? [];
    $trenereList = $trenereList ?? [];
}

// Cviky pro posilovnu
try {
    $cviky = $pdo->query("SELECT id, nazev FROM cviky ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $cviky = [];
}

// Segmenty
try {
    $segmenty = $pdo->query("SELECT id, nazev, kategorie FROM segmenty WHERE aktivni = 1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $segmenty = [];
}

$kategorieMeta = [
    'silnice' => ['label' => 'Silnice', 'color' => 'success',  'icon' => 'bi-bicycle'],
    'draha'   => ['label' => 'Dráha',   'color' => 'primary',  'icon' => 'bi-stopwatch'],
    'mtb'     => ['label' => 'MTB',     'color' => 'warning',  'icon' => 'bi-tree'],
];

$dnesDate = date('Y-m-d');

// Flash zprávy
$flashError   = $_SESSION['flash_error']   ?? null; unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nový závod</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
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
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container py-4">

    <!-- Hero banner -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="fw-semibold fs-5 mb-0">
                    <i class="bi bi-trophy me-2 opacity-75"></i>Nový závod
                </h1>
                <div class="opacity-75 small">Vyplňte níže a uložte nový závod do systému</div>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i>Rozcestník
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

    <form id="zavodForm" action="ulozit_zavod.php" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
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
                                   value="<?= h($dnesDate) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="kategorie" class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>Kategorie
                            </label>
                            <select name="kategorie" id="kategorie" class="form-select">
                                <?php foreach ($kategorieMeta as $val => $meta): ?>
                                <option value="<?= h($val) ?>"><?= h($meta['label']) ?></option>
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
                                  placeholder="Název závodu, místo konání, kategorie…" required></textarea>
                    </div>
                    <div class="mt-3">
                        <label for="poznamka" class="form-label fw-semibold">
                            <i class="bi bi-chat-left-text me-1 text-secondary"></i>Interní poznámka
                            <span class="badge bg-secondary fw-normal ms-1">neveřejné</span>
                        </label>
                        <textarea name="poznamka" id="poznamka" rows="2" class="form-control"
                                  placeholder="Poznámka pro trenéry"></textarea>
                        <div class="mini-muted mt-1">
                            <i class="bi bi-eye-slash me-1"></i>Nezobrazuje se na kartě sportovce
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="url_vysledky" class="form-label fw-semibold">
                            <i class="bi bi-link-45deg me-1 text-primary"></i>URL výsledků
                        </label>
                        <input type="url" name="url_vysledky" id="url_vysledky" class="form-control"
                               placeholder="https://…">
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
                                <option value="<?= (int)$s['id'] ?>"><?= h($s['nazev']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="podskupinaSelect" class="form-label fw-semibold">
                                <i class="bi bi-diagram-2 me-1 text-success"></i>Podskupiny
                            </label>
                            <select name="podskupiny[]" id="podskupinaSelect"
                                    class="form-select" multiple size="5" disabled>
                                <option value="">Vyberte nejprve skupinu</option>
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
                                <span class="text-muted small">Vzdálenost vždy doplňte jednotkou m nebo km.</span>
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
                    <input type="hidden" name="ucastnici" id="ucastnici" value="">
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-image me-1"></i>Fotografie
                        </label>
                        <input type="file" name="fotky[]" class="form-control"
                               accept="image/*" multiple>
                        <div class="mini-muted mt-1">
                            <i class="bi bi-info-circle me-1"></i>Formáty: JPG, PNG, WEBP
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Výsledky
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
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-floppy me-2"></i>Uložit závod
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
// ── Podskupiny podle skupiny ─────────────────────────────────────────────
document.getElementById('skupina_id').addEventListener('change', function () {
    const sel = document.getElementById('podskupinaSelect');
    if (!this.value) {
        sel.innerHTML = '<option value="">Vyberte nejprve skupinu</option>';
        sel.disabled = true;
        return;
    }
    sel.innerHTML = '<option>Načítám…</option>';
    sel.disabled = true;

    fetch('nacti_podskupiny.php?skupina_id=' + encodeURIComponent(this.value))
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
                    sel.appendChild(opt);
                });
                sel.disabled = false;
            } else {
                sel.innerHTML = '<option>Žádné podskupiny</option>';
            }
        })
        .catch(err => {
            sel.innerHTML = '<option>Chyba načítání</option>';
            console.error('Podskupiny error:', err);
        });
});

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
})();

// ── MĚŘENÍ: dynamické řádky + sportovec autocomplete ─────────────────────
(() => {
    const rowsWrap   = document.getElementById('mereniRows');
    const btnAdd     = document.getElementById('btnAddMereni');
    const hiddenJson = document.getElementById('mereni_json');
    const form       = document.getElementById('zavodForm');

    const CVIKY    = <?= json_encode($cviky, JSON_UNESCAPED_UNICODE) ?>;
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

        // Vlastní validace měření — sportovec musí být vybrán
        Array.from(rowsWrap.querySelectorAll('.mereni-row')).forEach(row => {
            const typ = row.querySelector('.js-typ').value;
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

    addRow(); // 1 prázdný řádek
})();
</script>

<script>
// ── Upozornění na neuložené změny ────────────────────────────────────────
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
