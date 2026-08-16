<?php
require_once __DIR__ . '/includes/session_security.php';
app_session_start();
require_once __DIR__ . '/includes/funkce.php';
if (!isset($_SESSION['trener_id']) || !canAccess('sync_evidence')) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'csrf_helper.php';
require 'vendor/autoload.php';
require_once __DIR__ . '/includes/kis_sync_lib.php';
require_once __DIR__ . '/includes/kis_import_run_lib.php';
require_once __DIR__ . '/includes/sportovec_status_lib.php';
require_once __DIR__ . '/includes/sportovec_history_lib.php';
require_once __DIR__ . '/includes/public_profile_token.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function validateKisUpload(string $field, string $label, array &$errors): bool {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Nahrajte XLSX soubor: ' . $label . '.';
        return false;
    }
    $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'Soubor ' . $label . ' neni platny upload.';
        return false;
    }
    $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        $errors[] = 'Soubor ' . $label . ' ma nepovolenou priponu.';
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string)finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);
    $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip',
    ];
    if (!in_array($mimeType, $allowedMimes, true)) {
        $errors[] = 'Soubor ' . $label . ' nema povoleny MIME typ.';
        return false;
    }
    return true;
}

/* ================== HELPERS (reusable from sync.php) ================== */

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
    return public_profile_token_generate();
}

function normalizeDateToMysql($value): ?string {
    if ($value === null) return null;
    if (is_numeric($value)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float)$value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
    $s = trim((string)$value);
    if ($s === '') return null;
    $s = preg_replace('/\s+.*$/', '', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
    if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $s, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
        if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    return null;
}

/**
 * Parse soupisky string into individual soupiska items.
 * Splits by "(2025/2026), " pattern to handle embedded commas.
 */
function parseSoupisky(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];

    // Split by season suffix followed by comma: "(YYYY/YYYY), "
    $parts = preg_split('/(\(\d{4}\/\d{4}\))\s*,\s*/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

    $result = [];
    $current = '';
    for ($i = 0; $i < count($parts); $i++) {
        if (preg_match('/^\(\d{4}\/\d{4}\)$/', $parts[$i])) {
            $current .= $parts[$i];
            $current = trim($current);
            if ($current !== '') $result[] = $current;
            $current = '';
        } else {
            $current .= $parts[$i];
        }
    }
    $current = trim($current);
    if ($current !== '') $result[] = $current;

    return $result;
}

/**
 * Try to auto-match a soupiska text to a podskupina by name similarity.
 */
function autoMatchSoupiska(string $soupiska, array $podskupiny): ?array {
    $lower = mb_strtolower(czDeaccent($soupiska), 'UTF-8');
    $bestMatch = null;
    $bestLen = 0;

    foreach ($podskupiny as $ps) {
        $psName = mb_strtolower(czDeaccent($ps['nazev']), 'UTF-8');
        if (mb_strlen($psName) >= 3 && str_contains($lower, $psName) && mb_strlen($psName) > $bestLen) {
            $bestMatch = $ps;
            $bestLen = mb_strlen($psName);
        }
    }

    return $bestMatch;
}

/* ================== DETERMINE STEP ================== */

$step = 1;
$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Neplatný CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'upload') $step = 1; // Process upload → go to step 2
        elseif ($action === 'save_mapping') $step = 2; // Process mapping → go to step 3
        elseif ($action === 'execute') $step = 4; // Execute sync
    }
}

// If we have session data but no POST, check what step we're at
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getStep = isset($_GET['step']) ? (int)$_GET['step'] : 0;
    if ($getStep === 3 && isset($_SESSION['sync_data'])) $step = 3;
    elseif ($getStep === 2 && isset($_SESSION['sync_data'])) $step = 2;
    else $step = 1;
}

