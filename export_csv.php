<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$trenerId = (int)$_SESSION['trener_id'];
$trenerJmeno = (string)($_SESSION['trener_jmeno'] ?? 'trener');
$mesic = $_GET['mesic'] ?? '';

if (!is_string($mesic) || !preg_match('/^\d{4}-\d{2}$/', $mesic)) {
    die('Neplatny mesic.');
}

$sql = "
    SELECT
        t.id,
        t.datum,
        t.napln,
        t.delka,
        GROUP_CONCAT(DISTINCT s.nazev ORDER BY s.poradi, s.nazev SEPARATOR ', ') AS skupiny,
        COUNT(DISTINCT tsv.sportovec_id) AS pocet_sportovcu
    FROM treninky t
    INNER JOIN trenink_trener tt ON tt.trenink_id = t.id AND tt.trener_id = :trener_id
    LEFT JOIN trenink_skupina tsk ON tsk.trenink_id = t.id
    LEFT JOIN skupiny s ON s.id = tsk.skupina_id
    LEFT JOIN trenink_sportovec tsv ON tsv.trenink_id = t.id
    WHERE DATE_FORMAT(t.datum, '%Y-%m') = :mesic
    GROUP BY t.id, t.datum, t.napln, t.delka
    ORDER BY t.datum ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':trener_id' => $trenerId, ':mesic' => $mesic]);
$treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);

function csvSafe($value): string {
    $value = (string)$value;
    return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
        ? "'" . $value
        : $value;
}

$safeName = preg_replace('/[^a-z0-9_-]+/i', '_', $trenerJmeno);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="treninky_' . $safeName . '_' . $mesic . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Datum', 'Skupiny', 'Napln', 'Pocet sportovcu', 'Delka (hod)']);

$totalAthletes = 0;
$totalHours = 0.0;

foreach ($treninky as $t) {
    $pocetSportovcu = (int)$t['pocet_sportovcu'];
    fputcsv($output, [
        csvSafe($t['datum'] ?? ''),
        csvSafe($t['skupiny'] ?? ''),
        csvSafe($t['napln'] ?? ''),
        $pocetSportovcu,
        $t['delka'],
    ]);

    $totalAthletes += $pocetSportovcu;
    $totalHours += (float)$t['delka'];
}

fputcsv($output, ['', '', 'Celkem sportovcu', $totalAthletes]);
fputcsv($output, ['', '', 'Celkem hodin', $totalHours]);
fputcsv($output, ['', '', 'Pocet treninku', count($treninky)]);

fclose($output);
exit;
