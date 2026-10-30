<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/family_calendar_feed.php';

function w83FamilyCalendarBaseline(PDO $pdo, int $accountId, string $from, string $to): array
{
    $fromDate = publicCalendarDate($from);
    $toDate = publicCalendarDate($to);
    if ($fromDate === null || $toDate === null || $toDate < $fromDate || $toDate->diff($fromDate)->days > 370) {
        throw new InvalidArgumentException('Neplatné období rodinného kalendáře.');
    }
    $people = familyPortalAuthorizedPeople($pdo, $accountId);
    $personIds = array_values(array_unique(array_map(static fn (array $person): int => (int)$person['sportovec_id'], $people)));
    if ($personIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($personIds), '?'));
    $items = [];

    $personDetails = [];
    $peopleStatement = $pdo->prepare('SELECT id,jmeno,prijmeni FROM sportovci WHERE id IN (' . $placeholders . ')');
    $peopleStatement->execute($personIds);
    foreach ($peopleStatement->fetchAll(PDO::FETCH_ASSOC) as $person) {
        $personDetails[(int)$person['id']] = $person;
    }
    $effectiveMembers = [];
    $membersForTarget = static function (int $teamId, string $date) use ($pdo, &$effectiveMembers): array {
        $key = $teamId . ':' . $date;
        if (!isset($effectiveMembers[$key])) {
            $effectiveMembers[$key] = array_fill_keys(kisRosterEffectiveMemberIds($pdo, $teamId, $date), true);
        }
        return $effectiveMembers[$key];
    };

    if (publicCalendarHasTables($pdo, ['training_roster_links', 'club_roster_members', 'planovane_treninky', 'sportovci', 'sportovist', 'skupiny'])) {
        $statement = $pdo->prepare(
            'SELECT p.id,p.datum,p.cas_od,p.cas_do,p.nazev,p.kategorie,s.nazev AS sportoviste,g.nazev AS skupina,'
            . 'l.team_id,l.team_name_snapshot FROM training_roster_links l '
            . 'JOIN planovane_treninky p ON p.id=l.plan_id LEFT JOIN sportovist s ON s.id=p.sportoviste_id '
            . 'LEFT JOIN skupiny g ON g.id=p.skupina_id '
            . "WHERE p.stav='planovany' AND p.datum BETWEEN ? AND ? ORDER BY p.datum,p.cas_od,p.id,l.team_id"
        );
        $statement->execute([$from, $to]);
        $seen = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members = $membersForTarget((int)$row['team_id'], (string)$row['datum']);
            foreach ($personDetails as $sportovecId => $person) {
                if (!isset($members[$sportovecId])) {
                    continue;
                }
                $key = (int)$row['id'] . ':' . $sportovecId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $item = familyCalendarTrainingItem($row + [
                    'sportovec_id' => $sportovecId,
                    'jmeno' => $person['jmeno'],
                    'prijmeni' => $person['prijmeni'],
                ]);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }
    }

    if (publicCalendarHasTables($pdo, ['club_event_registrations', 'club_events', 'club_event_sessions', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT r.id AS registration_id,r.status AS registration_status,r.sportovec_id,e.name,s.id AS session_id,'
            . 's.starts_at,s.ends_at,s.location,sp.jmeno,sp.prijmeni FROM club_event_registrations r '
            . 'JOIN club_events e ON e.id=r.event_id JOIN club_event_sessions s ON s.event_id=e.id '
            . 'JOIN sportovci sp ON sp.id=r.sportovec_id WHERE r.sportovec_id IN (' . $placeholders . ') '
            . "AND r.status IN ('confirmed','waitlisted','payment_pending') AND s.status='scheduled' "
            . 'AND s.starts_at<? AND s.ends_at>=? ORDER BY s.starts_at,s.id,r.id'
        );
        $statement->execute([...$personIds, $toDate->modify('+1 day')->format('Y-m-d 00:00:00'), $from . ' 00:00:00']);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = familyCalendarRegistrationStatus((string)$row['registration_status']);
            $item = publicCalendarTimedItem(
                'family-event-' . (int)$row['registration_id'] . '-session-' . (int)$row['session_id'],
                (string)$row['starts_at'],
                (string)$row['ends_at'],
                (string)$row['name'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                (string)$row['location'],
                'Stav přihlášky: ' . $status,
                'Rodinná akce'
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }
    }

    // Soupiskový předběžný plán patří do soukromého rodinného kalendáře ještě před přihlášením.
    if (publicCalendarHasTables($pdo, ['club_events','club_event_sessions','club_event_roster_targets','club_roster_members','sportovci'])
        && publicCalendarColumnExists($pdo, 'club_events', 'visibility')
        && publicCalendarColumnExists($pdo, 'club_events', 'planning_status')) {
        $statement = $pdo->prepare(
            'SELECT e.id AS event_id,e.name,e.planning_status,s.id AS session_id,s.starts_at,s.ends_at,s.location,'
            . 'rt.team_id FROM club_events e '
            . 'JOIN club_event_sessions s ON s.event_id=e.id JOIN club_event_roster_targets rt ON rt.event_id=e.id '
            . "WHERE e.visibility='rosters' "
            . "AND e.planning_status IN ('planned','confirmed') AND e.status<>'archived' AND s.status='scheduled' "
            . 'AND s.starts_at<? AND s.ends_at>=? ORDER BY s.starts_at,s.id,rt.team_id'
        );
        $statement->execute([$toDate->modify('+1 day')->format('Y-m-d 00:00:00'),$from.' 00:00:00']);
        $registrations = $pdo->prepare(
            'SELECT event_id,sportovec_id FROM club_event_registrations WHERE sportovec_id IN (' . $placeholders . ") AND status IN ('confirmed','waitlisted','payment_pending')"
        );
        $registrations->execute($personIds);
        $registered = [];
        foreach ($registrations->fetchAll(PDO::FETCH_ASSOC) as $registration) {
            $registered[(int)$registration['event_id'].':'.(int)$registration['sportovec_id']] = true;
        }
        $seenPlans = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eventDate = substr((string)$row['starts_at'], 0, 10);
            $members = $membersForTarget((int)$row['team_id'], $eventDate);
            foreach ($personDetails as $sportovecId => $person) {
                if (!isset($members[$sportovecId]) || isset($registered[(int)$row['event_id'].':'.$sportovecId])) {
                    continue;
                }
                $key = (int)$row['session_id'].':'.$sportovecId;
                if (isset($seenPlans[$key])) {
                    continue;
                }
                $seenPlans[$key] = true;
                $state = (string)$row['planning_status'] === 'planned' ? 'Předběžný plán – termín se může změnit.' : 'Potvrzená akce, účast zatím není přihlášená.';
                $item = publicCalendarTimedItem(
                    'family-plan-'.(int)$row['event_id'].'-'.(int)$row['session_id'].'-'.$sportovecId,
                    (string)$row['starts_at'],
                    (string)$row['ends_at'],
                    (string)$row['name'].' – '.trim((string)$person['jmeno'].' '.(string)$person['prijmeni']),
                    (string)$row['location'],
                    $state,
                    'Rodinný plán'
                );
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }
    }

    if (publicCalendarHasTables($pdo, ['verejne_rezervace', 'individualni_lekce', 'sportovist', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT r.id AS reservation_id,r.stav,r.sportovec_id,il.datum,il.cas_od,il.cas_do,il.nazev,'
            . 's.nazev AS sportoviste,sp.jmeno,sp.prijmeni FROM verejne_rezervace r '
            . 'JOIN individualni_lekce il ON il.id=r.lekce_id JOIN sportovist s ON s.id=il.sportoviste_id '
            . 'JOIN sportovci sp ON sp.id=r.sportovec_id WHERE r.sportovec_id IN (' . $placeholders . ') '
            . "AND r.stav IN ('ceka','potvrzena') AND il.stav='aktivni' AND il.datum BETWEEN ? AND ? "
            . 'ORDER BY il.datum,il.cas_od,r.id'
        );
        $statement->execute([...$personIds, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = publicCalendarTimedItem(
                'family-reservation-' . (int)$row['reservation_id'],
                (string)$row['datum'] . ' ' . (string)$row['cas_od'],
                (string)$row['datum'] . ' ' . (string)$row['cas_do'],
                (string)$row['nazev'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                (string)$row['sportoviste'],
                'Stav rezervace: ' . ((string)$row['stav'] === 'potvrzena' ? 'potvrzená' : 'čeká na potvrzení'),
                'Rodinná rezervace'
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }
    }

    if (publicCalendarHasTables($pdo, ['club_member_charges', 'sportovci'])) {
        $statement = $pdo->prepare(
            'SELECT c.id,c.due_on,c.title_snapshot,c.amount_minor,c.currency,c.sportovec_id,sp.jmeno,sp.prijmeni '
            . 'FROM club_member_charges c JOIN sportovci sp ON sp.id=c.sportovec_id '
            . 'WHERE c.sportovec_id IN (' . $placeholders . ") AND c.status='pending' AND c.due_on BETWEEN ? AND ? "
            . 'ORDER BY c.due_on,c.id'
        );
        $statement->execute([...$personIds, $from, $to]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = publicCalendarDate((string)$row['due_on']);
            if ($date === null) {
                continue;
            }
            $items[] = [
                'uid' => 'family-charge-' . (int)$row['id'], 'start' => $date->format('Y-m-d'),
                'end' => $date->modify('+1 day')->format('Y-m-d'), 'all_day' => true,
                'summary' => 'Splatnost: ' . (string)$row['title_snapshot'] . ' – ' . trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
                'location' => '', 'description' => number_format(((int)$row['amount_minor']) / 100, 2, ',', ' ') . ' ' . (string)$row['currency'],
                'category' => 'Členský předpis',
            ];
        }
    }

    usort($items, static fn (array $a, array $b): int => [$a['start'], $a['uid']] <=> [$b['start'], $b['uid']]);
    return $items;
}
