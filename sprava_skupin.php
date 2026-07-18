<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sprava_skupin')) {
    header("Location: login.php");
    exit;
}

// 1) Zpracování uložení / aktualizace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_skupin.php");
        exit;
    }

    $id     = (int)($_POST['id'] ?? 0);
    $nazev  = trim($_POST['nazev'] ?? '');
    $poradi = (int)($_POST['poradi'] ?? 0);
    $hash   = trim($_POST['hash'] ?? '');

    if ($nazev === '') {
        $_SESSION['flash_error'] = 'Název skupiny je povinný.';
        header("Location: sprava_skupin.php");
        exit;
    }

    if ($hash === '') {
        $hash = hash('sha256', uniqid($nazev, true));
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE skupiny SET nazev = :nazev, poradi = :poradi, hash = :hash WHERE id = :id");
        $stmt->execute([':nazev' => $nazev, ':poradi' => $poradi, ':hash' => $hash, ':id' => $id]);
        $_SESSION['flash_success'] = 'Skupina aktualizována.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO skupiny (nazev, poradi, hash) VALUES (:nazev, :poradi, :hash)");
        $stmt->execute([':nazev' => $nazev, ':poradi' => $poradi, ':hash' => $hash]);
        $_SESSION['flash_success'] = 'Skupina přidána.';
    }
    header("Location: sprava_skupin.php");
    exit;
}

// 2) Zpracování smazání
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_skupin.php");
        exit;
    }

    $delId = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM skupiny WHERE id = ?");
    $stmt->execute([$delId]);
    $_SESSION['flash_success'] = 'Skupina smazána.';
    header("Location: sprava_skupin.php");
    exit;
}

// 3) Načtení všech skupin
$groups = $pdo->query("
    SELECT id, nazev, poradi, hash
    FROM skupiny
    ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Správa skupin – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-diagram-2 fs-2 text-secondary"></i>
    <h1 class="h3 mb-0">Správa skupin</h1>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['flash_success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['flash_error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <!-- Tabulka existujících skupin -->
  <div class="card shadow-sm mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Název</th>
            <th>Pořadí</th>
            <th>Hash</th>
            <th>Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $g): ?>
          <tr>
            <td><?= $g['id'] ?></td>
            <td><strong><?= htmlspecialchars($g['nazev']) ?></strong></td>
            <td><?= $g['poradi'] ?></td>
            <td>
                <code class="small"><?= htmlspecialchars(substr($g['hash'], 0, 16)) ?>…</code>
                <a href="program_skupiny.php?hash=<?= urlencode($g['hash']) ?>" target="_blank"
                   class="btn btn-sm btn-outline-info py-0 ms-1" title="Veřejný program skupiny">
                    <i class="bi bi-calendar3-week"></i>
                </a>
            </td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary edit-btn"
                        data-id="<?= $g['id'] ?>"
                        data-nazev="<?= htmlspecialchars($g['nazev'], ENT_QUOTES) ?>"
                        data-poradi="<?= $g['poradi'] ?>"
                        data-hash="<?= $g['hash'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="m-0" data-confirm="Opravdu smazat skupinu?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete" value="1">
                  <input type="hidden" name="delete_id" value="<?= $g['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Formulář pro přidání / úpravu skupiny -->
  <div class="card shadow-sm">
    <div class="card-header">
      <i class="bi bi-plus-lg me-2"></i><span id="form-title">Přidat novou skupinu</span>
    </div>
    <div class="card-body">
      <form method="POST" id="group-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="group-id" value="0">
        <div class="row g-3">
          <div class="col-md-5">
            <label for="group-nazev" class="form-label fw-semibold">Název skupiny</label>
            <input type="text" class="form-control" id="group-nazev" name="nazev" required>
          </div>
          <div class="col-md-3">
            <label for="group-poradi" class="form-label fw-semibold">Pořadí</label>
            <input type="number" class="form-control" id="group-poradi" name="poradi" value="0" required>
          </div>
          <div class="col-md-4">
            <label for="group-hash" class="form-label fw-semibold">Hash (nepovinné)</label>
            <input type="text" class="form-control" id="group-hash" name="hash" placeholder="Auto-generace">
          </div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button type="submit" name="save" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Uložit
          </button>
          <button type="button" id="form-reset" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i>Zrušit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('form-title').innerText = 'Upravit skupinu #' + btn.dataset.id;
        document.getElementById('group-id').value     = btn.dataset.id;
        document.getElementById('group-nazev').value  = btn.dataset.nazev;
        document.getElementById('group-poradi').value = btn.dataset.poradi;
        document.getElementById('group-hash').value   = btn.dataset.hash;
        document.getElementById('group-form').scrollIntoView({ behavior: 'smooth' });
    });
});

document.getElementById('form-reset').addEventListener('click', () => {
    document.getElementById('form-title').innerText = 'Přidat novou skupinu';
    document.getElementById('group-id').value       = 0;
    document.getElementById('group-nazev').value    = '';
    document.getElementById('group-poradi').value   = 0;
    document.getElementById('group-hash').value     = '';
});
</script>
</body>
</html>
