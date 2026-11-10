<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/club_event_shop.php';

/**
 * One fresh, display-only projection. Registration/checkout keep their scalar,
 * transaction-scoped capacity and eligibility checks.
 *
 * @return array{free:list<array<string,mixed>>,paid:list<array<string,mixed>>}
 */
function clubEventPublicOpenLists(PDO $pdo): array
{
    $public = clubEventColumnExists($pdo, 'club_events', 'planning_status')
        && clubEventColumnExists($pdo, 'club_events', 'visibility')
        ? " AND e.planning_status='confirmed' AND e.visibility='public'" : '';
    $free = $pdo->query(
        "SELECT e.* FROM club_events e WHERE e.status='open'" . $public . " AND e.event_type IN ('club_event','camp') "
        . "AND e.pricing_policy='free' AND (e.registration_starts_at IS NULL OR e.registration_starts_at<=CURRENT_TIMESTAMP) "
        . "AND (e.registration_ends_at IS NULL OR e.registration_ends_at>=CURRENT_TIMESTAMP) "
        . 'ORDER BY e.registration_ends_at IS NULL, e.registration_ends_at, e.name'
    )->fetchAll(PDO::FETCH_ASSOC);
    $paid = $pdo->query(
        "SELECT e.*,v.id AS variant_id,v.sku,v.amount_minor FROM club_events e JOIN shop_product_event_links l ON "
        . "l.event_id=e.id JOIN shop_products p ON p.id=l.product_id JOIN shop_variants v ON v.product_id=p.id WHERE e.event_type "
        . "IN ('club_event','camp') AND e.status='open'".$public." AND e.pricing_policy='product_variants' AND p.catalog_status='active' AND v.catalog_status='active' AND "
        . "v.price_mode='fixed' AND v.amount_minor>0 AND v.currency='CZK' AND (v.visible IS NULL OR v.visible=1) AND "
        . "(e.registration_starts_at IS NULL OR e.registration_starts_at<=CURRENT_TIMESTAMP) AND (e.registration_ends_at IS NULL "
        . "OR e.registration_ends_at>=CURRENT_TIMESTAMP) AND EXISTS (SELECT 1 FROM club_event_sessions s WHERE s.event_id=e.id AND "
        . "s.status='scheduled' AND s.ends_at>=CURRENT_TIMESTAMP) ORDER BY e.registration_starts_at,e.id,v.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_values(array_unique(array_map(
        static fn (array $event): int => (int) $event['id'],
        array_merge($free, $paid)
    )));
    $details = clubEventPublicCardDetails($pdo, $ids);
    foreach ($free as &$event) {
        $detail = $details[(int) $event['id']];
        $event['sessions'] = $detail['sessions'];
        $event['effective_capacity'] = $detail['capacity_override'] === null
            ? (int) $event['capacity'] : min((int) $event['capacity'], $detail['capacity_override']);
        $event['registration_count'] = $detail['occupied'];
        $event['remaining_capacity'] = max(0, $event['effective_capacity'] - $event['registration_count']);
        $event['waitlist_count'] = $detail['waitlist'];
    }
    unset($event);
    foreach ($paid as &$event) {
        $detail = $details[(int) $event['id']];
        $event['sessions'] = $detail['sessions'];
        $event['effective_capacity'] = $detail['capacity_override'] === null
            ? (int) $event['capacity'] : min((int) $event['capacity'], $detail['capacity_override']);
        $event['remaining_capacity'] = max(0, $event['effective_capacity'] - $detail['occupied']);
    }
    unset($event);
    return ['free' => $free, 'paid' => $paid];
}

/**
 * Read only the selected event IDs; duplicate paid variants share the read work.
 * The UNION keeps the original per-event distinct-person occupancy semantics.
 * 400 IDs keep even the two UNION parameter sets below SQLite's legacy limit.
 *
 * @param list<int> $ids
 * @return array<int,array{sessions:list<array<string,mixed>>,capacity_override:?int,occupied:int,waitlist:int}>
 */
function clubEventPublicCardDetails(PDO $pdo, array $ids): array
{
    if ($ids === []) {
        return [];
    }
    $details = [];
    foreach ($ids as $id) {
        $details[$id] = ['sessions' => [], 'capacity_override' => null, 'occupied' => 0, 'waitlist' => 0];
    }
    $hasPlanned = clubEventTableExists($pdo, 'club_event_planned_participants');
    foreach (array_chunk($ids, 400) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sessions = $pdo->prepare(
            "SELECT * FROM club_event_sessions WHERE event_id IN ($placeholders) AND status='scheduled' "
            . 'ORDER BY event_id,starts_at,id'
        );
        $sessions->execute($chunk);
        foreach ($sessions->fetchAll(PDO::FETCH_ASSOC) as $session) {
            $details[(int) $session['event_id']]['sessions'][] = $session;
        }
        $capacities = $pdo->prepare(
            'SELECT event_id,MIN(capacity_override) AS capacity_override FROM club_event_sessions '
            . "WHERE event_id IN ($placeholders) AND status='scheduled' AND capacity_override IS NOT NULL GROUP BY event_id"
        );
        $capacities->execute($chunk);
        foreach ($capacities->fetchAll(PDO::FETCH_ASSOC) as $capacity) {
            $details[(int) $capacity['event_id']]['capacity_override'] = (int) $capacity['capacity_override'];
        }
        if ($hasPlanned) {
            $occupied = $pdo->prepare(
                'SELECT event_id,COUNT(*) AS occupied FROM ('
                . "SELECT event_id,sportovec_id FROM club_event_planned_participants WHERE event_id IN ($placeholders) AND status='confirmed' "
                . "UNION SELECT event_id,sportovec_id FROM club_event_registrations WHERE event_id IN ($placeholders) "
                . "AND status IN ('confirmed','payment_pending')) occupied_people GROUP BY event_id"
            );
            $occupied->execute(array_merge($chunk, $chunk));
        } else {
            $occupied = $pdo->prepare(
                'SELECT event_id,COUNT(*) AS occupied FROM club_event_registrations '
                . "WHERE event_id IN ($placeholders) AND status IN ('confirmed','payment_pending') GROUP BY event_id"
            );
            $occupied->execute($chunk);
        }
        foreach ($occupied->fetchAll(PDO::FETCH_ASSOC) as $count) {
            $details[(int) $count['event_id']]['occupied'] = (int) $count['occupied'];
        }
        $waitlist = $pdo->prepare(
            'SELECT event_id,COUNT(*) AS waitlist FROM club_event_registrations '
            . "WHERE event_id IN ($placeholders) AND status='waitlisted' GROUP BY event_id"
        );
        $waitlist->execute($chunk);
        foreach ($waitlist->fetchAll(PDO::FETCH_ASSOC) as $count) {
            $details[(int) $count['event_id']]['waitlist'] = (int) $count['waitlist'];
        }
    }
    return $details;
}
