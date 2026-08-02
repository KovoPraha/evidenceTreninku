<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// ---- Načtení filtrů z GET ----
$q            = trim($_GET['q'] ?? '');
$skupinaId    = $_GET['skupina_id'] ?? '';
$podskupinaId = $_GET['podskupina_id'] ?? '';

// ---- Načtení seznamu skupin ----
$stmtSk = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev");
$skupiny = $stmtSk->fetchAll(PDO::FETCH_ASSOC);

// ---- Načtení seznamu podskupin (jen pro vybranou skupinu, pokud je) ----
$podskupiny = [];
if ($skupinaId !== '' && ctype_digit($skupinaId)) {
    $stmtPs = $pdo->prepare(
        "SELECT id, nazev 
         FROM podskupiny 
         WHERE skupina_id = :sid 
         ORDER BY poradi, nazev"
    );
    $stmtPs->execute([':sid' => $skupinaId]);
    $podskupiny = $stmtPs->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Sestavení SQL pro výpis sportovců ----
// Vazba: sportovci -> sportovec_podskupina -> podskupiny -> skupiny
$sql = "
    SELECT DISTINCT s.id, s.jmeno, s.hash, s.prijmeni
    FROM sportovci s
    LEFT JOIN sportovec_podskupina sp ON sp.sportovec_id = s.id
    LEFT JOIN podskupiny p           ON p.id = sp.podskupina_id
    LEFT JOIN skupiny g              ON g.id = p.skupina_id
    WHERE 1=1
";
$params = [];

// filtr podle jména
if ($q !== '') {
    $sql .= " AND s.prijmeni LIKE :q";
    $params[':q'] = '%' . $q . '%';
}

// filtr podle skupiny (přes g.id)
if ($skupinaId !== '' && ctype_digit($skupinaId)) {
    $sql .= " AND g.id = :gid";
    $params[':gid'] = $skupinaId;
}

// filtr podle podskupiny (přes p.id)
if ($podskupinaId !== '' && ctype_digit($podskupinaId)) {
    $sql .= " AND p.id = :pid";
    $params[':pid'] = $podskupinaId;
}

$sql .= " ORDER BY s.prijmeni ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);

// AJAX mód: vrátí jen tělo tabulky
if (!empty($_GET['ajax'])) {
    header('Content-Type: text/html; charset=utf-8');
    if (empty($sportovci)) {
        echo '<div class="alert alert-info mt-2">Pro zadané filtrovací podmínky nebyli nalezeni žádní sportovci.</div>';
    } else {
        echo '<table class="table table-striped"><thead class="table-dark"><tr><th>Příjmení</th><th>Jméno</th><th>Profil</th></tr></thead><tbody>';
        foreach ($sportovci as $s) {
            $hash = urlencode($s['hash'] ?? '');
            $id   = (int)$s['id'];
            echo '<tr>';
            echo '<td>' . htmlspecialchars($s['prijmeni']) . '</td>';
            echo '<td>' . htmlspecialchars($s['jmeno']) . '</td>';
            echo '<td>';
            echo '<a href="sportovec_treninky.php?hash=' . $hash . '" class="btn btn-sm btn-outline-primary me-1">Veřejný profil</a>';
            echo '<a href="sportovec_detail.php?id=' . $id . '" class="btn btn-sm btn-outline-secondary">Profil</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="text-muted small mt-1">' . count($sportovci) . ' sportovců</div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přehled sportovců</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1>Přehled sportovců</h1>

    <!-- Filtrační panel -->
    <div class="card shadow-sm p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="q" class="form-label small mb-1">Jméno / příjmení</label>
                <input type="text" id="q" class="form-control form-control-sm"
                       placeholder="Začni psát…" value="<?= htmlspecialchars($q) ?>" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label for="skupina_id" class="form-label small mb-1">Skupina</label>
                <select id="skupina_id" class="form-select form-select-sm">
                    <option value="">— všechny skupiny —</option>
                    <?php foreach ($skupiny as $sk): ?>
                        <option value="<?= $sk['id'] ?>" <?= ($skupinaId == $sk['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sk['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="podskupina_id" class="form-label small mb-1">Podskupina</label>
                <select id="podskupina_id" class="form-select form-select-sm">
                    <option value="">— všechny podskupiny —</option>
                    <?php foreach ($podskupiny as $ps): ?>
                        <option value="<?= $ps['id'] ?>" <?= ($podskupinaId == $ps['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ps['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" id="btnClearSportovci" class="btn btn-sm btn-outline-secondary">Zrušit filtr</button>
            </div>
            <div class="col-auto ms-auto text-muted small" id="spCountLabel"></div>
        </div>
    </div>

    <!-- Výsledky (dynamicky plněné) -->
    <div id="spListWrap">
        <?php if (empty($sportovci)): ?>
            <div class="alert alert-info">Pro zadané filtrovací podmínky nebyli nalezeni žádní sportovci.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr><th>Příjmení</th><th>Jméno</th><th>Profil</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sportovci as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['prijmeni']) ?></td>
                        <td><?= htmlspecialchars($s['jmeno']) ?></td>
                        <td>
                            <a href="sportovec_treninky.php?hash=<?= urlencode($s['hash']) ?>" class="btn btn-sm btn-outline-primary me-1">Veřejný profil</a>
                            <a href="sportovec_detail.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-secondary">Profil</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-muted small mt-1"><?= count($sportovci) ?> sportovců</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const qInput      = document.getElementById('q');
    const skupinaEl   = document.getElementById('skupina_id');
    const podskupinaEl= document.getElementById('podskupina_id');
    const listWrap    = document.getElementById('spListWrap');
    const countLabel  = document.getElementById('spCountLabel');
    const btnClear    = document.getElementById('btnClearSportovci');

    let debounceTimer = null;
    let controller    = null;

    function loadList() {
        if (controller) controller.abort();
        controller = new AbortController();

        const params = new URLSearchParams({
            ajax:           '1',
            q:              qInput.value,
            skupina_id:     skupinaEl.value,
            podskupina_id:  podskupinaEl.value,
        });

        listWrap.style.opacity = '0.4';

        fetch('prehled_sportovcu.php?' + params.toString(), { signal: controller.signal })
            .then(r => r.text())
            .then(html => {
                listWrap.innerHTML = html;
                listWrap.style.opacity = '1';
                // Aktualizuj URL bez reloadu
                history.replaceState(null, '', '?' + params.toString().replace('&ajax=1','').replace('ajax=1&','').replace('ajax=1',''));
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    listWrap.innerHTML = '<div class="alert alert-danger">Chyba při načítání.</div>';
                    listWrap.style.opacity = '1';
                }
            });
    }

    function loadDebounced(ms = 300) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadList, ms);
    }

    // Dynamické načítání podskupin při změně skupiny
    skupinaEl.addEventListener('change', () => {
        const sid = skupinaEl.value;
        podskupinaEl.innerHTML = '<option value="">— všechny podskupiny —</option>';

        if (sid) {
            fetch('ajax_podskupiny.php?skupina_id=' + encodeURIComponent(sid))
                .then(r => r.json())
                .then(data => {
                    if (data.ok && data.items) {
                        data.items.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.nazev;
                            podskupinaEl.appendChild(opt);
                        });
                    }
                })
                .catch(() => {});
        }

        loadDebounced(100);
    });

    podskupinaEl.addEventListener('change', () => loadDebounced(100));
    qInput.addEventListener('input', () => loadDebounced(350));

    btnClear.addEventListener('click', () => {
        qInput.value        = '';
        skupinaEl.value     = '';
        podskupinaEl.innerHTML = '<option value="">— všechny podskupiny —</option>';
        loadList();
    });
})();
</script>
</body>
</html>
