<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id'])) { header('Location: login.php'); exit; }
if (!canAccess('nastaveni_zadavani')) { header('Location: index.php'); exit; }

require_once 'db.php';
require_once 'csrf_helper.php';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Zpracování POST ───────────────────────────────────────────────────────
$flashError = $flashSuccess = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $flashError = 'Neplatný CSRF token.';
    } else {
        $dniZpet = (int)($_POST['dni_zpet'] ?? -1);
        if ($dniZpet < 0 || $dniZpet > 365) {
            $flashError = 'Zadejte počet dní zpět (0–365).';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO nastaveni (klic, hodnota) VALUES ('zadavani_povoleno_od', ?)
                     ON DUPLICATE KEY UPDATE hodnota = VALUES(hodnota), upraveno = CURRENT_TIMESTAMP"
                )->execute([$dniZpet]);
                $flashSuccess = "Nastavení uloženo — zadávání povoleno $dniZpet " . ($dniZpet === 1 ? 'den zpět' : ($dniZpet <= 4 ? 'dny zpět' : 'dní zpět')) . '.';
            } catch (PDOException $e) {
                error_log('nastaveni save: ' . $e->getMessage());
                $flashError = 'Nastavení se nepodařilo uložit. Zkuste to prosím znovu.';
            }
        }
    }
}

// ── Načti aktuální hodnotu (int = počet dní zpět) ─────────────────────────
$defaultDni  = 2;
$currentDni  = $defaultDni;
$upraveno    = null;
try {
    $stmt = $pdo->prepare("SELECT hodnota, upraveno FROM nastaveni WHERE klic = 'zadavani_povoleno_od'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && ctype_digit((string)$row['hodnota'])) {
        $currentDni = (int)$row['hodnota'];
        $upraveno   = $row['upraveno'];
    }
} catch (PDOException $e) {
    error_log('nastaveni read: ' . $e->getMessage());
}

// ── Pomocné výpočty ───────────────────────────────────────────────────────
$dnesDate    = date('Y-m-d');
$allowedFrom = date('Y-m-d', strtotime("-{$currentDni} days")); // dynamicky

$czDaysMap = ['Monday'=>'Pondělí','Tuesday'=>'Úterý','Wednesday'=>'Středa',
              'Thursday'=>'Čtvrtek','Friday'=>'Pátek','Saturday'=>'Sobota','Sunday'=>'Neděle'];
function fmtCzDate(string $d, array $map): string {
    try {
        $dt = new DateTime($d);
        return ($map[$dt->format('l')] ?? '') . ' ' . $dt->format('j. n. Y');
    } catch (Exception $e) { return $d; }
}

