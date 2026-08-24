<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('servis')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$vozidlo_id = (int)($_GET['vozidlo_id'] ?? 0);

$zaznam = [
    'popis' => '', 'provedeno_dne' => date('Y-m-d'),
    'planovana_kontrola' => '', 'dokument' => ''
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ucto_servis WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $zaznam = $row;
        $vozidlo_id = $row['vozidlo_id'];
    }
}

$stmt = $pdo->prepare("SELECT * FROM ucto_vozidla WHERE id = ?");
$stmt->execute([$vozidlo_id]);
$vozidlo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vozidlo) {
    $_SESSION['flash_error'] = 'Vozidlo nenalezeno.';
    header('Location: ../vozidla/seznam.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servisní záznam – <?= h($vozidlo['znacka_model']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-wrench fs-2 text-secondary"></i>
    <div>
      <h1 class="h3 mb-0"><?= $id ? 'Úprava záznamu' : 'Nový servisní záznam' ?></h1>
      <p class="text-muted mb-0 small">
        <?= h($vozidlo['znacka_model']) ?> <span class="badge bg-dark"><?= h($vozidlo['spz']) ?></span>
      </p>
    </div>
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
      <form method="POST" action="uloz.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="vozidlo_id" value="<?= $vozidlo_id ?>">

        <div class="mb-3">
          <label for="popis" class="form-label fw-semibold">
            <i class="bi bi-card-text me-1"></i>Popis provedených prací
          </label>
          <textarea name="popis" id="popis" class="form-control" rows="4" required
                    placeholder="Popište provedené práce..."><?= h($zaznam['popis']) ?></textarea>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="provedeno_dne" class="form-label fw-semibold">
              <i class="bi bi-calendar-check me-1"></i>Datum provedení
            </label>
            <input type="date" name="provedeno_dne" id="provedeno_dne" class="form-control"
                   value="<?= h($zaznam['provedeno_dne']) ?>" required>
          </div>
          <div class="col-md-6">
            <label for="planovana_kontrola" class="form-label fw-semibold">
              <i class="bi bi-calendar-event me-1"></i>Plánovaný termín další kontroly
            </label>
            <input type="date" name="planovana_kontrola" id="planovana_kontrola" class="form-control"
                   value="<?= h($zaznam['planovana_kontrola']) ?>">
          </div>
        </div>

        <div class="mt-3">
          <label for="dokument" class="form-label fw-semibold">
            <i class="bi bi-paperclip me-1"></i>Dokument (faktura, protokol)
          </label>
          <?php if (!empty($zaznam['dokument'])): ?>
            <div class="mb-2">
              <a href="../<?= h($zaznam['dokument']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-file-earmark me-1"></i>Stávající dokument
              </a>
            </div>
          <?php endif; ?>
          <input type="file" name="dokument" id="dokument" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png">
          <div class="form-text">Povolené formáty: PDF, JPG, PNG</div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Aktualizovat' : 'Uložit' ?>
          </button>
          <a href="seznam.php?id=<?= $vozidlo_id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Zpět
          </a>
        </div>
      </form>
    </div>
  </div>
</div>


</body>
</html>