/* ================== STEP 1: UPLOAD & PARSE ================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload' && empty($errors)) {
    $requiredFiles = [
        'users_xlsx' => 'uzivatele',
        'payments_xlsx' => 'platby',
        'rosters_xlsx' => 'soupisky',
    ];

    foreach ($requiredFiles as $field => $label) {
        validateKisUpload($field, $label, $errors);
    }

    if (empty($errors)) {
        try {
            $payload = kis_build_import(
                $_FILES['users_xlsx']['tmp_name'],
                $_FILES['payments_xlsx']['tmp_name'],
                $_FILES['rosters_xlsx']['tmp_name']
            );

            $_SESSION['sync_data'] = $payload['people'];
            $_SESSION['sync_soupisky'] = $payload['soupisky'];
            $_SESSION['sync_soupisky_counts'] = $payload['soupisky_counts'];
            $_SESSION['sync_meta'] = $payload['meta'];
            $_SESSION['sync_warnings'] = $payload['warnings'];
            $artifactIds = [];
            foreach (['users' => 'users_xlsx', 'payments' => 'payments_xlsx', 'rosters' => 'rosters_xlsx'] as $sourceKind => $field) {
                $artifact = kisSourceArchive(
                    $pdo,
                    (string)$_FILES[$field]['tmp_name'],
                    $sourceKind,
                    KIS_IMPORT_FIELD_CONTRACT,
                    kisSourceConfiguredArchiveDirectory(),
                    isset($_SESSION['trener_id']) ? (int)$_SESSION['trener_id'] : null
                );
                $artifactIds[$sourceKind] = (int)$artifact['id'];
            }
            $_SESSION['sync_run_id'] = kisImportCreateRun(
                $pdo,
                $payload['people'],
                $payload['meta'],
                $payload['warnings'],
                [
                    'users' => $_FILES['users_xlsx']['name'] ?? null,
                    'payments' => $_FILES['payments_xlsx']['name'] ?? null,
                    'rosters' => $_FILES['rosters_xlsx']['name'] ?? null,
                ],
                isset($_SESSION['trener_id']) ? (int)$_SESSION['trener_id'] : null,
                $artifactIds
            );

            $step = 2;
            $messages[] = 'Nacteno ' . count($payload['people']) . ' osob a ' . count($payload['soupisky']) . ' unikatnich soupisek. Import run #' . (int)$_SESSION['sync_run_id'] . ' je ulozen jako preview.';
        } catch (\Throwable $e) {
            $errors[] = 'Chyba pri cteni XLSX: ' . $e->getMessage();
            $step = 1;
        }
    } else {
        $step = 1;
    }
}

if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload' && empty($errors)) {
    if (empty($_FILES['xlsx']['name']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Nahrajte platný XLSX soubor.';
        $step = 1;
    } else {
        // Validate MIME type
        $tmpPath = $_FILES['xlsx']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip', // XLSX is a ZIP file
            'application/octet-stream',
        ];
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'Neplatný typ souboru. Nahrajte XLSX soubor.';
            $step = 1;
        } else {
        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int)$sheet->getHighestRow();
            $highestCol = (string)$sheet->getHighestColumn();

            // Find header row (look for Jméno, Příjmení)
            $headerRow = null;
            $headerMap = [];
            for ($r = 1; $r <= min($highestRow, 10); $r++) {
                $rowData = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0] ?? [];
                foreach ($rowData as $ci => $cell) {
                    $norm = mb_strtolower(trim((string)$cell), 'UTF-8');
                    if (in_array($norm, ['jméno', 'jmeno', 'firstname'])) {
                        $headerRow = $r;
                        break 2;
                    }
                }
            }

            if ($headerRow === null) {
                $errors[] = 'Nelze najít hlavičku (sloupec "Jméno") v prvních 10 řádcích.';
                $step = 1;
            } else {
                // Map header columns
                $headerData = $sheet->rangeToArray("A{$headerRow}:{$highestCol}{$headerRow}", null, true, true, false)[0] ?? [];
                $colMap = [];
                $headerAliases = [
                    'jméno' => 'jmeno', 'jmeno' => 'jmeno', 'firstname' => 'jmeno',
                    'příjmení' => 'prijmeni', 'prijmeni' => 'prijmeni', 'lastname' => 'prijmeni', 'surname' => 'prijmeni',
                    'datum narození' => 'narozeni', 'datum_narozeni' => 'narozeni', 'narozeni' => 'narozeni', 'datum narozeni' => 'narozeni',
                    'soupisky' => 'soupisky',
                    'trvalá adresa - ulice' => 'adresa_ulice', 'ulice' => 'adresa_ulice',
                    'trvalá adresa - č.p.' => 'adresa_cp', 'č.p.' => 'adresa_cp', 'cp' => 'adresa_cp',
                    'trvalá adresa - č.o.' => 'adresa_co', 'č.o.' => 'adresa_co', 'co' => 'adresa_co',
                    'trvalá adresa - obec' => 'adresa_obec', 'obec' => 'adresa_obec',
                    'trvalá adresa - psč' => 'adresa_psc', 'psč' => 'adresa_psc', 'psc' => 'adresa_psc',
                    'rodné číslo' => 'rc', 'rodne cislo' => 'rc', 'rc' => 'rc',
                    'e-mail' => 'email', 'email' => 'email',
                    'telefon' => 'telefon', 'phone' => 'telefon',
                ];

                foreach ($headerData as $ci => $cell) {
                    $norm = mb_strtolower(trim((string)$cell), 'UTF-8');
                    if (isset($headerAliases[$norm])) {
                        $colMap[$ci] = $headerAliases[$norm];
                    }
                }

                // Read data rows
                $importData = [];
                $allSoupisky = [];

                for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
                    $rowData = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0] ?? [];
                    $row = [];
                    foreach ($colMap as $ci => $key) {
                        $row[$key] = $rowData[$ci] ?? null;
                    }

                    // Skip completely empty rows
                    $jmeno = trim((string)($row['jmeno'] ?? ''));
                    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
                    if ($jmeno === '' && $prijmeni === '') continue;

                    // Normalize date
                    if (isset($row['narozeni'])) {
                        $row['narozeni'] = normalizeDateToMysql($row['narozeni']);
                    }

                    // Parse soupisky
                    $rawSoupisky = trim((string)($row['soupisky'] ?? ''));
                    $row['_soupisky_parsed'] = parseSoupisky($rawSoupisky);
                    foreach ($row['_soupisky_parsed'] as $sp) {
                        $allSoupisky[$sp] = ($allSoupisky[$sp] ?? 0) + 1;
                    }

                    // Clean string fields
                    foreach (['jmeno','prijmeni','email','telefon','rc','adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc'] as $f) {
                        if (isset($row[$f])) $row[$f] = trim((string)$row[$f]);
                    }

                    $importData[] = $row;
                }

                $_SESSION['sync_data'] = $importData;
                $_SESSION['sync_soupisky'] = array_keys($allSoupisky);
                $_SESSION['sync_soupisky_counts'] = $allSoupisky;
                $step = 2;
                $messages[] = "Nahráno " . count($importData) . " sportovců, nalezeno " . count($allSoupisky) . " unikátních soupisek.";
            }
        } catch (\Throwable $e) {
            $errors[] = 'Chyba při čtení XLSX: ' . $e->getMessage();
            $step = 1;
        }
        } // end MIME check
    }
}

/* ================== STEP 2: SAVE MAPPING ================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_mapping' && empty($errors)) {
    if (!isset($_SESSION['sync_soupisky'])) {
        $errors[] = 'Chybí data soupisek. Nahrajte soubor znovu.';
        $step = 1;
    } else {
        $mappings = $_POST['mapping'] ?? [];
        $savedCount = 0;

        foreach ($_SESSION['sync_soupisky'] as $soupiska) {
            $key = md5($soupiska);
            $skupinaId = !empty($mappings[$key]['skupina_id']) ? (int)$mappings[$key]['skupina_id'] : null;
            $podskupinaId = !empty($mappings[$key]['podskupina_id']) ? (int)$mappings[$key]['podskupina_id'] : null;

            // Upsert into soupiska_mapping
            $stmt = $pdo->prepare("
                INSERT INTO soupiska_mapping (soupiska_text, skupina_id, podskupina_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE skupina_id = VALUES(skupina_id), podskupina_id = VALUES(podskupina_id)
            ");
            $stmt->execute([$soupiska, $skupinaId, $podskupinaId]);
            $savedCount++;
        }

        $_SESSION['sync_mapping_saved'] = true;
        $messages[] = "Uloženo {$savedCount} mapování soupisek.";
        $step = 3;
    }
}

/* ================== STEP 3: PREVIEW (DRY RUN) ================== */

$preview = null;
if ($step === 3 && isset($_SESSION['sync_data'])) {
    $preview = buildPreview($pdo, $_SESSION['sync_data']);
}

