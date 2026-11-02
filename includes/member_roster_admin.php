<?php

declare(strict_types=1);

require_once __DIR__ . '/roster_hierarchy.php';
require_once __DIR__ . '/operational_cases.php';
require_once __DIR__ . '/member_roster_preview.php';
require_once __DIR__ . '/member_roster_age.php';

function memberRosterActor(PDO $pdo): int
{
    if (!staffActivePositionIs('registrar')) {
        throw new DomainException('Správa členství patří registráři.');
    }
    $actor = (int) ($_SESSION['trener_id'] ?? 0);
    operationalCaseActor($pdo, $actor, 'registrar');
    return $actor;
}

/** All web mutations use one transaction and re-check the current assignment under lock. */
function memberRosterMutate(PDO $pdo, array $input): array
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Správa členství vyžaduje vlastní transakci.');
    }
    $reason = kisRosterText((string) ($input['reason'] ?? ''), 1000, 'Důvod');
    $pdo->beginTransaction();
    try {
        $actor = memberRosterActor($pdo);
        $action = (string) ($input['action'] ?? '');
        $teamId = (int) ($input['team_id'] ?? 0);
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $memberAction = in_array($action, ['grant','end_member','transfer'], true);
        if ($memberAction) {
            $fingerprint = (string) ($input['fingerprint'] ?? '');
            $requestJson = json_encode(memberRosterRequest($input), JSON_THROW_ON_ERROR);
            $query = $pdo->prepare('SELECT * FROM member_roster_operations WHERE fingerprint=?' . $lock);
            $query->execute([$fingerprint]);
            $previous = $query->fetch(PDO::FETCH_ASSOC);
            if ($previous) {
                if ((int) $previous['actor_trainer_id'] !== $actor || $previous['request_json'] !== $requestJson) {
                    throw new KisRosterException('Potvrzení nepatří k této změně.');
                }
                $pdo->commit();
                return json_decode($previous['result_json'], true, 512, JSON_THROW_ON_ERROR) + ['idempotent'
    => true];
            }
            $preview = memberRosterPreview($pdo, $input);
            if (!hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new KisRosterException('Nejdřív potvrďte aktuální náhled. Údaje se mohly změnit.');
            }
            if ($preview['impact']['blocked']) {
                throw new KisRosterException('Sportovec má dotčené budoucí odpovědi nebo přihlášky. Nejdřív je vyřešte v uvedených aktivitách.');
            }
        }
        if ($action === 'create') {
            $kind = kisRosterEnum(
                (string) ($input['activity_kind'] ?? ''),
                ['circle', 'competitive', 'other'],
                'Zaměření'
            );
            $result = kisRosterCreateStandingTeam(
                $pdo,
                $actor,
                (string) ($input['code'] ?? ''),
                (string) ($input['name'] ?? ''),
                (string) ($input['kind'] ?? ''),
                $reason
            );
            $pdo->prepare('UPDATE club_teams SET activity_kind=? WHERE id=?')->execute([$kind, $result['id']]);
            kisRosterStructureEvent(
                $pdo,
                'team',
                (int) $result['id'],
                $actor,
                'set_activity_kind',
                $reason,
                [],
                ['activity_kind' => $kind]
            );
        } elseif ($action === 'age_rule') {
            $result = memberRosterAgeSave($pdo, $actor, $teamId, $input, $reason);
        } elseif ($action === 'update_team') {
            $query = $pdo->prepare('SELECT * FROM club_teams WHERE id=?' . $lock);
            $query->execute([$teamId]);
            $before = $query->fetch(PDO::FETCH_ASSOC);
            if (!$before || $before['status'] !== 'active') {
                throw new KisRosterException('Aktivní skupina nebo soupiska nebyla nalezena.');
            }
            $name = kisRosterText((string) ($input['name'] ?? ''), 160, 'Název');
            $kind = kisRosterEnum(
                (string) ($input['activity_kind'] ?? ''),
                ['circle', 'competitive', 'other'],
                'Zaměření'
            );
            $pdo->prepare('UPDATE club_teams SET name=?,activity_kind=? WHERE id=?')->execute([$name, $kind,

     $teamId]);
            kisRosterStructureEvent(
                $pdo,
                'team',
                $teamId,
                $actor,
                'update_team',
                $reason,
                $before,
                array_replace($before, ['name' => $name, 'activity_kind' => $kind])
            );
            $result = ['id' => $teamId];
        } elseif ($action === 'attach') {
            $result = kisRosterAttachChildren(
                $pdo,
                (int) ($input['parent_team_id'] ?? 0),
                (array) ($input['child_ids'] ?? []),
                (string) ($input['valid_from'] ?? ''),
                $actor,
                $reason,
                (string) ($input['fingerprint'] ?? '')
            );
        } elseif ($action === 'end') {
            $result = kisRosterEndHierarchyEdge(
                $pdo,
                (int) ($input['edge_id'] ?? 0),
                $actor,
                (string) ($input['valid_to'] ?? ''),
                $reason,
                (string) ($input['fingerprint'] ?? '')
            );
        } elseif ($action === 'grant') {
            $ids = array_values(array_unique(array_map('intval', (array) ($input['person_ids'] ?? []))));
            if (!$ids || count($ids) > 100 || min($ids) < 1) {
                throw new InvalidArgumentException('Vyberte 1 až 100 sportovců.');
            }
            sort($ids, SORT_NUMERIC);
            $result = ['created_count' => 0];
            foreach ($ids as $personId) {
                $grant = kisRosterGrantPeriod(
                    $pdo,
                    $teamId,
                    $personId,
                    $actor,
                    'manual',
                    (string) ($input['valid_from'] ?? ''),
                    trim((string) ($input['valid_to'] ?? '')) ?: null,
                    $reason
                );
                $result['created_count'] += (int) $grant['created'];
            }
        } elseif (in_array($action, ['end_member', 'transfer'], true)) {
            $query = $pdo->prepare('SELECT * FROM club_roster_members WHERE id=? AND team_id=?' . $lock);
            $query->execute([(int) ($input['member_id'] ?? 0), $teamId]);
            $member = $query->fetch(PDO::FETCH_ASSOC);
            if (!$member || $member['status'] === 'cancelled') {
                throw new KisRosterException('Platné členství nebylo nalezeno.');
            }
            if ($member['source'] !== 'manual') {
                throw new KisRosterException('Tento nárok spravujte přes jeho zdrojovou účast nebo objednávku.');
            }
            if ($action === 'transfer') {
                $from = kisRosterDate((string) ($input['valid_from'] ?? ''));
                $targetId = (int) ($input['target_team_id'] ?? 0);
                if ($targetId === $teamId || $from <= $member['valid_from'] || ($member['valid_to'] !== null
    && $from > $member['valid_to'])) {
                    throw new InvalidArgumentException('Přestup vyžaduje jinou soupisku a den uvnitř původního členství po jeho začátku.');
                }
                $until = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
                $result = kisRosterEndPeriod($pdo, (int) $member['id'], $actor, $until, $reason);
                $result['target'] = kisRosterGrantPeriod(
                    $pdo,
                    $targetId,
                    (int) $member['sportovec_id'],
                    $actor,
                    'manual',
                    $from,
                    $member['valid_to'],
                    $reason
                );
            } else {
                $result = kisRosterEndPeriod(
                    $pdo,
                    (int) $member['id'],
                    $actor,
                    (string) ($input['valid_to'] ?? ''),
                    $reason
                );
            }
        } else {
            throw new InvalidArgumentException('Neznámá akce správy členství.');
        }
        if ($memberAction) {
            $pdo->prepare('INSERT INTO member_roster_operations(fingerprint,actor_trainer_id,request_json,result_json) VALUES(?,?,?,?)')
                ->execute([$fingerprint,$actor,$requestJson,json_encode($result, JSON_THROW_ON_ERROR)]);
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/** One roster, including future and ended intervals; no dependency on seasons. */
function memberRosterRead(PDO $pdo, int $teamId, string $on): array
{
    memberRosterActor($pdo);
    $on = kisRosterDate($on);
    $query = $pdo->prepare('SELECT * FROM club_teams WHERE id=?');
    $query->execute([$teamId]);
    $team = $query->fetch(PDO::FETCH_ASSOC);
    if (!$team) {
        throw new KisRosterException('Skupina nebo soupiska nebyla nalezena.');
    }
    $query = $pdo->prepare('SELECT m.*,p.jmeno,p.prijmeni FROM club_roster_members m '
        . 'JOIN sportovci p ON p.id=m.sportovec_id WHERE m.team_id=? ORDER BY p.prijmeni,p.jmeno,m.valid_from,m.id');
    $query->execute([$teamId]);
    return ['team' => $team, 'members' => $query->fetchAll(PDO::FETCH_ASSOC),
        'effective_ids' => kisRosterEffectiveMemberIds($pdo, $teamId, $on), 'on' => $on];
}
