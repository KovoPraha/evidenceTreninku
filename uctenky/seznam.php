<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filtr dle kategorie
$filtr_kategorie = $_GET['kategorie'] ?? '';

$sql = "
    SELECT u.*,
           v.znacka_model, v.spz,
           e.nazev AS udalost_nazev,
           t.jmeno AS trener_jmeno
    FROM ucto_uctenky u
    LEFT JOIN ucto_vozidla v ON u.vozidlo_id = v.id
    LEFT JOIN ucto_udalosti e ON u.udalost_id = e.id
    LEFT JOIN treneri t ON u.nahrano_kym = t.id
";
$params = [];

if ($filtr_kategorie !== '') {
    $sql .= " WHERE u.kategorie = ?";
    $params[] = $filtr_kategorie;
}

$sql .= " ORDER BY u.datum DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$uctenky = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kategorie_map = [
    'zavodni_oddil'  => 'Závodní oddíl',
    'velodrom_areal' => 'Velodrom-areál',
    'cyklo_krouzek'  => 'Cyklo kroužek',
    'ostatni'        => 'Ostatní',
];

$platba_map = [
    'kartou_tym'       => ['text' => 'Kartou – tým',       'class' => 'bg-primary'],
    'hotove_tym'       => ['text' => 'Hotově – tým',       'class' => 'bg-success'],
    'vlastni_karta'    => ['text' => 'Vlastní kartou',     'class' => 'bg-warning text-dark'],
    'vlastni_hotovost' => ['text' => 'Vlastní hotovost',   'class' => 'bg-secondary'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Účtenky – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-receipt fs-2 text-secondary"></i>
      <h1 class="h3 mb-0">Účtenky</h1>
    </div>
    <a href="formular.php" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>Nová účtenka
    </a>
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
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <form method="GET" class="d-flex align-items-center gap-3">
        <label class="form-label mb-0 fw-semibold small"><i class="bi bi-funnel me-1"></i>Kategorie:</label>
        <select name="kategorie" class="form-select form-select-sm" style="max-width: 220px;" onchange="this.form.submit()">
          <option value="">Všechny</option>
          <?php foreach ($kategorie_map as $k => $v): ?>
            <option value="<?= $k ?>" <?= $filtr_kategorie === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if (empty($uctenky)): ?>
    <div class="text-center py-5">
      <i class="bi bi-receipt text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádné účtenky</h4>
      <p class="text-muted">Přidejte první účtenku.</p>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Datum</th>
              <th>Částka</th>
              <th>Platba</th>
              <th>Kategorie</th>
              <th>Vozidlo</th>
              <th>Událost</th>
              <th>Poznámka</th>
              <th>Doklad</th>
              <th>Zadal</th>
              <th>Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($uctenky as $u):
              $platba_info = $platba_map[$u['platba']] ?? ['text' => h($u['platba']), 'class' => 'bg-light text-dark'];
            ?>
              <tr>
                <td><strong><?= date('d.m.Y H:i', strtotime($u['datum'])) ?></strong></td>
                <td class="text-end fw-semibold"><?= number_format($u['castka'], 2, ',', ' ') ?> Kč</td>
                <td><span class="badge <?= $platba_info['class'] ?>"><?= $platba_info['text'] ?></span></td>
                <td><?= h($kategorie_map[$u['kategorie']] ?? $u['kategorie']) ?></td>
                <td>
                  <?php if ($u['vozidlo_id']): ?>
                    <small><?= h($u['znacka_model']) ?> <span class="badge bg-dark"><?= h($u['spz']) ?></span></small>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= $u['udalost_id'] ? h($u['udalost_nazev']) : '<span class="text-muted">—</span>' ?></td>
                <td><small><?= h($u['poznamka']) ?></small></td>
                <td>
                  <?php if (!empty($u['obrazek_path'])): ?>
                    <a href="../<?= h($u['obrazek_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-image me-1"></i>Zobrazit
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><small><?= h($u['trener_jmeno'] ?? $u['nahrano_jmenem'] ?? '—') ?></small></td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="formular.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="smazat.php" class="m-0"
                          data-confirm="Opravdu smazat tuto účtenku?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
