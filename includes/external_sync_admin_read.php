<?php

declare(strict_types=1);

require_once __DIR__ . '/external_sync_read.php';

function externalSyncAdminReasons(): array
{
    return [
        'matched' => ['Spárováno', 'Jednoznačná shoda UCI ID a kontrolních údajů.'],
        'unmatched' => ['UCI ID nenalezeno v KIS', 'Ověřte UCI ID v kmenové evidenci. Jméno se nepoužívá jako náhradní klíč.'],
        'missing_uci' => ['Chybí UCI ID', 'Zdroj neobsahuje UCI ID. Vazba na osobu nebyla vytvořena.'],
        'invalid_uci' => ['Neplatný formát UCI ID', 'UCI ID musí obsahovat 11 číslic; povolené mezery se odstraní.'],
        'duplicate_kis_uci' => ['Duplicitní UCI ID v KIS', 'Více osob má stejné UCI ID. Ověřte kmenovou evidenci.'],
        'identity_conflict' => ['Rozpor identity', 'Nesouhlasí jméno, narození nebo dřívější vazba, případně si odporují řádky zdroje.'],
        'missing_source_key' => ['Chybí stabilní klíč zdroje', 'Osobu nelze bezpečně spojit s tímto zdrojovým řádkem.'],
        'duplicate_source_key' => ['Duplicitní klíč zdroje', 'Tentýž zdrojový klíč označuje více řádků.'],
        'not_applicable' => ['Bez párování osoby', 'Kalendář závodů se nepáruje na sportovce.'],
    ];
}

/** Explicit presentation allowlist; never render arbitrary JSON keys. */
function externalSyncAdminFields(string $collection): array
{
    return match ($collection) {
        'csc_licenses' => ['license_id' => 'ID licence', 'uci_id_raw' => 'UCI ID ve zdroji', 'display_name' => 'Jméno',
            'license_status_raw' => 'Stav licence ve zdroji', 'role_label' => 'Role', 'discipline_label' => 'Disciplína',
            'category_label' => 'Kategorie', 'hosting_checked' => 'Hostování', 'validity_year_raw' => 'Rok ve zdroji'],
        'sportsoft_members' => ['member_id' => 'ID člena', 'uci_id_raw' => 'UCI ID ve zdroji', 'display_name' => 'Jméno',
            'birth_date_raw' => 'Datum narození', 'nationality_label' => 'Národnost', 'role_label' => 'Role', 'source_label' => 'Zdroj'],
        'sportsoft_events' => ['event_id' => 'ID závodu', 'event_date_raw' => 'Datum závodu', 'event_name' => 'Závod',
            'venue_label' => 'Místo', 'registration_action_label' => 'Přihláška ve zdroji'],
        'sportsoft_registrations' => ['registration_id' => 'ID přihlášky', 'uci_id_raw' => 'UCI ID ve zdroji',
            'display_name' => 'Jméno', 'birth_date_raw' => 'Datum narození', 'category_label' => 'Kategorie', 'paid_raw' => 'Zaplaceno ve zdroji'],
        default => [],
    };
}

/** Scalar-only GET parameters; reject arrays and arbitrary SQL/filter fragments. */
function externalSyncAdminInput(array $query): array
{
    $result = ['connection' => '', 'run' => '', 'filter' => 'needs_review', 'page' => 1, 'history_page' => 1, 'connections_page' => 1];
    foreach ($result as $key => $default) {
        if (!isset($query[$key])) {
            continue;
        }
        $value = $query[$key];
        if (!is_string($value) || strlen($value) > 80) {
            throw new InvalidArgumentException('Neplatný parametr přehledu.');
        }
        if (is_int($default)) {
            if (!preg_match('/^[1-9][0-9]{0,5}$/D', $value)) {
                throw new InvalidArgumentException('Neplatné číslo stránky.');
            }
            $result[$key] = (int)$value;
        } else {
            $result[$key] = $value;
        }
    }
    if (!in_array($result['filter'], array_merge(['all', 'needs_review'], array_keys(externalSyncAdminReasons())), true)) {
        throw new InvalidArgumentException('Neplatný filtr párování.');
    }
    return $result;
}

