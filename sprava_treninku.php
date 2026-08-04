<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sprava_treninku')) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'csrf_helper.php';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Vstupní filtry (validace) ──────────────────────────────────────────────
$filterGroup = $_GET['skupina_id'] ?? '';
$filterSub   = $_GET['podskupina_id'] ?? '';
$filterTr    = $_GET['trenere'] ?? [];
if (!is_array($filterTr)) $filterTr = [$filterTr];

if ($filterGroup !== '' && !ctype_digit((string)$filterGroup)) $filterGroup = '';
if ($filterSub   !== '' && !ctype_digit((string)$filterSub))   $filterSub   = '';
$filterTr = array_map('intval', array_filter($filterTr, fn($v) => ctype_digit((string)$v)));

// Flash zprávy
$flashError   = $_SESSION['flash_error'] ?? null;   unset($_SESSION['flash_error']);
$flashSuccess = $_SESSION['flash_success'] ?? null;  unset($_SESSION['flash_success']);

// ── Načtení dat pro filtry ─────────────────────────────────────────────────
$groups = $subs = $trainers = [];
try {
    $groups   = $pdo->query('SELECT id, nazev FROM skupiny ORDER BY poradi, nazev')->fetchAll(PDO::FETCH_ASSOC);
    $trainers = $pdo->query('SELECT id, jmeno FROM treneri ORDER BY jmeno')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('sprava_treninku.php filter data: ' . $e->getMessage());
}

if ($filterGroup !== '') {
    try {
        $s = $pdo->prepare('SELECT id, nazev FROM podskupiny WHERE skupina_id = ? ORDER BY poradi, nazev');
        $s->execute([$filterGroup]);
        $subs = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('sprava_treninku.php subs: ' . $e->getMessage());
    }
}

// ── Hlavní dotaz ───────────────────────────────────────────────────────────
$treninky = [];
try {
    $sql = "SELECT t.id, t.datum, t.napln, t.poznamka, t.obrazky,
                   GROUP_CONCAT(DISTINCT tr.jmeno ORDER BY tr.jmeno SEPARATOR ', ') AS trenere
            FROM treninky t
            JOIN trenink_trener tt ON t.id = tt.trenink_id
            JOIN treneri tr ON tt.trener_id = tr.id
            LEFT JOIN trenink_skupina tg ON t.id = tg.trenink_id
            LEFT JOIN trenink_podskupina tp ON t.id = tp.trenink_id
            WHERE 1=1";
    $params = [];

    if ($filterGroup !== '') { $sql .= ' AND tg.skupina_id = ?';     $params[] = (int)$filterGroup; }
    if ($filterSub   !== '') { $sql .= ' AND tp.podskupina_id = ?';  $params[] = (int)$filterSub; }
    if (!empty($filterTr)) {
        $ph = implode(',', array_fill(0, count($filterTr), '?'));
        $sql .= " AND tt.trener_id IN ($ph)";
        $params = array_merge($params, $filterTr);
    }

    $sql .= ' GROUP BY t.id ORDER BY t.datum DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $treninky = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('sprava_treninku.php main query: ' . $e->getMessage());
    $flashError = 'Nepodařilo se načíst tréninky. Zkuste to znovu.';
}

