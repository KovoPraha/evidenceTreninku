<?php
session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('sprava_zavodu')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function softDeleteUpload(string $dir, string $filename): void {
    $base = basename($filename);
    if ($base === '' || str_starts_with($base, 'smazano_')) return;
    $realDir = realpath($dir);
    if ($realDir === false) return;

    $source = $realDir . DIRECTORY_SEPARATOR . $base;
    if (!is_file($source)) return;

    $target = $realDir . DIRECTORY_SEPARATOR . 'smazano_' . $base;
    if (is_file($target)) {
        $target = $realDir . DIRECTORY_SEPARATOR . 'smazano_' . date('Ymd_His') . '_' . $base;
    }
    @rename($source, $target);
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
    $backId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    header('Location: ' . ($backId > 0 ? 'edit_zavod_form.php?id=' . $backId : 'sprava_zavodu.php'));
    exit;
}

// ── Validace ID ──────────────────────────────────────────────────────────────
$zavodId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($zavodId <= 0) {
    $_SESSION['flash_error'] = 'Neplatné ID závodu.';
    header('Location: sprava_zavodu.php');
    exit;
}

// ── Vstupní data ─────────────────────────────────────────────────────────────
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

// Delete IDs for photos / imports
$smazatFotky   = array_map('intval', (array)($_POST['smazat_fotku'] ?? []));
$smazatImports = array_map('intval', (array)($_POST['smazat_import'] ?? []));