/** One connection query, independent of the number of rows/devices. No credentials selected. */
function externalSyncAdminOverview(PDO $pdo, int $trainerId, int $page = 1): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    $offset = (max(1, min(999999, $page)) - 1) * 25;
    $rows = $pdo->query('SELECT c.*, a.status AS accepted_status, a.finished_at AS accepted_at,
        r.id AS attempt_id, r.status AS attempt_status, r.failure_code, r.started_at AS attempt_at,
        r.finished_at AS attempt_finished_at, r.lease_until
        FROM external_sync_connections c
        LEFT JOIN external_sync_runs a ON a.id=c.latest_run_id AND a.connection_id=c.id
        LEFT JOIN external_sync_runs r ON r.connection_id=c.id AND r.sequence_no=c.next_sequence
        ORDER BY c.provider,c.collection,c.id LIMIT 26 OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
    $hasNext = count($rows) > 25;
    return ['rows' => array_slice($rows, 0, 25), 'has_next' => $hasNext, 'page' => $page];
}

/** Public read service. Authorization is performed before even looking up a scope/run. */
function externalSyncAdminDetail(PDO $pdo, int $trainerId, array $input): array
{
    operationalCaseActor($pdo, $trainerId, 'registrar');
    if ($pdo->inTransaction()) {
        throw new LogicException('external_sync_read_requires_own_transaction');
    }
    $pdo->beginTransaction();
    try {
        $result = externalSyncAdminDetailRead($pdo, $input);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/** Internal, called only after authorization. A coherent DB read, never publication. */
function externalSyncAdminDetailRead(PDO $pdo, array $input): array
{
    $connection = externalSyncQuery($pdo, 'SELECT * FROM external_sync_connections WHERE id=?', [$input['connection']])->fetch(PDO::FETCH_ASSOC);
    if (!$connection) {
        externalSyncReject('unknown_connection', 404);
    }
    $offset = (max(1, min(999999, (int)$input['history_page'])) - 1) * 25;
    $history = externalSyncQuery($pdo, 'SELECT id,sequence_no,status,started_at,finished_at,row_count,failure_code,lease_until
        FROM external_sync_runs WHERE connection_id=? ORDER BY sequence_no DESC LIMIT 26 OFFSET ' . $offset, [$connection['id']])->fetchAll(PDO::FETCH_ASSOC);
    $runId = $input['run'] !== '' ? $input['run'] : $connection['latest_run_id'];
    $run = $runId === null ? false : externalSyncQuery($pdo, 'SELECT id,connection_id,sequence_no,status,started_at,finished_at,row_count,failure_code
        FROM external_sync_runs WHERE id=? AND connection_id=?', [$runId, $connection['id']])->fetch(PDO::FETCH_ASSOC);
    if ($runId !== null && !$run) {
        externalSyncReject('unknown_run', 404);
    }
    if ($input['run'] === '' && $run && $run['status'] !== 'complete') {
        externalSyncReject('invalid_snapshot_pointer', 409);
    }
    return ['connection' => $connection, 'run' => $run ?: null,
        'history' => array_slice($history, 0, 25), 'history_has_next' => count($history) > 25,
        'projection' => $run && in_array($run['status'], ['complete', 'review_required'], true)
            ? externalSyncAdminRecords($pdo, $connection, $run, $input['filter'], (int)$input['page']) : null];
}

/** Two streaming passes preserve whole-snapshot conflicts without keeping every JSON row in PHP arrays. */
function externalSyncAdminRecords(PDO $pdo, array $connection, array $run, string $filter, int $page): array
{
    $people = $connection['collection'] === 'sportsoft_events' ? [] : externalSyncPeople($pdo);
    $byUci = [];
    $byId = [];
    foreach ($people as $person) {
        $byId[(int)$person['id']] = $person;
        $uci = externalIdentityUci($person['uciid'])['uci'];
        if ($uci !== null) {
            $byUci[$uci][(int)$person['id']] = $person;
        }
    }
    unset($people);
    $links = externalSyncQuery($pdo, 'SELECT entity_key,uci_id,sportovec_id FROM external_identity_links WHERE connection_id=?', [$connection['id']]);
    $linksByKey = [];
    while ($link = $links->fetch(PDO::FETCH_ASSOC)) {
        $linksByKey[$link['entity_key']] = $link;
    }
    $conflicts = externalSyncSourceConflicts(externalSyncQuery(
        $pdo,
        'SELECT uci_id,row_json FROM external_sync_records WHERE run_id=?',
        [$run['id']]
    ));
    $query = externalSyncQuery($pdo, 'SELECT ordinal_no,part_no,entity_key,uci_id,row_json,match_status
        FROM external_sync_records WHERE run_id=? ORDER BY ordinal_no', [$run['id']]);
    $counts = array_fill_keys(array_keys(externalSyncAdminReasons()), 0);
    $rows = [];
    $total = 0;
    $selected = 0;
    $offset = (max(1, min(999999, $page)) - 1) * 50;
    while ($record = $query->fetch(PDO::FETCH_ASSOC)) {
        // Retain the former linked person even if their UCI changed, so S1's conflict guard still applies.
        $candidates = $byUci[$record['uci_id']] ?? [];
        $prior = $linksByKey[$record['entity_key'] ?? '']['sportovec_id'] ?? null;
        if ($prior !== null && isset($byId[(int)$prior])) {
            $candidates[(int)$prior] = $byId[(int)$prior];
        }
        $match = externalSyncResolveRecord($record, $candidates, $linksByKey, $conflicts, $connection['collection']);
        $counts[$match['status']]++;
        $total++;
        $needsReview = !in_array($match['status'], ['matched', 'not_applicable'], true);
        if ($filter !== 'all' && !($filter === 'needs_review' && $needsReview) && $filter !== $match['status']) {
            continue;
        }
        $selected++;
        if ($selected <= $offset || count($rows) >= 50) {
            continue;
        }
        $source = json_decode($record['row_json'], true, 16, JSON_THROW_ON_ERROR);
        $match['candidate_count'] = count($match['candidate_ids']);
        $match['candidate_ids'] = array_slice($match['candidate_ids'], 0, 10);
        $rows[] = ['ordinal' => (int)$record['ordinal_no'], 'part_no' => (int)$record['part_no'], 'match' => $match,
            'source' => array_intersect_key($source, externalSyncAdminFields($connection['collection'])),
            'normalized' => externalSyncNormalized($connection['collection'], $source), 'observed_at' => null];
    }
    // Source observation time belongs to the source batch, not the publication timestamp.
    $observed = [];
    $parts = array_values(array_unique(array_column($rows, 'part_no')));
    if ($parts !== []) {
        $query = externalSyncQuery($pdo, 'SELECT part_no,payload_json FROM external_sync_parts WHERE run_id=? AND part_no IN ('
            . implode(',', array_fill(0, count($parts), '?')) . ')', array_merge([$run['id']], $parts));
        while ($part = $query->fetch(PDO::FETCH_ASSOC)) {
            $batch = json_decode($part['payload_json'], true, 32, JSON_THROW_ON_ERROR);
            $observed[(int)$part['part_no']] = $batch['observed_at'] ?? null;
        }
    }
    foreach ($rows as &$row) {
        $row['observed_at'] = $observed[$row['part_no']] ?? null;
    }
    unset($row);
    return ['rows' => $rows, 'total' => $total, 'selected' => $selected, 'counts' => $counts,
        'needs_review' => $total - $counts['matched'] - $counts['not_applicable'], 'has_next' => $selected > $offset + 50];
}
