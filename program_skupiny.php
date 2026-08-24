<?php
/**
 * program_skupiny.php — Veřejný program skupiny
 * Dostupné bez přihlášení. URL: ?hash=<skupina_hash>
 * Zobrazí nadcházející plánované tréninky skupiny (4–6 týdnů dopředu).
 */
require_once __DIR__ . '/db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── 1. Načíst skupinu podle hash ─────────────────────────────────────────────
$groupHash = trim($_GET['hash'] ?? '');
if ($groupHash === '') {
    http_response_code(400);
    die('<p>Chybí parametr hash skupiny.</p>');
}

$stmtG = $pdo->prepare("SELECT id, nazev FROM skupiny WHERE hash = ?");
$stmtG->execute([$groupHash]);
$skupina = $stmtG->fetch(PDO::FETCH_ASSOC);
if (!$skupina) {
    http_response_code(404);
    die('<p>Skupina nebyla nalezena.</p>');
}
$skupinaId   = (int)$skupina['id'];
$skupinaNazev = $skupina['nazev'];

// ── 2. Parametry: počet týdnů dopředu ────────────────────────────────────────
$tyzdny = max(1, min(12, (int)($_GET['tydny'] ?? 6)));
$datumOd = date('Y-m-d');
$datumDo = date('Y-m-d', strtotime("+{$tyzdny} weeks"));

