<?php
require_once __DIR__ . '/includes/session_security.php';
// cviky.php - správa cviků pro posilovnu
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$success = false;

$q = trim($_GET['q'] ?? '');
$show = $_GET['show'] ?? 'aktivni'; // aktivni | vse

// --------------------------------------
// POST: add / update / delete / toggle
// --------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    }
    $action = $_POST['action'] ?? '';

    // Pomocná validace ID
    $id = (isset($_POST['id']) && ctype_digit((string)$_POST['id'])) ? (int)$_POST['id'] : 0;

    if ($action === 'save') {
        $nazev  = trim($_POST['nazev'] ?? '');
        $popis  = trim($_POST['popis'] ?? '');
        $poradi = trim($_POST['poradi'] ?? '0');
        $aktivni = isset($_POST['aktivni']) ? 1 : 0;

        if ($nazev === '') $errors[] = 'Název cviku je povinný.';
        if ($poradi === '' || !preg_match('/^-?\d+$/', $poradi)) $errors[] = 'Pořadí musí být celé číslo.';

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE cviky
                        SET nazev = :nazev,
                            popis = :popis,
                            poradi = :poradi,
                            aktivni = :aktivni
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $stmt->execute([
                        ':nazev'   => $nazev,
                        ':popis'   => ($popis === '' ? null : $popis),
                        ':poradi'  => (int)$poradi,
                        ':aktivni' => $aktivni,
                        ':id'      => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO cviky (nazev, popis, poradi, aktivni)
                        VALUES (:nazev, :popis, :poradi, :aktivni)
                    ");
                    $stmt->execute([
                        ':nazev'   => $nazev,
                        ':popis'   => ($popis === '' ? null : $popis),
                        ':poradi'  => (int)$poradi,
                        ':aktivni' => $aktivni
                    ]);
                }

                header('Location: cviky.php?ok=1');
                exit;
            } catch (PDOException $e) {
                // Typicky duplicate unique nazev
                $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        if ($id <= 0) {
            $errors[] = 'Neplatné ID pro smazání.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM cviky WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                header('Location: cviky.php?ok=1');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Chyba při mazání: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        if ($id <= 0) {
            $errors[] = 'Neplatné ID.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE cviky SET aktivni = IF(aktivni=1,0,1) WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                header('Location: cviky.php?ok=1');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Chyba při změně aktivace: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['ok']) && $_GET['ok'] === '1') $success = true;

// --------------------------------------
// EDIT: načtení záznamu do formuláře
// --------------------------------------
$editId = (isset($_GET['edit']) && ctype_digit($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$edit = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM cviky WHERE id = ? LIMIT 1");
    $st->execute([$editId]);
    $edit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        $editId = 0;
        $errors[] = 'Cvik k editaci nebyl nalezen.';
    }
}

// --------------------------------------
// LIST: výpis cviků
// --------------------------------------
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(nazev LIKE :q OR popis LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($show !== 'vse') {
    $where[] = "aktivni = 1";
}

$sql = "SELECT * FROM cviky";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY poradi ASC, nazev ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cviky = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------
// FORM defaulty
// --------------------------------------
$form = [
    'id'      => $edit['id'] ?? 0,
    'nazev'   => $edit['nazev'] ?? '',
    'popis'   => $edit['popis'] ?? '',
    'poradi'  => $edit['poradi'] ?? 0,
    'aktivni' => isset($edit['aktivni']) ? (int)$edit['aktivni'] : 1,
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Správa cviků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-xs { padding: .18rem .45rem; font-size: .82rem; }
        .w-120 { width: 120px; }
        .w-90 { width: 90px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="mb-0">🏋️ Správa cviků</h1>
        <a class="btn btn-outline-secondary btn-sm" href="cviky.php">+ nový cvik</a>
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
    <div class="card mt-3 mb-4 shadow-sm" id="form">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h5 class="card-title mb-1">
                        <?= ($editId > 0) ? '✏️ Upravit cvik' : '➕ Přidat cvik' ?>
                    </h5>
                    <?php if ($editId > 0): ?>
                        <div class="small text-muted">ID: <?= (int)$editId ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="cviky.php">Zrušit editaci</a>
                <?php endif; ?>
            </div>

            <form method="POST" class="row g-3 mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="col-md-6">
                    <label class="form-label">Název</label>
                    <input type="text" name="nazev" class="form-control" required
                           value="<?= h($form['nazev']) ?>" placeholder="např. Dřep / Bench press / Mrtvý tah">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Pořadí</label>
                    <input type="number" name="poradi" class="form-control"
                           value="<?= h($form['poradi']) ?>">
                    <div class="form-text">Řazení ve selectu.</div>
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="aktivni" name="aktivni" value="1"
                               <?= ((int)$form['aktivni'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktivni">Aktivní</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Popis (volitelné)</label>
                    <textarea name="popis" class="form-control" rows="2"
                              placeholder="poznámka k technice, variantám…"><?= h($form['popis']) ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><?= ($editId > 0) ? 'Uložit změny' : 'Uložit cvik' ?></button>

                    <?php if ($editId > 0): ?>
                        <button type="submit"
                                class="btn btn-danger"
                                name="action"
                                value="delete"
                                data-confirm="Opravdu smazat tento cvik?">
                            Smazat
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTRY -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Hledat</label>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="název / popis…">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Zobrazit</label>
                    <select name="show" class="form-select">
                        <option value="aktivni" <?= ($show === 'aktivni') ? 'selected' : '' ?>>Jen aktivní</option>
                        <option value="vse" <?= ($show === 'vse') ? 'selected' : '' ?>>Vše</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Filtrovat</button>
                    <a class="btn btn-outline-secondary w-100" href="cviky.php">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- LIST -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Seznam cviků (<?= count($cviky) ?>)</h5>

            <?php if (empty($cviky)): ?>
                <div class="alert alert-info mb-0">Žádné cviky.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="w-90">Pořadí</th>
                                <th>Název</th>
                                <th>Popis</th>
                                <th class="w-120">Stav</th>
                                <th class="w-120 text-end">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cviky as $c): ?>
                            <tr>
                                <td class="mono"><?= (int)$c['poradi'] ?></td>
                                <td class="fw-semibold"><?= h($c['nazev']) ?></td>
                                <td class="text-muted small"><?= h(mb_strimwidth($c['popis'] ?? '', 0, 120, '…', 'UTF-8')) ?></td>
                                <td>
                                    <?php if ((int)$c['aktivni'] === 1): ?>
                                        <span class="badge text-bg-success">aktivní</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">neaktivní</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <a class="btn btn-xs btn-outline-secondary"
                                           href="cviky.php?edit=<?= (int)$c['id'] ?>#form">Upravit</a>

                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-xs btn-outline-primary"
                                                    name="action"
                                                    value="toggle">
                                                <?= ((int)$c['aktivni'] === 1) ? 'Deaktivovat' : 'Aktivovat' ?>
                                            </button>
                                        </form>

                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-xs btn-outline-danger"
                                                    name="action"
                                                    value="delete"
                                                    data-confirm="Opravdu smazat cvik: <?= h($c['nazev']) ?> ?">
                                                Smazat
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    Tip: pro select v tréninku budeme načítat jen <strong>aktivní</strong> cviky a řadit je podle <code>poradi</code>.
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>


</body>
</html>
