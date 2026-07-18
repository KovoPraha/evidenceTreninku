<?php
session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';

// Jen hlavní trenér
$is_hlavni = canAccess('prehled_kreditu');
if (!$is_hlavni) {
    header('Location: index.php');
    exit;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Live počet tréninků za období
function getLivePocet(PDO $pdo, int $sid, string $datum_od, ?string $datum_do): int {
    $sql = "
        SELECT COUNT(DISTINCT t.id)
        FROM treninky t
        JOIN trenink_sportovec ts ON ts.trenink_id = t.id
        WHERE ts.sportovec_id = :sid
          AND t.datum >= :od
    ";
    $p = [':sid' => $sid, ':od' => $datum_od];
    if ($datum_do !== null) {
        $sql .= " AND t.datum <= :do";
        $p[':do'] = $datum_do;
    }
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return (int)$st->fetchColumn();
}

// ── Zpracování rychlé akce (označit jako vyplaceno z přehledu) ────────────
$flashError   = null;
$flashSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $flashError = 'Neplatný CSRF token.';
    } else {
        $action       = $_POST['action']       ?? '';
        $sportovec_id = (int)($_POST['sportovec_id'] ?? 0);
        $obdobi_id    = (int)($_POST['obdobi_id']    ?? 0);

        if ($action === 'pay' && $sportovec_id > 0 && $obdobi_id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE sportovec_obdobi
                    SET vyplaceno = 1
                    WHERE id = :id AND sportovec_id = :sid AND datum_do IS NOT NULL
                ");
                $stmt->execute([':id' => $obdobi_id, ':sid' => $sportovec_id]);
                $_SESSION['flash_success'] = 'Odměna označena jako vyplacená.';
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Chyba: ' . $e->getMessage();
            }
        } elseif ($action === 'close' && $sportovec_id > 0 && $obdobi_id > 0) {
            try {
                $closeDate = date('Y-m-d');
                $stmtFetch = $pdo->prepare("SELECT datum_od, sazba_kc FROM sportovec_obdobi WHERE id = ? AND sportovec_id = ?");
                $stmtFetch->execute([$obdobi_id, $sportovec_id]);
                $oInfo = $stmtFetch->fetch(PDO::FETCH_ASSOC);
                if (!$oInfo) throw new Exception('Období nenalezeno.');
                $pocet  = getLivePocet($pdo, $sportovec_id, $oInfo['datum_od'], $closeDate);
                $castka = round($pocet * (float)$oInfo['sazba_kc'], 2);
                $stmt = $pdo->prepare("
                    UPDATE sportovec_obdobi
                    SET datum_do = :do, pocet_treninku = :pocet, castka_celkem = :castka
                    WHERE id = :id AND sportovec_id = :sid
                ");
                $stmt->execute([
                    ':do'     => $closeDate,
                    ':pocet'  => $pocet,
                    ':castka' => $castka,
                    ':id'     => $obdobi_id,
                    ':sid'    => $sportovec_id,
                ]);
                $_SESSION['flash_success'] = "Období uzavřeno k {$closeDate}. Snapshot: {$pocet} tréninků, " . number_format($castka, 2, ',', ' ') . " Kč.";
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Chyba při uzavírání: ' . $e->getMessage();
            }
        }
        header('Location: prehled_kreditu.php');
        exit;
    }
}

