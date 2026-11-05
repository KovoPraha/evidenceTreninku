<?php

declare(strict_types=1);
require_once __DIR__.'/staff_workspaces.php';
require_once __DIR__.'/member_charge_read.php';
require_once __DIR__.'/coach_training_access.php';

/** No grants are inferred from training history, contacts or public links. */
function coachScopePersonPredicateForTrainer(PDO $pdo, int $trainerId, string $personExpression = 's.id'): string
{
    if (!preg_match('/^[a-z_]+\.(?:id|sportovec_id)$/D', $personExpression)) {
        throw new InvalidArgumentException('Neplatný alias osoby.');
    }
    if ($trainerId < 1 || !memberChargeReadTableExists($pdo, 'coach_scope_grants') || !memberChargeReadTableExists($pdo, 'staff_user_positions')) {
        return '0=1';
    }
    $clauses = [];
    if (memberChargeReadTableExists($pdo, 'sportovec_podskupina')) {
        $clauses[] = "(g.scope_kind='subgroup' AND EXISTS(SELECT 1 FROM sportovec_podskupina cs WHERE cs.sportovec_id=$personExpression AND cs.podskupina_id=g.scope_id))";
    }
    if (memberChargeReadTableExists($pdo, 'club_roster_members') && memberChargeReadTableExists($pdo, 'club_teams')) {
        $clauses[] = "(g.scope_kind='team' AND EXISTS(SELECT 1 FROM club_roster_members cm JOIN club_teams ct ON ct.id=cm.team_id "
            . "WHERE cm.sportovec_id=$personExpression AND cm.team_id=g.scope_id AND cm.status='active' AND ct.status='active' "
            . 'AND cm.valid_from<=CURRENT_DATE AND (cm.valid_to IS NULL OR cm.valid_to>=CURRENT_DATE)))';
    }
    if (!$clauses) {
        return '0=1';
    }
    return 'EXISTS(SELECT 1 FROM coach_scope_grants g JOIN treneri cg_t ON cg_t.id=g.trainer_id AND cg_t.aktivni=1 '
        . "JOIN staff_user_positions cg_p ON cg_p.trainer_id=g.trainer_id AND cg_p.position_code='coach' WHERE g.trainer_id=$trainerId "
        . 'AND g.revoked_at IS NULL AND g.valid_from<=CURRENT_TIMESTAMP AND (g.valid_to IS NULL OR g.valid_to>CURRENT_TIMESTAMP) AND ('
    .implode(' OR ', $clauses).'))';
}

function coachScopePersonPredicate(PDO $pdo, string $personExpression = 's.id'): string
{
    if (!isset($_SESSION['trener_id'])) {
        return '0=1';
    }
    $position = staffActivePosition();
    // A registrar already has explicit person administration, separately guarded
    // by the route matrix. Switching workspaces does not merge their menus.
    if (in_array($position, ['sports_lead','registrar'], true)) {
        return '1=1';
    }
    if ($position !== 'coach') {
        return '0=1';
    }
    return coachScopePersonPredicateForTrainer($pdo, (int)$_SESSION['trener_id'], $personExpression);
}

function coachScopeCanViewPerson(PDO $pdo, int $personId): bool
{
    if ($personId < 1) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM sportovci s WHERE s.id=? AND '.coachScopePersonPredicate($pdo));
    $stmt->execute([$personId]);
    return (bool)$stmt->fetchColumn();
}

function coachScopeRequirePerson(PDO $pdo, int $personId): void
{
    if (!coachScopeCanViewPerson($pdo, $personId)) {
        http_response_code(403);
        exit('Sportovec není ve vašem aktuálním pracovním rozsahu.');
    }
}

/** Call before a transaction writes attendance or measurements. */
function coachScopeAssertPeople(PDO $pdo, array $personIds): void
{
    if ($pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && isset($_SESSION['trener_id'])) {
        $lock = $pdo->prepare('SELECT id FROM treneri WHERE id=? FOR UPDATE');
        $lock->execute([(int)$_SESSION['trener_id']]);
    }
    foreach (array_unique(array_map('intval', $personIds)) as $id) {
        if ($id < 1 || !coachScopeCanViewPerson($pdo, $id)) {
            throw new RuntimeException('Vybraný sportovec není ve vašem aktuálním pracovním rozsahu.');
        }
    }
}

