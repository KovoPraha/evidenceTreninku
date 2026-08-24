<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (!canAccess('oznameni')) {
    http_response_code(403);
    exit('Pristup odepren.');
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function cleanHtml($html) {
    // Povolené minimum: odstavce, zalomení, tučné, kurzíva, seznamy, odkazy
    $allowed = '<p><br><b><strong><i><em><ul><ol><li><a>';
    $html = (string)$html;
    $html = strip_tags($html, $allowed);
    // jednoduché bezpečné ořezání javascript: v href
    $html = preg_replace('~href\s*=\s*([\'"])\s*javascript:.*?\1~i', 'href="#"', $html);
    return $html;
}

function safeOznameniHtml($html): string {
    $html = strip_tags((string)$html, '<p><br><b><strong><i><em><ul><ol><li><a>');
    return preg_replace_callback('~<\s*(/)?\s*([a-z0-9]+)([^>]*)>~i', function ($m) {
        $closing = $m[1] === '/';
        $tag = strtolower($m[2]);
        $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'ul', 'ol', 'li', 'a'];
        if (!in_array($tag, $allowed, true)) {
            return '';
        }
        if ($closing) {
            return $tag === 'br' ? '' : "</{$tag}>";
        }
        if ($tag !== 'a') {
            return "<{$tag}>";
        }
        $attrs = $m[3] ?? '';
        if (!preg_match('~href\s*=\s*(["\'])(.*?)\1~is', $attrs, $hm)) {
            return '<a>';
        }
        $href = trim(html_entity_decode($hm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $compact = preg_replace('/[\x00-\x20]+/', '', $href);
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $compact, $sm)
            && !in_array(strtolower($sm[1]), ['http', 'https', 'mailto'], true)) {
            return '<a>';
        }
        return '<a href="' . h($href) . '" rel="noopener noreferrer">';
    }, $html);
}

$errors = [];
$success = false;

