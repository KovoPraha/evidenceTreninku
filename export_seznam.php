<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'csrf_helper.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -------------------------------------------------------------------
// Config
// -------------------------------------------------------------------
$errors    = [];
$skupinaId = trim((string)($_GET['skupina_id'] ?? ($_POST['skupina_id'] ?? '')));

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
// POST: Generate XLSX export
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $selectedIds = $_POST['sportovci'] ?? [];
        if (!is_array($selectedIds)) $selectedIds = [];
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), fn($v) => $v > 0));

        if (count($selectedIds) === 0) {
            $errors[] = "Není vybrán žádný sportovec pro export.";
        } else {
            try {
                // Load athletes in submitted order
                $in   = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $pdo->prepare("SELECT id, jmeno, prijmeni, narozeni, uciid, category FROM sportovci WHERE id IN ({$in})");
                $stmt->execute($selectedIds);
                $fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $byId    = array_column($fetched, null, 'id');
                $sportovciExport = [];
                foreach ($selectedIds as $id) {
                    if (isset($byId[$id])) $sportovciExport[] = $byId[$id];
                }

                // Build spreadsheet
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Seznam sportovců');

                // Header row
                $headers = ['Příjmení', 'Jméno', 'Datum narození', 'Ročník', 'Kategorie', 'UCI ID'];
                $cols    = ['A', 'B', 'C', 'D', 'E', 'F'];

                foreach ($headers as $i => $label) {
                    $sheet->setCellValue($cols[$i] . '1', $label);
                }

                // Header style
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a1a2e']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ];
                $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Data rows
                $row = 2;
                foreach ($sportovciExport as $s) {
                    $born     = $s['narozeni'] ?? '';
                    $bornFmt  = '';
                    $bornYear = '';
                    if ($born && $born !== '0000-00-00') {
                        $ts = strtotime($born);
                        if ($ts !== false && $ts > 0) {
                            $bornFmt  = date('d-m-Y', $ts);
                            $bornYear = date('Y', $ts);
                        }
                    }

                    // setCellValueExplicit TYPE_STRING — text z DB se nikdy neinterpretuje jako vzorec
                    $TS = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
                    $sheet->setCellValueExplicit("A{$row}", (string)($s['prijmeni'] ?? ''), $TS);
                    $sheet->setCellValueExplicit("B{$row}", (string)($s['jmeno']    ?? ''), $TS);
                    $sheet->setCellValueExplicit("C{$row}", $bornFmt, $TS);
                    $sheet->setCellValue("D{$row}", $bornYear);
                    $sheet->setCellValueExplicit("E{$row}", (string)($s['category'] ?? ''), $TS);
                    $sheet->setCellValueExplicit("F{$row}", (string)($s['uciid'] ?? ''), $TS);

                    $row++;
                }

                // Data borders
                $lastRow = $row - 1;
                if ($lastRow >= 2) {
                    $sheet->getStyle("A2:F{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                }

                // Column widths
                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(16);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(16);
                $sheet->getColumnDimension('F')->setWidth(18);

                // Find skupina name for filename
                $skupinaName = 'export';
                if ($skupinaId !== '' && ctype_digit($skupinaId)) {
                    foreach ($skupiny as $sk) {
                        if ((string)$sk['id'] === $skupinaId) {
                            $skupinaName = $sk['nazev'];
                            break;
                        }
                    }
                }
                // Sanitize filename
                $safeName = preg_replace('/[^a-zA-Z0-9áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ_\-]/u', '_', $skupinaName);
                $safeName = preg_replace('/_+/', '_', $safeName);
                $safeName = trim($safeName, '_');
                if ($safeName === '') $safeName = 'export';

                $filename = 'Seznam_' . $safeName . '_' . date('Ymd') . '.xlsx';

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
    <title>Export – Seznam sportovců</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        table { table-layout: fixed; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-ind { font-size: 12px; opacity: .7; margin-left: 4px; }

        .table-sm > :not(caption) > * > * { padding: 6px !important; }

        .col-check { width: 48px;  min-width: 48px;  max-width: 48px;  text-align: center; }
        .col-lname { width: 190px; min-width: 190px; max-width: 190px; }
        .col-fname { width: 150px; min-width: 150px; max-width: 150px; }
        .col-born  { width: 115px; min-width: 115px; max-width: 115px; }
        .col-uci   { width: 155px; min-width: 155px; max-width: 155px; }
        .col-cat   { width: 120px; min-width: 120px; max-width: 120px; }

        td.ellipsis, th.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        input.form-check-input { margin: 0 !important; }

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
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1 class="mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export – Seznam sportovců <small class="text-muted fs-6">(XLSX)</small></h1>
    <p class="text-muted small mb-3">Vyberte skupinu a sportovce pro export do Excel souboru.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mt-3">
            <?php foreach ($errors as $err): ?>
                <div><?= h($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
        <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Nejdřív vyber skupinu a poté zaškrtni sportovce pro export.</div>

    <?php elseif (empty($sportovci)): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Ve vybrané skupině nejsou žádní sportovci.</div>

    <?php else: ?>

        <!-- Sticky counter bar -->
        <div id="counterBar" class="rounded mb-3">
            <div>
                Vybráno: <strong id="cntTotal">0</strong> / <?= count($sportovci) ?>
            </div>
            <div class="ms-auto text-muted small">
                Export: Příjmení, Jméno, Datum nar., Ročník, Kategorie, UCI ID
            </div>
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
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportovat do XLSX
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="expTable" class="table table-striped table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th class="col-check text-center">&check;</th>
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
                Exportovaný soubor bude obsahovat sloupce: <strong>Příjmení, Jméno, Datum narození (dd-mm-yyyy), Ročník (yyyy), Kategorie, UCI ID</strong>.
            </div>
        </form>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const table = document.getElementById('expTable');
    if (!table) return;

    const tbody    = table.querySelector('tbody');
    const headers  = table.querySelectorAll('th.sortable');
    const cntTotal = document.getElementById('cntTotal');

    let currentSort = { key: null, dir: 1 };

    // ---- Counter ----
    function updateCounter() {
        const checked = tbody.querySelectorAll('.rowcb:checked').length;
        cntTotal.textContent = checked;
    }

    tbody.addEventListener('change', e => {
        if (e.target.classList.contains('rowcb')) updateCounter();
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
        tbody.querySelectorAll('.rowcb').forEach(cb => cb.checked = true);
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
        }
    });

    updateCounter();
})();
</script>
</body>
</html>
