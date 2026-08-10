<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('sprava_sportovist')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$errors = [];
$success = '';

// ── POST akce ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id    = (int)($_POST['id'] ?? 0);
            $kod   = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['kod'] ?? '')));
            $nazev = trim($_POST['nazev'] ?? '');
            $verejne = isset($_POST['je_verejne']) ? 1 : 0;
            $poradi  = (int)($_POST['poradi'] ?? 0);
            $aktivni = isset($_POST['aktivni']) ? 1 : 0;

            if ($kod === '') $errors[] = 'Kód nesmí být prázdný.';
            if ($nazev === '') $errors[] = 'Název nesmí být prázdný.';

            if (empty($errors)) {
                try {
                    if ($id > 0) {
                        $pdo->prepare("UPDATE sportovist SET kod=?, nazev=?, je_verejne=?, poradi=?, aktivni=? WHERE id=?")
                            ->execute([$kod, $nazev, $verejne, $poradi, $aktivni, $id]);
                        $success = 'Sportoviště bylo aktualizováno.';
                    } else {
                        $pdo->prepare("INSERT INTO sportovist (kod, nazev, je_verejne, poradi, aktivni) VALUES (?,?,?,?,?)")
                            ->execute([$kod, $nazev, $verejne, $poradi, $aktivni]);
                        $success = 'Sportoviště bylo přidáno.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare("DELETE FROM sportovist WHERE id=?")->execute([$id]);
                    $success = 'Sportoviště bylo smazáno.';
                } catch (PDOException $e) {
                    $errors[] = 'Nelze smazat — sportoviště je pravděpodobně použito v rezervacích.';
                }
            }
        }
    }
}

// ── Načtení dat ───────────────────────────────────────────────────────────────
$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM sportovist WHERE id=?");
    $st->execute([$editId]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC);
}

$sportovist = $pdo->query("SELECT * FROM sportovist ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa sportovišť</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4" style="max-width:900px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0"><i class="bi bi-building me-2 text-primary"></i>Správa sportovišť</h1>
        <a href="kalendar_sportovist.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-calendar3 me-1"></i>Kalendář
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <!-- Formulář -->
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <?= $editRow ? 'Upravit sportoviště' : 'Přidat sportoviště' ?>
        </div>
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label req" for="sportoviste-kod">Kód</label>
                        <input type="text" name="kod" id="sportoviste-kod" class="form-control"
                               value="<?= h($editRow['kod'] ?? '') ?>"
                               placeholder="napr. velodrom" pattern="[a-z0-9_]+" required>
                        <div class="form-text">Malá písmena, podtržítka</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label req" for="sportoviste-nazev">Název</label>
                        <input type="text" name="nazev" id="sportoviste-nazev" class="form-control"
                               value="<?= h($editRow['nazev'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="sportoviste-poradi">Pořadí</label>
                        <input type="number" name="poradi" id="sportoviste-poradi" class="form-control"
                               value="<?= h((string)($editRow['poradi'] ?? 0)) ?>">
                    </div>
                    <div class="col-md-2 d-flex flex-column justify-content-end gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="je_verejne" id="verejne"
                                   <?= ($editRow['je_verejne'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="verejne">Veřejné</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aktivni" id="aktivni"
                                   <?= !$editRow || $editRow['aktivni'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="aktivni">Aktivní</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i><?= $editRow ? 'Uložit změny' : 'Přidat' ?>
                    </button>
                    <?php if ($editRow): ?>
                        <a href="sprava_sportovist.php" class="btn btn-outline-secondary">Zrušit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabulka -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kód</th>
                        <th>Název</th>
                        <th class="text-center">Veřejné</th>
                        <th class="text-center">Pořadí</th>
                        <th class="text-center">Aktivní</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sportovist as $s): ?>
                    <tr class="<?= $s['aktivni'] ? '' : 'text-muted' ?>">
                        <td><code><?= h($s['kod']) ?></code></td>
                        <td><?= h($s['nazev']) ?></td>
                        <td class="text-center">
                            <?= $s['je_verejne']
                                ? '<i class="bi bi-globe text-success" title="Veřejné"></i>'
                                : '<i class="bi bi-lock text-secondary" title="Interní"></i>' ?>
                        </td>
                        <td class="text-center"><?= $s['poradi'] ?></td>
                        <td class="text-center">
                            <?= $s['aktivni']
                                ? '<i class="bi bi-check-circle-fill text-success"></i>'
                                : '<i class="bi bi-x-circle text-secondary"></i>' ?>
                        </td>
                        <td class="text-end">
                            <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" aria-label="Upravit sportoviště <?= h($s['nazev']) ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" class="d-inline"
                                  data-confirm="Opravdu smazat <?= h($s['nazev']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Smazat sportoviště <?= h($s['nazev']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sportovist)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Žádná sportoviště</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
