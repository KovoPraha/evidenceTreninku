<?php

declare(strict_types=1);

/** Canonical request excludes UI/session fields. It is also the retry identity. */
function memberRosterRequest(array $input): array
{
    $result = [];
    foreach (['action','team_id','target_team_id','member_id','valid_from','valid_to','reason'] as $key) {
        $result[$key] = trim((string) ($input[$key] ?? ''));
    }
    $result['person_ids'] = array_values(array_unique(array_map('intval', (array) ($input['person_ids'] ?? []))));
    sort($result['person_ids'], SORT_NUMERIC);
    return $result;
}

/** The same snapshot is recomputed under locks immediately before applying a confirmed request. */
function memberRosterPreview(PDO $pdo, array $input): array
{
    $actor = memberRosterActor($pdo);
    $request = memberRosterRequest($input);
    $action = $request['action'];
    if (!in_array($action, ['grant','end_member','transfer'], true)) {
        throw new InvalidArgumentException('Neplatná akce náhledu.');
    }
    if ((int) $request['team_id'] < 1 || ($action === 'transfer' && (int) $request['target_team_id'] < 1)) {
        throw new KisRosterException('Vyberte soupisku.');
    }
    kisRosterText($request['reason'], 1000, 'Důvod');
    $lock = $pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $teamIds = array_values(array_unique(array_filter([(int) $request['team_id'], (int) $request['target_team_id']])));
    sort($teamIds, SORT_NUMERIC);
    if (!$teamIds) {
        throw new KisRosterException('Vyberte soupisku.');
    }
    $marks = implode(',', array_fill(0, count($teamIds), '?'));
    $query = $pdo->prepare("SELECT * FROM club_teams WHERE id IN ($marks) ORDER BY id" . $lock);
    $query->execute($teamIds);
    $teams = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($teams) !== count($teamIds)) {
        throw new KisRosterException('Soupiska nebyla nalezena.');
    }
    foreach ($teams as $team) {
        if ($team['status'] !== 'active' || $team['team_kind'] !== 'roster') {
            throw new KisRosterException('Vyberte aktivní soupisku, nikoli skupinu.');
        }
    }
    $ids = $request['person_ids'];
    $from = kisRosterDate($action === 'end_member' ? $request['valid_to'] : $request['valid_from']);
    if ($action === 'end_member') {
        $from = (new DateTimeImmutable($from))->modify('+1 day')->format('Y-m-d');
    }
    if ($action !== 'grant') {
        $query = $pdo->prepare('SELECT sportovec_id,valid_from,valid_to FROM club_roster_members WHERE id=? AND team_id=?' .

     $lock);
        $query->execute([(int) $request['member_id'], (int) $request['team_id']]);
        $member = $query->fetch(PDO::FETCH_ASSOC);
        if (!$member) {
            throw new KisRosterException('Členství nebylo nalezeno.');
        }
        $ids = [(int) $member['sportovec_id']];
    }
    $teamNames = array_column($teams, 'name', 'id');
    $sourceId = (int) $request['team_id'];
    $periods = [['team_id' => $sourceId, 'name' => $teamNames[$sourceId],
        'valid_from' => $action === 'grant' ? $from : $member['valid_from'],
        'valid_to' => $request['valid_to'] ?: null]];
    if ($action === 'transfer') {
        $periods[0]['valid_to'] = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        $targetId = (int) $request['target_team_id'];
        $periods[] = ['team_id' => $targetId, 'name' => $teamNames[$targetId],
            'valid_from' => $from, 'valid_to' => $member['valid_to']];
    } elseif ($action === 'end_member' && $request['valid_to'] < $member['valid_from']) {
        $periods[0]['valid_to'] = $member['valid_from'];
        $periods[0]['cancelled'] = true;
    }
    if (!$ids || count($ids) > 100 || min($ids) < 1) {
        throw new InvalidArgumentException('Vyberte 1 až 100 sportovců.');
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare("SELECT id,jmeno,prijmeni,stav_clenstvi FROM sportovci WHERE id IN ($marks) ORDER BY id" .

     $lock);
    $query->execute($ids);
    $people = $query->fetchAll(PDO::FETCH_ASSOC);
    if (count($people) !== count($ids)) {
        throw new KisRosterException('Některý sportovec nebyl nalezen.');
    }
    $query = $pdo->prepare("SELECT * FROM club_roster_members WHERE sportovec_id IN ($marks) ORDER BY id" .

     $lock);
    $query->execute($ids);
    $members = $query->fetchAll(PDO::FETCH_ASSOC);
    $impact = kisRosterHierarchyImpact($pdo, (int) $request['team_id'], max(date('Y-m-d'), $from));
    $blocked = false;
    // Adding members cannot hide an existing response. Ending/transferring requires resolving their future responses.
    foreach (['plans' => ['training_rsvps','plan_id'], 'events' => ['club_event_registrations','event_id']] as $kind
    => [$table,$key]) {
        foreach ($impact[$kind] as &$item) {
            $item['affected_responses'] = 0;
            if ($action !== 'grant' && kisRosterHierarchyHasTable($pdo, $table)) {
                $query = $pdo->prepare("SELECT sportovec_id FROM $table WHERE $key=? AND sportovec_id IN ($marks)"
                    . ($kind === 'events' ? " AND status<>'cancelled'" : '') . $lock);
                $query->execute([$item['id'], ...$ids]);
                $item['affected_responses'] = count($query->fetchAll(PDO::FETCH_COLUMN));
                if ($kind === 'plans' && kisRosterHierarchyHasTable($pdo, 'club_makeup_reservations')) {
                    $query = $pdo->prepare("SELECT id FROM club_makeup_reservations WHERE target_plan_id=? "
                        . "AND sportovec_id IN ($marks) AND status='booked'" . $lock);
                    $query->execute([$item['id'], ...$ids]);
                    $item['affected_responses'] += count($query->fetchAll(PDO::FETCH_COLUMN));
                }
                $blocked = $blocked || $item['affected_responses'] > 0;
            }
        }
        unset($item);
    }
    $impact['blocked'] = $blocked;
    $fingerprint = hash('sha256', json_encode([$actor,$request,$teams,$people,$members,$impact], JSON_THROW_ON_ERROR));
    return compact('request', 'teams', 'people', 'impact', 'fingerprint', 'periods');
}
