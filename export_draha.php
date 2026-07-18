<?php
session_start();
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once __DIR__ . '/csrf_helper.php';

// PhpSpreadsheet (Composer)
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function yearFromDate($date): string {
    if (!$date) return '';
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    return date('Y', $ts);
}
function setTextCell($sheet, string $cell, $value): void {
    $sheet->setCellValueExplicit($cell, (string)$value, DataType::TYPE_STRING);
}

$errors = [];
$messages = [];

$skupinaId = $_GET['skupina_id'] ?? ($_POST['skupina_id'] ?? '');
$skupinaId = is_string($skupinaId) ? trim($skupinaId) : '';

/**
 * Šablona – dej ji na server sem:
 * /templates/prihlasovaci_tabulka_draha.xlsx
 */
$templatePath = __DIR__ . '/templates/prihlasovaci_tabulka_draha.xlsx';

// -------------------------
// Načti skupiny
// -------------------------
$skupiny = [];
try {
    $stmt = $pdo->query("SELECT id, nazev FROM skupiny ORDER BY poradi, nazev");
    $skupiny = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errors[] = "Chyba při načítání skupin: " . $e->getMessage();
}

// -------------------------
// Export (POST)
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = "Neplatny CSRF token.";
        $skupinaId = '';
    }
    if ($skupinaId === '' || !ctype_digit($skupinaId)) {
        $errors[] = "Neplatná skupina.";
    } else {
        $selectedIds = $_POST['sportovci'] ?? [];
        if (!is_array($selectedIds)) $selectedIds = [];

        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), fn($v) => $v > 0));

        if (count($selectedIds) === 0) {
            $errors[] = "Není vybrán žádný sportovec pro export.";
        } else {
            try {
                if (!file_exists($templatePath)) {
                    throw new Exception("Chybí šablona: " . $templatePath . " (nahraj ji na server do /templates/).");
                }

                // Načti sportovce (jen ti vybraní)
                $in = implode(',', array_fill(0, count($selectedIds), '?'));
                $sql = "
                    SELECT s.id, s.jmeno, s.prijmeni, s.email, s.category, s.uciid, s.narozeni, s.oddil
                    FROM sportovci s
                    WHERE s.id IN ($in)
                    ORDER BY s.prijmeni, s.jmeno
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($selectedIds);
                $sportovci = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Načti šablonu
                $spreadsheet = IOFactory::load($templatePath);
                $sheet = $spreadsheet->getSheet(0);

                /**
                 * Podle šablony, kterou jsi poslal:
                 * Header je na řádku 3
                 * Data začínají na řádku 5
                 * A: pořadí (v šabloně už bývá)
                 * B: ID UCI
                 * C: PŘÍJMENÍ
                 * D: Jméno
                 * E: Tým
                 * F: Národnost
                 * G: Kategorie
                 * H: Rok Nar.
                 */
                $startRow = 5;
                $r = $startRow;

                // (volitelné) vyčisti předchozí obsah v řádcích (B–H) – aby se nepletly staré exporty
                // projedeme rozumný rozsah, třeba 400 řádků
                for ($rr = $startRow; $rr < $startRow + 400; $rr++) {
                    foreach (['B','C','D','E','F','G','H'] as $col) {
                        $sheet->setCellValue($col.$rr, null);
                    }
                }

                foreach ($sportovci as $s) {
                    setTextCell($sheet, "B{$r}", $s['uciid'] ?? '');
                    setTextCell($sheet, "C{$r}", $s['prijmeni'] ?? '');
                    setTextCell($sheet, "D{$r}", $s['jmeno'] ?? '');
                    $sheet->setCellValue("E{$r}", (string)($s['oddil'] ?? ''));     // tým = oddíl (pokud chceš jinak, uprav)
                    $sheet->setCellValue("F{$r}", "");                              // národnost zatím prázdné
                    setTextCell($sheet, "E{$r}", $s['oddil'] ?? '');
                    setTextCell($sheet, "F{$r}", '');
                    setTextCell($sheet, "G{$r}", $s['category'] ?? '');
                    setTextCell($sheet, "H{$r}", yearFromDate($s['narozeni'] ?? null));
                    $r++;
                }

                // Druhý list s plnými údaji
                $exportSheet = $spreadsheet->getSheetByName('Export');
                if (!$exportSheet) {
                    $exportSheet = $spreadsheet->createSheet();
                    $exportSheet->setTitle('Export');
                } else {
                    // vyčisti
                    $exportSheet->removeRow(1, $exportSheet->getHighestRow());
                }

                $exportSheet->fromArray(
                    ['Příjmení', 'Jméno', 'Email', 'Kategorie', 'UCIID', 'Datum narození'],
                    null,
                    'A1'
                );

                $row2 = 2;
                foreach ($sportovci as $s) {
                    setTextCell($exportSheet, "A{$row2}", $s['prijmeni'] ?? '');
                    setTextCell($exportSheet, "B{$row2}", $s['jmeno'] ?? '');
                    setTextCell($exportSheet, "C{$row2}", $s['email'] ?? '');
                    setTextCell($exportSheet, "D{$row2}", $s['category'] ?? '');
                    setTextCell($exportSheet, "E{$row2}", $s['uciid'] ?? '');
                    setTextCell($exportSheet, "F{$row2}", $s['narozeni'] ?? '');
                    $row2++;
                }

                // Download
                $filename = "export_draha_" . date('Ymd_His') . ".xlsx";

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

// -------------------------
// Načti sportovce pro výpis (GET)
// -------------------------
$sportovci = [];
if ($skupinaId !== '' && ctype_digit($skupinaId)) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.jmeno, s.prijmeni, s.email, s.category, s.uciid, s.narozeni
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
    <title>Export – Dráha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        table { table-layout: fixed; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-ind { font-size: 12px; opacity: .7; margin-left: 4px; }

        .table-sm > :not(caption) > * > * { padding: 6px !important; }
        .col-check { width: 55px; min-width: 55px; max-width: 55px; text-align:center; }
        .col-lname { width: 200px; min-width: 200px; max-width: 200px; }
        .col-fname { width: 160px; min-width: 160px; max-width: 160px; }
        .col-born  { width: 120px; min-width: 120px; max-width: 120px; }
        .col-cat   { width: 180px; min-width: 180px; max-width: 180px; }

        td.ellipsis, th.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        input.form-check-input { margin:0 !important; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>

<div class="container mt-4">
    <h1>Export – Dráha (vyplnění šablony)</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" class="row g-3 mb-3">
        <div class="col-md-6">
            <label for="skupina_id" class="form-label">Skupina</label>
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
            <button type="submit" class="btn btn-primary w-100">Načíst</button>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="text-muted small">
                Šablona: <code><?= h(basename($templatePath)) ?></code><br>
                Umístění: <code>/templates/</code>
            </div>
        </div>
    </form>

    <?php if ($skupinaId === ''): ?>
        <div class="alert alert-info">Nejdřív vyber skupinu.</div>
    <?php else: ?>

        <?php if (empty($sportovci)): ?>
            <div class="alert alert-warning">Ve vybrané skupině nejsou žádní sportovci.</div>
        <?php else: ?>

            <form method="post" class="mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="skupina_id" value="<?= h($skupinaId) ?>">

                <div class="d-flex gap-2 align-items-center mb-2 flex-wrap"
                     style="position:sticky;top:56px;z-index:100;background:#fff;padding:.5rem 0;border-bottom:1px solid #dee2e6;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="checkAll">
                        <i class="bi bi-check-all me-1"></i>Zaškrtnout vše
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="uncheckAll">
                        <i class="bi bi-x-lg me-1"></i>Odškrtnout vše
                    </button>
                    <span class="text-muted small ms-2">Vybráno: <strong id="cntSelected">0</strong> / <?= count($sportovci) ?></span>

                    <div class="ms-auto">
                        <button type="submit" name="export" value="1" class="btn btn-success">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportovat vybrané do Excelu
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="expTable" class="table table-striped table-sm align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="col-check text-center">✓</th>
                                <th class="sortable col-lname ellipsis" data-sort="lname" title="Řadit dle příjmení">
                                    Příjmení <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-fname ellipsis" data-sort="fname" title="Řadit dle jména">
                                    Jméno <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-born" data-sort="born" title="Řadit dle narození (datum)">
                                    Narození <span class="sort-ind"></span>
                                </th>
                                <th class="sortable col-cat ellipsis" data-sort="cat" title="Řadit dle kategorie">
                                    Kategorie <span class="sort-ind"></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sportovci as $s): ?>
                            <?php
                                $sid = (int)$s['id'];
                                $lname = (string)($s['prijmeni'] ?? '');
                                $fname = (string)($s['jmeno'] ?? '');
                                $born  = (string)($s['narozeni'] ?? '');
                                $cat   = (string)($s['category'] ?? '');
                                $bornTs = $born ? strtotime($born) : 0;
                            ?>
                            <tr data-lname="<?= h(mb_strtolower($lname)) ?>"
                                data-fname="<?= h(mb_strtolower($fname)) ?>"
                                data-born="<?= h((string)$bornTs) ?>"
                                data-cat="<?= h(mb_strtolower($cat)) ?>">
                                <td class="col-check text-center">
                                    <input class="form-check-input rowcb" type="checkbox" name="sportovci[]" value="<?= $sid ?>">
                                </td>
                                <td class="col-lname ellipsis" title="<?= h($lname) ?>"><?= h($lname) ?></td>
                                <td class="col-fname ellipsis" title="<?= h($fname) ?>"><?= h($fname) ?></td>
                                <td class="col-born"><?= h($born) ?></td>
                                <td class="col-cat ellipsis" title="<?= h($cat) ?>"><?= h($cat) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-muted small">
                    Export vyplní šablonu: <strong>ID UCI, Příjmení, Jméno, Tým(oddíl), Kategorie, Rok narození</strong>.
                    Navíc přidá list <strong>Export</strong> s plnými sloupci včetně e-mailu a celého data narození.
                </div>
            </form>

        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function(){
    const table = document.getElementById('expTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('th.sortable');

    let currentSort = { key: null, dir: 1 };

    function setIndicators(activeTh, dir) {
        headers.forEach(th => {
            const ind = th.querySelector('.sort-ind');
            if (!ind) return;
            ind.textContent = (th === activeTh) ? (dir === 1 ? '▲' : '▼') : '';
        });
    }

    function sortRows(key, dir) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a,b) => {
            const va = a.getAttribute('data-'+key) || '';
            const vb = b.getAttribute('data-'+key) || '';

            if (key === 'born') {
                const na = parseInt(va || '0', 10);
                const nb = parseInt(vb || '0', 10);
                return (na - nb) * dir;
            }
            return va.localeCompare(vb, 'cs', { sensitivity:'base' }) * dir;
        });
        rows.forEach(r => tbody.appendChild(r));
    }

    headers.forEach(th => {
        th.addEventListener('click', () => {
            const key = th.getAttribute('data-sort');
            let dir = 1;
            if (currentSort.key === key) dir = -currentSort.dir;

            currentSort = { key, dir };
            sortRows(key, dir);
            setIndicators(th, dir);
        });
    });

    // Counter
    const cntEl = document.getElementById('cntSelected');
    function updateCount() {
        if (cntEl) cntEl.textContent = tbody.querySelectorAll('.rowcb:checked').length;
    }
    tbody.addEventListener('change', e => { if (e.target.classList.contains('rowcb')) updateCount(); });

    // select all / none
    const checkAllBtn = document.getElementById('checkAll');
    const uncheckAllBtn = document.getElementById('uncheckAll');

    function setAll(state) {
        document.querySelectorAll('.rowcb').forEach(cb => cb.checked = state);
        updateCount();
    }
    if (checkAllBtn) checkAllBtn.addEventListener('click', () => setAll(true));
    if (uncheckAllBtn) uncheckAllBtn.addEventListener('click', () => setAll(false));

    updateCount();
})();
</script>

</body>
</html>
