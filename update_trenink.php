<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';

if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/sports_measurement_input.php';
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný CSRF token.');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }


// --------------------
// Vstupy
// --------------------
$treninkId = $_POST['id'] ?? null;
if (!$treninkId || !ctype_digit((string)$treninkId)) die('Neplatn� ID tr�ninku.');
$treninkId = (int)$treninkId;

$currentTrener = (int)$_SESSION['trener_id'];
// Cizí tréninky smí editovat jen správce/admin; řadový trenér projde ownership checkem níže.
// (canAccess('formular') je true pro každého trenéra → ownership check by se nikdy nespustil.)
$isAdmin = roleAtLeast('hlavni');

$datum      = $_POST['datum'] ?? '';
$delka      = isset($_POST['delka']) ? (float)str_replace(',', '.', (string)$_POST['delka']) : 0.0;
$kategorie  = $_POST['kategorie'] ?? null;

$skupinaId  = $_POST['skupina_id'] ?? null;
$podskupiny = $_POST['podskupina_id'] ?? [];

$trenere    = $_POST['trenere'] ?? [];
$ucastnici  = trim((string)($_POST['ucastnici'] ?? ''));

$napln      = trim((string)($_POST['napln'] ?? ''));
$poznamka   = trim((string)($_POST['poznamka'] ?? ''));

try {
    $mereniRows = sportsMeasurementRowsFromPost($_POST);
} catch (InvalidArgumentException $exception) {
    $_SESSION['flash_error'] = $exception->getMessage();
    header('Location: edit_trenink.php?id=' . $treninkId);
    exit;
}

// Opr�vn�n�
if (!$isAdmin) {
    $stmtAuth = $pdo->prepare('SELECT 1 FROM trenink_trener WHERE trenink_id = :tid AND trener_id = :uid LIMIT 1');
    $stmtAuth->execute([':tid' => $treninkId, ':uid' => $currentTrener]);
    if (!$stmtAuth->fetchColumn()) die('Nem�te opr�vn�n� tento tr�nink upravit.');
}

