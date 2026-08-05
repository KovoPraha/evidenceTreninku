<?php
require_once 'db.php';

// Načti seznam trenérů a skupin
$treneri = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
$skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY nazev")->fetchAll(PDO::FETCH_ASSOC);

// Načti parametry filtru
$trener_id = $_GET['trener_id'] ?? '';
$skupina_id = $_GET['skupina_id'] ?? '';
$mesic = $_GET['mesic'] ?? '';

// Sestavení podmínky
$podminky = [];
$params = [];

if ($trener_id) {
    $podminky[] = 'tt.trener_id = :trener_id';
    $params[':trener_id'] = $trener_id;
}
if ($mesic) {
    $podminky[] = "DATE_FORMAT(t.datum, '%Y-%m') = :mesic";
    $params[':mesic'] = date('Y-m', strtotime($mesic));
}
if ($skupina_id) {
    $podminky[] = 'ts.skupina_id = :skupina_id';
    $params[':skupina_id'] = $skupina_id;
}

$whereSql = $podminky ? 'WHERE ' . implode(' AND ', $podminky) : '';

$sql = "SELECT t.*,
               GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trener,
               GROUP_CONCAT(DISTINCT s.nazev ORDER BY s.nazev SEPARATOR ', ') AS skupiny,
               COUNT(DISTINCT tsv.sportovec_id) AS pocet_sportovcu
        FROM treninky t
        LEFT JOIN trenink_trener tt ON tt.trenink_id = t.id
        LEFT JOIN treneri tr ON tr.id = tt.trener_id
        LEFT JOIN trenink_skupina ts ON ts.trenink_id = t.id
        LEFT JOIN skupiny s ON s.id = ts.skupina_id
        LEFT JOIN trenink_sportovec tsv ON tsv.trenink_id = t.id
        $whereSql
        GROUP BY t.id
        ORDER BY t.datum DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Veřejný přehled tréninků</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php appUiAssets(); ?>
    <style>
        .thumbnail {
            height: 50px;
            margin-right: 5px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h1 class="mb-4">Veřejný přehled tréninků</h1>

    <form class="card p-4 shadow mb-4" method="GET">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Trenér:</label>
                <select name="trener_id" class="form-select">
                    <option value="">-- Všichni --</option>
                    <?php foreach ($treneri as $tr): ?>
                        <option value="<?= $tr['id'] ?>" <?= $tr['id'] == $trener_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tr['jmeno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Skupina:</label>
                <select name="skupina_id" class="form-select">
                    <option value="">-- Všechny --</option>
                    <?php foreach ($skupiny as $sk): ?>
                        <option value="<?= $sk['id'] ?>" <?= $sk['id'] == $skupina_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sk['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Měsíc:</label>
                <input type="month" name="mesic" class="form-control" value="<?= htmlspecialchars($mesic) ?>">
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
        </div>
    </form>

    <?php if (count($treninky) === 0): ?>
        <div class="alert alert-warning">Žádné tréninky nebyly nalezeny.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Datum</th>
                        <th>Trenér</th>
                        <th>Skupiny</th>
                        <th>Náplň</th>
                        <th>Poznámka</th>
                        <th>Počet sportovců</th>
                        <th>Délka (hod)</th>
                        <th>Obrázky</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treninky as $t): 
                        $pocetSportovcu = (int)$t['pocet_sportovcu'];
                        $obrazky = array_filter(explode(',', $t['obrazky']));
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($t['datum']) ?></td>
                            <td><?= htmlspecialchars($t['trener']) ?></td>
                            <td><?= htmlspecialchars($t['skupiny']) ?></td>
                            <td><?= nl2br(htmlspecialchars($t['napln'])) ?></td>
                            <td><?= nl2br(htmlspecialchars($t['poznamka'])) ?></td>
                            <td><?= $pocetSportovcu ?></td>
                            <td><?= number_format($t['delka'], 2, ',', ' ') ?></td>
                            <td>
                                <?php foreach ($obrazky as $obrazek): ?>
                                    <a href="nahrane_obrazky/<?= htmlspecialchars($obrazek) ?>" target="_blank">
                                        <img src="nahrane_obrazky/<?= htmlspecialchars($obrazek) ?>" class="thumbnail">
                                    </a>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
