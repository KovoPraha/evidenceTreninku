<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

// Autoload a import tříd pro Excel export
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['trener_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash_error'] = 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.';
    } elseif (($_POST['action'] ?? '') === 'delete_activity' && ($_POST['confirm_action'] ?? '') === '1') {
        $delete = $pdo->prepare('DELETE FROM dalsi_cinnosti WHERE id = ? AND trener_id = ?');
        $delete->execute([(int)($_POST['activity_id'] ?? 0), (int)$_SESSION['trener_id']]);
        $_SESSION[$delete->rowCount() === 1 ? 'flash_success' : 'flash_error'] = $delete->rowCount() === 1
            ? 'Činnost byla odstraněna z výkazu.'
            : 'Činnost nebyla nalezena nebo ji nemůžete odstranit.';
    } else {
        $_SESSION['flash_error'] = 'Odstranění činnosti je nutné výslovně potvrdit.';
    }
    $returnMonth = (string)($_POST['mesic'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $returnMonth)) {
        $returnMonth = date('Y-m');
    }
    header('Location: vypis_vykazu.php?mesic=' . rawurlencode($returnMonth), true, 303);
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$trener_id = $_SESSION['trener_id'];
$mesic = $_GET['mesic'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesic)) {
    $mesic = date('Y-m');
}

// České názvy měsíců
$czMonths = [
    1=>'leden',2=>'únor',3=>'březen',4=>'duben',5=>'květen',6=>'červen',
    7=>'červenec',8=>'srpen',9=>'září',10=>'říjen',11=>'listopad',12=>'prosinec'
];
$mesicNum = (int)date('n', strtotime($mesic . '-01'));
$rokNum   = (int)date('Y', strtotime($mesic . '-01'));
$mesicLabel = ucfirst($czMonths[$mesicNum] ?? '') . ' ' . $rokNum;

// Překlad dnů
$czDays = [
    'Monday'=>'Po','Tuesday'=>'Út','Wednesday'=>'St',
    'Thursday'=>'Čt','Friday'=>'Pá','Saturday'=>'So','Sunday'=>'Ne'
];

// Načtení tréninků, kde je uživatel přiřazen jako trenér
$tr_stmt = $pdo->prepare(
    "SELECT t.*
     FROM trenink_trener tt
     JOIN treninky t ON tt.trenink_id = t.id
     WHERE tt.trener_id = ?
       AND DATE_FORMAT(t.datum, '%Y-%m') = ?
     ORDER BY t.datum"
);
$tr_stmt->execute([$trener_id, $mesic]);
$treninky = $tr_stmt->fetchAll(PDO::FETCH_ASSOC);

// Načtení dalších činností
$ac_stmt = $pdo->prepare(
    "SELECT *
     FROM dalsi_cinnosti
     WHERE trener_id = ?
       AND DATE_FORMAT(datum, '%Y-%m') = ?
     ORDER BY datum"
);
$ac_stmt->execute([$trener_id, $mesic]);
$aktivity = $ac_stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiky
$hodinTreninky = array_sum(array_column($treninky, 'delka'));
$hodinAktivity = array_sum(array_column($aktivity, 'delka'));
$soucet_hodin  = $hodinTreninky + $hodinAktivity;
$pocetTreninku = count($treninky);
$pocetAktivit  = count($aktivity);

// Kategorie tréninků meta
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

