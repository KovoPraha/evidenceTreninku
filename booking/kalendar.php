<?php
require_once dirname(__DIR__) . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf_helper.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function timeToMin(string $t): int { return (int)substr($t,0,2)*60 + (int)substr($t,3,2); }
function minToTime(int $m): string { return sprintf('%02d:%02d', intdiv($m,60), $m%60); }

/** Vygeneruje sloty z okna lekce. Vrátí pole ['cas_od'=>'10:00','cas_do'=>'11:00']. */
function generateSloty(string $casOd, string $casDo, int $slotMin): array {
    $odM  = timeToMin($casOd);
    $doM  = timeToMin($casDo);
    $sloty = [];
    for ($s = $odM; $s + $slotMin <= $doM; $s += $slotMin) {
        $sloty[] = ['cas_od' => minToTime($s), 'cas_do' => minToTime($s + $slotMin)];
    }
    return $sloty;
}

$prihlaseny   = isset($_SESSION['verejny_uzivatel_id']);
$uzivatelId   = (int)($_SESSION['verejny_uzivatel_id'] ?? 0);
$uzivatelJmeno= $_SESSION['verejny_uzivatel_jmeno'] ?? '';

// ── Filtry ────────────────────────────────────────────────────────────────────
$sportovist = $pdo->query(
    "SELECT * FROM sportovist WHERE je_verejne=1 AND aktivni=1 ORDER BY poradi"
)->fetchAll(PDO::FETCH_ASSOC);

$filterSport = (int)($_GET['sportoviste_id'] ?? 0);

// Měsíc z ?od= parametru (nebo aktuální)
$filterOd = trim($_GET['od'] ?? date('Y-m-01'));
try { $refDt = new DateTime($filterOd); } catch (Exception $e) { $refDt = new DateTime(); }
$rok   = (int)$refDt->format('Y');
$mesic = (int)$refDt->format('n');

$prvniDen    = new DateTime("$rok-$mesic-01");
$posledniDen = new DateTime($prvniDen->format('Y-m-t'));
$mesicOd     = $prvniDen->format('Y-m-d');
$mesicDo     = $posledniDen->format('Y-m-d');

// Prev / Next month
$prevMesic = (clone $prvniDen)->modify('-1 month')->format('Y-m-01');
$nextMesic = (clone $prvniDen)->modify('+1 month')->format('Y-m-01');
$sportParam = $filterSport ? "&sportoviste_id=$filterSport" : '';

// Minimální datum pro rezervaci (3 dny předem)
$minDatum = (new DateTime())->modify('+3 days')->format('Y-m-d');

// ── Načtení lekcí pro měsíc ────────────────────────────────────────────────
$params = [$mesicOd, $mesicDo];
$sportKl = '';
if ($filterSport) { $sportKl = ' AND il.sportoviste_id = ?'; $params[] = $filterSport; }

