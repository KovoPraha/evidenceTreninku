<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/funkce.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('udalosti')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);

// Načti údaje o události
$stmt = $pdo->prepare("SELECT * FROM ucto_udalosti WHERE id = ?");
$stmt->execute([$id]);
$udalost = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$udalost) {
    $_SESSION['flash_error'] = 'Událost nenalezena.';
    header('Location: seznam.php');
    exit;
}

// FIX: Správný název sloupce je "platba" (ne "platba_zpusob")
$stmt = $pdo->prepare("SELECT SUM(castka) FROM ucto_uctenky WHERE udalost_id = ? AND platba = 'hotove_tym'");
$stmt->execute([$id]);
$soucet_hotove = round((float)$stmt->fetchColumn(), 2);

// Celkový součet všech účtenek
$stmt = $pdo->prepare("SELECT SUM(castka) FROM ucto_uctenky WHERE udalost_id = ?");
$stmt->execute([$id]);
$soucet_vsech = round((float)$stmt->fetchColumn(), 2);

// Jednotlivé účtenky k události
$stmt = $pdo->prepare("
    SELECT uc.*, t.jmeno AS trener_jmeno
    FROM ucto_uctenky uc
    LEFT JOIN treneri t ON uc.nahrano_kym = t.id
    WHERE uc.udalost_id = ?
    ORDER BY uc.datum DESC
");
$stmt->execute([$id]);
$uctenky = $stmt->fetchAll(PDO::FETCH_ASSOC);

$zalohova_castka = round((float)($udalost['zalohova_castka'] ?? 0), 2);
$rozdil = $zalohova_castka - $soucet_hotove;

$platba_map = [
    'kartou_tym'       => 'Kartou – tým',
    'hotove_tym'       => 'Hotově – tým',
    'vlastni_karta'    => 'Vlastní kartou',
    'vlastni_hotovost' => 'Vlastní hotovost',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vyúčtování – <?= h($udalost['nazev']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-calculator fs-2 text-secondary"></i>
    <div>
      <h1 class="h3 mb-0">Vyúčtování události</h1>
      <p class="text-muted mb-0 small"><?= h($udalost['nazev']) ?></p>
    </div>
  </div>

  <!-- Finanční přehled -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-wallet2 me-1"></i>Záloha</div>
          <div class="fs-4 fw-bold"><?= number_format($zalohova_castka, 2, ',', ' ') ?> Kč</div>
          <?php if ($udalost['zalohu_predal']): ?>
            <small class="text-muted">Svěřena: <?= h($udalost['zalohu_predal']) ?></small>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-cash-stack me-1"></i>Hotově – tým</div>
          <div class="fs-4 fw-bold"><?= number_format($soucet_hotove, 2, ',', ' ') ?> Kč</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-arrow-left-right me-1"></i>Rozdíl</div>
          <div class="fs-4 fw-bold <?= $rozdil >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= number_format($rozdil, 2, ',', ' ') ?> Kč
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="text-muted small mb-1"><i class="bi bi-receipt me-1"></i>Celkem účtenky</div>
          <div class="fs-4 fw-bold"><?= number_format($soucet_vsech, 2, ',', ' ') ?> Kč</div>
          <small class="text-muted"><?= count($uctenky) ?> účtenek</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Stav a akce -->
  <div class="card shadow-sm mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <span class="fw-semibold">Stav události:</span>
        <?php if (($udalost['stav'] ?? 'otevrena') === 'uzavrena'): ?>
          <span class="badge bg-secondary ms-2">Uzavřená</span>
        <?php else: ?>
          <span class="badge bg-success ms-2">Otevřená</span>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-2">
        <?php if (($udalost['stav'] ?? 'otevrena') === 'otevrena'): ?>
          <form method="POST" action="uzavrit.php" data-confirm="Opravdu chcete událost uzavřít?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-lock me-1"></i>Uzavřít událost
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-info mb-0 py-2 px-3">
            <i class="bi bi-info-circle me-1"></i>Událost je uzavřená.
          </div>
        <?php endif; ?>
        <a href="seznam.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Zpět
        </a>
      </div>
    </div>
  </div>

  <!-- Seznam účtenek -->
  <?php if (!empty($uctenky)): ?>
  <div class="card shadow-sm">
    <div class="card-header">
      <i class="bi bi-receipt me-2"></i>Účtenky k této události
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Datum</th>
            <th>Částka</th>
            <th>Platba</th>
            <th>Kategorie</th>
            <th>Poznámka</th>
            <th>Zadal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($uctenky as $uc): ?>
            <tr>
              <td><?= date('d.m.Y H:i', strtotime($uc['datum'])) ?></td>
              <td class="text-end fw-semibold"><?= number_format($uc['castka'], 2, ',', ' ') ?> Kč</td>
              <td><?= h($platba_map[$uc['platba']] ?? $uc['platba']) ?></td>
              <td><?= h($uc['kategorie'] ?? '') ?></td>
              <td><small><?= h($uc['poznamka'] ?? '') ?></small></td>
              <td><small><?= h($uc['trener_jmeno'] ?? $uc['nahrano_jmenem'] ?? '—') ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
    <div class="text-center py-4 text-muted">
      <i class="bi bi-receipt" style="font-size: 2rem;"></i>
      <p class="mt-2">K této události nejsou žádné účtenky.</p>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle uzavrit.php response (returns JSON but form submits normally)
document.querySelector('form[action="uzavrit.php"]')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('uzavrit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                window.location.reload();
            } else {
                alert(data.message || 'Chyba při uzavírání.');
            }
        })
        .catch(() => alert('Chyba komunikace se serverem.'));
});
</script>
</body>
</html>
