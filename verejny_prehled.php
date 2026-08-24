<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';

$skupina_id = $_GET['skupina_id'] ?? null;
$trener_id = $_GET['trener_id'] ?? null;
$mesic = $_GET['mesic'] ?? null;

$query = "SELECT t.*, COUNT(DISTINCT tsv.sportovec_id) AS pocet_sportovcu
FROM treninky t
LEFT JOIN trenink_skupina ts ON t.id = ts.trenink_id
LEFT JOIN trenink_trener tt ON tt.trenink_id = t.id
LEFT JOIN trenink_sportovec tsv ON tsv.trenink_id = t.id
WHERE 1=1";
$params = [];

if ($skupina_id) {
    $query .= " AND ts.skupina_id = ?";
    $params[] = $skupina_id;
}
if ($trener_id) {
    $query .= " AND tt.trener_id = ?";
    $params[] = $trener_id;
}
if ($mesic) {
    $query .= " AND DATE_FORMAT(t.datum, '%Y-%m') = ?";
    $params[] = $mesic;
}

$query .= " GROUP BY t.id ORDER BY t.datum DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$treninky = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Veřejný přehled tréninků</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
</head>
<body>
<div class="container mt-5">
    <h1>Veřejný přehled tréninků</h1>
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="skupina_id" class="form-label">Skupina</label>
            <input type="text" name="skupina_id" id="skupina_id" class="form-control" value="<?= htmlspecialchars($skupina_id) ?>">
        </div>
        <div class="col-md-3">
            <label for="trener_id" class="form-label">Trenér ID</label>
            <input type="number" name="trener_id" id="trener_id" class="form-control" value="<?= htmlspecialchars($trener_id) ?>">
        </div>
        <div class="col-md-3">
            <label for="mesic" class="form-label">Měsíc</label>
            <input type="month" name="mesic" id="mesic" class="form-control" value="<?= htmlspecialchars($mesic) ?>">
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
        </div>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr><th>Datum</th><th>Náplň</th><th>Poznámka</th><th>Počet sportovců</th><th>Miniatury</th></tr>
        </thead>
        <tbody>
            <?php foreach ($treninky as $t):
                $pocet = (int)$t['pocet_sportovcu'];
                $obrazky = explode(',', $t['obrazky']);
            ?>
                <tr>
                    <td><?= htmlspecialchars($t['datum']) ?></td>
                    <td><?= htmlspecialchars($t['napln']) ?></td>
                    <td><?= htmlspecialchars($t['poznamka']) ?></td>
                    <td><?= $pocet ?></td>
                    <td>
                        <?php foreach ($obrazky as $ob): ?>
                            <?php if (trim($ob)): ?>
                                <a href="nahrane_obrazky/<?= htmlspecialchars($ob) ?>" target="_blank">
                                    <img src="nahrane_obrazky/<?= htmlspecialchars($ob) ?>" width="80" class="me-1 mb-1">
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