$lekceArr = $pdo->prepare("
    SELECT il.*, s.nazev AS sport_nazev, s.kod AS sport_kod, t.jmeno AS trener_jmeno
    FROM individualni_lekce il
    JOIN sportovist s ON s.id = il.sportoviste_id
    JOIN treneri t    ON t.id = il.trener_id
    WHERE il.stav = 'aktivni'
      AND il.datum BETWEEN ? AND ?
      AND s.je_verejne = 1
      {$sportKl}
    ORDER BY il.datum, il.cas_od
");
$lekceArr->execute($params);
$lekceArr = $lekceArr->fetchAll(PDO::FETCH_ASSOC);

// ── Počty rezervací per (lekce_id, slot_cas_od) ────────────────────────────
$bookingCounts  = []; // [lekce_id][slot_cas_od] = count (aktivní)
$waitingCounts  = []; // [lekce_id][slot_cas_od] = count (čekací listina)
if (!empty($lekceArr)) {
    $ids = implode(',', array_map('intval', array_column($lekceArr, 'id')));
    $bRows = $pdo->query("
        SELECT lekce_id, slot_cas_od, stav, COUNT(*) AS cnt
        FROM verejne_rezervace
        WHERE lekce_id IN ({$ids}) AND stav IN ('ceka','potvrzena','cekaci_listina')
        GROUP BY lekce_id, slot_cas_od, stav
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($bRows as $b) {
        $slotKey = substr($b['slot_cas_od'] ?? '', 0, 5);
        $lid = (int)$b['lekce_id'];
        if ($b['stav'] === 'cekaci_listina') {
            $waitingCounts[$lid][$slotKey] = ($waitingCounts[$lid][$slotKey] ?? 0) + (int)$b['cnt'];
        } else {
            $bookingCounts[$lid][$slotKey] = ($bookingCounts[$lid][$slotKey] ?? 0) + (int)$b['cnt'];
        }
    }
}

// ── Rezervace a čekací listina přihlášeného uživatele ────────────────────
$mojeSloty  = []; // [lekce_id]['HH:MM'] = true  (ceka/potvrzena)
$mujCekaci  = []; // [lekce_id]['HH:MM'] = pozice (1-based)
if ($prihlaseny && !empty($lekceArr)) {
    $ids = implode(',', array_map('intval', array_column($lekceArr, 'id')));
    $mRows = $pdo->query("
        SELECT lekce_id, slot_cas_od, stav FROM verejne_rezervace
        WHERE uzivatel_id = $uzivatelId AND lekce_id IN ($ids)
          AND stav IN ('ceka','potvrzena','cekaci_listina')
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mRows as $m) {
        $slotKey = substr($m['slot_cas_od'] ?? '', 0, 5);
        $lid = (int)$m['lekce_id'];
        if ($m['stav'] === 'cekaci_listina') {
            // Zjistit pozici na čekací listině (počet záznamu přede mnou + 1)
            $pos = ($waitingCounts[$lid][$slotKey] ?? 1); // zjednodušeno — přesná pozice níže
            $mujCekaci[$lid][$slotKey] = true;
        } else {
            $mojeSloty[$lid][$slotKey] = true;
        }
    }
    // Přesné pozice na čekací listině
    if (!empty($mujCekaci)) {
        $pozRows = $pdo->query("
            SELECT vr.lekce_id, vr.slot_cas_od,
                   (SELECT COUNT(*) FROM verejne_rezervace vr2
                    WHERE vr2.lekce_id = vr.lekce_id
                      AND vr2.slot_cas_od = vr.slot_cas_od
                      AND vr2.stav = 'cekaci_listina'
                      AND vr2.cas_rezervace <= vr.cas_rezervace) AS pozice
            FROM verejne_rezervace vr
            WHERE vr.uzivatel_id = $uzivatelId
              AND vr.lekce_id IN ($ids)
              AND vr.stav = 'cekaci_listina'
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pozRows as $pz) {
            $slotKey = substr($pz['slot_cas_od'] ?? '', 0, 5);
            $mujCekaci[(int)$pz['lekce_id']][$slotKey] = (int)$pz['pozice'];
        }
    }
}

// ── Sestavit data pro každý den (pro calendar grid a modaly) ──────────────
$dayData = []; // [Y-m-d => ['lekce'=>[], 'has_green'=>bool, 'has_yellow'=>bool, 'has_any'=>bool]]

foreach ($lekceArr as $l) {
    $datum    = $l['datum'];
    $slotMin  = max(15, (int)($l['slot_delka_min'] ?: 60));
    $sloty    = generateSloty($l['cas_od'], $l['cas_do'], $slotMin);
    $maxOsob  = (int)$l['max_osob'];

    $lekceData = [
        'id'          => (int)$l['id'],
        'nazev'       => $l['nazev'],
        'sport_nazev' => $l['sport_nazev'],
        'trener'      => $l['trener_jmeno'],
        'typ'         => $l['typ'],
        'cena_kc'     => (float)$l['cena_kc'],
        'popis'       => $l['popis'],
        'sloty'       => [],
        'has_volne'   => false,
    ];

    foreach ($sloty as $slot) {
        $obsazeno = $bookingCounts[$l['id']][$slot['cas_od']] ?? 0;
        $volno    = max(0, $maxOsob - $obsazeno);
        $moje        = !empty($mojeSloty[$l['id']][$slot['cas_od']]);
        $mojeListina = isset($mujCekaci[$l['id']][$slot['cas_od']]);
        $pozice      = is_int($mujCekaci[$l['id']][$slot['cas_od']] ?? null)
                       ? (int)$mujCekaci[$l['id']][$slot['cas_od']] : 0;
        $naListine   = $waitingCounts[$l['id']][$slot['cas_od']] ?? 0;
        $muzRez      = $datum >= $minDatum || !empty($l['vyjimka_3_dny']);

        $lekceData['sloty'][] = [
            'cas_od'      => $slot['cas_od'],
            'cas_do'      => $slot['cas_do'],
            'obsazeno'    => $obsazeno,
            'volno'       => $volno,
            'moje'        => $moje,
            'mojeListina' => $mojeListina,
            'pozice'      => $pozice,
            'naListine'   => $naListine,
            'muzRez'      => $muzRez,
        ];
        if ($volno > 0) {
            $lekceData['has_volne'] = true;
        }
    }

    if (!isset($dayData[$datum])) {
        $dayData[$datum] = ['lekce' => [], 'has_green' => false, 'has_yellow' => false, 'has_any' => false];
    }
    $dayData[$datum]['lekce'][] = $lekceData;
    $dayData[$datum]['has_any'] = true;
    if ($lekceData['has_volne']) {
        if ($l['typ'] === 'zelena') $dayData[$datum]['has_green'] = true;
        else                        $dayData[$datum]['has_yellow'] = true;
    }
}

// ── Česká lokalizace ──────────────────────────────────────────────────────
$mesiceCS = ['','Leden','Únor','Březen','Duben','Květen','Červen',
             'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
$dnyHeader = ['Po','Út','St','Čt','Pá','So','Ne'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rezervace — <?= h($mesiceCS[$mesic]) ?> <?= $rok ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php appUiAssets(); ?>
    <style>
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .cal-header-cell {
            text-align: center;
            font-weight: 600;
            font-size: .8rem;
            padding: 6px 2px;
            color: #6c757d;
        }
        .cal-day {
            min-height: 72px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            background: #fff;
            padding: 6px;
            position: relative;
            cursor: default;
            transition: box-shadow .12s;
        }
        .cal-day.has-slots {
            cursor: pointer;
            border-color: #adb5bd;
        }
        .cal-day.has-slots:hover { box-shadow: 0 2px 10px rgba(0,0,0,.12); }
        .cal-day.today { border-color: #0d6efd; border-width: 2px; }
        .cal-day.other-month { background: #f8f9fa; border-color: #f0f0f0; }
        .cal-day.past { opacity: .5; }
        .day-num {
            font-size: .85rem;
            font-weight: 600;
            color: #495057;
            line-height: 1;
        }
        .cal-day.today .day-num { color: #0d6efd; }
        .day-dots { margin-top: 4px; display: flex; gap: 3px; flex-wrap: wrap; }
        .dot {
            width: 8px; height: 8px; border-radius: 50%;
            display: inline-block;
        }
        .dot-green  { background: #16a34a; }
        .dot-yellow { background: #ca8a04; }
        .dot-full   { background: #dc3545; }
        .slot-btn { font-size: .82rem; }
        .badge-zelena { background: #16a34a; }
        .badge-zluta  { background: #b45309; }
        .slot-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .slot-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-light">

<?php publicShellNav('velodrome'); ?>

<div class="container mt-3 pb-5">

    <!-- Filtr sportoviště -->
    <form class="d-flex gap-2 mb-3 align-items-center flex-wrap" method="get">
        <input type="hidden" name="od" value="<?= h($prvniDen->format('Y-m-01')) ?>">
        <label for="calendar-sportoviste" class="small text-muted mb-0">Sportoviště:</label>
        <select id="calendar-sportoviste" name="sportoviste_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <option value="">Všechna veřejná</option>
            <?php foreach ($sportovist as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSport === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= h($s['nazev']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Navigace měsíce -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="?od=<?= $prevMesic . $sportParam ?>" class="btn btn-outline-secondary btn-sm" aria-label="Předchozí měsíc">
            <i class="bi bi-chevron-left"></i>
        </a>
        <h1 class="h5 mb-0 fw-bold"><?= $mesiceCS[$mesic] ?> <?= $rok ?></h1>
        <a href="?od=<?= $nextMesic . $sportParam ?>" class="btn btn-outline-secondary btn-sm" aria-label="Následující měsíc">
            <i class="bi bi-chevron-right"></i>
        </a>
    </div>

    <!-- Legenda -->
    <div class="d-flex gap-3 mb-3 small flex-wrap">
        <span><span class="dot dot-green d-inline-block me-1"></span>Zelená — ihned potvrzeno</span>
        <span><span class="dot dot-yellow d-inline-block me-1"></span>Žlutá — potřebuje potvrzení</span>
        <span class="text-muted"><i class="bi bi-clock me-1"></i>Zpravidla min. 3 dny předem</span>
    </div>

    <!-- Kalendářní mřížka -->
    <div class="cal-grid mb-2">
        <?php foreach ($dnyHeader as $dh): ?>
            <div class="cal-header-cell"><?= $dh ?></div>
        <?php endforeach; ?>

        <?php
        // Pondělí = 1, Neděle = 7
        $prvniIso = (int)$prvniDen->format('N'); // 1=Po ... 7=Ne
        $dnes     = date('Y-m-d');
        $pocetDni = (int)$prvniDen->format('t');

        // Prázdné buňky před prvním dnem
        for ($b = 1; $b < $prvniIso; $b++):
        ?>
            <div class="cal-day other-month"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $pocetDni; $d++):
            $datumStr = sprintf('%04d-%02d-%02d', $rok, $mesic, $d);
            $isToday  = $datumStr === $dnes;
            $isPast   = $datumStr < $dnes;
            $dayInfo  = $dayData[$datumStr] ?? null;
            $hasSlots = $dayInfo !== null;
            $cls = 'cal-day';
            if ($isToday)  $cls .= ' today';
            if ($isPast)   $cls .= ' past';
            if ($hasSlots) $cls .= ' has-slots';
        ?>
            <div class="<?= $cls ?>"
                 <?= $hasSlots ? "onclick=\"openModal('".h($datumStr)."')\" role=\"button\" tabindex=\"0\"" : '' ?>>
                <div class="day-num"><?= $d ?></div>
                <?php if ($hasSlots): ?>
                <div class="day-dots">
                    <?php if ($dayInfo['has_green']):  ?><span class="dot dot-green"></span><?php endif; ?>
                    <?php if ($dayInfo['has_yellow']): ?><span class="dot dot-yellow"></span><?php endif; ?>
                    <?php if ($dayInfo['has_any'] && !$dayInfo['has_green'] && !$dayInfo['has_yellow']): ?>
                        <span class="dot dot-full"></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>

        <?php
        // Prázdné buňky na konci (doplnit do 7)
        $posledniIso = (int)$posledniDen->format('N');
        for ($b = $posledniIso + 1; $b <= 7; $b++):
        ?>
            <div class="cal-day other-month"></div>
        <?php endfor; ?>
    </div><!-- /cal-grid -->

</div><!-- /container -->

<!-- Modal s sloty dne -->
<div class="modal fade" id="dayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dayModalTitle">Dostupné termíny</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dayModalBody">
                <div class="text-center text-muted py-3"><i class="bi bi-hourglass-split"></i> Načítám…</div>
            </div>
            <?php if (!$prihlaseny): ?>
            <div class="modal-footer bg-light">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>Pro rezervaci je třeba se přihlásit.</small>
                <a href="prihlaseni.php" class="btn btn-outline-primary btn-sm">Přihlásit</a>
                <a href="registrace.php" class="btn btn-primary btn-sm">Registrace</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
// Data všech dnů serializovaná z PHP
const dayData = <?= json_encode($dayData, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const jePrihlasen = <?= $prihlaseny ? 'true' : 'false' ?>;
const mesiceCS = ['','leden','únor','březen','duben','květen','červen','červenec','srpen','září','říjen','listopad','prosinec'];

const modal    = new bootstrap.Modal(document.getElementById('dayModal'));
const modalTitle = document.getElementById('dayModalTitle');
const modalBody  = document.getElementById('dayModalBody');

function openModal(datum) {
    const data = dayData[datum];
    if (!data) return;
    const [rok, mesStr, denStr] = datum.split('-');
    modalTitle.textContent = denStr + '. ' + mesiceCS[parseInt(mesStr)] + ' ' + rok;

    let html = '';
    for (const l of data.lekce) {
        const typBadge = l.typ === 'zelena'
            ? '<span class="badge" style="background:#16a34a">Zelená</span>'
            : '<span class="badge" style="background:#b45309">Žlutá</span>';
        const typNote = l.typ === 'zluta'
            ? '<span class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Potřebuje potvrzení trenéra</span>'
            : '';

        html += `<div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                ${typBadge}
                <strong>${esc(l.nazev)}</strong>
            </div>
            <div class="text-muted small mb-2">
                <i class="bi bi-building me-1"></i>${esc(l.sport_nazev)}
                &nbsp;·&nbsp;<i class="bi bi-person me-1"></i>${esc(l.trener)}
                &nbsp;·&nbsp;<strong>${cena(l.cena_kc)}</strong>
            </div>
            ${l.popis ? `<p class="small text-muted mb-2">${esc(l.popis)}</p>` : ''}
            ${typNote ? '<div class="mb-2">' + typNote + '</div>' : ''}`;

        if (l.sloty.length === 0) {
            html += '<div class="text-muted small">Pro toto okno nejsou žádné sloty.</div>';
        } else {
            html += '<div>';
            for (const s of l.sloty) {
                const volnoText = s.volno === 0 ? '<span class="text-danger small">Obsazeno</span>'
                    : `<span class="text-success small">${s.volno} volné</span>`;

                let akce = '';
                if (s.moje) {
                    akce = '<span class="badge bg-success">Rezervováno vámi</span>';
                } else if (s.mojeListina) {
                    akce = `<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Čekací listina #${s.pozice}</span>`;
                } else if (!s.muzRez) {
                    akce = '<span class="badge bg-secondary">Uzavřeno (3 dny)</span>';
                } else if (s.volno === 0) {
                    if (!jePrihlasen) {
                        akce = '<a href="prihlaseni.php?redirect=kalendar.php" class="btn btn-outline-warning btn-sm slot-btn">Přihlásit se</a>';
                    } else {
                        const urlWait = `rezervovat.php?lekce_id=${l.id}&slot_od=${encodeURIComponent(s.cas_od)}&slot_do=${encodeURIComponent(s.cas_do)}&waitlist=1`;
                        const listBadge = s.naListine > 0 ? ` (${s.naListine} čeká)` : '';
                        akce = `<a href="${urlWait}" class="btn btn-outline-warning btn-sm slot-btn"><i class="bi bi-hourglass me-1"></i>Čekací listina${listBadge}</a>`;
                    }
                } else if (!jePrihlasen) {
                    akce = '<a href="prihlaseni.php?redirect=kalendar.php" class="btn btn-outline-primary btn-sm slot-btn">Přihlásit se</a>';
                } else {
                    const url = `rezervovat.php?lekce_id=${l.id}&slot_od=${encodeURIComponent(s.cas_od)}&slot_do=${encodeURIComponent(s.cas_do)}`;
                    akce = `<a href="${url}" class="btn btn-primary btn-sm slot-btn"><i class="bi bi-calendar-check me-1"></i>Rezervovat</a>`;
                }

                html += `<div class="slot-row">
                    <div class="fw-semibold" style="min-width:90px">${esc(s.cas_od)}–${esc(s.cas_do)}</div>
                    <div class="flex-grow-1">${volnoText}</div>
                    <div>${akce}</div>
                </div>`;
            }
            html += '</div>';
        }
        html += '</div>';
    }

    modalBody.innerHTML = html || '<div class="text-muted text-center py-3">Žádné dostupné termíny.</div>';
    modal.show();
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function cena(v) { return Number(v).toLocaleString('cs-CZ') + ' Kč'; }
</script>
</body>
</html>
