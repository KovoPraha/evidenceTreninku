<?php
session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('formular_zavod')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/**
 * Postaví pole řádků měření z POST dat.
 * Podporované vstupy:
 *  - Varianta A: mereni_json (JSON pole objektů)
 *  - Varianta B: dynamická pole: mereni_typ[] + paralelní pole
 */
function buildMereniRowsFromPost(array $post): array
{
    // Varianta A (aktuální formulář): mereni_json = JSON pole objektů
    if (!empty($post['mereni_json'])) {
        $decoded = json_decode((string)$post['mereni_json'], true);
        if (!is_array($decoded)) return [];

        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;

            $typ = isset($row['typ']) ? trim((string)$row['typ']) : '';
            if ($typ === '') continue;

            $sid = null;
            if (isset($row['sportovec_id']) && ctype_digit((string)$row['sportovec_id'])) {
                $sidInt = (int)$row['sportovec_id'];
                if ($sidInt > 0) $sid = $sidInt;
            }

            if ($typ === 'kolo' || $typ === 'beh') {
                $v = isset($row['vzdalenost']) && $row['vzdalenost'] !== '' ? (float)str_replace(',', '.', (string)$row['vzdalenost']) : null;
                $c = isset($row['cas']) && trim((string)$row['cas']) !== '' ? trim((string)$row['cas']) : null;
                $p = isset($row['prevod']) && trim((string)$row['prevod']) !== '' ? trim((string)$row['prevod']) : null;
                $poz = isset($row['poznamka']) && trim((string)$row['poznamka']) !== '' ? trim((string)$row['poznamka']) : null;

                // prázdné řádky ignoruj
                if ($sid === null && $v === null && $c === null && $p === null && $poz === null) continue;

                $out[] = [
                    'typ' => $typ,
                    'sportovec_id' => $sid,
                    'vzdalenost' => $v,
                    'cas' => $c,
                    'prevod' => ($typ === 'kolo' ? $p : null),
                    'cvik_id' => null,
                    'segment_id' => null,
                    'vaha' => null,
                    'opakovani' => null,
                    'rpe' => null,
                    'poznamka' => $poz,
                ];
                continue;
            }

            if ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
                $segId = isset($row['segment_id']) && ctype_digit((string)$row['segment_id']) ? (int)$row['segment_id'] : null;
                $c = isset($row['cas']) && trim((string)$row['cas']) !== '' ? trim((string)$row['cas']) : null;
                $poz = isset($row['poznamka']) && trim((string)$row['poznamka']) !== '' ? trim((string)$row['poznamka']) : null;

                if ($sid === null && $segId === null && $c === null && $poz === null) continue;

                $out[] = [
                    'typ' => $typ,
                    'sportovec_id' => $sid,
                    'vzdalenost' => null,
                    'cas' => $c,
                    'prevod' => null,
                    'cvik_id' => null,
                    'segment_id' => $segId,
                    'vaha' => null,
                    'opakovani' => null,
                    'rpe' => null,
                    'poznamka' => $poz,
                ];
                continue;
            }

            if ($typ === 'posilovna') {
                $cvik = isset($row['cvik_id']) && ctype_digit((string)$row['cvik_id']) ? (int)$row['cvik_id'] : null;
                $vaha = isset($row['vaha']) && $row['vaha'] !== '' ? (float)str_replace(',', '.', (string)$row['vaha']) : null;
                $op   = isset($row['opakovani']) && $row['opakovani'] !== '' ? (int)$row['opakovani'] : null;
                $rpe  = isset($row['rpe']) && trim((string)$row['rpe']) !== '' ? trim((string)$row['rpe']) : null;
                $poz  = isset($row['poznamka']) && trim((string)$row['poznamka']) !== '' ? trim((string)$row['poznamka']) : null;

                if ($sid === null && $cvik === null && $vaha === null && $op === null && $rpe === null && $poz === null) continue;

                $out[] = [
                    'typ' => 'posilovna',
                    'sportovec_id' => $sid,
                    'vzdalenost' => null,
                    'cas' => null,
                    'prevod' => null,
                    'cvik_id' => $cvik,
                    'segment_id' => null,
                    'vaha' => $vaha,
                    'opakovani' => $op,
                    'rpe' => $rpe,
                    'poznamka' => $poz,
                ];
                continue;
            }
        }

        return $out;
    }

    // Varianta B (fallback): klasická pole mereni_typ[] + paralelní hodnoty
    if (empty($post['mereni_typ']) || !is_array($post['mereni_typ'])) {
        return [];
    }

    $typy = $post['mereni_typ'];

    $sportovecIds = $post['mereni_sportovec_id'] ?? [];

    $vzdalenost = $post['mereni_vzdalenost'] ?? $post['vzdalenost'] ?? [];
    $cas        = $post['mereni_cas']        ?? $post['cas']        ?? [];
    $prevod     = $post['mereni_prevod']     ?? $post['prevod']     ?? [];

    $cvikId     = $post['cvik_id']           ?? $post['mereni_cvik_id'] ?? [];
    $vaha       = $post['vaha']              ?? [];
    $opakovani  = $post['opakovani']         ?? [];
    $rpe        = $post['rpe']               ?? [];
    $pozP       = $post['poznamka_posilovna'] ?? $post['poznamka_cviku'] ?? [];

    $out = [];
    $n = count($typy);

    for ($i = 0; $i < $n; $i++) {
        $typ = trim((string)($typy[$i] ?? ''));
        if ($typ === '') continue;

        $sid = null;
        if (isset($sportovecIds[$i]) && ctype_digit((string)$sportovecIds[$i])) {
            $sidInt = (int)$sportovecIds[$i];
            if ($sidInt > 0) $sid = $sidInt;
        }

        if ($typ === 'kolo' || $typ === 'beh') {
            $row = [
                'typ' => $typ,
                'sportovec_id' => $sid,
                'vzdalenost' => isset($vzdalenost[$i]) && $vzdalenost[$i] !== ''
                    ? (float)str_replace(',', '.', (string)$vzdalenost[$i])
                    : null,
                'cas' => isset($cas[$i]) && trim((string)$cas[$i]) !== '' ? trim((string)$cas[$i]) : null,
                'prevod' => null,
                'cvik_id' => null,
                'vaha' => null,
                'opakovani' => null,
                'rpe' => null,
                'poznamka' => null,
            ];

            if ($typ === 'kolo') {
                $p = isset($prevod[$i]) ? trim((string)$prevod[$i]) : '';
                if ($p !== '') $row['prevod'] = $p;
            }

            if ($row['vzdalenost'] === null && $row['cas'] === null && $row['prevod'] === null && $row['sportovec_id'] === null) {
                continue;
            }

            $out[] = $row;
            continue;
        }

        if ($typ === 'posilovna') {
            $row = [
                'typ' => 'posilovna',
                'sportovec_id' => $sid,
                'vzdalenost' => null,
                'cas' => null,
                'prevod' => null,
                'cvik_id' => (isset($cvikId[$i]) && ctype_digit((string)$cvikId[$i])) ? (int)$cvikId[$i] : null,
                'vaha' => isset($vaha[$i]) && $vaha[$i] !== ''
                    ? (float)str_replace(',', '.', (string)$vaha[$i])
                    : null,
                'opakovani' => (isset($opakovani[$i]) && $opakovani[$i] !== '') ? (int)$opakovani[$i] : null,
                'rpe' => isset($rpe[$i]) && trim((string)$rpe[$i]) !== '' ? trim((string)$rpe[$i]) : null,
                'poznamka' => isset($pozP[$i]) && trim((string)$pozP[$i]) !== '' ? trim((string)$pozP[$i]) : null,
            ];

            if ($row['sportovec_id'] === null && $row['cvik_id'] === null && $row['vaha'] === null && $row['opakovani'] === null && $row['rpe'] === null && $row['poznamka'] === null) {
                continue;
            }

            $out[] = $row;
            continue;
        }
    }

    return $out;
}

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
$mereniRows  = buildMereniRowsFromPost($_POST);

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
        $stmtInsM = $pdo->prepare("
            INSERT INTO mereni_zaznamy
                (typ, sportovec_id, vzdalenost, cas, prevod, cvik_id, segment_id, vaha, opakovani, rpe, poznamka)
            VALUES
                (:typ, :sportovec_id, :vzdalenost, :cas, :prevod, :cvik_id, :segment_id, :vaha, :opakovani, :rpe, :poznamka)
        ");

        $stmtLinkM = $pdo->prepare("
            INSERT INTO zavod_mereni (zavod_id, mereni_id, poradi)
            VALUES (:zavod_id, :mereni_id, :poradi)
        ");

        $poradi = 0;
        foreach ($mereniRows as $row) {
            $stmtInsM->execute([
                ':typ'          => $row['typ'],
                ':sportovec_id' => $row['sportovec_id'],
                ':vzdalenost'   => $row['vzdalenost'],
                ':cas'          => $row['cas'],
                ':prevod'       => $row['prevod'],
                ':cvik_id'      => $row['cvik_id'],
                ':segment_id'   => $row['segment_id'] ?? null,
                ':vaha'         => $row['vaha'],
                ':opakovani'    => $row['opakovani'],
                ':rpe'          => $row['rpe'],
                ':poznamka'     => $row['poznamka'],
            ]);

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
