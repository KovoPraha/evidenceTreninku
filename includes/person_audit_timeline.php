<?php
declare(strict_types=1);

final class PersonAuditTimelineException extends RuntimeException
{
}

/** @return list<array<string,mixed>> */
function personAuditSearch(PDO $pdo, string $query, int $limit = 30): array
{
    $query = trim($query);
    $limit = max(1, min(100, $limit));
    if ($query === '') {
        return [];
    }
    if (mb_strlen($query, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Vyhledávací dotaz smí mít nejvýše 100 znaků.');
    }

    if (ctype_digit($query)) {
        $statement = $pdo->prepare(
            'SELECT id,jmeno,prijmeni,narozeni,stav_clenstvi FROM sportovci WHERE id=? LIMIT 1'
        );
        $statement->execute([(int)$query]);
    } else {
        $like = '%' . personAuditEscapeLike($query) . '%';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $pdo->prepare(
                "SELECT id,jmeno,prijmeni,narozeni,stav_clenstvi FROM sportovci "
                . "WHERE (jmeno || ' ' || prijmeni) LIKE ? ESCAPE '!' "
                . "OR (prijmeni || ' ' || jmeno) LIKE ? ESCAPE '!' "
                . 'ORDER BY prijmeni,jmeno,id LIMIT ' . $limit
            );
        } else {
            $statement = $pdo->prepare(
                "SELECT id,jmeno,prijmeni,narozeni,stav_clenstvi FROM sportovci "
                . "WHERE CONCAT_WS(' ',jmeno,prijmeni) LIKE ? ESCAPE '!' "
                . "OR CONCAT_WS(' ',prijmeni,jmeno) LIKE ? ESCAPE '!' "
                . 'ORDER BY prijmeni,jmeno,id LIMIT ' . $limit
            );
        }
        $statement->execute([$like, $like]);
    }
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed>|null */
function personAuditPerson(PDO $pdo, int $sportovecId): ?array
{
    if ($sportovecId < 1) {
        return null;
    }
    $statement = $pdo->prepare(
        'SELECT id,jmeno,prijmeni,narozeni,email,telefon,stav_clenstvi FROM sportovci WHERE id=?'
    );
    $statement->execute([$sportovecId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Read-only, person-scoped aggregation of existing append-only event streams.
 *
 * @return array{person:array<string,mixed>,events:list<array<string,mixed>>,page:int,page_size:int,has_previous:bool,has_next:bool}
 */
function personAuditTimeline(PDO $pdo, int $sportovecId, int $page = 1, int $pageSize = 50): array
{
    $person = personAuditPerson($pdo, $sportovecId);
    if ($person === null) {
        throw new PersonAuditTimelineException('Sportovec nebyl nalezen.');
    }
    $page = max(1, min(100, $page));
    $pageSize = max(10, min(100, $pageSize));
    $offset = ($page - 1) * $pageSize;
    $sourceLimit = $offset + $pageSize + 1;
    $events = [];

    $sources = personAuditSources();
    foreach ($sources as $source) {
        foreach ($source['tables'] as $table) {
            if (!personAuditTableExists($pdo, $table)) {
                continue 2;
            }
        }
        $statement = $pdo->prepare($source['sql'] . ' LIMIT ' . $sourceLimit);
        $statement->execute([$sportovecId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $events[] = personAuditNormalize($row, $source);
        }
    }

    personAuditResolveActors($pdo, $events);
    usort($events, static function (array $left, array $right): int {
        return [$right['occurred_at'], $right['source'], $right['source_id']]
            <=> [$left['occurred_at'], $left['source'], $left['source_id']];
    });
    $hasNext = count($events) > $offset + $pageSize;
    $events = array_slice($events, $offset, $pageSize);

    return [
        'person' => $person,
        'events' => $events,
        'page' => $page,
        'page_size' => $pageSize,
        'has_previous' => $page > 1,
        'has_next' => $hasNext,
    ];
}

/** @return list<array{tables:list<string>,sql:string,source:string,label:string,link:string}> */
function personAuditSources(): array
{
    return [
        [
            'tables' => ['account_person_roles', 'account_person_role_events'],
            'source' => 'identity_relation', 'label' => 'Vztah účtu a osoby',
            'link' => 'eshop_identity_admin.php',
            'sql' => "SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,"
                . "e.actor_trainer_id AS actor_id,'trainer' AS actor_type,e.from_status,e.to_status,NULL AS payload_json "
                . 'FROM account_person_role_events e JOIN account_person_roles r ON r.id=e.relation_id '
                . 'WHERE r.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['child_access_accounts', 'child_access_events'],
            'source' => 'child_access', 'label' => 'Přístup sportovce',
            'link' => 'kis_child_access_admin.php',
            'sql' => 'SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,'
                . "e.actor_id,e.actor_type,NULL AS from_status,NULL AS to_status,NULL AS payload_json "
                . 'FROM child_access_events e JOIN child_access_accounts a ON a.id=e.access_account_id '
                . 'WHERE a.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['shop_orders', 'shop_order_items', 'shop_order_events'],
            'source' => 'shop_order', 'label' => 'Objednávka e-shopu',
            'link' => 'eshop_orders_admin.php',
            'sql' => 'SELECT DISTINCT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,'
                . 'e.actor_id,e.actor_type,e.from_status,e.to_status,NULL AS payload_json '
                . 'FROM shop_order_events e JOIN shop_order_items i ON i.order_id=e.order_id '
                . 'WHERE i.beneficiary_sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['club_program_enrollments', 'club_program_enrollment_events'],
            'source' => 'club_program', 'label' => 'Kroužkový program',
            'link' => 'club_programs_admin.php',
            'sql' => 'SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,NULL AS reason,'
                . 'e.actor_id,e.actor_type,NULL AS from_status,NULL AS to_status,e.payload_json '
                . 'FROM club_program_enrollment_events e JOIN club_program_enrollments n ON n.id=e.enrollment_id '
                . 'WHERE n.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['club_roster_members', 'club_roster_events'],
            'source' => 'roster', 'label' => 'Soupiska',
            'link' => 'kis_rosters_admin.php',
            'sql' => 'SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,'
                . "e.actor_trainer_id AS actor_id,'trainer' AS actor_type,NULL AS from_status,NULL AS to_status,e.after_json AS payload_json "
                . 'FROM club_roster_events e JOIN club_roster_members m ON m.id=e.roster_member_id '
                . 'WHERE m.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['club_roster_rollover_runs', 'club_roster_rollover_run_items'],
            'source' => 'roster_rollover', 'label' => 'Obnova soupisky',
            'link' => 'kis_rosters_admin.php',
            'sql' => "SELECT i.id AS source_id,r.created_at AS occurred_at,i.action,r.reason,"
                . "r.actor_trainer_id AS actor_id,'trainer' AS actor_type,NULL AS from_status,NULL AS to_status,i.after_json AS payload_json "
                . 'FROM club_roster_rollover_run_items i JOIN club_roster_rollover_runs r ON r.id=i.run_id '
                . 'WHERE i.sportovec_id=? ORDER BY r.created_at DESC,i.id DESC',
        ],
        [
            'tables' => ['club_event_registrations', 'club_event_registration_events'],
            'source' => 'event_registration', 'label' => 'Přihláška na událost',
            'link' => 'eshop_events_admin.php',
            'sql' => 'SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,'
                . 'e.actor_id,e.actor_type,e.from_status,e.to_status,NULL AS payload_json '
                . 'FROM club_event_registration_events e JOIN club_event_registrations r ON r.id=e.registration_id '
                . 'WHERE r.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
        [
            'tables' => ['public_profile_events'],
            'source' => 'public_profile', 'label' => 'Veřejný profil',
            'link' => 'eshop_identity_admin.php',
            'sql' => "SELECT id AS source_id,created_at AS occurred_at,action,NULL AS reason,"
                . "account_id AS actor_id,'account' AS actor_type,NULL AS from_status,NULL AS to_status,payload_json "
                . 'FROM public_profile_events WHERE sportovec_id=? ORDER BY created_at DESC,id DESC',
        ],
        [
            'tables' => ['verejne_rezervace', 'public_velodrome_reservation_events'],
            'source' => 'velodrome', 'label' => 'Rezervace velodromu',
            'link' => 'verejny_velodrom_admin.php',
            'sql' => 'SELECT e.id AS source_id,e.created_at AS occurred_at,e.action,e.note AS reason,'
                . 'e.actor_id,e.actor_type,e.from_status,e.to_status,NULL AS payload_json '
                . 'FROM public_velodrome_reservation_events e JOIN verejne_rezervace r ON r.id=e.reservation_id '
                . 'WHERE r.sportovec_id=? ORDER BY e.created_at DESC,e.id DESC',
        ],
    ];
}

/** @param array<string,mixed> $row @param array<string,mixed> $source @return array<string,mixed> */
function personAuditNormalize(array $row, array $source): array
{
    $payload = personAuditDecodeJson($row['payload_json'] ?? null);
    $reason = trim((string)($row['reason'] ?? ''));
    if ($reason === '') {
        foreach (['reason', 'note', 'ended_reason'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && trim((string)$payload[$key]) !== '') {
                $reason = trim((string)$payload[$key]);
                break;
            }
        }
    }
    return [
        'source' => $source['source'], 'source_label' => $source['label'],
        'source_id' => (int)$row['source_id'], 'source_link' => $source['link'],
        'occurred_at' => (string)$row['occurred_at'], 'action' => (string)$row['action'],
        'from_status' => $row['from_status'] ?? null, 'to_status' => $row['to_status'] ?? null,
        'reason' => $reason !== '' ? $reason : null,
        'actor_type' => (string)($row['actor_type'] ?? 'unknown'),
        'actor_id' => isset($row['actor_id']) ? (int)$row['actor_id'] : null,
        'actor_label' => null,
    ];
}

/** @param list<array<string,mixed>> $events */
function personAuditResolveActors(PDO $pdo, array &$events): void
{
    $ids = ['trainer' => [], 'account' => []];
    foreach ($events as $event) {
        $type = $event['actor_type'];
        if (isset($ids[$type]) && (int)($event['actor_id'] ?? 0) > 0) {
            $ids[$type][(int)$event['actor_id']] = true;
        }
    }
    $labels = ['trainer' => [], 'account' => []];
    foreach ([
        'trainer' => ['treneri', "COALESCE(NULLIF(jmeno,''),email,CAST(id AS CHAR))"],
        'account' => ['verejni_uzivatele', "TRIM(COALESCE(jmeno,'') || ' ' || COALESCE(prijmeni,''))"],
    ] as $type => [$table, $expression]) {
        if ($ids[$type] === [] || !personAuditTableExists($pdo, $table)) {
            continue;
        }
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($type === 'account' && $driver === 'mysql') {
            $expression = "TRIM(CONCAT(COALESCE(jmeno,''),' ',COALESCE(prijmeni,'')))";
        }
        if ($type === 'trainer' && $driver === 'sqlite') {
            $expression = "COALESCE(NULLIF(jmeno,''),email,CAST(id AS TEXT))";
        }
        $numericIds = array_keys($ids[$type]);
        $placeholders = implode(',', array_fill(0, count($numericIds), '?'));
        $statement = $pdo->prepare("SELECT id,$expression AS actor_label FROM $table WHERE id IN ($placeholders)");
        $statement->execute($numericIds);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $labels[$type][(int)$row['id']] = trim((string)$row['actor_label']);
        }
    }
    foreach ($events as &$event) {
        $type = $event['actor_type'];
        $id = (int)($event['actor_id'] ?? 0);
        if ($id > 0 && isset($labels[$type][$id]) && $labels[$type][$id] !== '') {
            $event['actor_label'] = $labels[$type][$id];
        } elseif ($id > 0) {
            $event['actor_label'] = $type . ' #' . $id;
        } else {
            $event['actor_label'] = $type !== '' ? $type : 'nezaznamenáno';
        }
    }
    unset($event);
}

/** @return array<string,mixed> */
function personAuditDecodeJson(mixed $json): array
{
    if (!is_string($json) || trim($json) === '' || strlen($json) > 1000000) {
        return [];
    }
    try {
        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (JsonException) {
        return [];
    }
}

function personAuditTableExists(PDO $pdo, string $table): bool
{
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        return false;
    }
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    throw new PersonAuditTimelineException('Nepodporovaný databázový ovladač.');
}

function personAuditEscapeLike(string $value): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
}
