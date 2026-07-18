<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('udalosti')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$udalost = [
    'nazev'           => '',
    'popis'           => '',
    'datum_od'        => date('Y-m-d\T08:00'),
    'datum_do'        => date('Y-m-d\T17:00'),
    'typ'             => 'jine',
    'zalohova_castka' => '',
    'zalohu_predal'   => '',
    'stav'            => 'otevrena',
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_udalosti WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $udalost = $row;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id ? 'Úprava události' : 'Nová událost' ?> – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-calendar-event fs-2 text-secondary"></i>
    <h1 class="h3 mb-0"><?= $id ? 'Úprava události' : 'Nová událost' ?></h1>
  </div>

  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle me-2"></i><?= h($_SESSION['flash_error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form id="formular-udalost">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="mb-3">
          <label for="nazev" class="form-label fw-semibold">
            <i class="bi bi-type me-1"></i>Název události
          </label>
          <input type="text" name="nazev" id="nazev" class="form-control" required
                 value="<?= h($udalost['nazev']) ?>">
        </div>

        <div class="mb-3">
          <label for="popis" class="form-label fw-semibold">
            <i class="bi bi-card-text me-1"></i>Popis (nepovinný)
          </label>
          <textarea name="popis" id="popis" class="form-control" rows="3"><?= h($udalost['popis'] ?? '') ?></textarea>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="datum_od" class="form-label fw-semibold">
              <i class="bi bi-calendar-check me-1"></i>Datum od
            </label>
            <input type="datetime-local" name="datum_od" id="datum_od" class="form-control" required
                   value="<?= date('Y-m-d\TH:i', strtotime($udalost['datum_od'])) ?>">
          </div>
          <div class="col-md-6">
            <label for="datum_do" class="form-label fw-semibold">
              <i class="bi bi-calendar-x me-1"></i>Datum do
            </label>
            <input type="datetime-local" name="datum_do" id="datum_do" class="form-control" required
                   value="<?= date('Y-m-d\TH:i', strtotime($udalost['datum_do'])) ?>">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label for="typ" class="form-label fw-semibold">
              <i class="bi bi-bookmark me-1"></i>Typ události
            </label>
            <select name="typ" id="typ" class="form-select">
              <option value="zavod"       <?= ($udalost['typ'] ?? '') === 'zavod'       ? 'selected' : '' ?>>Závod</option>
              <option value="soustredeni" <?= ($udalost['typ'] ?? '') === 'soustredeni' ? 'selected' : '' ?>>Soustředění</option>
              <option value="trenink"     <?= ($udalost['typ'] ?? '') === 'trenink'     ? 'selected' : '' ?>>Trénink</option>
              <option value="jine"        <?= ($udalost['typ'] ?? '') === 'jine'        ? 'selected' : '' ?>>Jiné</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="zalohova_castka" class="form-label fw-semibold">
              <i class="bi bi-currency-exchange me-1"></i>Záloha (Kč)
            </label>
            <input type="number" step="0.01" name="zalohova_castka" id="zalohova_castka" class="form-control"
                   value="<?= h($udalost['zalohova_castka'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label for="zalohu_predal" class="form-label fw-semibold">
              <i class="bi bi-person me-1"></i>Komu svěřena záloha
            </label>
            <input type="text" name="zalohu_predal" id="zalohu_predal" class="form-control"
                   value="<?= h($udalost['zalohu_predal'] ?? '') ?>">
          </div>
        </div>

        <?php if ($id > 0): ?>
        <div class="mt-3">
          <label for="stav" class="form-label fw-semibold">
            <i class="bi bi-flag me-1"></i>Stav události
          </label>
          <select name="stav" id="stav" class="form-select">
            <option value="otevrena" <?= ($udalost['stav'] ?? '') === 'otevrena' ? 'selected' : '' ?>>Otevřená</option>
            <option value="uzavrena" <?= ($udalost['stav'] ?? '') === 'uzavrena' ? 'selected' : '' ?>>Uzavřená</option>
          </select>
        </div>
        <?php endif; ?>

        <div id="ulozeni-vysledek" class="mt-3"></div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Aktualizovat' : 'Uložit' ?>
          </button>
          <a href="seznam.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zpět
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('formular-udalost').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('uloz.php', { method: 'POST', body: fd })
        .then(r => {
            if (!r.ok) throw new Error('HTTP chyba: ' + r.status);
            return r.json();
        })
        .then(data => {
            const el = document.getElementById('ulozeni-vysledek');
            if (data.status === 'ok') {
                el.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Uloženo.</div>';
                setTimeout(() => window.location.href = 'seznam.php', 800);
            } else {
                el.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + data.message + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('ulozeni-vysledek').innerHTML =
                '<div class="alert alert-danger">Chyba komunikace se serverem.</div>';
        });
});
</script>
</body>
</html>
