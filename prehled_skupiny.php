<?php
// prehled_skupiny.php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php', true, 303);
    exit;
}
require_once __DIR__ . '/db.php';

// 1) Načíst hash skupiny z GET
$group_hash = isset($_GET['hash']) ? $_GET['hash'] : '';
if ($group_hash === '') {
    die("Skupina není určena.");
}

// 2) Najít skupinu podle hash
$stmtG = $pdo->prepare("SELECT id, nazev FROM skupiny WHERE hash = ?");
$stmtG->execute([$group_hash]);
$group = $stmtG->fetch(PDO::FETCH_ASSOC);
if (!$group) {
    die("Skupina nenalezena.");
}
$group_id   = $group['id'];
$group_name = $group['nazev'];

// 3) Výběr období
$periods = [
    1 => '1 měsíc',
    2 => '2 měsíce',
    6 => '6 měsíců',
];
$period = isset($_GET['period']) && isset($periods[(int)$_GET['period']])
        ? (int)$_GET['period']
        : 1;
$since_date = date('Y-m-d', strtotime("-{$period} months"));

// 4) Načíst tréninky dané skupiny a období
$stmt = $pdo->prepare("
    SELECT 
      t.id,
      t.datum,
      t.napln,
      t.obrazky
    FROM trenink_skupina ts
    JOIN treninky t ON t.id = ts.trenink_id
    WHERE ts.skupina_id = :gid
      AND t.datum >= :since_date
    ORDER BY t.datum DESC
");
$stmt->execute([
    ':gid'        => $group_id,
    ':since_date' => $since_date,
]);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <title>Tréninky skupiny “<?= htmlspecialchars($group_name) ?>”</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <?php appUiAssets(); ?>
  <style>
    .thumbnail { height: 80px; margin: 2px; object-fit: cover; }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5">
    <h1 class="mb-4">Tréninky skupiny “<?= htmlspecialchars($group_name) ?>”</h1>

    <!-- Filtrační formulář -->
    <form method="GET" class="row g-3 mb-4 align-items-end">
      <input type="hidden" name="hash" value="<?= htmlspecialchars($group_hash) ?>">
      <div class="col-md-6">
        <label for="period" class="form-label">Období:</label>
        <select name="period" id="period" class="form-select">
          <?php foreach ($periods as $m => $label): ?>
            <option value="<?= $m ?>" <?= $period === $m ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 text-end">
        <button type="submit" class="btn btn-primary w-100">Filtrovat</button>
      </div>
    </form>

    <?php if (empty($treninky)): ?>
      <div class="alert alert-info">V tomto období nejsou žádné tréninky.</div>
    <?php else: ?>
      <?php foreach ($treninky as $t): 
        // připrav miniatury
        $thumbs = [];
        if (!empty($t['obrazky'])) {
          foreach (explode(',', $t['obrazky']) as $fn) {
            $fn = trim($fn);
            $path = __DIR__ . '/nahrane_obrazky/' . $fn;
            if ($fn && file_exists($path)) {
              $thumbs[] = $fn;
            }
          }
        }
      ?>
      <div class="card mb-4">
        <div class="card-header">
          <strong><?= htmlspecialchars($t['datum']) ?></strong>
        </div>
        <div class="card-body">
          <!-- Náplň tréninku -->
          <p><?= nl2br(htmlspecialchars($t['napln'])) ?></p>
          <!-- Fotografie -->
          <?php if ($thumbs): ?>
            <div class="mb-3">
              <?php foreach ($thumbs as $img): ?>
                <a href="nahrane_obrazky/<?= htmlspecialchars($img) ?>" target="_blank">
                  <img src="nahrane_obrazky/<?= htmlspecialchars($img) ?>" class="thumbnail" alt="Foto">
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
