<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence')) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Chybí DB připojení ($pdo).');
}

$TABLE = 'sportovci';

/* ================== HELPERS ================== */

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validateSpreadsheetUpload(string $key, array &$errors): bool {
    if (empty($_FILES[$key]['name'])) {
        return true;
    }
    if (($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = "Soubor {$key} se nepodarilo nahrat.";
        return false;
    }
    $tmp = (string)($_FILES[$key]['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = "Soubor {$key} neni platny upload.";
        return false;
    }
    $ext = strtolower(pathinfo((string)$_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        $errors[] = "Soubor {$key} ma nepovolenou priponu.";
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);
    $allowed = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip',
    ];
    if (!in_array($mime, $allowed, true)) {
        $errors[] = "Soubor {$key} nema povoleny MIME typ.";
        return false;
    }
    return true;
}

function czDeaccent(string $s): string {
    static $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z',
    ];
    return strtr($s, $map);
}

function normalizeKey(string $jmeno, string $prijmeni): string {
    $s = trim($jmeno . ' ' . $prijmeni);
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = czDeaccent($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9 ]/', '', $s);
    return trim($s);
}

function generateHash(string $jmeno, string $prijmeni): string {
    return hash('sha256', uniqid($jmeno . $prijmeni, true));
}

function generateUciInternalCode(): string {
    return strtoupper(bin2hex(random_bytes(6)));
}

function normHeader(string $s): string {
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    $s = czDeaccent($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]/', '', $s);
    return $s;
}

function canonicalHeader(string $norm): string {
    static $map = [
        // Licence
        'licence' => 'licence',
        'cislicence' => 'licence',
        'cislolicence' => 'licence',
        'uciid' => 'uciid',
        'uciidcislo' => 'uciid',
        'role' => 'role',
        'disciplna' => 'disciplina',
        'disciplina' => 'disciplina',
        'kategorie' => 'kategorie',
        'rokplatnosti' => 'rokplatnosti',
        'oddl' => 'oddil',
        'oddil' => 'oddil',

        // KIS
        'jmeno' => 'jmeno',
        'firstname' => 'jmeno',
        'name' => 'jmeno',
        'prijmeni' => 'prijmeni',
        'lastname' => 'prijmeni',
        'surname' => 'prijmeni',
        'email' => 'email',
        'telefon' => 'telefon',
        'phone' => 'telefon',
        'datumnarozeni' => 'datumnarozeni',
        'narozeni' => 'datumnarozeni',
        'datum_narozeni' => 'datumnarozeni',
        'datumrozeni' => 'datumnarozeni',
    ];
    return $map[$norm] ?? $norm;
}

function parseLicenceName(string $cell): array {
    $cell = str_replace("\xC2\xA0", ' ', $cell);
    $cell = trim(preg_replace('/\s+/u', ' ', $cell));
    $parts = preg_split('/\s+/u', $cell);
    if (!$parts || count($parts) < 2) {
        return ['jmeno' => '', 'prijmeni' => $cell];
    }
    $jmeno = array_pop($parts);
    return ['jmeno' => $jmeno, 'prijmeni' => implode(' ', $parts)];
}

function isEmptyDb($v): bool {
    if ($v === null) return true;
    if (is_string($v) && trim($v) === '') return true;
    if (is_numeric($v) && (string)$v === '0') return true;
    return false;
}

/**
 * Normalizace data do YYYY-MM-DD (MySQL DATE).
 * - akceptuje: 18.04.2015 / 18.4.2015 / 18/04/2015 / 2015-04-18
 * - akceptuje Excel serial (číslo)
 */
function normalizeDateToMysql($value): ?string {
    if ($value === null) return null;

    // Excel serial date (int/float)
    if (is_numeric($value)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float)$value);
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    $s = trim((string)$value);
    if ($s === '') return null;

    // někdy Excel posílá "18.04.2015 00:00:00"
    $s = preg_replace('/\s+.*$/', '', $s);

    // yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }

    // dd.mm.yyyy nebo dd/mm/yyyy nebo dd-mm-yyyy
    if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        if (checkdate($mo, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        return null;
    }

    return null;
}

/* ================== XLSX ================== */

function findHeaderRow(Worksheet $sheet, array $needles, int $maxRows = 5000): ?int {
    $highestRow = (int)$sheet->getHighestRow();
    $highestCol = (string)$sheet->getHighestColumn();

    $needles = array_map(fn($x) => canonicalHeader(normHeader((string)$x)), $needles);

    for ($r = 1; $r <= min($highestRow, $maxRows); $r++) {
        $raw = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false);
        $row = $raw[0] ?? null;
        if (!is_array($row)) continue;

        $cells = [];
        foreach ($row as $v) {
            $nv = canonicalHeader(normHeader((string)$v));
            if ($nv !== '') $cells[] = $nv;
        }
        if (!$cells) continue;

        $ok = 0;
        foreach ($needles as $needle) {
            if ($needle === '') continue;
            foreach ($cells as $c) {
                if (str_contains($c, $needle)) { $ok++; break; }
            }
        }
        if ($ok >= count($needles)) return $r;
    }
    return null;
}

