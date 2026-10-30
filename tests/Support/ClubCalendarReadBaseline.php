<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2).'/includes/club_calendar.php';
function w84BaselineClubCalendarEvents(PDO $pdo, string $from, string $to, ?int $accountId = null, bool $staff = false): array
{
    $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$fromDate || !$toDate || $fromDate > $toDate) {
        throw new InvalidArgumentException('Neplatný rozsah kalendáře.');
    }
    $statement = $pdo->prepare(
        'SELECT e.*,s.id session_id,s.starts_at,s.ends_at,s.location '
        . 'FROM club_events e JOIN club_event_sessions s ON s.event_id=e.id AND s.status=\'scheduled\' '
        . 'WHERE s.starts_at<? AND s.ends_at>=? AND e.status<>\'archived\' ORDER BY s.starts_at,e.name,e.id'
    );
    $statement->execute([$toDate->modify('+1 day')->format('Y-m-d 00:00:00'),$fromDate->format('Y-m-d 00:00:00')]);
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!$staff && !clubCalendarAccountCanSee($pdo, (int)$row['id'], $accountId, (string)$row['visibility'])) {
            continue;
        }
        $row['teams'] = clubEventRosterTargets($pdo, (int)$row['id']);
        $row['links'] = clubCalendarLinks($pdo, (int)$row['id']);
        $row['people'] = clubCalendarPeople($pdo, (int)$row['id'], $staff);
        $row['registration_count'] = clubCalendarConfirmedCount($pdo, (int)$row['id']);
        $row['effective_capacity'] = clubEventEffectiveCapacity($pdo, (int)$row['id'], (int)$row['capacity']);
        $row['vehicle_conflicts'] = $staff ? w84BaselineClubCalendarVehicleConflictsForEvent($pdo, (int)$row['id']) : [];
        if (!$staff) {
            unset($row['internal_note']);
        }
        $rows[] = $row;
    }
    return $rows;
}
function w84BaselineClubCalendarDetail(PDO $pdo, int $eventId): ?array
{
    $statement = $pdo->prepare('SELECT e.*,s.id session_id,s.starts_at,s.ends_at,s.location FROM club_events e '
        . "LEFT JOIN club_event_sessions s ON s.event_id=e.id AND s.status='scheduled' WHERE e.id=? ORDER BY s.starts_at LIMIT 1");
    $statement->execute([$eventId]);
    $event = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        return null;
    }
    $event['teams'] = clubEventRosterTargets($pdo, $eventId);
    $event['people'] = clubCalendarPeople($pdo, $eventId, true);
    $event['links'] = clubCalendarLinks($pdo, $eventId);
    $event['vehicles'] = w84BaselineClubCalendarVehicles($pdo, $eventId);
    $event['participants'] = clubCalendarParticipants($pdo, $eventId);
    $event['vehicle_conflicts'] = w84BaselineClubCalendarVehicleConflictsForEvent($pdo, $eventId);
    return $event;
}
function w84BaselineClubCalendarVehicles(PDO $pdo, int $eventId): array
{
    $s = $pdo->prepare('SELECT r.*,v.znacka_model,v.spz,t.jmeno driver_trainer_name FROM club_event_vehicle_reservations r JOIN ucto_vozidla v '
        . 'ON v.id=r.vehicle_id LEFT JOIN treneri t ON t.id=r.driver_trainer_id WHERE r.event_id=? ORDER BY r.starts_at,r.id');
    $s->execute([$eventId]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['conflicts'] = clubCalendarVehicleConflicts($pdo, (int)$row['vehicle_id'], (string)$row['starts_at'], (string)$row['ends_at'], (int)$row['id']);
    }unset($row);
    return$rows;
}
function w84BaselineClubCalendarVehicleConflictsForEvent(PDO$pdo, int$eventId): array
{
    $conflicts = [];
    foreach (w84BaselineClubCalendarVehicles($pdo, $eventId) as $row) {
        foreach ($row['conflicts'] as $conflict) {
            $conflicts[] = ['reservation_id' => (int)$row['id'],'vehicle' => (string)$row['znacka_model'].' '.(string)$row['spz'],'conflict' => $conflict];
        }
    }return$conflicts;
}
