<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
require_once 'includes/funkce.php';
if (!canAccess('kalendar_sportovist')) { header('Location: index.php'); exit; }
require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/venue_calendar.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$trenerId = (int)$_SESSION['trener_id'];

// ── Smazání rezervace ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $delId = (int)($_POST['rezervace_id'] ?? 0);
        // Trenér může mazat jen své rezervace, admin libovolné
        $sql = roleAtLeast('hlavni')
            ? "DELETE FROM rezervace_sportovist WHERE id=?"
            : "DELETE FROM rezervace_sportovist WHERE id=? AND trener_id=?";
        $params = roleAtLeast('hlavni') ? [$delId] : [$delId, $trenerId];
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare($sql);
            $delete->execute($params);
            if ($delete->rowCount() === 1) {
                $pdo->prepare('UPDATE planovane_treninky SET rezervace_id=NULL WHERE rezervace_id=?')->execute([$delId]);
                $_SESSION['flash_success'] = 'Rezervace zrušena.';
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('kalendar_sportovist delete: ' . $exception->getMessage());
            $_SESSION['flash_error'] = 'Rezervaci se nepodařilo zrušit.';
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ── Datum + týden ─────────────────────────────────────────────────────────────
$datumParam = $_GET['datum'] ?? date('Y-m-d');
try { $datumBase = new DateTime($datumParam); } catch (Exception $e) { $datumBase = new DateTime(); }

// Pondělí aktuálního týdne
$dow = (int)$datumBase->format('N'); // 1=Po … 7=Ne
$monday = (clone $datumBase)->modify('-' . ($dow - 1) . ' days');
$sunday = (clone $monday)->modify('+6 days');

$dneTydne = [];
for ($i = 0; $i < 7; $i++) {
    $d = (clone $monday)->modify("+{$i} days");
    $dneTydne[] = $d->format('Y-m-d');
}

// ── Sportoviště ───────────────────────────────────────────────────────────────
$sportovist = $pdo->query("SELECT * FROM sportovist WHERE aktivni=1 ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

// ── Rezervace v týdnu ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.*, s.nazev AS sport_nazev, s.kod AS sport_kod,
           t.jmeno AS trener_jmeno,
           il.nazev AS lekce_nazev, il.typ AS lekce_typ, il.cena_kc
    FROM rezervace_sportovist r
    JOIN sportovist s ON s.id = r.sportoviste_id
    JOIN treneri t    ON t.id = r.trener_id
    LEFT JOIN individualni_lekce il ON il.id = r.lekce_id
    WHERE r.datum BETWEEN ? AND ?
    ORDER BY r.datum, r.cas_od
");
$stmt->execute([$monday->format('Y-m-d'), $sunday->format('Y-m-d')]);
$vsechnyRezervace = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indexovat dle [datum][sportoviste_id]
$rezIdx = [];
foreach ($vsechnyRezervace as $r) {
    $rezIdx[$r['datum']][$r['sportoviste_id']][] = $r;
}

// ── Individuální lekce v týdnu (zobrazeny jako informační bloky) ─────────────
$stmtLekce = $pdo->prepare("
    SELECT il.*, s.nazev AS sport_nazev, t.jmeno AS trener_jmeno,
           COUNT(vr.id)                                                    AS celkem_rez,
           SUM(vr.stav IN ('ceka','potvrzena'))                            AS aktivni_rez,
           SUM(vr.stav = 'potvrzena')                                      AS potvrzenych
    FROM individualni_lekce il
    JOIN sportovist s ON s.id = il.sportoviste_id
    JOIN treneri t    ON t.id = il.trener_id
    LEFT JOIN verejne_rezervace vr ON vr.lekce_id = il.id
    WHERE il.stav = 'aktivni'
      AND il.datum BETWEEN ? AND ?
    GROUP BY il.id
    ORDER BY il.datum, il.cas_od
");
$stmtLekce->execute([$monday->format('Y-m-d'), $sunday->format('Y-m-d')]);
$vsechnyLekce = $stmtLekce->fetchAll(PDO::FETCH_ASSOC);

// Indexovat lekce dle [datum][sportoviste_id]
$lekceIdx = [];
foreach ($vsechnyLekce as $l) {
    $lekceIdx[$l['datum']][$l['sportoviste_id']][] = $l;
}

// Plány bez samostatně vykreslené rezervace; zahrnuje i evidované a historickou deduplikaci.
$vsechnyPlan = venueCalendarUnreservedPlans(
    $pdo,
    $monday->format('Y-m-d'),
    $sunday->format('Y-m-d')
);

// Indexovat dle [datum][sportoviste_id]
$planIdx = [];
foreach ($vsechnyPlan as $p) {
    $planIdx[$p['datum']][$p['sportoviste_id']][] = $p;
}

// Trenéři pro barvy
$treneri = $pdo->query("SELECT id, jmeno FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_ASSOC);
$barvy = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];
$trenerBarva = [];
foreach ($treneri as $i => $t) {
    $trenerBarva[$t['id']] = $barvy[$i % count($barvy)];
}

$prevWeek = (clone $monday)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $monday)->modify('+7 days')->format('Y-m-d');

$dayNames = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kalendář sportovišť</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .rez-blok {
            border-radius: 6px;
            padding: 4px 7px;
            margin-bottom: 3px;
            font-size: .78rem;
            color: #fff;
            line-height: 1.3;
        }
        .rez-blok .kap-badge {
            font-size: .7rem;
            background: rgba(0,0,0,.25);
            border-radius: 3px;
            padding: 0 4px;
        }
        .sport-cell { min-width: 110px; vertical-align: top; }
        .day-cell   { min-width: 130px; vertical-align: top; padding: 6px !important; }
        .today-col  { background: #fffbeb; }
        .cap-bar    { height: 4px; border-radius: 2px; background: #e5e7eb; margin-top: 3px; }
        .cap-fill   { height: 100%; border-radius: 2px; }
        /* Individuální lekce — informační bloky (neblokují kapacitu) */
        .lekce-blok {
            border-radius: 6px;
            padding: 4px 7px;
            margin-bottom: 3px;
            font-size: .78rem;
            line-height: 1.3;
            border: 2px dashed;
        }
        .lekce-blok.zelena {
            background: #f0fdf4;
            border-color: #16a34a;
            color: #14532d;
        }
        .lekce-blok.zluta {
            background: #fffbeb;
            border-color: #b45309;
            color: #78350f;
        }
        .lekce-blok.has-rezervace {
            border-style: solid;
        }
        /* Plánované tréninky bez rezervace — jen informativní */
        .plan-blok {
            border-radius: 6px;
            padding: 4px 7px;
            margin-bottom: 3px;
            font-size: .78rem;
            line-height: 1.3;
            border: 2px dotted #3b82f6;
            background: #eff6ff;
            color: #1e40af;
        }
        .plan-blok.evidovany { border-style: solid; border-color: #198754 !important; background: #ecfdf3; color: #14532d; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container-fluid mt-3 px-3">

    <!-- Hlavička -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h5 mb-0 me-2"><i class="bi bi-calendar3 me-2 text-primary"></i>Kalendář sportovišť</h1>
        <div class="btn-group btn-group-sm">
            <a href="?datum=<?= $prevWeek ?>" class="btn btn-outline-secondary" aria-label="Předchozí týden">
                <i class="bi bi-chevron-left"></i>
            </a>
            <a href="?datum=<?= $today ?>" class="btn btn-outline-secondary">Dnes</a>
            <a href="?datum=<?= $nextWeek ?>" class="btn btn-outline-secondary" aria-label="Následující týden">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <span class="text-muted small">
            <?= $monday->format('j. n.') ?> – <?= $sunday->format('j. n. Y') ?>
        </span>
        <div class="ms-auto d-flex gap-2">
            <?php if (canAccess('rezervace_sportovist')): ?>
                <a href="rezervovat_sportoviste.php?datum=<?= h($today) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Nová rezervace
                </a>
            <?php endif; ?>
            <?php if (canAccess('individualni_lekce')): ?>
                <a href="individualni_lekce_form.php" class="btn btn-success btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Nová lekce
                </a>
            <?php endif; ?>
            <?php if (staffActivePositionIs('program_coordinator')): ?>
                <a href="sprava_sportovist.php" class="btn btn-outline-secondary btn-sm" aria-label="Správa sportovišť">
                    <i class="bi bi-gear"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Legenda -->
    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <?php foreach ($treneri as $t): ?>
            <span class="badge" style="background:<?= $trenerBarva[$t['id']] ?>">
                <?= h($t['jmeno']) ?>
            </span>
        <?php endforeach; ?>
        <span class="vr mx-1"></span>
        <span style="font-size:.8rem;padding:4px 8px;border-radius:4px;background:#f0fdf4;color:#14532d;border:2px dashed #16a34a">
            <i class="bi bi-calendar-event me-1"></i>Lekce (zelená) — bez rezervace
        </span>
        <span style="font-size:.8rem;padding:4px 8px;border-radius:4px;background:#fffbeb;color:#78350f;border:2px dashed #b45309">
            <i class="bi bi-calendar-event me-1"></i>Lekce (žlutá) — bez rezervace
        </span>
        <span style="font-size:.8rem;padding:4px 8px;border-radius:4px;background:#dcfce7;color:#14532d;border:2px solid #16a34a">
            <i class="bi bi-person-check me-1"></i>Lekce s rezervací
        </span>
        <span style="font-size:.8rem;padding:4px 8px;border-radius:4px;background:#eff6ff;color:#1e40af;border:2px dotted #3b82f6">
            <i class="bi bi-calendar3-week me-1"></i>Plánovaný trénink (bez rezervace)
        </span>
        <span style="font-size:.8rem;padding:4px 8px;border-radius:4px;background:#ecfdf3;color:#14532d;border:2px solid #198754">
            <i class="bi bi-check-circle me-1"></i>Zaevidovaný trénink (bez rezervace)
        </span>
    </div>

    <!-- Tabulka -->
    <div style="overflow-x:auto">
        <table class="table table-bordered bg-white shadow-sm" style="min-width:900px">
            <thead class="table-light">
                <tr>
                    <th class="sport-cell">Sportoviště</th>
                    <?php foreach ($dneTydne as $i => $d): ?>
                        <th class="day-cell text-center <?= $d === $today ? 'today-col' : '' ?>">
                            <div class="fw-semibold"><?= $dayNames[$i] ?></div>
                            <div class="text-muted small"><?= (new DateTime($d))->format('j. n.') ?></div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sportovist as $sport): ?>
                <tr>
                    <td class="sport-cell">
                        <div class="fw-semibold small"><?= h($sport['nazev']) ?></div>
                        <?php if ($sport['je_verejne']): ?>
                            <span class="badge bg-success-subtle text-success" style="font-size:.7rem">
                                <i class="bi bi-globe"></i> Veřejné
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($dneTydne as $d): ?>
                        <?php
                        $bloky = $rezIdx[$d][$sport['id']] ?? [];
                        $obsazeno = array_sum(array_column($bloky, 'kapacita_dilu'));
                        $max = (int)$sport['max_kapacita'];
                        $pct = $max > 0 ? min(100, round($obsazeno / $max * 100)) : 0;
                        $fillColor = $pct >= 100 ? '#ef4444' : ($pct >= 60 ? '#f59e0b' : '#10b981');
                        ?>
                        <td class="day-cell <?= $d === $today ? 'today-col' : '' ?>">
                            <?php foreach ($bloky as $r): ?>
                                <?php
                                // Starý kód: rezervace_sportovist s lekce_id — přeskočit (nová logika je níže)
                                if ($r['lekce_id']) continue;
                                $barva = $trenerBarva[$r['trener_id']] ?? '#6b7280';
                                ?>
                                <div class="rez-blok" style="background:<?= $barva ?>">
                                    <div>
                                        <i class="bi bi-person me-1"></i>
                                        <strong><?= h($r['trener_jmeno']) ?></strong>
                                        <span class="kap-badge"><?= $r['kapacita_dilu'] ?>/5</span>
                                    </div>
                                    <div><?= h(substr($r['cas_od'], 0, 5)) ?>–<?= h(substr($r['cas_do'], 0, 5)) ?></div>
                                    <?php if ($r['trenink_id']): ?>
                                        <div class="text-white-50" style="font-size:.7rem">
                                            <i class="bi bi-bicycle me-1"></i>Trénink
                                        </div>
                                    <?php elseif ($r['poznamka']): ?>
                                        <div class="text-white-50" style="font-size:.7rem">
                                            <?= h(mb_substr($r['poznamka'], 0, 30)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($r['trener_id'] == $trenerId || roleAtLeast('hlavni')): ?>
                                        <form method="post" class="mt-1"
                                              data-confirm="Zrušit rezervaci?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="rezervace_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm p-0 text-white-50"
                                                    style="font-size:.65rem;background:none;border:none">
                                                <i class="bi bi-x"></i> zrušit
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <!-- Kapacitní pruh (jen rezervace_sportovist bez lekce_id) -->
                            <?php
                            $obsazenoTeam = array_sum(array_map(
                                fn($r) => $r['lekce_id'] ? 0 : (int)$r['kapacita_dilu'],
                                $bloky
                            ));
                            $pctTeam = $max > 0 ? min(100, round($obsazenoTeam / $max * 100)) : 0;
                            $fillColorTeam = $pctTeam >= 100 ? '#ef4444' : ($pctTeam >= 60 ? '#f59e0b' : '#10b981');
                            ?>
                            <?php if ($obsazenoTeam > 0): ?>
                                <div class="cap-bar">
                                    <div class="cap-fill" style="width:<?= $pctTeam ?>%;background:<?= $fillColorTeam ?>"></div>
                                </div>
                                <div class="text-muted text-center" style="font-size:.7rem"><?= $obsazenoTeam ?>/<?= $max ?></div>
                            <?php endif; ?>

                            <!-- Individuální lekce — informační bloky (neblokují kapacitu) -->
                            <?php foreach ($lekceIdx[$d][$sport['id']] ?? [] as $l): ?>
                                <?php
                                $aktivniRez  = (int)$l['aktivni_rez'];
                                $potvrzenych = (int)$l['potvrzenych'];
                                $hasRez      = $aktivniRez > 0;
                                $cls         = 'lekce-blok ' . $l['typ'] . ($hasRez ? ' has-rezervace' : '');
                                ?>
                                <div class="<?= $cls ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span>
                                            <i class="bi bi-calendar-event me-1"></i>
                                            <strong><?= h(mb_substr($l['nazev'], 0, 22)) ?></strong>
                                        </span>
                                        <?php if ($hasRez): ?>
                                            <span class="badge bg-<?= $l['typ'] === 'zelena' ? 'success' : 'warning text-dark' ?>"
                                                  style="font-size:.65rem">
                                                <?= $potvrzenych ?>/<?= (int)$l['max_osob'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:.72rem">
                                        <?= h(substr($l['cas_od'], 0, 5)) ?>–<?= h(substr($l['cas_do'], 0, 5)) ?>
                                        · <?= (int)$l['slot_delka_min'] ?> min
                                    </div>
                                    <div style="font-size:.68rem;opacity:.75">
                                        <?php if ($hasRez): ?>
                                            <i class="bi bi-person-check me-1"></i><?= $aktivniRez ?> rezervace
                                        <?php else: ?>
                                            <i class="bi bi-hourglass me-1"></i>Bez rezervace
                                        <?php endif; ?>
                                        · <?= number_format($l['cena_kc'], 0) ?> Kč
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Plánované tréninky bez rezervace (jen s sportoviste_id) -->
                            <?php foreach ($planIdx[$d][$sport['id']] ?? [] as $pt): ?>
                                <?php
                                $barvaT = $trenerBarva[$pt['trener_id']] ?? '#3b82f6';
                                $isRecordedPlan = $pt['stav'] === 'evidovany';
                                ?>
                                <div class="plan-blok <?= $isRecordedPlan ? 'evidovany' : '' ?>" style="border-color:<?= $barvaT ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span>
                                            <i class="bi bi-calendar3-week me-1"></i>
                                            <strong><?= h(mb_substr($pt['nazev'] ?: 'Trénink', 0, 22)) ?></strong>
                                        </span>
                                        <?php if ($isRecordedPlan): ?>
                                            <span class="badge bg-success" style="font-size:.64rem">Zaevidováno</span>
                                        <?php else: ?>
                                            <span style="font-size:.68rem;opacity:.75">plán</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($pt['cas_od']): ?>
                                        <div style="font-size:.72rem">
                                            <?= h(substr($pt['cas_od'], 0, 5)) ?><?= $pt['cas_do'] ? '–'.h(substr($pt['cas_do'], 0, 5)) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size:.68rem;opacity:.8">
                                        <i class="bi bi-person me-1"></i><?= h($pt['trener_jmeno'] ?? '—') ?>
                                        <?php if ($pt['skupina_nazev']): ?>
                                            · <?= h($pt['skupina_nazev']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isRecordedPlan && (int)$pt['trenink_id'] > 0): ?>
                                        <a href="edit_trenink.php?id=<?= (int)$pt['trenink_id'] ?>" style="font-size:.68rem;color:#14532d">
                                            <i class="bi bi-bicycle me-1"></i>Otevřít trénink
                                        </a>
                                    <?php elseif (canAccess('rezervace_sportovist') && $d >= $today): ?>
                                        <a href="rezervovat_sportoviste.php?datum=<?= $d ?>&sportoviste_id=<?= $sport['id'] ?>"
                                           style="font-size:.68rem;color:#1e40af" title="Přidat rezervaci k tréninku">
                                            <i class="bi bi-lock me-1"></i>Rezervovat
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <!-- Akční ikonky — vždy zobrazeny (i pro minulá data) -->
                            <?php if (canAccess('rezervace_sportovist') || canAccess('individualni_lekce')): ?>
                            <div class="d-flex gap-1 mt-1 justify-content-center">
                                <?php if (canAccess('rezervace_sportovist')): ?>
                                <a href="rezervovat_sportoviste.php?datum=<?= $d ?>&sportoviste_id=<?= $sport['id'] ?>"
                                   class="btn btn-outline-primary btn-sm py-0 px-1"
                                   style="font-size:.7rem" title="Nová rezervace / trénink" aria-label="Nová rezervace / trénink">
                                    <i class="bi bi-lock-fill"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (canAccess('individualni_lekce')): ?>
                                <a href="individualni_lekce_form.php?datum=<?= $d ?>&sportoviste_id=<?= $sport['id'] ?>"
                                   class="btn btn-outline-success btn-sm py-0 px-1"
                                   style="font-size:.7rem" title="Nová individuální lekce" aria-label="Nová individuální lekce">
                                    <i class="bi bi-person-plus-fill"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
