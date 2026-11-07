<?php

declare(strict_types=1);

require_once __DIR__ . '/external_identity_match.php';

final class ExternalSyncException extends RuntimeException
{
}

function externalSyncReject(string $reason, int $status = 422): never
{
    throw new ExternalSyncException($reason, $status);
}

function externalSyncKeys(array $value, array $keys): void
{
    $actual = array_keys($value);
    sort($actual);
    sort($keys);
    if ($actual !== $keys) {
        externalSyncReject('unknown_or_missing_field');
    }
}

function externalSyncText(mixed $value, int $max, bool $nullable = true): ?string
{
    if ($value === null && $nullable) {
        return null;
    }
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > $max) {
        externalSyncReject('invalid_text');
    }
    if (class_exists('Normalizer')) {
        $value = Normalizer::normalize($value, Normalizer::FORM_C);
    } elseif (preg_match('/\p{M}/u', $value)) {
        // Do not silently claim NFC if the runtime cannot normalize combining marks.
        externalSyncReject('unicode_normalizer_required');
    }
    $value = preg_replace('/^[ \x{00A0}]+|[ \x{00A0}]+$/u', '', $value);
    if (!$nullable && $value === '') {
        externalSyncReject('empty_identifier');
    }
    return $value === '' ? null : $value;
}

function externalSyncInteger(mixed $value, int $min, int $max): int
{
    if (!is_int($value) || $value < $min || $value > $max) {
        externalSyncReject('invalid_integer');
    }
    return $value;
}

function externalSyncRow(string $collection, array $row): array
{
    $fields = match ($collection) {
        'csc_licenses' => ['license_id' => 80, 'uci_id_raw' => 80, 'display_name' => 200, 'license_status_raw' => 100,
            'role_label' => 200, 'discipline_label' => 200, 'category_label' => 200, 'hosting_checked' => 0, 'validity_year_raw' => 20],
        'sportsoft_members' => ['member_id' => 0, 'uci_id_raw' => 80, 'display_name' => 200, 'birth_date_raw' => 40,
            'nationality_label' => 100, 'role_label' => 200, 'source_label' => 50],
        'sportsoft_events' => ['event_id' => 80, 'event_date_raw' => 80, 'event_name' => 300,
            'venue_label' => 300, 'registration_action_label' => 100],
        'sportsoft_registrations' => ['registration_id' => 0, 'uci_id_raw' => 80, 'display_name' => 200,
            'birth_date_raw' => 40, 'category_label' => 200, 'paid_raw' => 100],
        default => externalSyncReject('invalid_collection'),
    };
    externalSyncKeys($row, array_keys($fields));
    foreach ($fields as $key => $max) {
        if ($key === 'hosting_checked') {
            if ($row[$key] !== null && !is_bool($row[$key])) {
                externalSyncReject('invalid_hosting');
            }
        } elseif ($max === 0) {
            if ($row[$key] !== null) {
                externalSyncReject('unverified_source_id');
            }
        } else {
            $row[$key] = externalSyncText($row[$key], $max, !in_array($key, ['license_id', 'event_name'], true));
        }
    }
    return $row;
}

function externalSyncBatch(array $batch): array
{
    externalSyncKeys($batch, ['schema_version', 'run_id', 'connection_id', 'lease_generation', 'collection', 'event_id',
        'scope_id', 'adapter_version', 'observed_at', 'part_no', 'page', 'rows']);
    if ($batch['schema_version'] !== 1) {
        externalSyncReject('unsupported_schema');
    }
    foreach (['connection_id', 'scope_id', 'adapter_version', 'run_id', 'collection', 'observed_at'] as $key) {
        $batch[$key] = externalSyncText($batch[$key], 80, false);
    }
    if (!preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/Di', $batch['run_id'])) {
        externalSyncReject('invalid_run_id');
    }
    if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]+)?Z$/D', $batch['observed_at'])) {
        externalSyncReject('invalid_observed_at');
    }
    $seconds = substr($batch['observed_at'], 0, 19);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $seconds, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d\TH:i:s') !== $seconds) {
        externalSyncReject('invalid_observed_at');
    }
    $batch['event_id'] = externalSyncText($batch['event_id'], 80);
    if (($batch['collection'] === 'sportsoft_registrations') !== ($batch['event_id'] !== null)) {
        externalSyncReject('invalid_event_scope');
    }
    externalSyncInteger($batch['lease_generation'], 1, PHP_INT_MAX);
    externalSyncInteger($batch['part_no'], 1, 1000);
    if (!is_array($batch['page']) || !is_array($batch['rows']) || !array_is_list($batch['rows']) || count($batch['rows']) > 250) {
        externalSyncReject('invalid_page');
    }
    externalSyncKeys($batch['page'], ['index', 'reported_total', 'row_offset', 'row_count']);
    externalSyncInteger($batch['page']['index'], 1, 1000);
    externalSyncInteger($batch['page']['row_offset'], 0, 100000);
    externalSyncInteger($batch['page']['row_count'], 0, 250);
    if ($batch['page']['reported_total'] !== null) {
        externalSyncInteger($batch['page']['reported_total'], 0, 100000);
    }
    if ($batch['page']['row_count'] !== count($batch['rows'])) {
        externalSyncReject('row_count_mismatch');
    }
    foreach ($batch['rows'] as &$row) {
        if (!is_array($row)) {
            externalSyncReject('invalid_row');
        }
        $row = externalSyncRow($batch['collection'], $row);
    }
    unset($row);
    // Reject an unknown collection even when the batch is empty.
    if (!in_array($batch['collection'], ['csc_licenses', 'sportsoft_members', 'sportsoft_events', 'sportsoft_registrations'], true)) {
        externalSyncReject('invalid_collection');
    }
    return $batch;
}


/** Stable canonical bytes for stored content; transport receipts hash raw request bytes. */
function externalSyncJson(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function externalSyncEntityKey(string $collection, array $row): ?string
{
    $uci = externalIdentityUci($row['uci_id_raw'] ?? null)['uci'];
    $parts = match ($collection) {
        'csc_licenses' => ['license', $row['license_id']],
        'sportsoft_events' => $row['event_id'] === null ? null : ['event', $row['event_id']],
        'sportsoft_members' => $uci === null || $row['role_label'] === null ? null : ['member', $uci, $row['role_label']],
        'sportsoft_registrations' => $uci === null || $row['category_label'] === null ? null : ['entry', $uci, $row['category_label']],
        default => null,
    };
    // Connection is one immutable provider/scope/collection/event, and namespaces this hash.
    return $parts === null ? null : hash('sha256', externalSyncJson($parts));
}
