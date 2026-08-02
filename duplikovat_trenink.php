<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Filtr skupiny ──────────────────────────────────────────────────────────
$filterGroup = $_GET['skupina_id'] ?? '';
if ($filterGroup !== '' && !ctype_digit((string)$filterGroup)) $filterGroup = '';

$skupiny = [];
try {
    $skupiny = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('duplikovat_trenink.php skupiny: ' . $e->getMessage());
}

// ── Načtení tréninků ───────────────────────────────────────────────────────
$treninky = [];
try {
    $sql = "
        SELECT
            t.id,
            t.datum,
            t.napln,
            t.delka,
            t.kategorie,
            GROUP_CONCAT(DISTINCT s.nazev  ORDER BY s.nazev  SEPARATOR ', ') AS skupiny_nazev,
            GROUP_CONCAT(DISTINCT ps.nazev ORDER BY ps.nazev SEPARATOR ', ') AS podskupiny_nazev,
            COUNT(DISTINCT ts.sportovec_id) AS pocet_ucastniku
        FROM treninky t
        LEFT JOIN trenink_skupina  tsk ON tsk.trenink_id = t.id
        LEFT JOIN skupiny          s   ON s.id  = tsk.skupina_id
        LEFT JOIN trenink_podskupina tp ON tp.trenink_id = t.id
        LEFT JOIN podskupiny       ps  ON ps.id = tp.podskupina_id
        LEFT JOIN trenink_sportovec ts ON ts.trenink_id = t.id
        WHERE 1=1
    ";
    $params = [];
    if ($filterGroup !== '') {
        $sql .= ' AND tsk.skupina_id = ?';
        $params[] = (int)$filterGroup;
    }
    $sql .= ' GROUP BY t.id ORDER BY t.datum DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('duplikovat_trenink.php main: ' . $e->getMessage());
}

// ── Seskupení podle měsíce ─────────────────────────────────────────────────
// Kategorie meta
$kategorieMeta = [
    'silnice'   => ['label'=>'Silnice',    'color'=>'success',  'icon'=>'bi-bicycle'],
    'mtb'       => ['label'=>'MTB',        'color'=>'warning',  'icon'=>'bi-tree'],
    'draha'     => ['label'=>'Dráha',      'color'=>'primary',  'icon'=>'bi-stopwatch'],
    'cyklokros' => ['label'=>'Cyklokros',  'color'=>'orange',   'icon'=>'bi-signpost-split'],
    'posilovna' => ['label'=>'Posilovna',  'color'=>'danger',   'icon'=>'bi-heart-pulse'],
    'atletika'  => ['label'=>'Atletika',   'color'=>'info',     'icon'=>'bi-person-walking'],
    'cviceni'   => ['label'=>'Cvičení',    'color'=>'secondary','icon'=>'bi-activity'],
    'plavani'   => ['label'=>'Plavání',    'color'=>'teal',     'icon'=>'bi-water'],
];

$byMonth = [];
$czDays  = ['Monday'=>'Po','Tuesday'=>'Út','Wednesday'=>'St',
            'Thursday'=>'Čt','Friday'=>'Pá','Saturday'=>'So','Sunday'=>'Ne'];
