<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/csrf_helper.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

$userId  = (int)$_SESSION['trener_id'];
$isAdmin = canAccess('moje_treninky');

// Skupiny pro filtr
try {
    $skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('moje_treninky.php skupiny: ' . $e->getMessage());
    $skupiny = [];
}

// Výchozí filtry (aktuální měsíc)
$rokDefault   = (int)date('Y');
$mesicDefault = (int)date('n');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Flash zprávy
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moje tréninky</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .thumbnail { height: 70px; margin: 2px; object-fit: cover; border-radius: .375rem; }
        .muted-label { color:#6c757d; font-size: .9rem; }
        .kv { margin-bottom: .25rem; }
        #listWrap { min-height: 80px; }
        .spinner-overlay { display:none; text-align:center; padding: 2rem; }
    </style>
    <script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Moje tréninky</h1>
        <a href="formular.php" class="btn btn-primary">+ Nový trénink</a>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Chyba:</strong> <?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= h($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtry -->
    <div class="card shadow-sm mb-4 p-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Rok</label>
                <select id="filterRok" class="form-select form-select-sm">
                    <option value="">Vše</option>
                    <?php for ($y = $rokDefault; $y >= $rokDefault - 4; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $rokDefault ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Měsíc</label>
                <select id="filterMesic" class="form-select form-select-sm">
                    <option value="">Vše</option>
                    <?php
                    $mesiceNames = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                                    'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
                    for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $mesicDefault ? 'selected' : '' ?>><?= $mesiceNames[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto" style="min-width:180px;">
                <label class="form-label small mb-1">Skupina</label>
                <select id="filterSkupina" class="form-select form-select-sm">
                    <option value="">Všechny skupiny</option>
                    <?php foreach ($skupiny as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= h($s['nazev']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col" style="min-width:180px;">
                <label class="form-label small mb-1"><i class="bi bi-search me-1"></i>Hledat</label>
                <input type="text" id="filterSearch" class="form-control form-control-sm"
                       placeholder="v náplni, poznámce…">
            </div>
            <div class="col-auto">
                <button type="button" id="btnClearFilter" class="btn btn-sm btn-outline-secondary">Zrušit filtr</button>
            </div>
            <div class="col-auto ms-auto text-muted small" id="countLabel"></div>
        </div>
    </div>

    <!-- Spinner -->
    <div class="spinner-overlay" id="spinner">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Načítám…</span></div>
    </div>

    <!-- Seznam tréninků (dynamicky plněný) -->
    <div id="listWrap"></div>
</div>


<script>
(() => {
    const filterRok     = document.getElementById('filterRok');
    const filterMesic   = document.getElementById('filterMesic');
    const filterSkupina = document.getElementById('filterSkupina');
    const filterSearch  = document.getElementById('filterSearch');
    const listWrap      = document.getElementById('listWrap');
    const spinner       = document.getElementById('spinner');
    const countLabel    = document.getElementById('countLabel');
    const btnClear      = document.getElementById('btnClearFilter');

    let controller = null; // pro abort předchozího requestu

    function load() {
        if (controller) controller.abort();
        controller = new AbortController();

        const params = new URLSearchParams({
            rok:        filterRok.value,
            mesic:      filterMesic.value,
            skupina_id: filterSkupina.value,
            q:          filterSearch.value.trim(),
        });

        spinner.style.display = 'block';
        listWrap.style.opacity = '0.4';

        fetch('ajax_treninky.php?' + params.toString(), { signal: controller.signal })
            .then(r => r.text())
            .then(html => {
                listWrap.innerHTML = html;
                listWrap.style.opacity = '1';
                spinner.style.display = 'none';

                // Reinicializace Bootstrap accordion po vložení HTML
                listWrap.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
                    new bootstrap.Collapse(document.querySelector(btn.dataset.bsTarget), { toggle: false });
                });

                // Počet výsledků
                const count = listWrap.querySelectorAll('.accordion-item').length;
                countLabel.textContent = count > 0 ? count + ' tréninků' : '';

                // Inline editace poznámek
                bindPoznamkaHandlers();
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    listWrap.innerHTML = '<div class="alert alert-danger">Chyba při načítání dat.</div>';
                    listWrap.style.opacity = '1';
                    spinner.style.display = 'none';
                }
            });
    }

    // Debounce – nespouštěj příliš rychle za sebou
    let debounceTimer = null;
    function loadDebounced() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(load, 200);
    }

    filterRok.addEventListener('change', loadDebounced);
    filterMesic.addEventListener('change', loadDebounced);
    filterSkupina.addEventListener('change', loadDebounced);
    filterSearch.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(load, 400);
    });

    btnClear.addEventListener('click', () => {
        filterRok.value     = '';
        filterMesic.value   = '';
        filterSkupina.value = '';
        filterSearch.value  = '';
        load();
    });

    // -------------------------------------------
    // Inline editace poznámky
    // -------------------------------------------
    function bindPoznamkaHandlers() {
        listWrap.querySelectorAll('.js-edit-poznamka').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const status = document.getElementById('poznamka-status-' + id);
                status.textContent = '';
                document.getElementById('poznamka-text-' + id).classList.add('d-none');
                document.getElementById('poznamka-form-'  + id).classList.remove('d-none');
            });
        });

        listWrap.querySelectorAll('.js-cancel-poznamka').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                document.getElementById('poznamka-text-' + id).classList.remove('d-none');
                document.getElementById('poznamka-form-'  + id).classList.add('d-none');
                document.getElementById('poznamka-form-' + id).querySelector('.js-poznamka-msg').textContent = '';
                const status = document.getElementById('poznamka-status-' + id);
                status.textContent = 'Změny poznámky nebyly uloženy.';
                status.className = 'small mt-1 text-muted';
            });
        });

        listWrap.querySelectorAll('.js-save-poznamka').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id       = btn.dataset.id;
                const textarea = document.getElementById('poznamka-ta-' + id);
                const msgEl    = document.getElementById('poznamka-form-' + id).querySelector('.js-poznamka-msg');

                btn.disabled = true;
                msgEl.textContent = 'Ukládám…';
                msgEl.className = 'js-poznamka-msg mt-1 small text-muted';

                try {
                    const fd = new FormData();
                    fd.append('trenink_id', id);
                    fd.append('poznamka', textarea.value);
                    fd.append('csrf_token', CSRF_TOKEN);

                    const resp = await fetch('ajax_update_poznamka.php', { method: 'POST', body: fd });
                    const data = await resp.json();

                    if (data.ok) {
                        // Aktualizuj zobrazený text (bezpečně – bez innerHTML s user daty)
                        const textEl = document.getElementById('poznamka-text-' + id);
                        textEl.textContent = '';
                        if (data.poznamka) {
                            const lines = data.poznamka.split('\n');
                            lines.forEach((line, i) => {
                                textEl.appendChild(document.createTextNode(line));
                                if (i < lines.length - 1) textEl.appendChild(document.createElement('br'));
                            });
                        } else {
                            const muted = document.createElement('span');
                            muted.className = 'text-muted';
                            muted.textContent = '—';
                            textEl.appendChild(muted);
                        }
                        textEl.classList.remove('d-none');
                        document.getElementById('poznamka-form-' + id).classList.add('d-none');
                        const status = document.getElementById('poznamka-status-' + id);
                        status.textContent = 'Poznámka byla uložena.';
                        status.className = 'small mt-1 text-success';
                    } else {
                        msgEl.textContent = data.message;
                        msgEl.className = 'js-poznamka-msg mt-1 small text-danger';
                    }
                } catch (e) {
                    msgEl.textContent = 'Chyba sítě.';
                    msgEl.className = 'js-poznamka-msg mt-1 small text-danger';
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    // Úvodní načtení
    load();
})();
</script>
</body>
</html>
