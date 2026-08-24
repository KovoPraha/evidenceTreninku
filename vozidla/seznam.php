<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('vozidla')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$vozidla = $pdo->query("
    SELECT v.*,
           (SELECT COUNT(*) FROM ucto_jizdy j WHERE j.vozidlo_id = v.id) AS pocet_jizd,
           (SELECT COUNT(*) FROM ucto_servis s WHERE s.vozidlo_id = v.id) AS pocet_servisu,
           (SELECT COUNT(*) FROM ucto_jizdy j WHERE j.vozidlo_id = v.id AND j.datum_konec IS NULL) AS otevrene_jizdy
    FROM ucto_vozidla v
    ORDER BY v.znacka_model
")->fetchAll(PDO::FETCH_ASSOC);

function stavDatumu(?string $datum): array {
    if (!$datum) return ['text' => 'Nenastaveno', 'class' => 'text-muted', 'badge' => 'bg-secondary'];
    $dny = (int)((strtotime($datum) - strtotime('today')) / 86400);
    if ($dny < 0)   return ['text' => 'Prošlé!', 'class' => 'text-danger fw-bold', 'badge' => 'bg-danger'];
    if ($dny <= 30)  return ['text' => 'Brzy (' . $dny . ' dní)', 'class' => 'text-warning fw-bold', 'badge' => 'bg-warning text-dark'];
    return ['text' => date('d.m.Y', strtotime($datum)), 'class' => 'text-success', 'badge' => 'bg-success'];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vozidla – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
  <style>
    .vehicle-card { transition: transform 0.15s, box-shadow 0.15s; border-left: 4px solid #0d6efd; }
    .vehicle-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12) !important; }
  </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-car-front fs-2 text-primary"></i>
      <div>
        <h1 class="h3 mb-0">Vozidla</h1>
        <p class="text-muted mb-0 small"><?= count($vozidla) ?> vozidel v evidenci</p>
      </div>
    </div>
    <a class="btn btn-primary" href="formular.php">
      <i class="bi bi-plus-lg me-1"></i>Nové vozidlo
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

  <?php if (empty($vozidla)): ?>
    <div class="text-center py-5">
      <i class="bi bi-car-front text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádná vozidla</h4>
      <p class="text-muted">Přidejte první vozidlo do evidence.</p>
      <a href="formular.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Přidat vozidlo</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($vozidla as $auto):
        $stk    = stavDatumu($auto['stk_datum']);
        $znamka = stavDatumu($auto['dalnicni_znamka_datum']);
      ?>
        <div class="col-md-6 col-xl-4">
          <div class="card vehicle-card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <h5 class="card-title mb-0"><?= h($auto['znacka_model']) ?></h5>
                  <span class="badge bg-dark mt-1"><?= h($auto['spz']) ?></span>
                </div>
                <?php if ($auto['otevrene_jizdy'] > 0): ?>
                  <span class="badge bg-warning text-dark">
                    <i class="bi bi-geo-alt-fill me-1"></i>Na cestě
                  </span>
                <?php endif; ?>
              </div>

              <div class="row g-2 mt-3 small">
                <?php if ($auto['rok_vyroby']): ?>
                  <div class="col-6">
                    <span class="text-muted">Rok výroby:</span><br>
                    <strong><?= (int)$auto['rok_vyroby'] ?></strong>
                  </div>
                <?php endif; ?>
                <?php if ($auto['palivo']): ?>
                  <div class="col-6">
                    <span class="text-muted">Palivo:</span><br>
                    <strong><?= h($auto['palivo']) ?></strong>
                  </div>
                <?php endif; ?>
                <div class="col-6">
                  <span class="text-muted">STK:</span><br>
                  <span class="<?= $stk['class'] ?>"><?= $stk['text'] ?></span>
                </div>
                <div class="col-6">
                  <span class="text-muted">Dálniční známka:</span><br>
                  <span class="<?= $znamka['class'] ?>"><?= $znamka['text'] ?></span>
                </div>
              </div>

              <div class="d-flex gap-3 mt-3 small text-muted">
                <span><i class="bi bi-signpost-2 me-1"></i><?= (int)$auto['pocet_jizd'] ?> jízd</span>
                <span><i class="bi bi-wrench me-1"></i><?= (int)$auto['pocet_servisu'] ?> servisů</span>
              </div>
            </div>
            <div class="card-footer bg-white border-top-0 d-flex gap-2 flex-wrap">
              <a href="formular.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Upravit
              </a>
              <a href="../jizdy/seznam.php?vozidlo_id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-info">
                <i class="bi bi-signpost-2 me-1"></i>Jízdy
              </a>
              <a href="../servis/seznam.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-wrench me-1"></i>Servis
              </a>
              <form method="POST" action="smazat.php" class="m-0 ms-auto"
                    data-confirm="Opravdu smazat vozidlo <?= h($auto['znacka_model']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>


</body>
</html>
