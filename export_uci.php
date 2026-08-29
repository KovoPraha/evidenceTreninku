<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/includes/private_storage.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Converts MySQL date (YYYY-MM-DD) to UCI format DD/MM/YYYY
 */
function dobToUci(?string $date): string {
    if (!$date || $date === '0000-00-00') return '';
    $ts = strtotime($date);
    if ($ts === false || $ts <= 0) return '';
    return date('d/m/Y', $ts);
}

/**
 * Converts Excel serial date to human-readable dd.mm.YYYY
 */
function excelDateToString($value): string {
    if (empty($value)) return '—';
    if (is_numeric($value)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float)$value);
            return $dt->format('d.m.Y');
        } catch (Throwable $e) {
            return (string)$value;
        }
    }
    return (string)$value;
}

/**
 * Remove files created by the retired public-webroot implementation.
 */
function cleanupLegacyPublicUciFiles(): void {
    $dir = __DIR__ . '/uploads/temp';
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/uci_*.xlsx') ?: [] as $file) {
        @unlink($file);
    }
}

function uciUploadPathFromSession(): ?string {
    $key = (string)($_SESSION['uci_upload_key'] ?? '');
    return $key !== '' ? privateStorageResolve($key) : null;
}

// -------------------------------------------------------------------
// Config
// -------------------------------------------------------------------
$errors      = [];
$uploadInfo  = null;
$skupinaId   = trim((string)($_GET['skupina_id'] ?? ($_POST['skupina_id'] ?? '')));

// Private garbage collection plus immediate cleanup of the retired webroot path.
try {
    privateStorageCleanupUciTemp();
} catch (Throwable $exception) {
    $errors[] = 'Soukromé úložiště UCI formulářů není dostupné.';
    error_log('UCI private storage cleanup failed: ' . $exception->getMessage());
}
cleanupLegacyPublicUciFiles();
unset($_SESSION['uci_upload_path']);

// -------------------------------------------------------------------
// Handle file upload (POST with uci_file)
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uci_file']) && $_FILES['uci_file']['error'] === UPLOAD_ERR_OK) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $tmpFile = $_FILES['uci_file']['tmp_name'];
        $origName = $_FILES['uci_file']['name'];

        // MIME validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpFile);
        finfo_close($finfo);

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
            'application/zip',
        ];

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($mime, $allowedMimes) || $ext !== 'xlsx') {
            $errors[] = 'Nahrajte prosím platný Excel soubor (.xlsx).';
        } elseif ($_FILES['uci_file']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Soubor je příliš velký (max. 5 MB).';
        } else {
            try {
                $previousKey = (string)($_SESSION['uci_upload_key'] ?? '');
                if ($previousKey !== '') {
                    privateStorageDeleteUciTemp($previousKey);
                }
                $storageKey = privateStorageStoreUciTemp($tmpFile, $origName);
                $_SESSION['uci_upload_key'] = $storageKey;
                $_SESSION['uci_upload_name'] = $origName;
                // Redirect to avoid re-upload on refresh
                header('Location: export_uci.php' . ($skupinaId !== '' ? '?skupina_id=' . urlencode($skupinaId) : ''));
                exit;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------
// Handle reset (clear uploaded file)
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_upload'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $storageKey = (string)($_SESSION['uci_upload_key'] ?? '');
        try {
            if ($storageKey !== '') {
                privateStorageDeleteUciTemp($storageKey);
            }
            unset($_SESSION['uci_upload_key'], $_SESSION['uci_upload_name']);
            header('Location: export_uci.php');
            exit;
        } catch (Throwable $exception) {
            $errors[] = 'Dočasný UCI formulář se nepodařilo odstranit.';
            error_log('UCI private storage reset failed: ' . $exception->getMessage());
        }
    }
}

// -------------------------------------------------------------------
// Load upload info (if file exists in session)
// -------------------------------------------------------------------
$hasUpload = false;
$uciUploadPath = uciUploadPathFromSession();
if ($uciUploadPath !== null) {
    $hasUpload = true;
    try {
        $previewSpreadsheet = IOFactory::load($uciUploadPath);
        $previewSheet = $previewSpreadsheet->getSheetByName('Bulletin Engagement UCI');
        if (!$previewSheet) $previewSheet = $previewSpreadsheet->getSheet(0);

        $uploadInfo = [
            'file'        => $_SESSION['uci_upload_name'] ?? basename($uciUploadPath),
            'event'       => (string)$previewSheet->getCell('C7')->getValue(),
            'class'       => (string)$previewSheet->getCell('G7')->getValue(),
            'organiser'   => (string)$previewSheet->getCell('C8')->getValue(),
            'category'    => (string)$previewSheet->getCell('G8')->getValue(),
            'country'     => (string)$previewSheet->getCell('C9')->getValue(),
            'date_start'  => excelDateToString($previewSheet->getCell('G9')->getValue()),
            'date_end'    => excelDateToString($previewSheet->getCell('G10')->getValue()),
        ];
        $previewSpreadsheet->disconnectWorksheets();
        unset($previewSpreadsheet);
    } catch (Throwable $e) {
        $errors[] = 'Chyba při čtení Excel souboru: ' . $e->getMessage();
        $hasUpload = false;
    }
}