// ── buildMereniRowsFromPost ───────────────────────────────────────────────────
function buildMereniRowsFromPost(array $post): array
{
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
                $v   = isset($row['vzdalenost']) && $row['vzdalenost'] !== '' ? (float)str_replace(',', '.', (string)$row['vzdalenost']) : null;
                $c   = isset($row['cas']) && trim((string)$row['cas']) !== '' ? trim((string)$row['cas']) : null;
                $p   = isset($row['prevod']) && trim((string)$row['prevod']) !== '' ? trim((string)$row['prevod']) : null;
                $poz = isset($row['poznamka']) && trim((string)$row['poznamka']) !== '' ? trim((string)$row['poznamka']) : null;

                if ($sid === null && $v === null && $c === null && $p === null && $poz === null) continue;

                $out[] = [
                    'typ'          => $typ,
                    'sportovec_id' => $sid,
                    'vzdalenost'   => $v,
                    'cas'          => $c,
                    'prevod'       => ($typ === 'kolo' ? $p : null),
                    'cvik_id'      => null,
                    'segment_id'   => null,
                    'vaha'         => null,
                    'opakovani'    => null,
                    'rpe'          => null,
                    'poznamka'     => $poz,
                ];
                continue;
            }

            if ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
                $segId = isset($row['segment_id']) && ctype_digit((string)$row['segment_id']) ? (int)$row['segment_id'] : null;
                $c     = isset($row['cas']) && trim((string)$row['cas']) !== '' ? trim((string)$row['cas']) : null;
                $poz   = isset($row['poznamka']) && trim((string)$row['poznamka']) !== '' ? trim((string)$row['poznamka']) : null;

                if ($sid === null && $segId === null && $c === null && $poz === null) continue;

                $out[] = [
                    'typ'          => $typ,
                    'sportovec_id' => $sid,
                    'vzdalenost'   => null,
                    'cas'          => $c,
                    'prevod'       => null,
                    'cvik_id'      => null,
                    'segment_id'   => $segId,
                    'vaha'         => null,
                    'opakovani'    => null,
                    'rpe'          => null,
                    'poznamka'     => $poz,
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
                    'typ'          => 'posilovna',
                    'sportovec_id' => $sid,
                    'vzdalenost'   => null,
                    'cas'          => null,
                    'prevod'       => null,
                    'cvik_id'      => $cvik,
                    'segment_id'   => null,
                    'vaha'         => $vaha,
                    'opakovani'    => $op,
                    'rpe'          => $rpe,
                    'poznamka'     => $poz,
                ];
                continue;
            }
        }

        return $out;
    }

    // Fallback: classic parallel arrays (varianta B)
    if (empty($post['mereni_typ']) || !is_array($post['mereni_typ'])) {
        return [];
    }

    $typy         = $post['mereni_typ'];
    $sportovecIds = $post['mereni_sportovec_id'] ?? [];
    $vzdalenost   = $post['mereni_vzdalenost'] ?? $post['vzdalenost'] ?? [];
    $cas          = $post['mereni_cas']        ?? $post['cas']        ?? [];
    $prevod       = $post['mereni_prevod']     ?? $post['prevod']     ?? [];
    $cvikId       = $post['cvik_id']           ?? $post['mereni_cvik_id'] ?? [];
    $vaha         = $post['vaha']              ?? [];
    $opakovani    = $post['opakovani']         ?? [];
    $rpe          = $post['rpe']               ?? [];
    $pozP         = $post['poznamka_posilovna'] ?? $post['poznamka_cviku'] ?? [];

    $out = [];
    $n   = count($typy);
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
                'typ'          => $typ,
                'sportovec_id' => $sid,
                'vzdalenost'   => isset($vzdalenost[$i]) && $vzdalenost[$i] !== '' ? (float)str_replace(',', '.', (string)$vzdalenost[$i]) : null,
                'cas'          => isset($cas[$i]) && trim((string)$cas[$i]) !== '' ? trim((string)$cas[$i]) : null,
                'prevod'       => null,
                'cvik_id'      => null,
                'segment_id'   => null,
                'vaha'         => null,
                'opakovani'    => null,
                'rpe'          => null,
                'poznamka'     => null,
            ];
            if ($typ === 'kolo') {
                $p = isset($prevod[$i]) ? trim((string)$prevod[$i]) : '';
                if ($p !== '') $row['prevod'] = $p;
            }
            if ($row['vzdalenost'] === null && $row['cas'] === null && $row['prevod'] === null && $row['sportovec_id'] === null) continue;
            $out[] = $row;
            continue;
        }

        if ($typ === 'posilovna') {
            $row = [
                'typ'          => 'posilovna',
                'sportovec_id' => $sid,
                'vzdalenost'   => null,
                'cas'          => null,
                'prevod'       => null,
                'cvik_id'      => (isset($cvikId[$i]) && ctype_digit((string)$cvikId[$i])) ? (int)$cvikId[$i] : null,
                'segment_id'   => null,
                'vaha'         => isset($vaha[$i]) && $vaha[$i] !== '' ? (float)str_replace(',', '.', (string)$vaha[$i]) : null,
                'opakovani'    => (isset($opakovani[$i]) && $opakovani[$i] !== '') ? (int)$opakovani[$i] : null,
                'rpe'          => isset($rpe[$i]) && trim((string)$rpe[$i]) !== '' ? trim((string)$rpe[$i]) : null,
                'poznamka'     => isset($pozP[$i]) && trim((string)$pozP[$i]) !== '' ? trim((string)$pozP[$i]) : null,
            ];
            if ($row['sportovec_id'] === null && $row['cvik_id'] === null && $row['vaha'] === null && $row['opakovani'] === null && $row['rpe'] === null && $row['poznamka'] === null) continue;
            $out[] = $row;
            continue;
        }
    }

    return $out;
}

$mereniRows = buildMereniRowsFromPost($_POST);

// ── Upload helpers ────────────────────────────────────────────────────────────
$uploadDirPhotos  = __DIR__ . '/nahrane_zavody/';
$uploadDirResults = __DIR__ . '/nahrane_zavody/results/';

$fotkyNove   = [];
$vysledkyNove = [];

