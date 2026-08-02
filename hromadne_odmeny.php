<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$messages = [];
$errors   = [];

// -------------------------------
// 1) Skupiny + výběr
// -------------------------------
$skupiny = $pdo->query("
    SELECT id, nazev
    FROM skupiny
    ORDER BY poradi, nazev
")->fetchAll(PDO::FETCH_ASSOC);

$filterSkupinaId    = $_GET['skupina_id'] ?? ($_POST['skupina_id'] ?? '');
$filterPodskupinaId = $_GET['podskupina_id'] ?? ($_POST['podskupina_id'] ?? '');

if ($filterSkupinaId !== '' && !ctype_digit((string)$filterSkupinaId)) $filterSkupinaId = '';
if ($filterPodskupinaId !== '' && !ctype_digit((string)$filterPodskupinaId)) $filterPodskupinaId = '';

// Podskupiny pro vybranou skupinu
$podskupiny = [];
if ($filterSkupinaId !== '') {
    $st = $pdo->prepare("
        SELECT id, nazev
        FROM podskupiny
        WHERE skupina_id = ?
        ORDER BY poradi, nazev
    ");
    $st->execute([(int)$filterSkupinaId]);
    $podskupiny = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($filterPodskupinaId !== '') {
        $allowed = array_map(fn($r) => (int)$r['id'], $podskupiny);
        if (!in_array((int)$filterPodskupinaId, $allowed, true)) {
            $filterPodskupinaId = '';
        }
    }
}

// -------------------------------
// 2) Uložení sazeb -> sportovec_obdobi
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ulozit_odmeny'])) {
    // CSRF
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } elseif ($filterSkupinaId === '') {
        $errors[] = 'Nejdřív vyber skupinu.';
    } else {
        $odmeny = $_POST['odmena'] ?? [];
        if (!is_array($odmeny)) $odmeny = [];

        // volitelné „nastavit všem"
        $applyAll      = trim((string)($_POST['apply_all'] ?? ''));
        $applyAllValue = null;
        if ($applyAll !== '') {
            $norm = str_replace(',', '.', $applyAll);
            if (!is_numeric($norm) || (float)$norm < 0) {
                $errors[] = 'Hodnota „nastavit všem" musí být nezáporné číslo.';
            } else {
                $applyAllValue = (float)$norm;
            }
        }

        if (empty($errors)) {
            try {
                // Zjisti sportovce v aktuálním filtru
                $params = [':gid' => (int)$filterSkupinaId];
                $sqlAllowed = "
                    SELECT DISTINCT s.id
                    FROM sportovci s
                    JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
                    WHERE sg.skupina_id = :gid
                ";

                if ($filterPodskupinaId !== '') {
                    $sqlAllowed .= "
                        AND EXISTS (
                            SELECT 1
                            FROM sportovec_podskupina sp
                            WHERE sp.sportovec_id = s.id
                              AND sp.podskupina_id = :pid
                        )
                    ";
                    $params[':pid'] = (int)$filterPodskupinaId;
                }

                $stAllowed = $pdo->prepare($sqlAllowed);
                $stAllowed->execute($params);
                $allowedIds = $stAllowed->fetchAll(PDO::FETCH_COLUMN);
                $allowedIds = array_map('intval', $allowedIds);
                $allowedSet = array_fill_keys($allowedIds, true);

                $pdo->beginTransaction();

                $stFindOpen = $pdo->prepare("
                    SELECT id
                    FROM sportovec_obdobi
                    WHERE sportovec_id = ?
                      AND (datum_do IS NULL OR datum_do = '0000-00-00')
                    ORDER BY datum_od DESC
                    LIMIT 1
                ");

                $stUpdOpen = $pdo->prepare("
                    UPDATE sportovec_obdobi
                    SET sazba_kc = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $stInsOpen = $pdo->prepare("
                    INSERT INTO sportovec_obdobi (sportovec_id, datum_od, datum_do, sazba_kc)
                    VALUES (?, ?, NULL, ?)
                ");

                $today    = (new DateTime('today'))->format('Y-m-d');
                $updated  = 0;
                $inserted = 0;

                $applyToIds = $allowedIds;

                if ($applyAllValue === null) {
                    $applyToIds = [];
                    foreach ($odmeny as $sidRaw => $valRaw) {
                        $sid = (int)$sidRaw;
                        if (!isset($allowedSet[$sid])) continue;

                        $val = trim((string)$valRaw);
                        if ($val === '') continue;

                        $norm = str_replace(',', '.', $val);
                        if (!is_numeric($norm) || (float)$norm < 0) {
                            throw new Exception("Neplatná sazba u sportovce ID $sid (povoleno nezáporné číslo).");
                        }

                        $applyToIds[] = $sid;
                    }
                }

                foreach ($applyToIds as $sid) {
                    if (!isset($allowedSet[$sid])) continue;

                    $newRate = $applyAllValue;
                    if ($newRate === null) {
                        $val = trim((string)($odmeny[$sid] ?? ''));
                        if ($val === '') continue;
                        $newRate = (float)str_replace(',', '.', $val);
                    }

                    $stFindOpen->execute([$sid]);
                    $openId = (int)$stFindOpen->fetchColumn();

                    if ($openId > 0) {
                        $stUpdOpen->execute([$newRate, $openId]);
                        $updated++;
                    } else {
                        $stInsOpen->execute([$sid, $today, $newRate]);
                        $inserted++;
                    }
                }

                $pdo->commit();
                $messages[] = "Uloženo. Aktualizováno otevřených období: $updated, nově založeno: $inserted.";

                // PRG redirect
                $qs = [
                    'skupina_id'    => $filterSkupinaId,
                    'podskupina_id' => $filterPodskupinaId,
                ];
                header('Location: ' . basename(__FILE__) . '?' . http_build_query($qs));
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Chyba při ukládání: ' . $e->getMessage();
            }
        }
    }
}

// -------------------------------
// 3) Načtení sportovců
// -------------------------------
$sportovci = [];
if ($filterSkupinaId !== '') {
    $params = [':gid' => (int)$filterSkupinaId];

    $sql = "
        SELECT DISTINCT
            s.id, s.jmeno, s.prijmeni, s.narozeni, s.category, s.uciid,
            so.sazba_kc AS sazba_aktualni,
            so.datum_od AS obdobi_od
        FROM sportovci s
        JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
        LEFT JOIN sportovec_obdobi so
            ON so.sportovec_id = s.id
           AND (so.datum_do IS NULL OR so.datum_do = '0000-00-00')
        WHERE sg.skupina_id = :gid
    ";

    if ($filterPodskupinaId !== '') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM sportovec_podskupina sp
                WHERE sp.sportovec_id = s.id
                  AND sp.podskupina_id = :pid
            )
        ";
        $params[':pid'] = (int)$filterPodskupinaId;
    }

    $sql .= " ORDER BY s.prijmeni, s.jmeno";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $sportovci = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hromadné nastavení sazeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600; font-size: .92rem;
            padding: .65rem 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .hero-card {
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }
        .table-wrap { max-height: 68vh; overflow: auto; }
        .sticky-head thead th { position: sticky; top: 0; z-index: 5; }
        .narrow { white-space: nowrap; }
        .w-odm { width: 160px; }
        .small-muted { font-size: .85rem; color: #666; }
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
                    <i class="bi bi-star me-2 opacity-75"></i>Hromadné nastavení sazeb za trénink
                </div>
                <div class="opacity-75 small">
                    Nastaví <code>sazba_kc</code> v otevřeném období pro celou skupinu najednou.
                    Pokud sportovec nemá otevřené období, automaticky se vytvoří od dnešního dne.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="sprava_sportovec_obdobi.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-wallet2 me-1"></i>Správa jednotlivce
                </a>
                <a href="prehled_kreditu.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-graph-up me-1"></i>Přehled kreditů
                </a>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i>Rozcestník
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($messages)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php foreach ($messages as $m): ?>
                <div><?= h($m) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Chyba:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtry skupin -->
    <div class="card section-card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-funnel"></i>Výběr skupiny / podskupiny
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold small mb-1" for="skupina_id">Skupina</label>
                    <select class="form-select" id="skupina_id" name="skupina_id" onchange="this.form.submit()">
                        <option value="">-- vyber skupinu --</option>
                        <?php foreach ($skupiny as $sk): ?>
                            <option value="<?= (int)$sk['id'] ?>"
                                <?= ((string)$filterSkupinaId === (string)$sk['id']) ? 'selected' : '' ?>>
                                <?= h($sk['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mini-muted mt-1">Po změně skupiny se stránka automaticky načte.</div>
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-semibold small mb-1" for="podskupina_id">Podskupina (volitelné)</label>
                    <select class="form-select" id="podskupina_id" name="podskupina_id">
                        <option value="">-- všechny podskupiny --</option>
                        <?php foreach ($podskupiny as $ps): ?>
                            <option value="<?= (int)$ps['id'] ?>"
                                <?= ((string)$filterPodskupinaId === (string)$ps['id']) ? 'selected' : '' ?>>
                                <?= h($ps['nazev']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">
                        <i class="bi bi-search me-1"></i>Načíst
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= h(basename(__FILE__)) ?>">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filterSkupinaId === ''): ?>
        <div class="alert alert-info">
            <i class="bi bi-arrow-up-circle me-2"></i>Vyber skupinu — zobrazí se sportovci a jejich aktuální sazby z otevřeného období.
        </div>
    <?php else: ?>

        <div class="card section-card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-1"></i>Sportovci</span>
                <span class="badge bg-light text-dark fw-normal">
                    Nalezeno: <?= count($sportovci) ?>
                </span>
            </div>
            <div class="card-body">

                <?php if (empty($sportovci)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>Pro zadané filtry nebyli nalezeni žádní sportovci.
                    </div>
                <?php else: ?>

                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="skupina_id" value="<?= h($filterSkupinaId) ?>">
                        <input type="hidden" name="podskupina_id" value="<?= h($filterPodskupinaId) ?>">

                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small mb-1">
                                    <i class="bi bi-lightning-fill text-warning me-1"></i>Nastavit všem v aktuálním výpisu
                                </label>
                                <div class="input-group">
                                    <input type="text" name="apply_all" class="form-control"
                                           placeholder="např. 50 nebo 50,00">
                                    <span class="input-group-text text-muted small">Kč/trénink</span>
                                </div>
                                <div class="mini-muted mt-1">Ponech prázdné pro ruční zadání níže.</div>
                            </div>

                            <div class="col-md-8 d-flex gap-2 justify-content-md-end align-items-end">
                                <button class="btn btn-success" type="submit" name="ulozit_odmeny" value="1">
                                    <i class="bi bi-floppy me-1"></i>Uložit změny
                                </button>
                            </div>
                        </div>

                        <div class="table-wrap border rounded">
                            <table class="table table-sm table-striped mb-0 sticky-head">
                                <thead class="table-dark">
                                <tr>
                                    <th class="narrow">ID</th>
                                    <th>Příjmení</th>
                                    <th>Jméno</th>
                                    <th class="narrow">Nar.</th>
                                    <th class="narrow">Kategorie</th>
                                    <th class="narrow">UCI ID</th>
                                    <th class="narrow">Aktivní období od</th>
                                    <th class="narrow w-odm">Sazba (Kč / trénink)</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($sportovci as $sp): ?>
                                    <tr>
                                        <td class="narrow text-muted small"><?= (int)$sp['id'] ?></td>
                                        <td><?= h($sp['prijmeni'] ?? '') ?></td>
                                        <td><?= h($sp['jmeno'] ?? '') ?></td>
                                        <td class="narrow"><?= h($sp['narozeni'] ?? '') ?></td>
                                        <td class="narrow"><?= h($sp['category'] ?? '') ?></td>
                                        <td class="narrow"><?= h($sp['uciid'] ?? '') ?></td>
                                        <td class="narrow">
                                            <?php if ($sp['obdobi_od']): ?>
                                                <?= h($sp['obdobi_od']) ?>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <i class="bi bi-dash"></i>žádné
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="narrow">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm"
                                                name="odmena[<?= (int)$sp['id'] ?>]"
                                                value="<?= h((string)($sp['sazba_aktualni'] ?? '')) ?>"
                                                placeholder="0"
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-success" type="submit" name="ulozit_odmeny" value="1">
                                <i class="bi bi-floppy me-1"></i>Uložit změny
                            </button>
                        </div>

                    </form>

                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
