<?php
require_once __DIR__ . '/includes/session_security.php';
/**
 * ajax_denny_rozvrh.php
 * Vrátí HTML s vertikální časovou osou (10:00–20:00) pro dané sportoviště a datum.
 * GET: sportoviste_id, datum, ghost_od, ghost_do (volitelné — preview výběru)
 */
app_session_start();
if (!isset($_SESSION['trener_id'])) { http_response_code(403); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/venue_calendar.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$sportId  = (int)($_GET['sportoviste_id'] ?? 0);
$datum    = trim($_GET['datum'] ?? '');
$ghostOd  = trim($_GET['ghost_od'] ?? '');
$ghostDo  = trim($_GET['ghost_do'] ?? '');

if (!$sportId || !$datum) {
    echo '<div class="text-muted small text-center py-4"><i class="bi bi-calendar3 me-1"></i>Vyberte sportoviště a datum.</div>';
    exit;
}

// ── Konstanty časové osy ───────────────────────────────────────────────────────
const START_H = 10;
const END_H   = 20;
const START_MIN = START_H * 60;  // 600
const END_MIN   = END_H   * 60;  // 1200
const PX_PER_MIN = 1;            // 1 px za minutu → 60 px/hod → 600 px celkem

function toMin(string $t): int {
    [$h, $m] = array_map('intval', explode(':', $t . ':00'));
    return $h * 60 + $m;
}
function blockGeom(string $od, string $do): ?array {
    $from = toMin(substr($od, 0, 5));
    $to   = toMin(substr($do, 0, 5));
    $from = max(START_MIN, min(END_MIN, $from));
    $to   = max(START_MIN, min(END_MIN, $to));
    if ($to <= $from) return null;
    return [
        'top'    => ($from - START_MIN) * PX_PER_MIN,
        'height' => max(18, ($to - $from) * PX_PER_MIN),
    ];
}

// ── Trenéři → barvy ───────────────────────────────────────────────────────────
$treneri = $pdo->query("SELECT id FROM treneri ORDER BY jmeno")->fetchAll(PDO::FETCH_COLUMN);
$barvy   = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];
$trBarva = [];
foreach ($treneri as $i => $tid) { $trBarva[$tid] = $barvy[$i % count($barvy)]; }

