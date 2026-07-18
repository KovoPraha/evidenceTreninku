<?php
/**
 * AJAX endpoint – seznam tréninků pro veřejný profil sportovce
 * Params: hash, rok (0 = vše), typ (optional filter: kolo|beh|posilovna|'')
 */
require_once __DIR__ . '/db.php';
header('Content-Type: text/html; charset=utf-8');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmtDate(?string $d): string {
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts ? date('d.m.Y', $ts) : h($d);
}

function renderMereniData(array $mz): string {
    $typ   = $mz['typ'] ?? '';
    $parts = [];

    if ($typ === 'kolo' || $typ === 'beh') {
        if (!empty($mz['vzdalenost']) && $mz['vzdalenost'] !== '0')
            $parts[] = '<strong>' . h($mz['vzdalenost']) . '</strong> km';
        if (!empty($mz['cas']))
            $parts[] = '⏱ ' . h($mz['cas']);
        if (!empty($mz['prevod']) && $typ === 'kolo')
            $parts[] = 'převod ' . h($mz['prevod']);
    } elseif ($typ === 'posilovna') {
        $cvik = $mz['cvik_nazev'] ?: ($mz['cvik_id'] ? '#' . (int)$mz['cvik_id'] : '—');
        $parts[] = '<strong>' . h($cvik) . '</strong>';
        if (!empty($mz['vaha']) && $mz['vaha'] !== '0')
            $parts[] = h($mz['vaha']) . ' kg';
        if (!empty($mz['opakovani']))
            $parts[] = (int)$mz['opakovani'] . '×';
        if (!empty($mz['rpe']))
            $parts[] = 'RPE ' . h($mz['rpe']);
    } elseif ($typ === 'kolo_krouzek' || $typ === 'kolo_silnice' || $typ === 'kolo_mtb') {
        $segment = $mz['segment_nazev'] ?? ($mz['segment_id'] ? '#' . (int)$mz['segment_id'] : '—');
        $parts[] = '<strong>' . h($segment) . '</strong>';
        if (!empty($mz['cas']))
            $parts[] = '⏱ ' . h($mz['cas']);
    } else {
        return '<span class="text-muted">—</span>';
    }

    return implode(' · ', $parts) ?: '<span class="text-muted">—</span>';
}

// --- auth ---
$hash = trim($_GET['hash'] ?? '');
if (!$hash) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Neplatný přístup.</div>';
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM sportovci WHERE hash = ?');
$stmt->execute([$hash]);
$spRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$spRow) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Sportovec nenalezen.</div>';
    exit;
}

$sportovec_id = (int)$spRow['id'];
$rok = isset($_GET['rok']) && ctype_digit($_GET['rok']) ? (int)$_GET['rok'] : 0;

// --- poznámky sportovce ---
$notes = [];
$nq = $pdo->prepare('SELECT trenink_id, poznamka FROM sportovec_poznamka WHERE sportovec_id = ?');
$nq->execute([$sportovec_id]);
while ($row = $nq->fetch(PDO::FETCH_ASSOC)) {
    $notes[(int)$row['trenink_id']] = $row['poznamka'];
}

// --- tréninky ---
$yearCond = ($rok > 0) ? 'AND YEAR(t.datum) = :rok' : '';

$sql = "
    SELECT DISTINCT
        t.id, t.datum, t.delka, t.napln, t.obrazky
    FROM treninky t
    LEFT JOIN trenink_sportovec ts
        ON ts.trenink_id = t.id AND ts.sportovec_id = :sid
    LEFT JOIN trenink_mereni tm
        ON tm.trenink_id = t.id
    LEFT JOIN mereni_zaznamy mz
        ON mz.id = tm.mereni_id AND mz.sportovec_id = :sid
    WHERE (ts.sportovec_id IS NOT NULL OR mz.sportovec_id IS NOT NULL)
    $yearCond
    ORDER BY t.datum DESC, t.id DESC
";

$params = [':sid' => $sportovec_id];
if ($rok > 0) $params[':rok'] = $rok;

$stmtT = $pdo->prepare($sql);
$stmtT->execute($params);
$treninky = $stmtT->fetchAll(PDO::FETCH_ASSOC);

// --- měření k tréninku ---
$mereniByTrenink = [];
$treninkIds = array_map(fn($r) => (int)$r['id'], $treninky);