// ── Batch načtení účastníků (místo N+1) ───────────────────────────────────
$ucastniciByTrenink = [];
if (!empty($treninky)) {
    try {
        $ids = array_column($treninky, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $su  = $pdo->prepare(
            "SELECT ts.trenink_id, CONCAT(sp.prijmeni, ' ', sp.jmeno) AS jmeno_cele
             FROM trenink_sportovec ts
             JOIN sportovci sp ON ts.sportovec_id = sp.id
             WHERE ts.trenink_id IN ($in)
             ORDER BY sp.prijmeni, sp.jmeno"
        );
        $su->execute($ids);
        foreach ($su->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ucastniciByTrenink[(int)$row['trenink_id']][] = $row['jmeno_cele'];
        }
    } catch (PDOException $e) {
        error_log('sprava_treninku.php ucastnici batch: ' . $e->getMessage());
    }
}

$czDays = ['Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
           'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Správa tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4 mb-5">
    <h1 class="h3 mb-3">Správa tréninků</h1>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Chyba:</strong> <?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= h($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtr -->
    <form method="GET" class="card shadow-sm p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Skupina:</label>
                <select id="skupina" name="skupina_id" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= (string)$filterGroup === (string)$g['id'] ? 'selected' : '' ?>>
                        <?= h($g['nazev']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Podskupina:</label>
                <select id="podskupina" name="podskupina_id" class="form-select">
                    <option value="">-- všechny --</option>
                    <?php foreach ($subs as $ps): ?>
                    <option value="<?= (int)$ps['id'] ?>" <?= (string)$filterSub === (string)$ps['id'] ? 'selected' : '' ?>>
                        <?= h($ps['nazev']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Trenéři:</label>
                <select name="trenere[]" multiple class="form-select">
                    <?php foreach ($trainers as $tr): ?>
                    <option value="<?= (int)$tr['id'] ?>" <?= in_array((int)$tr['id'], $filterTr) ? 'selected' : '' ?>>
                        <?= h($tr['jmeno']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 align-self-end">
                <button class="btn btn-primary w-100" type="submit">Filtrovat</button>
            </div>
        </div>
    </form>

    <!-- Tabulka -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th></th>
                    <th>Datum</th>
                    <th>Den</th>
                    <th>Fotky</th>
                    <th>Trenéři</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($treninky as $t):
                    $tid = (int)$t['id'];
                    try { $dayEng = (new DateTime($t['datum']))->format('l'); }
                    catch (Exception $e) { $dayEng = ''; }
                    $dayCz      = $czDays[$dayEng] ?? h($dayEng);
                    $ucastnici  = $ucastniciByTrenink[$tid] ?? [];
                    $thumbs     = array_filter(array_map('trim', explode(',', (string)($t['obrazky'] ?? ''))));
                ?>
                <tr>
                    <td class="p-0" style="width:1px;">
                        <button class="btn btn-link p-2" data-bs-toggle="collapse"
                                data-bs-target="#detail<?= $tid ?>">+</button>
                    </td>
                    <td><?= h($t['datum']) ?></td>
                    <td><?= h($dayCz) ?></td>
                    <td>
                        <?php foreach (array_slice($thumbs, 0, 3) as $img): ?>
                            <img src="nahrane_obrazky/<?= h($img) ?>"
                                 class="img-thumbnail me-1" style="height:50px;" alt="">
                        <?php endforeach; ?>
                    </td>
                    <td><?= h((string)($t['trenere'] ?? '')) ?></td>
                    <td>
                        <form method="POST" action="generuj_story.php" class="d-inline">
                            <input type="hidden" name="id" value="<?= $tid ?>"><?= csrf_field() ?>
                            <button type="submit" class="btn btn-info btn-sm">Story</button>
                        </form>
                        <a href="edit_trenink.php?id=<?= $tid ?>" class="btn btn-secondary btn-sm">Upravit</a>
                        <form method="POST" action="smazat_trenink.php" class="d-inline"
                              data-confirm="Opravdu smazat trénink?">
                            <input type="hidden" name="trenink_id" value="<?= $tid ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
                        </form>
                    </td>
                </tr>
                <tr class="collapse" id="detail<?= $tid ?>">
                    <td colspan="6" class="bg-light">
                        <strong>Náplň:</strong> <?= nl2br(h((string)($t['napln'] ?? ''))) ?><br>
                        <strong>Poznámka:</strong> <?= nl2br(h((string)($t['poznamka'] ?? ''))) ?><br>
                        <strong>Účastníci:</strong>
                        <?= h(implode(', ', $ucastnici)) ?: '<span class="text-muted">—</span>' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($treninky)): ?>
                <tr><td colspan="6" class="text-center text-muted">Žádné tréninky neodpovídají filtru.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// AJAX načtení podskupin při změně skupiny
document.getElementById('skupina')?.addEventListener('change', function () {
    fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(this.value))
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('podskupina');
            const items = Array.isArray(data) ? data : (data.items || []);
            let html = '<option value="">-- všechny --</option>';
            items.forEach(ps => {
                html += `<option value="${parseInt(ps.id, 10)}">${ps.nazev.replace(/</g,'&lt;')}</option>`;
            });
            sel.innerHTML = html;
        })
        .catch(err => console.error('Podskupiny error:', err));
});
</script>
</body>
</html>
