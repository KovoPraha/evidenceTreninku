<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

// Přihlášený trenér
$trenerId = $_SESSION['trener_id'];

// Načtení seznamu podskupin pro filtr
$podskupinyList = $pdo->query(
    "SELECT id, nazev FROM podskupiny ORDER BY poradi, nazev"
)->fetchAll(PDO::FETCH_ASSOC);

// Zpracování filtru podskupiny přes GET['podskupina_id'] nebo hash
$filterPodId = 'all';
if (!empty($_GET['podskupina_id'])) {
    $filterPodId = $_GET['podskupina_id'];
} elseif (!empty($_GET['hash'])) {
    $stmtHash = $pdo->prepare(
        "SELECT id FROM podskupiny WHERE hash = :hash"
    );
    $stmtHash->execute([':hash'=>$_GET['hash']]);
    $row = $stmtHash->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $filterPodId = $row['id'];
    }
}

// Překlad dnů
$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

// Načtení přehledu skupin/podskupin
$sql = 
    "SELECT s.nazev AS skupina, p.id AS podskupina_id, p.nazev AS podskupina, 
            COUNT(tp.trenink_id) AS pocet_treninku 
     FROM skupiny s
     LEFT JOIN podskupiny p ON p.skupina_id = s.id
     LEFT JOIN trenink_podskupina tp ON tp.podskupina_id = p.id
     LEFT JOIN treninky t ON t.id = tp.trenink_id AND (
         SELECT COUNT(*) FROM trenink_trener tt
         WHERE tt.trenink_id = t.id AND tt.trener_id = :trener_id
     ) > 0
    ";
$params = [':trener_id' => $trenerId];
if ($filterPodId !== 'all' && ctype_digit($filterPodId)) {
    $sql .= " WHERE p.id = :podskupina_id";
    $params[':podskupina_id'] = $filterPodId;
}
$sql .= " GROUP BY s.id, p.id ORDER BY s.poradi, s.nazev, p.poradi, p.nazev";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pokud je vybraná podskupina, načteme detaily tréninků
$detailTreninky = [];
$selectedPodName = '';
if ($filterPodId !== 'all' && ctype_digit($filterPodId)) {
    foreach ($podskupinyList as $ps) {
        if ($ps['id'] == $filterPodId) {
            $selectedPodName = $ps['nazev']; break;
        }
    }
    $stmtD = $pdo->prepare(
        "SELECT t.datum, t.napln, t.poznamka,
                (SELECT GROUP_CONCAT(tr.jmeno SEPARATOR ', ')
                   FROM trenink_trener tt2
                   JOIN treneri tr ON tt2.trener_id = tr.id
                  WHERE tt2.trenink_id = t.id
                ) AS trenere
         FROM treninky t
         JOIN trenink_podskupina tp ON t.id = tp.trenink_id
         WHERE tp.podskupina_id = :podskupina_id
           AND EXISTS(
             SELECT 1 FROM trenink_trener tt
              WHERE tt.trenink_id = t.id AND tt.trener_id = :trener_id
           )
         ORDER BY t.datum DESC"
    );
    $stmtD->execute([':podskupina_id'=>$filterPodId, ':trener_id'=>$trenerId]);
    $detailTreninky = $stmtD->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Moje skupiny</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">
    <h1>Moje skupiny a podskupiny</h1>
    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <label for="podskupina_id" class="form-label">Podskupina:</label>
            <select id="podskupina_id" name="podskupina_id" class="form-select">
                <option value="all">-- všechny podskupiny --</option>
                <?php foreach ($podskupinyList as $ps): ?>
                <option value="<?= $ps['id'] ?>" <?= ($filterPodId == $ps['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ps['nazev']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Filtrovat</button>
        </div>
    </form>
    <?php if (empty($rows)): ?>
        <div class="alert alert-info">Pro zvolenou podskupinu nejsou žádné údaje.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Skupina</th>
                    <th>Podskupina</th>
                    <th>Počet tréninků</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['skupina']) ?></td>
                    <td><?= htmlspecialchars($r['podskupina']) ?></td>
                    <td><?= htmlspecialchars($r['pocet_treninku']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($detailTreninky)): ?>
        <h2 class="mt-5">Detail tréninků pro podskupinu: <?= htmlspecialchars($selectedPodName) ?></h2>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Datum</th>
                    <th>Den</th>
                    <th>Náplň</th>
                    <th>Poznámka</th>
                    <th>Trenéři</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detailTreninky as $dt):
                    $dayEng = (new DateTime($dt['datum']))->format('l');
                    $dayCz = $czDays[$dayEng] ?? $dayEng;
                ?>
                <tr>
                    <td><?= htmlspecialchars($dt['datum']) ?></td>
                    <td><?= htmlspecialchars($dayCz) ?></td>
                    <td><?= nl2br(htmlspecialchars($dt['napln'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($dt['poznamka'])) ?></td>
                    <td><?= htmlspecialchars($dt['trenere']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