function readXlsxRowsAuto(string $path, array $needles, array &$meta = []): array {
    $spreadsheet = IOFactory::load($path);
    $meta = ['sheet' => null, 'header_row' => null, 'headers' => []];

    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $hr = findHeaderRow($sheet, $needles);
        if ($hr === null) continue;

        $highestRow = (int)$sheet->getHighestRow();
        $highestCol = (string)$sheet->getHighestColumn();

        $headersRaw = $sheet->rangeToArray("A{$hr}:{$highestCol}{$hr}", null, true, true, false);
        $headersRow = $headersRaw[0] ?? [];

        $canonHeaders = [];
        foreach ($headersRow as $i => $h) {
            $canonHeaders[$i] = canonicalHeader(normHeader((string)$h));
        }

        $meta['sheet'] = $sheet->getTitle();
        $meta['header_row'] = $hr;
        $meta['headers'] = $canonHeaders;

        $rows = [];
        for ($r = $hr + 1; $r <= $highestRow; $r++) {
            $dataRaw = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false);
            $data = $dataRaw[0] ?? null;
            if (!is_array($data)) continue;

            $nonEmpty = false;
            foreach ($data as $v) {
                if (trim((string)$v) !== '') { $nonEmpty = true; break; }
            }
            if (!$nonEmpty) continue;

            $row = [];
            foreach ($canonHeaders as $i => $key) {
                if ($key === '') continue;
                $row[$key] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    throw new RuntimeException('Nelze najít hlavičku podle: ' . implode(', ', $needles));
}

/* ================== DB ================== */

function tableDescribe(PDO $pdo, string $table): array {
    $out = [];
    foreach ($pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['Field']] = $r;
    }
    return $out;
}

function isNumericColumn(array $describeRow): bool {
    $type = strtolower((string)($describeRow['Type'] ?? ''));
    return (bool)preg_match('/\b(int|tinyint|smallint|mediumint|bigint|decimal|float|double)\b/', $type);
}

function loadDbIndex(PDO $pdo, string $table): array {
    $idx = [];
    foreach ($pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = normalizeKey((string)($r['jmeno'] ?? ''), (string)($r['prijmeni'] ?? ''));
        if ($k !== '') $idx[$k][] = $r;
    }
    return $idx;
}

function insertRow(PDO $pdo, string $table, array $row): void {
    $cols = [];
    $ph = [];
    $params = [];

    foreach ($row as $k => $v) {
        $cols[] = "`$k`";
        $ph[] = ":$k";
        $params[":$k"] = $v;
    }

    $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
    $st = $pdo->prepare($sql);
    $st->execute($params);
}

function updateRow(PDO $pdo, string $table, int $id, array $changes): void {
    $sets = [];
    $params = [':id' => $id];

    foreach ($changes as $col => $val) {
        $sets[] = "`$col` = :$col";
        $params[":$col"] = $val;
    }

    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `id` = :id LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
}

/* ================== BUILD IMPORT ================== */

function buildPeople(array $licRows, array $kisRows, array &$stats): array {
    $people = [];
    $importDup = [];

    $stats = [
        'lic_rows_total' => count($licRows),
        'kis_rows_total' => count($kisRows),
        'lic_used' => 0,
        'lic_skipped_no_name' => 0,
        'kis_used' => 0,
        'kis_skipped_no_name' => 0,
    ];

    $touch = function(string $k) use (&$importDup) {
        $importDup[$k] = ($importDup[$k] ?? 0) + 1;
    };

    foreach ($licRows as $r) {
        $licCell = trim((string)($r['licence'] ?? ''));
        if ($licCell === '') { $stats['lic_skipped_no_name']++; continue; }

        $n = parseLicenceName($licCell);
        if (!$n['jmeno'] || !$n['prijmeni']) { $stats['lic_skipped_no_name']++; continue; }

        $k = normalizeKey($n['jmeno'], $n['prijmeni']);
        if ($k === '') { $stats['lic_skipped_no_name']++; continue; }

        $touch($k);

        $people[$k] ??= ['jmeno' => $n['jmeno'], 'prijmeni' => $n['prijmeni'], 'data' => []];
        $people[$k]['data']['licence'] = $r;
        $stats['lic_used']++;
    }

    foreach ($kisRows as $r) {
        $jmeno = trim((string)($r['jmeno'] ?? ''));
        $prijmeni = trim((string)($r['prijmeni'] ?? ''));
        if ($jmeno === '' || $prijmeni === '') { $stats['kis_skipped_no_name']++; continue; }

        $k = normalizeKey($jmeno, $prijmeni);
        if ($k === '') { $stats['kis_skipped_no_name']++; continue; }

        $touch($k);

        $people[$k] ??= ['jmeno' => $jmeno, 'prijmeni' => $prijmeni, 'data' => []];
        $people[$k]['data']['kis'] = $r;
        $stats['kis_used']++;
    }

    return [$people, $importDup];
}