$czMonths = [1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
             7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'];

foreach ($treninky as $t) {
    try {
        $dt  = new DateTime($t['datum']);
        $key = $dt->format('Y-m');   // "2026-02"
        $mon = (int)$dt->format('n');
        $yr  = $dt->format('Y');
        $byMonth[$key]['label'] = ($czMonths[$mon] ?? $mon) . ' ' . $yr;
        $byMonth[$key]['items'][] = $t;
    } catch (Exception $e) {
        $byMonth['?']['label'] = 'Neznámý datum';
        $byMonth['?']['items'][] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Duplikovat trénink — výběr vzoru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }

        .section-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            font-size: .92rem;
            padding: .6rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .hero-card {
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }

        /* Měsíční nadpis */
        .month-heading {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6c757d;
            padding: .4rem .25rem;
            margin-top: 1.5rem;
            margin-bottom: .4rem;
            border-bottom: 2px solid #dee2e6;
        }
        .month-heading:first-child { margin-top: 0; }

        /* Karta tréninku */
        .trenink-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            padding: .75rem 1rem;
            margin-bottom: .5rem;
            text-decoration: none;
            color: inherit;
            transition: box-shadow .15s, border-color .15s, transform .1s;
            cursor: pointer;
        }
        .trenink-card:hover {
            box-shadow: 0 4px 16px rgba(13,110,253,.13);
            border-color: #0d6efd;
            transform: translateY(-1px);
            color: inherit;
        }

        .date-badge {
            min-width: 56px;
            text-align: center;
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: #fff;
            border-radius: 8px;
            padding: .4rem .5rem;
            flex-shrink: 0;
        }
        .date-badge .day-num  { font-size: 1.3rem; font-weight: 700; line-height: 1; }
        .date-badge .day-name { font-size: .72rem; opacity: .85; }

        .trenink-meta { flex: 1; min-width: 0; }
        .trenink-meta .group-name { font-weight: 600; font-size: .95rem; }
        .trenink-meta .sub-info   { font-size: .83rem; color: #6c757d; }

        .arrow-icon {
            color: #adb5bd;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .trenink-card:hover .arrow-icon { color: #0d6efd; }

        .badge-ucastnici {
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 999px;
            padding: .2rem .55rem;
            font-size: .78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .napln-preview {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: .82rem;
            color: #555;
            margin-top: .15rem;
        }

        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
        .badge-kat {
            font-size: .72rem;
            padding: .2rem .5rem;
            border-radius: 4px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4">

    <!-- Hero banner -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold fs-5">
                    <i class="bi bi-copy me-2 opacity-75"></i>Duplikovat trénink
                </div>
                <div class="opacity-75 small">
                    Vyberte vzorový trénink — přenesou se skupina, podskupiny a účastníci
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-center">
                    <div class="fs-4 fw-bold"><?= count($treninky) ?></div>
                    <div class="opacity-75" style="font-size:.72rem;">tréninků</div>
                </div>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Zpět
                </a>
            </div>
        </div>
    </div>

    <!-- Filtr skupiny -->
    <div class="card section-card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-funnel"></i>Filtrovat podle skupiny
        </div>
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <select name="skupina_id" class="form-select form-select-sm" style="max-width:260px;">
                    <option value="">— všechny skupiny —</option>
                    <?php foreach ($skupiny as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"
                            <?= (string)$filterGroup === (string)$s['id'] ? 'selected' : '' ?>>
                            <?= h($s['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i>Filtrovat
                </button>
                <?php if ($filterGroup !== ''): ?>
                <a href="duplikovat_trenink.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Zrušit filtr
                </a>
                <?php endif; ?>
            </form>
            <div class="mt-2">
                <input type="text" id="quickSearch" class="form-control form-control-sm"
                       placeholder="Rychlé hledání v náplni, skupině, podskupině…" style="max-width:400px;">
            </div>
        </div>
    </div>

    <!-- Výsledky -->
    <?php if (empty($treninky)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-50"></i>
            Žádné tréninky nebyly nalezeny.
        </div>
    <?php else: ?>

    <div class="row g-3">
        <div class="col-lg-8 col-md-10">

            <?php foreach ($byMonth as $monthKey => $monthData):
                $monthItems = $monthData['items'];
            ?>
            <div class="month-heading">
                <i class="bi bi-calendar3 me-2"></i><?= h($monthData['label']) ?>
                <span class="ms-2" style="font-weight:400;text-transform:none;letter-spacing:0;font-size:.8rem;">
                    (<?= count($monthItems) ?> tréninků)
                </span>
            </div>

            <?php foreach ($monthItems as $t):
                try { $dt = new DateTime($t['datum']); }
                catch (Exception $e) { $dt = null; }
                $dayNum  = $dt ? $dt->format('j') : '?';
                $dayShort = $dt ? ($czDays[$dt->format('l')] ?? '?') : '?';
                $tid      = (int)$t['id'];
                $tKat     = $t['kategorie'] ?? '';
                $tKm      = $kategorieMeta[$tKat] ?? null;
            ?>
            <a href="formular.php?duplikat=<?= $tid ?>" class="trenink-card">

                <!-- Datum badge -->
                <div class="date-badge">
                    <div class="day-num"><?= $dayNum ?></div>
                    <div class="day-name"><?= h($dayShort) ?></div>
                </div>

                <!-- Metadata -->
                <div class="trenink-meta">
                    <div class="group-name d-flex align-items-center gap-2">
                        <?php if ($t['skupiny_nazev']): ?>
                            <span>
                                <i class="bi bi-people me-1 text-primary" style="font-size:.9rem;"></i>
                                <?= h($t['skupiny_nazev']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Bez skupiny</span>
                        <?php endif; ?>
                        <?php if ($tKm): ?>
                            <span class="badge bg-<?= $tKm['color'] ?> badge-kat"><i class="bi <?= $tKm['icon'] ?> me-1"></i><?= $tKm['label'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sub-info d-flex flex-wrap gap-2 align-items-center mt-1">
                        <?php if ($t['podskupiny_nazev']): ?>
                            <span>
                                <i class="bi bi-diagram-2 me-1"></i><?= h($t['podskupiny_nazev']) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($t['delka']): ?>
                            <span>
                                <i class="bi bi-clock me-1"></i><?= h($t['delka']) ?> h
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($t['napln']): ?>
                        <div class="napln-preview">
                            <?= h($t['napln']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Počet účastníků -->
                <?php if ($t['pocet_ucastniku'] > 0): ?>
                <div class="badge-ucastnici">
                    <i class="bi bi-person-check me-1"></i><?= (int)$t['pocet_ucastniku'] ?> os.
                </div>
                <?php endif; ?>

                <!-- Šipka -->
                <div class="arrow-icon">
                    <i class="bi bi-arrow-right-circle"></i>
                </div>

            </a>
            <?php endforeach; ?>

            <?php endforeach; ?>

        </div><!-- /col -->

        <div class="col-lg-4 d-none d-lg-block">
            <div class="card section-card position-sticky" style="top:1.5rem;">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i>Jak to funguje
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-2">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:28px;height:28px;font-size:.8rem;font-weight:700;">1</div>
                            <div class="small">Vyberte trénink ze seznamu vlevo — přenesou se jeho <strong>skupina, podskupiny a seznam účastníků</strong>.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:28px;height:28px;font-size:.8rem;font-weight:700;">2</div>
                            <div class="small">Datum bude předvyplněno na <strong>dnešní den</strong>. Náplň, měření a poznámky se nepřenáší — vyplníte je nově.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:28px;height:28px;font-size:.8rem;font-weight:700;">3</div>
                            <div class="small">Na formuláři zkontrolujte vše a uložte nový trénink.</div>
                        </div>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        <i class="bi bi-lightbulb me-1 text-warning"></i>
                        Typické použití: <strong>soustředění</strong>, kdy se každý den opakuje stejná parta sportovců.
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->
    <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const input = document.getElementById('quickSearch');
    if (!input) return;

    const cards = document.querySelectorAll('.trenink-card');
    const headings = document.querySelectorAll('.month-heading');

    input.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();

        cards.forEach(card => {
            if (q === '') {
                card.style.display = '';
                return;
            }
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(q) ? '' : 'none';
        });

        // Skrýt měsíční nadpisy bez viditelných karet
        headings.forEach(h => {
            let next = h.nextElementSibling;
            let hasVisible = false;
            while (next && !next.classList.contains('month-heading')) {
                if (next.classList.contains('trenink-card') && next.style.display !== 'none') {
                    hasVisible = true;
                    break;
                }
                next = next.nextElementSibling;
            }
            h.style.display = (q === '' || hasVisible) ? '' : 'none';
        });
    });
})();
</script>
</body>
</html>
