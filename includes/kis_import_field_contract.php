<?php
declare(strict_types=1);

const KIS_IMPORT_FIELD_CONTRACT = 'kis-import-field-v1';

/** @return list<string> */
function kisFieldExternalIdHeaders(): array
{
    return ['kisid', 'iduzivatele', 'uzivatelid', 'idclena', 'clenid'];
}

/** @return list<string> */
function kisFieldPaymentIdHeaders(): array
{
    return ['idplatby', 'platbaid', 'paymentid', 'idpredpisu', 'predpisid'];
}

function kisFieldNormalizeExternalId(mixed $value): string
{
    $value = mb_strtoupper(trim((string)$value), 'UTF-8');
    if (preg_match('/^[A-Z0-9][A-Z0-9._:\/-]{1,79}$/D', $value) !== 1) {
        return '';
    }
    return $value;
}

/** @return array{raw:string,value:string,header:?string} */
function kisFieldExtractExternalId(array $row): array
{
    foreach (kisFieldExternalIdHeaders() as $header) {
        if (array_key_exists($header, $row) && trim((string)$row[$header]) !== '') {
            $raw = trim((string)$row[$header]);
            return ['raw' => $raw, 'value' => kisFieldNormalizeExternalId($raw), 'header' => $header];
        }
    }
    return ['raw' => '', 'value' => '', 'header' => null];
}

/** @return array{raw:string,value:string,header:?string} */
function kisFieldExtractPaymentId(array $row): array
{
    foreach (kisFieldPaymentIdHeaders() as $header) {
        if (array_key_exists($header, $row) && trim((string)$row[$header]) !== '') {
            $raw = trim((string)$row[$header]);
            return ['raw' => $raw, 'value' => kisFieldNormalizeExternalId($raw), 'header' => $header];
        }
    }
    return ['raw' => '', 'value' => '', 'header' => null];
}

/** @return array<string,mixed> */
function kisFieldContractEvaluate(array $people, array $meta, array $warnings): array
{
    $definitions = [
        'users' => ['meta' => 'users', 'required' => ['jmeno', 'prijmeni', 'datumnarozeni']],
        'payments' => ['meta' => 'payments', 'required' => ['stav', 'castka']],
        'rosters' => ['meta' => 'soupisky', 'required' => ['soupiska', 'jmeno', 'prijmeni']],
    ];
    $sources = [];
    $headerBlockers = 0;
    foreach ($definitions as $source => $definition) {
        $sourceMeta = is_array($meta[$definition['meta']] ?? null) ? $meta[$definition['meta']] : [];
        $headers = is_array($sourceMeta['headers'] ?? null) ? array_values(array_unique(array_map('strval', $sourceMeta['headers']))) : [];
        sort($headers, SORT_STRING);
        $missing = array_values(array_diff($definition['required'], $headers));
        $externalHeader = null;
        foreach (kisFieldExternalIdHeaders() as $candidate) {
            if (in_array($candidate, $headers, true)) {
                $externalHeader = $candidate;
                break;
            }
        }
        if ($externalHeader === null) {
            $missing[] = 'kis_external_id';
        }
        $paymentIdHeader = null;
        if ($source === 'payments') {
            foreach (kisFieldPaymentIdHeaders() as $candidate) {
                if (in_array($candidate, $headers, true)) {
                    $paymentIdHeader = $candidate;
                    break;
                }
            }
            if ($paymentIdHeader === null) $missing[] = 'payment_external_id';
        }
        $missing = array_values(array_unique($missing));
        $headerBlockers += count($missing);
        $sources[$source] = [
            'status' => $missing === [] ? 'valid' : 'blocked',
            'external_id_header' => $externalHeader,
            'payment_id_header' => $paymentIdHeader,
            'missing_headers' => $missing,
            'row_count' => max(0, (int)($sourceMeta['rows'] ?? 0)),
        ];
    }

    $rows = [];
    $byExternalId = [];
    foreach (array_values($people) as $index => $person) {
        $raw = trim((string)($person['_kis_external_id_raw'] ?? $person['kis_external_id'] ?? ''));
        $externalId = kisFieldNormalizeExternalId($person['kis_external_id'] ?? $raw);
        $status = 'valid';
        $reason = 'stable_external_id';
        if ($raw === '') {
            $status = 'blocked';
            $reason = 'missing_external_id';
        } elseif ($externalId === '') {
            $status = 'blocked';
            $reason = 'invalid_external_id';
        } elseif (!empty($person['_kis_external_id_conflict'])) {
            $status = 'blocked';
            $reason = 'external_id_identity_conflict';
        } else {
            $byExternalId[$externalId][] = $index;
        }
        $rows[] = ['source_ref' => 'source:' . ($index + 1), 'status' => $status, 'reason' => $reason];
    }
    foreach ($byExternalId as $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        foreach ($indexes as $index) {
            $rows[$index]['status'] = 'blocked';
            $rows[$index]['reason'] = 'duplicate_external_id';
        }
    }

    $warningBlockers = 0;
    foreach ($warnings as $warning) {
        $text = (string)$warning;
        if (str_starts_with($text, 'PAYMENT_')) {
            $warningBlockers++;
        }
    }
    $rowBlockers = count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'blocked'));
    $canonical = [
        'contract' => KIS_IMPORT_FIELD_CONTRACT,
        'status' => ($headerBlockers + $rowBlockers + $warningBlockers) === 0 ? 'ready_for_parity' : 'blocked',
        'summary' => [
            'total_people' => count($rows),
            'valid_people' => count($rows) - $rowBlockers,
            'row_blockers' => $rowBlockers,
            'header_blockers' => $headerBlockers,
            'warning_blockers' => $warningBlockers,
            'total_blockers' => $headerBlockers + $rowBlockers + $warningBlockers,
        ],
        'sources' => $sources,
        'rows' => $rows,
    ];
    $canonical['fingerprint'] = hash(
        'sha256',
        json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    );
    return $canonical;
}

/** @return array<string,mixed> */
function kisFieldContractStoredReport(array $run): ?array
{
    $json = $run['field_contract_report_json'] ?? null;
    if (!is_string($json) || trim($json) === '') {
        return null;
    }
    try {
        $report = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    if (!is_array($report)
        || ($report['contract'] ?? null) !== KIS_IMPORT_FIELD_CONTRACT
        || !is_string($report['fingerprint'] ?? null)
        || !hash_equals((string)($run['field_contract_fingerprint'] ?? ''), (string)$report['fingerprint'])) {
        return null;
    }
    return $report;
}
