<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('formular_zavod')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/sports_measurement_input.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --------------------
// CSRF ověření
// --------------------
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
    header('Location: formular_zavod.php');
    exit;
}

// --------------------
// Vstupy
// --------------------
$trenId      = (int)$_SESSION['trener_id'];
$datum       = $_POST['datum'] ?? date('Y-m-d');
$kategorie   = in_array($_POST['kategorie'] ?? '', ['silnice', 'draha', 'mtb'], true)
               ? $_POST['kategorie']
               : 'silnice';
$popis       = trim($_POST['popis'] ?? '');
$poznamka    = trim($_POST['poznamka'] ?? '');
$urlVysledky = filter_var(trim($_POST['url_vysledky'] ?? ''), FILTER_VALIDATE_URL) ?: null;
$skupinaId   = (int)($_POST['skupina_id'] ?? 0) ?: null;
$podskupiny  = array_map('intval', (array)($_POST['podskupiny'] ?? []));
$trenere     = array_map('intval', (array)($_POST['trenere'] ?? []));
$ucastnici   = trim($_POST['ucastnici'] ?? '');
try {
    $mereniRows = sportsMeasurementRowsFromPost($_POST);
} catch (InvalidArgumentException $exception) {
    $_SESSION['flash_error'] = $exception->getMessage();
    header('Location: formular_zavod.php');
    exit;
}

// --------------------
// Fotografie
// --------------------
$fotkyPaths = [];
if (!empty($_FILES['fotky']['name'][0])) {
    $uploadDir = __DIR__ . '/nahrane_zavody/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    foreach ($_FILES['fotky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['fotky']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) continue;

        $ext  = strtolower(pathinfo($_FILES['fotky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) continue;

        $name = 'zavod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . $name;

        if (move_uploaded_file($tmp, $dest)) {
            $fotkyPaths[] = 'nahrane_zavody/' . $name;
        }
    }
}

// --------------------
// Výsledky (soubory)
// --------------------
$vysledkyPaths = [];
if (!empty($_FILES['vysledky']['name'][0])) {
    $uploadDir = __DIR__ . '/nahrane_zavody/results/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    $allowedMimes = [
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $allowedExts = ['pdf', 'xls', 'xlsx'];

    foreach ($_FILES['vysledky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['vysledky']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMimes, true)) continue;

        $ext = strtolower(pathinfo($_FILES['vysledky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) continue;

        $origName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($_FILES['vysledky']['name'][$i]));
        $name     = 'vysledky_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $origName;
        $dest     = $uploadDir . $name;

        if (move_uploaded_file($tmp, $dest)) {
            $vysledkyPaths[] = 'nahrane_zavody/results/' . $name;
        }
    }
}

// --------------------
// Uložení do databáze
// --------------------
try {
    if ($popis === '') throw new Exception('Popis závodu nesmí být prázdný.');
    if ($datum === '') throw new Exception('Chybí datum závodu.');

    // Validace: měření musí mít vybraného sportovce
    foreach ($mereniRows as $i => $row) {
        if (empty($row['sportovec_id']) || (int)$row['sportovec_id'] <= 0) {
            throw new Exception('U měření na řádku ' . ($i + 1) . ' je potřeba vybrat sportovce ze seznamu.');
        }
    }

    $pdo->beginTransaction();

    // 1) INSERT zavody
    $stmtZ = $pdo->prepare(
        'INSERT INTO zavody (datum, kategorie, popis, poznamka, url_vysledky, trener_id)
         VALUES (:datum, :kategorie, :popis, :poznamka, :url_vysledky, :trener_id)'
    );
    $stmtZ->execute([
        ':datum'        => $datum,
        ':kategorie'    => $kategorie,
        ':popis'        => $popis,
        ':poznamka'     => ($poznamka !== '' ? $poznamka : null),
        ':url_vysledky' => $urlVysledky,
        ':trener_id'    => $trenId,
    ]);
    $zavodId = (int)$pdo->lastInsertId();

    // 2) Vazba na trenéry — přihlášený trenér vždy zahrnut
    if (!in_array($trenId, $trenere, true)) {
        array_unshift($trenere, $trenId);
    }
    $trenere = array_values(array_unique(array_filter($trenere)));

    $stmtT = $pdo->prepare('INSERT IGNORE INTO zavod_trener (zavod_id, trener_id) VALUES (?, ?)');
    foreach ($trenere as $trid) {
        if ($trid <= 0) continue;
        $stmtT->execute([$zavodId, $trid]);
    }

    // 3) Vazba na skupinu
    if ($skupinaId !== null) {
        $pdo->prepare('INSERT IGNORE INTO zavod_skupina (zavod_id, skupina_id) VALUES (?, ?)')
            ->execute([$zavodId, $skupinaId]);
    }

    // 4) Vazba na podskupiny
    if (!empty($podskupiny)) {
        $stmtPs = $pdo->prepare('INSERT IGNORE INTO zavod_podskupina (zavod_id, podskupina_id) VALUES (?, ?)');
        foreach ($podskupiny as $psid) {
            if ($psid <= 0) continue;
            $stmtPs->execute([$zavodId, $psid]);
        }
    }

    // 5) Účastníci — formát "id:label, id:label"
    if ($ucastnici !== '') {
        $stmtSp = $pdo->prepare('INSERT IGNORE INTO zavod_sportovec (zavod_id, sportovec_id) VALUES (?, ?)');
        foreach (array_filter(array_map('trim', explode(',', $ucastnici))) as $part) {
            $colon = strpos($part, ':');
            if ($colon !== false) {
                $sid = (int)substr($part, 0, $colon);
                if ($sid > 0) $stmtSp->execute([$zavodId, $sid]);
            }
        }
    }

    // 6) Fotografie — ukládá se jen název souboru (čtečky přidávají prefix nahrane_zavody/)
    if (!empty($fotkyPaths)) {
        $stmtF = $pdo->prepare('INSERT INTO zavod_fotka (zavod_id, soubor) VALUES (?, ?)');
        foreach ($fotkyPaths as $cesta) {
            $stmtF->execute([$zavodId, basename($cesta)]);
        }
    }

    // 7) Soubory výsledků — sloupce soubor + typ (jako update_zavod.php)
    if (!empty($vysledkyPaths)) {
        $stmtI = $pdo->prepare('INSERT INTO zavod_import (zavod_id, soubor, typ) VALUES (?, ?, ?)');
        foreach ($vysledkyPaths as $cesta) {
            $name = basename($cesta);
            $typ  = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bin';
            $stmtI->execute([$zavodId, $name, $typ]);
        }
    }

    // 8) Měření (mereni_zaznamy + zavod_mereni)
    if (!empty($mereniRows)) {
        $stmtInsM = $pdo->prepare(sportsMeasurementInsertSql());

        $stmtLinkM = $pdo->prepare("
            INSERT INTO zavod_mereni (zavod_id, mereni_id, poradi)
            VALUES (:zavod_id, :mereni_id, :poradi)
        ");

        $poradi = 0;
        foreach ($mereniRows as $row) {
            $stmtInsM->execute(sportsMeasurementInsertParameters($row));

            $mid = (int)$pdo->lastInsertId();

            $stmtLinkM->execute([
                ':zavod_id'  => $zavodId,
                ':mereni_id' => $mid,
                ':poradi'    => $poradi++,
            ]);
        }
    }

    $pdo->commit();

    $_SESSION['flash_success'] = 'Závod byl uložen.';
    header('Location: zavod_detail.php?id=' . $zavodId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Chyba při ukládání závodu: ' . $e->getMessage();
    header('Location: formular_zavod.php');
    exit;
}
