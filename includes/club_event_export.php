<?php
declare(strict_types=1);

final class ClubEventExportException extends RuntimeException
{
}

/**
 * @return array{contract_version:string,event:array<string,mixed>,rows:list<array<string,mixed>>}
 */
function clubEventParticipantExport(PDO $pdo, int $eventId): array
{
    if ($eventId < 1) {
        throw new InvalidArgumentException('Vyberte platnou klubovou akci.');
    }

    $eventStatement = $pdo->prepare(
        'SELECT id,code,name,event_type,status FROM club_events WHERE id=?'
    );
    $eventStatement->execute([$eventId]);
    $event = $eventStatement->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        throw new ClubEventExportException('Klubová akce neexistuje.');
    }

    $rowsStatement = $pdo->prepare(
        'SELECT r.id AS registration_id,r.sportovec_id,r.status,r.registered_at,'
        . 'r.waitlisted_at,r.promoted_at,r.cancelled_at,r.relation_role_snapshot,'
        . 'r.consent_version_snapshot,r.cancellation_deadline_snapshot,'
        . 'r.eligibility_team_ids_snapshot,r.eligibility_reason_snapshot,'
        . 's.jmeno AS participant_first_name,s.prijmeni AS participant_last_name,'
        . 's.narozeni AS participant_birth_date,vu.jmeno AS account_first_name,'
        . 'vu.prijmeni AS account_last_name,vu.email AS account_email '
        . 'FROM club_event_registrations r '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'WHERE r.event_id=? '
        . "ORDER BY CASE r.status WHEN 'confirmed' THEN 0 WHEN 'payment_pending' THEN 1 "
        . "WHEN 'waitlisted' THEN 2 WHEN 'cancelled' THEN 3 ELSE 4 END,"
        . 'COALESCE(r.waitlisted_at,r.registered_at),r.id'
    );
    $rowsStatement->execute([$eventId]);

    return [
        'contract_version' => 'm2.event-participants.v1',
        'event' => $event,
        'rows' => $rowsStatement->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/** @param array{contract_version:string,event:array<string,mixed>,rows:list<array<string,mixed>>} $export */
function clubEventParticipantExportCsv(array $export): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new ClubEventExportException('Export se nepodařilo připravit.');
    }

    fwrite($stream, "\xEF\xBB\xBF");
    $headers = [
        'export_contract', 'event_code', 'event_name', 'registration_id',
        'participant_id', 'last_name', 'first_name', 'birth_date', 'status',
        'registered_at', 'waitlisted_at', 'promoted_at', 'cancelled_at',
        'responsible_account_name', 'responsible_account_email', 'relation_role',
        'consent_version', 'cancellation_deadline', 'eligibility_team_ids',
        'eligibility_reason',
    ];
    fputcsv($stream, $headers, ';', '"', '');

    foreach ($export['rows'] as $row) {
        $values = [
            $export['contract_version'],
            $export['event']['code'],
            $export['event']['name'],
            $row['registration_id'],
            $row['sportovec_id'],
            $row['participant_last_name'],
            $row['participant_first_name'],
            $row['participant_birth_date'],
            $row['status'],
            $row['registered_at'],
            $row['waitlisted_at'],
            $row['promoted_at'],
            $row['cancelled_at'],
            trim((string)$row['account_first_name'] . ' ' . (string)$row['account_last_name']),
            $row['account_email'],
            $row['relation_role_snapshot'],
            $row['consent_version_snapshot'],
            $row['cancellation_deadline_snapshot'],
            $row['eligibility_team_ids_snapshot'],
            $row['eligibility_reason_snapshot'],
        ];
        fputcsv(
            $stream,
            array_map(static fn(mixed $value): string => clubEventExportCsvCell($value), $values),
            ';',
            '"',
            ''
        );
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    if ($csv === false) {
        throw new ClubEventExportException('Export se nepodařilo načíst.');
    }
    return $csv;
}

function clubEventExportCsvCell(mixed $value): string
{
    $cell = str_replace("\0", '', (string)($value ?? ''));
    if (preg_match('/^[\s]*[=+\-@]/u', $cell) === 1 || preg_match('/^[\t\r]/', $cell) === 1) {
        return "'" . $cell;
    }
    return $cell;
}

/** @param array{contract_version:string,event:array<string,mixed>,rows:list<array<string,mixed>>} $export */
function clubEventAuditParticipantExport(PDO $pdo, array $export, int $actorId): void
{
    if ($actorId < 1) {
        throw new InvalidArgumentException('Chybí administrátor exportu.');
    }
    $statusCounts = [];
    foreach ($export['rows'] as $row) {
        $status = (string)$row['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }
    clubEventAudit(
        $pdo,
        (int)$export['event']['id'],
        $actorId,
        'export_participants',
        'event',
        (int)$export['event']['id'],
        'Export účastníků do CSV.',
        [
            'contract_version' => $export['contract_version'],
            'row_count' => count($export['rows']),
            'status_counts' => $statusCounts,
        ]
    );
}

function clubEventParticipantExportFilename(array $event): string
{
    $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($event['code'] ?? 'event'));
    $code = trim((string)$code, '-');
    return 'ucastnici-' . ($code !== '' ? strtolower($code) : 'event') . '-' . date('Ymd-His') . '.csv';
}
