<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../csrf_helper.php';

if (!isset($_SESSION['trener_id']) || !canAccess('udalosti')) {
    header('Location: ../login.php');
    exit;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$stmt = $pdo->query("
    SELECT u.*, t.jmeno AS trener_jmeno,
           (SELECT COUNT(*) FROM ucto_uctenky uc WHERE uc.udalost_id = u.id) AS pocet_uctenek,
           (SELECT COALESCE(SUM(castka), 0) FROM ucto_uctenky uc WHERE uc.udalost_id = u.id) AS celkem_uctenky
    FROM ucto_udalosti u
    LEFT JOIN treneri t ON u.vytvoril_id = t.id
    ORDER BY u.datum_od DESC
");
$udalosti = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typ_map = [
    'zavod'       => ['text' => 'Závod',        'class' => 'bg-danger'],
    'soustredeni' => ['text' => 'Soustředění',   'class' => 'bg-primary'],
    'trenink'     => ['text' => 'Trénink',       'class' => 'bg-success'],
    'jine'        => ['text' => 'Jiné',          'class' => 'bg-secondary'],
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Události – Evidence</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../hlavicka.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-calendar-event fs-2 text-secondary"></i>
      <h1 class="h3 mb-0">Události</h1>
    </div>
    <a href="formular.php" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>Nová událost
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

  <?php if (empty($udalosti)): ?>
    <div class="text-center py-5">
      <i class="bi bi-calendar-event text-muted" style="font-size: 4rem;"></i>
      <h4 class="text-muted mt-3">Žádné události</h4>
      <p class="text-muted">Přidejte první událost.</p>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Název</th>
              <th>Typ</th>
              <th>Od</th>
              <th>Do</th>
              <th>Stav</th>
              <th>Záloha</th>
              <th>Účtenky</th>
              <th>Vytvořil</th>
              <th>Akce</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($udalosti as $u):
              $typ_info = $typ_map[$u['typ']] ?? ['text' => h($u['typ']), 'class' => 'bg-light text-dark'];
              $stav_class = ($u['stav'] ?? 'otevrena') === 'uzavrena' ? 'bg-secondary' : 'bg-success';
              $stav_text  = ($u['stav'] ?? 'otevrena') === 'uzavrena' ? 'Uzavřená' : 'Otevřená';
            ?>
              <tr>
                <td><strong><?= h($u['nazev']) ?></strong></td>
                <td><span class="badge <?= $typ_info['class'] ?>"><?= $typ_info['text'] ?></span></td>
                <td><?= date('d.m.Y H:i', strtotime($u['datum_od'])) ?></td>
                <td><?= date('d.m.Y H:i', strtotime($u['datum_do'])) ?></td>
                <td><span class="badge <?= $stav_class ?>"><?= $stav_text ?></span></td>
                <td class="text-end">
                  <?php if ($u['zalohova_castka']): ?>
                    <?= number_format($u['zalohova_castka'], 0, ',', ' ') ?> Kč
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <small>
                    <?= (int)$u['pocet_uctenek'] ?> ks
                    (<?= number_format($u['celkem_uctenky'], 0, ',', ' ') ?> Kč)
                  </small>
                </td>
                <td><small><?= h($u['trener_jmeno'] ?? '—') ?></small></td>
                <td>
                  <div class="d-flex gap-1">
                    <a href="formular.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Upravit">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <a href="vyuctovat.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-info" title="Vyúčtování">
                      <i class="bi bi-calculator"></i>
                    </a>
                    <form method="POST" action="smazat.php" class="m-0"
                          data-confirm="Opravdu smazat tuto událost?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Smazat">
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