if (!empty($_FILES['fotky']['name'][0])) {
    if (!is_dir($uploadDirPhotos)) @mkdir($uploadDirPhotos, 0755, true);

    $allowedImgMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedImgExts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    foreach ($_FILES['fotky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['fotky']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowedImgMimes, true)) continue;

        $ext = strtolower(pathinfo($_FILES['fotky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedImgExts, true)) continue;

        $name = 'zavod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDirPhotos . $name)) {
            $fotkyNove[] = $name;
        }
    }
}

if (!empty($_FILES['vysledky']['name'][0])) {
    if (!is_dir($uploadDirResults)) @mkdir($uploadDirResults, 0755, true);

    $allowedFileMimes = [
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    $allowedFileExts = ['pdf', 'xls', 'xlsx'];

    foreach ($_FILES['vysledky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['vysledky']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowedFileMimes, true)) continue;

        $ext = strtolower(pathinfo($_FILES['vysledky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedFileExts, true)) continue;

        $origName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($_FILES['vysledky']['name'][$i]));
        $name     = 'vysledky_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $origName;
        if (move_uploaded_file($tmp, $uploadDirResults . $name)) {
            $vysledkyNove[] = ['soubor' => $name, 'typ' => $ext];
        }
    }
}

// ── Transakce ─────────────────────────────────────────────────────────────────
try {
    if ($popis === '') throw new Exception('Popis závodu nesmí být prázdný.');
    if ($datum === '') throw new Exception('Chybí datum závodu.');

    foreach ($mereniRows as $i => $row) {
        if (empty($row['sportovec_id']) || (int)$row['sportovec_id'] <= 0) {
            throw new Exception('U měření na řádku ' . ($i + 1) . ' je potřeba vybrat sportovce ze seznamu.');
        }
    }

    $pdo->beginTransaction();

    // 1) UPDATE zavody
    $pdo->prepare(
        "UPDATE zavody
         SET datum = :datum, kategorie = :kategorie, popis = :popis,
             poznamka = :poznamka, url_vysledky = :url_vysledky
         WHERE id = :id"
    )->execute([
        ':datum'        => $datum,
        ':kategorie'    => $kategorie,
        ':popis'        => $popis,
        ':poznamka'     => ($poznamka !== '' ? $poznamka : null),
        ':url_vysledky' => $urlVysledky,
        ':id'           => $zavodId,
    ]);

    // 2) Trenéři — přihlášený vždy zahrnut
    if (!in_array($trenId, $trenere, true)) {
        array_unshift($trenere, $trenId);
    }
    $trenere = array_values(array_unique(array_filter($trenere)));

    $pdo->prepare('DELETE FROM zavod_trener WHERE zavod_id = ?')->execute([$zavodId]);
    $stmtT = $pdo->prepare('INSERT IGNORE INTO zavod_trener (zavod_id, trener_id) VALUES (?, ?)');
    foreach ($trenere as $trid) {
        if ($trid <= 0) continue;
        $stmtT->execute([$zavodId, $trid]);
    }

    // 3) Skupina
    $pdo->prepare('DELETE FROM zavod_skupina WHERE zavod_id = ?')->execute([$zavodId]);
    if ($skupinaId !== null) {
        $pdo->prepare('INSERT IGNORE INTO zavod_skupina (zavod_id, skupina_id) VALUES (?, ?)')
            ->execute([$zavodId, $skupinaId]);
    }

    // 4) Podskupiny
    $pdo->prepare('DELETE FROM zavod_podskupina WHERE zavod_id = ?')->execute([$zavodId]);
    if (!empty($podskupiny)) {
        $stmtPs = $pdo->prepare('INSERT IGNORE INTO zavod_podskupina (zavod_id, podskupina_id) VALUES (?, ?)');
        foreach ($podskupiny as $psid) {
            if ($psid <= 0) continue;
            $stmtPs->execute([$zavodId, $psid]);
        }
    }

    // 5) Smazání označených fotek
    if (!empty($smazatFotky)) {
        $stmtGetF = $pdo->prepare('SELECT soubor FROM zavod_fotka WHERE id = ? AND zavod_id = ?');
        $stmtDelF = $pdo->prepare('DELETE FROM zavod_fotka WHERE id = ? AND zavod_id = ?');
        foreach ($smazatFotky as $fid) {
            if ($fid <= 0) continue;
            $stmtGetF->execute([$fid, $zavodId]);
            $soubor = $stmtGetF->fetchColumn();
            if ($soubor) {
                softDeleteUpload($uploadDirPhotos, (string)$soubor);
            }
            $stmtDelF->execute([$fid, $zavodId]);
        }
    }

    // 6) Smazání označených importů
    if (!empty($smazatImports)) {
        $stmtGetI = $pdo->prepare('SELECT soubor FROM zavod_import WHERE id = ? AND zavod_id = ?');
        $stmtDelI = $pdo->prepare('DELETE FROM zavod_import WHERE id = ? AND zavod_id = ?');
        foreach ($smazatImports as $iid) {
            if ($iid <= 0) continue;
            $stmtGetI->execute([$iid, $zavodId]);
            $soubor = $stmtGetI->fetchColumn();
            if ($soubor) {
                softDeleteUpload($uploadDirResults, (string)$soubor);
            }
            $stmtDelI->execute([$iid, $zavodId]);
        }
    }

    // 7) Účastníci — sesouhlas set: odeber jen chybějící, přidej nové.
    // NEMAZAT všechny a znovu vkládat — smazalo by výsledky (poradi/cas/body/klub) importované
    // z výsledkové listiny u závodníků, kteří v sestavě zůstávají.
    $novaSada = [];
    if ($ucastnici !== '') {
        foreach (array_filter(array_map('trim', explode(',', $ucastnici))) as $part) {
            $colon = strpos($part, ':');
            if ($colon !== false) {
                $sid = (int)substr($part, 0, $colon);
                if ($sid > 0) $novaSada[$sid] = true;
            }
        }
    }
    // Odeber interní účastníky, kteří v nové sestavě nejsou
    $stExist = $pdo->prepare('SELECT sportovec_id FROM zavod_sportovec WHERE zavod_id = ? AND sportovec_id IS NOT NULL');
    $stExist->execute([$zavodId]);
    $stDelOne = $pdo->prepare('DELETE FROM zavod_sportovec WHERE zavod_id = ? AND sportovec_id = ?');
    foreach ($stExist->fetchAll(PDO::FETCH_COLUMN) as $existId) {
        if (!isset($novaSada[(int)$existId])) {
            $stDelOne->execute([$zavodId, (int)$existId]);
        }
    }
    // Přidej nové — INSERT IGNORE nezmění existující řádky (výsledky zůstanou)
    $stmtSp = $pdo->prepare('INSERT IGNORE INTO zavod_sportovec (zavod_id, sportovec_id) VALUES (?, ?)');
    foreach (array_keys($novaSada) as $sid) {
        $stmtSp->execute([$zavodId, $sid]);
    }

    // 8) Nové fotky
    if (!empty($fotkyNove)) {
        $stmtF = $pdo->prepare('INSERT INTO zavod_fotka (zavod_id, soubor) VALUES (?, ?)');
        foreach ($fotkyNove as $soubor) {
            $stmtF->execute([$zavodId, $soubor]);
        }
    }

    // 9) Nové soubory výsledků
    if (!empty($vysledkyNove)) {
        $stmtI = $pdo->prepare('INSERT INTO zavod_import (zavod_id, soubor, typ) VALUES (?, ?, ?)');
        foreach ($vysledkyNove as $imp) {
            $stmtI->execute([$zavodId, $imp['soubor'], $imp['typ']]);
        }
    }

    // 10) Měření — smaž staré záznamy a znovu vlož
    // Nejdříve získej ID propojených mereni_zaznamy před smazáním
    $oldMereniIds = $pdo->prepare('SELECT mereni_id FROM zavod_mereni WHERE zavod_id = ?');
    $oldMereniIds->execute([$zavodId]);
    $oldIds = $oldMereniIds->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM zavod_mereni WHERE zavod_id = ?')->execute([$zavodId]);

    // Smaž orphan mereni_zaznamy (které nejsou napojeny na žádný trénink/závod)
    if (!empty($oldIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($oldIds), '?'));
        // Smaž záznamy, které NEJSOU v trenink_mereni (cascade pro závod byl výše)
        $pdo->prepare(
            "DELETE FROM mereni_zaznamy
             WHERE id IN ($inPlaceholders)
               AND id NOT IN (SELECT mereni_id FROM trenink_mereni)"
        )->execute($oldIds);
    }

    // Vlož nová měření
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

    $_SESSION['flash_success'] = 'Závod byl úspěšně aktualizován.';
    header('Location: zavod_detail.php?id=' . $zavodId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update_zavod.php error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Chyba při ukládání závodu: ' . $e->getMessage();
    header('Location: edit_zavod_form.php?id=' . $zavodId);
    exit;
}
