<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$vozidla = $pdo->query("SELECT id, znacka_model, spz FROM ucto_vozidla ORDER BY znacka_model")->fetchAll(PDO::FETCH_ASSOC);
$udalosti = $pdo->query("SELECT id, nazev FROM ucto_udalosti ORDER BY datum_od DESC")->fetchAll(PDO::FETCH_ASSOC);
$treneri  = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);

$id = (int)($_GET['id'] ?? 0);
$uctenka = [
    'castka'         => '',
    'platba'         => 'hotove_tym',
    'vozidlo_id'     => '',
    'udalost_id'     => '',
    'kategorie'      => '',
    'obrazek_path'   => '',
    'poznamka'       => '',
    'nahrano_kym'    => $_SESSION['trener_id'],
    'nahrano_jmenem' => '',
    'datum'          => date('Y-m-d\TH:i')
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_uctenky WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $uctenka = $row;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $id ? 'Úprava účtenky' : 'Nová účtenka' ?> – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-receipt fs-2 text-secondary"></i>
    <h1 class="h3 mb-0"><?= $id ? 'Úprava účtenky' : 'Nová účtenka' ?></h1>
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
      <form id="formular-uctenka" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label for="castka" class="form-label fw-semibold">
              <i class="bi bi-currency-exchange me-1"></i>Částka (Kč)
            </label>
            <input type="number" step="0.01" name="castka" id="castka" class="form-control" required
                   value="<?= h($uctenka['castka']) ?>">
          </div>
          <div class="col-md-6">
            <label for="platba" class="form-label fw-semibold">
              <i class="bi bi-credit-card me-1"></i>Způsob platby
            </label>
            <select name="platba" id="platba" class="form-select">
              <option value="kartou_tym"      <?= ($uctenka['platba'] ?? '') === 'kartou_tym'      ? 'selected' : '' ?>>Kartou – tým</option>
              <option value="hotove_tym"      <?= ($uctenka['platba'] ?? '') === 'hotove_tym'      ? 'selected' : '' ?>>Hotově – tým</option>
              <option value="vlastni_karta"   <?= ($uctenka['platba'] ?? '') === 'vlastni_karta'   ? 'selected' : '' ?>>Vlastní kartou</option>
              <option value="vlastni_hotovost"<?= ($uctenka['platba'] ?? '') === 'vlastni_hotovost'? 'selected' : '' ?>>Vlastní hotovost</option>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label for="kategorie" class="form-label fw-semibold">
            <i class="bi bi-tag me-1"></i>Kategorie účtenky
          </label>
          <select name="kategorie" id="kategorie" class="form-select" required>
            <option value="" disabled <?= ($uctenka['kategorie'] ?? '') === '' ? 'selected' : '' ?>>— Vyber kategorii —</option>
            <option value="zavodni_oddil"  <?= ($uctenka['kategorie'] ?? '') === 'zavodni_oddil'  ? 'selected' : '' ?>>Závodní oddíl</option>
            <option value="velodrom_areal" <?= ($uctenka['kategorie'] ?? '') === 'velodrom_areal' ? 'selected' : '' ?>>Velodrom-areál</option>
            <option value="cyklo_krouzek"  <?= ($uctenka['kategorie'] ?? '') === 'cyklo_krouzek'  ? 'selected' : '' ?>>Cyklo kroužek</option>
            <option value="ostatni"        <?= ($uctenka['kategorie'] ?? '') === 'ostatni'        ? 'selected' : '' ?>>Ostatní</option>
          </select>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label for="vozidlo_id" class="form-label fw-semibold">
              <i class="bi bi-car-front me-1"></i>Vozidlo (volitelně)
            </label>
            <select name="vozidlo_id" id="vozidlo_id" class="form-select">
              <option value="">—</option>
              <?php foreach ($vozidla as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $v['id'] == ($uctenka['vozidlo_id'] ?? '') ? 'selected' : '' ?>>
                  <?= h($v['znacka_model']) ?> (<?= h($v['spz']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="udalost_id" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-1"></i>Událost (volitelně)
            </label>
            <select name="udalost_id" id="udalost_id" class="form-select">
              <option value="">—</option>
              <?php foreach ($udalosti as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $e['id'] == ($uctenka['udalost_id'] ?? '') ? 'selected' : '' ?>>
                  <?= h($e['nazev']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label fw-semibold">
            <i class="bi bi-person me-1"></i>Zadáno (kdo pořídil)
          </label>
          <div class="row g-2">
            <div class="col-md-6">
              <select name="nahrano_kym" class="form-select">
                <option value="">Není v seznamu</option>
                <?php foreach ($treneri as $t): ?>
                  <option value="<?= $t['id'] ?>" <?= $t['id'] == ($uctenka['nahrano_kym'] ?? '') ? 'selected' : '' ?>>
                    <?= h($t['jmeno']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <input type="text" name="nahrano_jmenem" class="form-control" placeholder="Jméno (není v seznamu)"
                     value="<?= h($uctenka['nahrano_jmenem'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label for="datum" class="form-label fw-semibold">
              <i class="bi bi-calendar-check me-1"></i>Datum
            </label>
            <input type="datetime-local" name="datum" id="datum" class="form-control"
                   value="<?= date('Y-m-d\TH:i', strtotime($uctenka['datum'])) ?>">
          </div>
        </div>

        <div class="mt-3">
          <label for="poznamka" class="form-label fw-semibold">
            <i class="bi bi-chat-left-text me-1"></i>Poznámka
          </label>
          <textarea name="poznamka" id="poznamka" class="form-control" rows="2"><?= h($uctenka['poznamka'] ?? '') ?></textarea>
        </div>

        <div class="mt-3">
          <label for="obrazek" class="form-label fw-semibold">
            <i class="bi bi-image me-1"></i>Fotografie účtenky
          </label>
          <?php if (!empty($uctenka['obrazek_path'])): ?>
            <div class="mb-2">
              <a href="../<?= h($uctenka['obrazek_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-image me-1"></i>Stávající doklad
              </a>
            </div>
          <?php endif; ?>
          <input type="file" name="obrazek" id="obrazek" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          <div class="form-text">Povolené formáty: PDF, JPG, PNG</div>
        </div>

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
document.getElementById('formular-uctenka').addEventListener('submit', function(e) {
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
