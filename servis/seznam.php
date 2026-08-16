<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('servis')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$vozidlo_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM ucto_vozidla WHERE id = ?");
$stmt->execute([$vozidlo_id]);
$vozidlo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vozidlo) {
    $_SESSION['flash_error'] = 'Vozidlo nenalezeno.';
    header('Location: ../vozidla/seznam.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, t.jmeno AS trener_jmeno
    FROM ucto_servis s
    LEFT JOIN treneri t ON s.vytvoril_id = t.id
    WHERE s.vozidlo_id = ?
    ORDER BY s.provedeno_dne DESC
");
$stmt->execute([$vozidlo_id]);
$zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

function stavKontroly(?string $datum): array {
    if (!$datum) return ['text' => '—', 'class' => 'text-muted'];
    $dny = (int)((strtotime($datum) - strtotime('today')) / 86400);
    $formatted = date('d.m.Y', strtotime($datum));
    if ($dny < 0)   return ['text' => $formatted . ' (prošlá!)', 'class' => 'text-danger fw-bold'];
    if ($dny <= 30)  return ['text' => $formatted . ' (za ' . $dny . ' dní)', 'class' => 'text-warning fw-bold'];
    return ['text' => $formatted, 'class' => 'text-success'];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servis – <?= h($vozidlo['znacka_model']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-wrench fs-2 text-secondary"></i>
      <div>
        <h1 class="h3 mb-0">Servis vozidla</h1>
        <p class="text-muted mb-0 small">
          <?= h($vozidlo['znacka_model']) ?> <span class="badge bg-dark"><?= h($vozidlo['spz']) ?></span>
        </p>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="formular.php?vozidlo_id=<?= $vozidlo_id ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Přidat záznam
      </a>
      <a href="../vozidla/seznam.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Vozidla
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

  <?php if (empty($zaznamy)): ?>
    <div class="text-center py-5">
      <i class="bi bi-wrench text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádné servisní záznamy</h4>
      <p class="text-muted">Přidejte první servisní záznam pro toto vozidlo.</p>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Provedeno</th>
              <th>Popis</th>
              <th>Další kontrola</th>
              <th>Dokument</th>
              <th>Zadal</th>
              <th>Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($zaznamy as $z):
              $kontrola = stavKontroly($z['planovana_kontrola']);
            ?>
              <tr>
                <td>
                  <strong><?= date('d.m.Y', strtotime($z['provedeno_dne'])) ?></strong>
                </td>
                <td><?= nl2br(h($z['popis'])) ?></td>
                <td>
                  <span class="<?= $kontrola['class'] ?>"><?= $kontrola['text'] ?></span>
                </td>
                <td>
                  <?php if ($z['dokument']): ?>
                    <a href="../<?= h($z['dokument']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-paperclip me-1"></i>Otevřít
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><small><?= h($z['trener_jmeno'] ?? '—') ?></small></td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="formular.php?id=<?= $z['id'] ?>&vozidlo_id=<?= $vozidlo_id ?>"
                       class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="smazat.php" class="m-0"
                          data-confirm="Opravdu smazat tento servisní záznam?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $z['id'] ?>">
                      <input type="hidden" name="vozidlo_id" value="<?= $vozidlo_id ?>">
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


</body>
</html>