function coachScopeTargetPredicate(PDO $pdo, string $kind, string $idExpression): string
{
    if (!in_array($kind, ['team','subgroup'], true) || !preg_match('/^[a-z_]+\.id$/D', $idExpression)) {
        throw new InvalidArgumentException('Neplatný rozsah.');
    }
    if (in_array(staffActivePosition(), ['sports_lead','registrar'], true) && isset($_SESSION['trener_id'])) {
        return '1=1';
    }
    if (!staffActivePositionIs('coach') || !memberChargeReadTableExists($pdo, 'coach_scope_grants')) {
        return '0=1';
    }
    $id = (int)$_SESSION['trener_id'];
    return 'EXISTS(SELECT 1 FROM coach_scope_grants gs JOIN treneri gst ON gst.id=gs.trainer_id AND gst.aktivni=1 '
        . "JOIN staff_user_positions gsp ON gsp.trainer_id=gs.trainer_id AND gsp.position_code='coach' "
        . "WHERE gs.trainer_id=$id AND gs.scope_kind='$kind' AND gs.scope_id=$idExpression AND gs.revoked_at IS NULL "
        . 'AND gs.valid_from<=CURRENT_TIMESTAMP AND (gs.valid_to IS NULL OR gs.valid_to>CURRENT_TIMESTAMP))';
}

function coachScopeAssertTeams(PDO $pdo, array $ids, ?string $date = null): void
{
    coachScopeAssertPeople($pdo, []);
    if ($date !== null && staffActivePositionIs('coach')) {
        require_once __DIR__.'/roster_hierarchy.php';
        foreach ($ids as $id) {
            $ids = array_merge($ids, kisRosterDescendantIds($pdo, (int)$id, $date));
        }
    }
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $s = $pdo->prepare('SELECT 1 FROM club_teams ct WHERE ct.id=? AND '.coachScopeTargetPredicate($pdo, 'team', 'ct.id'));
        $s->execute([$id]);
        if (!$s->fetchColumn()) {
            throw new RuntimeException('Vybraná soupiska není ve vašem aktuálním pracovním rozsahu.');
        }
    }
}

/** A coach can work on their own plan without a roster assignment. */
function coachScopeAssertPlan(PDO $pdo, int $planId, bool $substitution = false): void
{
    coachTrainingRequireActor($pdo);
    $q = $pdo->prepare('SELECT trener_id FROM planovane_treninky WHERE id=?');
    $q->execute([$planId]);
    $owner = $q->fetchColumn();
    if ($owner === false || (!$substitution && !staffActivePositionIs('sports_lead') && (int)$owner !== (int)$_SESSION['trener_id'])) {
        throw new RuntimeException('Plán není přiřazen vám.');
    }
}

function coachScopeRequirePlan(PDO $pdo, int $planId, bool $substitution = false): void
{
    try {
        coachScopeAssertPlan($pdo, $planId, $substitution);
    } catch (RuntimeException $exception) {
        http_response_code(403);
        exit('Plán není ve vašem aktuálním pracovním rozsahu.');
    }
}

function coachScopeTrainingPredicate(PDO $pdo, string $trainingExpression = 't.id'): string
{
    if (!preg_match('/^[a-z_]+\.id$/D', $trainingExpression)) {
        throw new InvalidArgumentException('Neplatný alias tréninku.');
    }
    if (!coachTrainingActorAllowed($pdo)) {
        return '0=1';
    }
    if (staffActivePositionIs('sports_lead')) {
        return '1=1';
    }
    $id = (int)$_SESSION['trener_id'];
    return "EXISTS(SELECT 1 FROM trenink_trener ctt WHERE ctt.trenink_id=$trainingExpression AND ctt.trener_id=$id)";
}

function coachScopeAssertManager(PDO $pdo, int $actor): void
{
    if ($actor < 1 || $actor !== (int)($_SESSION['trener_id'] ?? 0) || !staffActivePositionIs('sports_lead')) {
        throw new RuntimeException('Rozsahy smí měnit vedoucí sportu v aktivní pracovní pozici.');
    }
    $lock = $pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $s = $pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1'.$lock);
    $s->execute([$actor]);
    if (!$s->fetchColumn()) {
        throw new RuntimeException('Pracovní oprávnění vedoucího sportu již neplatí.');
    }
    $s = $pdo->prepare("SELECT trainer_id FROM staff_user_positions WHERE trainer_id=? AND position_code='sports_lead'"
    .$lock);
    $s->execute([$actor]);
    if (!$s->fetchColumn()) {
        $s = $pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?'.$lock);
        $s->execute([$actor]);
        if (!$s->fetchColumn()) {
            throw new RuntimeException('Pracovní oprávnění vedoucího sportu již neplatí.');
        }
    }
}

function coachScopeParticipantIds(string $participants, array $measurements = []): array
{
    $ids = [];
    foreach (array_filter(array_map('trim', explode(',', $participants))) as $value) {
        if (preg_match('/^(\d+):(.+)$/', $value, $match)) {
            $ids[] = (int)$match[1];
        }
    }
    foreach ($measurements as $row) {
        $ids[] = (int)($row['sportovec_id'] ?? 0);
    }
    return array_values(array_unique($ids));
}

/** Training ownership does not grant access to a participant's private profile. */
function coachScopeRequireTraining(PDO $pdo, int $trainingId): void
{
    $q = $pdo->prepare('SELECT t.id FROM treninky t WHERE t.id=? AND '.coachScopeTrainingPredicate($pdo));
    $q->execute([$trainingId]);
    if (!$q->fetchColumn()) {
        http_response_code(403);
        exit('Trénink není přiřazen vám nebo pracovní oprávnění již neplatí.');
    }
}