// -------------------------------------------------------------------
// Load skupiny
// -------------------------------------------------------------------
$skupiny = [];
try {
    $skupiny = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errors[] = "Chyba při načítání skupin: " . $e->getMessage();
}

// -------------------------------------------------------------------
// POST: Generate export
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } elseif (!$hasUpload) {
        $errors[] = 'Nejdříve nahrajte UCI formulář.';
    } else {
        $selectedIds = $_POST['sportovci'] ?? [];
        if (!is_array($selectedIds)) $selectedIds = [];
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), fn($v) => $v > 0));

        if (count($selectedIds) === 0) {
            $errors[] = "Není vybrán žádný sportovec pro export.";
        } elseif (count($selectedIds) > 11) {
            $errors[] = "Lze vybrat maximálně 11 sportovců (prvních 1–8 → Titular Riders, 9–11 → Substitute Riders).";
        } else {
            try {
                // ---- Load athletes in submitted order ----
                $in    = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt  = $pdo->prepare("SELECT id, jmeno, prijmeni, narozeni, uciid FROM sportovci WHERE id IN ({$in})");
                $stmt->execute($selectedIds);
                $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $byId  = array_column($fetched, null, 'id');
                $sportovciExport = [];
                foreach ($selectedIds as $id) {
                    if (isset($byId[$id])) $sportovciExport[] = $byId[$id];
                }

                // ---- Load Sport Directors ----
                $stmtMixa = $pdo->prepare("SELECT jmeno, prijmeni, uciid FROM sportovci WHERE prijmeni LIKE ? LIMIT 1");
                $stmtMixa->execute(['%Mixa%']);
                $mixa = $stmtMixa->fetch(PDO::FETCH_ASSOC) ?: null;

                $stmtPapik = $pdo->prepare("SELECT jmeno, prijmeni, uciid FROM sportovci WHERE prijmeni LIKE ? OR prijmeni LIKE ? LIMIT 1");
                $stmtPapik->execute(['%Papík%', '%Papik%']);
                $papik = $stmtPapik->fetch(PDO::FETCH_ASSOC) ?: null;

                $directors = array_values(array_filter([$mixa, $papik]));

                // ---- Open uploaded file ----
                $spreadsheet = IOFactory::load($uciUploadPath);
                $sheet = $spreadsheet->getSheetByName('Bulletin Engagement UCI');
                if (!$sheet) {
                    $sheet = $spreadsheet->getSheet(0);
                }

                // ---- Clear rider data rows (preserve everything else) ----
                $riderCols  = ['K', 'L', 'M', 'N', 'O'];
                $riderRows  = array_merge(range(15, 22), range(25, 28));
                foreach ($riderRows as $r) {
                    foreach ($riderCols as $c) {
                        $sheet->setCellValue($c . $r, '');
                    }
                }

                // Clear director rows
                $directorCols = ['K', 'L', 'M', 'N', 'O', 'P'];
                $directorRows = [33, 38, 39];
                foreach ($directorRows as $r) {
                    foreach ($directorCols as $c) {
                        $sheet->setCellValue($c . $r, '');
                    }
                }

                // ---- Fill Titular Riders (rows 15–22, max 8) ----
                $titularRows    = [15, 16, 17, 18, 19, 20, 21, 22];
                $substituteRows = [25, 26, 27, 28];

                foreach ($sportovciExport as $idx => $s) {
                    if ($idx < 8) {
                        $row = $titularRows[$idx];
                    } else {
                        $subIdx = $idx - 8;
                        if ($subIdx >= count($substituteRows)) break;
                        $row = $substituteRows[$subIdx];
                    }
                    $sheet->setCellValue("K{$row}", (string)($s['prijmeni'] ?? ''));
                    $sheet->setCellValue("L{$row}", (string)($s['jmeno']    ?? ''));
                    $sheet->setCellValue("M{$row}", 'CZE');
                    $sheet->setCellValue("N{$row}", dobToUci($s['narozeni'] ?? null));
                    $sheet->setCellValue("O{$row}", (string)($s['uciid']    ?? ''));
                }

                // ---- Fill Titular Sport Director (row 33) ----
                if (isset($directors[0])) {
                    $d = $directors[0];
                    $sheet->setCellValue("K33", (string)($d['prijmeni'] ?? ''));
                    $sheet->setCellValue("L33", (string)($d['jmeno']    ?? ''));
                    $sheet->setCellValue("M33", 'CZE');
                    $sheet->setCellValue("N33", (string)($d['uciid']    ?? ''));
                }

                // ---- Fill Sport Directors on event (rows 38–39) ----
                foreach ($directors as $idx => $d) {
                    if ($idx >= 2) break;
                    $row = 38 + $idx;
                    $sheet->setCellValue("K{$row}", (string)($d['prijmeni'] ?? ''));
                    $sheet->setCellValue("L{$row}", (string)($d['jmeno']    ?? ''));
                    $sheet->setCellValue("M{$row}", 'CZE');
                    $sheet->setCellValue("N{$row}", (string)($d['uciid']    ?? ''));
                }

                // ---- Stream download ----
                $filename = 'UCI_enrolment_' . date('Ymd_His') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save('php://output');
                exit;

            } catch (Throwable $e) {
                $errors[] = "Chyba při exportu: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------
// Load athletes for display (GET)
// -------------------------------------------------------------------
$sportovci = [];
if ($skupinaId !== '' && ctype_digit($skupinaId)) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.jmeno, s.prijmeni, s.narozeni, s.uciid, s.category
            FROM sportovci s
            INNER JOIN sportovec_skupina sg ON sg.sportovec_id = s.id
            WHERE sg.skupina_id = :gid
            ORDER BY s.prijmeni, s.jmeno
        ");
        $stmt->execute([':gid' => (int)$skupinaId]);
        $sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = "Chyba při načítání sportovců: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Export – UCI přihláška</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <style>
        table { table-layout: fixed; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-ind { font-size: 12px; opacity: .7; margin-left: 4px; }

        .table-sm > :not(caption) > * > * { padding: 6px !important; }

        .col-check { width: 48px;  min-width: 48px;  max-width: 48px;  text-align: center; }
        .col-rank  { width: 48px;  min-width: 48px;  max-width: 48px;  text-align: center; }
        .col-lname { width: 190px; min-width: 190px; max-width: 190px; }
        .col-fname { width: 150px; min-width: 150px; max-width: 150px; }
        .col-born  { width: 115px; min-width: 115px; max-width: 115px; }
        .col-uci   { width: 155px; min-width: 155px; max-width: 155px; }
        .col-cat   { width: 120px; min-width: 120px; max-width: 120px; }

        td.ellipsis, th.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        input.form-check-input { margin: 0 !important; }

        .badge-titular    { background: #198754; color: #fff; font-size: .75rem; padding: 2px 6px; border-radius: 4px; }
        .badge-substitute { background: #fd7e14; color: #fff; font-size: .75rem; padding: 2px 6px; border-radius: 4px; }

        #counterBar {
            position: sticky;
            top: 56px;
            z-index: 100;
            padding: .55rem 1rem;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,.06);
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .limit-warning { color: #dc3545; font-weight: 600; animation: flash .3s ease; }
        @keyframes flash { 0%,100%{ opacity:1 } 50%{ opacity:0 } }

        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: .5rem;
            padding: 2rem;
            text-align: center;
            transition: border-color .2s, background .2s;
            cursor: pointer;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: #0d6efd;
            background: rgba(13,110,253,.04);
        }
        .upload-zone input[type="file"] {
            position: absolute;
            left: -9999px;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1 class="mb-1"><i class="bi bi-file-earmark-arrow-up me-2"></i>Export – UCI přihláška <small class="text-muted fs-6">(Enrolment Form)</small></h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <?php foreach ($errors as $err): ?>
                <div><?= h($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STEP 1: Upload UCI form                                       -->
    <!-- ============================================================ -->
    <?php if (!$hasUpload): ?>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-1-circle me-2"></i>Nahrajte UCI formulář
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Nahrajte předvyplněný UCI Enrollment Form (.xlsx) pro konkrétní závod.
                    Aplikace zachová všechna vyplněná pole a doplní seznam jezdců.
                </p>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <?= csrf_field() ?>
                    <div class="upload-zone" id="uploadZone">
                        <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-2"></i>
                        <strong>Přetáhněte sem .xlsx soubor</strong>
                        <span class="text-muted d-block mt-1">nebo klikněte pro výběr souboru</span>
                        <input type="file" name="uci_file" id="uciFileInput" accept=".xlsx">
                        <div id="fileNameDisplay" class="mt-2 text-primary fw-semibold" style="display:none;"></div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                            <i class="bi bi-upload me-1"></i>Nahrát formulář
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>

        <!-- ============================================================ -->
        <!-- STEP 2: Event preview + athlete selection                     -->
        <!-- ============================================================ -->

        <!-- Event info card -->
        <div class="card shadow-sm mt-3 border-success">
            <div class="card-header bg-success text-white d-flex align-items-center">
                <i class="bi bi-check-circle me-2"></i>Nahraný formulář
                <form method="post" class="ms-auto">
                    <?= csrf_field() ?>
                    <button type="submit" name="reset_upload" value="1" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Nahrát jiný
                    </button>
                </form>
            </div>
            <?php if ($uploadInfo): ?>
            <div class="card-body py-2">
                <div class="row g-2 small">
                    <div class="col-md-5">
                        <strong><i class="bi bi-trophy me-1"></i>Závod:</strong>
                        <?= h($uploadInfo['event'] ?: '—') ?>
                    </div>
                    <div class="col-md-2">
                        <strong>Třída:</strong> <?= h($uploadInfo['class'] ?: '—') ?>
                    </div>
                    <div class="col-md-2">
                        <strong>Kategorie:</strong> <?= h($uploadInfo['category'] ?: '—') ?>
                    </div>
                    <div class="col-md-3">
                        <strong><i class="bi bi-geo-alt me-1"></i></strong><?= h($uploadInfo['country'] ?: '—') ?>
                    </div>
                </div>
                <div class="row g-2 small mt-1">
                    <div class="col-md-3">
                        <strong>Organizátor:</strong> <?= h($uploadInfo['organiser'] ?: '—') ?>
                    </div>
                    <div class="col-md-3">
                        <strong><i class="bi bi-calendar me-1"></i>Datum:</strong>
                        <?= h($uploadInfo['date_start']) ?>
                        <?php if ($uploadInfo['date_start'] !== $uploadInfo['date_end']): ?>
                            – <?= h($uploadInfo['date_end']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-muted">
                        <i class="bi bi-file-earmark-excel me-1"></i><?= h($uploadInfo['file']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info bar -->
        <p class="text-muted small mt-3 mb-2">
            Max. <strong>11</strong> sportovců &nbsp;·&nbsp;
            prvních 8 → <span class="badge-titular">Titular Riders</span> &nbsp;·&nbsp;
            9–11 → <span class="badge-substitute">Substitute Riders</span> &nbsp;·&nbsp;
            Sport Directors: <strong>Mixa + Papík</strong> (auto)
        </p>

        <!-- Skupina filter -->
        <form method="get" class="row g-3 mb-3">
            <div class="col-md-5">
                <label for="skupina_id" class="form-label fw-semibold">Skupina</label>
                <select class="form-select" id="skupina_id" name="skupina_id" onchange="this.form.submit()">
                    <option value="">— vyber skupinu —</option>
                    <?php foreach ($skupiny as $sk): ?>
                        <option value="<?= (int)$sk['id'] ?>" <?= ($skupinaId == (string)$sk['id']) ? 'selected' : '' ?>>
                            <?= h((string)$sk['nazev']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Načíst</button>
            </div>
        </form>

        <?php if ($skupinaId === ''): ?>
            <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Nejdřív vyber skupinu a poté zaškrtni sportovce pro přihlášku.</div>

        <?php elseif (empty($sportovci)): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Ve vybrané skupině nejsou žádní sportovci.</div>

        <?php else: ?>

            <!-- Sticky counter bar -->
            <div id="counterBar" class="rounded mb-3">
                <div>
                    Vybráno: <strong id="cntTotal">0</strong> / 11
                </div>
                <div>
                    <span class="badge-titular">Titular</span>&nbsp;<strong id="cntTitular">0</strong> / 8
                </div>
                <div>
                    <span class="badge-substitute">Substitute</span>&nbsp;<strong id="cntSubstitute">0</strong> / 3
                </div>
                <div class="ms-auto text-muted small" id="cntWarning"></div>
            </div>

            <form method="post" id="exportForm" class="mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="skupina_id" value="<?= h($skupinaId) ?>">

                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="checkAll">
                        <i class="bi bi-check-all me-1"></i>Zaškrtnout vše
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="uncheckAll">
                        <i class="bi bi-x-lg me-1"></i>Odškrtnout vše
                    </button>
                    <div class="ms-auto">
                        <button type="submit" name="export" value="1" class="btn btn-success">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportovat do UCI formuláře
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="expTable" class="table table-striped table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="col-check text-center">&check;</th>
                                <th class="col-rank text-center">#</th>
                                <th class="sortable col-lname ellipsis" data-sort="lname">
                                    Příjmení <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-fname ellipsis" data-sort="fname">
                                    Jméno <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-born" data-sort="born">
                                    Datum nar. <span class="sort-ind"></span>
                                </th>
                                <th class="col-uci ellipsis">UCI ID</th>
                                <th class="sortable col-cat ellipsis" data-sort="cat">
                                    Kategorie <span class="sort-ind"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sportovci as $s): ?>
                            <?php
                                $sid   = (int)$s['id'];
                                $lname = (string)($s['prijmeni'] ?? '');
                                $fname = (string)($s['jmeno']    ?? '');
                                $born  = (string)($s['narozeni'] ?? '');
                                $uciid = (string)($s['uciid']    ?? '');
                                $cat   = (string)($s['category'] ?? '');
                                $bornTs = $born ? (string)strtotime($born) : '0';
                                $bornFmt = ($born && $born !== '0000-00-00') ? date('d.m.Y', strtotime($born)) : '—';
                            ?>
                            <tr data-lname="<?= h(mb_strtolower($lname, 'UTF-8')) ?>"
                                data-fname="<?= h(mb_strtolower($fname, 'UTF-8')) ?>"
                                data-born="<?= h($bornTs) ?>"
                                data-cat="<?= h(mb_strtolower($cat, 'UTF-8')) ?>">
                                <td class="col-check text-center">
                                    <input class="form-check-input rowcb" type="checkbox"
                                           name="sportovci[]" value="<?= $sid ?>">
                                </td>
                                <td class="col-rank text-center">
                                    <span class="rank-label text-muted">—</span>
                                </td>
                                <td class="col-lname ellipsis" title="<?= h($lname) ?>"><?= h($lname) ?></td>
                                <td class="col-fname ellipsis" title="<?= h($fname) ?>"><?= h($fname) ?></td>
                                <td class="col-born"><?= h($bornFmt) ?></td>
                                <td class="col-uci ellipsis" title="<?= h($uciid) ?>">
                                    <?= $uciid ? h($uciid) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="col-cat ellipsis" title="<?= h($cat) ?>"><?= h($cat) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-muted small mt-1">
                    Pořadí zaškrtnutých sportovců (dle viditelného řazení tabulky) určuje, kdo je <strong>Titular</strong> (1.–8.) a kdo <strong>Substitute</strong> (9.–11.).<br>
                    Export doplní sloupce: <strong>Příjmení, Jméno, NAT (CZE), Datum nar., UCI ID</strong>.
                    Sport Directors: <strong>Marek Mixa + Václav Papík</strong> (automaticky z databáze).
                </div>
            </form>

        <?php endif; ?>

    <?php endif; ?>
</div>


<script>
(function () {
    // ----------------------------------------------------------------
    // Upload zone drag & drop + click
    // ----------------------------------------------------------------
    const uploadZone = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('uciFileInput');
    const uploadBtn  = document.getElementById('uploadBtn');
    const fileNameDisplay = document.getElementById('fileNameDisplay');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', e => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('drag-over');
        });
        uploadZone.addEventListener('drop', e => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                onFileSelected();
            }
        });

        fileInput.addEventListener('change', onFileSelected);

        function onFileSelected() {
            if (fileInput.files.length > 0) {
                const name = fileInput.files[0].name;
                if (!name.toLowerCase().endsWith('.xlsx')) {
                    alert('Prosím vyberte soubor s příponou .xlsx');
                    fileInput.value = '';
                    uploadBtn.disabled = true;
                    fileNameDisplay.style.display = 'none';
                    return;
                }
                fileNameDisplay.textContent = name;
                fileNameDisplay.style.display = 'block';
                uploadBtn.disabled = false;
            }
        }
    }

    // ----------------------------------------------------------------
    // Athlete table logic (only if table exists)
    // ----------------------------------------------------------------
    const table = document.getElementById('expTable');
    if (!table) return;

    const tbody        = table.querySelector('tbody');
    const headers      = table.querySelectorAll('th.sortable');
    const cntTotal     = document.getElementById('cntTotal');
    const cntTitular   = document.getElementById('cntTitular');
    const cntSubstitute= document.getElementById('cntSubstitute');
    const cntWarning   = document.getElementById('cntWarning');

    let currentSort = { key: null, dir: 1 };
    const MAX = 11;

    // ---- Counter + visual rank labels ----
    function updateCounter() {
        const allCbs = Array.from(tbody.querySelectorAll('.rowcb'));
        const checked = allCbs.filter(cb => cb.checked);
        const count   = checked.length;

        const titular    = Math.min(count, 8);
        const substitute = Math.max(count - 8, 0);

        cntTotal.textContent      = count;
        cntTitular.textContent    = titular;
        cntSubstitute.textContent = substitute;

        if (count > MAX) {
            cntWarning.textContent  = '\u26A0 Překročen limit 11!';
            cntWarning.className    = 'ms-auto limit-warning';
        } else {
            cntWarning.textContent  = '';
            cntWarning.className    = 'ms-auto text-muted small';
        }

        allCbs.forEach(cb => {
            const tr   = cb.closest('tr');
            const rank = tr.querySelector('.rank-label');
            tr.classList.remove('table-success', 'table-warning');
            rank.textContent = '\u2014';
            rank.style.fontWeight = '';
        });

        checked.forEach((cb, idx) => {
            const tr   = cb.closest('tr');
            const rank = tr.querySelector('.rank-label');
            const pos  = idx + 1;

            if (idx < 8) {
                tr.classList.add('table-success');
                rank.innerHTML = '<span class="badge-titular">' + pos + '</span>';
            } else {
                tr.classList.add('table-warning');
                rank.innerHTML = '<span class="badge-substitute">' + pos + '</span>';
            }
        });
    }

    // ---- Checkbox change ----
    function onCbChange(e) {
        const checked = tbody.querySelectorAll('.rowcb:checked');
        if (checked.length > MAX) {
            e.target.checked = false;
            cntWarning.textContent = '\u26A0 Maximálně 11 sportovců!';
            cntWarning.className   = 'ms-auto limit-warning';
            setTimeout(() => { cntWarning.textContent = ''; cntWarning.className = 'ms-auto text-muted small'; }, 2000);
        }
        updateCounter();
    }

    tbody.addEventListener('change', e => {
        if (e.target.classList.contains('rowcb')) onCbChange(e);
    });

    // ---- Sort ----
    function setIndicators(activeTh, dir) {
        headers.forEach(th => {
            const ind = th.querySelector('.sort-ind');
            if (!ind) return;
            ind.textContent = (th === activeTh) ? (dir === 1 ? '\u25B2' : '\u25BC') : '';
        });
    }

    function sortRows(key, dir) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const va = a.getAttribute('data-' + key) || '';
            const vb = b.getAttribute('data-' + key) || '';
            if (key === 'born') {
                return (parseInt(va || '0', 10) - parseInt(vb || '0', 10)) * dir;
            }
            return va.localeCompare(vb, 'cs', { sensitivity: 'base' }) * dir;
        });
        rows.forEach(r => tbody.appendChild(r));
        updateCounter();
    }

    headers.forEach(th => {
        th.addEventListener('click', () => {
            const key = th.getAttribute('data-sort');
            const dir = (currentSort.key === key) ? -currentSort.dir : 1;
            currentSort = { key, dir };
            sortRows(key, dir);
            setIndicators(th, dir);
        });
    });

    // ---- Select all / none ----
    document.getElementById('checkAll')?.addEventListener('click', () => {
        const cbs = Array.from(tbody.querySelectorAll('.rowcb'));
        cbs.forEach((cb, idx) => { cb.checked = idx < MAX; });
        updateCounter();
    });

    document.getElementById('uncheckAll')?.addEventListener('click', () => {
        tbody.querySelectorAll('.rowcb').forEach(cb => cb.checked = false);
        updateCounter();
    });

    // ---- Validate before submit ----
    document.getElementById('exportForm')?.addEventListener('submit', e => {
        const count = tbody.querySelectorAll('.rowcb:checked').length;
        if (count === 0) {
            e.preventDefault();
            alert('Vyberte alespoň jednoho sportovce.');
        } else if (count > MAX) {
            e.preventDefault();
            alert('Lze exportovat maximálně 11 sportovců.');
        }
    });

    updateCounter();
})();
</script>
</body>
</html>
