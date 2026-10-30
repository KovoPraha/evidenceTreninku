<?php

declare(strict_types=1);

/** Request-local read projection. Call only with rows whose visibility was already checked. */
final class ClubCalendarRead
{
    public static function hydrate(PDO $pdo, array $rows, bool $staff): array
    {
        $data = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', array_column($rows, 'id')))), 400) as $ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach ($ids as $id) {
                $data[$id] = ['teams' => [], 'links' => [], 'people' => [], 'registration_count' => 0,
                    'capacity' => null, 'vehicle_conflicts' => []];
            }
            $queries = [
                'teams' => 'SELECT rt.event_id,rt.team_id,rt.actor_trainer_id,rt.decision_note,rt.created_at, '
                    . 't.code AS team_code,t.name AS team_name,t.status AS team_status, '
                    . 's.name AS season_name,s.starts_on,s.ends_on,s.status AS season_status '
                    . 'FROM club_event_roster_targets rt JOIN club_teams t ON t.id=rt.team_id '
                    . 'JOIN club_seasons s ON s.id=t.season_id WHERE rt.event_id IN (' . $marks . ') AND rt.event_id>0 ORDER BY t.name,t.id',
                'links' => 'SELECT * FROM club_event_links WHERE event_id IN (' . $marks . ') ORDER BY sort_order,id',
                'people' => 'SELECT p.*,t.jmeno trainer_name FROM club_event_people p LEFT JOIN treneri t ON t.id=p.trainer_id '
                    . 'WHERE p.event_id IN (' . $marks . ')' . ($staff ? '' : ' AND p.visible_to_members=1') . ' ORDER BY p.id',
            ];
            foreach ($queries as $key => $sql) {
                $statement = $pdo->prepare($sql);
                $statement->execute($ids);
                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
                    if ($key === 'people' && !$staff) {
                        unset($item['external_contact'], $item['note']);
                    }
                    $data[(int) $item['event_id']][$key][] = $item;
                }
            }
            // UNION retains a single NULL person too, exactly like the individual COUNT(*) helper.
            $statement = $pdo->prepare('SELECT event_id,COUNT(*) total FROM ('
                . "SELECT event_id,sportovec_id FROM club_event_planned_participants WHERE event_id IN ($marks) AND status='confirmed' "
                . "UNION SELECT event_id,sportovec_id FROM club_event_registrations WHERE event_id IN ($marks) "
                . "AND status IN ('confirmed','payment_pending')) confirmed_people GROUP BY event_id");
            $statement->execute(array_merge($ids, $ids));
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $data[(int) $item['event_id']]['registration_count'] = (int) $item['total'];
            }
            $statement = $pdo->prepare('SELECT event_id,MIN(capacity_override) capacity FROM club_event_sessions '
                . "WHERE event_id IN ($marks) AND status='scheduled' AND capacity_override IS NOT NULL GROUP BY event_id");
            $statement->execute($ids);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $item) {
                $data[(int) $item['event_id']]['capacity'] = (int) $item['capacity'];
            }
            if ($staff) {
                foreach (self::vehicles($pdo, $ids) as $item) {
                    foreach (self::flattenConflicts([$item]) as $conflict) {
                        $data[(int) $item['event_id']]['vehicle_conflicts'][] = $conflict;
                    }
                }
            }
        }
        foreach ($rows as &$row) {
            $item = $data[(int) $row['id']];
            foreach (['teams', 'links', 'people', 'registration_count'] as $key) {
                $row[$key] = $item[$key];
            }
            $row['effective_capacity'] = $item['capacity'] === null ? (int) $row['capacity'] : min((int) $row['capacity'], $item['capacity']);
            $row['vehicle_conflicts'] = $item['vehicle_conflicts'];
            if (!$staff) {
                unset($row['internal_note']);
            }
        }
        unset($row);
        return $rows;
    }

    public static function vehicles(PDO $pdo, array $eventIds): array
    {
        $rows = [];
        foreach (array_chunk(array_values(array_unique($eventIds)), 400) as $ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare('SELECT r.*,v.znacka_model,v.spz,t.jmeno driver_trainer_name '
                . 'FROM club_event_vehicle_reservations r JOIN ucto_vozidla v ON v.id=r.vehicle_id '
                . 'LEFT JOIN treneri t ON t.id=r.driver_trainer_id WHERE r.event_id IN (' . $marks . ') ORDER BY r.starts_at,r.id');
            $statement->execute($ids);
            array_push($rows, ...$statement->fetchAll(PDO::FETCH_ASSOC));
        }
        $conflicts = [];
        foreach (array_chunk($rows, 200) as $batch) {
            $sources = [];
            $params = [];
            foreach ($batch as $row) {
                $sources[] = 'SELECT ? source_id,? vehicle_id,? starts_at,? ends_at';
                array_push($params, (int) $row['id'], (int) $row['vehicle_id'], (string) $row['starts_at'], (string) $row['ends_at']);
            }
            // Use the source interval already read, rather than re-reading potentially changed source rows.
            $statement = $pdo->prepare('SELECT source.source_id,r.id,r.event_id,r.starts_at,r.ends_at,e.name,e.planning_status '
                . 'FROM (' . implode(' UNION ALL ', $sources) . ') source JOIN club_event_vehicle_reservations r '
                . "ON r.vehicle_id=source.vehicle_id AND r.status='active' AND r.id<>source.source_id "
                . 'AND r.starts_at<source.ends_at AND r.ends_at>source.starts_at JOIN club_events e ON e.id=r.event_id '
                . 'ORDER BY source.source_id,r.starts_at,r.id');
            $statement->execute($params);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $conflict) {
                $sourceId = (int) $conflict['source_id'];
                unset($conflict['source_id']);
                $conflicts[$sourceId][] = $conflict;
            }
        }
        foreach ($rows as &$row) {
            $row['conflicts'] = $conflicts[(int) $row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    public static function flattenConflicts(array $vehicles): array
    {
        $conflicts = [];
        foreach ($vehicles as $row) {
            foreach ($row['conflicts'] as $conflict) {
                $conflicts[] = ['reservation_id' => (int) $row['id'], 'vehicle' => (string) $row['znacka_model'] . ' ' . (string) $row['spz'], 'conflict' => $conflict];
            }
        }
        return $conflicts;
    }
}