// -------------------------------
// Data pro selecty (skupiny)
// -------------------------------
$skupiny = $pdo->query("
    SELECT id, nazev
    FROM skupiny
    ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------
// Editace – načti oznámení
// -------------------------------
$editId = (isset($_GET['edit']) && ctype_digit($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$editRow = null;
$editTargets = [
    'skupiny' => [],
    'podskupiny' => [],
    'sportovci' => [],
];

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM oznameni WHERE id = ? LIMIT 1");
    $st->execute([$editId]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$editRow) {
        $errors[] = 'Editované oznámení nebylo nalezeno.';
        $editId = 0;
    } else {
        $stT = $pdo->prepare("SELECT target_type, target_id FROM oznameni_targets WHERE oznameni_id = ?");
        $stT->execute([$editId]);
        while ($r = $stT->fetch(PDO::FETCH_ASSOC)) {
            $type = $r['target_type'];
            $tid  = (int)$r['target_id'];
            if ($type === 'skupina') $editTargets['skupiny'][] = $tid;
            if ($type === 'podskupina') $editTargets['podskupiny'][] = $tid;
            if ($type === 'sportovec') $editTargets['sportovci'][] = $tid;
        }
    }
}

// -------------------------------
// POST: delete / save
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $errors[] = 'Neplatny CSRF token.';
        $_POST = [];
    }

    // Delete
    if (isset($_POST['delete_id']) && ctype_digit($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM oznameni WHERE id = ? LIMIT 1");
            $stmt->execute([$deleteId]);
            header('Location: oznameni.php?ok=1');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Chyba při mazání: ' . $e->getMessage();
        }
    }

    // Save (add/update)
    $id    = (int)($_POST['id'] ?? 0);
    $nazev = trim($_POST['nazev'] ?? '');
    $datum = trim($_POST['datum'] ?? date('Y-m-d'));
    $obsah = (string)($_POST['obsah_html'] ?? '');

    // targets
    $t_skupiny    = $_POST['target_skupiny'] ?? [];
    $t_podskupiny = $_POST['target_podskupiny'] ?? [];
    $t_sportovci  = $_POST['target_sportovci'] ?? [];

    // normalize arrays to ints
    $t_skupiny = array_values(array_unique(array_filter(array_map('intval', (array)$t_skupiny))));
    $t_podskupiny = array_values(array_unique(array_filter(array_map('intval', (array)$t_podskupiny))));
    $t_sportovci = array_values(array_unique(array_filter(array_map('intval', (array)$t_sportovci))));

    // Validace
    if ($datum === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        $errors[] = 'Datum musí být ve formátu YYYY-MM-DD.';
    }

    $obsahClean = safeOznameniHtml($obsah);
    if (trim(strip_tags($obsahClean)) === '') {
        $errors[] = 'Text oznámení nesmí být prázdný.';
    }

    if (empty($t_skupiny) && empty($t_podskupiny) && empty($t_sportovci)) {
        $errors[] = 'Vyber cílení alespoň na jednu skupinu / podskupinu / sportovce.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $st = $pdo->prepare("
                    UPDATE oznameni
                    SET nazev = ?, obsah_html = ?, datum = ?, vlozil_trener_id = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                $st->execute([
                    ($nazev === '' ? null : $nazev),
                    $obsahClean,
                    $datum,
                    (int)$_SESSION['trener_id'],
                    $id
                ]);

                // přepiš cílení
                $pdo->prepare("DELETE FROM oznameni_targets WHERE oznameni_id = ?")->execute([$id]);
                $ozId = $id;
            } else {
                $st = $pdo->prepare("
                    INSERT INTO oznameni (nazev, obsah_html, datum, vlozil_trener_id)
                    VALUES (?, ?, ?, ?)
                ");
                $st->execute([
                    ($nazev === '' ? null : $nazev),
                    $obsahClean,
                    $datum,
                    (int)$_SESSION['trener_id'],
                ]);
                $ozId = (int)$pdo->lastInsertId();
            }

            $insT = $pdo->prepare("
                INSERT INTO oznameni_targets (oznameni_id, target_type, target_id)
                VALUES (?, ?, ?)
            ");

            foreach ($t_skupiny as $gid)    $insT->execute([$ozId, 'skupina', (int)$gid]);
            foreach ($t_podskupiny as $pid) $insT->execute([$ozId, 'podskupina', (int)$pid]);
            foreach ($t_sportovci as $sid)  $insT->execute([$ozId, 'sportovec', (int)$sid]);

            $pdo->commit();

            header('Location: oznameni.php?ok=1');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['ok']) && $_GET['ok'] === '1') $success = true;

// -------------------------------
// Výpis oznámení (poslední nahoře)
// + načti cílení pro štítky
// -------------------------------
$oznameni = $pdo->query("
    SELECT o.*, tr.jmeno AS trener_jmeno
    FROM oznameni o
    LEFT JOIN treneri tr ON tr.id = o.vlozil_trener_id
    ORDER BY o.datum DESC, o.created_at DESC, o.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$targetsByOznameni = [];
if (!empty($oznameni)) {
    $ids = array_map(fn($r) => (int)$r['id'], $oznameni);
    $in = implode(',', array_fill(0, count($ids), '?'));

    $stT = $pdo->prepare("
        SELECT ot.oznameni_id, ot.target_type, ot.target_id
        FROM oznameni_targets ot
        WHERE ot.oznameni_id IN ($in)
        ORDER BY ot.target_type, ot.target_id
    ");
    $stT->execute($ids);
    while ($r = $stT->fetch(PDO::FETCH_ASSOC)) {
        $oid = (int)$r['oznameni_id'];
        if (!isset($targetsByOznameni[$oid])) $targetsByOznameni[$oid] = ['skupina'=>[], 'podskupina'=>[], 'sportovec'=>[]];
        $targetsByOznameni[$oid][$r['target_type']][] = (int)$r['target_id'];
    }
}

// -------------------------------
// Pro edit: předvyplnění formuláře
// -------------------------------
$form = [
    'id' => $editRow['id'] ?? ($_POST['id'] ?? 0),
    'nazev' => $editRow['nazev'] ?? ($_POST['nazev'] ?? ''),
    'datum' => $editRow['datum'] ?? ($_POST['datum'] ?? date('Y-m-d')),
    'obsah_html' => $editRow['obsah_html'] ?? ($_POST['obsah_html'] ?? ''),
    'target_skupiny' => $editTargets['skupiny'] ?? ($_POST['target_skupiny'] ?? []),
    'target_podskupiny' => $editTargets['podskupiny'] ?? ($_POST['target_podskupiny'] ?? []),
    'target_sportovci' => $editTargets['sportovci'] ?? ($_POST['target_sportovci'] ?? []),
];

// Načti názvy sportovců pro edit (aby se zobrazily “chips”)
$sportovciSelected = [];
if (!empty($form['target_sportovci'])) {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$form['target_sportovci']))));
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, jmeno, prijmeni FROM sportovci WHERE id IN ($in) ORDER BY prijmeni, jmeno");
        $st->execute($ids);
        $sportovciSelected = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Oznámení</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .small-muted { font-size: .85rem; color: #666; }
        .chip {
            display: inline-flex; align-items: center; gap: .35rem;
            border: 1px solid #ddd; background: #fff;
            border-radius: 999px; padding: .2rem .55rem; margin: .15rem .15rem 0 0;
            font-size: .9rem;
        }
        .chip button { border: 0; background: transparent; color: #a00; font-weight: 700; line-height: 1; }
        .typeahead-box { position: relative; }
        .typeahead-list {
            position: absolute; z-index: 50; left: 0; right: 0;
            background: #fff; border: 1px solid #ddd; border-top: 0;
            max-height: 240px; overflow: auto;
        }
        .typeahead-item { padding: .5rem .75rem; cursor: pointer; }
        .typeahead-item:hover { background: #f6f6f6; }
        .wysiwyg-note { font-size: .85rem; color: #666; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div>
            <h1 class="mb-0">Oznámení</h1>
            <div class="text-muted small">Zadávání oznámení s cílením na skupiny / podskupiny / sportovce</div>
        </div>
        <?php if ($editId > 0): ?>
            <a class="btn btn-outline-secondary" href="oznameni.php">Zrušit editaci</a>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mt-3">Uloženo.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <strong>Chyba:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="card shadow-sm mt-3" id="form">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= ($editId > 0) ? '✏️ Upravit oznámení' : '➕ Nové oznámení' ?></h5>

            <form method="POST" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="col-md-8">
                    <label class="form-label">Název (volitelný)</label>
                    <input type="text" name="nazev" class="form-control" value="<?= h($form['nazev']) ?>" placeholder="např. Změna času tréninku, Info k víkendu...">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Datum</label>
                    <input type="date" name="datum" class="form-control" value="<?= h($form['datum']) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Text oznámení</label>
                    <textarea name="obsah_html" id="obsah_html" class="form-control" rows="8"><?= h($form['obsah_html']) ?></textarea>
                    <div class="wysiwyg-note mt-1">Editor: tučně, odkaz, základní odstavce/seznamy.</div>
                </div>

                <div class="col-12">
                    <div class="border rounded p-3 bg-white">
                        <div class="fw-semibold mb-2">Cílení oznámení</div>
                        <div class="row g-3">

                            <!-- Skupiny -->
                            <div class="col-md-4">
                                <label class="form-label">Skupiny (lze více)</label>
                                <select name="target_skupiny[]" id="target_skupiny" class="form-select" multiple size="6">
                                    <?php foreach ($skupiny as $sk): ?>
                                        <?php $sel = in_array((int)$sk['id'], (array)$form['target_skupiny'], true); ?>
                                        <option value="<?= (int)$sk['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= h($sk['nazev']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">CTRL/Shift pro vícenásobný výběr.</div>
                            </div>

                            <!-- Podskupiny -->
                            <div class="col-md-4">
                                <label class="form-label">Podskupiny (po výběru skupiny)</label>
                                <div class="mb-2">
                                    <select id="podskupiny_skupina_filter" class="form-select">
                                        <option value="">— vyber skupinu pro načtení podskupin —</option>
                                        <?php foreach ($skupiny as $sk): ?>
                                            <option value="<?= (int)$sk['id'] ?>"><?= h($sk['nazev']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <select name="target_podskupiny[]" id="target_podskupiny" class="form-select" multiple size="6">
                                    <?php
                                    // pokud editace poslala podskupiny, necháme je zatím bez názvů - JS si je umí dočíst po načtení skupiny,
                                    // ale pro pohodlí načteme rovnou názvy:
                                    $psSelected = array_values(array_unique(array_filter(array_map('intval', (array)$form['target_podskupiny']))));
                                    if (!empty($psSelected)) {
                                        $in = implode(',', array_fill(0, count($psSelected), '?'));
                                        $st = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE id IN ($in) ORDER BY nazev");
                                        $st->execute($psSelected);
                                        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($rows as $r) {
                                            echo '<option value="'.(int)$r['id'].'" selected>'.h($r['nazev']).'</option>';
                                        }
                                    }
                                    ?>
                                </select>

                                <div class="form-text">Vyber skupinu výše a podskupiny se načtou bez reloadu.</div>
                            </div>

                            <!-- Sportovci -->
                            <div class="col-md-4">
                                <label class="form-label">Sportovci (našeptávač, lze více)</label>

                                <div class="typeahead-box">
                                    <input type="text" id="sportovec_search" class="form-control" placeholder="Začni psát jméno...">
                                    <div id="sportovec_list" class="typeahead-list d-none"></div>
                                </div>

                                <div class="mt-2" id="sportovec_chips">
                                    <?php foreach ($sportovciSelected as $sp): ?>
                                        <span class="chip" data-id="<?= (int)$sp['id'] ?>">
                                            <?= h(trim(($sp['prijmeni'] ?? '').' '.($sp['jmeno'] ?? ''))) ?>
                                            <button type="button" class="chip-remove" title="odebrat">×</button>
                                            <input type="hidden" name="target_sportovci[]" value="<?= (int)$sp['id'] ?>">
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="form-text">Klikni na návrh → přidá se do cílení.</div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><?= ($editId > 0) ? 'Uložit změny' : 'Uložit oznámení' ?></button>

                    <?php if ($editId > 0): ?>
                        <button type="submit"
                                name="delete_id"
                                value="<?= (int)$editId ?>"
                                class="btn btn-danger"
                                data-confirm="Opravdu smazat toto oznámení?">
                            Smazat
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- VÝPIS -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">📣 Oznámení – přehled</h5>

            <?php if (empty($oznameni)): ?>
                <div class="alert alert-info mb-0">Zatím nejsou žádná oznámení.</div>
            <?php else: ?>
                <div class="accordion" id="accOznameni">
                    <?php foreach ($oznameni as $i => $o):
                        $oid = (int)$o['id'];
                        $collapseId = 'oz_' . $oid;
                        $headingId  = 'oz_h_' . $oid;
                        $open = ($i === 0);

                        $targets = $targetsByOznameni[$oid] ?? ['skupina'=>[], 'podskupina'=>[], 'sportovec'=>[]];
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?= h($headingId) ?>">
                                <button class="accordion-button <?= $open ? '' : 'collapsed' ?>"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?= h($collapseId) ?>"
                                        aria-expanded="<?= $open ? 'true' : 'false' ?>"
                                        aria-controls="<?= h($collapseId) ?>">
                                    <span class="me-2"><?= h($o['datum']) ?></span>
                                    <span class="fw-semibold"><?= h($o['nazev'] ?: ('Oznámení #' . $oid)) ?></span>
                                </button>
                            </h2>

                            <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse <?= $open ? 'show' : '' ?>"
                                 aria-labelledby="<?= h($headingId) ?>" data-bs-parent="#accOznameni">
                                <div class="accordion-body">

                                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                        <div class="small-muted">
                                            vložil: <?= h($o['trener_jmeno'] ?: ('ID ' . (int)$o['vlozil_trener_id'])) ?>
                                            · vytvořeno: <?= h($o['created_at']) ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="oznameni.php?edit=<?= $oid ?>#form">Upravit</a>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <?= safeOznameniHtml($o['obsah_html']) ?>
                                    </div>

                                    <div class="small text-muted">
                                        <strong>Cílení:</strong>
                                        <?php
                                        $parts = [];
                                        if (!empty($targets['skupina'])) $parts[] = 'skupiny: ' . count($targets['skupina']);
                                        if (!empty($targets['podskupina'])) $parts[] = 'podskupiny: ' . count($targets['podskupina']);
                                        if (!empty($targets['sportovec'])) $parts[] = 'sportovci: ' . count($targets['sportovec']);
                                        echo h($parts ? implode(' · ', $parts) : '—');
                                        ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<!-- TinyMCE (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" integrity="sha384-1Miaw0hyo/w0cd9ZHUnc7Z8ACgtO+lphAEziGNW4z2C1h3nJfMVVEWA5MI031P+X" crossorigin="anonymous"></script>
<script>
tinymce.init({
    selector: '#obsah_html',
    menubar: false,
    branding: false,
    plugins: 'link lists',
    toolbar: 'bold | bullist numlist | link | removeformat',
    default_link_target: '_blank',
    link_assume_external_targets: true,
    height: 280
});
</script>

<script>
/* =========================================
   AJAX podskupiny podle skupiny
========================================= */
const selGroup = document.getElementById('podskupiny_skupina_filter');
const selSub   = document.getElementById('target_podskupiny');

async function loadSubgroups(groupId) {
    if (!groupId) return;
    const res = await fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(groupId), { credentials: 'same-origin' });
    const data = await res.json();
    if (!data || !data.ok) return;

    // zachovej již vybrané ID
    const selected = new Set(Array.from(selSub.selectedOptions).map(o => o.value));

    // doplň seznam (necháme i již existující selected položky, ať se “neztratí”)
    selSub.innerHTML = '';
    for (const item of data.items) {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.nazev;
        if (selected.has(String(item.id))) opt.selected = true;
        selSub.appendChild(opt);
    }
}

if (selGroup && selSub) {
    selGroup.addEventListener('change', () => loadSubgroups(selGroup.value));
}

/* =========================================
   Našeptávač sportovců (ajax_sportovci.php?q=...)
========================================= */
const input = document.getElementById('sportovec_search');
const list  = document.getElementById('sportovec_list');
const chips = document.getElementById('sportovec_chips');

function selectedIds() {
    return new Set(Array.from(chips.querySelectorAll('span.chip')).map(c => c.getAttribute('data-id')));
}

function addChip(id, label) {
    const ids = selectedIds();
    if (ids.has(String(id))) return;

    const span = document.createElement('span');
    span.className = 'chip';
    span.setAttribute('data-id', id);

    span.innerHTML = `
        ${label}
        <button type="button" class="chip-remove" title="odebrat">×</button>
        <input type="hidden" name="target_sportovci[]" value="${id}">
    `;
    chips.appendChild(span);
}

function clearList() {
    list.classList.add('d-none');
    list.innerHTML = '';
}

chips.addEventListener('click', (e) => {
    const btn = e.target.closest('.chip-remove');
    if (!btn) return;
    const chip = btn.closest('.chip');
    if (chip) chip.remove();
});

let tmr = null;
input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(tmr);

    if (q.length < 2) {
        clearList();
        return;
    }

    tmr = setTimeout(async () => {
        try {
            const res = await fetch('ajax_sportovci.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
            const data = await res.json();

            // očekáváme buď pole, nebo objekt {items:[...]}
            const items = Array.isArray(data) ? data : (data.items || []);
            const ids = selectedIds();

            list.innerHTML = '';
            for (const it of items) {
                const id = it.id ?? it.ID ?? it.sportovec_id;
                if (!id) continue;

                // label: “Příjmení Jméno” nebo “Jméno”
                const label =
                    (it.prijmeni ? (it.prijmeni + ' ') : '') +
                    (it.jmeno ? it.jmeno : (it.nazev || ('ID ' + id)));

                if (ids.has(String(id))) continue;

                const div = document.createElement('div');
                div.className = 'typeahead-item';
                div.textContent = label;
                div.addEventListener('click', () => {
                    addChip(id, label);
                    input.value = '';
                    clearList();
                    input.focus();
                });
                list.appendChild(div);
            }

            if (list.children.length > 0) list.classList.remove('d-none');
            else clearList();

        } catch (e) {
            clearList();
        }
    }, 200);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.typeahead-box')) clearList();
});
</script>


</body>
</html>
