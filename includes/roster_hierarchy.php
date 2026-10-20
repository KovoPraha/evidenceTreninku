<?php
declare(strict_types=1);

require_once __DIR__.'/kis_roster.php';

function kisRosterHierarchyHasTable(PDO $pdo, string $table): bool
{
    $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $sql = $mysql
        ? 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        : "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function kisRosterHierarchyHasColumn(PDO $pdo, string $table, string $column): bool
{
    if (!kisRosterHierarchyHasTable($pdo, $table)) return false;
    $mysql = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $rows = $pdo->query($mysql ? 'SHOW COLUMNS FROM '.$table : 'PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC);
    return in_array($column, array_column($rows, $mysql ? 'Field' : 'name'), true);
}

/** Current or historical descendants, including the requested team itself. */
function kisRosterDescendantIds(PDO $pdo, int $teamId, string $date): array
{
    $date = kisRosterDate($date);
    if ($teamId < 1) return [];
    $seen = [$teamId => true];
    if (!kisRosterHierarchyHasTable($pdo, 'club_team_edges')) return [$teamId];
    $stmt = $pdo->prepare('SELECT parent_team_id,child_team_id FROM club_team_edges WHERE valid_from<=? AND (valid_to IS NULL OR valid_to>=?)');
    $stmt->execute([$date, $date]);
    $children = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $edge) $children[(int)$edge['parent_team_id']][] = (int)$edge['child_team_id'];
    $queue = [$teamId];
    for ($i = 0; $i < count($queue); $i++) {
        foreach ($children[$queue[$i]] ?? [] as $childId) {
            if (isset($seen[$childId])) continue;
            $seen[$childId] = true;
            $queue[] = $childId;
        }
    }
    return $queue;
}

/** One athlete ID per person, even when several descendant paths match. */
function kisRosterEffectiveMemberIds(PDO $pdo, int $teamId, string $date): array
{
    $date = kisRosterDate($date);
    $teamIds = kisRosterDescendantIds($pdo, $teamId, $date);
    if (!$teamIds || !kisRosterHierarchyHasTable($pdo, 'club_roster_members')) return [];
    $marks = implode(',', array_fill(0, count($teamIds), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT sportovec_id FROM club_roster_members WHERE team_id IN ($marks) AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) AND status<>'cancelled' AND (status<>'removed' OR valid_to IS NOT NULL) ORDER BY sportovec_id");
    $stmt->execute([...$teamIds, $date, $date]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Return only target IDs to which this athlete belonged on the supplied date. */
function kisRosterPersonTargetIds(PDO $pdo, int $sportovecId, array $teamIds, string $date): array
{
    $date = kisRosterDate($date);
    if ($sportovecId < 1 || !$teamIds || !kisRosterHierarchyHasTable($pdo, 'club_roster_members')) return [];
    $expanded = [];
    $targets = [];
    foreach ($teamIds as $teamId) {
        $teamId = (int)$teamId;
        if ($teamId < 1 || isset($targets[$teamId])) continue;
        $targets[$teamId] = kisRosterDescendantIds($pdo, $teamId, $date);
        foreach ($targets[$teamId] as $descendantId) $expanded[$descendantId] = true;
    }
    if (!$expanded) return [];
    $ids = array_keys($expanded);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT team_id FROM club_roster_members WHERE sportovec_id=? AND team_id IN ($marks) AND valid_from<=? AND (valid_to IS NULL OR valid_to>=?) AND status<>'cancelled' AND (status<>'removed' OR valid_to IS NOT NULL)");
    $stmt->execute([$sportovecId, ...$ids, $date, $date]);
    $memberTeams = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    $matched = [];
    foreach ($targets as $targetId => $descendants) {
        foreach ($descendants as $descendantId) if (isset($memberTeams[$descendantId])) {
            $matched[] = $targetId;
            break;
        }
    }
    return $matched;
}

/** Rows for a group → roster selector. A roster with two parents appears twice. */
function kisRosterSelectableTeams(PDO $pdo, string $date): array
{
    $date = kisRosterDate($date);
    if (!kisRosterHierarchyHasTable($pdo, 'club_teams')) return [];
    $hasKind = kisRosterHierarchyHasColumn($pdo, 'club_teams', 'team_kind');
    $sql = 'SELECT id,name'.($hasKind?',team_kind':'').' FROM club_teams';
    if (kisRosterHierarchyHasColumn($pdo, 'club_teams', 'status')) $sql .= " WHERE status='active'";
    $sql .= ' ORDER BY name,id';
    $teams = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $byId = [];
    foreach ($teams as $team) $byId[(int)$team['id']] = $team;
    $groups = [];
    foreach ($teams as $team) if (($team['team_kind'] ?? 'roster') === 'group') $groups[(int)$team['id']] = $team;
    $rows = [];
    $assigned = [];
    foreach ($groups as $groupId => $group) {
        $rows[] = ['id'=>$groupId,'name'=>(string)$group['name'],'group_id'=>$groupId,'group_name'=>(string)$group['name'],'is_group'=>true];
        foreach (kisRosterDescendantIds($pdo, $groupId, $date) as $memberTeamId) {
            if ($memberTeamId === $groupId || !isset($byId[$memberTeamId])) continue;
            $child = $byId[$memberTeamId];
            if (($child['team_kind'] ?? 'roster') === 'group') continue;
            $rows[] = ['id'=>$memberTeamId,'name'=>(string)$child['name'],'group_id'=>$groupId,'group_name'=>(string)$group['name'],'is_group'=>false];
            $assigned[$memberTeamId] = true;
        }
    }
    foreach ($teams as $team) {
        $id = (int)$team['id'];
        if (($team['team_kind'] ?? 'roster') === 'group' || isset($assigned[$id])) continue;
        $rows[] = ['id'=>$id,'name'=>(string)$team['name'],'group_id'=>0,'group_name'=>'Bez skupiny','is_group'=>false];
    }
    return $rows;
}

/** Preserve a separate interval for each independent entitlement. Dates are inclusive. */
function kisRosterGrantPeriod(PDO $pdo, int $teamId, int $sportovecId, int $actorId, string $source, string $validFrom, ?string $validTo, string $note, ?string $sourceRef = null): array
{
    $validFrom = kisRosterDate($validFrom);
    $validTo = $validTo === null ? null : kisRosterDate($validTo);
    $note = kisRosterText($note, 1000, 'Duvod');
    $source = kisRosterText($source, 24, 'Zdroj');
    $sourceRef = $sourceRef === null ? null : kisRosterText($sourceRef, 120, 'Identifikator zdroje');
    if (min($teamId, $sportovecId, $actorId) < 1 || ($validTo !== null && $validTo < $validFrom)) throw new InvalidArgumentException('Neplatné členství nebo interval.');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $lock = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $hasTeamKind = kisRosterHierarchyHasColumn($pdo,'club_teams','team_kind');
        $stmt = $pdo->prepare('SELECT id,status'.($hasTeamKind?',team_kind':'').' FROM club_teams WHERE id=?'.$lock);
        $stmt->execute([$teamId]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$team || $team['status'] !== 'active') throw new KisRosterException('Aktivní soupiska nebyla nalezena.');
        if (($team['team_kind'] ?? 'roster') === 'group') throw new KisRosterException('Členství ve skupině se počítá ze soupisek; sportovce zařaďte do podřízené soupisky.');
        $hasUci = kisRosterHierarchyHasColumn($pdo,'sportovci','uciid');
        $hasMemberStatus = kisRosterHierarchyHasColumn($pdo,'sportovci','stav_clenstvi');
        $stmt = $pdo->prepare('SELECT id'.($hasUci?',uciid':'').($hasMemberStatus?',stav_clenstvi':'').' FROM sportovci WHERE id=?'.$lock);
        $stmt->execute([$sportovecId]);
        $person = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$person || ($person['stav_clenstvi'] ?? '') === 'archiv') throw new KisRosterException('Sportovec neexistuje nebo je archivovaný.');
        $hasRef = kisRosterHierarchyHasColumn($pdo, 'club_roster_members', 'source_ref');
        if ($sourceRef !== null && !$hasRef) throw new KisRosterException('Schéma časových členství ještě není připravené.');
        $select = 'SELECT * FROM club_roster_members WHERE team_id=? AND sportovec_id=? AND source=?';
        $params = [$teamId, $sportovecId, $source];
        if ($sourceRef !== null) { $select .= ' AND source_ref=?'; $params[] = $sourceRef; }
        $select .= ' ORDER BY id DESC'.$lock;
        $stmt = $pdo->prepare($select);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($sourceRef !== null && $row['source_ref'] !== $sourceRef) continue;
            if ($row['status'] === 'active' && $row['valid_from'] === $validFrom && $row['valid_to'] === $validTo) {
                if ($ownTransaction) $pdo->commit();
                return ['id'=>(int)$row['id'],'created'=>false,'status'=>'active'];
            }
            if ($sourceRef !== null) throw new KisRosterException('Zdroj už má jiné období členství; vyžaduje ruční kontrolu.');
            if ($row['status'] === 'cancelled') continue;
            if ($row['valid_from'] <= ($validTo ?? '9999-12-31') && ($row['valid_to'] === null || $row['valid_to'] >= $validFrom)) {
                throw new KisRosterException('Stejný zdroj již pokrývá část tohoto období.');
            }
        }
        $snapshot = trim((string)($person['uciid'] ?? '')) ?: null;
        $hasSnapshot = kisRosterHierarchyHasColumn($pdo,'club_roster_members','kis_external_id_snapshot');
        $columns = 'team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id';
        $values = '?,?,\'active\',?,?,?,?';
        $arguments = [$teamId,$sportovecId,$source,$validFrom,$validTo,$actorId];
        if ($hasSnapshot) { $columns .= ',kis_external_id_snapshot'; $values .= ',?'; $arguments[] = $snapshot; }
        if ($hasRef) { $columns .= ',source_ref'; $values .= ',?'; $arguments[] = $sourceRef; }
        $pdo->prepare('INSERT INTO club_roster_members('.$columns.') VALUES ('.$values.')')->execute($arguments);
        $id = (int)$pdo->lastInsertId();
        kisRosterEvent($pdo, $teamId, $id, $actorId, 'grant_period', null, ['id'=>$id,'sportovec_id'=>$sportovecId,'source'=>$source,'source_ref'=>$sourceRef,'valid_from'=>$validFrom,'valid_to'=>$validTo], $note);
        if ($ownTransaction) $pdo->commit();
        return ['id'=>$id,'created'=>true,'status'=>'active'];
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** End only the identified grant; other source grants continue to count. */
function kisRosterEndPeriod(PDO $pdo, int $memberId, int $actorId, string $validTo, string $note): array
{
    $validTo = kisRosterDate($validTo);
    $note = kisRosterText($note, 1000, 'Duvod');
    if (min($memberId, $actorId) < 1) throw new InvalidArgumentException('Neplatný člen nebo správce.');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM club_roster_members WHERE id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$memberId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) throw new KisRosterException('Členství nebylo nalezeno.');
        if ($before['status'] === 'cancelled') {
            if ($ownTransaction) $pdo->commit();
            return ['id'=>$memberId,'changed'=>false,'status'=>'cancelled'];
        }
        if ($before['status'] === 'removed' && $before['valid_to'] === $validTo) {
            if ($ownTransaction) $pdo->commit();
            return ['id'=>$memberId,'changed'=>false,'status'=>'removed'];
        }
        if ($before['valid_to'] !== null && $validTo > $before['valid_to']) throw new KisRosterException('Členství již skončilo dříve.');
        $cancelledBeforeStart = $validTo < $before['valid_from'];
        $newStatus = $cancelledBeforeStart ? 'cancelled' : 'removed';
        $storedEnd = $cancelledBeforeStart ? $before['valid_from'] : $validTo;
        $pdo->prepare('UPDATE club_roster_members SET status=?,valid_to=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$newStatus,$storedEnd,$memberId]);
        $after = $before;
        $after['status'] = $newStatus;
        $after['valid_to'] = $storedEnd;
        kisRosterEvent($pdo, (int)$before['team_id'], $memberId, $actorId, $cancelledBeforeStart?'cancel_future_period':'end_period', $before, $after, $note);
        if ($ownTransaction) $pdo->commit();
        return ['id'=>$memberId,'changed'=>true,'status'=>$newStatus];
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** Create a continuing group or roster without asking an administrator for a season. */
function kisRosterCreateStandingTeam(PDO $pdo, int $actorId, string $code, string $name, string $kind, string $reason): array
{
    $code = kisRosterCode($code);
    $name = kisRosterText($name, 160, 'Nazev');
    $kind = kisRosterEnum($kind, ['group','roster'], 'Typ soupisky');
    $reason = kisRosterText($reason, 1000, 'Duvod');
    if ($actorId < 1 || !kisRosterHierarchyHasColumn($pdo, 'club_teams', 'team_kind')) throw new KisRosterException('Časová hierarchie ještě není připravená.');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM club_teams WHERE season_id IS NULL AND code=?');
        $stmt->execute([$code]);
        if ($stmt->fetchColumn()) throw new KisRosterException('Kód trvalé skupiny nebo soupisky již existuje.');
        $pdo->prepare("INSERT INTO club_teams(season_id,code,standing_code,name,discipline,age_label,status,created_by_trainer_id,team_kind) VALUES(NULL,?, ?,?,'vše','vše','active',?,?)")
            ->execute([$code,$code,$name,$actorId,$kind]);
        $id = (int)$pdo->lastInsertId();
        kisRosterEvent($pdo,$id,null,$actorId,'create_standing_team',null,['id'=>$id,'code'=>$code,'name'=>$name,'team_kind'=>$kind],$reason);
        if ($ownTransaction) $pdo->commit();
        return ['id'=>$id,'code'=>$code,'name'=>$name,'team_kind'=>$kind];
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/** All-time reachability prevents a future edge from creating a cycle later. */
function kisRosterWouldCycle(PDO $pdo, int $parentId, int $childId): bool
{
    if ($parentId === $childId) return true;
    $stmt = $pdo->query('SELECT parent_team_id,child_team_id FROM club_team_edges');
    $children = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $edge) $children[(int)$edge['parent_team_id']][] = (int)$edge['child_team_id'];
    $queue = [$childId];
    $seen = [$childId => true];
    for ($i=0; $i<count($queue); $i++) {
        foreach ($children[$queue[$i]] ?? [] as $next) {
            if ($next === $parentId) return true;
            if (!isset($seen[$next])) { $seen[$next] = true; $queue[] = $next; }
        }
    }
    return false;
}

/** Future targets whose effective audience can change when this parent changes. */
function kisRosterHierarchyImpact(PDO $pdo, int $parentId, string $fromDate): array
{
    $fromDate = kisRosterDate($fromDate);
    $plans = [];
    $events = [];
    if (kisRosterHierarchyHasTable($pdo,'training_roster_links') && kisRosterHierarchyHasTable($pdo,'planovane_treninky')) {
        $stmt = $pdo->prepare("SELECT l.plan_id,l.team_id,p.nazev,p.datum FROM training_roster_links l JOIN planovane_treninky p ON p.id=l.plan_id WHERE l.plan_id IS NOT NULL AND p.datum>=? AND p.stav='planovany' ORDER BY p.datum,p.id");
        $stmt->execute([$fromDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string)$row['datum'];
            if (!in_array($parentId,kisRosterDescendantIds($pdo,(int)$row['team_id'],$date),true)) continue;
            $planId = (int)$row['plan_id'];
            if (isset($plans[$planId])) continue;
            $responseCount = 0;
            $makeupCount = 0;
            if (kisRosterHierarchyHasTable($pdo,'training_rsvps')) {
                $count = $pdo->prepare('SELECT COUNT(*) FROM training_rsvps WHERE plan_id=?');
                $count->execute([$planId]);
                $responseCount = (int)$count->fetchColumn();
            }
            if (kisRosterHierarchyHasTable($pdo,'club_makeup_reservations')) {
                $count = $pdo->prepare("SELECT COUNT(*) FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");
                $count->execute([$planId]);
                $makeupCount = (int)$count->fetchColumn();
            }
            $plans[$planId] = ['id'=>$planId,'name'=>(string)$row['nazev'],'date'=>$date,'rsvp_count'=>$responseCount,'makeup_count'=>$makeupCount];
        }
    }
    if (kisRosterHierarchyHasTable($pdo,'club_event_roster_targets') && kisRosterHierarchyHasTable($pdo,'club_event_sessions')) {
        $stmt = $pdo->prepare("SELECT r.event_id,r.team_id,e.name,SUBSTR(s.starts_at,1,10) AS event_date FROM club_event_roster_targets r JOIN club_events e ON e.id=r.event_id JOIN club_event_sessions s ON s.event_id=e.id WHERE s.status='scheduled' AND s.starts_at>=? ORDER BY s.starts_at,e.id");
        $stmt->execute([$fromDate.' 00:00:00']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $date = (string)$row['event_date'];
            if (!in_array($parentId,kisRosterDescendantIds($pdo,(int)$row['team_id'],$date),true)) continue;
            $eventId = (int)$row['event_id'];
            if (isset($events[$eventId])) continue;
            $registrations = 0;
            if (kisRosterHierarchyHasTable($pdo,'club_event_registrations')) {
                $count = $pdo->prepare("SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status<>'cancelled'");
                $count->execute([$eventId]);
                $registrations = (int)$count->fetchColumn();
            }
            $events[$eventId] = ['id'=>$eventId,'name'=>(string)$row['name'],'date'=>$date,'registration_count'=>$registrations];
        }
    }
    $blocked = false;
    foreach ($plans as $plan) if ($plan['rsvp_count'] > 0 || $plan['makeup_count'] > 0) $blocked = true;
    foreach ($events as $event) if ($event['registration_count'] > 0) $blocked = true;
    return ['plans'=>array_values($plans),'events'=>array_values($events),'blocked'=>$blocked];
}

function kisRosterPreviewHierarchy(PDO $pdo, int $parentId, array $childIds, string $date): array
{
    $date = kisRosterDate($date);
    $childIds = array_values(array_unique(array_filter(array_map('intval', $childIds), static fn(int $id): bool => $id > 0)));
    sort($childIds, SORT_NUMERIC);
    if ($parentId < 1 || !$childIds) throw new InvalidArgumentException('Vyberte skupinu a alespoň jednu soupisku.');
    $stmt = $pdo->prepare('SELECT id,name,status'.(kisRosterHierarchyHasColumn($pdo,'club_teams','team_kind')?',team_kind':'').' FROM club_teams WHERE id=?');
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent || $parent['status'] !== 'active' || ($parent['team_kind'] ?? '') !== 'group') throw new KisRosterException('Aktivní nadřazená skupina nebyla nalezena.');
    $existing = array_fill_keys(kisRosterEffectiveMemberIds($pdo,$parentId,$date), true);
    $all = $existing;
    $overlap = [];
    $children = [];
    foreach ($childIds as $childId) {
        $stmt->execute([$childId]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$child || $child['status'] !== 'active') throw new KisRosterException('Vybraná soupiska nebyla nalezena.');
        if (kisRosterWouldCycle($pdo,$parentId,$childId)) throw new KisRosterException('Vazba by vytvořila cyklus.');
        $personIds = kisRosterEffectiveMemberIds($pdo,$childId,$date);
        foreach ($personIds as $personId) {
            if (isset($all[$personId])) $overlap[$personId] = true;
            $all[$personId] = true;
        }
        $children[] = ['id'=>$childId,'name'=>(string)$child['name'],'member_count'=>count($personIds)];
    }
    $currentEdges = $pdo->prepare('SELECT id,parent_team_id,child_team_id,valid_from,valid_to FROM club_team_edges WHERE parent_team_id=? ORDER BY id');
    $currentEdges->execute([$parentId]);
    $edgeSnapshot = $currentEdges->fetchAll(PDO::FETCH_ASSOC);
    $allIds = array_keys($all);
    $overlapIds = array_keys($overlap);
    sort($allIds,SORT_NUMERIC);
    sort($overlapIds,SORT_NUMERIC);
    $impact = kisRosterHierarchyImpact($pdo,$parentId,$date);
    $fingerprint = hash('sha256',json_encode([$parentId,$childIds,$date,$allIds,$overlapIds,$edgeSnapshot,$impact],JSON_THROW_ON_ERROR));
    return ['parent'=>$parent,'children'=>$children,'date'=>$date,'before_count'=>count($existing),'after_count'=>count($all),'after_member_ids'=>$allIds,'overlap_count'=>count($overlap),'overlap_member_ids'=>$overlapIds,'impact'=>$impact,'fingerprint'=>$fingerprint];
}

/** Commit one reviewed bulk attachment; memberships themselves are never copied. */
function kisRosterAttachChildren(PDO $pdo, int $parentId, array $childIds, string $date, int $actorId, string $reason, string $fingerprint): array
{
    $reason = kisRosterText($reason,1000,'Duvod');
    if ($actorId < 1 || !kisRosterHierarchyHasTable($pdo,'club_team_edges')) throw new KisRosterException('Hierarchie není připravená.');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
            $lock = $pdo->prepare('SELECT id FROM club_teams WHERE id=? FOR UPDATE');
            $lock->execute([$parentId]);
            $lock->fetchColumn();
            $lock->closeCursor();
        }
        $preview = kisRosterPreviewHierarchy($pdo,$parentId,$childIds,$date);
        if (!hash_equals($preview['fingerprint'],$fingerprint)) throw new KisRosterException('Náhled se mezitím změnil. Načtěte ho znovu.');
        if ($preview['impact']['blocked']) throw new KisRosterException('Změna zasahuje budoucí trénink s odpovědí či náhradou nebo akci s přihláškou. Nejprve upravte konkrétní cíle.');
        $created = 0;
        foreach ($preview['children'] as $child) {
            $childId = $child['id'];
            $stmt = $pdo->prepare('SELECT id,valid_from,valid_to FROM club_team_edges WHERE parent_team_id=? AND child_team_id=? ORDER BY id DESC');
            $stmt->execute([$parentId,$childId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $edge) {
                if ($edge['valid_to'] === null || $edge['valid_to'] >= $date) {
                    if ($edge['valid_from'] <= $date) continue 2;
                    throw new KisRosterException('Vazba už má novější interval; vyžaduje ruční kontrolu.');
                }
            }
            $pdo->prepare('INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,actor_trainer_id,reason) VALUES(?,?,?,?,?)')
                ->execute([$parentId,$childId,$date,$actorId,$reason]);
            $edgeId = (int)$pdo->lastInsertId();
            kisRosterStructureEvent($pdo,'edge',$edgeId,$actorId,'attach_team',$reason,[],['parent_team_id'=>$parentId,'child_team_id'=>$childId,'valid_from'=>$date]);
            $created++;
        }
        if ($ownTransaction) $pdo->commit();
        return ['created_count'=>$created,'preview'=>$preview];
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function kisRosterPreviewEdgeEnd(PDO $pdo, int $edgeId, string $validTo): array
{
    $validTo = kisRosterDate($validTo);
    $stmt = $pdo->prepare('SELECT * FROM club_team_edges WHERE id=?');
    $stmt->execute([$edgeId]);
    $edge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edge) throw new KisRosterException('Vazba nebyla nalezena.');
    if ($validTo < $edge['valid_from']) throw new InvalidArgumentException('Konec vazby nesmí být před začátkem.');
    if ($edge['valid_to'] !== null && $edge['valid_to'] !== $validTo) throw new KisRosterException('Vazba již skončila jiným dnem.');
    $fromDate = (new DateTimeImmutable($validTo))->modify('+1 day')->format('Y-m-d');
    $impact = kisRosterHierarchyImpact($pdo,(int)$edge['parent_team_id'],$fromDate);
    $fingerprint = hash('sha256',json_encode([$edge,$validTo,$impact],JSON_THROW_ON_ERROR));
    return ['edge'=>$edge,'valid_to'=>$validTo,'impact'=>$impact,'fingerprint'=>$fingerprint];
}

function kisRosterEndHierarchyEdge(PDO $pdo, int $edgeId, int $actorId, string $validTo, string $reason, string $fingerprint): array
{
    $validTo = kisRosterDate($validTo);
    $reason = kisRosterText($reason,1000,'Duvod');
    if (min($edgeId,$actorId) < 1) throw new InvalidArgumentException('Vazba a správce jsou povinní.');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM club_team_edges WHERE id=?'.((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
        $stmt->execute([$edgeId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) throw new KisRosterException('Vazba nebyla nalezena.');
        if ($validTo < $before['valid_from']) throw new InvalidArgumentException('Konec vazby nesmí být před začátkem.');
        $preview = kisRosterPreviewEdgeEnd($pdo,$edgeId,$validTo);
        if (!hash_equals($preview['fingerprint'],$fingerprint)) throw new KisRosterException('Náhled dopadu se mezitím změnil. Načtěte jej znovu.');
        if ($preview['impact']['blocked']) throw new KisRosterException('Ukončení vazby zasahuje budoucí trénink s odpovědí či náhradou nebo akci s přihláškou. Nejprve upravte konkrétní cíle.');
        if ($before['valid_to'] !== null) {
            if ($before['valid_to'] !== $validTo) throw new KisRosterException('Vazba již skončila jiným dnem.');
            if ($ownTransaction) $pdo->commit();
            return ['id'=>$edgeId,'changed'=>false];
        }
        $pdo->prepare('UPDATE club_team_edges SET valid_to=? WHERE id=?')->execute([$validTo,$edgeId]);
        $after = $before;
        $after['valid_to'] = $validTo;
        kisRosterStructureEvent($pdo,'edge',$edgeId,$actorId,'end_edge',$reason,$before,$after);
        if ($ownTransaction) $pdo->commit();
        return ['id'=>$edgeId,'changed'=>true];
    } catch (Throwable $error) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
