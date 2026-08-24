<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';

// Jen hlavní trenér
$is_hlavni = canAccess('kreditni_obdobi');
if (!$is_hlavni) {
    header('Location: index.php');
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Funkce: statistiky za období (live z DB)
function getPeriodStats(PDO $pdo, int $sportovec_id, string $datum_od, ?string $datum_do, float $sazba_kc): array {
    $sql = "
        SELECT COUNT(DISTINCT t.id) AS pocet
        FROM treninky t
        JOIN trenink_sportovec ts ON ts.trenink_id = t.id
        WHERE ts.sportovec_id = :sid
          AND t.datum >= :od
    ";
    $params = [':sid' => $sportovec_id, ':od' => $datum_od];
    if ($datum_do !== null) {
        $sql .= " AND t.datum <= :do";
        $params[':do'] = $datum_do;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pocet = (int)$stmt->fetchColumn();
    return [
        'pocet'  => $pocet,
        'kredit' => round($pocet * $sazba_kc, 2),
    ];
}

$flashError   = null;
$flashSuccess = null;

// ── Zpracování POST ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $flashError = 'Neplatný CSRF token.';
    } else {
        $action       = $_POST['action']       ?? 'save';
        $sportovec_id = (int)($_POST['sportovec_id'] ?? 0);
        $obdobi_id    = (int)($_POST['obdobi_id']    ?? 0);
        $sazba_kc_raw = str_replace(',', '.', (string)($_POST['sazba_kc'] ?? ''));
        $sazba_kc     = $sazba_kc_raw !== '' ? (float)$sazba_kc_raw : null;
        $datum_od     = $_POST['datum_od'] ?? '';
        $datum_do     = $_POST['datum_do'] ?? '';

        if ($sportovec_id <= 0) {
            $flashError = 'Musíte vybrat sportovce.';
        } elseif ($action === 'save') {
            // ── Uložení / vytvoření období ──────────────────────────────
            if ($sazba_kc === null) {
                $flashError = 'Musíte zadat sazbu (Kč za trénink).';
            } elseif ($datum_od === '') {
                $flashError = 'Musíte zadat datum začátku období.';
            } else {
                try {
                    if ($obdobi_id > 0) {
                        $stmt = $pdo->prepare("
                            UPDATE sportovec_obdobi
                            SET sazba_kc = :sazba, datum_od = :od, datum_do = :do
                            WHERE id = :id AND sportovec_id = :sid
                        ");
                        $stmt->execute([
                            ':sazba' => $sazba_kc,
                            ':od'    => $datum_od,
                            ':do'    => $datum_do !== '' ? $datum_do : null,
                            ':id'    => $obdobi_id,
                            ':sid'   => $sportovec_id,
                        ]);
                        $_SESSION['flash_success'] = 'Změny období uloženy.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO sportovec_obdobi (sportovec_id, sazba_kc, datum_od, datum_do)
                            VALUES (:sid, :sazba, :od, :do)
                        ");
                        $stmt->execute([
                            ':sid'   => $sportovec_id,
                            ':sazba' => $sazba_kc,
                            ':od'    => $datum_od,
                            ':do'    => $datum_do !== '' ? $datum_do : null,
                        ]);
                        $_SESSION['flash_success'] = 'Nové kreditní období vytvořeno.';
                    }
                    header('Location: sprava_sportovec_obdobi.php?sportovec_id=' . $sportovec_id);
                    exit;
                } catch (Exception $e) {
                    $flashError = 'Chyba při ukládání: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'close') {
            // ── Uzavřít otevřené období: datum_do + snapshot ────────────
            if ($obdobi_id <= 0) {
                $flashError = 'Není určeno období k ukončení.';
            } else {
                $closeDate = ($datum_do !== '') ? $datum_do : date('Y-m-d');
                try {
                    // Načíst info o období
                    $stmtFetch = $pdo->prepare("
                        SELECT datum_od, sazba_kc
                        FROM sportovec_obdobi
                        WHERE id = ? AND sportovec_id = ?
                    ");
                    $stmtFetch->execute([$obdobi_id, $sportovec_id]);
                    $oInfo = $stmtFetch->fetch(PDO::FETCH_ASSOC);
                    if (!$oInfo) throw new Exception('Období nenalezeno.');

                    // Spočítat snapshot
                    $stats = getPeriodStats(
                        $pdo,
                        $sportovec_id,
                        $oInfo['datum_od'],
                        $closeDate,
                        (float)$oInfo['sazba_kc']
                    );

                    $stmt = $pdo->prepare("
                        UPDATE sportovec_obdobi
                        SET datum_do = :do,
                            pocet_treninku = :pocet,
                            castka_celkem  = :castka
                        WHERE id = :id AND sportovec_id = :sid
                    ");
                    $stmt->execute([
                        ':do'     => $closeDate,
                        ':pocet'  => $stats['pocet'],
                        ':castka' => $stats['kredit'],
                        ':id'     => $obdobi_id,
                        ':sid'    => $sportovec_id,
                    ]);
                    $_SESSION['flash_success'] =
                        "Období uzavřeno k {$closeDate}. " .
                        "Snapshot: {$stats['pocet']} tréninků, " .
                        number_format($stats['kredit'], 2, ',', ' ') . " Kč. " .
                        "Nyní lze označit jako vyplacené.";
                    header('Location: sprava_sportovec_obdobi.php?sportovec_id=' . $sportovec_id);
                    exit;
                } catch (Exception $e) {
                    $flashError = 'Chyba při ukončení: ' . $e->getMessage();
                }
            }

        } elseif ($action === 'pay') {
            // ── Označit uzavřené období jako vyplacené ──────────────────
            if ($obdobi_id <= 0) {
                $flashError = 'Není určeno období.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE sportovec_obdobi
                        SET vyplaceno = 1
                        WHERE id = :id AND sportovec_id = :sid AND datum_do IS NOT NULL
                    ");
                    $stmt->execute([':id' => $obdobi_id, ':sid' => $sportovec_id]);
                    $_SESSION['flash_success'] = 'Odměna označena jako vyplacená.';
                    header('Location: sprava_sportovec_obdobi.php?sportovec_id=' . $sportovec_id);
                    exit;
                } catch (Exception $e) {
                    $flashError = 'Chyba: ' . $e->getMessage();
                }
            }
        }
    }
}

// Flash ze session po redirect (PRG)
if (isset($_SESSION['flash_success'])) {
    $flashSuccess = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_error'])) {
    $flashError = $flashError ?? $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// ── GET – seznam sportovců (filtr neomezuje na uciid) ──────────────────────
$filterName          = trim($_GET['q'] ?? '');
$selectedSportovecId = isset($_GET['sportovec_id']) ? (int)$_GET['sportovec_id'] : 0;

$sqlSport    = "SELECT id, jmeno, prijmeni FROM sportovci";
$paramsSport = [];
if ($filterName !== '') {
    $sqlSport .= " WHERE (prijmeni LIKE :q OR jmeno LIKE :q2)";
    $paramsSport[':q']  = '%' . $filterName . '%';
    $paramsSport[':q2'] = '%' . $filterName . '%';
}
$sqlSport .= " ORDER BY prijmeni, jmeno";
$stmtS = $pdo->prepare($sqlSport);
$stmtS->execute($paramsSport);
$sportovci = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// Načtení vybraného sportovce (přímý dotaz – nezávisí na filtru)
$selectedSp = null;
$obdobi     = [];
$openObdobi = null;

if ($selectedSportovecId > 0) {
    $stmtSp = $pdo->prepare("SELECT id, jmeno, prijmeni FROM sportovci WHERE id = ?");
    $stmtSp->execute([$selectedSportovecId]);
    $selectedSp = $stmtSp->fetch(PDO::FETCH_ASSOC);

    $stmtO = $pdo->prepare("
        SELECT id, sportovec_id, sazba_kc, datum_od, datum_do,
               pocet_treninku, castka_celkem, vyplaceno
        FROM sportovec_obdobi
        WHERE sportovec_id = ?
        ORDER BY datum_od DESC
    ");
    $stmtO->execute([$selectedSportovecId]);
    $obdobi = $stmtO->fetchAll(PDO::FETCH_ASSOC);

    foreach ($obdobi as $o) {
        if ($o['datum_do'] === null) {
            $openObdobi = $o;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Správa období sportovců</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <style>
        body { background: #f0f2f5; }
        .section-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600; font-size: .92rem;
            padding: .65rem 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .section-card .card-body { padding: 1.2rem; }
        .hero-card {
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }
        .mini-muted { font-size: .84rem; color: #6c757d; }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4" style="max-width: 1000px;">

    <!-- Hero banner -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="fw-semibold fs-5 mb-0">
                    <i class="bi bi-wallet2 me-2 opacity-75"></i>Správa kreditních období sportovců
                </h1>
                <div class="opacity-75 small">Nastavení sazby, otevírání / uzavírání období, označení výplaty odměn.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="prehled_kreditu.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-graph-up me-1"></i>Přehled kreditů
                </a>
                <a href="hromadne_odmeny.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-star me-1"></i>Hromadné sazby
                </a>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house me-1"></i>Rozcestník
                </a>
            </div>
        </div>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ── Filtr sportovců ───────────────────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-search"></i>Vyhledání sportovce
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small mb-1">Filtrovat dle jména / příjmení</label>
                    <input type="text" name="q" value="<?= h($filterName) ?>"
                           class="form-control" placeholder="Začněte psát…">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small mb-1">Sportovec</label>
                    <select name="sportovec_id" class="form-select">
                        <option value="0">-- vyberte --</option>
                        <?php foreach ($sportovci as $sp): ?>
                            <option value="<?= (int)$sp['id'] ?>"
                                <?= $selectedSportovecId === (int)$sp['id'] ? 'selected' : '' ?>>
                                <?= h($sp['prijmeni'] . ' ' . $sp['jmeno']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Načíst</button>
                </div>
                <div class="col-md-2">
                    <a href="sprava_sportovec_obdobi.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedSportovecId > 0 && $selectedSp): ?>

    <!-- Nadpis vybraného sportovce -->
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <h2 class="h4 mb-0">
            <i class="bi bi-person-circle text-primary me-1"></i>
            <?= h($selectedSp['prijmeni'] . ' ' . $selectedSp['jmeno']) ?>
        </h2>
        <?php if ($openObdobi): ?>
            <span class="badge bg-success fs-6">Aktivní období</span>
        <?php else: ?>
            <span class="badge bg-secondary fs-6">Žádné aktivní období</span>
        <?php endif; ?>
    </div>

    <!-- ── Historie kreditních období ────────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-clock-history"></i>Historie kreditních období
        </div>
        <div class="card-body p-0">
        <?php if (empty($obdobi)): ?>
            <div class="p-3 text-muted">Tento sportovec zatím nemá žádné kreditní období.</div>
        <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Datum od</th>
                        <th>Datum do</th>
                        <th class="text-end">Sazba (Kč/trénink)</th>
                        <th class="text-end">Tréninků</th>
                        <th class="text-end">Kredit (Kč)</th>
                        <th>Stav</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($obdobi as $o):
                    $isOpen      = ($o['datum_do'] === null);
                    $isVyplaceno = ((int)$o['vyplaceno'] === 1);

                    if ($isOpen) {
                        // Otevřené: vždy live výpočet
                        $stats  = getPeriodStats($pdo, (int)$o['sportovec_id'], $o['datum_od'], null, (float)$o['sazba_kc']);
                        $pocet  = $stats['pocet'];
                        $kredit = $stats['kredit'];
                        $source = 'live';
                    } elseif ($o['pocet_treninku'] !== null && $o['castka_celkem'] !== null) {
                        // Uzavřené se snapshotem – spolehlivé
                        $pocet  = (int)$o['pocet_treninku'];
                        $kredit = (float)$o['castka_celkem'];
                        $source = 'snapshot';
                    } else {
                        // Uzavřené bez snapshotu – live fallback
                        $stats  = getPeriodStats($pdo, (int)$o['sportovec_id'], $o['datum_od'], $o['datum_do'], (float)$o['sazba_kc']);
                        $pocet  = $stats['pocet'];
                        $kredit = $stats['kredit'];
                        $source = 'live';
                    }

                    if ($isOpen) {
                        $rowClass = 'table-success';
                    } elseif ($isVyplaceno) {
                        $rowClass = '';
                    } else {
                        $rowClass = 'table-warning';
                    }
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted small"><?= (int)$o['id'] ?></td>
                        <td><?= h($o['datum_od']) ?></td>
                        <td>
                            <?php if ($isOpen): ?>
                                <em class="text-success">otevřené</em>
                            <?php else: ?>
                                <?= h($o['datum_do']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format((float)$o['sazba_kc'], 2, ',', ' ') ?></td>
                        <td class="text-end">
                            <?= $pocet ?>
                            <?php if ($source === 'live'): ?>
                                <span class="mini-muted" title="živý výpočet">(live)</span>
                            <?php else: ?>
                                <i class="bi bi-camera-fill text-secondary" style="font-size:.75rem;" title="snapshot při uzavření"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold">
                            <?= number_format($kredit, 2, ',', ' ') ?> Kč
                        </td>
                        <td>
                            <?php if ($isOpen): ?>
                                <span class="badge bg-success">Aktivní</span>
                            <?php elseif ($isVyplaceno): ?>
                                <span class="badge bg-secondary">Vyplaceno</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Čeká na výplatu</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($isOpen): ?>
                                <!-- Ukončit otevřené období (zaznamená snapshot) -->
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="sportovec_id" value="<?= (int)$o['sportovec_id'] ?>">
                                    <input type="hidden" name="obdobi_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-warning"
                                            data-confirm="Uzavřít aktivní období k dnešnímu dni a zaznamenat snapshot?">
                                        <i class="bi bi-calendar-x me-1"></i>Ukončit
                                    </button>
                                </form>
                            <?php elseif (!$isVyplaceno): ?>
                                <!-- Označit uzavřené období jako vyplacené -->
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="pay">
                                    <input type="hidden" name="sportovec_id" value="<?= (int)$o['sportovec_id'] ?>">
                                    <input type="hidden" name="obdobi_id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success"
                                            data-confirm="Označit <?= number_format($kredit, 2, ",", " ") ?> Kč jako vyplacenou odměnu?">
                                        <i class="bi bi-cash-coin me-1"></i>Vyplaceno
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-check2-all me-1"></i>Hotovo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── Formulář nové / editace otevřeného období ─────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header <?= $openObdobi ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
            <i class="bi bi-<?= $openObdobi ? 'pencil-square' : 'plus-circle' ?>"></i>
            <?= $openObdobi ? 'Úprava aktivního (otevřeného) období' : 'Vytvořit nové kreditní období' ?>
        </div>
        <div class="card-body">
            <?php if ($openObdobi): ?>
            <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:.88rem;">
                <i class="bi bi-info-circle me-1"></i>
                Upravuješ <strong>aktivní</strong> období. Pro uzavření a snapshot použij tlačítko
                <strong>Ukončit</strong> v tabulce výše.
            </div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="sportovec_id" value="<?= $selectedSportovecId ?>">
                <input type="hidden" name="obdobi_id" value="<?= $openObdobi ? (int)$openObdobi['id'] : 0 ?>">

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Datum od</label>
                    <input type="date" name="datum_od" class="form-control"
                           value="<?= $openObdobi ? h($openObdobi['datum_od']) : date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Datum do
                        <span class="text-muted fw-normal small">(volitelné)</span>
                    </label>
                    <input type="date" name="datum_do" class="form-control"
                           value="<?= ($openObdobi && $openObdobi['datum_do']) ? h($openObdobi['datum_do']) : '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Sazba (Kč / trénink)</label>
                    <input type="number" step="0.1" min="0" name="sazba_kc" class="form-control"
                           value="<?= $openObdobi ? h($openObdobi['sazba_kc']) : '' ?>"
                           placeholder="např. 50" required>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-floppy me-1"></i>
                        <?= $openObdobi ? 'Uložit změny' : 'Vytvořit období' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php elseif ($selectedSportovecId > 0): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>Sportovec ID <?= $selectedSportovecId ?> nebyl nalezen.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-arrow-up-circle me-2"></i>Vyhledej a vyber sportovce pro správu jeho kreditních období.
        </div>
    <?php endif; ?>

</div>


</body>
</html>
