<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/includes/private_storage.php';
require_once __DIR__ . '/includes/person_sensitive.php';

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
$legacyPath = null;
if ($kind === 'receipt') {
    if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('finance_manager')) {
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
    $trainer = isset($_SESSION['trener_id']) && staffActivePositionIs('coach');
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
} elseif ($kind === 'athlete-photo') {
    if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('registrar')) {
        http_response_code(403);
        exit('Pristup odepren.');
    }
    $stmt = $pdo->prepare(
        "SELECT id,request_id,sportovec_id,storage_key FROM athlete_private_files "
        . "WHERE id=? AND file_kind='profile_photo' AND status='active' LIMIT 1"
    );
    $stmt->execute([(int)$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        exit('Soubor nebyl nalezen.');
    }
    personSensitiveAdminAuditPhotoView($pdo, $file, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $key = (string)$file['storage_key'];
    $originalName = 'sportovec-foto-' . (int)$id;
} elseif ($kind === 'service') {
    if (!isset($_SESSION['trener_id']) || !staffActivePositionIs('finance_manager') || !canAccess('servis')) {
        http_response_code(403);
        exit('Pristup odepren.');
    }
    $stmt = $pdo->prepare('SELECT dokument FROM ucto_servis WHERE id=? LIMIT 1');
    $stmt->execute([(int)$id]);
    $key = (string)($stmt->fetchColumn() ?: '');
    $originalName = 'servisni-dokument-' . (int)$id;
    if ($key !== '' && !str_starts_with($key, 'private://')) {
        $normalized = str_replace('\\', '/', ltrim($key, '/'));
        $basename = basename($normalized);
        $legacyRoot = realpath(__DIR__ . '/uploads/servis');
        $candidate = $legacyRoot === false ? false : realpath($legacyRoot . DIRECTORY_SEPARATOR . $basename);
        if (!str_starts_with($normalized, 'uploads/servis/')
            || $basename === ''
            || $normalized !== 'uploads/servis/' . $basename
            || $candidate === false
            || !str_starts_with($candidate, $legacyRoot . DIRECTORY_SEPARATOR)
        ) {
            http_response_code(404);
            exit('Soubor nebyl nalezen.');
        }
        $legacyPath = $candidate;
    }
} else {
    http_response_code(404);
    exit('Soubor nebyl nalezen.');
}

$path = $legacyPath ?? privateStorageResolve($key);
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
