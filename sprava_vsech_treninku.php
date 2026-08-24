<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sprava_treninku')) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Flash zprávy jsou zpracovány centrálně v hlavicka.php (toast notifikace)

// Filtry – validace vstupů
$filterGroup = $_GET['skupina_id'] ?? '';
$filterSub   = $_GET['podskupina_id'] ?? '';
$filterTr    = $_GET['trenere'] ?? [];
$filterKat   = $_GET['kategorie'] ?? '';
$filterQ     = trim($_GET['q'] ?? '');

if ($filterGroup !== '' && !ctype_digit($filterGroup)) $filterGroup = '';
if ($filterSub   !== '' && !ctype_digit($filterSub))   $filterSub   = '';
if (!is_array($filterTr)) $filterTr = [$filterTr];
$filterTr = array_values(array_filter(array_map('intval', $filterTr), fn($v) => $v > 0));

$validKategorie = ['silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani'];
if ($filterKat !== '' && !in_array($filterKat, $validKategorie)) $filterKat = '';

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

// Načtení dat pro filtry
try {
    $groups   = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi, nazev')->fetchAll(PDO::FETCH_ASSOC);
    $trainers = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('sprava_vsech_treninku filter query: ' . $e->getMessage());
    $groups = $trainers = [];
}

$subs = [];
if ($filterGroup !== '') {
    try {
        $s = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev');
        $s->execute([(int)$filterGroup]);
        $subs = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $subs = []; }
}

// Hlavní dotaz na tréninky
$sql    = "
    SELECT t.id, t.datum, t.napln, t.poznamka, t.obrazky, t.kategorie, t.delka,
           GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
    FROM treninky t
    JOIN trenink_trener tt ON t.id = tt.trenink_id
    JOIN treneri tr ON tt.trener_id = tr.id
    LEFT JOIN trenink_skupina  tg ON t.id = tg.trenink_id
    LEFT JOIN trenink_podskupina tp ON t.id = tp.trenink_id
    WHERE 1=1
";
$params = [];

if ($filterGroup !== '') { $sql .= ' AND tg.skupina_id = ?';    $params[] = (int)$filterGroup; }
if ($filterSub   !== '') { $sql .= ' AND tp.podskupina_id = ?'; $params[] = (int)$filterSub; }
if ($filterKat   !== '') { $sql .= ' AND t.kategorie = ?';      $params[] = $filterKat; }
if ($filterQ     !== '') {
    $sql .= ' AND (t.napln LIKE ? OR t.poznamka LIKE ?)';
    $params[] = "%{$filterQ}%";
    $params[] = "%{$filterQ}%";
}
if (!empty($filterTr)) {
    $ph     = implode(',', array_fill(0, count($filterTr), '?'));
    $sql   .= " AND tt.trener_id IN ($ph)";
    $params = array_merge($params, $filterTr);
}
$sql .= ' GROUP BY t.id ORDER BY t.datum DESC';

$treninky = [];
try {
    $stmt     = $pdo->prepare($sql);
    $stmt->execute($params);
    $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('sprava_vsech_treninku main query: ' . $e->getMessage());
    $flashError = 'Chyba při načítání tréninků.';
}

