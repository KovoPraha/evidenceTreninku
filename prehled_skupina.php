<?php
// prehled_skupin.php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php', true, 303);
    exit;
}
require_once 'db.php';

// Načtení hash a načtení skupiny
$hash = $_GET['hash'] ?? '';
$stmtHash = $pdo->prepare(
    "SELECT id, nazev FROM skupiny WHERE hash = :hash"
);
$stmtHash->execute([':hash' => $hash]);
$sg = $stmtHash->fetch(PDO::FETCH_ASSOC);
if (!$sg) {
    echo '<p>Skupina nenalezena.</p>';
    exit;
}
$skupinaId   = $sg['id'];
$skupinaNazev = htmlspecialchars($sg['nazev']);

// Filtr období
$allowed = [1,2,6];
$months  = isset($_GET['months']) && in_array((int)$_GET['months'], $allowed)
           ? (int)$_GET['months'] : 1;
$dateFrom = date('Y-m-d', strtotime("-{$months} months"));

// Překlad dnů
$czDays = [
    'Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
    'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'
];

// Načtení tréninků skupiny
$stmt = $pdo->prepare(
    "SELECT t.id, t.datum, t.delka, t.napln,
            (SELECT GROUP_CONCAT(tr.jmeno SEPARATOR ', ')
             FROM trenink_trener tt
             JOIN treneri tr ON tt.trener_id = tr.id
             WHERE tt.trenink_id = t.id
            ) AS trenere
     FROM treninky t
     JOIN trenink_skupina tg ON t.id = tg.trenink_id
     WHERE tg.skupina_id = :skupina_id
       AND t.datum >= :date_from
     GROUP BY t.id
     ORDER BY t.datum DESC"
);
$stmt->execute([
    ':skupina_id' => $skupinaId,
    ':date_from'  => $dateFrom
]);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled skupiny: <?= $skupinaNazev ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
</head>
<body class="bg-light">

<div class="container mt-5">
    <h1>Přehled skupiny: <?= $skupinaNazev ?></h1>
    <div class="mb-4">
        <span>Interval: </span>
        <?php foreach ($allowed as $m): ?>
            <?php $active = ($m === $months) ? 'fw-bold' : ''; ?>
            <a href="?hash=<?= urlencode($hash) ?>&months=<?= $m ?>" class="me-2 <?= $active ?>">
                <?= $m ?> měsíc<?= $m>1 ? 'ů' : '' ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($treninky)): ?>
        <div class="alert alert-info">Žádné tréninky za posledních <?= $months ?> měsíců.</div>
    <?php else: ?>
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Datum</th>
                    <th>Den</th>
                    <th>Délka (h)</th>
                    <th>Trenéři</th>
                    <th>Náplň</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($treninky as $t):
                    $dayEng = (new DateTime($t['datum']))->format('l');
                    $dayCz  = $czDays[$dayEng] ?? $dayEng;
                ?>
                <tr>
                    <td><?= htmlspecialchars($t['datum']) ?></td>
                    <td><?= htmlspecialchars($dayCz) ?></td>
                    <td><?= htmlspecialchars($t['delka']) ?></td>
                    <td><?= htmlspecialchars($t['trenere']) ?></td>
                    <td><?= nl2br(htmlspecialchars($t['napln'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
