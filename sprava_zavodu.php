<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sprava_zavodu')) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';

// Filtry
$filterGroup = isset($_GET['skupina_id']) ? (int)$_GET['skupina_id'] : '';
$filterSub   = isset($_GET['podskupina_id']) ? (int)$_GET['podskupina_id'] : '';
$filterTr    = isset($_GET['trenere']) ? (array)$_GET['trenere'] : [];

// Načtení možností pro filtry
$groups = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi')->fetchAll(PDO::FETCH_ASSOC);
$subs = [];
if ($filterGroup) {
    $stmt = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi');
    $stmt->execute([$filterGroup]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$trainers = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);

// Hlavní dotaz pro závody
$kategorieMeta = [
    'silnice' => ['label'=>'Silnice', 'color'=>'success', 'icon'=>'bi-bicycle'],
    'draha'   => ['label'=>'Dráha',   'color'=>'primary', 'icon'=>'bi-stopwatch'],
    'mtb'     => ['label'=>'MTB',     'color'=>'warning',  'icon'=>'bi-tree'],
];

$sql = "
SELECT
  z.id,
  z.datum,
  z.kategorie,
  z.popis,
  z.poznamka,
  GROUP_CONCAT(DISTINCT tr.jmeno SEPARATOR ', ') AS trenere,
  COUNT(DISTINCT zsp.sportovec_id)   AS pocet_ucastniku,
  COUNT(DISTINCT zi.id)              AS pocet_importu
FROM zavody z
JOIN zavod_trener        zt  ON z.id = zt.zavod_id
JOIN treneri             tr  ON zt.trener_id = tr.id
LEFT JOIN zavod_skupina      zsg  ON z.id = zsg.zavod_id
LEFT JOIN zavod_podskupina   zspg ON z.id = zspg.zavod_id
LEFT JOIN zavod_sportovec    zsp  ON z.id = zsp.zavod_id
LEFT JOIN zavod_import       zi   ON z.id = zi.zavod_id
WHERE 1=1";
$params = [];
if ($filterGroup) {
    $sql      .= " AND zsg.skupina_id = ?";
    $params[]  = $filterGroup;
}
if ($filterSub) {
    $sql      .= " AND zspg.podskupina_id = ?";
    $params[]  = $filterSub;
}
if (!empty($filterTr)) {
    $ph        = implode(',', array_fill(0, count($filterTr), '?'));
    $sql      .= " AND zt.trener_id IN ($ph)";
    $params    = array_merge($params, $filterTr);
}
$sql .= "
GROUP BY z.id
ORDER BY z.datum DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$zavody = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Překlad dnů
$daysCz = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Správa závodů</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .thumbnail { height: 60px; width: auto; margin-right:5px; object-fit:cover; border:1px solid #ddd; }
  </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-5">
  <h1>Správa závodů</h1>

  <form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">Skupina</label>
      <select id="skupina" name="skupina_id" class="form-select">
        <option value="">-- všechny --</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= $g['id'] ?>" <?= $filterGroup==$g['id']?'selected':''?>><?= htmlspecialchars($g['nazev']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Podskupina</label>
      <select id="podskupina" name="podskupina_id" class="form-select">
        <option value="">-- všechny --</option>
        <?php foreach ($subs as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterSub==$s['id']?'selected':''?>><?= htmlspecialchars($s['nazev']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Trenéři</label>
      <select name="trenere[]" multiple class="form-select">
        <?php foreach ($trainers as $tr): ?>
          <option value="<?= $tr['id'] ?>" <?= in_array($tr['id'],$filterTr)?'selected':''?>><?= htmlspecialchars($tr['jmeno']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 align-self-end">
      <button type="submit" class="btn btn-primary w-100">Filtrovat</button>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Datum</th>
          <th>Den</th>
          <th>Kat.</th>
          <th>Popis</th>
          <th>Trenéři</th>
          <th>Účastníci</th>
          <th>Importů</th>
          <th>Fotografie</th>
          <th>Výsledky</th>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($zavody as $z):
          $day = $daysCz[(new DateTime($z['datum']))->format('l')];
          // Načtení fotek
          $pStmt = $pdo->prepare('SELECT soubor FROM zavod_fotka WHERE zavod_id = ?');
          $pStmt->execute([$z['id']]);
          $photos = $pStmt->fetchAll(PDO::FETCH_COLUMN);
          // Načtení importů
          $iStmt = $pdo->prepare('SELECT id, soubor FROM zavod_import WHERE zavod_id = ?');
          $iStmt->execute([$z['id']]);
          $imports = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php
          $kat   = $z['kategorie'] ?? 'silnice';
          $kmeta = $kategorieMeta[$kat] ?? $kategorieMeta['silnice'];
        ?>
        <tr>
          <td class="text-nowrap"><?= htmlspecialchars(date('d.m.Y', strtotime($z['datum']))) ?></td>
          <td><?= htmlspecialchars($day) ?></td>
          <td>
            <span class="badge bg-<?= $kmeta['color'] ?>">
              <i class="bi <?= $kmeta['icon'] ?>"></i>
            </span>
          </td>
          <td><?= nl2br(htmlspecialchars($z['popis'])) ?></td>
          <td><?= htmlspecialchars($z['trenere']) ?></td>
          <td class="text-center"><?= (int)$z['pocet_ucastniku'] ?></td>
          <td class="text-center"><?= (int)$z['pocet_importu'] ?></td>
          <td>
            <?php foreach ($photos as $img): ?>
              <img src="nahrane_zavody/<?= htmlspecialchars($img) ?>" class="thumbnail" alt="Foto">
            <?php endforeach; ?>
          </td>
          <td>
            <?php foreach ($imports as $imp): ?>
              <a href="download_import.php?id=<?= $imp['id'] ?>" class="btn btn-sm btn-outline-secondary mb-1">
                <i class="bi bi-download me-1"></i><?= htmlspecialchars(pathinfo($imp['soubor'], PATHINFO_EXTENSION)) ?>
              </a><br>
            <?php endforeach; ?>
          </td>
          <td class="text-nowrap">
            <a href="zavod_detail.php?id=<?= (int)$z['id'] ?>" class="btn btn-sm btn-outline-warning" title="Detail" aria-label="Detail závodu">
              <i class="bi bi-eye"></i>
            </a>
            <a href="edit_zavod_form.php?id=<?= (int)$z['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1" title="Upravit" aria-label="Upravit závod">
              <i class="bi bi-pencil"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


<script>
  document.getElementById('skupina').addEventListener('change', function() {
    fetch('nacti_podskupiny.php?skupina_id=' + this.value)
      .then(r => r.json())
      .then(data => {
        let opts = '<option value="">-- všechny --</option>';
        data.forEach(ps => opts += `<option value="${ps.id}">${ps.nazev}</option>`);
        document.getElementById('podskupina').innerHTML = opts;
      });
  });
</script>
</body>
</html>
