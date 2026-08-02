<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: zatezovy_test_form.php');
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný CSRF token.');
}

// 1) Načtení a základní kontrola vstupů
$sportovec_id     = isset($_POST['sportovec_id']) ? (int)$_POST['sportovec_id'] : 0;
$datum            = $_POST['datum'] ?? '';
$vek              = $_POST['vek'] !== '' ? (int)$_POST['vek'] : null;
$vaha_kg          = isset($_POST['vaha_kg'])  && $_POST['vaha_kg']  !== '' ? (float)$_POST['vaha_kg']  : null;
$vyska_cm         = isset($_POST['vyska_cm']) && $_POST['vyska_cm'] !== '' ? (float)$_POST['vyska_cm'] : null;
$popis_interni    = trim($_POST['popis_interni'] ?? '');
$popis_sportovec  = trim($_POST['popis_sportovec'] ?? '');

if ($sportovec_id <= 0) {
    die('Sportovec není určen.');
}
if (!$datum) {
    die('Datum testu je povinné.');
}

// Ověříme, že sportovec existuje
$stmt = $pdo->prepare("SELECT id FROM sportovci WHERE id = ?");
$stmt->execute([$sportovec_id]);
if (!$stmt->fetch()) {
    die('Zvolený sportovec neexistuje.');
}

// 2) Vložení záznamu zátěžového testu
try {
    $pdo->beginTransaction();

    $stmtIns = $pdo->prepare("
        INSERT INTO zatezove_testy
            (sportovec_id, datum, vek, vaha_kg, vyska_cm, popis_interni, popis_sportovec, created_at)
        VALUES
            (:sid, :datum, :vek, :vaha, :vyska, :pin, :psp, NOW())
    ");
    $stmtIns->execute([
        ':sid'   => $sportovec_id,
        ':datum' => $datum,
        ':vek'   => $vek,
        ':vaha'  => $vaha_kg,
        ':vyska' => $vyska_cm,
        ':pin'   => $popis_interni,
        ':psp'   => $popis_sportovec
    ]);

    $testId = (int)$pdo->lastInsertId();

    // 3) Upload souborů
    $uploadBaseDir = __DIR__ . '/uploads/zatezove_testy/';
    $uploadBaseUrl = 'uploads/zatezove_testy/';

    if (!is_dir($uploadBaseDir)) {
        mkdir($uploadBaseDir, 0755, true);
    }

    /**
     * Pomocná funkce na zpracování pole souborů
     *
     * @param string $poleName   - jméno inputu ve formuláři
     * @param string $typ        - typ do DB (public_img | internal_img | other)
     */
    $saveFiles = function(string $poleName, string $typ) use ($testId, $uploadBaseDir, $uploadBaseUrl, $pdo) {
        if (!isset($_FILES[$poleName])) {
            return;
        }

        $files = $_FILES[$poleName];

        // Může být jeden nebo více souborů
        $count = is_array($files['name']) ? count($files['name']) : 0;
        if ($count === 0) return;

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['name'][$i] === '') {
                continue;
            }

            $origName = $files['name'][$i];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION) ?: '');
            if ($ext === '') {
                $ext = 'dat';
            }

            $safeExt  = preg_replace('~[^a-z0-9]+~', '', $ext);
            if ($safeExt === '') {
                $safeExt = 'dat';
            }

            // MIME validace — blokuj spustitelné typy bez ohledu na příponu
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mime     = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            $blocked  = ['text/x-php','application/x-php','application/php','application/x-httpd-php','application/x-sh','text/x-sh'];
            if (in_array($mime, $blocked, true) || $safeExt === 'php') {
                continue;
            }

            $filename = 'test_' . $testId . '_' . $typ . '_' . time() . '_' . $i . '.' . $safeExt;
            $target   = $uploadBaseDir . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                $relPath = $uploadBaseUrl . $filename;

                $stmtFile = $pdo->prepare("
                    INSERT INTO zatezove_testy_soubory (test_id, typ, nazev, cesta, created_at)
                    VALUES (:tid, :typ, :nazev, :cesta, NOW())
                ");
                $stmtFile->execute([
                    ':tid'   => $testId,
                    ':typ'   => $typ,
                    ':nazev' => $origName,
                    ':cesta' => $relPath
                ]);
            }
        }
    };

    // veřejné obrázky
    $saveFiles('public_img', 'public_img');
    // interní obrázky
    $saveFiles('internal_img', 'internal_img');
    // ostatní soubory
    $saveFiles('other_files', 'other');

    $pdo->commit();

    // 4) Přesměrování – až budeš mít interní kartu sportovce, můžeš URL upravit
    header('Location: sportovec_detail.php?id=' . $sportovec_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Chyba při ukládání zátěžového testu: ' . htmlspecialchars($e->getMessage()));
}