function coachScopeGrant(PDO $pdo, int $trainerId, string $kind, int $scopeId, string $from, ?string $to, string $reason, int $actor): int
{
    $reason = trim($reason);
    if ($actor < 1 || !in_array($kind, ['team','subgroup'], true) || $scopeId < 1 || mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
        throw new InvalidArgumentException('Vyberte rozsah a uveďte důvod přidělení (10–1000 znaků).');
    }
    foreach ([$from,$to] as $date) {
        if ($date !== null) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date);
            if (!$d || $d->format('Y-m-d H:i:s') !== $date) {
                throw new InvalidArgumentException('Neplatný okamžik platnosti.');
            }
        }
    }
    if ($to !== null && $to <= $from) {
        throw new InvalidArgumentException('Konec platnosti musí být po začátku.');
    }
    $pdo->beginTransaction();
    try {
        coachScopeAssertManager($pdo, $actor);
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $trainer = $pdo->prepare('SELECT id FROM treneri WHERE id=? AND aktivni=1'.$lock);
        $trainer->execute([$trainerId]);
        if (!$trainer->fetchColumn()) {
            throw new InvalidArgumentException('Trenér neexistuje nebo není aktivní.');
        }
        $position = $pdo->prepare("SELECT 1 FROM staff_user_positions WHERE trainer_id=? AND position_code='coach'");
        $position->execute([$trainerId]);
        if (!$position->fetchColumn()) {
            throw new InvalidArgumentException('Pracovník nemá přidělenou pozici trenéra.');
        }
        $scope = $pdo->prepare('SELECT id FROM '.($kind === 'team' ? 'club_teams' : 'podskupiny').' WHERE id=?'
    .$lock);
        $scope->execute([$scopeId]);
        if (!$scope->fetchColumn()) {
            throw new InvalidArgumentException('Vybraný tým nebo podskupina neexistuje.');
        }
        $overlap = $pdo->prepare(('SELECT id FROM coach_scope_grants WHERE trainer_id=? AND scope_kind=? AND scope_id=? AND revoked_at IS NULL AND (valid_to IS ' .
    'NULL OR valid_to>?) AND (? IS NULL OR valid_from<?)').$lock);
        $overlap->execute([$trainerId,$kind,$scopeId,$from,$to,$to]);
        if ($overlap->fetchColumn()) {
            throw new RuntimeException('V tomto intervalu již platí přidělení stejného rozsahu.');
        }
        $pdo->prepare('INSERT INTO coach_scope_grants(trainer_id,scope_kind,scope_id,valid_from,valid_to,reason,created_by_trainer_id) VALUES(?,?,?,?,?,?,?)')
    ->execute([$trainerId,$kind,$scopeId,$from,$to,$reason,$actor]);
        $id = (int)$pdo->lastInsertId();
        coachScopeEvent($pdo, $id, $actor, 'grant', ['trainer_id' => $trainerId,'scope_kind' => $kind,'scope_id'
    => $scopeId,'valid_from' => $from,'valid_to' => $to,'reason' => $reason]);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }throw $e;
    }
}

function coachScopeRevoke(PDO $pdo, int $grantId, string $reason, int $actor): void
{
    $reason = trim($reason);
    if ($actor < 1 || mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
        throw new InvalidArgumentException('Uveďte důvod ukončení (10–1000 znaků).');
    }
    $pdo->beginTransaction();
    try {
        coachScopeAssertManager($pdo, $actor);
        $owner = $pdo->prepare('SELECT trainer_id FROM coach_scope_grants WHERE id=?');
        $owner->execute([$grantId]);
        $trainerId = $owner->fetchColumn();
        if ($trainerId && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lock = $pdo->prepare('SELECT id FROM treneri WHERE id=? FOR UPDATE');
            $lock->execute([(int)$trainerId]);
        }
        $stmt = $pdo->prepare('SELECT * FROM coach_scope_grants WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE'
    : ''));
        $stmt->execute([$grantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Přidělení neexistuje.');
        }
        if ($row['revoked_at'] === null) {
            $pdo->prepare('UPDATE coach_scope_grants SET revoked_at=CURRENT_TIMESTAMP,revoked_by_trainer_id=?,revoke_reason=? WHERE id=?')
    ->execute([$actor,$reason,$grantId]);
            coachScopeEvent($pdo, $grantId, $actor, 'revoke', ['reason' => $reason]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }throw $e;
    }
}

function coachScopeEvent(PDO $pdo, int $id, int $actor, string $action, array $detail): void
{
    $pdo->prepare('INSERT INTO coach_scope_events(grant_id,actor_trainer_id,action,detail_json) VALUES(?,?,?,?)')
    ->execute([$id,$actor,$action,json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}
