<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
        ];
        return $rows;
    }

    throw new RuntimeException('Nelze najit hlavicku v souboru ' . basename($path));
}

function kis_upsert_person(array &$people, string $jmeno, string $prijmeni, ?string $narozeni): array
{
    $nameKey = kis_normalize_name($jmeno, $prijmeni);
    $key = $nameKey . '|' . ($narozeni ?? '');
    if ($nameKey === '') {
        return ['', []];
    }
    if (!isset($people[$key])) {
        $people[$key] = [
            'jmeno' => trim($jmeno),
            'prijmeni' => trim($prijmeni),
            'narozeni' => $narozeni,
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
        ];
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
        [$key] = kis_upsert_person($people, $jmeno, $prijmeni, $narozeni);
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
        [$key] = kis_upsert_person($people, $jmeno, $prijmeni, $narozeni);
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

    $paymentRows = kis_read_rows($paymentsPath, ['jmeno', 'prijmeni', 'stav'], $meta['payments']);
    $paymentsByName = [];
    foreach ($paymentRows as $row) {
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        $nameKey = kis_normalize_name($jmeno, $prijmeni);
        if ($nameKey === '') {
            continue;
        }
        $paymentsByName[$nameKey] ??= [
            'paid_rows' => 0,
            'open_rows' => 0,
            'open_total' => 0.0,
            'paid_total' => 0.0,
            'latest_paid' => null,
            'latest_due' => null,
        ];

        $remaining = kis_money_to_float($row['zbyvazaplatit'] ?? null);
        $amount = kis_money_to_float($row['castka'] ?? null);
        $status = mb_strtolower(kis_deaccent(trim((string)($row['stav'] ?? ''))), 'UTF-8');
        $paidDate = kis_date_to_mysql($row['datumuhrady'] ?? null);
        $dueDate = kis_date_to_mysql($row['datumsplatnosti'] ?? null);

        if ($status === 'zaplaceno' || $remaining === 0.0) {
            $paymentsByName[$nameKey]['paid_rows']++;
            $paymentsByName[$nameKey]['paid_total'] += $amount ?? 0.0;
        }
        if ($remaining !== null && $remaining > 0.009) {
            $paymentsByName[$nameKey]['open_rows']++;
            $paymentsByName[$nameKey]['open_total'] += $remaining;
        }
        if ($paidDate && (!$paymentsByName[$nameKey]['latest_paid'] || $paidDate > $paymentsByName[$nameKey]['latest_paid'])) {
            $paymentsByName[$nameKey]['latest_paid'] = $paidDate;
        }
        if ($dueDate && (!$paymentsByName[$nameKey]['latest_due'] || $dueDate > $paymentsByName[$nameKey]['latest_due'])) {
            $paymentsByName[$nameKey]['latest_due'] = $dueDate;
        }
    }

    $peopleByName = [];
    foreach ($people as $key => $person) {
        $peopleByName[kis_normalize_name($person['jmeno'], $person['prijmeni'])][] = $key;
    }
    foreach ($paymentsByName as $nameKey => $payment) {
        $matches = $peopleByName[$nameKey] ?? [];
        if (count($matches) === 1) {
            $people[$matches[0]]['_kis_payment'] = $payment;
        } elseif (count($matches) > 1) {
            $warnings[] = 'Platby nelze jednoznacne priradit duplicitnimu jmenu: ' . $nameKey;
        }
    }

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