if (!empty($treninkIds)) {
    $in    = implode(',', array_fill(0, count($treninkIds), '?'));
    $sqlM  = "
        SELECT
            tm.trenink_id, tm.poradi,
            mz.id AS mereni_id, mz.typ,
            mz.vzdalenost, mz.cas, mz.prevod,
            mz.cvik_id, mz.segment_id, mz.vaha, mz.opakovani, mz.rpe,
            mz.poznamka AS mereni_poznamka,
            c.nazev AS cvik_nazev,
            seg.nazev AS segment_nazev
        FROM trenink_mereni tm
        JOIN mereni_zaznamy mz ON mz.id = tm.mereni_id
        LEFT JOIN cviky c ON c.id = mz.cvik_id
        LEFT JOIN segmenty seg ON seg.id = mz.segment_id
        WHERE tm.trenink_id IN ($in)
          AND mz.sportovec_id = ?
        ORDER BY tm.trenink_id ASC, tm.poradi ASC, mz.id ASC
    ";
    $params2 = array_merge($treninkIds, [$sportovec_id]);
    $st = $pdo->prepare($sqlM);
    $st->execute($params2);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $mereniByTrenink[(int)$r['trenink_id']][] = $r;
    }
}

// --- výstup ---
if (empty($treninky)) {
    echo '<div class="alert alert-info m-0">Za vybraný rok nejsou žádné tréninky.</div>';
    exit;
}

$totalMinutes = 0;
foreach ($treninky as $t) {
    if (!empty($t['delka'])) {
        $totalMinutes += (float)str_replace(',', '.', $t['delka']) * 60;
    }
}
$totalH = floor($totalMinutes / 60);
$totalM = (int)($totalMinutes % 60);
$totalHlabel = $totalH > 0 ? "{$totalH} h " . ($totalM > 0 ? "{$totalM} min" : '') : ($totalM > 0 ? "{$totalM} min" : '');
?>

<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <span class="badge bg-light text-dark border fs-6">
        Tréninků: <strong><?= count($treninky) ?></strong>
    </span>
    <?php if ($totalHlabel): ?>
    <span class="badge bg-light text-dark border fs-6">
        Celkem: <strong><?= h($totalHlabel) ?></strong>
    </span>
    <?php endif; ?>
</div>

<?php foreach ($treninky as $t):
    $tid_int = (int)$t['id'];
    $thumbs  = array_filter(array_map('trim', explode(',', $t['obrazky'] ?? '')));
    $note    = $notes[$tid_int] ?? '';
    $mRows   = $mereniByTrenink[$tid_int] ?? [];
    $delka   = !empty($t['delka']) ? $t['delka'] : null;
?>
<div class="card mb-3 trenink-card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <strong class="me-1"><?= fmtDate($t['datum']) ?></strong>
        <?php if ($delka !== null): ?>
            <span class="badge bg-secondary"><?= h((string)$delka) ?> h</span>
        <?php endif; ?>
        <?php if (!empty($mRows)): ?>
            <span class="badge bg-info text-dark"><?= count($mRows) ?> měření</span>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <?php if (!empty($thumbs)): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($thumbs as $img): ?>
                    <a href="nahrane_obrazky/<?= h($img) ?>" target="_blank">
                        <img src="nahrane_obrazky/<?= h($img) ?>" class="thumb-tr" alt="">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($t['napln'])): ?>
            <div class="mb-3">
                <div class="fw-semibold text-muted small mb-1">NÁPLŇ TRÉNINKU</div>
                <div class="ps-2 border-start border-2"><?= nl2br(h($t['napln'])) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($mRows)): ?>
            <div class="mb-3">
                <div class="fw-semibold text-muted small mb-2">MOJE MĚŘENÍ</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:100px;">Typ</th>
                                <th>Hodnoty</th>
                                <th style="width:180px;">Poznámka</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mRows as $mz): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $typBadge = ['kolo' => 'bg-primary', 'kolo_krouzek' => 'bg-info', 'kolo_silnice' => 'bg-danger', 'kolo_mtb' => 'bg-warning text-dark', 'beh' => 'bg-success', 'posilovna' => 'bg-warning text-dark'];
                                        $cls = $typBadge[$mz['typ']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $cls ?>"><?= h($mz['typ']) ?></span>
                                    </td>
                                    <td><?= renderMereniData($mz) ?></td>
                                    <td class="text-muted small"><?= nl2br(h($mz['mereni_poznamka'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Athlete note – AJAX save -->
        <div class="mt-3">
            <div class="fw-semibold text-muted small mb-1">TVOJE POZNÁMKA</div>
            <textarea class="form-control athlete-note-input"
                      rows="2"
                      data-tid="<?= $tid_int ?>"><?= h($note) ?></textarea>
            <div class="d-flex align-items-center gap-2 mt-1">
                <button class="btn btn-sm btn-primary save-note-btn" data-tid="<?= $tid_int ?>">
                    Uložit
                </button>
                <span class="note-status text-success small" data-tid="<?= $tid_int ?>" style="display:none;">
                    ✓ Uloženo
                </span>
            </div>
        </div>

    </div>
</div>
<?php endforeach; ?>