try {
    if ($datum === '') throw new Exception('Chyb� datum.');
    if ($delka < 0) throw new Exception('Neplatn� d�lka.');

    // Validace: m��en� mus� m� t vybran�ho sportovce
    foreach ($mereniRows as $i => $row) {
        if (empty($row['sportovec_id']) || (int)$row['sportovec_id'] <= 0) {
            throw new Exception('U m��en� na ��dku ' . ($i + 1) . ' je pot�eba vybrat sportovce ze seznamu (na�pt�va�).');
        }
    }

    // --------------------
    // Obr�zky: existing + nov� - odebran�
    // --------------------
    // Zdroj pravdy jsou obrázky v DB, ne klientův existing_obrazky (ten lze podvrhnout
    // libovolnými cestami — např. ../uploads/uctenka.jpg).
    $stDbImg = $pdo->prepare('SELECT obrazky FROM treninky WHERE id = ?');
    $stDbImg->execute([$treninkId]);
    $dbImgJson = $stDbImg->fetchColumn();
    $dbImg = $dbImgJson ? json_decode((string)$dbImgJson, true) : [];
    if (!is_array($dbImg)) $dbImg = [];

    $remove = $_POST['remove_obrazky'] ?? [];
    if (!is_array($remove)) $remove = [];
    $remove = array_map('strval', $remove);

    $safeRel = function ($p): ?string {
        $rel = ltrim(str_replace('\\', '/', (string)$p), '/');
        return (strpos($rel, 'nahrane_obrazky/') === 0 && strpos($rel, '..') === false) ? $rel : null;
    };

    // Zůstávající = DB obrázky mimo odebrané (a jen validní cesty)
    $existing = [];
    foreach ($dbImg as $p) {
        if (in_array((string)$p, $remove, true)) continue;
        if ($safeRel($p) !== null) $existing[] = (string)$p;
    }
    // Soft-delete odebraných souborů (konvence: prefix smazano_)
    foreach ($remove as $p) {
        $rel = $safeRel($p);
        if ($rel !== null) {
            $path = __DIR__ . '/' . $rel;
            if (is_file($path)) @rename($path, dirname($path) . '/smazano_' . basename($path));
        }
    }

    $newPaths = [];
    if (!empty($_FILES['obrazky']['name'][0])) {
        $uploadDir = __DIR__ . '/nahrane_obrazky/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        foreach ($_FILES['obrazky']['tmp_name'] as $i => $tmp) {
            if (!isset($_FILES['obrazky']['error'][$i]) || $_FILES['obrazky']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($_FILES['obrazky']['name'][$i] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) continue;

            $name = 'trenink_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $name;

            if (move_uploaded_file($tmp, $dest)) {
                $newPaths[] = 'nahrane_obrazky/' . $name;
            }
        }
    }

    $allImages = array_values(array_filter(array_merge($existing, $newPaths)));
    $obrazkyJson = !empty($allImages) ? json_encode($allImages, JSON_UNESCAPED_UNICODE) : null;

    // --------------------
    // Ulo�en�
    // --------------------
    $pdo->beginTransaction();

    // UPDATE treninky (robustn�: s kategorie / bez)
    try {
        $stmt = $pdo->prepare(
            'UPDATE treninky
             SET datum = :datum,
                 delka = :delka,
                 kategorie = :kategorie,
                 napln = :napln,
                 poznamka = :poznamka,
                 obrazky = :obrazky
             WHERE id = :id'
        );
        $stmt->execute([
            ':datum' => $datum,
            ':delka' => $delka,
            ':kategorie' => ($kategorie === '' ? null : $kategorie),
            ':napln' => ($napln === '' ? '' : $napln),
            ':poznamka' => ($poznamka === '' ? null : $poznamka),
            ':obrazky' => $obrazkyJson,
            ':id' => $treninkId,
        ]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare(
            'UPDATE treninky
             SET datum = :datum,
                 delka = :delka,
                 napln = :napln,
                 poznamka = :poznamka,
                 obrazky = :obrazky
             WHERE id = :id'
        );
        $stmt->execute([
            ':datum' => $datum,
            ':delka' => $delka,
            ':napln' => ($napln === '' ? '' : $napln),
            ':poznamka' => ($poznamka === '' ? null : $poznamka),
            ':obrazky' => $obrazkyJson,
            ':id' => $treninkId,
        ]);
    }

    // skupina
    $pdo->prepare('DELETE FROM trenink_skupina WHERE trenink_id = ?')->execute([$treninkId]);
    if ($skupinaId && ctype_digit((string)$skupinaId)) {
        $pdo->prepare('INSERT INTO trenink_skupina (trenink_id, skupina_id) VALUES (?, ?)')->execute([$treninkId, (int)$skupinaId]);
    }

    // podskupiny
    $pdo->prepare('DELETE FROM trenink_podskupina WHERE trenink_id = ?')->execute([$treninkId]);
    if (is_array($podskupiny)) {
        $stmtPs = $pdo->prepare('INSERT INTO trenink_podskupina (trenink_id, podskupina_id) VALUES (?, ?)');
        foreach ($podskupiny as $psid) {
            if (!ctype_digit((string)$psid)) continue;
            $stmtPs->execute([$treninkId, (int)$psid]);
        }
    }

    // tren��i (v�dy aktu�ln�; ne-admin nesm� m�nit ciz�)
    $pdo->prepare('DELETE FROM trenink_trener WHERE trenink_id = ?')->execute([$treninkId]);
    if (!is_array($trenere)) $trenere = [];
    $trenere = array_map('intval', $trenere);

    if (!$isAdmin) {
        $trenere = [$currentTrener];
    } else {
        if (!in_array($currentTrener, $trenere, true)) array_unshift($trenere, $currentTrener);
        $trenere = array_values(array_unique($trenere));
    }

    $stmtT = $pdo->prepare('INSERT INTO trenink_trener (trenink_id, trener_id) VALUES (?, ?)');
    foreach ($trenere as $tid) {
        if ($tid <= 0) continue;
        $stmtT->execute([$treninkId, (int)$tid]);
    }

    // ��astn�ci � p�ep� vazby (nezakl�dej sportovce)
    $pdo->prepare('DELETE FROM trenink_sportovec WHERE trenink_id = ?')->execute([$treninkId]);
    $stmtLinkSp = $pdo->prepare('INSERT IGNORE INTO trenink_sportovec (trenink_id, sportovec_id) VALUES (?, ?)');

    if ($ucastnici !== '') {
        foreach (array_filter(array_map('trim', explode(',', $ucastnici))) as $participant) {
            if (preg_match('/^(\d+):(.+)$/', $participant, $m)) {
                $sid = (int)$m[1];
                if ($sid > 0) $stmtLinkSp->execute([$treninkId, $sid]);
            }
        }
    }

    // m��en� � sma� star� nav�zan� na tr�nink (v�etn� z�znam�)
    $stmtOld = $pdo->prepare('SELECT mereni_id FROM trenink_mereni WHERE trenink_id = ?');
    $stmtOld->execute([$treninkId]);
    $oldIds = array_map('intval', $stmtOld->fetchAll(PDO::FETCH_COLUMN));

    $pdo->prepare('DELETE FROM trenink_mereni WHERE trenink_id = ?')->execute([$treninkId]);
    if (!empty($oldIds)) {
        $in = implode(',', array_fill(0, count($oldIds), '?'));
        $pdo->prepare("DELETE FROM mereni_zaznamy WHERE id IN ($in)")->execute($oldIds);
    }

    // vlo� nov� m��en�
    if (!empty($mereniRows)) {
        $stmtInsM = $pdo->prepare(sportsMeasurementInsertSql());

        $stmtLinkM = $pdo->prepare("
            INSERT INTO trenink_mereni (trenink_id, mereni_id, poradi)
            VALUES (:trenink_id, :mereni_id, :poradi)
        ");

        $poradi = 0;
        foreach ($mereniRows as $row) {
            $stmtInsM->execute(sportsMeasurementInsertParameters($row));

            $mid = (int)$pdo->lastInsertId();
            $stmtLinkM->execute([
                ':trenink_id' => $treninkId,
                ':mereni_id' => $mid,
                ':poradi' => $poradi++
            ]);
        }
    }

    // tagy � p�epi� vazby + umo�ni vytvo�it nov�
    $pdo->prepare('DELETE FROM trenink_tag WHERE trenink_id = ?')->execute([$treninkId]);
    $tagIds = [];

    if (!empty($_POST['tagy_json'])) {
        $decoded = json_decode((string)$_POST['tagy_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $t) {
                if (!is_array($t)) continue;

                $tid = $t['id'] ?? null;
                $name = isset($t['name']) ? trim((string)$t['name']) : '';

                if ($tid !== null && ctype_digit((string)$tid)) {
                    $tagIds[] = (int)$tid;
                    continue;
                }

                if ($name === '') continue;

                $stmtFindTag = $pdo->prepare('SELECT id FROM tagy WHERE nazev = ? LIMIT 1');
                $stmtFindTag->execute([$name]);
                $ex = (int)($stmtFindTag->fetchColumn() ?: 0);

                if ($ex > 0) {
                    $tagIds[] = $ex;
                } else {
                    $stmtInsTag = $pdo->prepare('INSERT INTO tagy (nazev) VALUES (?)');
                    $stmtInsTag->execute([$name]);
                    $tagIds[] = (int)$pdo->lastInsertId();
                }
            }
        }
    }

    $tagIds = array_values(array_unique(array_filter($tagIds)));
    if (!empty($tagIds)) {
        $stmtLinkTag = $pdo->prepare('INSERT INTO trenink_tag (trenink_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tid) $stmtLinkTag->execute([$treninkId, $tid]);
    }

    $pdo->commit();
    header('Location: moje_treninky.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Chyba p�i aktualizaci: ' . $e->getMessage();
    header('Location: edit_trenink.php?id=' . $treninkId);
    exit;
}