// ── Dotazy ────────────────────────────────────────────────────────────────────
$rezStmt = $pdo->prepare("
    SELECT r.cas_od, r.cas_do, r.kapacita_dilu, r.lekce_id, r.poznamka, r.trener_id,
           t.jmeno AS trener_jmeno,
           pt.nazev AS plan_nazev
    FROM rezervace_sportovist r
    LEFT JOIN treneri t  ON t.id  = r.trener_id
    LEFT JOIN planovane_treninky pt ON pt.rezervace_id = r.id
    WHERE r.sportoviste_id = ? AND r.datum = ?
    ORDER BY r.cas_od
");
$rezStmt->execute([$sportId, $datum]);
$rezervace = $rezStmt->fetchAll(PDO::FETCH_ASSOC);

$lekceStmt = $pdo->prepare("
    SELECT il.cas_od, il.cas_do, il.nazev, il.typ, il.max_osob,
           COALESCE(SUM(vr.stav IN ('ceka','potvrzena')), 0) AS aktivni_rez
    FROM individualni_lekce il
    LEFT JOIN verejne_rezervace vr ON vr.lekce_id = il.id
    WHERE il.sportoviste_id = ? AND il.datum = ? AND il.stav = 'aktivni'
    GROUP BY il.id
    ORDER BY il.cas_od
");
$lekceStmt->execute([$sportId, $datum]);
$lekce = $lekceStmt->fetchAll(PDO::FETCH_ASSOC);

$planovane = array_values(array_filter(
    venueCalendarUnreservedPlans($pdo, $datum, $datum, $sportId),
    static fn(array $plan): bool => !empty($plan['cas_od'])
));

$totalPx = (END_MIN - START_MIN) * PX_PER_MIN; // 600 px
?>
<div class="position-relative" style="height:<?= $totalPx ?>px;overflow:hidden;user-select:none">

    <!-- Mřížka hodin -->
    <?php for ($h = START_H; $h <= END_H; $h++):
        $top = ($h - START_H) * 60 * PX_PER_MIN;
    ?>
    <div class="position-absolute w-100"
         style="top:<?= $top ?>px;border-top:1px solid <?= $h === START_H ? 'transparent' : '#e5e7eb' ?>;z-index:1">
        <span style="font-size:.62rem;color:#9ca3af;line-height:1;position:absolute;left:1px;top:-8px"><?= sprintf('%02d:00', $h) ?></span>
    </div>
    <?php endfor; ?>

    <!-- Rezervace sportoviště (bez lekce) -->
    <?php foreach ($rezervace as $r):
        if ($r['lekce_id']) continue;
        $g = blockGeom($r['cas_od'], $r['cas_do']);
        if (!$g) continue;
        $barva = $trBarva[$r['trener_id']] ?? '#6b7280';
        $label = $r['plan_nazev'] ?? ($r['poznamka'] ? mb_substr($r['poznamka'], 0, 20) : '');
    ?>
    <div class="position-absolute rounded"
         style="top:<?= $g['top'] ?>px;height:<?= $g['height'] ?>px;left:28px;right:2px;
                background:<?= $barva ?>;opacity:.88;z-index:2;padding:2px 5px;overflow:hidden"
         title="<?= h($r['trener_jmeno'] ?? '') ?>: <?= h(substr($r['cas_od'],0,5)) ?>–<?= h(substr($r['cas_do'],0,5)) ?>">
        <div style="font-size:.67rem;color:#fff;line-height:1.25">
            <strong><?= h(substr($r['cas_od'],0,5)) ?>–<?= h(substr($r['cas_do'],0,5)) ?></strong>
            <span style="font-size:.6rem;background:rgba(0,0,0,.25);border-radius:2px;padding:0 3px;margin-left:2px"><?= (int)$r['kapacita_dilu'] ?>/5</span>
            <?php if ($g['height'] >= 26): ?>
            <br><?= h(mb_substr($r['trener_jmeno'] ?? '', 0, 14)) ?>
            <?php if ($label): ?><span style="opacity:.8"> · <?= h(mb_substr($label, 0, 14)) ?></span><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Individuální lekce -->
    <?php foreach ($lekce as $l):
        $g = blockGeom($l['cas_od'], $l['cas_do']);
        if (!$g) continue;
        $isGreen = $l['typ'] === 'zelena';
        $bg     = $isGreen ? '#f0fdf4'  : '#fffbeb';
        $border = $isGreen ? '#16a34a'  : '#b45309';
        $color  = $isGreen ? '#14532d'  : '#78350f';
        $rez    = (int)$l['aktivni_rez'];
    ?>
    <div class="position-absolute rounded"
         style="top:<?= $g['top'] ?>px;height:<?= $g['height'] ?>px;left:28px;right:2px;
                background:<?= $bg ?>;border:2px dashed <?= $border ?>;z-index:2;padding:2px 5px;overflow:hidden"
         title="<?= h($l['nazev']) ?>">
        <div style="font-size:.67rem;color:<?= $color ?>;line-height:1.25">
            <strong><?= h(substr($l['cas_od'],0,5)) ?>–<?= h(substr($l['cas_do'],0,5)) ?></strong>
            <?php if ($rez > 0): ?>
            <span style="font-size:.6rem;background:<?= $border ?>;color:#fff;border-radius:2px;padding:0 3px;margin-left:2px"><?= $rez ?>/<?= (int)$l['max_osob'] ?></span>
            <?php endif; ?>
            <?php if ($g['height'] >= 26): ?>
            <br><?= h(mb_substr($l['nazev'], 0, 16)) ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Plánované tréninky bez rezervace -->
    <?php foreach ($planovane as $p):
        if (!$p['cas_od'] || !$p['cas_do']) continue;
        $g = blockGeom($p['cas_od'], $p['cas_do']);
        if (!$g) continue;
        $isRecordedPlan = $p['stav'] === 'evidovany';
        $planBackground = $isRecordedPlan ? '#ecfdf3' : '#eff6ff';
        $planBorder = $isRecordedPlan ? '#198754' : '#3b82f6';
        $planColor = $isRecordedPlan ? '#14532d' : '#1e40af';
    ?>
    <div class="position-absolute rounded"
         style="top:<?= $g['top'] ?>px;height:<?= $g['height'] ?>px;left:28px;right:2px;
                background:<?= $planBackground ?>;border:2px <?= $isRecordedPlan ? 'solid' : 'dotted' ?> <?= $planBorder ?>;z-index:2;padding:2px 5px;overflow:hidden"
         title="<?= h($p['nazev'] ?? 'Trénink') ?>">
        <div style="font-size:.67rem;color:<?= $planColor ?>;line-height:1.25">
            <strong><?= h(substr($p['cas_od'],0,5)) ?>–<?= h(substr($p['cas_do'],0,5)) ?></strong>
            <?php if ($isRecordedPlan): ?><span style="font-size:.58rem;background:#198754;color:#fff;border-radius:2px;padding:0 3px">Zaevidováno</span><?php endif; ?>
            <?php if ($g['height'] >= 26): ?>
            <br><?= h(mb_substr($p['nazev'] ?? 'Trénink', 0, 16)) ?>
            <?php if ($isRecordedPlan && (int)$p['trenink_id'] > 0): ?>
                · <a href="edit_trenink.php?id=<?= (int)$p['trenink_id'] ?>" style="color:inherit">trénink</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Ghost preview (váš výběr) -->
    <?php if ($ghostOd && $ghostDo && $ghostOd < $ghostDo):
        $g = blockGeom($ghostOd, $ghostDo);
        if ($g):
    ?>
    <div class="position-absolute rounded"
         style="top:<?= $g['top'] ?>px;height:<?= $g['height'] ?>px;left:28px;right:2px;
                background:rgba(59,130,246,.22);border:2px solid #3b82f6;z-index:4;padding:2px 5px;overflow:hidden">
        <div style="font-size:.67rem;color:#1e40af;line-height:1.25">
            <i class="bi bi-crosshair me-1"></i><strong><?= h($ghostOd) ?>–<?= h($ghostDo) ?></strong>
            <?php if ($g['height'] >= 26): ?><br><span style="opacity:.7">Váš výběr</span><?php endif; ?>
        </div>
    </div>
    <?php endif; endif; ?>

</div>
<?php if (empty($rezervace) && empty($lekce) && empty($planovane)): ?>
<div class="text-muted small text-center mt-2"><i class="bi bi-check-circle text-success me-1"></i>Den je volný</div>
<?php endif; ?>
