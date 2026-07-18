<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('sprava_podskupin')) {
    header("Location: login.php");
    exit;
}

// 1) Zpracování uložení / aktualizace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_podskupin.php");
        exit;
    }

    $id         = (int)($_POST['id'] ?? 0);
    $nazev      = trim($_POST['nazev'] ?? '');
    $poradi     = (int)($_POST['poradi'] ?? 0);
    $skupina_id = (int)($_POST['skupina_id'] ?? 0);
    $hash       = trim($_POST['hash'] ?? '');

    if ($nazev === '' || $skupina_id <= 0) {
        $_SESSION['flash_error'] = 'Název i skupina jsou povinné.';
        header("Location: sprava_podskupin.php");
        exit;
    }

    if ($hash === '') {
        $hash = hash('sha256', uniqid($nazev, true));
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE podskupiny SET nazev = :nazev, poradi = :poradi, skupina_id = :skupina, hash = :hash WHERE id = :id");
        $stmt->execute([':nazev' => $nazev, ':poradi' => $poradi, ':skupina' => $skupina_id, ':hash' => $hash, ':id' => $id]);
        $_SESSION['flash_success'] = 'Podskupina aktualizována.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO podskupiny (nazev, poradi, skupina_id, hash) VALUES (:nazev, :poradi, :skupina, :hash)");
        $stmt->execute([':nazev' => $nazev, ':poradi' => $poradi, ':skupina' => $skupina_id, ':hash' => $hash]);
        $_SESSION['flash_success'] = 'Podskupina přidána.';
    }
    header("Location: sprava_podskupin.php");
    exit;
}

// 2) Zpracování smazání
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Neplatný CSRF token.';
        header("Location: sprava_podskupin.php");
        exit;
    }

    $delId = (int)$_POST['delete_id'];
    $pdo->prepare("DELETE FROM podskupiny WHERE id = ?")->execute([$delId]);
    $_SESSION['flash_success'] = 'Podskupina smazána.';
    header("Location: sprava_podskupin.php");
    exit;
}

// 3) Načtení dat
$allGroups = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
$subGroups = $pdo->query("
    SELECT p.id, p.nazev, p.poradi, p.hash, p.skupina_id, g.nazev AS skupina_nazev
    FROM podskupiny p
    JOIN skupiny g ON g.id = p.skupina_id
    ORDER BY g.poradi, p.poradi
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Správa podskupin – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-diagram-3 fs-2 text-secondary"></i>
    <h1 class="h3 mb-0">Správa podskupin</h1>
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

  <!-- Tabulka podskupin -->
  <div class="card shadow-sm mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Skupina</th>
            <th>Podskupina</th>
            <th>Pořadí</th>
            <th>Hash</th>
            <th>Akce</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subGroups as $p): ?>
          <tr>
            <td><span class="badge bg-dark"><?= htmlspecialchars($p['skupina_nazev']) ?></span></td>
            <td><strong><?= htmlspecialchars($p['nazev']) ?></strong></td>
            <td><?= $p['poradi'] ?></td>
            <td><code class="small"><?= htmlspecialchars(substr($p['hash'], 0, 16)) ?>…</code></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary edit-btn"
                        data-id="<?= $p['id'] ?>"
                        data-nazev="<?= htmlspecialchars($p['nazev'], ENT_QUOTES) ?>"
                        data-poradi="<?= $p['poradi'] ?>"
                        data-skupina="<?= $p['skupina_id'] ?>"
                        data-hash="<?= $p['hash'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="m-0" data-confirm="Opravdu smazat podskupinu?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete" value="1">
                  <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
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

  <!-- Formulář -->
  <div class="card shadow-sm">
    <div class="card-header">
      <i class="bi bi-plus-lg me-2"></i><span id="form-title">Přidat podskupinu</span>
    </div>
    <div class="card-body">
      <form method="POST" id="sub-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="sub-id" value="0">
        <div class="row g-3">
          <div class="col-md-4">
            <label for="sub-nazev" class="form-label fw-semibold">Název podskupiny</label>
            <input type="text" class="form-control" id="sub-nazev" name="nazev" required>
          </div>
          <div class="col-md-2">
            <label for="sub-poradi" class="form-label fw-semibold">Pořadí</label>
            <input type="number" class="form-control" id="sub-poradi" name="poradi" value="0" required>
          </div>
          <div class="col-md-3">
            <label for="sub-skupina" class="form-label fw-semibold">Rodičovská skupina</label>
            <select id="sub-skupina" name="skupina_id" class="form-select" required>
              <option value="">— Vyber skupinu —</option>
              <?php foreach ($allGroups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nazev']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label for="sub-hash" class="form-label fw-semibold">Hash (nepovinné)</label>
            <input type="text" class="form-control" id="sub-hash" name="hash" placeholder="Auto-generace">
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
        document.getElementById('form-title').innerText = 'Upravit podskupinu #' + btn.dataset.id;
        document.getElementById('sub-id').value      = btn.dataset.id;
        document.getElementById('sub-nazev').value   = btn.dataset.nazev;
        document.getElementById('sub-poradi').value  = btn.dataset.poradi;
        document.getElementById('sub-skupina').value = btn.dataset.skupina;
        document.getElementById('sub-hash').value    = btn.dataset.hash;
        document.getElementById('sub-form').scrollIntoView({ behavior: 'smooth' });
    });
});

document.getElementById('form-reset').addEventListener('click', () => {
    document.getElementById('form-title').innerText = 'Přidat podskupinu';
    document.getElementById('sub-id').value      = 0;
    document.getElementById('sub-nazev').value   = '';
    document.getElementById('sub-poradi').value  = 0;
    document.getElementById('sub-skupina').value = '';
    document.getElementById('sub-hash').value    = '';
});
</script>
</body>
</html>
