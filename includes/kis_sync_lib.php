<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

require_once __DIR__ . '/kis_import_field_contract.php';

function kis_deaccent(string $s): string
{
    static $map = [
        'á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z',
        'Á'=>'A','Č'=>'C','Ď'=>'D','É'=>'E','Ě'=>'E','Í'=>'I','Ň'=>'N','Ó'=>'O','Ř'=>'R','Š'=>'S','Ť'=>'T','Ú'=>'U','Ů'=>'U','Ý'=>'Y','Ž'=>'Z',
    ];
    return strtr($s, $map);
}

function kis_header_key(string $s): string
{
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    $s = kis_deaccent($s);
    $s = mb_strtolower($s, 'UTF-8');
    return (string)preg_replace('/[^a-z0-9]/', '', $s);
}

function kis_normalize_name(string $jmeno, string $prijmeni): string
{
    $s = trim($jmeno . ' ' . $prijmeni);
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = (string)preg_replace('/\s+/u', ' ', $s);
    $s = kis_deaccent($s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = (string)preg_replace('/[^a-z0-9 ]/', '', $s);
    return trim($s);
}

function kis_date_to_mysql($value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_numeric($value)) {
        try {
            return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }

    $s = trim((string)$value);
    if ($s === '') {
        return null;
    }
    $s = (string)preg_replace('/\s+.*$/', '', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $s, $m)) {
        $d = (int)$m[1];
        $mo = (int)$m[2];
        $y = (int)$m[3];
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }
    return null;
}

function kis_money_to_float($value): ?float
{
    $s = trim((string)$value);
    if ($s === '') {
        return null;
    }
    // Ponech jen číslice, oddělovače a znaménko (odstraní měnu, nbsp, tisícové mezery)
    $s = (string)preg_replace('/[^\d,.-]/u', '', $s);
    // Česká desetinná čárka → tečka
    $s = str_replace(',', '.', $s);
    // Víc teček = tisícové oddělovače; ponech jen poslední jako desetinnou
    if (substr_count($s, '.') > 1) {
        $parts = explode('.', $s);
        $dec = array_pop($parts);
        $s = implode('', $parts) . '.' . $dec;
    }
    return is_numeric($s) ? (float)$s : null;
}

function kis_parse_soupisky(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/(\(\d{4}\/\d{4}\))\s*,\s*/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!$parts) {
        return [$raw];
    }

    $result = [];
    $current = '';
    foreach ($parts as $part) {
        if (preg_match('/^\(\d{4}\/\d{4}\)$/', $part)) {
            $current .= $part;
            $current = trim($current);
            if ($current !== '') {
                $result[] = $current;
            }
            $current = '';
        } else {
            $current .= $part;
        }
    }
    $current = trim($current);
    if ($current !== '') {
        $result[] = $current;
    }

    return array_values(array_unique($result));
}

function kis_find_header_row(Worksheet $sheet, array $requiredKeys, int $maxRows = 20): ?array
{
    $highestRow = (int)$sheet->getHighestRow();
    $highestCol = (string)$sheet->getHighestColumn();

    for ($r = 1; $r <= min($highestRow, $maxRows); $r++) {
        $row = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0] ?? [];
        $headers = [];
        foreach ($row as $ci => $cell) {
            $key = kis_header_key((string)$cell);
            if ($key !== '') {
                $headers[$key] = $ci;
            }
        }

        $ok = true;
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $headers)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return [$r, $headers];
        }
    }
    return null;
}