// Náhled dat, která uvidí trenéři (počítáno dynamicky od dnešního dne)
$previewDates = [];
try {
    $d    = new DateTime($allowedFrom);
    $dnes = new DateTime($dnesDate);
    while ($d <= $dnes) {
        $previewDates[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    $previewDates = array_reverse($previewDates);
} catch (Exception $e) {
    $previewDates = [$dnesDate];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nastavení zadávání tréninků</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .section-card {
            border: none; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .section-card .card-header {
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600; font-size: .92rem;
            padding: .6rem 1.1rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .section-card .card-body { padding: 1.2rem; }
        .hero-card {
            border: none; border-radius: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            color: #fff;
        }
        .mini-muted { font-size: .84rem; color: #6c757d; }
        .preset-btn { font-size: .85rem; }
        .date-preview-item {
            padding: .3rem .75rem;
            border-radius: 6px;
            font-size: .9rem;
        }
        .date-preview-item.today  { background: #d1e7dd; color: #0f5132; font-weight: 600; }
        .date-preview-item.recent { background: #f8f9fa; }
        .date-preview-item.older  { background: #fff3cd; color: #664d03; }
    </style>
</head>
<body>
<?php include 'hlavicka.php'; ?>

<div class="container py-4" style="max-width:860px;">

    <!-- Hero -->
    <div class="hero-card card mb-4 p-3 px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="h5 fw-semibold mb-0">
                    <i class="bi bi-calendar-lock me-2 opacity-75"></i>Nastavení zadávání tréninků
                </h1>
                <div class="opacity-75 small">Určete, jak daleko do minulosti mohou trenéři zadávat tréninky.</div>
            </div>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house me-1"></i>Rozcestník
            </a>
        </div>
    </div>

    <?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- ═══ LEVÝ SLOUPEC: nastavení ═══ -->
        <div class="col-lg-7">

            <!-- Aktuální stav -->
            <div class="card section-card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i>Aktuální nastavení
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 flex-wrap">
                        <div class="text-center px-3 py-2 rounded"
                             style="background:<?= $currentDni <= 3 ? '#d1e7dd' : '#fff3cd' ?>;min-width:90px;">
                            <div class="fs-2 fw-bold" style="color:<?= $currentDni <= 3 ? '#0f5132' : '#664d03' ?>">
                                <?= $currentDni ?>
                            </div>
                            <div class="small" style="color:<?= $currentDni <= 3 ? '#0f5132' : '#664d03' ?>">
                                <?= $currentDni === 1 ? 'den zpět' : ($currentDni <= 4 ? 'dny zpět' : 'dní zpět') ?>
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold mb-1">Trenéři mohou zadávat od:</div>
                            <div class="fs-5 text-primary fw-semibold">
                                <?= h(fmtCzDate($allowedFrom, $czDaysMap)) ?>
                            </div>
                            <div class="mini-muted mt-1">
                                <i class="bi bi-arrow-repeat me-1 text-success"></i>
                                Datum se posouvá automaticky každý den.
                            </div>
                            <?php if ($upraveno): ?>
                            <div class="mini-muted mt-1">
                                <i class="bi bi-clock me-1"></i>Naposledy změněno: <?= h($upraveno) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($currentDni > 3): ?>
                    <div class="alert alert-warning mt-3 mb-0 py-2 px-3" style="font-size:.88rem;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Okno je rozšířeno — nezapomeňte ho po soustředění vrátit zpět.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulář změny -->
            <div class="card section-card mb-3">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-pencil-square"></i>Změnit nastavení
                </div>
                <div class="card-body">

                    <!-- Rychlé předvolby -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small mb-2">
                            <i class="bi bi-lightning me-1 text-warning"></i>Rychlé předvolby
                        </label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary preset-btn"
                                    data-days="2">2 dny zpět
                                <span class="badge bg-secondary ms-1">výchozí</span>
                            </button>
                            <button type="button" class="btn btn-outline-primary preset-btn"
                                    data-days="7">7 dní</button>
                            <button type="button" class="btn btn-outline-primary preset-btn"
                                    data-days="14">14 dní</button>
                            <button type="button" class="btn btn-outline-warning preset-btn"
                                    data-days="30">30 dní</button>
                        </div>
                    </div>

                    <form method="post" id="nastaveniForm">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="dni_zpet" class="form-label fw-semibold">
                                <i class="bi bi-calendar-event me-1 text-primary"></i>
                                Počet dní zpět
                            </label>
                            <div class="input-group" style="max-width:220px;">
                                <input type="number" name="dni_zpet" id="dni_zpet"
                                       class="form-control"
                                       value="<?= $currentDni ?>"
                                       min="0" max="365"
                                       required>
                                <span class="input-group-text">dní zpět</span>
                            </div>
                            <div class="mini-muted mt-1">
                                Okno se posouvá automaticky každý den — zadaný počet dní zpět od <strong>aktuálního data</strong>.
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-floppy me-1"></i>Uložit nastavení
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Zrušit</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Poznámka o adminech -->
            <div class="card section-card" style="border-left:4px solid #0d6efd !important;">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-primary fs-5"></i>
                        <div class="small">
                            <strong>Hlavní trenéři</strong> (admin) mohou zadávat tréninky na
                            <strong>libovolné datum</strong> — toto nastavení se jich netýká.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ═══ PRAVÝ SLOUPEC: náhled ═══ -->
        <div class="col-lg-5">
            <div class="card section-card" style="position:sticky;top:1rem;">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-eye"></i>Náhled — co uvidí trenéři
                </div>
                <div class="card-body">
                    <div class="mini-muted mb-2">
                        Trenér uvidí tento výběr dat ve formuláři (pro dnešní den <?= h($dnesDate) ?>):
                    </div>
                    <div id="previewList" style="max-height:340px;overflow-y:auto;">
                        <?php
                        $todayTs = strtotime($dnesDate);
                        foreach ($previewDates as $i => $pd):
                            $ts = strtotime($pd);
                            $diff = (int)round(($todayTs - $ts) / 86400);
                            if ($pd === $dnesDate) {
                                $label = 'Dnes';
                                $cls   = 'today';
                            } elseif ($diff === 1) {
                                $label = 'Včera';
                                $cls   = 'recent';
                            } elseif ($diff === 2) {
                                $label = 'Předevčírem';
                                $cls   = 'recent';
                            } else {
                                $label = '';
                                $cls   = 'older';
                            }
                        ?>
                        <div class="date-preview-item <?= $cls ?> mb-1 d-flex justify-content-between align-items-center">
                            <span>
                                <?php if ($label): ?>
                                    <strong><?= h($label) ?></strong> —
                                <?php endif; ?>
                                <?= h(fmtCzDate($pd, $czDaysMap)) ?>
                            </span>
                            <?php if ($i === count($previewDates) - 1 && count($previewDates) > 3): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.72rem;">nejstarší</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mini-muted mt-2 text-center">
                        Celkem <?= count($previewDates) ?> <?= count($previewDates) === 1 ? 'datum' : (count($previewDates) <= 4 ? 'data' : 'datumů') ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>


<script>
// Předvolby tlačítka — nastaví přímo počet dní
document.querySelectorAll('.preset-btn[data-days]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('dni_zpet').value = parseInt(btn.dataset.days, 10);
    });
});
</script>
</body>
</html>
