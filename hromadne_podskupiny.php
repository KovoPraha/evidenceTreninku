<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
if (!canAccess('sprava_sportovcu')) {
    http_response_code(403);
    exit('Pristup odepren.');
}

require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function isNonEmptyString($v): bool {
    return is_string($v) && trim($v) !== '';
}
function rocnik($date): string {
    if (!$date) return '';
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    return date('Y', $ts);
}

$messages = [];
$errors   = [];

$SPECIAL_UNASSIGNED = 'unassigned';
$ARCHIVE_NAME = 'Archiv';
$ARCHIVE_COL_KEY = 'archiv'; // pseudo-sloupec v UI

// vybraný režim / skupina
$selected = $_GET['skupina'] ?? ($_POST['skupina'] ?? '');
$selected = is_string($selected) ? trim($selected) : '';

// -------------------------
// Načti skupiny + zjisti Archiv ID (případně založ)
// -------------------------
$skupiny = [];
$archivSkupinaId = null;

try {
    $stmtSk = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev");
    $skupiny = $stmtSk->fetchAll(PDO::FETCH_ASSOC);

    foreach ($skupiny as $sk) {
        if (mb_strtolower(trim((string)$sk['nazev'])) === mb_strtolower($ARCHIVE_NAME)) {
            $archivSkupinaId = (int)$sk['id'];
            break;
        }
    }

    if ($archivSkupinaId === null) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO skupiny (nazev, poradi)
            SELECT :nazev, 9999
            WHERE NOT EXISTS (SELECT 1 FROM skupiny WHERE nazev = :nazev)
        ");
        $stmt->execute([':nazev' => $ARCHIVE_NAME]);

        $stmt = $pdo->prepare("SELECT id FROM skupiny WHERE nazev = :nazev LIMIT 1");
        $stmt->execute([':nazev' => $ARCHIVE_NAME]);
        $archivSkupinaId = (int)$stmt->fetchColumn();

        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errors[] = "Chyba při načítání / zakládání skupiny Archiv: " . $e->getMessage();
}

