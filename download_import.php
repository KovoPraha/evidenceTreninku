<?php
require_once __DIR__ . '/includes/session_security.php';
// download_import.php
// Stáhne importovaný soubor výsledků závodu

app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (!canAccess('zavod_detail')) { http_response_code(403); exit('Přístup odepřen.'); }
require_once __DIR__ . '/db.php';

// Načtení ID importu
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('Neplatné ID.');
}

// Získání názvu souboru a typu z DB
$stmt = $pdo->prepare('SELECT soubor, typ FROM zavod_import WHERE id = ?');
$stmt->execute([$id]);
$imp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$imp) {
    http_response_code(404);
    die('Import nenalezen.');
}

$filename = basename((string)$imp['soubor']);
$type     = $imp['typ'];
$baseDir  = realpath(__DIR__ . '/nahrane_zavody/results');
$path     = $baseDir === false ? false : realpath($baseDir . DIRECTORY_SEPARATOR . $filename);

if ($path === false || !str_starts_with($path, $baseDir . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    die('Soubor neexistuje.');
}

// Určení Content-Type
switch (strtolower($type)) {
    case 'pdf':  $ctype = 'application/pdf'; break;
    case 'xls':  $ctype = 'application/vnd.ms-excel'; break;
    case 'xlsx': $ctype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
    default:     $ctype = 'application/octet-stream';
}

// Odeslání hlaviček a vlastního souboru
header('Content-Description: File Transfer');
header('Content-Type: ' . $ctype);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
