<?php
// sprava_segmentu.php — správa segmentů pro kolo-kroužek / kolo-silnice
session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('segmenty')) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];

$q    = trim($_GET['q'] ?? '');
$show = $_GET['show'] ?? 'aktivni';
$kat  = $_GET['kat'] ?? 'vse';

// ─── POST: save / delete / toggle ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (isset($_POST['id']) && ctype_digit((string)$_POST['id'])) ? (int)$_POST['id'] : 0;

        if ($action === 'save') {
            $nazev     = trim($_POST['nazev'] ?? '');
            $popis     = trim($_POST['popis'] ?? '');
            $kategorie = trim($_POST['kategorie'] ?? '');
            $poradi    = trim($_POST['poradi'] ?? '0');
            $aktivni   = isset($_POST['aktivni']) ? 1 : 0;
            $odkaz_1   = trim($_POST['odkaz_1'] ?? '');
            $odkaz_2   = trim($_POST['odkaz_2'] ?? '');

            if ($nazev === '') $errors[] = 'Název segmentu je povinný.';
            if (!in_array($kategorie, ['krouzek', 'silnice', 'mtb'])) $errors[] = 'Vyberte kategorii.';
            if (!preg_match('/^-?\d+$/', $poradi)) $errors[] = 'Pořadí musí být celé číslo.';

            // Handle photo upload
            $fotografie = null;
            $keepOldPhoto = true;
            $removePhoto = isset($_POST['remove_photo']);

            if (!empty($_FILES['fotografie']['name']) && $_FILES['fotografie']['error'] === UPLOAD_ERR_OK) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['fotografie']['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mime, $allowedMimes)) {
                    $errors[] = 'Neplatný formát obrázku. Povolené: JPG, PNG, WEBP, GIF.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/segmenty/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                    $ext = strtolower(pathinfo($_FILES['fotografie']['name'], PATHINFO_EXTENSION));
                    $newName = 'segment_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                    if (move_uploaded_file($_FILES['fotografie']['tmp_name'], $uploadDir . $newName)) {
                        $fotografie = 'uploads/segmenty/' . $newName;
                        $keepOldPhoto = false;
                    } else {
                        $errors[] = 'Nepodařilo se nahrát fotografii.';
                    }
                }
            }

            if (empty($errors)) {
                try {
                    if ($id > 0) {
                        // Load old record for photo handling
                        $oldStmt = $pdo->prepare("SELECT fotografie FROM segmenty WHERE id = ?");
                        $oldStmt->execute([$id]);
                        $oldPhoto = (string)$oldStmt->fetchColumn();

                        // Determine final photo value
                        if ($removePhoto && $keepOldPhoto) {
                            // User wants to remove existing photo
                            if ($oldPhoto && file_exists(__DIR__ . '/' . $oldPhoto)) {
                                $dir = dirname(__DIR__ . '/' . $oldPhoto);
                                rename(__DIR__ . '/' . $oldPhoto, $dir . '/smazano_' . basename($oldPhoto));
                            }
                            $fotoValue = null;
                        } elseif (!$keepOldPhoto) {
                            // New photo uploaded — soft-delete old
                            if ($oldPhoto && file_exists(__DIR__ . '/' . $oldPhoto)) {
                                $dir = dirname(__DIR__ . '/' . $oldPhoto);
                                rename(__DIR__ . '/' . $oldPhoto, $dir . '/smazano_' . basename($oldPhoto));
                            }
                            $fotoValue = $fotografie;
                        } else {
                            $fotoValue = $oldPhoto ?: null;
                        }

                        $stmt = $pdo->prepare("
                            UPDATE segmenty SET
                                nazev = :nazev, popis = :popis, kategorie = :kategorie,
                                poradi = :poradi, aktivni = :aktivni,
                                odkaz_1 = :odkaz_1, odkaz_2 = :odkaz_2, fotografie = :fotografie
                            WHERE id = :id LIMIT 1
                        ");
                        $stmt->execute([
                            ':nazev'      => $nazev,
                            ':popis'      => $popis === '' ? null : $popis,
                            ':kategorie'  => $kategorie,
                            ':poradi'     => (int)$poradi,
                            ':aktivni'    => $aktivni,
                            ':odkaz_1'    => $odkaz_1 === '' ? null : $odkaz_1,
                            ':odkaz_2'    => $odkaz_2 === '' ? null : $odkaz_2,
                            ':fotografie' => $fotoValue,
                            ':id'         => $id,
                        ]);
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO segmenty (nazev, popis, kategorie, poradi, aktivni, odkaz_1, odkaz_2, fotografie)
                            VALUES (:nazev, :popis, :kategorie, :poradi, :aktivni, :odkaz_1, :odkaz_2, :fotografie)
                        ");
                        $stmt->execute([
                            ':nazev'      => $nazev,
                            ':popis'      => $popis === '' ? null : $popis,
                            ':kategorie'  => $kategorie,
                            ':poradi'     => (int)$poradi,
                            ':aktivni'    => $aktivni,
                            ':odkaz_1'    => $odkaz_1 === '' ? null : $odkaz_1,
                            ':odkaz_2'    => $odkaz_2 === '' ? null : $odkaz_2,
                            ':fotografie' => $fotografie,
                        ]);
                    }

                    $_SESSION['flash_success'] = 'Segment uložen.';
                    header('Location: sprava_segmentu.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete') {
            if ($id <= 0) {
                $errors[] = 'Neplatné ID.';
            } else {
                // Check if segment is used in measurements
                $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM mereni_zaznamy WHERE segment_id = ?");
                $usedStmt->execute([$id]);
                $usedCount = (int)$usedStmt->fetchColumn();

                if ($usedCount > 0) {
                    $errors[] = "Segment nelze smazat — je použit v $usedCount měření. Deaktivujte ho místo mazání.";
                } else {
                    try {
                        // Soft-delete photo
                        $fotoStmt = $pdo->prepare("SELECT fotografie FROM segmenty WHERE id = ?");
                        $fotoStmt->execute([$id]);
                        $foto = (string)$fotoStmt->fetchColumn();
                        if ($foto && file_exists(__DIR__ . '/' . $foto)) {
                            $dir = dirname(__DIR__ . '/' . $foto);
                            rename(__DIR__ . '/' . $foto, $dir . '/smazano_' . basename($foto));
                        }

                        $pdo->prepare("DELETE FROM segmenty WHERE id = ? LIMIT 1")->execute([$id]);
                        $_SESSION['flash_success'] = 'Segment smazán.';
                        header('Location: sprava_segmentu.php');
                        exit;
                    } catch (PDOException $e) {
                        $errors[] = 'Chyba při mazání: ' . $e->getMessage();
                    }
                }
            }
        }

        if ($action === 'toggle') {
            if ($id <= 0) {
                $errors[] = 'Neplatné ID.';
            } else {
                try {
                    $pdo->prepare("UPDATE segmenty SET aktivni = IF(aktivni=1,0,1) WHERE id = ? LIMIT 1")->execute([$id]);
                    $_SESSION['flash_success'] = 'Stav segmentu změněn.';
                    header('Location: sprava_segmentu.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Chyba: ' . $e->getMessage();
                }
            }
        }
    }
}

// ─── EDIT: load record ──────────────────────────────────────────────────────
$editId = (isset($_GET['edit']) && ctype_digit($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$edit = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM segmenty WHERE id = ? LIMIT 1");
    $st->execute([$editId]);
    $edit = $st->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        $editId = 0;
        $errors[] = 'Segment k editaci nebyl nalezen.';
    }
}

// ─── LIST: fetch segments ───────────────────────────────────────────────────
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(nazev LIKE :q OR popis LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($show !== 'vse') {
    $where[] = "aktivni = 1";
}
if ($kat === 'krouzek' || $kat === 'silnice' || $kat === 'mtb') {
    $where[] = "kategorie = :kat";
    $params[':kat'] = $kat;
}

$sql = "SELECT * FROM segmenty";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY kategorie, poradi ASC, nazev ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$segmenty = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$statsAll = $pdo->query("SELECT COUNT(*) FROM segmenty")->fetchColumn();
$statsKrouzek = $pdo->query("SELECT COUNT(*) FROM segmenty WHERE kategorie='krouzek'")->fetchColumn();
$statsSilnice = $pdo->query("SELECT COUNT(*) FROM segmenty WHERE kategorie='silnice'")->fetchColumn();
$statsMtb = $pdo->query("SELECT COUNT(*) FROM segmenty WHERE kategorie='mtb'")->fetchColumn();
$statsActive = $pdo->query("SELECT COUNT(*) FROM segmenty WHERE aktivni=1")->fetchColumn();

// ─── FORM defaults ──────────────────────────────────────────────────────────
$form = [
    'id'        => $edit['id'] ?? 0,
    'nazev'     => $edit['nazev'] ?? '',
    'popis'     => $edit['popis'] ?? '',
    'kategorie' => $edit['kategorie'] ?? '',
    'poradi'    => $edit['poradi'] ?? 0,
    'aktivni'   => isset($edit['aktivni']) ? (int)$edit['aktivni'] : 1,
    'odkaz_1'   => $edit['odkaz_1'] ?? '',
    'odkaz_2'   => $edit['odkaz_2'] ?? '',
    'fotografie'=> $edit['fotografie'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa segmentů</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-xs { padding: .18rem .45rem; font-size: .82rem; }
        .thumb-sm { width: 48px; height: 48px; object-fit: cover; border-radius: 6px; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h1 class="mb-0"><i class="bi bi-signpost-split me-2 text-primary"></i>Správa segmentů</h1>
        <a class="btn btn-outline-secondary btn-sm" href="sprava_segmentu.php">
            <i class="bi bi-plus-lg me-1"></i>Nový segment
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-primary"><?= (int)$statsAll ?></div>
                    <div class="text-muted small">Celkem</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold" style="color:#7c3aed;"><?= (int)$statsKrouzek ?></div>
                    <div class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i>Kroužek</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-danger"><?= (int)$statsSilnice ?></div>
                    <div class="text-muted small"><i class="bi bi-signpost me-1"></i>Silnice</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-warning"><?= (int)$statsMtb ?></div>
                    <div class="text-muted small"><i class="bi bi-tree me-1"></i>MTB</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-3 fw-bold text-success"><?= (int)$statsActive ?></div>
                    <div class="text-muted small"><i class="bi bi-check-circle me-1"></i>Aktivních</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="card mb-4 shadow-sm" id="form">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?= ($editId > 0) ? 'pencil' : 'plus-lg' ?> me-1"></i>
            <?= ($editId > 0) ? 'Upravit segment' : 'Nový segment' ?>
            <?php if ($editId > 0): ?>
                <a class="btn btn-sm btn-outline-light float-end" href="sprava_segmentu.php">Zrušit</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

                <div class="col-md-5">
                    <label class="form-label req">Název</label>
                    <input type="text" name="nazev" class="form-control" required
                           value="<?= h($form['nazev']) ?>" placeholder="např. Strahov - okruh 2 km">
                </div>
                <div class="col-md-3">
                    <label class="form-label req">Kategorie</label>
                    <select name="kategorie" class="form-select" required>
                        <option value="">— vyberte —</option>
                        <option value="krouzek" <?= $form['kategorie'] === 'krouzek' ? 'selected' : '' ?>>Kroužek</option>
                        <option value="silnice" <?= $form['kategorie'] === 'silnice' ? 'selected' : '' ?>>Silnice</option>
                        <option value="mtb" <?= $form['kategorie'] === 'mtb' ? 'selected' : '' ?>>MTB</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Pořadí</label>
                    <input type="number" name="poradi" class="form-control" value="<?= (int)$form['poradi'] ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="aktivni" name="aktivni" value="1"
                               <?= ((int)$form['aktivni'] === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="aktivni">Aktivní</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Popis</label>
                    <textarea name="popis" class="form-control" rows="2"
                              placeholder="popis trasy, terén, obtížnost…"><?= h($form['popis']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Odkaz 1 <span class="text-muted fw-normal">(mapy.cz, Strava…)</span></label>
                    <input type="url" name="odkaz_1" class="form-control" value="<?= h($form['odkaz_1']) ?>"
                           placeholder="https://mapy.cz/...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Odkaz 2</label>
                    <input type="url" name="odkaz_2" class="form-control" value="<?= h($form['odkaz_2']) ?>"
                           placeholder="https://...">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Fotografie</label>
                    <input type="file" name="fotografie" class="form-control" accept="image/*">
                    <?php if ($form['fotografie']): ?>
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <img src="<?= h($form['fotografie']) ?>" class="thumb-sm" alt="foto">
                            <span class="small text-muted"><?= h(basename($form['fotografie'])) ?></span>
                            <div class="form-check ms-2">
                                <input class="form-check-input" type="checkbox" name="remove_photo" id="removePhoto" value="1">
                                <label class="form-check-label small text-danger" for="removePhoto">Odebrat</label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= ($editId > 0) ? 'Uložit změny' : 'Uložit segment' ?>
                    </button>
                    <?php if ($editId > 0): ?>
                        <button type="submit" class="btn btn-danger" name="action" value="delete"
                                data-confirm="Opravdu smazat tento segment?">
                            <i class="bi bi-trash me-1"></i>Smazat
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTER -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Hledat</label>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="název / popis…">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategorie</label>
                    <select name="kat" class="form-select">
                        <option value="vse" <?= $kat === 'vse' ? 'selected' : '' ?>>Vše</option>
                        <option value="krouzek" <?= $kat === 'krouzek' ? 'selected' : '' ?>>Kroužek</option>
                        <option value="silnice" <?= $kat === 'silnice' ? 'selected' : '' ?>>Silnice</option>
                        <option value="mtb" <?= $kat === 'mtb' ? 'selected' : '' ?>>MTB</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stav</label>
                    <select name="show" class="form-select">
                        <option value="aktivni" <?= $show === 'aktivni' ? 'selected' : '' ?>>Aktivní</option>
                        <option value="vse" <?= $show === 'vse' ? 'selected' : '' ?>>Vše</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-primary w-100">Filtrovat</button>
                    <a class="btn btn-outline-secondary w-100" href="sprava_segmentu.php">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- LIST -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-list-ul me-1"></i>Segmenty (<?= count($segmenty) ?>)
            </h5>

            <?php if (empty($segmenty)): ?>
                <div class="alert alert-info mb-0">Žádné segmenty.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;">Poř.</th>
                                <th>Název</th>
                                <th style="width:100px;">Kategorie</th>
                                <th style="width:60px;">Foto</th>
                                <th>Odkazy</th>
                                <th style="width:90px;">Stav</th>
                                <th style="width:180px;" class="text-end">Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($segmenty as $s): ?>
                            <tr class="<?= (int)$s['aktivni'] === 0 ? 'table-secondary' : '' ?>">
                                <td class="text-muted"><?= (int)$s['poradi'] ?></td>
                                <td>
                                    <strong><?= h($s['nazev']) ?></strong>
                                    <?php if ($s['popis']): ?>
                                        <div class="text-muted small"><?= h(mb_strimwidth($s['popis'], 0, 80, '…', 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['kategorie'] === 'krouzek'): ?>
                                        <span class="badge" style="background:#7c3aed;">Kroužek</span>
                                    <?php elseif ($s['kategorie'] === 'mtb'): ?>
                                        <span class="badge bg-warning text-dark">MTB</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Silnice</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['fotografie'] && file_exists(__DIR__ . '/' . $s['fotografie'])): ?>
                                        <img src="<?= h($s['fotografie']) ?>" class="thumb-sm" alt="foto">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if ($s['odkaz_1']): ?>
                                        <a href="<?= h($s['odkaz_1']) ?>" target="_blank" class="me-2">
                                            <i class="bi bi-link-45deg"></i>Odkaz 1
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($s['odkaz_2']): ?>
                                        <a href="<?= h($s['odkaz_2']) ?>" target="_blank">
                                            <i class="bi bi-link-45deg"></i>Odkaz 2
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$s['odkaz_1'] && !$s['odkaz_2']): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (int)$s['aktivni'] === 1
                                        ? '<span class="badge text-bg-success">aktivní</span>'
                                        : '<span class="badge text-bg-secondary">neaktivní</span>' ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <a class="btn btn-xs btn-outline-secondary"
                                           href="sprava_segmentu.php?edit=<?= (int)$s['id'] ?>#form">Upravit</a>

                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button class="btn btn-xs btn-outline-primary" name="action" value="toggle">
                                                <?= (int)$s['aktivni'] === 1 ? 'Deaktivovat' : 'Aktivovat' ?>
                                            </button>
                                        </form>

                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                            <button class="btn btn-xs btn-outline-danger" name="action" value="delete"
                                                    data-confirm="Opravdu smazat: <?= h($s['nazev']) ?> ?">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