// Export do Excelu
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Výkaz aktivit");

    // Hlavička
    $sheet->setCellValue('A1', "Měsíc: $mesic");
    $sheet->setCellValue('A2', 'Tréninky');
    $sheet->fromArray(['Datum', 'Náplň', 'Délka'], NULL, 'A3');

    // Tréninky
    $row = 4;
    foreach ($treninky as $t) {
        $sheet->setCellValue("A{$row}", $t['datum']);
        $sheet->setCellValue("B{$row}", $t['napln']);
        $sheet->setCellValue("C{$row}", $t['delka']);
        $row++;
    }

    // Další činnosti
    $sheet->setCellValue("A{$row}", 'Další činnosti');
    $row++;
    $sheet->fromArray(['Datum', 'Název', 'Délka', 'Poznámka'], NULL, "A{$row}");
    $row++;
    foreach ($aktivity as $a) {
        $sheet->setCellValue("A{$row}", $a['datum']);
        $sheet->setCellValue("B{$row}", $a['nazev']);
        $sheet->setCellValue("C{$row}", $a['delka']);
        $sheet->setCellValue("D{$row}", $a['poznamka']);
        $row++;
    }

    // Součet
    $sheet->setCellValue("A{$row}", 'Celkem hodin');
    $sheet->setCellValue("B{$row}", $soucet_hodin);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vykaz_' . $mesic . '.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Výkaz činností</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .badge-orange { background-color: #fd7e14; color: #fff; }
        .badge-teal   { background-color: #20c997; color: #fff; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-clipboard-data me-2 text-primary"></i>Výkaz činností</h1>
    </div>

    <!-- Filtr měsíce -->
    <form method="GET" class="card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="mesic" class="form-label"><i class="bi bi-calendar-month me-1"></i>Měsíc</label>
                <input type="month" name="mesic" id="mesic" value="<?= h($mesic) ?>" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Zobrazit
                </button>
                <a href="?mesic=<?= h($mesic) ?>&export=xls" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export XLSX
                </a>
            </div>
        </div>
    </form>

    <!-- Stats karty -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary"><?= number_format($soucet_hodin, 1, ',', ' ') ?></div>
                    <div class="text-muted small"><i class="bi bi-clock me-1"></i>Hodin celkem</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success"><?= $pocetTreninku ?></div>
                    <div class="text-muted small"><i class="bi bi-bicycle me-1"></i>Tréninků</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-info"><?= $pocetAktivit ?></div>
                    <div class="text-muted small"><i class="bi bi-list-check me-1"></i>Dalších činností</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-warning"><?= $pocetTreninku + $pocetAktivit ?></div>
                    <div class="text-muted small"><i class="bi bi-bar-chart me-1"></i>Položek celkem</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tréninky -->
    <div class="d-flex align-items-center mb-2">
        <h4 class="mb-0"><i class="bi bi-bicycle me-2 text-success"></i>Tréninky</h4>
        <span class="badge bg-success ms-2"><?= $pocetTreninku ?></span>
    </div>
    <?php if (empty($treninky)): ?>
        <div class="alert alert-light border small mb-4"><i class="bi bi-info-circle me-1"></i>Žádné tréninky v tomto měsíci.</div>
    <?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table table-striped table-hover align-middle table-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width:100px;"><i class="bi bi-calendar3 me-1"></i>Datum</th>
                    <th style="width:40px;">Den</th>
                    <th><i class="bi bi-card-text me-1"></i>Náplň</th>
                    <th style="width:90px;"><i class="bi bi-tag me-1"></i>Kategorie</th>
                    <th style="width:75px;" class="text-end"><i class="bi bi-clock me-1"></i>Hodin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($treninky as $t):
                    $dayEng = (new DateTime($t['datum']))->format('l');
                    $dayCz  = $czDays[$dayEng] ?? '';
                    $kat    = $t['kategorie'] ?? '';
                    $km     = $kategorieMeta[$kat] ?? null;
                ?>
                <tr>
                    <td class="fw-semibold"><?= date('d.m.', strtotime($t['datum'])) ?></td>
                    <td class="text-muted small"><?= h($dayCz) ?></td>
                    <td><?= h($t['napln']) ?></td>
                    <td>
                        <?php if ($km): ?>
                            <span class="badge bg-<?= $km['color'] ?>"><i class="bi <?= $km['icon'] ?> me-1"></i><?= $km['label'] ?></span>
                        <?php elseif ($kat): ?>
                            <span class="badge bg-secondary"><?= h($kat) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end fw-semibold"><?= number_format($t['delka'], 1, ',', '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="4" class="text-end"><i class="bi bi-calculator me-1"></i>Součet tréninky:</td>
                    <td class="text-end"><?= number_format($hodinTreninky, 1, ',', ' ') ?> h</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- Další činnosti -->
    <div class="d-flex align-items-center mb-2">
        <h4 class="mb-0"><i class="bi bi-list-check me-2 text-info"></i>Další činnosti</h4>
        <span class="badge bg-info ms-2"><?= $pocetAktivit ?></span>
    </div>
    <?php if (empty($aktivity)): ?>
        <div class="alert alert-light border small mb-4"><i class="bi bi-info-circle me-1"></i>Žádné další činnosti v tomto měsíci.</div>
    <?php else: ?>
    <div class="table-responsive mb-4">
        <table class="table table-striped table-hover align-middle table-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width:100px;"><i class="bi bi-calendar3 me-1"></i>Datum</th>
                    <th style="width:40px;">Den</th>
                    <th><i class="bi bi-card-text me-1"></i>Název</th>
                    <th><i class="bi bi-chat-left-text me-1"></i>Poznámka</th>
                    <th style="width:75px;" class="text-end"><i class="bi bi-clock me-1"></i>Hodin</th>
                    <th style="width:90px;" class="text-end">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aktivity as $a):
                    $dayEng = (new DateTime($a['datum']))->format('l');
                    $dayCz  = $czDays[$dayEng] ?? '';
                ?>
                <tr>
                    <td class="fw-semibold"><?= date('d.m.', strtotime($a['datum'])) ?></td>
                    <td class="text-muted small"><?= h($dayCz) ?></td>
                    <td><?= h($a['nazev']) ?></td>
                    <td class="text-muted small"><?= h($a['poznamka']) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($a['delka'], 1, ',', '') ?></td>
                    <td class="text-end"><details class="d-inline-block text-start"><summary class="btn btn-sm btn-outline-danger" aria-label="Odstranit činnost <?= h($a['nazev']) ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="d-none d-lg-inline ms-1">Odstranit</span></summary><form method="post" class="border rounded bg-white p-2 mt-1" style="min-width:210px"><?= csrf_field() ?><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="mesic" value="<?= h($mesic) ?>"><label class="small d-block"><input type="checkbox" name="confirm_action" value="1" required> Opravdu odstranit tuto činnost</label><button class="btn btn-sm btn-danger mt-2">Potvrdit odstranění</button></form></details></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-info fw-bold">
                    <td colspan="4" class="text-end"><i class="bi bi-calculator me-1"></i>Součet činnosti:</td>
                    <td class="text-end"><?= number_format($hodinAktivity, 1, ',', ' ') ?> h</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- Celkový součet -->
    <div class="card border-primary mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-check-circle-fill text-primary me-2 fs-4"></i>
                <span class="fs-5 fw-bold">Celkem odpracováno za <?= h($mesicLabel) ?>:</span>
            </div>
            <div class="fs-3 fw-bold text-primary">
                <?= number_format($soucet_hodin, 1, ',', ' ') ?> h
            </div>
        </div>
    </div>

</div>

</body>
</html>
