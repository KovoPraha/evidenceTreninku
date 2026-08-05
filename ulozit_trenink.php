<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();

if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/includes/funkce.php';
require_once __DIR__ . '/includes/training_roster_bridge.php';
require_once __DIR__ . '/includes/sports_measurement_input.php';

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Neplatný CSRF token.');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --------------------
// Vstupy
// --------------------
$trenerId   = (int)$_SESSION['trener_id'];

$datum      = $_POST['datum'] ?? '';
$delka      = isset($_POST['delka']) ? (float)str_replace(',', '.', (string)$_POST['delka']) : 0.0;
$kategorie  = $_POST['kategorie'] ?? null;
// Validace proti ENUM whitelistu — nevalidní hodnota by jinak shodila INSERT do tichého
// fallbacku, který kategorii zahodí bez varování
$kategorieValid = ['silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani'];
if ($kategorie !== null && $kategorie !== '' && !in_array($kategorie, $kategorieValid, true)) {
    $kategorie = null;
}

$skupinaId  = $_POST['skupina_id'] ?? null;
$podskupiny = $_POST['podskupina_id'] ?? [];

$trenere    = $_POST['trenere'] ?? [];
$ucastnici  = trim((string)($_POST['ucastnici'] ?? ''));
$planId     = (int)($_POST['plan_id'] ?? 0);

$napln      = trim((string)($_POST['napln'] ?? ''));
$poznamka   = trim((string)($_POST['poznamka'] ?? ''));

$tagy       = $_POST['tagy'] ?? [];

try {
    $mereniRows = sportsMeasurementRowsFromPost($_POST);
} catch (InvalidArgumentException $exception) {
    $_SESSION['flash_error'] = $exception->getMessage();
    header('Location: formular.php');
    exit;
}