function kis_read_rows(string $path, array $requiredKeys, array &$meta): array
{
    $spreadsheet = IOFactory::load($path);
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $found = kis_find_header_row($sheet, $requiredKeys);
        if ($found === null) {
            continue;
        }

        [$headerRow, $headers] = $found;
        $highestRow = (int)$sheet->getHighestRow();
        $highestCol = (string)$sheet->getHighestColumn();
        $rows = [];

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $data = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0] ?? [];
            $nonEmpty = false;
            foreach ($data as $v) {
                if (trim((string)$v) !== '') {
                    $nonEmpty = true;
                    break;
                }
            }
            if (!$nonEmpty) {
                continue;
            }

            $row = ['_row' => $r];
            foreach ($headers as $key => $ci) {
                $row[$key] = $data[$ci] ?? null;
            }
            $rows[] = $row;
        }

        $meta = [
            'sheet' => $sheet->getTitle(),
            'header_row' => $headerRow,
            'rows' => count($rows),
            'headers' => array_values(array_keys($headers)),
        ];
        return $rows;
    }

    throw new RuntimeException('Nelze najit hlavicku v souboru ' . basename($path));
}

function kis_upsert_person(array &$people, string $jmeno, string $prijmeni, ?string $narozeni, string $externalId = '', string $externalIdRaw = ''): array
{
    $nameKey = kis_normalize_name($jmeno, $prijmeni);
    $externalId = kisFieldNormalizeExternalId($externalId);
    $key = $externalId !== '' ? 'external:' . $externalId : 'fallback:' . $nameKey . '|' . ($narozeni ?? '');
    if ($nameKey === '') {
        return ['', []];
    }
    if (!isset($people[$key])) {
        $people[$key] = [
            'jmeno' => trim($jmeno),
            'prijmeni' => trim($prijmeni),
            'narozeni' => $narozeni,
            'kis_external_id' => $externalId,
            '_kis_external_id_raw' => trim($externalIdRaw),
            '_kis_external_id_conflict' => false,
            'email' => '',
            'telefon' => '',
            'rc' => '',
            'adresa_ulice' => '',
            'adresa_cp' => '',
            'adresa_co' => '',
            'adresa_obec' => '',
            'adresa_psc' => '',
            '_soupisky_parsed' => [],
            '_kis_soupiska_active' => null,
            '_kis_payment' => [
                'paid_rows' => 0,
                'open_rows' => 0,
                'open_total' => 0.0,
                'paid_total' => 0.0,
                'latest_paid' => null,
                'latest_due' => null,
            ],
            '_kis_payment_rows' => [],
        ];
    } elseif ($externalId !== '') {
        $existingName = kis_normalize_name((string)$people[$key]['jmeno'], (string)$people[$key]['prijmeni']);
        $existingBirth = (string)($people[$key]['narozeni'] ?? '');
        if ($existingName !== $nameKey || ($existingBirth !== '' && $narozeni !== null && $existingBirth !== $narozeni)) {
            $people[$key]['_kis_external_id_conflict'] = true;
        }
    }
    return [$key, $people[$key]];
}

function kis_merge_contact(array &$person, array $source): void
{
    foreach (['email','telefon','rc','adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc'] as $field) {
        if (($person[$field] ?? '') === '' && isset($source[$field]) && trim((string)$source[$field]) !== '') {
            $person[$field] = trim((string)$source[$field]);
        }
    }
}

