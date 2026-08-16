<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('prehled_popisu')) { header('Location: index.php'); exit; }

require_once 'db.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── Auto-registrace oprávnění ──────────────────────────────────────────────
try {
    $pdo->exec("INSERT IGNORE INTO opravneni (klic, nazev, popis, min_role, skupina, poradi)
        VALUES ('prehled_popisu', 'Přehled popisů tréninků', 'Zobrazení popisů tréninků dle skupiny/podskupiny za zvolené období', 'trener', 'Přehledy', 32)");
} catch (PDOException $e) { /* záznam již existuje */ }

// ── Role ────────────────────────────────────────────────────────────────────
$isSupervisor = roleAtLeast('hlavni');           // správce + admin vidí vše
$trenerId     = (int)$_SESSION['trener_id'];

// ── Filtry ────────────────────────────────────────────────────────────────
$skupinaId    = isset($_GET['skupina_id'])    && ctype_digit($_GET['skupina_id'])    ? (int)$_GET['skupina_id']    : 0;
$podskupinaId = isset($_GET['podskupina_id']) && ctype_digit($_GET['podskupina_id']) ? (int)$_GET['podskupina_id'] : 0;
$dniParam = (int)($_GET['dni'] ?? 14);
$dni = in_array($dniParam, [7, 14, 30, 180], true) ? $dniParam : 14;

// ── Skupiny — filtrované dle role ──────────────────────────────────────────
$skupiny    = [];
$podskupiny = [];
try {
    if ($isSupervisor) {
        // Správce/admin: všechny skupiny
        $skupiny = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi, nazev')->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Trenér: pouze skupiny, kde vedl alespoň 1 trénink
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.id, s.nazev
            FROM skupiny s
            JOIN trenink_skupina tg ON s.id = tg.skupina_id
            JOIN trenink_trener  tt ON tg.trenink_id = tt.trenink_id
            WHERE tt.trener_id = ?
            ORDER BY s.poradi, s.nazev
        ");
        $stmt->execute([$trenerId]);
        $skupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('prehled_popisu skupiny: ' . $e->getMessage());
}

// ── Bezpečnostní kontrola skupinaId pro trenéra ───────────────────────────
// Trenér nesmí zobrazit skupinu, ve které nikdy nevedl trénink
if ($skupinaId && !$isSupervisor) {
    try {
        $chk = $pdo->prepare("
            SELECT COUNT(*) FROM trenink_skupina tg
            JOIN trenink_trener tt ON tg.trenink_id = tt.trenink_id
            WHERE tg.skupina_id = ? AND tt.trener_id = ?
        ");
        $chk->execute([$skupinaId, $trenerId]);
        if ((int)$chk->fetchColumn() === 0) {
            $skupinaId    = 0;
            $podskupinaId = 0;
        }
    } catch (PDOException $e) {
        $skupinaId = 0;
    }
}

// ── Podskupiny (pro vybranou skupinu) ─────────────────────────────────────
if ($skupinaId) {
    try {
        $psStmt = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev');
        $psStmt->execute([$skupinaId]);
        $podskupiny = $psStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('prehled_popisu podskupiny: ' . $e->getMessage());
    }
}

// ── Kategorie meta ─────────────────────────────────────────────────────────
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

// ── Dotaz na tréninky ──────────────────────────────────────────────────────
$treninky        = [];
$nazevSkupiny    = '';
$nazevPodskupiny = '';

if ($skupinaId) {
    // Název skupiny
    try {
        $r = $pdo->prepare('SELECT nazev FROM skupiny WHERE id = ?');
        $r->execute([$skupinaId]);
        $nazevSkupiny = $r->fetchColumn() ?: '';
    } catch (PDOException $e) {}

    // Název podskupiny (ověřit příslušnost ke skupině)
    if ($podskupinaId) {
        try {
            $r = $pdo->prepare('SELECT nazev FROM podskupiny WHERE id = ? AND skupina_id = ?');
            $r->execute([$podskupinaId, $skupinaId]);
            $nazevPodskupiny = $r->fetchColumn() ?: '';
            if (!$nazevPodskupiny) $podskupinaId = 0; // neplatná kombinace
        } catch (PDOException $e) {}
    }

    // Sestavení dotazu tréninků
    try {
        if ($podskupinaId) {
            $sql = "
                SELECT DISTINCT t.id, t.datum, t.napln, t.kategorie, t.delka,
                    GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
                FROM treninky t
                JOIN trenink_podskupina tp ON t.id = tp.trenink_id AND tp.podskupina_id = ?
                LEFT JOIN trenink_trener tt ON t.id = tt.trenink_id
                LEFT JOIN treneri tr ON tt.trener_id = tr.id
                WHERE t.datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY t.id, t.datum, t.napln, t.kategorie, t.delka
                ORDER BY t.datum DESC
            ";
            $params = [$podskupinaId, $dni];
        } else {
            $sql = "
                SELECT DISTINCT t.id, t.datum, t.napln, t.kategorie, t.delka,
                    GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
                FROM treninky t
                JOIN trenink_skupina tg ON t.id = tg.trenink_id AND tg.skupina_id = ?
                LEFT JOIN trenink_trener tt ON t.id = tt.trenink_id
                LEFT JOIN treneri tr ON tt.trener_id = tr.id
                WHERE t.datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY t.id, t.datum, t.napln, t.kategorie, t.delka
                ORDER BY t.datum DESC
            ";
            $params = [$skupinaId, $dni];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('prehled_popisu query: ' . $e->getMessage());
    }
}

// ── Formátování data ───────────────────────────────────────────────────────
$czDays   = ['Mon'=>'Po','Tue'=>'Út','Wed'=>'St','Thu'=>'Čt','Fri'=>'Pá','Sat'=>'So','Sun'=>'Ne'];
$czMonths = [1=>'ledna',2=>'února',3=>'března',4=>'dubna',5=>'května',6=>'června',
             7=>'července',8=>'srpna',9=>'září',10=>'října',11=>'listopadu',12=>'prosince'];
function fmtDatum(string $d, array $days, array $months): string {
    try {
        $dt  = new DateTime($d);
        $den = $days[$dt->format('D')] ?? $dt->format('D');
        $m   = (int)$dt->format('n');
        return $den . ' ' . (int)$dt->format('j') . '. ' . $months[$m] . ' ' . $dt->format('Y');
    } catch (Exception $e) { return $d; }
}

// ── Pomocné ────────────────────────────────────────────────────────────────
$kontextNazev = $nazevPodskupiny ? "$nazevSkupiny › $nazevPodskupiny" : $nazevSkupiny;
$dniLabels    = [7=>'7 dní', 14=>'14 dní', 30=>'1 měsíc', 180=>'6 měsíců'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přehled popisů tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
        .trenink-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
            margin-bottom: 1rem;
            transition: box-shadow .15s;
        }
        .trenink-card:hover { box-shadow: 0 3px 14px rgba(0,0,0,.13); }
        .trenink-datum {
            font-size: .82rem;
            font-weight: 600;
            color: #6c757d;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .trenink-napln {
            white-space: pre-wrap;
            font-size: .97rem;
            line-height: 1.65;
        }
        .side-bar {
            width: 5px;
            border-radius: 4px 0 0 4px;
            flex-shrink: 0;
        }
        .empty-state {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            color: #6c757d;
        }
        .filter-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .hero-bar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
            border-radius: 12px;
            padding: 1rem 1.3rem;
            margin-bottom: 1.2rem;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4 mb-5" style="max-width:900px;">

    <!-- Hero -->
    <div class="hero-bar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="fw-semibold fs-5 mb-0">
                <i class="bi bi-journal-text me-2 opacity-75"></i>Přehled popisů tréninků
            </h1>
            <div class="opacity-75 small">
                <?php if ($isSupervisor): ?>
                    Popisy tréninků dle skupiny nebo podskupiny za zvolené období — všechny skupiny.
                <?php else: ?>
                    Popisy tréninků skupin, ve kterých jste vedl/a alespoň jeden trénink.
                <?php endif; ?>
            </div>
        </div>
        <a href="index.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-house me-1"></i>Rozcestník
        </a>
    </div>

    <?php if (empty($skupiny)): ?>
    <!-- Trenér nemá žádné tréninky v žádné skupině -->
    <div class="empty-state">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
        <div class="fw-semibold">Žádné skupiny k zobrazení</div>
        <div class="small mt-1">Nemáte zatím žádné tréninky přiřazené ke skupině.</div>
    </div>
    <?php else: ?>

    <!-- ── Filtr ───────────────────────────────────────────────────────── -->
    <div class="card filter-card mb-4">
        <div class="card-body py-3">
            <form method="get" id="filterForm" class="row g-2 align-items-end">
                <!-- Skupina -->
                <div class="col-sm-4">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-diagram-2 me-1 text-primary"></i>Skupina
                        <?php if (!$isSupervisor): ?>
                        <span class="text-muted fw-normal">(vaše skupiny)</span>
                        <?php endif; ?>
                    </label>
                    <select name="skupina_id" id="skupinaSelect" class="form-select">
                        <option value="">— vyberte skupinu —</option>
                        <?php foreach ($skupiny as $sk): ?>
                            <option value="<?= (int)$sk['id'] ?>" <?= $skupinaId === (int)$sk['id'] ? 'selected' : '' ?>>
                                <?= h($sk['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Podskupina (dynamická) -->
                <div class="col-sm-4">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-diagram-3 me-1 text-secondary"></i>Podskupina
                        <span class="text-muted fw-normal">(nepovinná)</span>
                    </label>
                    <select name="podskupina_id" id="podskupinaSelect" class="form-select"
                            <?= !$skupinaId ? 'disabled' : '' ?>>
                        <option value="">— všechny —</option>
                        <?php foreach ($podskupiny as $ps): ?>
                            <option value="<?= (int)$ps['id'] ?>" <?= $podskupinaId === (int)$ps['id'] ? 'selected' : '' ?>>
                                <?= h($ps['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Interval -->
                <div class="col-sm-2">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-calendar-range me-1 text-success"></i>Období
                    </label>
                    <select name="dni" class="form-select">
                        <?php foreach ($dniLabels as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= $dni === $val ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tlačítko -->
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100"
                            <?= !$skupinaId ? 'disabled' : '' ?> id="submitBtn">
                        <i class="bi bi-search me-1"></i>Zobrazit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($skupinaId): ?>

        <!-- Stats + kontext -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-people me-1 text-primary"></i><?= h($kontextNazev) ?>
                </h5>
                <div class="text-muted small mt-1">
                    <i class="bi bi-calendar me-1"></i>Posledních <?= h($dniLabels[$dni]) ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-journal-check me-1"></i><?= count($treninky) ?> <?= count($treninky) === 1 ? 'trénink' : (count($treninky) <= 4 ? 'tréninky' : 'tréninků') ?>
                </div>
            </div>
            <?php if (!empty($treninky)): ?>
            <button class="btn btn-outline-secondary btn-sm no-print" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Tisk
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($treninky)): ?>
        <div class="empty-state">
            <i class="bi bi-journal-x fs-1 text-muted d-block mb-2"></i>
            <div class="fw-semibold">Žádné tréninky za toto období</div>
            <div class="small mt-1">Pro skupinu <strong><?= h($kontextNazev) ?></strong> nebyly nalezeny žádné tréninky za posledních <?= $dni ?> dní.</div>
        </div>

        <?php else: ?>

        <!-- ── Výpis tréninků ─────────────────────────────────────────── -->
        <?php foreach ($treninky as $t):
            $kat     = $t['kategorie'] ?? '';
            $meta    = $kategorieMeta[$kat] ?? ['label'=>$kat,'color'=>'secondary','icon'=>'bi-circle'];
            $color   = $meta['color'];
            $bsCls   = in_array($color, ['orange','teal']) ? 'badge-'.$color : 'bg-'.$color;
            $sideClr = match($color) { 'orange'=>'warning', 'teal'=>'success', default=>$color };
            $napln   = trim($t['napln'] ?? '');
            $delka   = (int)($t['delka'] ?? 0);
            $trenere = $t['trenere'] ?? '';
        ?>
        <div class="card trenink-card">
            <div class="d-flex">
                <div class="side-bar bg-<?= $sideClr ?>"></div>
                <div class="card-body py-3 px-3 flex-grow-1">
                    <!-- Hlavička -->
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-1 mb-2">
                        <div>
                            <div class="trenink-datum">
                                <i class="bi bi-calendar3 me-1"></i><?= h(fmtDatum($t['datum'], $czDays, $czMonths)) ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <span class="badge <?= $bsCls ?>">
                                    <i class="bi <?= h($meta['icon']) ?> me-1"></i><?= h($meta['label']) ?>
                                </span>
                                <?php if ($delka): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-clock me-1"></i><?= $delka ?> min
                                </span>
                                <?php endif; ?>
                                <?php if ($trenere): ?>
                                <span class="text-muted small">
                                    <i class="bi bi-person me-1"></i><?= h($trenere) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isSupervisor): ?>
                        <a href="edit_trenink.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline-secondary btn-sm no-print"
                           title="Otevřít trénink">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Popis -->
                    <?php if ($napln): ?>
                    <div class="trenink-napln"><?= h($napln) ?></div>
                    <?php else: ?>
                    <div class="text-muted fst-italic small">— bez popisu —</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; // empty treninky ?>

    <?php else: ?>
    <!-- Úvodní stav — nic nevybráno -->
    <div class="empty-state">
        <i class="bi bi-diagram-2 fs-1 text-muted d-block mb-2"></i>
        <div class="fw-semibold">Vyberte skupinu</div>
        <div class="small mt-1">Ze seznamu vyberte skupinu (případně podskupinu) a klikněte na <strong>Zobrazit</strong>.</div>
    </div>
    <?php endif; ?>

    <?php endif; // empty skupiny ?>

</div><!-- /container -->


<script>
const skupinaSelect    = document.getElementById('skupinaSelect');
const podskupinaSelect = document.getElementById('podskupinaSelect');
const submitBtn        = document.getElementById('submitBtn');

if (skupinaSelect) {
    skupinaSelect.addEventListener('change', function () {
        const sid = this.value;
        submitBtn.disabled = !sid;

        if (!sid) {
            podskupinaSelect.innerHTML = '<option value="">— všechny —</option>';
            podskupinaSelect.disabled = true;
            return;
        }

        podskupinaSelect.innerHTML = '<option value="">— načítám… —</option>';
        podskupinaSelect.disabled = true;

        fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(sid))
            .then(r => r.json())
            .then(data => {
                podskupinaSelect.innerHTML = '<option value="">— všechny —</option>';
                (data || []).forEach(ps => {
                    const opt = document.createElement('option');
                    opt.value = ps.id;
                    opt.textContent = ps.nazev;
                    podskupinaSelect.appendChild(opt);
                });
                podskupinaSelect.disabled = false;
                // Automatický submit po výběru skupiny
                document.getElementById('filterForm').submit();
            })
            .catch(() => {
                podskupinaSelect.innerHTML = '<option value="">— chyba načítání —</option>';
                podskupinaSelect.disabled = false;
            });
    });

    podskupinaSelect.addEventListener('change', () => document.getElementById('filterForm').submit());
    document.querySelector('select[name="dni"]').addEventListener('change', () => {
        if (skupinaSelect.value) document.getElementById('filterForm').submit();
    });
}
</script>

<style media="print">
    .hero-bar, .filter-card, .no-print, nav, footer { display: none !important; }
    .trenink-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; page-break-inside: avoid; }
    body { background: #fff !important; }
    .container { max-width: 100% !important; }
</style>

</body>
</html>