function buildPreview(PDO $pdo, array $importData): array {
    // Load all sportovci from DB
    $allDb = $pdo->query("SELECT * FROM sportovci")->fetchAll(PDO::FETCH_ASSOC);
    $dbByKey = [];      // normalizedKey+narozeni => row
    $dbByName = [];     // normalizedKey => [rows]
    $dbIds = [];        // all DB ids

    foreach ($allDb as $row) {
        $id = (int)$row['id'];
        $dbIds[$id] = $row;
        $nameKey = normalizeKey((string)$row['jmeno'], (string)$row['prijmeni']);
        $fullKey = $nameKey . '|' . ($row['narozeni'] ?? '');
        $dbByKey[$fullKey] = $row;
        $dbByName[$nameKey][] = $row;
    }

    // Load soupiska mappings
    $mappings = [];
    $mapRows = $pdo->query("SELECT * FROM soupiska_mapping")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mapRows as $mr) {
        $mappings[$mr['soupiska_text']] = [
            'skupina_id' => $mr['skupina_id'] ? (int)$mr['skupina_id'] : null,
            'podskupina_id' => $mr['podskupina_id'] ? (int)$mr['podskupina_id'] : null,
        ];
    }

    $result = [
        'new' => [],
        'new_inactive' => [],
        'update' => [],
        'archive' => [],
        'unchanged' => [],
        'warnings' => [],
        'matched_db_ids' => [],
        'not_imported_count' => 0,
    ];

    // Match Excel rows to DB
    foreach ($importData as $idx => $row) {
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        $narozeni = $row['narozeni'] ?? null;

        if ($jmeno === '' && $prijmeni === '') continue;

        $nameKey = normalizeKey($jmeno, $prijmeni);
        $fullKey = $nameKey . '|' . ($narozeni ?? '');

        $dbRow = null;
        $matchType = '';

        // 1. Try exact match: name + birth date
        if ($narozeni && isset($dbByKey[$fullKey])) {
            $dbRow = $dbByKey[$fullKey];
            $matchType = 'exact';
        }
        // 2. Fallback: name only (if exactly 1 match)
        elseif (isset($dbByName[$nameKey]) && count($dbByName[$nameKey]) === 1) {
            $dbRow = $dbByName[$nameKey][0];
            $matchType = $narozeni ? 'name_only' : 'name_no_birth';
            if ($matchType === 'name_only') {
                $result['warnings'][] = [
                    'name' => "$prijmeni $jmeno",
                    'msg' => "Shoda jen dle jména (narozeni v Excelu: " . ($narozeni ?? '—') . ", v DB: " . ($dbRow['narozeni'] ?? '—') . ")",
                ];
            }
        }
        // 3. Name matches multiple => warning, treat as new
        elseif (isset($dbByName[$nameKey]) && count($dbByName[$nameKey]) > 1) {
            $result['warnings'][] = [
                'name' => "$prijmeni $jmeno",
                'msg' => "Více shod v DB (" . count($dbByName[$nameKey]) . "), nelze jednoznačně přiřadit. Bude přeskočen.",
            ];
            continue;
        }

        // Resolve soupisky for this row
        $rowSkupiny = [];
        $rowPodskupiny = [];
        foreach ($row['_soupisky_parsed'] ?? [] as $sp) {
            $m = $mappings[$sp] ?? null;
            if ($m) {
                if ($m['skupina_id']) $rowSkupiny[$m['skupina_id']] = true;
                if ($m['podskupina_id']) $rowPodskupiny[$m['podskupina_id']] = true;
            }
        }

        if ($dbRow) {
            // UPDATE case
            $id = (int)$dbRow['id'];
            $result['matched_db_ids'][$id] = true;

            $changes = [];
            $fields = ['email','rc','telefon','adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc'];
            foreach ($fields as $f) {
                $newVal = $row[$f] ?? '';
                $oldVal = $dbRow[$f] ?? '';
                if ((string)$newVal !== '' && (string)$newVal !== (string)$oldVal) {
                    $changes[$f] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            foreach (['kis_aktivni','kis_platebne_aktivni','kis_neuhrazeno','kis_posledni_uhrada','kis_soupisky'] as $f) {
                $newVal = $row[$f] ?? null;
                $oldVal = $dbRow[$f] ?? null;
                if ((string)$newVal !== (string)$oldVal) {
                    $changes[$f] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            // Update narozeni if DB is empty and Excel has it
            if ($narozeni && empty($dbRow['narozeni'])) {
                $changes['narozeni'] = ['old' => $dbRow['narozeni'] ?? '', 'new' => $narozeni];
            }

            if (!empty($changes) || !empty($rowSkupiny) || !empty($rowPodskupiny)) {
                $result['update'][] = [
                    'db_id' => $id,
                    'name' => "$prijmeni $jmeno",
                    'narozeni' => $narozeni,
                    'changes' => $changes,
                    'skupiny' => array_keys($rowSkupiny),
                    'podskupiny' => array_keys($rowPodskupiny),
                    'match_type' => $matchType,
                ];
            } else {
                $result['unchanged'][] = [
                    'db_id' => $id,
                    'name' => "$prijmeni $jmeno",
                ];
            }
        } else {
            // NEW case — neaktivní historické osoby (bez soupisky, bez platby, bez dluhu)
            // oddělujeme, aby import nezaplavil DB stovkami mrtvých záznamů z KIS
            $isInactive = (int)($row['kis_aktivni'] ?? 0) === 0
                && (int)($row['kis_platebne_aktivni'] ?? 0) === 0
                && (float)($row['kis_neuhrazeno'] ?? 0) <= 0;
            $result[$isInactive ? 'new_inactive' : 'new'][] = [
                'name' => "$prijmeni $jmeno",
                'narozeni' => $narozeni,
                'email' => $row['email'] ?? '',
                'kis_aktivni' => $row['kis_aktivni'] ?? 0,
                'kis_platebne_aktivni' => $row['kis_platebne_aktivni'] ?? 0,
                'kis_neuhrazeno' => $row['kis_neuhrazeno'] ?? 0,
                'skupiny' => array_keys($rowSkupiny),
                'podskupiny' => array_keys($rowPodskupiny),
                'row' => $row,
            ];
        }
    }

    // Sportovci chybejici v KIS importu se pouze pocitaji. Automaticka archivace je
    // pro tri oddelene KIS exporty prilis destruktivni.
    foreach ($dbIds as $id => $dbRow) {
        if (!isset($result['matched_db_ids'][$id])) {
            $result['not_imported_count']++;
        }
    }

    return $result;
}

/* ================== STEP 4: EXECUTE SYNC ================== */

$execReport = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'execute' && empty($errors)) {
    if (!isset($_SESSION['sync_data'])) {
        $errors[] = 'Chybí data. Nahrajte soubor znovu.';
        $step = 1;
    } else {
        $currentRunId = (int)($_SESSION['sync_run_id'] ?? 0);
        $currentRun = $currentRunId > 0 ? kisImportRunDetail($pdo, $currentRunId)['run'] : null;
        $fieldContract = is_array($currentRun) ? kisFieldContractStoredReport($currentRun) : null;
        if ($fieldContract === null
            || ($fieldContract['status'] ?? null) !== 'ready_for_parity'
            || (int)($fieldContract['summary']['total_blockers'] ?? -1) !== 0) {
            $errors[] = 'Synchronizaci nelze provést: exporty nemají úplný a stabilní kontrakt KIS ID.';
            $step = 3;
        } else {
            $vcetneNeaktivnich = !empty($_POST['vcetne_neaktivnich']);
            $execReport = executeSync($pdo, $_SESSION['sync_data'], $vcetneNeaktivnich);
            $step = 4;
        }
    }
}

function executeSync(PDO $pdo, array $importData, bool $vcetneNeaktivnich = false): array {
    $report = ['added' => 0, 'updated' => 0, 'archived' => 0, 'unchanged' => 0, 'skipped_inactive' => 0, 'errors' => []];

    // Load soupiska mappings
    $mappings = [];
    $mapRows = $pdo->query("SELECT * FROM soupiska_mapping")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mapRows as $mr) {
        $mappings[$mr['soupiska_text']] = [
            'skupina_id' => $mr['skupina_id'] ? (int)$mr['skupina_id'] : null,
            'podskupina_id' => $mr['podskupina_id'] ? (int)$mr['podskupina_id'] : null,
        ];
    }

    // Load all DB sportovci
    $allDb = $pdo->query("SELECT * FROM sportovci")->fetchAll(PDO::FETCH_ASSOC);
    $dbByKey = [];
    $dbByName = [];
    $dbIds = [];
    foreach ($allDb as $row) {
        $id = (int)$row['id'];
        $dbIds[$id] = $row;
        $nameKey = normalizeKey((string)$row['jmeno'], (string)$row['prijmeni']);
        $fullKey = $nameKey . '|' . ($row['narozeni'] ?? '');
        $dbByKey[$fullKey] = $row;
        $dbByName[$nameKey][] = $row;
    }

    // Ensure Archiv group exists
    $archStmt = $pdo->prepare("SELECT id FROM skupiny WHERE LOWER(nazev) = 'archiv' LIMIT 1");
    $archStmt->execute();
    $archivId = (int)$archStmt->fetchColumn();
    if (!$archivId) {
        $pdo->exec("INSERT INTO skupiny (nazev, poradi) VALUES ('Archiv', 9999)");
        $archivId = (int)$pdo->lastInsertId();
    }

    $matchedDbIds = [];

    $pdo->beginTransaction();
    try {
        foreach ($importData as $row) {
            $jmeno = trim((string)($row['jmeno'] ?? ''));
            $prijmeni = trim((string)($row['prijmeni'] ?? ''));
            $narozeni = $row['narozeni'] ?? null;

            if ($jmeno === '' && $prijmeni === '') continue;

            $match = kisMatchResolve($pdo, $row);
            $dbRow = null;
            if ($match['status'] === 'matched' && !empty($match['sportovec_id'])) {
                $stMatch = $pdo->prepare("SELECT * FROM sportovci WHERE id = ? LIMIT 1");
                $stMatch->execute([(int)$match['sportovec_id']]);
                $dbRow = $stMatch->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif (in_array($match['status'], ['ambiguous', 'conflict', 'ignored'], true)) {
                $report['errors'][] = "$prijmeni $jmeno: preskoceno, vyzaduje rucni potvrzeni (" . $match['reason'] . ")";
                continue;
            }

            // Resolve soupisky
            $rowSkupiny = [];
            $rowPodskupiny = [];
            foreach ($row['_soupisky_parsed'] ?? [] as $sp) {
                $m = $mappings[$sp] ?? null;
                if ($m) {
                    if ($m['skupina_id']) $rowSkupiny[$m['skupina_id']] = true;
                    if ($m['podskupina_id']) $rowPodskupiny[$m['podskupina_id']] = true;
                }
            }

            if ($dbRow) {
                // UPDATE
                $id = (int)$dbRow['id'];
                $matchedDbIds[$id] = true;

                $updates = [];
                $params = [];
                $fields = ['email','rc','telefon','adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc'];
                foreach ($fields as $f) {
                    $newVal = $row[$f] ?? '';
                    if ((string)$newVal !== '' && (string)$newVal !== (string)($dbRow[$f] ?? '')) {
                        $updates[] = "`$f` = ?";
                        $params[] = $newVal;
                    }
                }
                if ($narozeni && empty($dbRow['narozeni'])) {
                    $updates[] = "`narozeni` = ?";
                        $params[] = $narozeni;
                }
                foreach (['kis_aktivni','kis_platebne_aktivni','kis_neuhrazeno','kis_posledni_uhrada','kis_soupisky'] as $f) {
                    $newVal = $row[$f] ?? null;
                    if ((string)$newVal !== (string)($dbRow[$f] ?? null)) {
                        $updates[] = "`$f` = ?";
                        $params[] = $newVal;
                    }
                }
                $externalId = kisFieldNormalizeExternalId($row['kis_external_id'] ?? '');
                if ($externalId !== '' && $externalId !== (string)($dbRow['kis_external_id'] ?? '')) {
                    $updates[] = '`kis_external_id` = ?';
                    $params[] = $externalId;
                }
                $updates[] = "`kis_posledni_sync` = CURRENT_TIMESTAMP";
                $updates[] = "`kis_last_seen_at` = CURRENT_TIMESTAMP";
                $updates[] = "`kis_identity_key` = ?";
                $params[] = kisMatchPersonKey($row);
                $updates[] = "`kis_match_confidence` = ?";
                $params[] = (int)($match['confidence'] ?? 0);

                if (!empty($updates)) {
                    $params[] = $id;
                    $sql = "UPDATE sportovci SET " . implode(', ', $updates) . " WHERE id = ? LIMIT 1";
                    $pdo->prepare($sql)->execute($params);
                    $report['updated']++;
                    sportovecLogEvent(
                        $pdo,
                        $id,
                        'kis_update',
                        'Aktualizace z KIS importu',
                        $dbRow,
                        $row,
                        'kis_import',
                        isset($_SESSION['trener_id']) ? (int)$_SESSION['trener_id'] : null,
                        'Import aktualizoval kontakty, KIS stav nebo platební údaje.',
                        'kis_import_runs',
                        isset($_SESSION['sync_run_id']) ? (int)$_SESSION['sync_run_id'] : null
                    );
                } else {
                    $report['unchanged']++;
                }

                // Vazby skupin přepiš JEN pokud import přinesl namapované skupiny/podskupiny.
                // Jinak by členové na nenamapovaných soupiskách (nebo ruční přiřazení mimo KIS)
                // přišli o členství bez náhrady.
                if (!empty($rowSkupiny) || !empty($rowPodskupiny)) {
                    $pdo->prepare("DELETE FROM sportovec_skupina WHERE sportovec_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM sportovec_podskupina WHERE sportovec_id = ?")->execute([$id]);

                    foreach (array_keys($rowSkupiny) as $skId) {
                        $pdo->prepare("INSERT IGNORE INTO sportovec_skupina (sportovec_id, skupina_id) VALUES (?, ?)")->execute([$id, $skId]);
                    }
                    foreach (array_keys($rowPodskupiny) as $psId) {
                        $pdo->prepare("INSERT IGNORE INTO sportovec_podskupina (sportovec_id, podskupina_id) VALUES (?, ?)")->execute([$id, $psId]);
                    }
                }
                $fresh = $pdo->prepare("SELECT * FROM sportovci WHERE id = ?");
                $fresh->execute([$id]);
                $freshRow = $fresh->fetch(PDO::FETCH_ASSOC);
                if ($freshRow) {
                    sportovecStatusUpdate($pdo, $id, $freshRow);
                }

            } else {
                // INSERT — neaktivní nové osoby přeskočit, pokud nebylo výslovně povoleno
                $isInactive = (int)($row['kis_aktivni'] ?? 0) === 0
                    && (int)($row['kis_platebne_aktivni'] ?? 0) === 0
                    && (float)($row['kis_neuhrazeno'] ?? 0) <= 0;
                if ($isInactive && !$vcetneNeaktivnich) {
                    $report['skipped_inactive']++;
                    continue;
                }
                $newRow = [
                    'jmeno' => $jmeno,
                    'prijmeni' => $prijmeni,
                    'narozeni' => $narozeni ?? '0000-00-00',
                    'email' => $row['email'] ?? '',
                    'rc' => $row['rc'] ?? '',
                    'telefon' => $row['telefon'] ?? '',
                    'adresa_ulice' => $row['adresa_ulice'] ?? '',
                    'adresa_cp' => $row['adresa_cp'] ?? '',
                    'adresa_co' => $row['adresa_co'] ?? '',
                    'adresa_obec' => $row['adresa_obec'] ?? '',
                    'adresa_psc' => $row['adresa_psc'] ?? '',
                    'kis_aktivni' => (int)($row['kis_aktivni'] ?? 0),
                    'kis_platebne_aktivni' => (int)($row['kis_platebne_aktivni'] ?? 0),
                    'kis_neuhrazeno' => (float)($row['kis_neuhrazeno'] ?? 0),
                    'kis_posledni_uhrada' => $row['kis_posledni_uhrada'] ?? null,
                    'kis_posledni_sync' => date('Y-m-d H:i:s'),
                    'kis_last_seen_at' => date('Y-m-d H:i:s'),
                    'kis_identity_key' => kisMatchPersonKey($row),
                    'kis_external_id' => kisFieldNormalizeExternalId($row['kis_external_id'] ?? '') ?: null,
                    'kis_match_confidence' => 0,
                    'kis_soupisky' => $row['kis_soupisky'] ?? '',
                    'hash' => generateHash($jmeno, $prijmeni),
                    'uci' => 0,
                    'odmena_za_trenink' => 0,
                ];

                $cols = array_keys($newRow);
                $ph = array_fill(0, count($cols), '?');
                $sql = "INSERT INTO sportovci (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                $pdo->prepare($sql)->execute(array_values($newRow));
                $newId = (int)$pdo->lastInsertId();
                $matchedDbIds[$newId] = true;

                // Assign skupiny
                foreach (array_keys($rowSkupiny) as $skId) {
                    $pdo->prepare("INSERT IGNORE INTO sportovec_skupina (sportovec_id, skupina_id) VALUES (?, ?)")->execute([$newId, $skId]);
                }
                foreach (array_keys($rowPodskupiny) as $psId) {
                    $pdo->prepare("INSERT IGNORE INTO sportovec_podskupina (sportovec_id, podskupina_id) VALUES (?, ?)")->execute([$newId, $psId]);
                }
                $fresh = $pdo->prepare("SELECT * FROM sportovci WHERE id = ?");
                $fresh->execute([$newId]);
                $freshRow = $fresh->fetch(PDO::FETCH_ASSOC);
                if ($freshRow) {
                    sportovecStatusUpdate($pdo, $newId, $freshRow);
                }
                sportovecLogEvent(
                    $pdo,
                    $newId,
                    'kis_create',
                    'Založení z KIS importu',
                    [],
                    $newRow,
                    'kis_import',
                    isset($_SESSION['trener_id']) ? (int)$_SESSION['trener_id'] : null,
                    'Nový člen byl založen z KIS importu.',
                    'kis_import_runs',
                    isset($_SESSION['sync_run_id']) ? (int)$_SESSION['sync_run_id'] : null
                );

                $report['added']++;
            }
        }

        // Automaticka archivace chybejicich lidi je zamerne vypnuta. KIS exporty
        // maji ruzny rozsah a chybejici radek nemusi znamenat ukoncene clenstvi.

        if (isset($_SESSION['sync_run_id'])) {
            $stRun = $pdo->prepare("
                UPDATE kis_import_runs
                SET status = 'applied',
                    applied_at = NOW(),
                    stats_json = :stats
                WHERE id = :id
            ");
            $stRun->execute([
                ':stats' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => (int)$_SESSION['sync_run_id'],
            ]);
        }

        $pdo->commit();

        // Clear session data
        unset($_SESSION['sync_data'], $_SESSION['sync_soupisky'], $_SESSION['sync_soupisky_counts'], $_SESSION['sync_mapping_saved'], $_SESSION['sync_meta'], $_SESSION['sync_warnings'], $_SESSION['sync_run_id']);

        $_SESSION['flash_success'] = "Synchronizace dokončena: {$report['added']} nových, {$report['updated']} aktualizovaných, {$report['archived']} archivovaných.";

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (isset($_SESSION['sync_run_id'])) {
            try {
                $stRun = $pdo->prepare("UPDATE kis_import_runs SET status = 'failed', note = ? WHERE id = ?");
                $stRun->execute([$e->getMessage(), (int)$_SESSION['sync_run_id']]);
            } catch (\Throwable $ignored) {}
        }
        $report['errors'][] = $e->getMessage();
    }

    return $report;
}

/* ================== LOAD DATA FOR STEP 2 ================== */

$skupiny = [];
$podskupinyAll = [];
$existingMappings = [];

if ($step === 2) {
    $skupiny = $pdo->query("SELECT id, nazev FROM skupiny WHERE LOWER(nazev) != 'archiv' ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);

    // All podskupiny grouped by skupina_id
    $psAll = $pdo->query("SELECT id, nazev, skupina_id FROM podskupiny ORDER BY poradi, nazev")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($psAll as $ps) {
        $podskupinyAll[(int)$ps['skupina_id']][] = $ps;
    }

    // Load existing mappings
    $mapRows = $pdo->query("SELECT * FROM soupiska_mapping")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mapRows as $mr) {
        $existingMappings[$mr['soupiska_text']] = $mr;
    }

    // Auto-match if no existing mapping
    $allPodskupiny = $psAll; // flat list for auto-matching
}

/* ================== LOAD SKUPINY NAMES FOR PREVIEW ================== */

$skupinyNames = [];
$podskupinyNames = [];
if ($step === 3 || $step === 4) {
    foreach ($pdo->query("SELECT id, nazev FROM skupiny")->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $skupinyNames[(int)$s['id']] = $s['nazev'];
    }
    foreach ($pdo->query("SELECT id, nazev FROM podskupiny")->fetchAll(PDO::FETCH_ASSOC) as $ps) {
        $podskupinyNames[(int)$ps['id']] = $ps['nazev'];
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Synchronizace evidence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .step-indicator { display: flex; gap: 0; margin-bottom: 1.5rem; }
        .step-item { flex: 1; text-align: center; padding: .6rem .5rem; font-size: .85rem; font-weight: 500;
                     background: #e9ecef; color: #6c757d; border-right: 2px solid #fff; position: relative; }
        .step-item:first-child { border-radius: 8px 0 0 8px; }
        .step-item:last-child { border-radius: 0 8px 8px 0; border-right: none; }
        .step-item.active { background: #0d6efd; color: #fff; }
        .step-item.done { background: #198754; color: #fff; }
        .diff-old { background: #f8d7da; text-decoration: line-through; }
        .diff-new { background: #d1e7dd; font-weight: 600; }
    </style>
</head>
<body class="bg-light">
<?php include 'hlavicka.php'; ?>
<div class="container mt-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Synchronizace evidence</h1>
        <a href="kis_sync_center.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i>KIS centrum
        </a>
        <?php if ($step > 1): ?>
        <a href="sync_evidence.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Začít znovu
        </a>
        <?php endif; ?>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator">
        <div class="step-item <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">
            <i class="bi bi-cloud-upload me-1"></i>1. Upload
        </div>
        <div class="step-item <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">
            <i class="bi bi-diagram-3 me-1"></i>2. Soupisky
        </div>
        <div class="step-item <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">
            <i class="bi bi-eye me-1"></i>3. Preview
        </div>
        <div class="step-item <?= $step === 4 ? 'active' : '' ?>">
            <i class="bi bi-check2-all me-1"></i>4. Provedení
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i>
        <?php foreach ($messages as $m): ?><div><?= h($m) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['sync_warnings'])): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php foreach ($_SESSION['sync_warnings'] as $w): ?><div><?= h($w) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- STEP 1: UPLOAD -->
    <!-- ============================================================= -->
    <?php if ($step === 1): ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-cloud-upload me-1"></i>Nahrát XLSX soubor
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Nahrajte tři Excel exporty z KIS. Pro bezpečný převod musí všechny obsahovat stejný stabilní sloupec
                <strong>KIS ID</strong> (podporované názvy např. KIS ID, ID uživatele nebo ID člena).
                KIS ID je interní identifikátor osoby a není to UCI licence. Starší export bez tohoto sloupce lze prohlédnout,
                ale systém jej nepovolí aplikovat ani do testovacího sandboxu.
            </p>
            <form method="POST" enctype="multipart/form-data" id="kisUploadForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="mb-3">
                    <label for="users_xlsx" class="form-label req">Uzivatele z KIS (.xlsx)</label>
                    <input type="file" name="users_xlsx" id="users_xlsx" accept=".xlsx,.xls" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="payments_xlsx" class="form-label req">Export plateb (.xlsx)</label>
                    <input type="file" name="payments_xlsx" id="payments_xlsx" accept=".xlsx,.xls" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="rosters_xlsx" class="form-label req">Soupisky z KIS (.xlsx)</label>
                    <input type="file" name="rosters_xlsx" id="rosters_xlsx" accept=".xlsx,.xls" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload me-1"></i>Nahrát a pokračovat
                </button>
            </form>
        </div>
    </div>

    <!-- Overlay během parsování XLSX -->
    <div id="kisUploadOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000;" class="text-center">
        <div class="bg-white rounded shadow p-4 d-inline-block" style="margin-top:20vh;">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <div class="fw-semibold">Zpracovávám XLSX soubory…</div>
            <div class="text-muted small">Načtení a spárování tří exportů může trvat několik sekund.</div>
        </div>
    </div>
    <script>
    (function () {
        var form = document.getElementById('kisUploadForm');
        if (!form) return;
        form.addEventListener('submit', function () {
            document.getElementById('kisUploadOverlay').style.display = 'block';
        });
    })();
    </script>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- STEP 2: MAPPING SOUPISKY -->
    <!-- ============================================================= -->
    <?php if ($step === 2 && isset($_SESSION['sync_soupisky'])): ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-diagram-3 me-1"></i>Mapování soupisek na skupiny/podskupiny
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Ke každé soupisce z Excelu přiřaďte odpovídající skupinu a podskupinu.
                Mapování se ukládá pro opakované použití.
            </p>
            <form method="POST" id="mappingForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_mapping">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Soupiska z Excelu</th>
                                <th style="width:60px;" class="text-center">Počet</th>
                                <th style="width:200px;">Skupina</th>
                                <th style="width:200px;">Podskupina</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $soupisky = $_SESSION['sync_soupisky'] ?? [];
                        $counts = $_SESSION['sync_soupisky_counts'] ?? [];
                        $num = 0;
                        foreach ($soupisky as $sp):
                            $num++;
                            $key = md5($sp);
                            $existing = $existingMappings[$sp] ?? null;

                            // Auto-match if no existing mapping
                            $preSkupina = $existing ? ($existing['skupina_id'] ?: '') : '';
                            $prePodskupina = $existing ? ($existing['podskupina_id'] ?: '') : '';

                            if (!$existing && !empty($allPodskupiny)) {
                                $autoMatch = autoMatchSoupiska($sp, $allPodskupiny);
                                if ($autoMatch) {
                                    $preSkupina = $autoMatch['skupina_id'];
                                    $prePodskupina = $autoMatch['id'];
                                }
                            }
                        ?>
                        <tr>
                            <td class="text-muted"><?= $num ?></td>
                            <td><code class="text-dark"><?= h($sp) ?></code></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $counts[$sp] ?? 0 ?></span></td>
                            <td>
                                <select name="mapping[<?= $key ?>][skupina_id]" class="form-select form-select-sm sel-skupina" data-key="<?= $key ?>">
                                    <option value="">— žádná —</option>
                                    <?php foreach ($skupiny as $sk): ?>
                                    <option value="<?= $sk['id'] ?>" <?= (int)$preSkupina === (int)$sk['id'] ? 'selected' : '' ?>>
                                        <?= h($sk['nazev']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="mapping[<?= $key ?>][podskupina_id]" class="form-select form-select-sm sel-podskupina" data-key="<?= $key ?>" data-pre="<?= (int)$prePodskupina ?>">
                                    <option value="">— žádná —</option>
                                    <?php
                                    if ($preSkupina && isset($podskupinyAll[(int)$preSkupina])) {
                                        foreach ($podskupinyAll[(int)$preSkupina] as $ps) {
                                            $sel = (int)$prePodskupina === (int)$ps['id'] ? 'selected' : '';
                                            echo '<option value="' . (int)$ps['id'] . '" ' . $sel . '>' . h($ps['nazev']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-success mt-3">
                    <i class="bi bi-save me-1"></i>Uložit mapování &amp; pokračovat na preview
                </button>
            </form>
        </div>
    </div>

    <script>
    // Dynamic podskupiny loading when skupina changes
    document.querySelectorAll('.sel-skupina').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var key = this.getAttribute('data-key');
            var psSelect = document.querySelector('.sel-podskupina[data-key="' + key + '"]');
            var skupinaId = this.value;

            psSelect.innerHTML = '<option value="">— žádná —</option>';

            if (!skupinaId) return;

            fetch('nacti_podskupiny.php?skupina_id=' + encodeURIComponent(skupinaId))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    data.forEach(function(ps) {
                        var opt = document.createElement('option');
                        opt.value = ps.id;
                        opt.textContent = ps.nazev;
                        psSelect.appendChild(opt);
                    });
                });
        });
    });
    </script>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- STEP 3: PREVIEW -->
    <!-- ============================================================= -->
    <?php if ($step === 3 && $preview !== null): ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success"><?= count($preview['new']) ?></div>
                    <div class="text-muted small"><i class="bi bi-plus-circle me-1"></i>Nových sportovců</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary"><?= count($preview['update']) ?></div>
                    <div class="text-muted small"><i class="bi bi-pencil-square me-1"></i>Aktualizovaných</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-warning"><?= (int)($preview['not_imported_count'] ?? 0) ?></div>
                    <div class="text-muted small"><i class="bi bi-exclamation-circle me-1"></i>Mimo import</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-secondary"><?= count($preview['unchanged']) ?></div>
                    <div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Beze změn</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-muted"><?= count($preview['new_inactive'] ?? []) ?></div>
                    <div class="text-muted small"><i class="bi bi-person-dash me-1"></i>Nových neaktivních</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($preview['warnings'])): ?>
    <div class="alert alert-warning">
        <h6><i class="bi bi-exclamation-triangle me-1"></i>Upozornění (<?= count($preview['warnings']) ?>)</h6>
        <ul class="mb-0 small">
        <?php foreach ($preview['warnings'] as $w): ?>
            <li><strong><?= h($w['name']) ?></strong>: <?= h($w['msg']) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- New sportovci -->
    <?php if (!empty($preview['new'])): ?>
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0"><i class="bi bi-plus-circle me-2 text-success"></i>Noví sportovci</h5>
        <span class="badge bg-success ms-2"><?= count($preview['new']) ?></span>
    </div>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-striped align-middle">
            <thead class="table-success">
                <tr><th>Jméno</th><th>Narozeni</th><th>Email</th><th>Skupiny</th></tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($preview['new'], 0, 100) as $n): ?>
            <tr>
                <td class="fw-semibold"><?= h($n['name']) ?></td>
                <td><?= h($n['narozeni'] ?? '—') ?></td>
                <td class="small"><?= h($n['email']) ?></td>
                <td class="small">
                    <?php foreach ($n['skupiny'] as $skId): ?>
                        <span class="badge bg-primary me-1"><?= h($skupinyNames[$skId] ?? $skId) ?></span>
                    <?php endforeach; ?>
                    <?php foreach ($n['podskupiny'] as $psId): ?>
                        <span class="badge bg-info me-1"><?= h($podskupinyNames[$psId] ?? $psId) ?></span>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($preview['new']) > 100): ?>
            <tr><td colspan="4" class="text-muted text-center">… a dalších <?= count($preview['new']) - 100 ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- New inactive (skipped by default) -->
    <?php if (!empty($preview['new_inactive'])): ?>
    <div class="mb-4">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#neaktivniCollapse">
            <i class="bi bi-person-dash me-1"></i>Noví neaktivní (<?= count($preview['new_inactive']) ?>) — ve výchozím stavu se nezakládají
        </button>
        <div class="collapse mt-2" id="neaktivniCollapse">
            <div class="alert alert-secondary small mb-2">
                Osoby z KIS exportu bez aktivní soupisky, bez zaplacené platby a bez dluhu — typicky historické záznamy.
                Založí se jen pokud níže zaškrtnete „Založit i neaktivní osoby".
            </div>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-striped align-middle">
                    <thead><tr><th>Jméno</th><th>Narozeni</th><th>Email</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($preview['new_inactive'], 0, 200) as $n): ?>
                    <tr>
                        <td><?= h($n['name']) ?></td>
                        <td><?= h($n['narozeni'] ?? '—') ?></td>
                        <td class="small"><?= h($n['email']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($preview['new_inactive']) > 200): ?>
                    <tr><td colspan="3" class="text-muted text-center">… a dalších <?= count($preview['new_inactive']) - 200 ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Updated sportovci -->
    <?php if (!empty($preview['update'])): ?>
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Aktualizace</h5>
        <span class="badge bg-primary ms-2"><?= count($preview['update']) ?></span>
    </div>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-striped align-middle">
            <thead class="table-primary">
                <tr><th>Jméno</th><th>Změny</th><th>Skupiny</th></tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($preview['update'], 0, 100) as $u): ?>
            <tr>
                <td class="fw-semibold">
                    <?= h($u['name']) ?>
                    <?php if ($u['match_type'] === 'name_only'): ?>
                        <span class="badge bg-warning text-dark ms-1" title="Shoda jen dle jména">⚠</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php foreach ($u['changes'] as $field => $diff): ?>
                        <div>
                            <strong><?= h($field) ?>:</strong>
                            <span class="diff-old"><?= h($diff['old'] ?: '—') ?></span>
                            → <span class="diff-new"><?= h($diff['new']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($u['changes'])): ?>
                        <span class="text-muted">Jen skupiny</span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php foreach ($u['skupiny'] as $skId): ?>
                        <span class="badge bg-primary me-1"><?= h($skupinyNames[$skId] ?? $skId) ?></span>
                    <?php endforeach; ?>
                    <?php foreach ($u['podskupiny'] as $psId): ?>
                        <span class="badge bg-info me-1"><?= h($podskupinyNames[$psId] ?? $psId) ?></span>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($preview['update']) > 100): ?>
            <tr><td colspan="3" class="text-muted text-center">… a dalších <?= count($preview['update']) - 100 ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Archive sportovci -->
    <?php if (!empty($preview['archive'])): ?>
    <div class="d-flex align-items-center mb-2">
        <h5 class="mb-0"><i class="bi bi-archive me-2 text-warning"></i>K archivaci</h5>
        <span class="badge bg-warning text-dark ms-2"><?= count($preview['archive']) ?></span>
    </div>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-striped align-middle">
            <thead class="table-warning">
                <tr><th>Jméno</th><th>Narozeni</th><th>Stav</th></tr>
            </thead>
            <tbody>
            <?php foreach ($preview['archive'] as $a): ?>
            <tr>
                <td class="fw-semibold"><?= h($a['name']) ?></td>
                <td><?= h($a['narozeni'] ?: '—') ?></td>
                <td>
                    <?php if ($a['already_archived']): ?>
                        <span class="badge bg-secondary">Již archivován</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Bude archivován</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Execute button -->
    <div class="card border-primary mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-exclamation-diamond text-primary me-2 fs-4"></i>
                    <span class="fw-bold">Potvrďte spuštění synchronizace</span>
                    <div class="text-muted small mt-1">
                        Tato operace aktualizuje <?= count($preview['update']) ?> sportovců,
                        přidá <?= count($preview['new']) ?> nových
                        bez automaticke archivace lidi chybejicich v importu.
                        <?php if (!empty($preview['new_inactive'])): ?>
                        <br><?= count($preview['new_inactive']) ?> neaktivních nových osob bude přeskočeno (pokud nezaškrtnete volbu níže).
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" data-confirm="Opravdu provést synchronizaci? Tuto akci nelze vrátit zpět.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="execute">
                    <?php if (!empty($preview['new_inactive'])): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="vcetne_neaktivnich" value="1" id="vcetneNeaktivnich">
                        <label class="form-check-label small" for="vcetneNeaktivnich">
                            Založit i neaktivní osoby (<?= count($preview['new_inactive']) ?>)
                        </label>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="bi bi-check2-all me-1"></i>Provést synchronizaci
                    </button>
                </form>
            </div>
        </div>
    </div>

    <a href="sync_evidence.php?step=2" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zpět na mapování soupisek
    </a>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- STEP 4: RESULTS -->
    <!-- ============================================================= -->
    <?php if ($step === 4 && $execReport !== null): ?>

    <?php if (!empty($execReport['errors'])): ?>
    <div class="alert alert-warning">
        <h6><i class="bi bi-exclamation-triangle me-1"></i>Část řádků nebyla zpracována (<?= count($execReport['errors']) ?>)</h6>
        <div class="small text-muted mb-2">Ostatní změny níže proběhly a byly uloženy.</div>
        <ul class="mb-0">
        <?php foreach ($execReport['errors'] as $err): ?>
            <li><?= h($err) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100 border-success" style="border-left: 4px solid #198754 !important;">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-success"><?= $execReport['added'] ?></div>
                    <div class="text-muted small"><i class="bi bi-plus-circle me-1"></i>Přidáno</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100 border-primary" style="border-left: 4px solid #0d6efd !important;">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-primary"><?= $execReport['updated'] ?></div>
                    <div class="text-muted small"><i class="bi bi-pencil-square me-1"></i>Aktualizováno</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100 border-warning" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-warning"><?= $execReport['archived'] ?></div>
                    <div class="text-muted small"><i class="bi bi-archive me-1"></i>Archivováno</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-secondary"><?= $execReport['unchanged'] ?></div>
                    <div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Beze změn</div>
                </div>
            </div>
        </div>
        <?php if (!empty($execReport['skipped_inactive'])): ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-muted"><?= (int)$execReport['skipped_inactive'] ?></div>
                    <div class="text-muted small"><i class="bi bi-person-dash me-1"></i>Přeskočeno neaktivních</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="alert alert-<?= !empty($execReport['errors']) ? 'warning' : 'success' ?>">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong><?= !empty($execReport['errors'])
            ? 'Synchronizace dokončena — část řádků vyžaduje ruční kontrolu (viz výše).'
            : 'Synchronizace úspěšně dokončena!' ?></strong>
    </div>

    <a href="sync_evidence.php" class="btn btn-primary">
        <i class="bi bi-arrow-repeat me-1"></i>Nová synchronizace
    </a>
    <a href="index.php" class="btn btn-outline-secondary ms-2">
        <i class="bi bi-house me-1"></i>Nástěnka
    </a>
    <?php endif; ?>

</div>

</body>
</html>