/* ================== REQUEST ================== */

$errors = [];
$report = [
    'meta' => [],
    'stats' => [],
    'people_total' => 0,
    'added' => 0,
    'updated' => 0,
    'skipped' => 0,
    'added_rows' => [],
    'updated_rows' => [],
    'skipped_rows' => [],
    'invalid_dates' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dryRun = isset($_POST['dry_run']);
    $overwrite = isset($_POST['overwrite']);

    try {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Neplatny CSRF token.');
        }
        if (!validateSpreadsheetUpload('licence', $errors) || !validateSpreadsheetUpload('kis', $errors)) {
            throw new RuntimeException('Upload XLS souboru neprosel validaci.');
        }

        $licMeta = [];
        $kisMeta = [];

        $licRows = !empty($_FILES['licence']['name'])
            ? readXlsxRowsAuto($_FILES['licence']['tmp_name'], ['licenc'], $licMeta)
            : [];

        $kisRows = !empty($_FILES['kis']['name'])
            ? readXlsxRowsAuto($_FILES['kis']['tmp_name'], ['jmeno','prijmen'], $kisMeta)
            : [];

        $report['meta'] = ['licence' => $licMeta, 'kis' => $kisMeta];

        $stats = [];
        [$people, $importDup] = buildPeople($licRows, $kisRows, $stats);
        $report['stats'] = $stats;
        $report['people_total'] = count($people);

        $describe = tableDescribe($pdo, $TABLE);
        $dbIndex  = loadDbIndex($pdo, $TABLE);

        $uciIsNumeric = isset($describe['uci']) ? isNumericColumn($describe['uci']) : false;

        if (!$dryRun) $pdo->beginTransaction();

        foreach ($people as $k => $p) {

            if (($importDup[$k] ?? 0) > 1) {
                $report['skipped']++;
                $report['skipped_rows'][] = [
                    'name' => $p['jmeno'].' '.$p['prijmeni'],
                    'reason' => 'Duplicitní shoda v importu (po normalizaci jména)',
                ];
                continue;
            }

            $rows = $dbIndex[$k] ?? [];

            if (count($rows) > 1) {
                $report['skipped']++;
                $report['skipped_rows'][] = [
                    'name' => $p['jmeno'].' '.$p['prijmeni'],
                    'reason' => 'Více shod v DB pro stejné jméno+příjmení',
                    'db_matches' => count($rows),
                ];
                continue;
            }

            $lic = $p['data']['licence'] ?? [];
            $kis = $p['data']['kis'] ?? [];

            $candidate = [];

            // UCI ID -> uciid
            if (isset($describe['uciid']) && isset($lic['uciid']) && trim((string)$lic['uciid']) !== '') {
                $candidate['uciid'] = (string)$lic['uciid'];
            }

            if (isset($describe['oddil']) && isset($lic['oddil']) && trim((string)$lic['oddil']) !== '') {
                $candidate['oddil'] = (string)$lic['oddil'];
            }

            if (isset($describe['category']) && isset($lic['kategorie']) && trim((string)$lic['kategorie']) !== '') {
                $candidate['category'] = (string)$lic['kategorie'];
            }

            if (isset($describe['email']) && isset($kis['email']) && trim((string)$kis['email']) !== '') {
                $candidate['email'] = (string)$kis['email'];
            }
            if (isset($describe['telefon']) && isset($kis['telefon']) && trim((string)$kis['telefon']) !== '') {
                $candidate['telefon'] = (string)$kis['telefon'];
            }

            // narozeni – normalizace
            if (isset($describe['narozeni']) && isset($kis['datumnarozeni'])) {
                $mysqlDate = normalizeDateToMysql($kis['datumnarozeni']);
                if ($mysqlDate !== null) {
                    $candidate['narozeni'] = $mysqlDate;
                } elseif (trim((string)$kis['datumnarozeni']) !== '') {
                    $report['invalid_dates'][] = [
                        'name' => $p['jmeno'].' '.$p['prijmeni'],
                        'raw' => $kis['datumnarozeni'],
                    ];
                }
            }

            // ADD
            if (count($rows) === 0) {
                $row = [
                    'jmeno' => $p['jmeno'],
                    'prijmeni' => $p['prijmeni'],
                ];

                if (isset($describe['hash'])) {
                    $row['hash'] = generateHash($p['jmeno'], $p['prijmeni']);
                }
                if (isset($describe['uci'])) {
                    $row['uci'] = $uciIsNumeric ? 0 : generateUciInternalCode();
                }

                foreach ($candidate as $col => $val) $row[$col] = $val;

                if (!$dryRun) insertRow($pdo, $TABLE, $row);

                $report['added']++;
                $report['added_rows'][] = ['name' => $p['jmeno'].' '.$p['prijmeni'], 'insert' => $row];
                continue;
            }

            // UPDATE
            $dbRow = $rows[0];
            $id = (int)($dbRow['id'] ?? 0);
            if ($id <= 0) {
                $report['skipped']++;
                $report['skipped_rows'][] = [
                    'name' => $p['jmeno'].' '.$p['prijmeni'],
                    'reason' => 'Chybí id v DB řádku',
                ];
                continue;
            }

            $changes = [];
            foreach ($candidate as $col => $val) {
                $old = $dbRow[$col] ?? null;

                if ($overwrite) {
                    if ((string)$old !== (string)$val) $changes[$col] = $val;
                } else {
                    if (isEmptyDb($old)) $changes[$col] = $val;
                }
            }

            if (!$changes) {
                $report['skipped']++;
                $report['skipped_rows'][] = [
                    'name' => $p['jmeno'].' '.$p['prijmeni'],
                    'reason' => 'Bez změn',
                ];
                continue;
            }

            if (!$dryRun) updateRow($pdo, $TABLE, $id, $changes);

            $report['updated']++;
            $report['updated_rows'][] = [
                'name' => $p['jmeno'].' '.$p['prijmeni'],
                'id' => $id,
                'changes' => $changes,
            ];
        }

        if (!$dryRun) $pdo->commit();

    } catch (Throwable $e) {
        if (!$dryRun && $pdo->inTransaction()) $pdo->rollBack();
        $errors[] = "Chyba při zpracování: " . $e->getMessage();
    }
}

