<?php
// prehled_podskupin.php
require_once __DIR__ . '/db.php';

// Podskupina lze identifikovat buď hash nebo id
$hash = $_GET['hash'] ?? null;
$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($hash) {
    $stmt = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE hash = :hash');
    $stmt->execute([':hash' => $hash]);
    $ps = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($id) {
    $stmt = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $ps = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    die('<p>Podskupina není určena.</p>');
}

if (!$ps) {
    die('<p>Podskupina nenalezena.</p>');
}

$podskupinaId   = $ps['id'];
$podskupinaNazev = htmlspecialchars($ps['nazev'], ENT_QUOTES, 'UTF-8');

// Filtr intervalu
$allowed = [1,2,6];
$months  = isset($_GET['months']) && in_array((int)$_GET['months'], $allowed)
           ? (int)$_GET['months'] : 1;
$dateFrom = date('Y-m-d', strtotime("-{$months} months"));

// Překlad dnů
$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

// Načtení tréninků a závodů pro podskupinu
// Tréninky
$stmtT = $pdo->prepare(
    'SELECT t.id, t.datum, t.napln, t.poznamka,
            GROUP_CONCAT(DISTINCT tr.jmeno SEPARATOR ", ") AS trenere
       FROM treninky t
       JOIN trenink_podskupina tp ON t.id = tp.trenink_id
       LEFT JOIN trenink_trener tt ON t.id = tt.trenink_id
       LEFT JOIN treneri tr ON tt.trener_id = tr.id
      WHERE tp.podskupina_id = :pid
        AND t.datum >= :df
      GROUP BY t.id ORDER BY t.datum DESC'
);
$stmtT->execute([':pid'=>$podskupinaId, ':df'=>$dateFrom]);$treninky = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// Závody
$stmtZ = $pdo->prepare(
    'SELECT z.id, z.datum, z.popis AS napln, z.poznamka,
            GROUP_CONCAT(DISTINCT tr.jmeno SEPARATOR ", ") AS trenere
       FROM zavody z
       JOIN zavod_podskupina zp ON z.id = zp.zavod_id
       LEFT JOIN zavod_trener zt ON z.id = zt.zavod_id
       LEFT JOIN treneri tr ON zt.trener_id = tr.id
      WHERE zp.podskupina_id = :pid
        AND z.datum >= :df
      GROUP BY z.id ORDER BY z.datum DESC'
);
$stmtZ->execute([':pid'=>$podskupinaId, ':df'=>$dateFrom]);$zavody = $stmtZ->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Přehled podskupiny: <?= $podskupinaNazev ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php // include 'hlavicka.php'; ?>
<div class="container mt-5">
  <h1>Přehled podskupiny: <?= $podskupinaNazev ?></h1>
  <div class="mb-4">
    <span>Interval: </span>
    <?php foreach ($allowed as $m): ?>
      <?php $active = ($m === $months) ? 'fw-bold' : ''; ?>
      <a href="?<?= $hash ? 'hash='.urlencode($hash) : 'id='.$podskupinaId ?>&months=<?= $m ?>" class="me-2 <?= $active ?>">
        <?= $m ?> měsíc<?= $m>1?'ů':'' ?>
      </a>
    <?php endforeach; ?>
  </div>

  <h2>Tréninky</h2>
  <?php if (empty($treninky)): ?>
    <div class="alert alert-info">Žádné tréninky za posledních <?= $months ?> měsíců.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead class="table-dark">
        <tr><th>Datum</th><th>Den</th><th>Náplň</th><th>Poznámka</th><th>Trenéři</th></tr>
      </thead>
      <tbody>
      <?php foreach ($treninky as $t):
        $day = $czDays[(new DateTime($t['datum']))->format('l')];
      ?>
        <tr>
          <td><?= htmlspecialchars($t['datum'],ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($day,ENT_QUOTES) ?></td>
          <td><?= nl2br(htmlspecialchars($t['napln'],ENT_QUOTES)) ?></td>
          <td><?= nl2br(htmlspecialchars($t['poznamka'],ENT_QUOTES)) ?></td>
          <td><?= htmlspecialchars($t['trenere'],ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h2>Závody</h2>
  <?php if (empty($zavody)): ?>
    <div class="alert alert-info">Žádné závody za posledních <?= $months ?> měsíců.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead class="table-dark">
        <tr><th>Datum</th><th>Den</th><th>Popis</th><th>Poznámka</th><th>Trenéři</th></tr>
      </thead>
      <tbody>
      <?php foreach ($zavody as $z):
        $day = $czDays[(new DateTime($z['datum']))->format('l')];
      ?>
        <tr>
          <td><?= htmlspecialchars($z['datum'],ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($day,ENT_QUOTES) ?></td>
          <td><?= nl2br(htmlspecialchars($z['napln'],ENT_QUOTES)) ?></td>
          <td><?= nl2br(htmlspecialchars($z['poznamka'],ENT_QUOTES)) ?></td>
          <td><?= htmlspecialchars($z['trenere'],ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
