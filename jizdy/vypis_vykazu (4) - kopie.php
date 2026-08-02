<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once 'db.php';

// Autoload a import tříd pro Excel export
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}

$trener_id = $_SESSION['trener_id'];
$mesic = $_GET['mesic'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesic)) {
    $mesic = date('Y-m');
}

// Načtení tréninků, kde je uživatel přiřazen jako trenér
$tr_stmt = $pdo->prepare(
    "SELECT t.*
     FROM trenink_trener tt
     JOIN treninky t ON tt.trenink_id = t.id
     WHERE tt.trener_id = ?
       AND DATE_FORMAT(t.datum, '%Y-%m') = ?
     ORDER BY t.datum"
);
$tr_stmt->execute([$trener_id, $mesic]);
$treninky = $tr_stmt->fetchAll(PDO::FETCH_ASSOC);

// Načtení dalších činností
$ac_stmt = $pdo->prepare(
    "SELECT *
     FROM dalsi_cinnosti
     WHERE trener_id = ?
       AND DATE_FORMAT(datum, '%Y-%m') = ?
     ORDER BY datum"
);
$ac_stmt->execute([$trener_id, $mesic]);
$aktivity = $ac_stmt->fetchAll(PDO::FETCH_ASSOC);

// Součet hodin
$soucet_hodin = array_sum(array_column($treninky, 'delka'))
              + array_sum(array_column($aktivity, 'delka'));

// Export do Excelu
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Výkaz aktivit");

    // Hlavička
    $sheet->setCellValue('A1', "Měsíc: $mesic");
    $sheet->setCellValue('A2', 'Tréninky');
    $sheet->fromArray(['Datum', 'Náplň', 'Délka'], NULL, 'A3');

    // Tréninky
    $row = 4;
    foreach ($treninky as $t) {
        $sheet->setCellValue("A{$row}", $t['datum']);
        $sheet->setCellValue("B{$row}", $t['napln']);
        $sheet->setCellValue("C{$row}", $t['delka']);
        $row++;
    }

    // Další činnosti
    $sheet->setCellValue("A{$row}", 'Další činnosti');
    $row++;
    $sheet->fromArray(['Datum', 'Název', 'Délka', 'Poznámka'], NULL, "A{$row}");
    $row++;
    foreach ($aktivity as $a) {
        $sheet->setCellValue("A{$row}", $a['datum']);
        $sheet->setCellValue("B{$row}", $a['nazev']);
        $sheet->setCellValue("C{$row}", $a['delka']);
        $sheet->setCellValue("D{$row}", $a['poznamka']);
        $row++;
    }

    // Součet
    $sheet->setCellValue("A{$row}", 'Celkem hodin');
    $sheet->setCellValue("B{$row}", $soucet_hodin);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vykaz_' . $mesic . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Výkaz činností</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'hlavicka.php'; ?>
<div class="container mt-5">
    <h1>Výkaz činností za měsíc <?= htmlspecialchars($mesic) ?></h1>
    <form method="GET" class="mb-4">
        <label for="mesic" class="form-label">Vyber měsíc:</label>
        <input type="month" name="mesic" id="mesic" value="<?= htmlspecialchars($mesic) ?>" class="form-control mb-2" style="max-width: 300px;">
        <button type="submit" class="btn btn-primary">Zobrazit</button>
        <a href="?mesic=<?= htmlspecialchars($mesic) ?>&export=xls" class="btn btn-success">Export do Excelu</a>
    </form>

    <h3>Tréninky</h3>
    <ul class="list-group mb-4">
        <?php foreach ($treninky as $t): ?>
            <li class="list-group-item">
                <?= htmlspecialchars($t['datum']) ?> &ndash; <?= htmlspecialchars($t['napln']) ?> (<?= number_format($t['delka'], 2) ?> h)
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Další činnosti</h3>
    <ul class="list-group mb-4">
        <?php foreach ($aktivity as $a): ?>
            <li class="list-group-item">
                <?= htmlspecialchars($a['datum']) ?> &ndash; <?= htmlspecialchars($a['nazev']) ?> (<?= number_format($a['delka'], 2) ?> h)<br>
                <small><?= htmlspecialchars($a['poznamka']) ?></small>
            </li>
        <?php endforeach; ?>
    </ul>

    <p><strong>Celkem odpracováno:</strong> <?= number_format($soucet_hodin, 2, ',', ' ') ?> hodin</p>
</div>
</body>
</html>
