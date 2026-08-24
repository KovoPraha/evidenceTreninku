<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// -------------------------------------------------
// AJAX: načtení podskupin pro vybranou skupinu (bez reloadu)
// volání: google_sheets_linky.php?ajax=podskupiny&skupina_id=123
// -------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'podskupiny') {
    header('Content-Type: application/json; charset=utf-8');

    $gid = $_GET['skupina_id'] ?? '';
    if ($gid === '' || !ctype_digit($gid)) {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }

    $st = $pdo->prepare("
        SELECT id, nazev
        FROM podskupiny
        WHERE skupina_id = ?
        ORDER BY poradi, nazev
    ");
    $st->execute([(int)$gid]);
    echo json_encode(['ok' => true, 'items' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$errors  = [];
$success = false;

// -------------------------------------------------
// 0) Data pro selecty (kategorie, skupiny)
// -------------------------------------------------
$kategorie = $pdo->query("
    SELECT id, nazev
    FROM gs_kategorie
    ORDER BY nazev
")->fetchAll(PDO::FETCH_ASSOC);

$skupiny = $pdo->query("
    SELECT id, nazev
    FROM skupiny
    ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------
// 1) Editace – načti záznam + cílení
// -------------------------------------------------
$editId  = (isset($_GET['edit']) && ctype_digit($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$editRow = null;

$editTargets = [
    'skupina_id'    => '',
    'podskupina_id' => '',
    'sportovci'     => [], // [id => "Prijmeni Jmeno"]
];

if ($editId > 0) {
    $st = $pdo->prepare("
        SELECT l.*, k.nazev AS kategorie_nazev, tr.jmeno AS trener_jmeno
        FROM gs_linky l
        JOIN gs_kategorie k ON k.id = l.kategorie_id
        LEFT JOIN treneri tr ON tr.id = l.vlozil_trener_id
        WHERE l.id = ?
        LIMIT 1
    ");
    $st->execute([$editId]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC);

    if (!$editRow) {
        $errors[] = 'Editovaný odkaz nebyl nalezen.';
        $editId = 0;
    } else {
        // Targets
        $stT = $pdo->prepare("
            SELECT target_type, target_id
            FROM gs_link_targets
            WHERE link_id = ?
            ORDER BY id ASC
        ");
        $stT->execute([$editId]);
        $rowsT = $stT->fetchAll(PDO::FETCH_ASSOC);

        $sportIds = [];
        foreach ($rowsT as $r) {
            $tt = $r['target_type'];
            $tid = (int)$r['target_id'];
            if ($tt === 'skupina') $editTargets['skupina_id'] = (string)$tid;
            if ($tt === 'podskupina') $editTargets['podskupina_id'] = (string)$tid;
            if ($tt === 'sportovec') $sportIds[] = $tid;
        }

        if (!empty($sportIds)) {
            $in = implode(',', array_fill(0, count($sportIds), '?'));
            $stS = $pdo->prepare("
                SELECT id, jmeno, prijmeni
                FROM sportovci
                WHERE id IN ($in)
                ORDER BY prijmeni, jmeno
            ");
            $stS->execute($sportIds);
            while ($r = $stS->fetch(PDO::FETCH_ASSOC)) {
                $label = trim(($r['prijmeni'] ?? '') . ' ' . ($r['jmeno'] ?? ''));
                $editTargets['sportovci'][(int)$r['id']] = $label !== '' ? $label : ('ID ' . (int)$r['id']);
            }
        }
    }
}

// -------------------------------------------------
// 2) Uložení (POST): add / update / delete
// -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {

    // Mazání (z listu / z editace)
    if (isset($_POST['delete_id']) && ctype_digit($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM gs_link_targets WHERE link_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM gs_linky WHERE id = ? LIMIT 1")->execute([$deleteId]);
            $pdo->commit();

            header('Location: google_sheets_linky.php?ok=1');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('google_sheets_linky mazani: ' . $e->getMessage());
            $errors[] = 'Chyba při mazání odkazu.';
        }
    }

    // Add / Update
    $id          = (int)($_POST['id'] ?? 0);
    $url         = trim($_POST['url'] ?? '');
    $nazev       = trim($_POST['nazev'] ?? '');
    $datum       = trim($_POST['datum'] ?? date('Y-m-d'));
    $popis       = trim($_POST['popis'] ?? '');

    $kategorie_id = $_POST['kategorie_id'] ?? '';
    $nova_kat     = trim($_POST['nova_kategorie'] ?? '');

    // Cílení
    $cileni = $_POST['cileni'] ?? 'treneri'; // treneri|verejny|skupina|podskupina|sportovci
    if (!in_array($cileni, ['treneri','verejny','skupina','podskupina','sportovci'], true)) {
        $cileni = 'treneri';
    }

    $target_skupina_id    = $_POST['target_skupina_id'] ?? '';
    $target_podskupina_id = $_POST['target_podskupina_id'] ?? '';
    $target_sportovci_ids = trim($_POST['target_sportovci_ids'] ?? ''); // "1,2,3"

    // Validace
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'Zadejte platný odkaz (URL).';
    if ($nazev === '') $errors[] = 'Zadejte název.';
    if ($datum === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) $errors[] = 'Datum musí být ve formátu YYYY-MM-DD.';
    if ($kategorie_id === '' && $nova_kat === '') $errors[] = 'Vyberte kategorii nebo zadejte novou.';

    // Validace cílení
    $targetsToInsert = []; // each: [type, id]
    if ($cileni === 'skupina') {
        if ($target_skupina_id === '' || !ctype_digit($target_skupina_id)) $errors[] = 'Vyberte skupinu pro cílení.';
        else $targetsToInsert[] = ['skupina', (int)$target_skupina_id];
    } elseif ($cileni === 'podskupina') {
        if ($target_podskupina_id === '' || !ctype_digit($target_podskupina_id)) $errors[] = 'Vyberte podskupinu pro cílení.';
        else $targetsToInsert[] = ['podskupina', (int)$target_podskupina_id];
    } elseif ($cileni === 'sportovci') {
        if ($target_sportovci_ids === '') {
            $errors[] = 'Vyberte alespoň jednoho sportovce.';
        } else {
            $ids = array_values(array_filter(array_map('trim', explode(',', $target_sportovci_ids)), fn($x) => $x !== ''));
            $ids = array_values(array_unique($ids));
            $clean = [];
            foreach ($ids as $x) if (ctype_digit($x)) $clean[] = (int)$x;
            $clean = array_values(array_unique($clean));
            if (empty($clean)) $errors[] = 'Vybraní sportovci nejsou validní.';
            foreach ($clean as $sid) $targetsToInsert[] = ['sportovec', $sid];
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Kategorie
            if ($nova_kat !== '') {
                try {
                    $ins = $pdo->prepare("INSERT INTO gs_kategorie (nazev) VALUES (?)");
                    $ins->execute([$nova_kat]);
                    $kategorie_id = (int)$pdo->lastInsertId();
                } catch (PDOException $e) {
                    // duplicitní název -> načti id
                    $sel = $pdo->prepare("SELECT id FROM gs_kategorie WHERE nazev = ? LIMIT 1");
                    $sel->execute([$nova_kat]);
                    $kategorie_id = (int)($sel->fetchColumn() ?: 0);
                    if ($kategorie_id <= 0) throw $e;
                }
            } else {
                $kategorie_id = ctype_digit((string)$kategorie_id) ? (int)$kategorie_id : 0;
            }
            if ($kategorie_id <= 0) throw new Exception('Kategorie nebyla správně určena.');

            // viditelnost zatím jen uložit (logiku řeší cílení)
            $viditelnost = ($cileni === 'verejny') ? 'verejny' : 'treneri';

            // Insert / Update gs_linky
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE gs_linky
                    SET kategorie_id = ?, url = ?, nazev = ?, popis = ?, datum = ?, viditelnost = ?,
                        vlozil_trener_id = ?, updated_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([
                    $kategorie_id,
                    $url,
                    $nazev,
                    ($popis === '' ? null : $popis),
                    $datum,
                    $viditelnost,
                    (int)$_SESSION['trener_id'],
                    $id
                ]);
                $linkId = $id;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO gs_linky (kategorie_id, url, nazev, popis, datum, viditelnost, vlozil_trener_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $kategorie_id,
                    $url,
                    $nazev,
                    ($popis === '' ? null : $popis),
                    $datum,
                    $viditelnost,
                    (int)$_SESSION['trener_id']
                ]);
                $linkId = (int)$pdo->lastInsertId();
            }

            // Targets – přepíšeme
            $pdo->prepare("DELETE FROM gs_link_targets WHERE link_id = ?")->execute([$linkId]);

            if (!empty($targetsToInsert)) {
                $insT = $pdo->prepare("
                    INSERT INTO gs_link_targets (link_id, target_type, target_id)
                    VALUES (?, ?, ?)
                ");
                foreach ($targetsToInsert as [$tt, $tid]) {
                    $insT->execute([$linkId, $tt, (int)$tid]);
                }
            }

            $pdo->commit();
            header('Location: google_sheets_linky.php?ok=1');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('google_sheets_linky ulozeni: ' . $e->getMessage());
            $errors[] = 'Chyba při ukládání odkazu.';
        }
    }
    } // konec else (CSRF ověřeno)
}

if (isset($_GET['ok']) && $_GET['ok'] === '1') $success = true;

// -------------------------------------------------
// 3) Výpis – kategorie dle poslední aktivity
// -------------------------------------------------
$catsForAccordion = $pdo->query("
    SELECT k.id, k.nazev, MAX(l.created_at) AS last_created
    FROM gs_kategorie k
    JOIN gs_linky l ON l.kategorie_id = k.id
    GROUP BY k.id, k.nazev
    ORDER BY last_created DESC, k.nazev
")->fetchAll(PDO::FETCH_ASSOC);

$linksByCat = [];
$linkIdsAll = [];

if (!empty($catsForAccordion)) {
    $catIds = array_map(fn($r) => (int)$r['id'], $catsForAccordion);
    $in = implode(',', array_fill(0, count($catIds), '?'));

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            tr.jmeno AS trener_jmeno
        FROM gs_linky l
        LEFT JOIN treneri tr ON tr.id = l.vlozil_trener_id
        WHERE l.kategorie_id IN ($in)
        ORDER BY l.created_at DESC, l.id DESC
    ");
    $stmt->execute($catIds);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int)$r['kategorie_id'];
        if (!isset($linksByCat[$cid])) $linksByCat[$cid] = [];
        $linksByCat[$cid][] = $r;
        $linkIdsAll[] = (int)$r['id'];
    }
}

// Targets pro výpis – načteme hromadně + doplníme názvy
$targetsByLink = []; // link_id => [['type'=>..., 'id'=>..., 'label'=>...], ...]
if (!empty($linkIdsAll)) {
    $in = implode(',', array_fill(0, count($linkIdsAll), '?'));
    $st = $pdo->prepare("
        SELECT link_id, target_type, target_id
        FROM gs_link_targets
        WHERE link_id IN ($in)
        ORDER BY id ASC
    ");
    $st->execute($linkIdsAll);
    $rawT = $st->fetchAll(PDO::FETCH_ASSOC);

    $skIds = [];
    $psIds = [];
    $spIds = [];

    foreach ($rawT as $r) {
        if ($r['target_type'] === 'skupina') $skIds[] = (int)$r['target_id'];
        if ($r['target_type'] === 'podskupina') $psIds[] = (int)$r['target_id'];
        if ($r['target_type'] === 'sportovec') $spIds[] = (int)$r['target_id'];
    }

    $skIds = array_values(array_unique($skIds));
    $psIds = array_values(array_unique($psIds));
    $spIds = array_values(array_unique($spIds));

    $skMap = [];
    if (!empty($skIds)) {
        $in2 = implode(',', array_fill(0, count($skIds), '?'));
        $st2 = $pdo->prepare("SELECT id, nazev FROM skupiny WHERE id IN ($in2)");
        $st2->execute($skIds);
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) $skMap[(int)$r['id']] = $r['nazev'];
    }

    $psMap = [];
    if (!empty($psIds)) {
        $in2 = implode(',', array_fill(0, count($psIds), '?'));
        $st2 = $pdo->prepare("SELECT id, nazev FROM podskupiny WHERE id IN ($in2)");
        $st2->execute($psIds);
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) $psMap[(int)$r['id']] = $r['nazev'];
    }

    $spMap = [];
    if (!empty($spIds)) {
        $in2 = implode(',', array_fill(0, count($spIds), '?'));
        $st2 = $pdo->prepare("SELECT id, jmeno, prijmeni FROM sportovci WHERE id IN ($in2)");
        $st2->execute($spIds);
        while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
            $label = trim(($r['prijmeni'] ?? '') . ' ' . ($r['jmeno'] ?? ''));
            $spMap[(int)$r['id']] = $label !== '' ? $label : ('ID ' . (int)$r['id']);
        }
    }

    foreach ($rawT as $r) {
        $lid = (int)$r['link_id'];
        $tt  = $r['target_type'];
        $tid = (int)$r['target_id'];

        $label = 'ID ' . $tid;
        if ($tt === 'skupina') $label = $skMap[$tid] ?? $label;
        if ($tt === 'podskupina') $label = $psMap[$tid] ?? $label;
        if ($tt === 'sportovec') $label = $spMap[$tid] ?? $label;

        if (!isset($targetsByLink[$lid])) $targetsByLink[$lid] = [];
        $targetsByLink[$lid][] = ['type' => $tt, 'id' => $tid, 'label' => $label];
    }
}

// -------------------------------------------------
// 4) Předvyplnění formuláře (edit má přednost, pak POST)
// -------------------------------------------------
$postedSportIds = trim($_POST['target_sportovci_ids'] ?? '');
$formSportLabels = [];
if ($postedSportIds !== '') {
    $ids = array_values(array_unique(array_filter(array_map('trim', explode(',', $postedSportIds)), fn($x) => ctype_digit($x))));
    $ids = array_map('intval', $ids);
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, jmeno, prijmeni FROM sportovci WHERE id IN ($in)");
        $st->execute($ids);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $label = trim(($r['prijmeni'] ?? '') . ' ' . ($r['jmeno'] ?? ''));
            $formSportLabels[(int)$r['id']] = $label !== '' ? $label : ('ID ' . (int)$r['id']);
        }
    }
}

$defaultCileni = 'treneri';
if ($editRow) {
    if (!empty($editTargets['sportovci'])) $defaultCileni = 'sportovci';
    elseif ($editTargets['podskupina_id'] !== '') $defaultCileni = 'podskupina';
    elseif ($editTargets['skupina_id'] !== '') $defaultCileni = 'skupina';
    else $defaultCileni = ($editRow['viditelnost'] === 'verejny') ? 'verejny' : 'treneri';
}

$form = [
    'id'            => $editRow['id'] ?? ($_POST['id'] ?? 0),
    'url'           => $editRow['url'] ?? ($_POST['url'] ?? ''),
    'nazev'         => $editRow['nazev'] ?? ($_POST['nazev'] ?? ''),
    'datum'         => $editRow['datum'] ?? ($_POST['datum'] ?? date('Y-m-d')),
    'popis'         => $editRow['popis'] ?? ($_POST['popis'] ?? ''),
    'kategorie_id'  => $editRow['kategorie_id'] ?? ($_POST['kategorie_id'] ?? ''),
    'nova_kategorie'=> $_POST['nova_kategorie'] ?? '',
    'cileni'        => $_POST['cileni'] ?? $defaultCileni,
    'target_skupina_id'    => $editTargets['skupina_id'] ?? ($_POST['target_skupina_id'] ?? ''),
    'target_podskupina_id' => $editTargets['podskupina_id'] ?? ($_POST['target_podskupina_id'] ?? ''),
    'target_sportovci_ids' => $postedSportIds !== '' ? $postedSportIds : implode(',', array_keys($editTargets['sportovci'] ?? [])),
];

// Podskupiny do selectu (pro první render – pak už AJAX)
$podskupinyForSelect = [];
if ($form['target_skupina_id'] !== '' && ctype_digit((string)$form['target_skupina_id'])) {
    $st = $pdo->prepare("
        SELECT id, nazev
        FROM podskupiny
        WHERE skupina_id = ?
        ORDER BY poradi, nazev
    ");
    $st->execute([(int)$form['target_skupina_id']]);
    $podskupinyForSelect = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Google Sheets odkazy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .small-muted { font-size: .85rem; color: #666; }
        .url-break { word-break: break-all; }
        .btn-xs { padding: .18rem .45rem; font-size: .82rem; }
        .chip { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .5rem; border:1px solid #ddd; border-radius:999px; background:#fff; margin:.15rem; }
        .chip button { border:0; background:transparent; line-height:1; color:#b02a37; font-weight:700; }
        .suggestions { position: relative; }
        .suggestions-list { position:absolute; z-index:1000; left:0; right:0; top:100%; max-height:240px; overflow:auto; background:#fff; border:1px solid #ddd; border-top:0; display:none; }
        .suggestions-item { padding:.5rem .75rem; cursor:pointer; }
        .suggestions-item:hover { background:#f8f9fa; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-3">Google Sheets odkazy</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">Uloženo.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Chyba:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="card mb-4 shadow-sm" id="form">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="card-title mb-1">
                        <?= ($editId > 0) ? '✏️ Upravit odkaz' : '➕ Přidat nový odkaz' ?>
                    </h5>
                    <?php if ($editId > 0): ?>
                        <div class="small text-muted">ID: <?= (int)$editId ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="google_sheets_linky.php">Zrušit editaci</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="row g-3 mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="col-12">
                    <label class="form-label">URL (odkaz)</label>
                    <input type="url" name="url" class="form-control" required placeholder="https://..."
                           value="<?= h($form['url']) ?>">
                </div>

                <div class="col-md-7">
                    <label class="form-label">Název</label>
                    <input type="text" name="nazev" class="form-control" required
                           value="<?= h($form['nazev']) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label">Datum uložení</label>
                    <input type="date" name="datum" class="form-control"
                           value="<?= h($form['datum']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kategorie (vyber)</label>
                    <select name="kategorie_id" class="form-select" id="kategorie_id">
                        <option value="">— nevybráno —</option>
                        <?php foreach ($kategorie as $k): ?>
                            <option value="<?= (int)$k['id'] ?>"
                                <?= ((string)$form['kategorie_id'] === (string)$k['id']) ? 'selected' : '' ?>>
                                <?= h($k['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Buď vyber kategorii, nebo níže zadej novou.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nová kategorie (pokud nevybereš)</label>
                    <input type="text" name="nova_kategorie" class="form-control" id="nova_kategorie"
                           value="<?= h($form['nova_kategorie']) ?>"
                           placeholder="např. Dráha / Testy / Rozpisy...">
                </div>

                <!-- Cílení -->
                <div class="col-12">
                    <label class="form-label">Cílení odkazu</label>
                    <select name="cileni" id="cileni" class="form-select">
                        <option value="treneri"   <?= ($form['cileni'] === 'treneri') ? 'selected' : '' ?>>Jen trenéři</option>
                        <option value="verejny"   <?= ($form['cileni'] === 'verejny') ? 'selected' : '' ?>>Veřejný</option>
                        <option value="skupina"   <?= ($form['cileni'] === 'skupina') ? 'selected' : '' ?>>Konkrétní skupina</option>
                        <option value="podskupina"<?= ($form['cileni'] === 'podskupina') ? 'selected' : '' ?>>Konkrétní podskupina</option>
                        <option value="sportovci" <?= ($form['cileni'] === 'sportovci') ? 'selected' : '' ?>>Konkrétní sportovci</option>
                    </select>
                    <div class="form-text">Viditelnost budeme řešit později – teď jen ukládáme cílení.</div>
                </div>

                <div class="col-md-6" id="wrap_target_skupina">
                    <label class="form-label">Skupina (pro cílení)</label>
                    <select name="target_skupina_id" id="target_skupina_id" class="form-select">
                        <option value="">— vyber skupinu —</option>
                        <?php foreach ($skupiny as $sk): ?>
                            <option value="<?= (int)$sk['id'] ?>" <?= ((string)$form['target_skupina_id'] === (string)$sk['id']) ? 'selected' : '' ?>>
                                <?= h($sk['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6" id="wrap_target_podskupina">
                    <label class="form-label">Podskupina (pro cílení)</label>
                    <select name="target_podskupina_id" id="target_podskupina_id" class="form-select">
                        <option value="">— vyber podskupinu —</option>
                        <?php foreach ($podskupinyForSelect as $ps): ?>
                            <option value="<?= (int)$ps['id'] ?>" <?= ((string)$form['target_podskupina_id'] === (string)$ps['id']) ? 'selected' : '' ?>>
                                <?= h($ps['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Podskupiny se po změně skupiny načítají AJAXem.</div>
                </div>

                <div class="col-12" id="wrap_target_sportovci">
                    <label class="form-label">Sportovci (může být více)</label>

                    <input type="hidden" name="target_sportovci_ids" id="target_sportovci_ids" value="<?= h($form['target_sportovci_ids']) ?>">

                    <div class="suggestions">
                        <input type="text" id="sportovec_search" class="form-control" autocomplete="off"
                               placeholder="Začni psát jméno sportovce…">
                        <div class="suggestions-list" id="sportovec_suggestions"></div>
                    </div>

                    <div class="mt-2" id="sportovec_chips"></div>

                    <div class="form-text">
                        Našeptávač bere data z <code>ajax_sportovci.php?q=...</code>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Vložil (aktuální uživatel)</label>
                    <input type="text" class="form-control" value="<?= h($_SESSION['email'] ?? ('Trenér ID: '.$_SESSION['trener_id'])) ?>" disabled>
                    <div class="form-text">Při editaci se přepíše na aktuálního uživatele (audit).</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Popis (volitelné)</label>
                    <textarea name="popis" class="form-control" rows="3"><?= h($form['popis']) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><?= ($editId > 0) ? 'Uložit změny' : 'Uložit odkaz' ?></button>

                    <?php if ($editId > 0): ?>
                        <button type="submit"
                                name="delete_id"
                                value="<?= (int)$editId ?>"
                                class="btn btn-danger"
                                data-confirm="Opravdu smazat tento odkaz?">
                            Smazat odkaz
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- VÝPIS -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">📚 Uložené odkazy</h5>

            <?php if (empty($catsForAccordion)): ?>
                <div class="alert alert-info mb-0">Zatím nejsou uloženy žádné odkazy.</div>
            <?php else: ?>
                <div class="accordion" id="accLinks">
                    <?php foreach ($catsForAccordion as $i => $c):
                        $cid = (int)$c['id'];
                        $items = $linksByCat[$cid] ?? [];
                        $collapseId = 'cat_' . $cid;
                        $headingId  = 'head_' . $cid;
                        $open = ($i === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?= h($headingId) ?>">
                                <button class="accordion-button <?= $open ? '' : 'collapsed' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>"
                                        aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= h($collapseId) ?>">
                                    <?= h($c['nazev']) ?>
                                    <span class="badge text-bg-light border ms-2"><?= count($items) ?></span>
                                </button>
                            </h2>
                            <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse <?= $open ? 'show' : '' ?>"
                                 aria-labelledby="<?= h($headingId) ?>" data-bs-parent="#accLinks">
                                <div class="accordion-body">
                                    <?php if (empty($items)): ?>
                                        <div class="text-muted">Bez odkazů.</div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($items as $l):
                                                $lid = (int)$l['id'];
                                                $tgs = $targetsByLink[$lid] ?? [];
                                            ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <div>
                                                            <div class="fw-semibold"><?= h($l['nazev']) ?></div>

                                                            <?php if (!empty($l['popis'])): ?>
                                                                <div class="small text-muted mt-1"><?= nl2br(h($l['popis'])) ?></div>
                                                            <?php endif; ?>

                                                            <div class="mt-2 d-flex flex-wrap gap-1">
                                                                <?php if (empty($tgs)): ?>
                                                                    <span class="badge text-bg-secondary">
                                                                        <?= h($l['viditelnost'] === 'verejny' ? 'veřejný' : 'jen trenéři') ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <?php foreach ($tgs as $tg): ?>
                                                                        <?php if ($tg['type'] === 'skupina'): ?>
                                                                            <span class="badge text-bg-primary">skupina: <?= h($tg['label']) ?></span>
                                                                        <?php elseif ($tg['type'] === 'podskupina'): ?>
                                                                            <span class="badge text-bg-success">podskupina: <?= h($tg['label']) ?></span>
                                                                        <?php elseif ($tg['type'] === 'sportovec'): ?>
                                                                            <span class="badge text-bg-warning text-dark">sportovec: <?= h($tg['label']) ?></span>
                                                                        <?php else: ?>
                                                                            <span class="badge text-bg-light text-dark"><?= h($tg['type']) ?>: <?= h($tg['label']) ?></span>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <div class="text-end">
                                                            <div class="small text-muted"><?= h($l['datum']) ?></div>
                                                            <div class="d-flex gap-1 justify-content-end mt-1 flex-wrap">
                                                                <a class="btn btn-xs btn-outline-primary"
                                                                   href="<?= h($l['url']) ?>"
                                                                   target="_blank" rel="noopener">Otevřít</a>

                                                                <a class="btn btn-xs btn-outline-secondary"
                                                                   href="google_sheets_linky.php?edit=<?= (int)$l['id'] ?>#form">
                                                                    Upravit
                                                                </a>

                                                                <form method="POST" class="d-inline">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="delete_id" value="<?= (int)$l['id'] ?>">
                                                                    <button class="btn btn-xs btn-outline-danger"
                                                                            data-confirm="Opravdu smazat tento odkaz?">
                                                                        Smazat
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-2 small-muted">
                                                        <span class="url-break"><?= h($l['url']) ?></span><br>
                                                        <span class="text-muted">
                                                            vložil: <?= h($l['trener_jmeno'] ?: ('ID ' . (int)$l['vlozil_trener_id'])) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
/* UX: když uživatel začne psát novou kategorii, odznačí select (a naopak) */
(() => {
    const sel = document.getElementById('kategorie_id');
    const txt = document.getElementById('nova_kategorie');
    if (sel && txt) {
        txt.addEventListener('input', () => { if (txt.value.trim() !== '') sel.value = ''; });
        sel.addEventListener('change', () => { if (sel.value !== '') txt.value = ''; });
    }
})();

/* Cílení – zobrazení správných bloků */
function refreshTargetVisibility() {
    const cileni = document.getElementById('cileni')?.value || 'treneri';

    const wSk = document.getElementById('wrap_target_skupina');
    const wPs = document.getElementById('wrap_target_podskupina');
    const wSp = document.getElementById('wrap_target_sportovci');

    if (wSk) wSk.style.display = (cileni === 'skupina' || cileni === 'podskupina') ? '' : 'none';
    if (wPs) wPs.style.display = (cileni === 'podskupina') ? '' : 'none';
    if (wSp) wSp.style.display = (cileni === 'sportovci') ? '' : 'none';
}
document.getElementById('cileni')?.addEventListener('change', refreshTargetVisibility);
refreshTargetVisibility();

/* AJAX podskupiny po změně skupiny */
async function loadPodskupinyForSkupina(groupId, selectedId = '') {
    const selPs = document.getElementById('target_podskupina_id');
    if (!selPs) return;

    selPs.innerHTML = '<option value="">— načítám… —</option>';

    if (!groupId) {
        selPs.innerHTML = '<option value="">— vyber podskupinu —</option>';
        return;
    }

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'podskupiny');
        url.searchParams.set('skupina_id', groupId);

        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        const items = (data && data.items) ? data.items : [];
        selPs.replaceChildren(new Option('— vyber podskupinu —', ''));
        for (const it of items) {
            const id = String(it.id);
            const name = String(it.nazev || '');
            selPs.appendChild(new Option(name, id, false, Boolean(selectedId && selectedId === id)));
        }
    } catch (e) {
        selPs.innerHTML = '<option value="">— chyba načítání —</option>';
        console.error(e);
    }
}

const selGroup = document.getElementById('target_skupina_id');
if (selGroup) {
    selGroup.addEventListener('change', () => {
        loadPodskupinyForSkupina(selGroup.value, '');
    });
}

/* Sportovci – multi-select přes našeptávač (ajax_sportovci.php) */
(() => {
    const input = document.getElementById('sportovec_search');
    const list  = document.getElementById('sportovec_suggestions');
    const chips = document.getElementById('sportovec_chips');
    const hidden = document.getElementById('target_sportovci_ids');
    if (!input || !list || !chips || !hidden) return;

    const selected = new Map(); // id -> label

    const initialLabels = window.__initialSportovciLabels || {};
    for (const [id, label] of Object.entries(initialLabels)) {
        selected.set(String(id), String(label));
    }

    function syncHidden() {
        hidden.value = Array.from(selected.keys()).join(',');
    }

    function renderChips() {
        chips.innerHTML = '';
        for (const [id, label] of selected.entries()) {
            const el = document.createElement('span');
            el.className = 'chip';
            const text = document.createElement('span');
            text.textContent = label;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Odebrat');
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                selected.delete(id);
                syncHidden();
                renderChips();
            });
            el.append(text, remove);
            chips.appendChild(el);
        }
    }

    function showList(items) {
        if (!items.length) {
            list.style.display = 'none';
            list.innerHTML = '';
            return;
        }
        list.innerHTML = '';
        for (const it of items) {
            const div = document.createElement('div');
            div.className = 'suggestions-item';
            div.textContent = it.label;
            div.addEventListener('click', () => {
                selected.set(String(it.id), String(it.label));
                syncHidden();
                renderChips();
                input.value = '';
                list.style.display = 'none';
                list.innerHTML = '';
            });
            list.appendChild(div);
        }
        list.style.display = 'block';
    }

    let timer = null;
    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (timer) clearTimeout(timer);

        if (q.length < 2) {
            showList([]);
            return;
        }

        timer = setTimeout(async () => {
            try {
                const res = await fetch(`ajax_sportovci.php?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                const items = Array.isArray(data) ? data : (data.items || []);
                const norm = items
                    .map(x => ({ id: String(x.id), label: String(x.label || x.jmeno || x.name || '') }))
                    .filter(x => x.label);
                showList(norm.filter(x => !selected.has(x.id)));
            } catch (e) {
                console.error(e);
                showList([]);
            }
        }, 200);
    });

    document.addEventListener('click', (e) => {
        if (!list.contains(e.target) && e.target !== input) showList([]);
    });

    syncHidden();
    renderChips();
})();
</script>

<script>
window.__initialSportovciLabels = <?= json_encode(
    !empty($editTargets['sportovci']) ? $editTargets['sportovci'] : $formSportLabels,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
</script>


</body>
</html>
