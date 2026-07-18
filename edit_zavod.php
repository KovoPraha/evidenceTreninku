<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: prehled_zavodu.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný CSRF token.');
}

// Načtení dat z formuláře
$trenId     = $_SESSION['trener_id'];
$datum      = $_POST['datum']      ?? date('Y-m-d');
$popis      = trim($_POST['popis'] ?? '');
$poznamka   = trim($_POST['poznamka'] ?? '');
$skupinaId  = $_POST['skupina_id'] ?? null;
$podskupiny = $_POST['podskupiny'] ?? [];
$trenere    = $_POST['trenere'] ?? [];
$ucastnici  = trim($_POST['ucastnici'] ?? '');

// Nahrání fotografií závodu
$photoFiles = [];
if (!empty($_FILES['fotky']['name'][0])) {
    $uploadDir     = __DIR__ . '/nahrane_zavody/';
    $allowedImgMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedImgExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    foreach ($_FILES['fotky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['fotky']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['fotky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedImgExt, true)) continue;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowedImgMime, true)) continue;
        $fn = uniqid('zavod_') . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDir . $fn)) {
            $photoFiles[] = $fn;
        }
    }
}

// Nahrání importovaných výsledků
$importFiles = [];
if (!empty($_FILES['vysledky']['name'][0])) {
    $importDir      = __DIR__ . '/nahrane_zavody/results/';
    if (!is_dir($importDir)) mkdir($importDir, 0755, true);
    $allowedResMime = ['application/pdf','application/vnd.ms-excel',
                       'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'text/csv','text/plain'];
    $allowedResExt  = ['pdf', 'xls', 'xlsx', 'csv'];
    foreach ($_FILES['vysledky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['vysledky']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($_FILES['vysledky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedResExt, true)) continue;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowedResMime, true)) continue;
        $fn = uniqid('res_') . '.' . $ext;
        if (move_uploaded_file($tmp, $importDir . $fn)) {
            $importFiles[] = ['file' => $fn, 'type' => $ext];
        }
    }
}

try {
    $pdo->beginTransaction();

    // 1) Vložení základního záznamu závodu
    $stmt = $pdo->prepare(
        'INSERT INTO zavody (datum, popis, poznamka, trener_id)
         VALUES (:datum, :popis, :poznamka, :tid)'
    );
    $stmt->execute([
        ':datum'    => $datum,
        ':popis'    => $popis,
        ':poznamka' => $poznamka,
        ':tid'      => $trenId,
    ]);
    $zavodId = $pdo->lastInsertId();

    // 2) Vazba trenérů
    if (!in_array($trenId, $trenere, true)) {
        array_unshift($trenere, $trenId);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO zavod_trener (zavod_id, trener_id) VALUES (:zid, :trid)'
    );
    foreach ($trenere as $tr) {
        $stmt->execute([':zid' => $zavodId, ':trid' => $tr]);
    }

    // 3) Vazba skupiny
    if ($skupinaId) {
        $stmt = $pdo->prepare(
            'INSERT INTO zavod_skupina (zavod_id, skupina_id) VALUES (:zid, :gid)'
        );
        $stmt->execute([':zid' => $zavodId, ':gid' => $skupinaId]);
    }

    // 4) Vazba podskupin
    if (!empty($podskupiny)) {
        $stmt = $pdo->prepare(
            'INSERT INTO zavod_podskupina (zavod_id, podskupina_id) VALUES (:zid, :psid)'
        );
        foreach ($podskupiny as $ps) {
            $stmt->execute([':zid' => $zavodId, ':psid' => $ps]);
        }
    }

    // 5) Vazba účastníků (bez výsledků)
    $find = $pdo->prepare('SELECT id FROM sportovci WHERE jmeno = ?');
    $ins  = $pdo->prepare('INSERT INTO sportovci (jmeno, hash) VALUES (?, "")');
    $upd  = $pdo->prepare('UPDATE sportovci SET hash = SHA2(CONCAT(id,"-",jmeno),256) WHERE id = ?');
    $link = $pdo->prepare(
        'INSERT IGNORE INTO zavod_sportovec (zavod_id, sportovec_id) VALUES (:zid, :sid)'
    );
    if ($ucastnici !== '') {
        $names = array_filter(array_map('trim', explode(',', $ucastnici)));
        foreach ($names as $jm) {
            $find->execute([$jm]);
            if ($r = $find->fetch(PDO::FETCH_ASSOC)) {
                $sid = $r['id'];
            } else {
                $ins->execute([$jm]);
                $sid = $pdo->lastInsertId();
                $upd->execute([$sid]);
            }
            $link->execute([':zid'=>$zavodId, ':sid'=>$sid]);
        }
    }

    // 6) Uložení fotek závodu
    $stmt = $pdo->prepare(
        'INSERT INTO zavod_fotka (zavod_id, soubor) VALUES (:zid, :file)'
    );
    foreach ($photoFiles as $f) {
        $stmt->execute([':zid' => $zavodId, ':file' => $f]);
    }

    // 7) Uložení importovaných výsledků
    $stmt = $pdo->prepare(
        'INSERT INTO zavod_import (zavod_id, soubor, typ) VALUES (:zid, :file, :typ)'
    );
    foreach ($importFiles as $imp) {
        $stmt->execute([':zid'=>$zavodId, ':file'=>$imp['file'], ':typ'=>$imp['type']]);
        // TODO: Zpracovat obsah XLS/PDF a doplnit poradi, cas, body do zavod_sportovec
    }

    $pdo->commit();
    header('Location: prehled_zavodu.php?zavod_id=' . $zavodId);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo 'Chyba při ukládání závodu: ' . htmlspecialchars($e->getMessage());
    exit;
}