// -------------------------
// Uložení (POST) - 2 režimy
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ulozit'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $errors[] = "Neplatny CSRF token.";
        $selected = '';
    }
    if ($selected === '') {
        $errors[] = "Nejdřív vyber skupinu / režim.";
    } else {
        $assign  = $_POST['assign'] ?? [];
        $archive = $_POST['archive'] ?? []; // archive[sportovec_id] = 1
        if (!is_array($assign))  $assign = [];
        if (!is_array($archive)) $archive = [];

        // -------------------------
        // REŽIM A: Nepřiřazení → přiřazení do SKUPIN
        // -------------------------
        if ($selected === $SPECIAL_UNASSIGNED) {
            try {
                $stmt = $pdo->query("
                    SELECT s.id
                    FROM sportovci s
                    LEFT JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
                    WHERE sg.sportovec_id IS NULL
                ");
                $unassignedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $unassignedIds = array_map('intval', $unassignedIds);
                $unassignedSet = array_fill_keys($unassignedIds, true);

                $groupIds = array_map(fn($r) => (int)$r['id'], $skupiny);
                $groupSet = array_fill_keys($groupIds, true);

                $toInsert = [];
                foreach ($assign as $sportovecIdRaw => $groupIdsRaw) {
                    $sid = (int)$sportovecIdRaw;
                    if (!isset($unassignedSet[$sid])) continue;
                    if (!is_array($groupIdsRaw)) continue;

                    foreach ($groupIdsRaw as $gidRaw) {
                        $gid = (int)$gidRaw;
                        if (isset($groupSet[$gid])) {
                            $toInsert[] = [$sid, $gid];
                        }
                    }
                }

                $pdo->beginTransaction();

                if (count($unassignedIds) > 0) {
                    $in = implode(',', array_fill(0, count($unassignedIds), '?'));
                    $stmtDel = $pdo->prepare("DELETE FROM sportovec_skupina WHERE sportovec_id IN ($in)");
                    $stmtDel->execute($unassignedIds);
                }

                if (count($toInsert) > 0) {
                    $stmtIns = $pdo->prepare("INSERT INTO sportovec_skupina (sportovec_id, skupina_id) VALUES (?, ?)");
                    foreach ($toInsert as [$sid, $gid]) {
                        $stmtIns->execute([$sid, $gid]);
                    }
                }

                $pdo->commit();
                $messages[] = "Uloženo. Nepřiřazení sportovci byli podle checkboxů přiřazeni do skupin (kompletní přepsání).";

                header("Location: " . $_SERVER['PHP_SELF'] . "?skupina=" . urlencode($SPECIAL_UNASSIGNED));
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Chyba při ukládání skupin: " . $e->getMessage();
            }
        }

        // -------------------------
        // REŽIM B: Konkrétní skupina → přiřazení do PODSKUPIN (+ Archiv sloupec)
        // -------------------------
        else {
            if (!ctype_digit($selected)) {
                $errors[] = "Neplatná skupina.";
            } else {
                $skupinaId = (int)$selected;

                try {
                    // povolené podskupiny pro skupinu
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM podskupiny
                        WHERE skupina_id = :sid
                        ORDER BY poradi, nazev
                    ");
                    $stmt->execute([':sid' => $skupinaId]);
                    $allowedPodIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $allowedPodIds = array_map('intval', $allowedPodIds);
                    $allowedPodSet = array_fill_keys($allowedPodIds, true);

                    if (count($allowedPodIds) === 0) {
                        $errors[] = "Vybraná skupina zatím nemá žádné podskupiny. Nejdřív je vytvoř.";
                    } else {
                        // sportovci v této skupině
                        $stmt = $pdo->prepare("
                            SELECT s.id
                            FROM sportovci s
                            INNER JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
                            WHERE sg.skupina_id = :sid
                        ");
                        $stmt->execute([':sid' => $skupinaId]);
                        $sportIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        $sportIds = array_map('intval', $sportIds);
                        $sportSet = array_fill_keys($sportIds, true);

                        // kdo je archivovaný v tomto uložení?
                        $archiveIds = [];
                        foreach ($archive as $sidRaw => $val) {
                            $sid = (int)$sidRaw;
                            if (isset($sportSet[$sid]) && (string)$val === '1') {
                                $archiveIds[] = $sid;
                            }
                        }
                        $archiveSet = array_fill_keys($archiveIds, true);

                        // normalizace podskupin -> ignoruj ty, co jdou do archivu
                        $toInsert = [];
                        foreach ($assign as $sportovecIdRaw => $podIdsRaw) {
                            $sid = (int)$sportovecIdRaw;
                            if (!isset($sportSet[$sid])) continue;
                            if (isset($archiveSet[$sid])) continue;
                            if (!is_array($podIdsRaw)) continue;

                            foreach ($podIdsRaw as $pidRaw) {
                                $pid = (int)$pidRaw;
                                if (isset($allowedPodSet[$pid])) {
                                    $toInsert[] = [$sid, $pid];
                                }
                            }
                        }

                        $pdo->beginTransaction();

                        // 1) Přepsání podskupin pro tuto skupinu (pro všechny sportovce v této skupině)
                        if (count($sportIds) > 0) {
                            $in = implode(',', array_fill(0, count($sportIds), '?'));
                            $sqlDelete = "
                                DELETE sp
                                FROM sportovec_podskupina sp
                                INNER JOIN podskupiny p ON p.id = sp.podskupina_id
                                WHERE p.skupina_id = ?
                                  AND sp.sportovec_id IN ($in)
                            ";
                            $params = array_merge([$skupinaId], $sportIds);
                            $stmtDel = $pdo->prepare($sqlDelete);
                            $stmtDel->execute($params);
                        }

                        // 2) Vložit nové podskupiny (jen pro ty, kteří nejsou archivovaní)
                        if (count($toInsert) > 0) {
                            $stmtIns = $pdo->prepare("INSERT INTO sportovec_podskupina (sportovec_id, podskupina_id) VALUES (?, ?)");
                            foreach ($toInsert as [$sid, $pid]) {
                                $stmtIns->execute([$sid, $pid]);
                            }
                        }

                        // 3) ARCHIVACE: pro zaškrtnuté sportovce
                        if (count($archiveIds) > 0) {
                            if (!$archivSkupinaId) {
                                throw new Exception("Skupina Archiv nemá ID (nepodařilo se ji načíst/založit).");
                            }

                            $inA = implode(',', array_fill(0, count($archiveIds), '?'));

                            // 3a) smaž VŠECHNY podskupiny sportovce (globálně)
                            $stmt = $pdo->prepare("DELETE FROM sportovec_podskupina WHERE sportovec_id IN ($inA)");
                            $stmt->execute($archiveIds);

                            // 3b) smaž členství ve všech skupinách
                            $stmt = $pdo->prepare("DELETE FROM sportovec_skupina WHERE sportovec_id IN ($inA)");
                            $stmt->execute($archiveIds);

                            // 3c) přiřaď do skupiny Archiv
                            $stmtInsArch = $pdo->prepare("INSERT INTO sportovec_skupina (sportovec_id, skupina_id) VALUES (?, ?)");
                            foreach ($archiveIds as $sid) {
                                $stmtInsArch->execute([(int)$sid, (int)$archivSkupinaId]);
                            }
                        }

                        $pdo->commit();

                        $msg = "Uloženo. Podskupiny byly aktualizovány pro vybranou skupinu.";
                        if (count($archiveIds) > 0) {
                            $msg .= " Archivováno: " . count($archiveIds) . " sportovců (odpojeno od všech podskupin a přesun do skupiny Archiv).";
                        }
                        $messages[] = $msg;

                        header("Location: " . $_SERVER['PHP_SELF'] . "?skupina=" . urlencode((string)$skupinaId));
                        exit;
                    }

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = "Chyba při ukládání podskupin/archivu: " . $e->getMessage();
                }
            }
        }
    }
}

// -------------------------
// Načtení dat pro zobrazení
// -------------------------
$sportovci = [];
$podskupiny = [];

$checkedGroupMap = [];
$checkedPodMap   = [];
$checkedArchive  = []; // [$sid]=true pokud je sportovec v Archiv skupině

if ($selected !== '') {

    // REŽIM A
    if ($selected === $SPECIAL_UNASSIGNED) {
        try {
            $stmt = $pdo->query("
                SELECT s.id, s.jmeno, s.prijmeni, s.uciid, s.narozeni
                FROM sportovci s
                LEFT JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
                WHERE sg.sportovec_id IS NULL
                ORDER BY s.prijmeni, s.jmeno
            ");
            $sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($sportovci) > 0) {
                $spIds = array_map(fn($r) => (int)$r['id'], $sportovci);
                $inSp  = implode(',', array_fill(0, count($spIds), '?'));
                $stmt  = $pdo->prepare("SELECT sportovec_id, skupina_id FROM sportovec_skupina WHERE sportovec_id IN ($inSp)");
                $stmt->execute($spIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $sid = (int)$r['sportovec_id'];
                    $gid = (int)$r['skupina_id'];
                    $checkedGroupMap[$sid][$gid] = true;
                }
            }
        } catch (Throwable $e) {
            $errors[] = "Chyba při načítání nepřiřazených sportovců: " . $e->getMessage();
        }
    }

    // REŽIM B
    else {
        if (ctype_digit($selected)) {
            $skupinaId = (int)$selected;

            try {
                $stmt = $pdo->prepare("
                    SELECT id, nazev, poradi
                    FROM podskupiny
                    WHERE skupina_id = :sid
                    ORDER BY poradi, nazev
                ");
                $stmt->execute([':sid' => $skupinaId]);
                $podskupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $errors[] = "Chyba při načítání podskupin: " . $e->getMessage();
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT s.id, s.jmeno, s.prijmeni, s.uciid, s.narozeni
                    FROM sportovci s
                    INNER JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
                    WHERE sg.skupina_id = :sid
                    ORDER BY s.prijmeni, s.jmeno
                ");
                $stmt->execute([':sid' => $skupinaId]);
                $sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $errors[] = "Chyba při načítání sportovců: " . $e->getMessage();
            }

            if (count($sportovci) > 0 && count($podskupiny) > 0) {
                try {
                    $spIds  = array_map(fn($r) => (int)$r['id'], $sportovci);
                    $podIds = array_map(fn($r) => (int)$r['id'], $podskupiny);

                    $inSp  = implode(',', array_fill(0, count($spIds), '?'));
                    $inPod = implode(',', array_fill(0, count($podIds), '?'));

                    $stmt = $pdo->prepare("
                        SELECT sportovec_id, podskupina_id
                        FROM sportovec_podskupina
                        WHERE sportovec_id IN ($inSp)
                          AND podskupina_id IN ($inPod)
                    ");
                    $stmt->execute(array_merge($spIds, $podIds));
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $r) {
                        $sid = (int)$r['sportovec_id'];
                        $pid = (int)$r['podskupina_id'];
                        $checkedPodMap[$sid][$pid] = true;
                    }
                } catch (Throwable $e) {
                    $errors[] = "Chyba při načítání přiřazení: " . $e->getMessage();
                }
            }

            // kdo je už v archivu (pro předzaškrtnutí)
            if (count($sportovci) > 0 && $archivSkupinaId) {
                try {
                    $spIds = array_map(fn($r) => (int)$r['id'], $sportovci);
                    $inSp  = implode(',', array_fill(0, count($spIds), '?'));
                    $params = array_merge([(int)$archivSkupinaId], $spIds);

                    $stmt = $pdo->prepare("
                        SELECT sportovec_id
                        FROM sportovec_skupina
                        WHERE skupina_id = ?
                          AND sportovec_id IN ($inSp)
                    ");
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($rows as $sid) {
                        $checkedArchive[(int)$sid] = true;
                    }
                } catch (Throwable $e) {
                    $errors[] = "Chyba při načítání stavu Archiv: " . $e->getMessage();
                }
            }

        } else {
            $errors[] = "Neplatná skupina.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Hromadná administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        /* ---- layout tabulky ---- */
        table { table-layout: fixed; }

        /* základní kompaktní tabulka */
        .table-sm > :not(caption) > * > * { padding: 4px !important; }

        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-ind { font-size: 12px; opacity: .7; margin-left: 4px; }

        .sticky-actions {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #f8f9fa;
            padding: 8px 0;
        }

        /* ---- šířky "textových" sloupců (jméno apod.) ---- */
        th.col-lname, td.col-lname { width: 160px; min-width: 160px; max-width: 160px; }
        th.col-fname, td.col-fname { width: 120px; min-width: 120px; max-width: 120px; }
        th.col-year,  td.col-year  { width: 70px;  min-width: 70px;  max-width: 70px;  }

        /* ---- úzké checkbox sloupce ---- */
        th[data-sort="col"], td[data-col]{
            width: 60px;
            min-width: 60px;
            max-width: 60px;
            padding: 3px !important;
            text-align: center;
            vertical-align: middle;
        }

        /* název v hlavičce sloupce: zkrátit ... */
        th[data-sort="col"] > div:first-child{
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* mini tlačítka ve sloupci */
        .col-actions {
            display: flex;
            flex-direction: column;
            gap: 3px !important;
            align-items: center;
            margin-top: 4px;
        }
        .col-actions .btn {
            padding: 1px 0 !important;
            font-size: 10px !important;
            line-height: 1 !important;
            width: 100%;
        }

        /* checkbox bez marginů */
        input.form-check-input { margin: 0 !important; }

        /* archiv vizuálně */
        .archiv-col { background: rgba(255, 193, 7, 0.15); }

        /* aby se v textových buňkách nezalamovalo */
        td.col-lname, td.col-fname { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        
        
        :root { --sticky-actions-h: 0px; }

/* Sticky hlavička tabulky (zůstane pod "Uložit...") */
.table-sticky-head th{
    position: sticky;
    top: var(--sticky-actions-h);
    z-index: 4;
}

/* aby sticky hlavička nepřesvitla (table-dark) */
.table-sticky-head.table-dark th,
.table-sticky-head .table-dark th{
    background-color: #212529; /* bootstrap table-dark */
}
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1>Hromadná administrace</h1>

    <?php if (!empty($messages)): ?>
        <div class="alert alert-success">
            <?php foreach ($messages as $m): ?>
                <div><?= h($m) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Výběr režimu / skupiny -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-5">
            <label for="skupina" class="form-label">Skupina / režim</label>
            <select id="skupina" name="skupina" class="form-select" onchange="this.form.submit()">
                <option value="">— vyber —</option>
                <?php foreach ($skupiny as $sk): ?>
                    <option value="<?= (int)$sk['id'] ?>" <?= ($selected == (string)$sk['id']) ? 'selected' : '' ?>>
                        <?= h((string)$sk['nazev']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="<?= h($SPECIAL_UNASSIGNED) ?>" <?= ($selected === $SPECIAL_UNASSIGNED) ? 'selected' : '' ?>>
                    Nepřiřazení sportovci ve skupině
                </option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Načíst</button>
        </div>
    </form>

    <?php if ($selected === ''): ?>
        <div class="alert alert-info">Vyber skupinu nebo „Nepřiřazení…“.</div>

    <?php elseif ($selected === $SPECIAL_UNASSIGNED): ?>

        <!-- REŽIM A: nepřiřazení sportovci × skupiny -->
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="skupina" value="<?= h($SPECIAL_UNASSIGNED) ?>">

            <div class="sticky-actions d-flex flex-wrap gap-2 align-items-center mb-2">
                <button type="submit" name="ulozit" value="1" class="btn btn-success">
                    Uložit (skupiny)
                </button>
                <div class="text-muted">
                    Sportovci: <strong><?= count($sportovci) ?></strong>,
                    Skupiny: <strong><?= count($skupiny) ?></strong>
                </div>
            </div>

            <?php if (count($sportovci) === 0): ?>
                <div class="alert alert-warning">Žádní nepřiřazení sportovci.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="massTable" class="table table-bordered table-sm align-middle">
                        <thead class="table-dark ">
                            <tr>
                                <th class="sortable col-lname" data-sort="lname" title="Řadit podle příjmení">
                                    Příjmení <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-fname" data-sort="fname" title="Řadit podle jména">
                                    Jméno <span class="sort-ind"></span>
                                </th>
                                <th class="sortable text-center col-year" data-sort="year" title="Řadit podle ročníku">
                                    Roč. <span class="sort-ind"></span>
                                </th>

                                <?php foreach ($skupiny as $sk): ?>
                                    <?php $gid = (int)$sk['id']; ?>
                                    <th class="text-center sortable"
                                        data-sort="col"
                                        data-col="<?= $gid ?>"
                                        title="<?= h((string)$sk['nazev']) ?>">
                                        <div title="<?= h((string)$sk['nazev']) ?>"><?= h((string)$sk['nazev']) ?><span class="sort-ind"></span></div>
                                        <div class="col-actions">
                                            <button type="button" class="btn btn-outline-light btn-sm col-check" data-col="<?= $gid ?>">✓</button>
                                            <button type="button" class="btn btn-outline-light btn-sm col-uncheck" data-col="<?= $gid ?>">✕</button>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($sportovci as $s): ?>
                            <?php
                                $sid  = (int)$s['id'];
                                $lname = (string)($s['prijmeni'] ?? '');
                                $fname = (string)($s['jmeno'] ?? '');
                                $year  = rocnik($s['narozeni'] ?? null);
                            ?>
                            <tr data-lname="<?= h(mb_strtolower($lname)) ?>"
                                data-fname="<?= h(mb_strtolower($fname)) ?>"
                                data-year="<?= h($year) ?>">
                                <td class="fw-semibold col-lname" title="<?= h($lname) ?>"><?= h($lname) ?></td>
                                <td class="col-fname" title="<?= h($fname) ?>"><?= h($fname) ?></td>
                                <td class="text-center col-year"><?= h($year) ?></td>

                                <?php foreach ($skupiny as $sk): ?>
                                    <?php
                                        $gid = (int)$sk['id'];
                                        $checked = isset($checkedGroupMap[$sid][$gid]);
                                    ?>
                                    <td class="text-center" data-col="<?= $gid ?>" data-checked="<?= $checked ? '1' : '0' ?>">
                                        <input
                                            type="checkbox"
                                            class="form-check-input masscb"
                                            data-col="<?= $gid ?>"
                                            name="assign[<?= $sid ?>][]"
                                            value="<?= $gid ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary mt-3">
                    <strong>Pozor:</strong> Uložení provede kompletní přepsání vazeb sportovec↔skupina
                    pro zobrazené (nepřiřazené) sportovce.
                </div>
            <?php endif; ?>
        </form>

    <?php else: ?>

        <!-- REŽIM B: sportovci ve skupině × podskupiny + Archiv -->
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="skupina" value="<?= h($selected) ?>">

            <div class="sticky-actions d-flex flex-wrap gap-2 align-items-center mb-2">
                <button type="submit" name="ulozit" value="1" class="btn btn-success">
                    Uložit (podskupiny + archiv)
                </button>
                <div class="text-muted">
                    Sportovci: <strong><?= count($sportovci) ?></strong>,
                    Podskupiny: <strong><?= count($podskupiny) ?></strong>
                </div>
                
                   <table id="massTable" class="table table-bordered table-sm align-middle">
                       <thead class="table-dark table-sticky-head">
                            <tr>
                                <th class="sortable col-lname" data-sort="lname" title="Řadit podle příjmení">
                                    Příjmení <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-fname" data-sort="fname" title="Řadit podle jména">
                                    Jméno <span class="sort-ind"></span>
                                </th>
                                <th class="sortable text-center col-year" data-sort="year" title="Řadit podle ročníku">
                                    Roč. <span class="sort-ind"></span>
                                </th>

                                <?php foreach ($podskupiny as $ps): ?>
                                    <?php $pid = (int)$ps['id']; ?>
                                    <th class="text-center sortable"
                                        data-sort="col"
                                        data-col="<?= $pid ?>"
                                        title="<?= h((string)$ps['nazev']) ?>">
                                        <div title="<?= h((string)$ps['nazev']) ?>"><?= h((string)$ps['nazev']) ?><span class="sort-ind"></span></div>
                                        <div class="col-actions">
                                            <button type="button" class="btn btn-outline-light btn-sm col-check" data-col="<?= $pid ?>">✓</button>
                                            <button type="button" class="btn btn-outline-light btn-sm col-uncheck" data-col="<?= $pid ?>">✕</button>
                                        </div>
                                    </th>
                                <?php endforeach; ?>

                                <!-- Archiv sloupec -->
                                <th class="text-center sortable archiv-col"
                                    data-sort="col"
                                    data-col="<?= h($ARCHIVE_COL_KEY) ?>"
                                    title="Archiv">
                                    <div title="Archiv">Archiv<span class="sort-ind"></span></div>
                                    <div class="col-actions">
                                        <button type="button" class="btn btn-outline-light btn-sm col-check" data-col="<?= h($ARCHIVE_COL_KEY) ?>">✓</button>
                                        <button type="button" class="btn btn-outline-light btn-sm col-uncheck" data-col="<?= h($ARCHIVE_COL_KEY) ?>">✕</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        
                        </table>
            </div>

            <?php if (count($podskupiny) === 0): ?>
                <div class="alert alert-warning">Tato skupina nemá žádné podskupiny.</div>
            <?php elseif (count($sportovci) === 0): ?>
                <div class="alert alert-warning">Ve vybrané skupině nejsou žádní sportovci.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="massTable" class="table table-bordered table-sm align-middle">
                      
                        <tbody>
                        <?php foreach ($sportovci as $s): ?>
                            <?php
                                $sid  = (int)$s['id'];
                                $lname = (string)($s['prijmeni'] ?? '');
                                $fname = (string)($s['jmeno'] ?? '');
                                $year  = rocnik($s['narozeni'] ?? null);
                                $isArch = isset($checkedArchive[$sid]);
                            ?>
                            <tr data-lname="<?= h(mb_strtolower($lname)) ?>"
                                data-fname="<?= h(mb_strtolower($fname)) ?>"
                                data-year="<?= h($year) ?>">
                                <td class="fw-semibold col-lname" title="<?= h($lname) ?>"><?= h($lname) ?></td>
                                <td class="col-fname" title="<?= h($fname) ?>"><?= h($fname) ?></td>
                                <td class="text-center col-year"><?= h($year) ?></td>

                                <?php foreach ($podskupiny as $ps): ?>
                                    <?php
                                        $pid = (int)$ps['id'];
                                        $checked = isset($checkedPodMap[$sid][$pid]);
                                    ?>
                                    <td class="text-center" data-col="<?= $pid ?>" data-checked="<?= $checked ? '1' : '0' ?>">
                                        <input
                                            type="checkbox"
                                            class="form-check-input masscb"
                                            data-col="<?= $pid ?>"
                                            name="assign[<?= $sid ?>][]"
                                            value="<?= $pid ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                        >
                                    </td>
                                <?php endforeach; ?>

                                <td class="text-center archiv-col" data-col="<?= h($ARCHIVE_COL_KEY) ?>" data-checked="<?= $isArch ? '1' : '0' ?>">
                                    <input
                                        type="checkbox"
                                        class="form-check-input masscb archivecb"
                                        data-col="<?= h($ARCHIVE_COL_KEY) ?>"
                                        name="archive[<?= $sid ?>]"
                                        value="1"
                                        <?= $isArch ? 'checked' : '' ?>
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-secondary mt-3">
                    <strong>Archiv:</strong> Pokud zaškrtneš „Archiv“, při uložení se sportovec odpojí od všech podskupin,
                    zruší se členství ve všech skupinách a přiřadí se do skupiny <strong>Archiv</strong>.
                </div>
            <?php endif; ?>
        </form>

    <?php endif; ?>

</div>

<script>



// ---------- Sloupcové akce: zaškrtnout / odškrtnout vše ----------
document.addEventListener('click', function(e) {
    const btnCheck = e.target.closest('.col-check');
    const btnUncheck = e.target.closest('.col-uncheck');

    if (!btnCheck && !btnUncheck) return;

    const col = (btnCheck || btnUncheck).getAttribute('data-col');
    const doCheck = !!btnCheck;

    const boxes = document.querySelectorAll('input.masscb[data-col="'+col+'"]');
    boxes.forEach(cb => { cb.checked = doCheck; });

    const tds = document.querySelectorAll('td[data-col="'+col+'"]');
    tds.forEach(td => td.setAttribute('data-checked', doCheck ? '1' : '0'));
});

// při ručním kliknutí checkboxu update data-checked pro sorting
document.addEventListener('change', function(e) {
    const cb = e.target.closest('input.masscb');
    if (!cb) return;
    const col = cb.getAttribute('data-col');
    const td = cb.closest('td[data-col="'+col+'"]');
    if (td) td.setAttribute('data-checked', cb.checked ? '1' : '0');
});

// Archiv checkbox → odškrtnout podskupiny v řádku (UX)
document.addEventListener('change', function(e) {
    const arch = e.target.closest('input.archivecb');
    if (!arch) return;

    const row = arch.closest('tr');
    if (!row) return;

    if (arch.checked) {
        const podBoxes = row.querySelectorAll('input.masscb:not(.archivecb)');
        podBoxes.forEach(cb => {
            cb.checked = false;
            const col = cb.getAttribute('data-col');
            const td = cb.closest('td[data-col="'+col+'"]');
            if (td) td.setAttribute('data-checked', '0');
        });
    }
});

// ---------- Řazení ----------
(function(){
    const table = document.getElementById('massTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th.sortable');

    let currentSort = { key: null, dir: 1 };

    function setIndicators(activeTh, dir) {
        headers.forEach(th => {
            const ind = th.querySelector('.sort-ind');
            if (!ind) return;
            if (th === activeTh) ind.textContent = (dir === 1 ? '▲' : '▼');
            else ind.textContent = '';
        });
    }

    function sortRowsByKey(key, dir, colId=null) {
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            if (key === 'lname' || key === 'fname' || key === 'year') {
                const va = a.getAttribute('data-'+key) || '';
                const vb = b.getAttribute('data-'+key) || '';
                if (key === 'year') {
                    const na = parseInt(va || '0', 10);
                    const nb = parseInt(vb || '0', 10);
                    return (na - nb) * dir;
                }
                return va.localeCompare(vb, 'cs', { sensitivity: 'base' }) * dir;
            }

            if (key === 'col' && colId !== null) {
                const tda = a.querySelector('td[data-col="'+colId+'"]');
                const tdb = b.querySelector('td[data-col="'+colId+'"]');
                const ca = tda ? (tda.getAttribute('data-checked') || '0') : '0';
                const cb = tdb ? (tdb.getAttribute('data-checked') || '0') : '0';

                if (ca !== cb) return (parseInt(ca,10) - parseInt(cb,10)) * dir;

                const la = a.getAttribute('data-lname') || '';
                const lb = b.getAttribute('data-lname') || '';
                const cmpL = la.localeCompare(lb, 'cs', { sensitivity: 'base' });
                if (cmpL !== 0) return cmpL;

                const fa = a.getAttribute('data-fname') || '';
                const fb = b.getAttribute('data-fname') || '';
                return fa.localeCompare(fb, 'cs', { sensitivity: 'base' });
            }

            return 0;
        });

        rows.forEach(r => tbody.appendChild(r));
    }

    headers.forEach(th => {
        th.addEventListener('click', () => {
            const key = th.getAttribute('data-sort');
            const col = th.getAttribute('data-col');

            let dir = 1;
            if (currentSort.key === (key + ':' + (col || ''))) dir = -currentSort.dir;

            currentSort.key = key + ':' + (col || '');
            currentSort.dir = dir;

            if (key === 'col') sortRowsByKey('col', dir, col);
            else sortRowsByKey(key, dir);

            setIndicators(th, dir);
        });
    });
})();
</script>

</body>
</html>
