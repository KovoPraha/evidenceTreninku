<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('jizdy')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$vozidla = $pdo->query("SELECT id, znacka_model, spz FROM ucto_vozidla ORDER BY znacka_model")->fetchAll(PDO::FETCH_ASSOC);
$treneri = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);

$prihlaseny_id      = $_SESSION['trener_id'];
$vybrane_vozidlo_id = (int)($_GET['id_vozidla'] ?? 0);

// Otevřené jízdy
$otevrene = $pdo->query("
    SELECT j.id, j.datum_start, j.tachometr_start, j.poloha_start, v.znacka_model, v.spz
    FROM ucto_jizdy j
    JOIN ucto_vozidla v ON j.vozidlo_id = v.id
    WHERE j.datum_konec IS NULL
    ORDER BY j.datum_start DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Záznam jízdy – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-signpost-2 fs-2 text-info"></i>
    <h1 class="h3 mb-0">Záznam jízdy</h1>
  </div>

  <?php if (!empty($otevrene)): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Otevřené jízdy:</strong>
      <?php foreach ($otevrene as $oj): ?>
        <span class="badge bg-dark ms-2">
          <?= h($oj['znacka_model']) ?> (<?= h($oj['spz']) ?>) – od <?= date('d.m. H:i', strtotime($oj['datum_start'])) ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form id="formular-jizda">
        <?= csrf_field() ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="vozidlo_id" class="form-label fw-semibold">
              <i class="bi bi-car-front me-1"></i>Vozidlo
            </label>
            <select name="vozidlo_id" id="vozidlo_id" class="form-select" required>
              <option value="">— Vyberte vozidlo —</option>
              <?php foreach ($vozidla as $v): ?>
                <option value="<?= $v['id'] ?>" <?= $v['id'] == $vybrane_vozidlo_id ? 'selected' : '' ?>>
                  <?= h($v['znacka_model']) ?> (<?= h($v['spz']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">
              <i class="bi bi-arrow-left-right me-1"></i>Fáze jízdy
            </label>
            <div class="d-flex gap-3 mt-1">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="fazejizdy" id="start" value="start" checked>
                <label class="form-check-label" for="start">
                  <i class="bi bi-play-circle text-success me-1"></i>Začátek jízdy
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="fazejizdy" id="end" value="end">
                <label class="form-check-label" for="end">
                  <i class="bi bi-stop-circle text-danger me-1"></i>Konec jízdy
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label for="ridic_id" class="form-label fw-semibold">
              <i class="bi bi-person me-1"></i>Řidič
            </label>
            <select name="ridic_id" id="ridic_id" class="form-select">
              <option value="0">Není v seznamu</option>
              <?php foreach ($treneri as $t): ?>
                <option value="<?= $t['id'] ?>" <?= $t['id'] == $prihlaseny_id ? 'selected' : '' ?>>
                  <?= h($t['jmeno']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4" id="jmeno_ridice_wrap" style="display:none;">
            <label for="ridic_text" class="form-label fw-semibold">Jméno řidiče</label>
            <input type="text" class="form-control" name="ridic_text" id="ridic_text" placeholder="Zadejte jméno">
          </div>
          <div class="col-md-4">
            <label for="stav_km" class="form-label fw-semibold">
              <i class="bi bi-speedometer2 me-1"></i>Stav tachometru (km)
            </label>
            <input type="number" class="form-control" name="stav_km" id="stav_km" required min="0">
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label for="poloha" class="form-label fw-semibold">
              <i class="bi bi-geo-alt me-1"></i>Místo (start/cíl)
            </label>
            <input type="text" class="form-control" name="poloha" id="poloha" placeholder="např. Praha, sídlo klubu">
          </div>
          <div class="col-md-6">
            <label for="poznamka" class="form-label fw-semibold">
              <i class="bi bi-chat-left-text me-1"></i>Poznámka
            </label>
            <input type="text" class="form-control" name="poznamka" id="poznamka" placeholder="Účel jízdy...">
          </div>
        </div>

        <div id="ulozeni-vysledek" class="mt-3"></div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Uložit jízdu
          </button>
          <a href="seznam.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zpět
          </a>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
// Řidič: zobrazit textové pole
document.getElementById('ridic_id').addEventListener('change', function() {
    const wrap = document.getElementById('jmeno_ridice_wrap');
    wrap.style.display = this.value === '0' ? 'block' : 'none';
    if (this.value !== '0') document.getElementById('ridic_text').value = '';
});

// AJAX odeslání
document.getElementById('formular-jizda').addEventListener('submit', function(e) {
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