// ── 3. Načíst plánované tréninky skupiny ─────────────────────────────────────
$stmtPT = $pdo->prepare("
    SELECT pt.id, pt.datum, pt.cas_od, pt.cas_do, pt.nazev, pt.kategorie,
           pt.popis, pt.trenink_id,
           ps.nazev AS podskupina_nazev,
           sp.nazev AS sport_nazev,
           t.jmeno  AS trener_jmeno
    FROM planovane_treninky pt
    LEFT JOIN podskupiny ps ON ps.id = pt.podskupina_id
    LEFT JOIN sportovist  sp ON sp.id = pt.sportoviste_id
    LEFT JOIN treneri      t ON t.id  = pt.trener_id
    WHERE pt.skupina_id = ?
      AND pt.stav IN ('planovany', 'evidovany')
      AND pt.datum BETWEEN ? AND ?
    ORDER BY pt.datum ASC, pt.cas_od ASC
");
$stmtPT->execute([$skupinaId, $datumOd, $datumDo]);
$planovane = $stmtPT->fetchAll(PDO::FETCH_ASSOC);

// ── 4. Seskupit po dnech ─────────────────────────────────────────────────────
$byDate = [];
foreach ($planovane as $p) {
    $byDate[$p['datum']][] = $p;
}

$kategorieMeta = [
    'silnice'   => ['label' => 'Silnice',   'color' => '#16a34a', 'bg' => '#dcfce7', 'icon' => '🚴'],
    'mtb'       => ['label' => 'MTB',       'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => '🌲'],
    'draha'     => ['label' => 'Dráha',     'color' => '#2563eb', 'bg' => '#dbeafe', 'icon' => '⏱'],
    'cyklokros' => ['label' => 'Cyklokros', 'color' => '#ea580c', 'bg' => '#ffedd5', 'icon' => '🌀'],
    'posilovna' => ['label' => 'Posilovna', 'color' => '#dc2626', 'bg' => '#fee2e2', 'icon' => '💪'],
    'atletika'  => ['label' => 'Atletika',  'color' => '#0891b2', 'bg' => '#cffafe', 'icon' => '🏃'],
    'cviceni'   => ['label' => 'Cvičení',   'color' => '#6b7280', 'bg' => '#f3f4f6', 'icon' => '🫀'],
    'plavani'   => ['label' => 'Plavání',   'color' => '#0d9488', 'bg' => '#ccfbf1', 'icon' => '🌊'],
];

$czDayNames = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];
$czMonthNames = [
    1=>'ledna',2=>'února',3=>'března',4=>'dubna',5=>'května',6=>'června',
    7=>'července',8=>'srpna',9=>'září',10=>'října',11=>'listopadu',12=>'prosince'
];

function formatDatum(string $datum, array $czDay, array $czMonth): string {
    $ts = strtotime($datum);
    $dow = (int)date('w', $ts);
    $d   = (int)date('j', $ts);
    $m   = (int)date('n', $ts);
    $y   = date('Y', $ts);
    return $czDay[$dow] . ' ' . $d . '. ' . $czMonth[$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Program skupiny — <?= h($skupinaNazev) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php appUiAssets(); ?>
    <style>
        body { background: #f0f2f5; font-family: system-ui, -apple-system, sans-serif; }
        .page-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff; padding: 2rem 0 1.5rem; margin-bottom: 2rem;
        }
        .week-label {
            font-size: .75rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #6b7280;
            border-bottom: 2px solid #e5e7eb; padding-bottom: .35rem;
            margin: 1.5rem 0 .75rem;
        }
        .trening-card {
            border: none; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
            margin-bottom: .65rem; overflow: hidden;
        }
        .trening-card .card-body { padding: .75rem 1rem; }
        .kat-badge {
            display: inline-block; padding: .2rem .55rem; border-radius: 20px;
            font-size: .7rem; font-weight: 700; letter-spacing: .04em; margin-bottom: .35rem;
        }
        .evidovano-bar {
            border-left: 4px solid #16a34a; padding-left: .6rem;
        }
        .info-row { font-size: .82rem; color: #6b7280; display: flex; flex-wrap: wrap; gap: .6rem; margin-top: .3rem; }
        .info-row i { font-size: .85rem; }
        .empty-state { text-align: center; padding: 3rem 1rem; color: #9ca3af; }
        .filter-bar { background: #fff; border-radius: 10px; padding: .75rem 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.07); margin-bottom: 1.5rem; }
        .week-sep { height: 1px; background: #e5e7eb; margin: 1.5rem 0 1rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/hlavicka.php'; ?>

<div class="page-header">
    <div class="container">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
                <div class="opacity-75 small mb-1"><i class="bi bi-calendar3-week me-1"></i>Program skupiny</div>
                <h1 class="h3 fw-bold mb-0"><?= h($skupinaNazev) ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">

    <!-- Filter bar -->
    <div class="filter-bar d-flex align-items-center gap-3 flex-wrap">
        <span class="text-muted small fw-semibold">Zobrazit:</span>
        <?php foreach ([2=>2, 4=>4, 6=>6, 8=>8] as $t => $tLabel): ?>
            <a href="?hash=<?= h($groupHash) ?>&tydny=<?= $t ?>"
               class="btn btn-sm <?= $tyzdny === $t ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $tLabel ?> týdny
            </a>
        <?php endforeach; ?>
        <span class="ms-auto text-muted small">
            <?= h(date('j. n.')) ?> – <?= h(date('j. n. Y', strtotime($datumDo))) ?>
        </span>
    </div>

    <?php if (empty($byDate)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x" style="font-size:3rem;"></i>
            <p class="mt-3 mb-0 fw-semibold">Žádné plánované tréninky</p>
            <p class="small">V nadcházejících <?= $tyzdny ?> týdnech nejsou žádné tréninky pro tuto skupinu.</p>
        </div>
    <?php else: ?>

        <?php
        $prevWeek = null;
        foreach ($byDate as $datum => $dne):
            $ts = strtotime($datum);
            // Týdenní nadpis — ISO week
            $weekNum = (int)date('W', $ts);
            $weekYear = (int)date('o', $ts);
            $weekKey  = $weekYear . 'W' . $weekNum;

            if ($weekKey !== $prevWeek):
                $prevWeek = $weekKey;
                // Pondělí a neděle týdne (ISO: N = 1..7, 1=Po, 7=Ne)
                $isoDay   = (int)date('N', $ts); // 1=Po, 7=Ne
                $mondayTs = $ts - ($isoDay - 1) * 86400;
                $sundayTs = $mondayTs + 6 * 86400;
        ?>
            <div class="week-label">
                Týden <?= $weekNum ?> &nbsp;·&nbsp;
                <?= date('j. n.', $mondayTs) ?> – <?= date('j. n. Y', $sundayTs) ?>
            </div>
        <?php endif; ?>

            <!-- Den -->
            <div class="mb-2">
                <div class="text-muted small fw-semibold mb-1 ms-1">
                    <?php
                    $dow = (int)date('w', $ts);
                    $d   = (int)date('j', $ts);
                    $m   = (int)date('n', $ts);
                    $isToday = ($datum === date('Y-m-d'));
                    echo $czDayNames[$dow] . ' ' . $d . '. ' . $czMonthNames[$m];
                    if ($isToday): ?> <span style="background:#fef3c7;color:#92400e;border-radius:4px;padding:.1rem .4rem;font-size:.7rem;font-weight:700">Dnes</span><?php endif; ?>
                </div>

                <?php foreach ($dne as $p):
                    $kat = $kategorieMeta[$p['kategorie'] ?? ''] ?? null;
                    $cas = '';
                    if (!empty($p['cas_od'])) {
                        $cas = substr($p['cas_od'], 0, 5);
                        if (!empty($p['cas_do'])) $cas .= '–' . substr($p['cas_do'], 0, 5);
                    }
                    $isEvidovano = !empty($p['trenink_id']);
                ?>
                <div class="trening-card card" style="border-left: 4px solid <?= $kat ? $kat['color'] : '#9ca3af' ?>;">
                    <div class="card-body <?= $isEvidovano ? 'evidovano-bar' : '' ?>">
                        <?php if ($kat): ?>
                            <span class="kat-badge" style="background:<?= $kat['bg'] ?>;color:<?= $kat['color'] ?>;">
                                <?= $kat['icon'] ?> <?= h($kat['label']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($isEvidovano): ?>
                            <span class="kat-badge" style="background:#dcfce7;color:#16a34a;">
                                <i class="bi bi-check2-circle"></i> Evidováno
                            </span>
                        <?php endif; ?>

                        <div class="fw-semibold" style="font-size:.95rem;">
                            <?= h($p['nazev'] ?: 'Trénink') ?>
                        </div>

                        <div class="info-row">
                            <?php if ($cas): ?>
                                <span><i class="bi bi-clock"></i> <?= h($cas) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['podskupina_nazev'])): ?>
                                <span><i class="bi bi-people"></i> <?= h($p['podskupina_nazev']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['sport_nazev'])): ?>
                                <span><i class="bi bi-geo-alt"></i> <?= h($p['sport_nazev']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($p['trener_jmeno'])): ?>
                                <span><i class="bi bi-person"></i> <?= h($p['trener_jmeno']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($p['popis'])): ?>
                            <div class="mt-2 small text-muted"><?= nl2br(h($p['popis'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center text-muted small mt-4">
        <i class="bi bi-shield-check me-1"></i>
        Veřejný program skupiny <?= h($skupinaNazev) ?> &nbsp;·&nbsp;
        Aktualizováno <?= date('j. n. Y H:i') ?>
    </div>
</div>


</body>
</html>
