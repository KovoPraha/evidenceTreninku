<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/includes/private_storage.php';

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");

$kind = (string)($_GET['kind'] ?? '');
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(404);
    exit('Soubor nebyl nalezen.');
}

$key = '';
$originalName = 'soubor';
if ($kind === 'receipt') {
    if (!isset($_SESSION['trener_id']) || !canAccess('uctenky')) {
        http_response_code(403);
        exit('Pristup odepren.');
    }
    $stmt = $pdo->prepare('SELECT obrazek_path FROM ucto_uctenky WHERE id = ?');
    $stmt->execute([(int)$id]);
    $key = (string)($stmt->fetchColumn() ?: '');
    $originalName = 'uctenka-' . (int)$id;
} elseif ($kind === 'stress') {
    $stmt = $pdo->prepare(
        'SELECT f.cesta, f.nazev, f.typ, s.hash '
        . 'FROM zatezove_testy_soubory f '
        . 'JOIN zatezove_testy z ON z.id = f.test_id '
        . 'JOIN sportovci s ON s.id = z.sportovec_id WHERE f.id = ?'
    );
    $stmt->execute([(int)$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        exit('Soubor nebyl nalezen.');
    }
    $trainer = isset($_SESSION['trener_id']);
    $publicHash = trim((string)($_GET['hash'] ?? ''));
    $public = (string)$file['typ'] === 'public_img'
        && $publicHash !== ''
        && hash_equals((string)$file['hash'], $publicHash);
    if (!$trainer && !$public) {
        http_response_code(403);
        exit('Pristup odepren.');
    }
    $key = (string)$file['cesta'];
    $originalName = (string)($file['nazev'] ?: ('zatezovy-test-' . (int)$id));
} else {
    http_response_code(404);
    exit('Soubor nebyl nalezen.');
}

$path = privateStorageResolve($key);
if ($path === null) {
    http_response_code(404);
    exit('Soubor nebyl nalezen.');
}

$mime = privateStorageMime($path);
$extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime];
$base = pathinfo(str_replace(["\r", "\n", "\0"], '', $originalName), PATHINFO_FILENAME);
$safeName = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-.');
$safeName = ($safeName !== '' ? $safeName : 'soubor') . '.' . $extension;

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
readfile($path);
