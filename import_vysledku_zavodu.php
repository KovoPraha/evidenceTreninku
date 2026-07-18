<?php
// import_vysledku_zavodu.php
// Skript pro parsování a import výsledků (XLS/XLSX) do tabulky zavod_sportovec

session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sprava_zavodu')) {
    http_response_code(403);
    exit('Pristup odepren.');
}
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

// Načtení ID importu z GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Neplatny pozadavek.');
}

$importId = isset($_POST['import_id']) ? (int)$_POST['import_id'] : 0;
if ($importId <= 0) {
    die('Neplatný import.');
}

// Načtení záznamu importu
$stmt = $pdo->prepare('SELECT zavod_id, soubor, typ FROM zavod_import WHERE id = ?');
$stmt->execute([$importId]);
$import = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$import) {
    die('Import nenalezen.');
}

$zavodId = $import['zavod_id'];
$file    = __DIR__ . '/nahrane_zavody/results/' . $import['soubor'];
$type = strtolower($import['typ']);

if (!file_exists($file)) {
    die('Soubor neexistuje.');
}

// Podpora pouze XLS/XLSX
if (!in_array($type, ['xls','xlsx'])) {
    die('Nepodporovaný formát: ' . htmlspecialchars($type));
}

// Načtení spreadsheetu
$spreadsheet = IOFactory::load($file);
$sheet       = $spreadsheet->getActiveSheet();

// Předpoklad: data počínají řádkem 2, sloupce: A=jmeno, B=poradi, C=cas, D=body
// Vymažeme předchozí výsledky zavod_sportovec (poradi, cas, body)
$pdo->prepare(
    'UPDATE zavod_sportovec SET poradi = NULL, cas = NULL, body = NULL WHERE zavod_id = ?'
)->execute([$zavodId]);

// Parsování
$row = 2;
while (true) {
    $jmeno = trim($sheet->getCell("A{$row}")->getValue());
    if ($jmeno === null || $jmeno === '') break;
    $poradi = (int) $sheet->getCell("B{$row}")->getValue();
    $cas     = trim($sheet->getCell("C{$row}")->getValue());
    $body    = $sheet->getCell("D{$row}")->getValue();

    // Najdeme sportovce (dříve importovaného nebo existujícího)
    $stmtFind = $pdo->prepare('SELECT sportovec_id FROM zavod_sportovec WHERE zavod_id = ? AND sportovec_id = (
        SELECT id FROM sportovci WHERE jmeno = ? LIMIT 1
    )');
    $stmtFind->execute([$zavodId, $jmeno]);
    $link = $stmtFind->fetchColumn();
    if ($link) {
        // Aktualizujeme
        $stmtUpd = $pdo->prepare(
            'UPDATE zavod_sportovec SET poradi = :por, cas = :cas, body = :body
             WHERE zavod_id = :zid AND sportovec_id = (
                SELECT id FROM sportovci WHERE jmeno = :jmeno
             )'
        );
        $stmtUpd->execute([
            ':por'   => $poradi,
            ':cas'   => $cas,
            ':body'  => $body,
            ':zid'   => $zavodId,
            ':jmeno' => $jmeno,
        ]);
    }
    $row++;
}

// Přesměrování zpět na detail závodu
header('Location: edit_zavod.php?id=' . $zavodId);
exit;
?>
