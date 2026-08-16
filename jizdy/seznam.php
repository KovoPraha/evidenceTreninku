<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('jizdy')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$filterVozidlo = (int)($_GET['vozidlo_id'] ?? 0);
$vozidla = $pdo->query("SELECT id, znacka_model, spz FROM ucto_vozidla ORDER BY znacka_model")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT j.*, v.znacka_model, v.spz,
           COALESCE(t.jmeno, NULLIF(j.ridic_text,''), CONCAT('ID #', j.ridic_id)) AS ridic_jmeno
    FROM ucto_jizdy j
    JOIN ucto_vozidla v ON j.vozidlo_id = v.id
    LEFT JOIN treneri t ON j.ridic_id = t.id
";
$params = [];
if ($filterVozidlo > 0) {
    $sql .= " WHERE j.vozidlo_id = ?";
    $params[] = $filterVozidlo;
}
$sql .= " ORDER BY COALESCE(j.datum_konec, j.datum_start) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jizdy = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filterNazev = '';
if ($filterVozidlo) {
    foreach ($vozidla as $v) {
        if ($v['id'] == $filterVozidlo) { $filterNazev = $v['znacka_model'] . ' (' . $v['spz'] . ')'; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kniha jízd – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-signpost-2 fs-2 text-info"></i>
      <div>
        <h1 class="h3 mb-0">Kniha jízd</h1>
        <?php if ($filterNazev): ?>
          <p class="text-muted mb-0 small"><?= h($filterNazev) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-primary" href="formular.php<?= $filterVozidlo ? '?id_vozidla=' . $filterVozidlo : '' ?>">
        <i class="bi bi-plus-lg me-1"></i>Nová jízda
      </a>
      <a class="btn btn-outline-secondary" href="../vozidla/seznam.php">
        <i class="bi bi-car-front me-1"></i>Vozidla
      </a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="bi bi-check-circle me-2"></i><?= h($_SESSION['flash_success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="bi bi-exclamation-triangle me-2"></i><?= h($_SESSION['flash_error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>

  <!-- Filtr -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6">
          <label for="vozidlo_id" class="form-label fw-semibold"><i class="bi bi-funnel me-1"></i>Vozidlo</label>
          <select name="vozidlo_id" id="vozidlo_id" class="form-select">
            <option value="">— všechna vozidla —</option>
            <?php foreach ($vozidla as $v): ?>
              <option value="<?= $v['id'] ?>" <?= $filterVozidlo == $v['id'] ? 'selected' : '' ?>>
                <?= h($v['znacka_model']) ?> (<?= h($v['spz']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrovat</button>
          <?php if ($filterVozidlo): ?>
            <a href="seznam.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Zrušit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if (empty($jizdy)): ?>
    <div class="text-center py-5">
      <i class="bi bi-signpost-2 text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádné jízdy</h4>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Vozidlo</th>
              <th>Řidič</th>
              <th>Start</th>
              <th>Konec</th>
              <th>Trasa</th>
              <th class="text-end">km</th>
              <th>Poznámka</th>
              <th>Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jizdy as $j):
              $isOpen = empty($j['datum_konec']);
              $km = (!$isOpen && $j['tachometr_konec'] && $j['tachometr_start'])
                  ? ((int)$j['tachometr_konec'] - (int)$j['tachometr_start'])
                  : null;
            ?>
              <tr class="<?= $isOpen ? 'table-warning' : '' ?>">
                <td>
                  <strong><?= h($j['znacka_model']) ?></strong><br>
                  <small class="text-muted"><?= h($j['spz']) ?></small>
                </td>
                <td><?= h($j['ridic_jmeno'] ?? '—') ?></td>
                <td><small><?= $j['datum_start'] ? date('d.m.Y H:i', strtotime($j['datum_start'])) : '—' ?></small></td>
                <td>
                  <?php if ($isOpen): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-geo-alt-fill me-1"></i>Probíhá</span>
                  <?php else: ?>
                    <small><?= date('d.m.Y H:i', strtotime($j['datum_konec'])) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <small>
                    <?= h($j['poloha_start'] ?? '') ?>
                    <?php if (!empty($j['poloha_start']) && !empty($j['poloha_konec'])): ?>
                      <i class="bi bi-arrow-right mx-1"></i>
                    <?php endif; ?>
                    <?= h($j['poloha_konec'] ?? '') ?>
                  </small>
                </td>
                <td class="text-end">
                  <?php if ($km !== null): ?>
                    <strong><?= number_format($km, 0, ',', ' ') ?></strong>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><small><?= h($j['poznamka'] ?? '') ?></small></td>
                <td>
                  <form method="POST" action="smazat.php" class="d-inline"
                        data-confirm="Opravdu smazat tuto jízdu?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $j['id'] ?>">
                    <input type="hidden" name="vozidlo_id" value="<?= $filterVozidlo ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>


</body>
</html>
