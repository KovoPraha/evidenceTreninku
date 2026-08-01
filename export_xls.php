<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'vendor/autoload.php';
require_once 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$trener_id = $_SESSION['trener_id'];
$trenerJmeno = $_SESSION['trener_jmeno'];
$mesic = $_GET['mesic'] ?? '';

if (!$mesic) {
    die("Neplatný měsíc.");
}

$rokMesic = date('Y-m', strtotime($mesic));

$sql = "SELECT t.id, t.datum, t.napln, t.poznamka, t.delka,
               GROUP_CONCAT(DISTINCT s.nazev ORDER BY s.poradi, s.nazev SEPARATOR ', ') AS skupiny,
               COUNT(DISTINCT tsv.sportovec_id) AS pocet_sportovcu
        FROM treninky t
        INNER JOIN trenink_trener tt ON tt.trenink_id = t.id AND tt.trener_id = :trener_id
        LEFT JOIN trenink_skupina tsk ON tsk.trenink_id = t.id
        LEFT JOIN skupiny s ON s.id = tsk.skupina_id
        LEFT JOIN trenink_sportovec tsv ON tsv.trenink_id = t.id
        WHERE DATE_FORMAT(t.datum, '%Y-%m') = :mesic
        GROUP BY t.id, t.datum, t.napln, t.poznamka, t.delka
        ORDER BY t.datum ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':trener_id' => $trener_id, ':mesic' => $rokMesic]);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vytvoření tabulky
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Tréninky');

$sheet->fromArray([
    'Datum', 'Skupiny', 'Náplň', 'Poznámka', 'Počet sportovců', 'Délka (hod)', 'Měření časů'
], NULL, 'A1');

$row = 2;

foreach ($treninky as $t) {
    $pocetSportovcu = (int)$t['pocet_sportovcu'];

    $stmtMereni = $pdo->prepare("SELECT s.jmeno, s.prijmeni, mz.vzdalenost, mz.cas, mz.poznamka
                                 FROM trenink_mereni tm
                                 JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
                                 LEFT JOIN sportovci s ON s.id = mz.sportovec_id
                                 WHERE tm.trenink_id = :tid
                                 ORDER BY tm.poradi, mz.id");
    $stmtMereni->execute([':tid' => $t['id']]);
    $mereniZaznamy = $stmtMereni->fetchAll(PDO::FETCH_ASSOC);

    $mereniText = '';
    foreach ($mereniZaznamy as $m) {
        $jmeno = trim((string)($m['prijmeni'] ?? '') . ' ' . (string)($m['jmeno'] ?? ''));
        $mereniText .= "{$jmeno}, {$m['vzdalenost']}, {$m['cas']}, {$m['poznamka']}\n";
    }

    // Textová pole z DB explicitně jako string — nikdy se nevyhodnotí jako vzorec (=CMD…)
    $TS = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
    $sheet->setCellValueExplicit("A$row", (string)$t['datum'], $TS);
    $sheet->setCellValueExplicit("B$row", (string)$t['skupiny'], $TS);
    $sheet->setCellValueExplicit("C$row", (string)$t['napln'], $TS);
    $sheet->setCellValueExplicit("D$row", (string)$t['poznamka'], $TS);
    $sheet->setCellValue("E$row", $pocetSportovcu);
    $sheet->setCellValue("F$row", $t['delka']);
    $sheet->setCellValueExplicit("G$row", (string)$mereniText, $TS);

    $row++;
}

$filename = "treninky_{$trenerJmeno}_{$rokMesic}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