if (isset($_SESSION['flash_success'])) { $flashSuccess = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_error']))   { $flashError   = $_SESSION['flash_error'];   unset($_SESSION['flash_error']); }

// ── Načtení aktivních (otevřených) období ─────────────────────────────────
$stmtAktivni = $pdo->query("
    SELECT
        s.id AS sportovec_id, s.jmeno, s.prijmeni,
        so.id AS obdobi_id, so.datum_od, so.sazba_kc
    FROM sportovci s
    JOIN sportovec_obdobi so ON so.sportovec_id = s.id
    WHERE so.datum_do IS NULL
    ORDER BY s.prijmeni, s.jmeno
");
$aktivniRows = $stmtAktivni->fetchAll(PDO::FETCH_ASSOC);

// Pro každého aktivního: živý počet tréninků
$aktivni = [];
$totalAktivniKredit = 0.0;
foreach ($aktivniRows as $row) {
    $pocet  = getLivePocet($pdo, (int)$row['sportovec_id'], $row['datum_od'], null);
    $kredit = round($pocet * (float)$row['sazba_kc'], 2);
    $totalAktivniKredit += $kredit;
    $aktivni[] = array_merge($row, ['pocet' => $pocet, 'kredit' => $kredit]);
}

// ── Načtení uzavřených nevyplacených období ───────────────────────────────
$stmtNevypl = $pdo->query("
    SELECT
        s.id AS sportovec_id, s.jmeno, s.prijmeni,
        so.id AS obdobi_id, so.datum_od, so.datum_do, so.sazba_kc,
        so.pocet_treninku, so.castka_celkem
    FROM sportovci s
    JOIN sportovec_obdobi so ON so.sportovec_id = s.id
    WHERE so.datum_do IS NOT NULL
      AND so.vyplaceno = 0
    ORDER BY so.datum_do ASC, s.prijmeni, s.jmeno
");
$nevyplRows = $stmtNevypl->fetchAll(PDO::FETCH_ASSOC);

$nevyplacene = [];
$totalNevyplKredit = 0.0;
foreach ($nevyplRows as $row) {
    if ($row['pocet_treninku'] !== null && $row['castka_celkem'] !== null) {
        $pocet  = (int)$row['pocet_treninku'];
        $kredit = (float)$row['castka_celkem'];
    } else {
        // Fallback: live výpočet (pro starší záznamy bez snapshotu)
        $pocet  = getLivePocet($pdo, (int)$row['sportovec_id'], $row['datum_od'], $row['datum_do']);
        $kredit = round($pocet * (float)$row['sazba_kc'], 2);
    }
    $totalNevyplKredit += $kredit;
    $nevyplacene[] = array_merge($row, ['pocet' => $pocet, 'kredit' => $kredit]);
}

// ── Statistiky vyplacených (za posledních 12 měsíců) ─────────────────────
$stmtVypl = $pdo->prepare("
    SELECT
        COUNT(*) AS pocet_obdobi,
        COALESCE(SUM(castka_celkem), 0) AS suma_castka,
        COALESCE(SUM(pocet_treninku), 0) AS suma_treninku
    FROM sportovec_obdobi
    WHERE vyplaceno = 1
      AND datum_do >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
");
$stmtVypl->execute();
$vyplStats = $stmtVypl->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přehled kreditů sportovců</title>
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
        .stat-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            text-align: center; padding: 1.1rem;
        }
        .stat-card .stat-val { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
        .stat-card .stat-label { font-size: .82rem; color: #6c757d; margin-top: .3rem; }
        .mini-muted { font-size: .84rem; color: #6c757d; }
        .table-wrap { max-height: 420px; overflow-y: auto; }
        .sticky-head thead th { position: sticky; top: 0; z-index: 5; }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4" style="max-width: 1100px;">

    <!-- Hero -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold fs-5">
                    <i class="bi bi-graph-up me-2 opacity-75"></i>Přehled kreditů sportovců
                </div>
                <div class="opacity-75 small">Aktivní odměny, čekající výplaty a statistiky vyplacených odměn.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="sprava_sportovec_obdobi.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-wallet2 me-1"></i>Správa jednotlivce
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

    <!-- ── Souhrnné karty ─────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success text-white">
                <div class="stat-val"><?= count($aktivni) ?></div>
                <div class="stat-label opacity-75">Aktivních sportovců</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-success text-white">
                <div class="stat-val"><?= number_format($totalAktivniKredit, 0, ',', ' ') ?> Kč</div>
                <div class="stat-label opacity-75">Aktuálně naběhlý kredit</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-warning">
                <div class="stat-val text-dark"><?= number_format($totalNevyplKredit, 0, ',', ' ') ?> Kč</div>
                <div class="stat-label text-dark">Čeká na výplatu</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-secondary text-white">
                <div class="stat-val"><?= number_format((float)$vyplStats['suma_castka'], 0, ',', ' ') ?> Kč</div>
                <div class="stat-label opacity-75">Vyplaceno (posl. 12 měs.)</div>
            </div>
        </div>
    </div>

    <!-- ── Aktivní (otevřená) období ─────────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-activity me-1"></i>Aktivní kreditní období <span class="badge bg-light text-success ms-1"><?= count($aktivni) ?></span></span>
            <span class="small opacity-75">živý výpočet z tréninků</span>
        </div>
        <div class="card-body p-0">
        <?php if (empty($aktivni)): ?>
            <div class="p-3 text-muted">
                <i class="bi bi-info-circle me-2"></i>Žádný sportovec nemá aktivní kreditní období.
                <a href="hromadne_odmeny.php" class="ms-2">Nastavit hromadně →</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
            <table class="table table-sm table-hover align-middle mb-0 sticky-head">
                <thead class="table-dark">
                    <tr>
                        <th>Příjmení a jméno</th>
                        <th class="text-end">Sazba (Kč/trénink)</th>
                        <th>Aktivní od</th>
                        <th class="text-end">Tréninků</th>
                        <th class="text-end">Kredit (Kč)</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($aktivni as $r): ?>
                    <tr>
                        <td>
                            <a href="sprava_sportovec_obdobi.php?sportovec_id=<?= (int)$r['sportovec_id'] ?>"
                               class="text-decoration-none fw-semibold">
                                <?= h($r['prijmeni'] . ' ' . $r['jmeno']) ?>
                            </a>
                        </td>
                        <td class="text-end"><?= number_format((float)$r['sazba_kc'], 2, ',', ' ') ?></td>
                        <td><?= h($r['datum_od']) ?></td>
                        <td class="text-end"><?= $r['pocet'] ?></td>
                        <td class="text-end fw-semibold text-success">
                            <?= number_format($r['kredit'], 2, ',', ' ') ?> Kč
                        </td>
                        <td class="text-nowrap">
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="close">
                                <input type="hidden" name="sportovec_id" value="<?= (int)$r['sportovec_id'] ?>">
                                <input type="hidden" name="obdobi_id" value="<?= (int)$r['obdobi_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        data-confirm="Uzavřít aktivní období <?= h($r['prijmeni'] . ' ' . $r['jmeno']) ?> k dnešnímu dni?">
                                    <i class="bi bi-calendar-x me-1"></i>Ukončit
                                </button>
                            </form>
                            <a href="sprava_sportovec_obdobi.php?sportovec_id=<?= (int)$r['sportovec_id'] ?>"
                               class="btn btn-sm btn-outline-secondary ms-1" title="Detail">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-success">
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Celkem naběhlý kredit:</td>
                        <td class="text-end fw-bold"><?= number_format($totalAktivniKredit, 2, ',', ' ') ?> Kč</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── Uzavřená nevyplacená období ───────────────────────────── -->
    <div class="card section-card mb-4">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-hourglass-split me-1"></i>Čeká na výplatu
                <span class="badge bg-dark text-white ms-1"><?= count($nevyplacene) ?></span>
            </span>
            <span class="small">snapshot při uzavření</span>
        </div>
        <div class="card-body p-0">
        <?php if (empty($nevyplacene)): ?>
            <div class="p-3 text-success">
                <i class="bi bi-check-circle me-2"></i>Všechna uzavřená období jsou vyplacena.
            </div>
        <?php else: ?>
            <div class="table-wrap">
            <table class="table table-sm table-hover align-middle mb-0 sticky-head">
                <thead class="table-dark">
                    <tr>
                        <th>Příjmení a jméno</th>
                        <th>Období od – do</th>
                        <th class="text-end">Sazba (Kč/trénink)</th>
                        <th class="text-end">Tréninků</th>
                        <th class="text-end">Kredit (Kč)</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($nevyplacene as $r): ?>
                    <tr>
                        <td>
                            <a href="sprava_sportovec_obdobi.php?sportovec_id=<?= (int)$r['sportovec_id'] ?>"
                               class="text-decoration-none fw-semibold">
                                <?= h($r['prijmeni'] . ' ' . $r['jmeno']) ?>
                            </a>
                        </td>
                        <td class="text-nowrap">
                            <?= h($r['datum_od']) ?> –
                            <span class="fw-semibold"><?= h($r['datum_do']) ?></span>
                        </td>
                        <td class="text-end"><?= number_format((float)$r['sazba_kc'], 2, ',', ' ') ?></td>
                        <td class="text-end"><?= $r['pocet'] ?></td>
                        <td class="text-end fw-semibold text-warning">
                            <?= number_format($r['kredit'], 2, ',', ' ') ?> Kč
                        </td>
                        <td class="text-nowrap">
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="sportovec_id" value="<?= (int)$r['sportovec_id'] ?>">
                                <input type="hidden" name="obdobi_id" value="<?= (int)$r['obdobi_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                        data-confirm="Označit <?= number_format($r['kredit'], 2, ",", " ") ?> Kč jako vyplacenou odměnu pro <?= h($r['prijmeni'] . ' ' . $r['jmeno']) ?>?">
                                    <i class="bi bi-cash-coin me-1"></i>Vyplaceno
                                </button>
                            </form>
                            <a href="sprava_sportovec_obdobi.php?sportovec_id=<?= (int)$r['sportovec_id'] ?>"
                               class="btn btn-sm btn-outline-secondary ms-1" title="Detail">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-warning">
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Celkem k výplatě:</td>
                        <td class="text-end fw-bold"><?= number_format($totalNevyplKredit, 2, ',', ' ') ?> Kč</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── Statistiky vyplacených ────────────────────────────────── -->
    <div class="card section-card">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-check2-all"></i>Vyplaceno (posledních 12 měsíců)
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-secondary"><?= (int)$vyplStats['pocet_obdobi'] ?></div>
                    <div class="mini-muted">vyplacených období</div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-secondary"><?= (int)$vyplStats['suma_treninku'] ?></div>
                    <div class="mini-muted">tréninků zaplaceno</div>
                </div>
                <div class="col-sm-4 text-center">
                    <div class="fs-3 fw-bold text-secondary"><?= number_format((float)$vyplStats['suma_castka'], 0, ',', ' ') ?> Kč</div>
                    <div class="mini-muted">celkem vyplaceno</div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
