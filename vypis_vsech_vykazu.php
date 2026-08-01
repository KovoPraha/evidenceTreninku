<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('vsechny_vykazy')) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Filtry: trenér a měsíc
$trainerId = $_GET['trainer_id'] ?? '';
$month     = $_GET['month']      ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
list($year, $mon) = explode('-', $month);

// Seznam trenérů pro filtr
$trainers = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);

// SQL pro tréninky daného trenéra
$sql = "
SELECT
t.datum,
t.napln,
t.poznamka,
t.delka,
GROUP_CONCAT(DISTINCT s.nazev SEPARATOR ', ') AS skupiny,
GROUP_CONCAT(DISTINCT p.nazev SEPARATOR ', ') AS podskupiny,
COUNT(DISTINCT ts.sportovec_id) AS pocet_sportovcu,
COUNT(DISTINCT m.id) AS pocet_mereni
FROM trenink_trener tt
JOIN treninky t ON tt.trenink_id = t.id
LEFT JOIN trenink_skupina tg ON t.id = tg.trenink_id
LEFT JOIN skupiny s ON tg.skupina_id = s.id
LEFT JOIN trenink_podskupina tp ON t.id = tp.trenink_id
LEFT JOIN podskupiny p ON tp.podskupina_id = p.id
LEFT JOIN trenink_sportovec ts ON t.id = ts.trenink_id
LEFT JOIN mereni m ON t.id = m.trenink_id
WHERE tt.trener_id = :trainer_id
  AND YEAR(t.datum) = :year
  AND MONTH(t.datum) = :mon
GROUP BY t.id
ORDER BY t.datum ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':trainer_id' => $trainerId,
    ':year'       => $year,
    ':mon'        => $mon
]);
$trData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// SQL pro další činnosti
$sql2 = "
SELECT datum, nazev, delka, poznamka
FROM dalsi_cinnosti
WHERE trener_id = :trainer_id
  AND YEAR(datum) = :year
  AND MONTH(datum) = :mon
ORDER BY datum ASC";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute([
    ':trainer_id' => $trainerId,
    ':year'       => $year,
    ':mon'        => $mon
]);
$actData = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Součet hodin
$totalHours = 0;
foreach ($trData as $row) $totalHours += $row['delka'];
foreach ($actData as $row) $totalHours += $row['delka'];

// Export XLS
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Výkazy');
    $sheet->setCellValue('A1', "Trenér: " . ($trainerId ? array_column( // find name
        array_filter($trainers, fn($t)=>$t['id']==$trainerId), 'jmeno')[0] : ''));
    $sheet->setCellValue('A2', "Měsíc: $month");

    $sheet->fromArray(['Datum','Náplň','Poznámka','Délka','Skupiny','Podskupiny','Počet sportovců','Počet měření'], null, 'A4');
    $row = 5;
    foreach ($trData as $d) {
        $sheet->setCellValue("A{$row}", $d['datum']);
        $sheet->setCellValue("B{$row}", $d['napln']);
        $sheet->setCellValue("C{$row}", $d['poznamka']);
        $sheet->setCellValue("D{$row}", $d['delka']);
        $sheet->setCellValue("E{$row}", $d['skupiny']);
        $sheet->setCellValue("F{$row}", $d['podskupiny']);
        $sheet->setCellValue("G{$row}", $d['pocet_sportovcu']);
        $sheet->setCellValue("H{$row}", $d['pocet_mereni']);
        $row++;
    }
    $sheet->setCellValue("A{$row}", 'Další činnosti');
    $row++;
    $sheet->fromArray(['Datum','Název','Délka','Poznámka'], null, "A{$row}");
    $row++;
    foreach ($actData as $d) {
        $sheet->setCellValue("A{$row}", $d['datum']);
        $sheet->setCellValue("B{$row}", $d['nazev']);
        $sheet->setCellValue("C{$row}", $d['delka']);
        $sheet->setCellValue("D{$row}", $d['poznamka']);
        $row++;
    }
    $sheet->setCellValue("A{$row}", 'Celkem hodin');
    $sheet->setCellValue("B{$row}", $totalHours);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vsech_vykazu_'.$month.'.xlsx"');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Výpis všech výkazů</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-5">
    <h1>Výpis všech výkazů</h1>
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Trenér:</label>
            <select name="trainer_id" class="form-select">
                <option value="">-- vyber trenéra --</option>
                <?php foreach ($trainers as $tr): ?>
                <option value="<?= $tr['id'] ?>" <?= $trainerId == $tr['id'] ? 'selected' : '' ?>
                    ><?= htmlspecialchars($tr['jmeno']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Měsíc:</label>
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="form-control">
        </div>
        <div class="col-md-5">
            <button name="export" value="xls" class="btn btn-success me-2">Export do Excelu</button>
            <button type="submit" class="btn btn-primary">Zobrazit</button>
        </div>
    </form>

    <h3>Tréninky</h3>
    <ul class="list-group mb-4">
        <?php foreach ($trData as $d): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= htmlspecialchars($d['datum']) ?> &ndash; <?= htmlspecialchars($d['napln']) ?> (<?= $d['delka'] ?> h)
            <a href="edit_trenink.php?id=<?= $trenink['id'] ?>" class="btn btn-sm btn-secondary">Upravit</a>
        </li>
        <?php endforeach; ?>
    </ul>

    <h3>Další činnosti</h3>
    <ul class="list-group mb-4">
        <?php foreach ($actData as $a): ?>
        <li class="list-group-item">
            <?= htmlspecialchars($a['datum']) ?> &ndash; <?= htmlspecialchars($a['nazev']) ?> (<?= $a['delka'] ?> h)<br>
            <small><?= htmlspecialchars($a['poznamka']) ?></small>
        </li>
        <?php endforeach; ?>
    </ul>

    <p><strong>Celkem hodin:</strong> <?= $totalHours ?> h</p>
</div>
</body>
</html>