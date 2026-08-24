<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('vozidla')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vozidlo = [
    'znacka_model' => '', 'spz' => '', 'rok_vyroby' => '',
    'palivo' => '', 'stk_datum' => '', 'dalnicni_znamka_datum' => '', 'poznamka' => ''
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_vozidla WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $vozidlo = $row;
}

$palivaOptions = ['Benzín', 'Nafta', 'CNG/LPG', 'Elektro', 'Hybrid'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id ? 'Úprava vozidla' : 'Nové vozidlo' ?> – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-<?= $id ? 'pencil-square' : 'plus-circle' ?> fs-2 text-primary"></i>
    <h1 class="h3 mb-0"><?= $id ? 'Úprava vozidla' : 'Nové vozidlo' ?></h1>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form id="formular-vozidlo">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="znacka_model" class="form-label fw-semibold">
              <i class="bi bi-car-front me-1"></i>Značka a model
            </label>
            <input type="text" name="znacka_model" id="znacka_model" class="form-control"
                   required value="<?= h($vozidlo['znacka_model']) ?>" placeholder="např. Škoda Octavia">
          </div>
          <div class="col-md-3">
            <label for="spz" class="form-label fw-semibold">
              <i class="bi bi-card-text me-1"></i>SPZ
            </label>
            <input type="text" name="spz" id="spz" class="form-control"
                   required value="<?= h($vozidlo['spz']) ?>" placeholder="1A2 3456" style="text-transform: uppercase;">
          </div>
          <div class="col-md-3">
            <label for="rok_vyroby" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-1"></i>Rok výroby
            </label>
            <input type="number" name="rok_vyroby" id="rok_vyroby" class="form-control"
                   min="1970" max="2030" value="<?= h($vozidlo['rok_vyroby']) ?>">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label for="palivo" class="form-label fw-semibold">
              <i class="bi bi-fuel-pump me-1"></i>Palivo
            </label>
            <select name="palivo" id="palivo" class="form-select">
              <option value="">— vyberte —</option>
              <?php foreach ($palivaOptions as $p): ?>
                <option value="<?= h($p) ?>" <?= $vozidlo['palivo'] === $p ? 'selected' : '' ?>>
                  <?= h($p) ?>
                </option>
              <?php endforeach; ?>
              <?php if ($vozidlo['palivo'] && !in_array($vozidlo['palivo'], $palivaOptions)): ?>
                <option value="<?= h($vozidlo['palivo']) ?>" selected><?= h($vozidlo['palivo']) ?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="stk_datum" class="form-label fw-semibold">
              <i class="bi bi-shield-check me-1"></i>Platnost STK
            </label>
            <input type="date" name="stk_datum" id="stk_datum" class="form-control"
                   value="<?= h($vozidlo['stk_datum']) ?>">
          </div>
          <div class="col-md-4">
            <label for="dalnicni_znamka_datum" class="form-label fw-semibold">
              <i class="bi bi-signpost me-1"></i>Platnost dálniční známky
            </label>
            <input type="date" name="dalnicni_znamka_datum" id="dalnicni_znamka_datum" class="form-control"
                   value="<?= h($vozidlo['dalnicni_znamka_datum']) ?>">
          </div>
        </div>

        <div class="mt-3">
          <label for="poznamka" class="form-label fw-semibold">
            <i class="bi bi-chat-left-text me-1"></i>Poznámka
          </label>
          <textarea name="poznamka" id="poznamka" class="form-control" rows="3"><?= h($vozidlo['poznamka']) ?></textarea>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Aktualizovat' : 'Uložit' ?>
          </button>
          <a href="seznam.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zpět
          </a>
        </div>

        <div id="ulozeni-vysledek" class="mt-3"></div>
      </form>
    </div>
  </div>
</div>


<script>
document.getElementById('formular-vozidlo').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('uloz.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('ulozeni-vysledek');
            if (data.status === 'ok') {
                el.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Uloženo.</div>';
                setTimeout(() => window.location.href = 'seznam.php', 800);
            } else {
                el.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>' + data.message + '</div>';
            }
        })
        .catch(() => {
            document.getElementById('ulozeni-vysledek').innerHTML =
                '<div class="alert alert-danger">Chyba komunikace se serverem.</div>';
        });
});
</script>
</body>
</html>