// Batch načtení účastníků – 1 dotaz místo N (oprava N+1)
$ucastniciByTrenink = [];
if (!empty($treninky)) {
    $treninkIds = array_column($treninky, 'id');
    $in         = implode(',', array_fill(0, count($treninkIds), '?'));
    try {
        $suStmt = $pdo->prepare("
            SELECT ts.trenink_id, CONCAT(sp.prijmeni, ' ', sp.jmeno) AS jmeno_cele
            FROM trenink_sportovec ts
            JOIN sportovci sp ON ts.sportovec_id = sp.id
            WHERE ts.trenink_id IN ($in)
            ORDER BY sp.prijmeni, sp.jmeno
        ");
        $suStmt->execute($treninkIds);
        while ($row = $suStmt->fetch(PDO::FETCH_ASSOC)) {
            $ucastniciByTrenink[(int)$row['trenink_id']][] = $row['jmeno_cele'];
        }
    } catch (PDOException $e) {
        error_log('sprava_vsech_treninku ucasnici: ' . $e->getMessage());
    }
}

// Statistiky
$totalHodin = array_sum(array_column($treninky, 'delka'));
$kategorieCount = [];
foreach ($treninky as $t) {
    $k = $t['kategorie'] ?? '';
    if ($k !== '') {
        $kategorieCount[$k] = ($kategorieCount[$k] ?? 0) + 1;
    }
}

$czDays = [
    'Monday' => 'Pondělí', 'Tuesday' => 'Úterý',   'Wednesday' => 'Středa',
    'Thursday' => 'Čtvrtek','Friday'  => 'Pátek',   'Saturday'  => 'Sobota',
    'Sunday' => 'Neděle',
];

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <style>
        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
        #searchBox:focus { box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1 class="mb-3"><i class="bi bi-list-ul me-2 text-primary"></i>Správa tréninků</h1>

    <!-- Flash zprávy se zobrazují jako toast notifikace (hlavicka.php) -->

    <!-- Filtry -->
    <form method="GET" class="card p-3 mb-4" id="filterForm">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-people me-1"></i>Skupina</label>
                <select id="skupinaFilter" name="skupina_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- všechny --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= ($filterGroup == $g['id']) ? 'selected' : '' ?>>
                            <?= h($g['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-diagram-3 me-1"></i>Podskupina</label>
                <select id="podskupinaFilter" name="podskupina_id" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($subs as $ps): ?>
                        <option value="<?= (int)$ps['id'] ?>" <?= ($filterSub == $ps['id']) ? 'selected' : '' ?>>
                            <?= h($ps['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><i class="bi bi-tag me-1"></i>Kategorie</label>
                <select name="kategorie" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($kategorieMeta as $key => $meta): ?>
                        <option value="<?= h($key) ?>" <?= $filterKat === $key ? 'selected' : '' ?>>
                            <?= $meta['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="bi bi-person-badge me-1"></i>Trenéři</label>
                <select name="trenere[]" multiple class="form-select" size="3">
                    <?php foreach ($trainers as $tr): ?>
                        <option value="<?= (int)$tr['id'] ?>" <?= in_array($tr['id'], $filterTr) ? 'selected' : '' ?>>
                            <?= h($tr['jmeno']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Filtrovat</button>
                <a class="btn btn-outline-secondary" href="sprava_vsech_treninku.php" title="Zrušit filtr"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
        <!-- Fulltext hledání -->
        <div class="row g-3 mt-1">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" id="searchBox" class="form-control"
                           placeholder="Hledat v náplni a poznámce..."
                           value="<?= h($filterQ) ?>">
                    <?php if ($filterQ !== ''): ?>
                        <a href="?<?= http_build_query(array_diff_key($_GET, ['q'=>1])) ?>" class="btn btn-outline-secondary" title="Zrušit hledání">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>

    <!-- Stats řádek -->
    <div class="row g-3 mb-3">
        <div class="col-sm-4 col-lg-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary"><?= count($treninky) ?></div>
                    <div class="text-muted small"><i class="bi bi-calendar-check me-1"></i>Tréninků</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success"><?= number_format($totalHodin, 1, ',', ' ') ?></div>
                    <div class="text-muted small"><i class="bi bi-clock me-1"></i>Hodin</div>
                </div>
            </div>
        </div>
        <?php foreach ($kategorieCount as $kk => $cnt):
            $km = $kategorieMeta[$kk] ?? null;
            if (!$km) continue;
        ?>
        <div class="col-sm-4 col-lg-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-<?= $km['color'] ?>"><?= $cnt ?></div>
                    <div class="text-muted small"><i class="bi <?= $km['icon'] ?> me-1"></i><?= $km['label'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($treninky)): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Žádné tréninky neodpovídají filtru.</div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle table-sm" id="trainingsTable">
            <thead class="table-dark">
                <tr>
                    <th style="width:32px;"></th>
                    <th style="width:110px;"><i class="bi bi-calendar3 me-1"></i>Datum</th>
                    <th style="width:90px;">Den</th>
                    <th style="width:90px;"><i class="bi bi-tag me-1"></i>Kategorie</th>
                    <th style="width:65px;"><i class="bi bi-clock me-1"></i>Délka</th>
                    <th style="width:130px;"><i class="bi bi-image me-1"></i>Fotky</th>
                    <th><i class="bi bi-person-badge me-1"></i>Trenéři</th>
                    <th style="width:155px;">Akce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($treninky as $t):
                try { $dayEng = (new DateTime($t['datum']))->format('l'); }
                catch (Exception $e) { $dayEng = ''; }
                $dayCz     = $czDays[$dayEng] ?? '';
                $ucastnici = $ucastniciByTrenink[(int)$t['id']] ?? [];
                $colId     = 'detail' . (int)$t['id'];
                $imgs      = array_filter(array_map('trim', explode(',', $t['obrazky'] ?? '')));
                $kat       = $t['kategorie'] ?? '';
                $km        = $kategorieMeta[$kat] ?? null;
            ?>
                <tr data-search="<?= h(strtolower(($t['napln'] ?? '') . ' ' . ($t['poznamka'] ?? '') . ' ' . ($t['trenere'] ?? ''))) ?>">
                    <td class="p-0 text-center">
                        <button class="btn btn-link btn-sm p-1 text-muted"
                                data-bs-toggle="collapse" data-bs-target="#<?= h($colId) ?>">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </td>
                    <td class="fw-semibold"><?= h($t['datum']) ?></td>
                    <td class="text-muted"><?= h($dayCz) ?></td>
                    <td>
                        <?php if ($km): ?>
                            <span class="badge bg-<?= $km['color'] ?>"><i class="bi <?= $km['icon'] ?> me-1"></i><?= $km['label'] ?></span>
                        <?php elseif ($kat): ?>
                            <span class="badge bg-secondary"><?= h($kat) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= $t['delka'] ? number_format($t['delka'], 1, ',', '') . ' h' : '—' ?></td>
                    <td>
                        <?php foreach (array_slice($imgs, 0, 3) as $img): ?>
                            <img src="nahrane_obrazky/<?= h($img) ?>"
                                 class="img-thumbnail me-1"
                                 style="height:48px; width:48px; object-fit:cover;"
                                 alt="foto"
                                 onerror="this.style.display='none'">
                        <?php endforeach; ?>
                        <?php if (count($imgs) > 3): ?>
                            <span class="text-muted small">+<?= count($imgs) - 3 ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($t['trenere'] ?? '') ?></td>
                    <td>
                        <a href="edit_trenink.php?id=<?= (int)$t['id'] ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil-square"></i> Upravit
                        </a>
                        <!-- Smazání přes POST + CSRF -->
                        <form method="POST" action="smazat_trenink.php" class="d-inline"
                              data-confirm="Opravdu smazat trénink ze dne <?= h($t['datum']) ?>?&#10;Tato akce je nevratná.">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="trenink_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <tr class="collapse" id="<?= h($colId) ?>">
                    <td colspan="8" class="bg-light">
                        <div class="p-2 small">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <strong><i class="bi bi-card-text me-1"></i>Náplň:</strong>
                                    <div class="text-muted"><?= nl2br(h($t['napln'] ?? '')) ?: '—' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <strong><i class="bi bi-chat-left-text me-1"></i>Poznámka (interní):</strong>
                                    <div class="text-muted"><?= nl2br(h($t['poznamka'] ?? '')) ?: '—' ?></div>
                                    <strong class="d-block mt-2"><i class="bi bi-people me-1"></i>Účastníci (<?= count($ucastnici) ?>):</strong>
                                    <div class="text-muted">
                                        <?= !empty($ucastnici) ? h(implode(', ', $ucastnici)) : '—' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<script>
// Dynamické načítání podskupin
document.getElementById('skupinaFilter')?.addEventListener('change', function () {
    const gid = this.value;
    fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(gid))
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('podskupinaFilter');
            if (!sel) return;
            let html = '<option value="">-- všechny --</option>';
            (data || []).forEach(ps => {
                html += `<option value="${parseInt(ps.id)}">${ps.nazev.replace(/</g,'&lt;')}</option>`;
            });
            sel.innerHTML = html;
        })
        .catch(() => {});
});

// Client-side instant search (filtruje tabulku v reálném čase)
(() => {
    const box = document.getElementById('searchBox');
    const table = document.getElementById('trainingsTable');
    if (!box || !table) return;

    let debounceTimer;
    box.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const q = box.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody > tr');
            let visibleCount = 0;
            for (let i = 0; i < rows.length; i += 2) {
                const dataRow = rows[i];
                const detailRow = rows[i + 1];
                const searchText = dataRow.getAttribute('data-search') || '';
                const show = q === '' || searchText.includes(q);
                dataRow.style.display = show ? '' : 'none';
                if (detailRow) detailRow.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            }
        }, 200);
    });
})();
</script>
</body>
</html>
