<?php
require_once __DIR__ . '/includes/session_security.php';
// prehled_treninku_skupiny_kalendar.php
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

// -------------------------------
// Filtry: skupina -> podskupina
// -------------------------------
$skupiny = $pdo->query("
    SELECT id, nazev
    FROM skupiny
    ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);

$filterSkupinaId    = $_GET['skupina_id'] ?? '';
$filterPodskupinaId = $_GET['podskupina_id'] ?? '';

if ($filterSkupinaId !== '' && !ctype_digit($filterSkupinaId)) $filterSkupinaId = '';
if ($filterPodskupinaId !== '' && !ctype_digit($filterPodskupinaId)) $filterPodskupinaId = '';

// Podskupiny podle vybrané skupiny (pro dropdown)
$podskupiny = [];
if ($filterSkupinaId !== '') {
    $stmt = $pdo->prepare("
        SELECT id, nazev
        FROM podskupiny
        WHERE skupina_id = ?
        ORDER BY poradi, nazev
    ");
    $stmt->execute([(int)$filterSkupinaId]);
    $podskupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------
// Měsíc: přepínání vpřed/vzad
// GET ym=YYYY-MM
// -------------------------------
$ym = $_GET['ym'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = (new DateTime('today'))->format('Y-m');
}

try {
    $monthStart = new DateTime($ym . '-01');
} catch (Exception $e) {
    $monthStart = new DateTime('first day of this month');
    $ym = $monthStart->format('Y-m');
}
$monthEnd = (clone $monthStart)->modify('last day of this month');

$rangeFrom = $monthStart->format('Y-m-d');
$rangeTo   = $monthEnd->format('Y-m-d');

// týdenní osa (pondělí -> neděle) pokrývající celý měsíc
$rangeWeekStart = (clone $monthStart)->modify('monday this week');
$rangeWeekEnd   = (clone $monthEnd)->modify('sunday this week');

$weekStarts = [];
$tmp = clone $rangeWeekStart;
while ($tmp <= $rangeWeekEnd) {
    $weekStarts[] = clone $tmp;
    $tmp->modify('+1 week');
}

$ymPrev = (clone $monthStart)->modify('-1 month')->format('Y-m');
$ymNext = (clone $monthStart)->modify('+1 month')->format('Y-m');

function buildUrl(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    $qs = http_build_query($params);
    return basename(__FILE__) . ($qs ? ('?' . $qs) : '');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function dateInMonth(DateTime $d, DateTime $monthStart): bool {
    return $d->format('Y-m') === $monthStart->format('Y-m');
}

function renderWeekRow(DateTime $weekStart, DateTime $monthStart, array $treninkyByDate, array $treninkMeta, string $parentId): string {
    $czDaysShort = ['Po','Út','St','Čt','Pá','So','Ne'];

    $html = '<table class="table table-bordered table-sm mb-0 calendar-mini">';
    $html .= '<thead><tr>';
    foreach ($czDaysShort as $ds) $html .= '<th class="text-center small text-muted">'.$ds.'</th>';
    $html .= '</tr></thead><tbody><tr>';

    $d = clone $weekStart;
    for ($i=0; $i<7; $i++) {
        $isThisMonth = dateInMonth($d, $monthStart);
        if (!$isThisMonth) {
            $html .= '<td class="bg-light-subtle calendar-cell empty"></td>';
            $d->modify('+1 day');
            continue;
        }

        $dateKey = $d->format('Y-m-d');
        $dayNum  = $d->format('j');

        $list = $treninkyByDate[$dateKey] ?? [];
        $count = count($list);

        $collapseId = 'c_' . str_replace('-', '', $dateKey) . '_' . $parentId;

        $html .= '<td class="calendar-cell">';
        $html .= '<div class="d-flex justify-content-between align-items-center">';
        $html .= '<div class="fw-semibold">'.$dayNum.'</div>';

        if ($count > 0) {
            $html .= '<button class="btn btn-sm btn-outline-primary py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-controls="'.$collapseId.'">';
            $html .= $count . '×';
            $html .= '</button>';
        } else {
            $html .= '<span class="text-muted small">—</span>';
        }
        $html .= '</div>';

        if ($count > 0) {
            $html .= '<div class="collapse mt-2" id="'.$collapseId.'" data-bs-parent="#'.$parentId.'">';
            foreach ($list as $t) {
                $tid = (int)$t['id'];
                $trs = $treninkMeta[$tid]['treneri'] ?? [];
                $tgs = $treninkMeta[$tid]['tagy'] ?? [];

                $html .= '<div class="card mb-2">';
                $html .= '<div class="card-body p-2">';
                $html .= '<div class="d-flex justify-content-between align-items-start gap-2">';
                $html .= '<div class="fw-semibold">Trénink #'.$tid.'</div>';
                $html .= '<a class="btn btn-sm btn-outline-secondary py-0 px-2" href="edit_trenink.php?id='.$tid.'" target="_blank">detail</a>';
                $html .= '</div>';

                if (!empty($t['delka']) && (float)$t['delka'] > 0) {
                    $html .= '<div class="small text-muted">Délka: '.h($t['delka']).' h</div>';
                }

                if (!empty($trs)) $html .= '<div class="small"><strong>Trenéři:</strong> '.h(implode(', ', $trs)).'</div>';
                if (!empty($tgs)) $html .= '<div class="small"><strong>Tagy:</strong> '.h(implode(', ', $tgs)).'</div>';

                if (!empty($t['napln']))    $html .= '<div class="mt-2 small"><strong>Náplň:</strong><br>'.nl2br(h($t['napln'])).'</div>';
                if (!empty($t['poznamka'])) $html .= '<div class="mt-2 small"><strong>Poznámka:</strong><br>'.nl2br(h($t['poznamka'])).'</div>';
                if (!empty($t['mereni']))   $html .= '<div class="mt-2 small"><strong>Měření:</strong><br>'.nl2br(h($t['mereni'])).'</div>';

                $html .= '</div></div>';
            }
            $html .= '</div>';
        }

        $html .= '</td>';
        $d->modify('+1 day');
    }

    $html .= '</tr></tbody></table>';
    return $html;
}

// -------------------------------
// Načti tréninky (jen pro vybranou podskupinu)
// + dotáhni trenéry a tagy bez N+1
// -------------------------------
$treninkyByDate = [];
$treninkMeta = [];

if ($filterPodskupinaId !== '') {
    $pid = (int)$filterPodskupinaId;

    $stmt = $pdo->prepare("
        SELECT t.id, t.datum, t.delka, t.napln, t.poznamka, t.mereni, t.obrazky
        FROM treninky t
        JOIN trenink_podskupina tp ON tp.trenink_id = t.id
        WHERE tp.podskupina_id = :pid
          AND t.datum BETWEEN :d1 AND :d2
        ORDER BY t.datum DESC, t.id DESC
    ");
    $stmt->execute([
        ':pid' => $pid,
        ':d1'  => $rangeFrom,
        ':d2'  => $rangeTo,
    ]);
    $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ids = [];
    foreach ($treninky as $t) {
        $ids[] = (int)$t['id'];
        $d = $t['datum'];
        if (!isset($treninkyByDate[$d])) $treninkyByDate[$d] = [];
        $treninkyByDate[$d][] = $t;
    }

    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));

        $stmtTr = $pdo->prepare("
            SELECT tt.trenink_id, tr.jmeno
            FROM trenink_trener tt
            JOIN treneri tr ON tr.id = tt.trener_id
            WHERE tt.trenink_id IN ($in)
            ORDER BY tr.jmeno
        ");
        $stmtTr->execute($ids);
        while ($r = $stmtTr->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int)$r['trenink_id'];
            if (!isset($treninkMeta[$tid])) $treninkMeta[$tid] = ['treneri'=>[], 'tagy'=>[]];
            $treninkMeta[$tid]['treneri'][] = $r['jmeno'];
        }

        $stmtTag = $pdo->prepare("
            SELECT tt.trenink_id, tg.nazev
            FROM trenink_tag tt
            JOIN tagy tg ON tg.id = tt.tag_id
            WHERE tt.trenink_id IN ($in)
            ORDER BY tg.nazev
        ");
        $stmtTag->execute($ids);
        while ($r = $stmtTag->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int)$r['trenink_id'];
            if (!isset($treninkMeta[$tid])) $treninkMeta[$tid] = ['treneri'=>[], 'tagy'=>[]];
            $treninkMeta[$tid]['tagy'][] = $r['nazev'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Kalendář tréninků dle skupin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .calendar-mini th { width: calc(100%/7); }
        .calendar-cell { vertical-align: top; min-width: 120px; }
        .calendar-cell.empty { height: 56px; }
        .calendar-mini .btn { font-size: .75rem; }
        .calendar-mini .card { border-radius: .5rem; }
        .month-title { font-weight: 600; }
        @media (max-width: 992px) { .calendar-cell { min-width: 0; } }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="mb-0">Tréninky dle skupiny / podskupiny – kalendář</h1>

        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= h(buildUrl(['ym' => $ymPrev])) ?>">
                ◀️ <?= h($ymPrev) ?>
            </a>
            <span class="badge text-bg-light border">
                Zvolený měsíc: <?= h($monthStart->format('m/Y')) ?>
            </span>
            <a class="btn btn-outline-secondary btn-sm" href="<?= h(buildUrl(['ym' => $ymNext])) ?>">
                <?= h($ymNext) ?> ▶️
            </a>
        </div>
    </div>

    <div class="text-muted small mt-1">
        Rozsah: <?= h($rangeFrom) ?> → <?= h($rangeTo) ?>
    </div>

    <div class="card mt-3 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="ym" value="<?= h($ym) ?>">

                <div class="col-md-4">
                    <label class="form-label" for="skupina_id">Skupina</label>
                    <select class="form-select" id="skupina_id" name="skupina_id" onchange="this.form.submit()">
                        <option value="">-- vyber skupinu --</option>
                        <?php foreach ($skupiny as $sk): ?>
                            <option value="<?= (int)$sk['id'] ?>" <?= ($filterSkupinaId == $sk['id']) ? 'selected' : '' ?>>
                                <?= h($sk['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="podskupina_id">Podskupina</label>
                    <select class="form-select" id="podskupina_id" name="podskupina_id">
                        <option value="">-- vyber podskupinu --</option>
                        <?php foreach ($podskupiny as $ps): ?>
                            <option value="<?= (int)$ps['id'] ?>" <?= ($filterPodskupinaId == $ps['id']) ? 'selected' : '' ?>>
                                <?= h($ps['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button class="btn btn-primary" type="submit">Načíst kalendář</button>
                    <a class="btn btn-outline-secondary" href="<?= h(buildUrl(['skupina_id'=>null,'podskupina_id'=>null])) ?>">Reset filtrů</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filterPodskupinaId === ''): ?>
        <div class="alert alert-info">
            Vyber skupinu a podskupinu – poté se zobrazí kalendář pro zvolený měsíc.
        </div>
    <?php else: ?>

        <?php $parentId = 'month_parent_single'; ?>

        <div class="row g-3 mb-2">
            <div class="col-12">
                <div class="p-2 bg-white border rounded">
                    <div class="month-title">
                        <?= h($monthStart->format('m/Y')) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($weekStarts as $ws): ?>
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="bg-white border rounded p-2" id="<?= h($parentId) ?>">
                        <div class="small text-muted mb-2">
                            Týden od <?= h($ws->format('d.m.Y')) ?>
                        </div>
                        <?= renderWeekRow($ws, $monthStart, $treninkyByDate, $treninkMeta, $parentId) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php
        $total = 0;
        foreach ($treninkyByDate as $arr) $total += count($arr);
        ?>
        <?php if ($total === 0): ?>
            <div class="alert alert-warning">
                V zadané podskupině nejsou v tomto měsíci žádné tréninky.
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>


</body>
</html>