function kis_build_import(string $usersPath, string $paymentsPath, string $rostersPath): array
{
    $meta = ['users' => [], 'payments' => [], 'soupisky' => []];
    $warnings = [];
    $people = [];
    $soupiskyCounts = [];

    $userRows = kis_read_rows($usersPath, ['jmeno', 'prijmeni'], $meta['users']);
    foreach ($userRows as $row) {
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        if ($jmeno === '' || $prijmeni === '') {
            continue;
        }
        $narozeni = kis_date_to_mysql($row['datumnarozeni'] ?? null);
        $external = kisFieldExtractExternalId($row);
        [$key] = kis_upsert_person($people, $jmeno, $prijmeni, $narozeni, $external['value'], $external['raw']);
        if ($key === '') {
            continue;
        }

        $contact = [
            'email' => $row['email'] ?? '',
            'telefon' => $row['telefon'] ?? '',
            'rc' => $row['rodnecislo'] ?? '',
            'adresa_ulice' => $row['trvalaadresaulice'] ?? '',
            'adresa_cp' => $row['trvalaadresacp'] ?? '',
            'adresa_co' => $row['trvalaadresaco'] ?? '',
            'adresa_obec' => $row['trvalaadresaobec'] ?? '',
            'adresa_psc' => $row['trvalaadresapsc'] ?? '',
        ];
        kis_merge_contact($people[$key], $contact);

        foreach (kis_parse_soupisky((string)($row['soupisky'] ?? '')) as $sp) {
            $people[$key]['_soupisky_parsed'][$sp] = true;
            $soupiskyCounts[$sp] = ($soupiskyCounts[$sp] ?? 0) + 1;
        }
    }

    $rosterRows = kis_read_rows($rostersPath, ['soupiska', 'jmeno', 'prijmeni'], $meta['soupisky']);
    foreach ($rosterRows as $row) {
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        if ($jmeno === '' || $prijmeni === '') {
            continue;
        }
        $narozeni = kis_date_to_mysql($row['datumnarozeni'] ?? null);
        $external = kisFieldExtractExternalId($row);
        [$key] = kis_upsert_person($people, $jmeno, $prijmeni, $narozeni, $external['value'], $external['raw']);
        if ($key === '') {
            continue;
        }

        kis_merge_contact($people[$key], [
            'email' => $row['email'] ?? '',
            'telefon' => $row['telefon'] ?? '',
            'rc' => $row['rodnecislo'] ?? '',
            'adresa_ulice' => $row['trvalaadresaulice'] ?? '',
            'adresa_cp' => $row['trvalaadresacp'] ?? '',
            'adresa_co' => $row['trvalaadresaco'] ?? '',
            'adresa_obec' => $row['trvalaadresaobec'] ?? '',
            'adresa_psc' => $row['trvalaadresapsc'] ?? '',
        ]);

        $soupiska = trim((string)($row['soupiska'] ?? ''));
        if ($soupiska !== '') {
            $people[$key]['_soupisky_parsed'][$soupiska] = true;
            $soupiskyCounts[$soupiska] = ($soupiskyCounts[$soupiska] ?? 0) + 1;
        }
        $activeRaw = mb_strtolower(kis_deaccent(trim((string)($row['aktivni'] ?? ''))), 'UTF-8');
        if ($activeRaw !== '') {
            $people[$key]['_kis_soupiska_active'] = in_array($activeRaw, ['ano', '1', 'true', 'aktivni'], true);
        }
    }

    $paymentRows = kis_read_rows($paymentsPath, ['stav'], $meta['payments']);
    $paymentsByIdentity = [];
    $paymentRowsByIdentity = [];
    $paymentFallbackRows = 0;
    $paymentInvalidExternalRows = 0;
    $paymentMissingPrescriptionIds = 0;
    $paymentInvalidPrescriptionIds = 0;
    $paymentMissingAmounts = 0;
    $paymentInvalidAmounts = 0;
    $paymentMissingPaidDates = 0;
    foreach ($paymentRows as $row) {
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        $nameKey = kis_normalize_name($jmeno, $prijmeni);
        $external = kisFieldExtractExternalId($row);
        if ($external['raw'] !== '' && $external['value'] === '') {
            $paymentInvalidExternalRows++;
        }
        if ($external['value'] === '') {
            $paymentFallbackRows++;
        }
        if ($external['value'] === '' && $nameKey === '') {
            continue;
        }
        $paymentKey = $external['value'] !== '' ? 'external:' . $external['value'] : 'name:' . $nameKey;
        $prescriptionId = kisFieldExtractPaymentId($row);
        if ($prescriptionId['raw'] === '') {
            $paymentMissingPrescriptionIds++;
        } elseif ($prescriptionId['value'] === '') {
            $paymentInvalidPrescriptionIds++;
        }
        $paymentsByIdentity[$paymentKey] ??= [
            'paid_rows' => 0,
            'open_rows' => 0,
            'open_total' => 0.0,
            'paid_total' => 0.0,
            'latest_paid' => null,
            'latest_due' => null,
        ];

        $remaining = kis_money_to_float($row['zbyvazaplatit'] ?? null);
        $amount = kis_money_to_float($row['castka'] ?? null);
        if ($amount === null) {
            $paymentMissingAmounts++;
        } elseif ($amount <= 0) {
            $paymentInvalidAmounts++;
        }
        $status = mb_strtolower(kis_deaccent(trim((string)($row['stav'] ?? ''))), 'UTF-8');
        $paidDate = kis_date_to_mysql($row['datumuhrady'] ?? null);
        $dueDate = kis_date_to_mysql($row['datumsplatnosti'] ?? null);
        $statusClass = ($status === 'zaplaceno' || $remaining === 0.0) ? 'paid'
            : (in_array($status, ['zruseno', 'stornovano', 'storno'], true) ? 'cancelled' : 'pending');
        if ($statusClass === 'paid' && $paidDate === null) $paymentMissingPaidDates++;
        if ($prescriptionId['value'] !== '' && $amount !== null && $amount > 0 && ($statusClass !== 'paid' || $paidDate !== null)) {
            $amountMinor = (int)round($amount * 100);
            $outstandingMinor = $remaining === null
                ? ($statusClass === 'pending' ? $amountMinor : 0)
                : max(0, (int)round($remaining * 100));
            $paymentRowsByIdentity[$paymentKey][] = [
                'payment_external_id' => $prescriptionId['value'],
                'status' => $statusClass,
                'amount_minor' => $amountMinor,
                'outstanding_minor' => $outstandingMinor,
                'currency' => 'CZK',
                'due_on' => $dueDate,
                'paid_on' => $paidDate,
            ];
        }

        if ($status === 'zaplaceno' || $remaining === 0.0) {
            $paymentsByIdentity[$paymentKey]['paid_rows']++;
            $paymentsByIdentity[$paymentKey]['paid_total'] += $amount ?? 0.0;
        }
        if ($remaining !== null && $remaining > 0.009) {
            $paymentsByIdentity[$paymentKey]['open_rows']++;
            $paymentsByIdentity[$paymentKey]['open_total'] += $remaining;
        }
        if ($paidDate && (!$paymentsByIdentity[$paymentKey]['latest_paid'] || $paidDate > $paymentsByIdentity[$paymentKey]['latest_paid'])) {
            $paymentsByIdentity[$paymentKey]['latest_paid'] = $paidDate;
        }
        if ($dueDate && (!$paymentsByIdentity[$paymentKey]['latest_due'] || $dueDate > $paymentsByIdentity[$paymentKey]['latest_due'])) {
            $paymentsByIdentity[$paymentKey]['latest_due'] = $dueDate;
        }
    }

    $peopleByName = [];
    $peopleByExternal = [];
    foreach ($people as $key => $person) {
        $peopleByName[kis_normalize_name($person['jmeno'], $person['prijmeni'])][] = $key;
        if (($person['kis_external_id'] ?? '') !== '') {
            $peopleByExternal[(string)$person['kis_external_id']][] = $key;
        }
    }
    $paymentAmbiguousRows = 0;
    $paymentUnmatchedExternalRows = 0;
    foreach ($paymentsByIdentity as $identityKey => $payment) {
        if (str_starts_with($identityKey, 'external:')) {
            $matches = $peopleByExternal[substr($identityKey, 9)] ?? [];
            if ($matches === []) $paymentUnmatchedExternalRows++;
        } else {
            $matches = $peopleByName[substr($identityKey, 5)] ?? [];
        }
        if (count($matches) === 1) {
            $people[$matches[0]]['_kis_payment'] = $payment;
            $people[$matches[0]]['_kis_payment_rows'] = $paymentRowsByIdentity[$identityKey] ?? [];
        } elseif (count($matches) > 1) {
            $paymentAmbiguousRows++;
        }
    }
    if ($paymentFallbackRows > 0) $warnings[] = 'PAYMENT_NAME_FALLBACK:' . $paymentFallbackRows;
    if ($paymentInvalidExternalRows > 0) $warnings[] = 'PAYMENT_EXTERNAL_ID_INVALID:' . $paymentInvalidExternalRows;
    if ($paymentUnmatchedExternalRows > 0) $warnings[] = 'PAYMENT_EXTERNAL_ID_UNMATCHED:' . $paymentUnmatchedExternalRows;
    if ($paymentAmbiguousRows > 0) $warnings[] = 'PAYMENT_NAME_AMBIGUOUS:' . $paymentAmbiguousRows;
    if ($paymentMissingPrescriptionIds > 0) $warnings[] = 'PAYMENT_PRESCRIPTION_ID_MISSING:' . $paymentMissingPrescriptionIds;
    if ($paymentInvalidPrescriptionIds > 0) $warnings[] = 'PAYMENT_PRESCRIPTION_ID_INVALID:' . $paymentInvalidPrescriptionIds;
    if ($paymentMissingAmounts > 0) $warnings[] = 'PAYMENT_PRESCRIPTION_AMOUNT_MISSING:' . $paymentMissingAmounts;
    if ($paymentInvalidAmounts > 0) $warnings[] = 'PAYMENT_PRESCRIPTION_AMOUNT_INVALID:' . $paymentInvalidAmounts;
    if ($paymentMissingPaidDates > 0) $warnings[] = 'PAYMENT_PRESCRIPTION_PAID_DATE_MISSING:' . $paymentMissingPaidDates;

    // Sloučení sezónních variant: "X (2025/2026)" → "X", pokud existuje i základní název.
    // KIS exportuje tutéž soupisku dvakrát (users bez sufixu, soupisky se sufixem sezóny) —
    // bez sloučení by uživatel mapoval každou soupisku dvakrát.
    $canonical = [];
    foreach (array_keys($soupiskyCounts) as $sp) {
        if (preg_match('/^(.*\S)\s*\(\d{4}\/\d{4}\)$/u', $sp, $m) && isset($soupiskyCounts[trim($m[1])])) {
            $canonical[$sp] = trim($m[1]);
        }
    }
    if ($canonical) {
        foreach ($canonical as $from => $to) {
            $soupiskyCounts[$to] = ($soupiskyCounts[$to] ?? 0) + $soupiskyCounts[$from];
            unset($soupiskyCounts[$from]);
        }
        foreach ($people as &$person) {
            foreach (array_keys($person['_soupisky_parsed']) as $sp) {
                if (isset($canonical[$sp])) {
                    unset($person['_soupisky_parsed'][$sp]);
                    $person['_soupisky_parsed'][$canonical[$sp]] = true;
                }
            }
        }
        unset($person);
    }

    foreach ($people as &$person) {
        $person['_soupisky_parsed'] = array_keys($person['_soupisky_parsed']);
        $person['kis_soupisky'] = implode(', ', $person['_soupisky_parsed']);
        $person['kis_aktivni'] = $person['_kis_soupiska_active'] === null
            ? (!empty($person['_soupisky_parsed']) ? 1 : 0)
            : ($person['_kis_soupiska_active'] ? 1 : 0);
        $person['kis_platebne_aktivni'] = $person['_kis_payment']['paid_rows'] > 0 ? 1 : 0;
        $person['kis_neuhrazeno'] = round((float)$person['_kis_payment']['open_total'], 2);
        $person['kis_posledni_uhrada'] = $person['_kis_payment']['latest_paid'];
    }
    unset($person);

    ksort($soupiskyCounts, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'people' => array_values($people),
        'soupisky' => array_keys($soupiskyCounts),
        'soupisky_counts' => $soupiskyCounts,
        'meta' => $meta,
        'warnings' => $warnings,
    ];
}