// --------------------
// Obrázky
// --------------------
$imagePaths = [];
if (!empty($_FILES['obrazky']['name'][0])) {
    $uploadDir = __DIR__ . '/nahrane_obrazky/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

    foreach ($_FILES['obrazky']['tmp_name'] as $i => $tmp) {
        if ($_FILES['obrazky']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($_FILES['obrazky']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) continue;

        $name = 'trenink_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . $name;

        if (move_uploaded_file($tmp, $dest)) {
            $imagePaths[] = 'nahrane_obrazky/' . $name;
        }
    }
}
$obrazky = !empty($imagePaths) ? json_encode($imagePaths, JSON_UNESCAPED_UNICODE) : null;

// --------------------
// Uložení
// --------------------
try {
    if ($datum === '') throw new Exception('Chybí datum.');
    if ($delka < 0) throw new Exception('Neplatná délka.');

    // Validace: měření musí mít vybraného sportovce
    foreach ($mereniRows as $i => $row) {
        if (empty($row['sportovec_id']) || (int)$row['sportovec_id'] <= 0) {
            throw new Exception('U měření na řádku ' . ($i + 1) . ' je potřeba vybrat sportovce ze seznamu (našeptávač).');
        }
    }

    $pdo->beginTransaction();

    $planRow=null;
    if($planId>0){
        $planSql='SELECT id,trener_id,datum,stav FROM planovane_treninky WHERE id=?';
        if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$planSql.=' FOR UPDATE';
        $planStatement=$pdo->prepare($planSql);$planStatement->execute([$planId]);$planRow=$planStatement->fetch(PDO::FETCH_ASSOC);
        if(!$planRow||(string)$planRow['stav']!=='planovany')throw new TrainingRosterBridgeException('Plánovaný trénink už není dostupný pro zápis evidence.');
        if((int)$planRow['trener_id']!==$trenerId&&!roleAtLeast('hlavni'))throw new TrainingRosterBridgeException('Cizí plánovaný trénink nemůžete zaevidovat.');
        if((string)$planRow['datum']!==$datum)throw new TrainingRosterBridgeException('Datum evidence musí odpovídat datu plánovaného tréninku.');
    }

    $mereniRaw = null;
    $treninkId = 0;

    // 1) INSERT treninky (robustně: s kategorie + s mereni_json / bez)
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO treninky (datum, delka, kategorie, napln, poznamka, obrazky, mereni, mereni_json)
             VALUES (:datum, :delka, :kategorie, :napln, :poznamka, :obrazky, :mereni, :mereni_json)'
        );
        $stmt->execute([
            ':datum'       => $datum,
            ':delka'       => $delka,
            ':kategorie'   => ($kategorie === '' ? null : $kategorie),
            ':napln'       => ($napln === '' ? '' : $napln),
            ':poznamka'    => ($poznamka === '' ? null : $poznamka),
            ':obrazky'     => $obrazky,
            ':mereni'      => ($mereniRaw === '' ? null : $mereniRaw),
            ':mereni_json' => null,
        ]);
        $treninkId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // fallback bez mereni_json
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO treninky (datum, delka, kategorie, napln, poznamka, obrazky, mereni)
                 VALUES (:datum, :delka, :kategorie, :napln, :poznamka, :obrazky, :mereni)'
            );
            $stmt->execute([
                ':datum'     => $datum,
                ':delka'     => $delka,
                ':kategorie' => ($kategorie === '' ? null : $kategorie),
                ':napln'     => ($napln === '' ? '' : $napln),
                ':poznamka'  => ($poznamka === '' ? null : $poznamka),
                ':obrazky'   => $obrazky,
                ':mereni'    => ($mereniRaw === '' ? null : $mereniRaw),
            ]);
            $treninkId = (int)$pdo->lastInsertId();
        } catch (PDOException $e2) {
            // fallback bez kategorie (a bez mereni_json)
            $stmt = $pdo->prepare(
                'INSERT INTO treninky (datum, napln, poznamka, delka, obrazky, mereni)
                 VALUES (:datum, :napln, :poznamka, :delka, :obrazky, :mereni)'
            );
            $stmt->execute([
                ':datum'       => $datum,
                ':napln'       => ($napln === '' ? '' : $napln),
                ':poznamka'    => ($poznamka === '' ? null : $poznamka),
                ':delka'       => $delka,
                ':obrazky'     => $obrazky,
                ':mereni'      => ($mereniRaw === '' ? null : $mereniRaw),
            ]);
            $treninkId = (int)$pdo->lastInsertId();
        }
    }

    // 2) Vazba na skupinu
    $pdo->prepare('DELETE FROM trenink_skupina WHERE trenink_id = ?')->execute([$treninkId]);
    if ($skupinaId && ctype_digit((string)$skupinaId)) {
        $pdo->prepare('INSERT INTO trenink_skupina (trenink_id, skupina_id) VALUES (?, ?)')->execute([$treninkId, (int)$skupinaId]);
    }

    // 3) Vazba na podskupiny
    $pdo->prepare('DELETE FROM trenink_podskupina WHERE trenink_id = ?')->execute([$treninkId]);
    if (is_array($podskupiny)) {
        $stmtPs = $pdo->prepare('INSERT INTO trenink_podskupina (trenink_id, podskupina_id) VALUES (?, ?)');
        foreach ($podskupiny as $psid) {
            if (!ctype_digit((string)$psid)) continue;
            $stmtPs->execute([$treninkId, (int)$psid]);
        }
    }

    // 4) Vazba na trenéry (vždy přidej i přihlášeného)
    $pdo->prepare('DELETE FROM trenink_trener WHERE trenink_id = ?')->execute([$treninkId]);
    if (!is_array($trenere)) $trenere = [];
    $trenere = array_map('intval', $trenere);
    if (!in_array($trenerId, $trenere, true)) array_unshift($trenere, $trenerId);
    $trenere = array_values(array_unique($trenere));

    $stmtT = $pdo->prepare('INSERT INTO trenink_trener (trenink_id, trener_id) VALUES (?, ?)');
    foreach ($trenere as $trid) {
        if ($trid <= 0) continue;
        $stmtT->execute([$treninkId, (int)$trid]);
    }

    // 5) Účastníci
    $stmtLinkSp = $pdo->prepare('INSERT IGNORE INTO trenink_sportovec (trenink_id, sportovec_id) VALUES (?, ?)');

    if ($ucastnici !== '') {
        foreach (array_filter(array_map('trim', explode(',', $ucastnici))) as $participant) {
            if (preg_match('/^(\d+):(.+)$/', $participant, $m)) {
                $sid = (int)$m[1];
                if ($sid > 0) $stmtLinkSp->execute([$treninkId, $sid]);
            }
        }
    }

    // 6) Měření (mereni_zaznamy + trenink_mereni)
    if (!empty($mereniRows)) {
        $pdo->prepare('DELETE FROM trenink_mereni WHERE trenink_id = ?')->execute([$treninkId]);

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

    // 7) Tagy (dynamicky)
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

                if ($ex > 0) $tagIds[] = $ex;
                else {
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

    // 8) Rezervace sportoviště (volitelná — pokud trenér vyplnil panel)
    $rezSportId  = (int)($_POST['rez_sportoviste_id'] ?? 0);
    $rezKapacita = max(1, min(5, (int)($_POST['rez_kapacita'] ?? 1)));
    $rezCasOd    = trim($_POST['rez_cas_od'] ?? '');
    $rezCasDo    = trim($_POST['rez_cas_do'] ?? '');
    $treninkDatum = trim($_POST['datum'] ?? '');

    if ($rezSportId > 0 && $rezCasOd && $rezCasDo && $rezCasOd < $rezCasDo && $treninkDatum) {
        // Kontrola kapacity — nepřekročit max_kapacita sportoviště
        $stMaxKap = $pdo->prepare("SELECT max_kapacita FROM sportovist WHERE id=? AND aktivni=1");
        $stMaxKap->execute([$rezSportId]);
        $maxKap = (int)($stMaxKap->fetchColumn() ?: 5);

        $stObsaz = $pdo->prepare("
            SELECT COALESCE(SUM(kapacita_dilu),0)
            FROM rezervace_sportovist
            WHERE sportoviste_id=? AND datum=? AND cas_od < ? AND cas_do > ?
              AND lekce_id IS NULL
        ");
        $stObsaz->execute([$rezSportId, $treninkDatum, $rezCasDo, $rezCasOd]);
        $obsazeno = (int)$stObsaz->fetchColumn();

        if ($obsazeno + $rezKapacita <= $maxKap) {
            $pdo->prepare("
                INSERT INTO rezervace_sportovist
                    (sportoviste_id, trener_id, datum, cas_od, cas_do, kapacita_dilu, trenink_id)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([
                $rezSportId, (int)$_SESSION['trener_id'],
                $treninkDatum, $rezCasOd, $rezCasDo,
                $rezKapacita, $treninkId,
            ]);
        }
        // Pokud kapacita překročena: tichý fail — trénink se uloží, rezervace ne
    }

    // 9) Propojení s plánovaným tréninkem (pokud byl formulář otevřen z plánovače)
    if ($planId > 0) {
        trainingRosterBridgeCopyPlanToTraining($pdo,$planId,$treninkId,$trenerId);
        $pdo->prepare("
            UPDATE planovane_treninky
            SET trenink_id = ?, stav = 'evidovany'
            WHERE id = ? AND stav = 'planovany'
        ")->execute([$treninkId, $planId]);
    }

    $pdo->commit();
    $redirectBack = ($planId > 0) ? 'planovac.php' : 'index.php';
    header('Location: ' . $redirectBack);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Chyba při ukládání: ' . $e->getMessage();
    header('Location: formular.php');
    exit;
}