?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<title>Synchronizace sportovců</title>
<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:1200px;margin:24px auto;padding:0 12px;}
    .box{border:1px solid #ddd;border-radius:12px;padding:14px;margin:12px 0;background:#fff;}
    pre{white-space:pre-wrap;background:#f6f6f6;padding:12px;border-radius:10px;border:1px solid #ddd;overflow:auto;}
    .err{color:#b00020}
    .muted{color:#666}
</style>
</head>
<body>

<h1>Synchronizace sportovců</h1>

<form method="post" enctype="multipart/form-data" class="box">
    <?= csrf_field() ?>
    <div>Licence: <input type="file" name="licence" accept=".xlsx,.xls"></div>
    <div style="margin-top:10px">KIS: <input type="file" name="kis" accept=".xlsx,.xls"></div>
    <div style="margin-top:12px">
        <label><input type="checkbox" name="dry_run" <?= isset($_POST['dry_run'])?'checked':''; ?>> Dry-run (nezapisovat do DB)</label>
        &nbsp;&nbsp;
        <label><input type="checkbox" name="overwrite" <?= isset($_POST['overwrite'])?'checked':''; ?>> Overwrite</label>
    </div>
    <div style="margin-top:12px"><button type="submit">Spustit</button></div>
    <div class="muted" style="margin-top:10px">Datum narození se normalizuje do <code>YYYY-MM-DD</code>. Neplatné se neukládá a vypíše do reportu.</div>
</form>

<?php if ($errors): ?>
<div class="box err">
    <b>Chyby:</b>
    <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="box">
    <b>Souhrn</b><br>
    People total: <b><?= (int)$report['people_total'] ?></b><br><br>
    Přidáno: <b><?= (int)$report['added'] ?></b> •
    Aktualizováno: <b><?= (int)$report['updated'] ?></b> •
    Přeskočeno: <b><?= (int)$report['skipped'] ?></b>
</div>

<div class="box"><b>Neplatná data narození (raw)</b><pre><?= h(print_r($report['invalid_dates'], true)) ?></pre></div>
<div class="box"><b>Meta</b><pre><?= h(print_r($report['meta'], true)) ?></pre></div>
<div class="box"><b>Stats</b><pre><?= h(print_r($report['stats'], true)) ?></pre></div>
<div class="box"><b>Přidáno</b><pre><?= h(print_r($report['added_rows'], true)) ?></pre></div>
<div class="box"><b>Aktualizováno</b><pre><?= h(print_r($report['updated_rows'], true)) ?></pre></div>
<div class="box"><b>Přeskočeno</b><pre><?= h(print_r($report['skipped_rows'], true)) ?></pre></div>

</body>
</html>
