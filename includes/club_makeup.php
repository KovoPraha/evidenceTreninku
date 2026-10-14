<?php
declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';
require_once __DIR__ . '/child_access.php';

final class ClubMakeupException extends RuntimeException {}

function clubMakeupReady(PDO $pdo): bool
{
    foreach (['club_makeup_policies','club_makeup_offer_periods','club_makeup_excuse_rules','club_makeup_slots','club_makeup_credits','club_makeup_reservations','club_makeup_events','club_makeup_config_events'] as $table) {
        if (!familyPortalTableExists($pdo, $table)) return false;
    }
    return true;
}

function clubMakeupNow(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
}

/** @param array<string,mixed> $plan */
function clubMakeupStart(array $plan): DateTimeImmutable
{
    return new DateTimeImmutable((string)$plan['datum'] . ' ' . ((string)($plan['cas_od'] ?? '') !== '' ? (string)$plan['cas_od'] : '23:59:59'), new DateTimeZone('Europe/Prague'));
}

function clubMakeupEvent(PDO $pdo, int $creditId, ?int $reservationId, string $action, string $actorType, int $actorId): void
{
    $pdo->prepare('INSERT INTO club_makeup_events(credit_id,reservation_id,action,actor_type,actor_id) VALUES(?,?,?,?,?)')
        ->execute([$creditId,$reservationId,$action,$actorType,$actorId]);
}

/** @param array<string,mixed>|null $before @param array<string,mixed>|null $after */
function clubMakeupConfigEvent(PDO $pdo, string $scope, int $id, int $actorId, ?array $before, ?array $after): void
{
    $pdo->prepare('INSERT INTO club_makeup_config_events(scope_type,scope_id,actor_trainer_id,before_json,after_json) VALUES(?,?,?,?,?)')
        ->execute([$scope,$id,$actorId,$before?json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE):null]);
}

function clubMakeupPolicySave(PDO $pdo, int $actorId, int $programId, int $seasonId, int $teamId, bool $enabled, int $hours, bool $applyFuture): void
{
    if (!clubMakeupReady($pdo)) throw new ClubMakeupException('Náhradní lekce zatím nejsou dostupné.');
    if ($actorId < 1 || $programId < 1 || $seasonId < 1 || $teamId < 1 || $hours < 0 || $hours > 720) throw new InvalidArgumentException('Neplatné nastavení omluv kroužku.');
    $scope = $pdo->prepare('SELECT 1 FROM club_program_offers WHERE program_id=? AND season_id=? AND team_id=? LIMIT 1');
    $scope->execute([$programId,$seasonId,$teamId]);
    if (!$scope->fetchColumn()) throw new ClubMakeupException('Program, sezona a soupiska k sobě nepatří.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT * FROM club_makeup_policies WHERE program_id=? AND season_id=? AND team_id=?');
        $select->execute([$programId,$seasonId,$teamId]);
        $before=$select->fetch(PDO::FETCH_ASSOC)?:null;
        $policyId = (int)($before['id']??0);
        if ($policyId) {
            $pdo->prepare('UPDATE club_makeup_policies SET enabled=?,excuse_hours=?,updated_by_trainer_id=? WHERE id=?')
                ->execute([$enabled ? 1 : 0,$hours,$actorId,$policyId]);
        } else {
            $pdo->prepare('INSERT INTO club_makeup_policies(program_id,season_id,team_id,enabled,excuse_hours,updated_by_trainer_id) VALUES(?,?,?,?,?,?)')
                ->execute([$programId,$seasonId,$teamId,$enabled ? 1 : 0,$hours,$actorId]);
            $policyId = (int)$pdo->lastInsertId();
        }
        clubMakeupConfigEvent($pdo,'policy',$policyId,$actorId,$before,['program_id'=>$programId,'season_id'=>$seasonId,'team_id'=>$teamId,'enabled'=>$enabled,'excuse_hours'=>$hours,'apply_future'=>$applyFuture]);
        if ($applyFuture) {
            $plans = $pdo->prepare('SELECT DISTINCT p.id FROM planovane_treninky p JOIN training_roster_links l ON l.plan_id=p.id AND l.team_id=? JOIN club_program_offers o ON o.program_id=? AND o.season_id=? AND o.team_id=? AND o.starts_on<=p.datum AND o.ends_on>=p.datum WHERE p.stav=? AND p.datum>=?');
            $plans->execute([$teamId,$programId,$seasonId,$teamId,'planovany',clubMakeupNow()->format('Y-m-d')]);
            foreach ($plans->fetchAll(PDO::FETCH_COLUMN) as $planId) {
                $future=$pdo->prepare('SELECT datum,cas_od FROM planovane_treninky WHERE id=?');$future->execute([(int)$planId]);
                if (clubMakeupStart($future->fetch(PDO::FETCH_ASSOC)) <= clubMakeupNow()) continue;
                if (!$enabled) {
                    $pdo->prepare('DELETE FROM club_makeup_excuse_rules WHERE plan_id=? AND policy_id=?')->execute([(int)$planId,$policyId]);
                } else {
                    $existingRule=$pdo->prepare('SELECT 1 FROM club_makeup_excuse_rules WHERE plan_id=? AND policy_id=?');
                    $existingRule->execute([(int)$planId,$policyId]);
                    if ($existingRule->fetchColumn()) {
                        $pdo->prepare('UPDATE club_makeup_excuse_rules SET excuse_hours=? WHERE plan_id=? AND policy_id=?')->execute([$hours,(int)$planId,$policyId]);
                    } else {
                        $pdo->prepare('INSERT INTO club_makeup_excuse_rules(plan_id,policy_id,excuse_hours) VALUES(?,?,?)')->execute([(int)$planId,$policyId,$hours]);
                    }
                }
            }
        }
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function clubMakeupOfferPeriodSave(PDO $pdo, int $actorId, int $offerId, string $kind, ?int $nextOfferId): void
{
    if (!clubMakeupReady($pdo)) throw new ClubMakeupException('Náhradní lekce zatím nejsou dostupné.');
    if ($actorId < 1 || $offerId < 1 || !in_array($kind,['annual','first','second','single'],true)) throw new InvalidArgumentException('Neplatný typ období.');
    $query = $pdo->prepare('SELECT id,program_id,season_id,team_id,starts_on,ends_on FROM club_program_offers WHERE id=?');
    $query->execute([$offerId]);
    $offer = $query->fetch(PDO::FETCH_ASSOC);
    if (!$offer) throw new ClubMakeupException('Nabídka neexistuje.');
    if ($kind === 'first') {
        if (!$nextOfferId || $nextOfferId === $offerId) throw new InvalidArgumentException('První pololetí musí mít navazující nabídku.');
        $query->execute([$nextOfferId]);
        $next = $query->fetch(PDO::FETCH_ASSOC);
        if (!$next || (int)$next['program_id'] !== (int)$offer['program_id'] || (int)$next['season_id'] !== (int)$offer['season_id'] || (int)$next['team_id'] !== (int)$offer['team_id'] || (string)$next['starts_on'] <= (string)$offer['ends_on']) {
            throw new ClubMakeupException('Navazující nabídka musí být pozdější pololetí stejného programu, sezony a soupisky.');
        }
        $nextKind=$pdo->prepare('SELECT period_kind FROM club_makeup_offer_periods WHERE offer_id=?');$nextKind->execute([$nextOfferId]);
        if($nextKind->fetchColumn()!=='second') throw new ClubMakeupException('Navazující nabídku nejdřív označte jako druhé pololetí.');
    } elseif ($nextOfferId !== null) {
        throw new InvalidArgumentException('Navazující nabídku lze určit jen pro první pololetí.');
    }
    if ($kind!=='second') {
        $referenced=$pdo->prepare("SELECT 1 FROM club_makeup_offer_periods WHERE next_offer_id=? AND period_kind='first' LIMIT 1");
        $referenced->execute([$offerId]);
        if ($referenced->fetchColumn()) throw new ClubMakeupException('Tato nabídka navazuje na první pololetí; nejdřív změňte jeho vazbu.');
    }
    $existing = $pdo->prepare('SELECT * FROM club_makeup_offer_periods WHERE offer_id=?');
    $existing->execute([$offerId]);$before=$existing->fetch(PDO::FETCH_ASSOC)?:null;
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        if ($before) {
            $pdo->prepare('UPDATE club_makeup_offer_periods SET period_kind=?,next_offer_id=?,updated_by_trainer_id=? WHERE offer_id=?')->execute([$kind,$nextOfferId,$actorId,$offerId]);
        } else {
            $pdo->prepare('INSERT INTO club_makeup_offer_periods(offer_id,period_kind,next_offer_id,updated_by_trainer_id) VALUES(?,?,?,?)')->execute([$offerId,$kind,$nextOfferId,$actorId]);
        }
        clubMakeupConfigEvent($pdo,'period',$offerId,$actorId,$before,['period_kind'=>$kind,'next_offer_id'=>$nextOfferId]);
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $exception) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function clubMakeupSyncPlanRules(PDO $pdo, int $planId): void
{
    if (!clubMakeupReady($pdo)) return;
    $query = $pdo->prepare('SELECT DISTINCT q.id,q.excuse_hours FROM planovane_treninky p JOIN training_roster_links l ON l.plan_id=p.id JOIN club_program_offers o ON o.team_id=l.team_id AND o.starts_on<=p.datum AND o.ends_on>=p.datum JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id AND q.enabled=1 WHERE p.id=? AND p.stav=?');
    $query->execute([$planId,'planovany']);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $rule) {
        $exists = $pdo->prepare('SELECT 1 FROM club_makeup_excuse_rules WHERE plan_id=? AND policy_id=?');
        $exists->execute([$planId,(int)$rule['id']]);
        if (!$exists->fetchColumn()) $pdo->prepare('INSERT INTO club_makeup_excuse_rules(plan_id,policy_id,excuse_hours) VALUES(?,?,?)')->execute([$planId,(int)$rule['id'],(int)$rule['excuse_hours']]);
    }
}

/** @return array{enrollment_id:int,hours:int}|null */
function clubMakeupExcuseEntitlement(PDO $pdo, int $planId, int $sportovecId, DateTimeImmutable $now): ?array
{
    if (!clubMakeupReady($pdo)) return null;
    $query = $pdo->prepare(
        'SELECT e.id AS enrollment_id,r.excuse_hours,p.datum,p.cas_od FROM planovane_treninky p '
        . 'JOIN training_roster_links l ON l.plan_id=p.id JOIN club_program_offers o ON o.team_id=l.team_id AND o.starts_on<=p.datum AND o.ends_on>=p.datum '
        . 'JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id AND q.enabled=1 '
        . 'JOIN club_makeup_excuse_rules r ON r.plan_id=p.id AND r.policy_id=q.id '
        . "JOIN club_program_enrollments e ON e.offer_id=o.id AND e.sportovec_id=? AND e.status='active' AND e.valid_from<=p.datum AND e.valid_to>=p.datum "
        . 'JOIN shop_order_items oi ON oi.id=e.source_order_item_id AND oi.line_amount_minor>0 '
        . "WHERE p.id=? AND p.stav='planovany' ORDER BY CASE WHEN o.id IN (SELECT offer_id FROM club_makeup_offer_periods WHERE period_kind='annual') THEN 0 ELSE 1 END,e.id LIMIT 1"
    );
    $query->execute([$sportovecId,$planId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ($now > clubMakeupStart($row)->modify('-' . (int)$row['excuse_hours'] . ' hours')) return null;
    return ['enrollment_id'=>(int)$row['enrollment_id'],'hours'=>(int)$row['excuse_hours']];
}

function clubMakeupFinalizeSource(PDO $pdo, int $planId, int $trainingId, int $actorId): void
{
    if (!clubMakeupReady($pdo)) return;
    $planQuery = $pdo->prepare('SELECT datum,cas_od,stav,trenink_id FROM planovane_treninky WHERE id=?');
    $planQuery->execute([$planId]);
    $plan = $planQuery->fetch(PDO::FETCH_ASSOC);
    if (!$plan || $plan['stav'] !== 'evidovany' || (int)$plan['trenink_id'] !== $trainingId || clubMakeupStart($plan) > clubMakeupNow()) return;
    $query = $pdo->prepare(
        'SELECT r.sportovec_id,r.makeup_excused_at,r.makeup_cutoff_hours,e.id AS enrollment_id,e.offer_id,o.ends_on,o.program_id,o.season_id,o.team_id,'
        . 'cfg.period_kind,cfg.next_offer_id,n.ends_on AS next_ends_on '
        . 'FROM training_rsvps r JOIN club_program_enrollments e ON e.id=r.makeup_source_enrollment_id '
        . 'JOIN shop_order_items oi ON oi.id=e.source_order_item_id AND oi.line_amount_minor>0 '
        . 'JOIN club_program_offers o ON o.id=e.offer_id LEFT JOIN club_makeup_offer_periods cfg ON cfg.offer_id=o.id '
        . 'LEFT JOIN club_program_offers n ON n.id=cfg.next_offer_id '
        . "WHERE r.plan_id=? AND r.response='not_going' AND r.makeup_excused_at IS NOT NULL AND e.status='active'"
    );
    $query->execute([$planId]);
    $attendance = $pdo->prepare('SELECT 1 FROM trenink_sportovec WHERE trenink_id=? AND sportovec_id=?');
    $exists = $pdo->prepare('SELECT id FROM club_makeup_credits WHERE source_plan_id=? AND sportovec_id=?');
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $personId = (int)$row['sportovec_id'];
        $attendance->execute([$trainingId,$personId]);
        if ($attendance->fetchColumn()) continue;
        $excusedAt = new DateTimeImmutable((string)$row['makeup_excused_at'],new DateTimeZone('Europe/Prague'));
        if ($excusedAt > clubMakeupStart($plan)->modify('-' . (int)$row['makeup_cutoff_hours'] . ' hours')) continue;
        $coverage = $pdo->prepare('SELECT 1 FROM training_roster_links l WHERE l.plan_id=? AND l.team_id=?');
        $coverage->execute([$planId,(int)$row['team_id']]);
        if (!$coverage->fetchColumn()) continue;
        $enrollment = $pdo->prepare("SELECT 1 FROM club_program_enrollments WHERE id=? AND status='active' AND valid_from<=? AND valid_to>=?");
        $enrollment->execute([(int)$row['enrollment_id'],(string)$plan['datum'],(string)$plan['datum']]);
        if (!$enrollment->fetchColumn()) continue;
        $exists->execute([$planId,$personId]);
        if ($exists->fetchColumn()) continue;
        $carry = (string)$row['period_kind'] === 'first' && (int)$row['next_offer_id'] > 0 ? (int)$row['next_offer_id'] : null;
        $expires = $carry ? (string)$row['next_ends_on'] : (string)$row['ends_on'];
        $pdo->prepare("INSERT INTO club_makeup_credits(source_plan_id,sportovec_id,source_enrollment_id,source_offer_id,carry_offer_id,status,expires_on,excused_at) VALUES(?,?,?,?,?,'available',?,?)")
            ->execute([$planId,$personId,(int)$row['enrollment_id'],(int)$row['offer_id'],$carry,$expires,(string)$row['makeup_excused_at']]);
        clubMakeupEvent($pdo,(int)$pdo->lastInsertId(),null,'issued','trainer',$actorId);
    }
}

/** Reconciles already recorded trainings, including a training entered before its start. */
function clubMakeupSyncPersonCredits(PDO $pdo, int $personId): void
{
    if (!clubMakeupReady($pdo)) return;
    $query=$pdo->prepare("SELECT DISTINCT p.id,p.trenink_id FROM training_rsvps r JOIN planovane_treninky p ON p.id=r.plan_id LEFT JOIN club_makeup_credits c ON c.source_plan_id=p.id AND c.sportovec_id=r.sportovec_id WHERE r.sportovec_id=? AND r.response='not_going' AND r.makeup_excused_at IS NOT NULL AND c.id IS NULL AND p.stav='evidovany' AND p.trenink_id IS NOT NULL AND p.datum<=? ORDER BY p.id");
    $query->execute([$personId,clubMakeupNow()->format('Y-m-d')]);
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
        $pdo->beginTransaction();
        try{
            if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
                $lock=$pdo->prepare('SELECT id FROM planovane_treninky WHERE id=? FOR UPDATE');$lock->execute([(int)$row['id']]);
            }
            clubMakeupFinalizeSource($pdo,(int)$row['id'],(int)$row['trenink_id'],0);
            $pdo->commit();
        }catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();throw $exception;}
    }
}

/** @return list<array<string,mixed>> */
function clubMakeupPendingExcusesForPerson(PDO $pdo, int $personId): array
{
    if (!clubMakeupReady($pdo)) return [];
    $query=$pdo->prepare("SELECT p.id,p.nazev,p.datum FROM training_rsvps r JOIN planovane_treninky p ON p.id=r.plan_id LEFT JOIN club_makeup_credits c ON c.source_plan_id=p.id AND c.sportovec_id=r.sportovec_id WHERE r.sportovec_id=? AND r.response='not_going' AND r.makeup_excused_at IS NOT NULL AND c.id IS NULL AND p.stav='planovany' ORDER BY p.datum,p.id");
    $query->execute([$personId]);return $query->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{pending:int,available:int,locked:int,reserved:int,used:int} */
function clubMakeupProfileSummary(PDO $pdo, int $personId): array
{
    $summary=['pending'=>0,'available'=>0,'locked'=>0,'reserved'=>0,'used'=>0];
    if (!clubMakeupReady($pdo)) return $summary;
    $summary['pending']=count(clubMakeupPendingExcusesForPerson($pdo,$personId));
    foreach (clubMakeupCreditsForPerson($pdo,$personId) as $credit) {
        $status=(string)$credit['display_status'];
        if ($status==='available') $summary['available']++;
        elseif ($status==='carried_locked') $summary['locked']++;
        elseif ($status==='reserved') $summary['reserved']++;
        elseif ($status==='used') $summary['used']++;
    }
    return $summary;
}

/** @param array<string,mixed> $credit */
function clubMakeupCreditUsableOn(PDO $pdo, array $credit, string $targetDate): bool
{
    if ((string)$credit['status'] !== 'available' || $targetDate > (string)$credit['expires_on']) return false;
    $source = $pdo->prepare('SELECT e.status,e.valid_from,e.valid_to,o.ends_on,o.program_id,o.season_id,o.team_id FROM club_program_enrollments e JOIN club_program_offers o ON o.id=e.offer_id WHERE e.id=?');
    $source->execute([(int)$credit['source_enrollment_id']]);
    $offer = $source->fetch(PDO::FETCH_ASSOC);
    if (!$offer || $offer['status'] !== 'active') return false;
    if ($targetDate <= (string)$offer['ends_on']) return $targetDate >= (string)$offer['valid_from'] && $targetDate <= (string)$offer['valid_to'];
    if (!(int)$credit['carry_offer_id']) return false;
    $follow = $pdo->prepare("SELECT 1 FROM club_program_enrollments e JOIN club_program_offers o ON o.id=e.offer_id JOIN shop_order_items oi ON oi.id=e.source_order_item_id AND oi.line_amount_minor>0 LEFT JOIN club_makeup_offer_periods c ON c.offer_id=o.id WHERE e.sportovec_id=? AND e.status='active' AND e.valid_from<=? AND e.valid_to>=? AND o.program_id=? AND o.season_id=? AND o.team_id=? AND (o.id=? OR c.period_kind='annual') LIMIT 1");
    $follow->execute([(int)$credit['sportovec_id'],$targetDate,$targetDate,(int)$offer['program_id'],(int)$offer['season_id'],(int)$offer['team_id'],(int)$credit['carry_offer_id']]);
    return (bool)$follow->fetchColumn();
}

function clubMakeupSlotSave(PDO $pdo, int $actorId, int $planId, int $offerId, int $capacity, int $fromYear, int $toYear, bool $allowOtherTrainer = false): void
{
    if (!clubMakeupReady($pdo)) throw new ClubMakeupException('Náhradní lekce zatím nejsou dostupné.');
    if ($planId < 1 || $actorId < 1 || $offerId < 1 || $capacity < 1 || $capacity > 500 || $fromYear < 1900 || $toYear > (int)date('Y') || $toYear < $fromYear) throw new InvalidArgumentException('Neplatné nastavení náhradníků.');
    $query = $pdo->prepare("SELECT p.id,p.datum,p.cas_od,p.stav,o.id AS offer_id,o.birth_year_from,o.birth_year_to FROM planovane_treninky p JOIN club_program_offers o ON o.id=? AND o.starts_on<=p.datum AND o.ends_on>=p.datum JOIN training_roster_links l ON l.plan_id=p.id AND l.team_id=o.team_id WHERE p.id=? AND (p.trener_id=? OR ?=1) AND p.stav='planovany' LIMIT 1");
    $query->execute([$offerId,$planId,$actorId,$allowOtherTrainer?1:0]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || clubMakeupStart($row) <= clubMakeupNow()) throw new ClubMakeupException('Termín nebo nabídka už nejsou dostupné pro náhradníky.');
    if ($row['birth_year_from'] === null || $row['birth_year_to'] === null || $fromYear < (int)$row['birth_year_from'] || $toYear > (int)$row['birth_year_to']) throw new ClubMakeupException('Nejdřív nastavte strukturované věkové rozmezí nabídky; termín jej nesmí rozšířit.');
    $policy = $pdo->prepare('SELECT 1 FROM club_makeup_policies q JOIN club_program_offers o ON o.program_id=q.program_id AND o.season_id=q.season_id AND o.team_id=q.team_id WHERE o.id=? AND q.enabled=1');
    $policy->execute([$offerId]);
    if (!$policy->fetchColumn()) throw new ClubMakeupException('Náhrady pro tento kroužek nejsou zapnuté v administraci kroužků.');
    $exists = $pdo->prepare('SELECT * FROM club_makeup_slots WHERE plan_id=?');$exists->execute([$planId]);$before=$exists->fetch(PDO::FETCH_ASSOC)?:null;
    if ($before) {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lock=$pdo->prepare('SELECT plan_id FROM club_makeup_slots WHERE plan_id=? FOR UPDATE');$lock->execute([$planId]);
        }
        $count = $pdo->prepare("SELECT COUNT(*) FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");$count->execute([$planId]);
        $booked=(int)$count->fetchColumn();
        if ($booked > $capacity) throw new ClubMakeupException('Kapacita nesmí klesnout pod počet existujících rezervací.');
        if ($booked>0 && ((int)$before['offer_id']!==$offerId || (int)$before['birth_year_from']!==$fromYear || (int)$before['birth_year_to']!==$toYear)) throw new ClubMakeupException('U termínu s rezervacemi lze změnit pouze kapacitu.');
        $pdo->prepare('UPDATE club_makeup_slots SET offer_id=?,capacity=?,accepting=1,birth_year_from=?,birth_year_to=?,updated_by_trainer_id=? WHERE plan_id=?')->execute([$offerId,$capacity,$fromYear,$toYear,$actorId,$planId]);
    } else {
        $pdo->prepare('INSERT INTO club_makeup_slots(plan_id,offer_id,capacity,birth_year_from,birth_year_to,updated_by_trainer_id) VALUES(?,?,?,?,?,?)')->execute([$planId,$offerId,$capacity,$fromYear,$toYear,$actorId]);
    }
    clubMakeupConfigEvent($pdo,'slot',$planId,$actorId,$before,['offer_id'=>$offerId,'capacity'=>$capacity,'accepting'=>true,'birth_year_from'=>$fromYear,'birth_year_to'=>$toYear]);
}

function clubMakeupSlotDisable(PDO $pdo, int $actorId, int $planId, bool $allowOtherTrainer = false): void
{
    $own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{
        $plan=$pdo->prepare('SELECT trener_id FROM planovane_treninky WHERE id=?');$plan->execute([$planId]);
        if ((int)$plan->fetchColumn() !== $actorId && !$allowOtherTrainer) throw new ClubMakeupException('Termín nepatří tomuto trenérovi.');
        $sql='SELECT * FROM club_makeup_slots WHERE plan_id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
        $slot=$pdo->prepare($sql);$slot->execute([$planId]);$before=$slot->fetch(PDO::FETCH_ASSOC)?:null;
        if($before && (int)$before['accepting']===1){
            $pdo->prepare('UPDATE club_makeup_slots SET accepting=0,updated_by_trainer_id=? WHERE plan_id=?')->execute([$actorId,$planId]);
            clubMakeupConfigEvent($pdo,'slot',$planId,$actorId,$before,['accepting'=>false,'existing_reservations_preserved'=>true]);
        }
        if($own)$pdo->commit();
    }catch(Throwable $exception){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $exception;}
}

/** @return list<array<string,mixed>> */
function clubMakeupCreditsForPerson(PDO $pdo, int $personId): array
{
    if (!clubMakeupReady($pdo)) return [];
    $query = $pdo->prepare('SELECT c.*,p.datum AS source_date,p.nazev AS source_name,o.name AS offer_name,o.ends_on AS source_ends_on FROM club_makeup_credits c JOIN planovane_treninky p ON p.id=c.source_plan_id JOIN club_program_offers o ON o.id=c.source_offer_id WHERE c.sportovec_id=? ORDER BY p.datum DESC,c.id DESC');
    $query->execute([$personId]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $today = clubMakeupNow()->format('Y-m-d');
    foreach ($rows as &$row) {
        $row['display_status'] = (string)$row['status'];
        if ($row['status'] === 'available' && $today > (string)$row['expires_on']) $row['display_status'] = 'expired';
        elseif ($row['status'] === 'available' && $today > (string)$row['source_ends_on'] && !clubMakeupCreditUsableOn($pdo,$row,$today)) $row['display_status'] = 'carried_locked';
    }
    unset($row);
    return $rows;
}

/** @return list<array<string,mixed>> */
function clubMakeupReservationsForPerson(PDO $pdo, int $personId): array
{
    if (!clubMakeupReady($pdo)) return [];
    $query=$pdo->prepare("SELECT r.id,r.status,p.nazev,p.datum,p.cas_od,c.source_plan_id FROM club_makeup_reservations r JOIN planovane_treninky p ON p.id=r.target_plan_id JOIN club_makeup_credits c ON c.id=r.credit_id WHERE r.sportovec_id=? AND r.status='booked' ORDER BY p.datum,p.cas_od,r.id");
    $query->execute([$personId]);return $query->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubMakeupAvailableSlots(PDO $pdo, int $personId, int $creditId): array
{
    if (!clubMakeupReady($pdo)) return [];
    $creditQuery = $pdo->prepare('SELECT c.*,o.season_id FROM club_makeup_credits c JOIN club_program_offers o ON o.id=c.source_offer_id WHERE c.id=? AND c.sportovec_id=?');
    $creditQuery->execute([$creditId,$personId]);$credit=$creditQuery->fetch(PDO::FETCH_ASSOC);
    if (!$credit || $credit['status'] !== 'available') return [];
    $birth = $pdo->prepare('SELECT narozeni FROM sportovci WHERE id=?');$birth->execute([$personId]);$born=(string)$birth->fetchColumn();
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$born)!==1) return [];
    $year = (int)substr($born,0,4);
    $query = $pdo->prepare("SELECT s.plan_id,s.capacity,s.birth_year_from,s.birth_year_to,p.nazev,p.datum,p.cas_od,o.name AS offer_name,(SELECT COUNT(*) FROM club_makeup_reservations r WHERE r.target_plan_id=s.plan_id AND r.status='booked') AS booked FROM club_makeup_slots s JOIN planovane_treninky p ON p.id=s.plan_id JOIN club_program_offers o ON o.id=s.offer_id JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id AND q.enabled=1 WHERE p.stav='planovany' AND s.accepting=1 AND p.datum>=? AND o.season_id=? AND o.starts_on<=p.datum AND o.ends_on>=p.datum AND s.birth_year_from<=? AND s.birth_year_to>=? AND NOT EXISTS (SELECT 1 FROM training_roster_links l JOIN club_roster_members m ON m.team_id=l.team_id WHERE l.plan_id=p.id AND m.sportovec_id=? AND m.status='active' AND m.valid_from<=p.datum AND (m.valid_to IS NULL OR m.valid_to>=p.datum)) ORDER BY p.datum,p.cas_od,p.id");
    $query->execute([clubMakeupNow()->format('Y-m-d'),(int)$credit['season_id'],$year,$year,$personId]);
    return array_values(array_filter($query->fetchAll(PDO::FETCH_ASSOC),static function(array $row) use ($pdo,$credit): bool {
        return (int)$row['plan_id'] !== (int)$credit['source_plan_id'] && (int)$row['booked'] < (int)$row['capacity']
            && clubMakeupStart($row) > clubMakeupNow() && clubMakeupCreditUsableOn($pdo,$credit,(string)$row['datum']);
    }));
}

function clubMakeupReserve(PDO $pdo, int $personId, int $creditId, int $targetPlanId, string $actorType, int $actorId): int
{
    if (!clubMakeupReady($pdo)) throw new ClubMakeupException('Náhradní lekce zatím nejsou dostupné.');
    if ($personId < 1 || $creditId < 1 || $targetPlanId < 1 || $actorId < 1) throw new InvalidArgumentException('Neplatná rezervace.');
    if ($actorType === 'account' && !familyPortalCanViewPerson($pdo,$actorId,$personId)) throw new ClubMakeupException('K dítěti nemáte oprávnění.');
    if ($actorType === 'athlete' && (int)(childAccessIdentity($pdo,$actorId)['sportovec_id'] ?? 0) !== $personId) throw new ClubMakeupException('K profilu nemáte oprávnění.');
    if (!in_array($actorType,['account','athlete'],true)) throw new InvalidArgumentException('Neplatný typ účtu.');
    $pdo->beginTransaction();
    try {
        $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $creditSql = 'SELECT c.*,o.season_id FROM club_makeup_credits c JOIN club_program_offers o ON o.id=c.source_offer_id WHERE c.id=? AND c.sportovec_id=?' . ($mysql ? ' FOR UPDATE' : '');
        $query=$pdo->prepare($creditSql);$query->execute([$creditId,$personId]);$credit=$query->fetch(PDO::FETCH_ASSOC);
        if (!$credit) throw new ClubMakeupException('Nárok neexistuje.');
        $slotSql = 'SELECT s.*,p.nazev,p.datum,p.cas_od,p.stav,o.season_id,o.starts_on,o.ends_on,q.enabled FROM club_makeup_slots s JOIN planovane_treninky p ON p.id=s.plan_id JOIN club_program_offers o ON o.id=s.offer_id JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id WHERE s.plan_id=?' . ($mysql ? ' FOR UPDATE' : '');
        $query=$pdo->prepare($slotSql);$query->execute([$targetPlanId]);$slot=$query->fetch(PDO::FETCH_ASSOC);
        if (!$slot || (int)$slot['accepting'] !== 1 || (int)$slot['enabled'] !== 1 || $slot['stav'] !== 'planovany' || (int)$slot['season_id'] !== (int)$credit['season_id'] || $slot['datum'] < $slot['starts_on'] || $slot['datum'] > $slot['ends_on'] || clubMakeupStart($slot) <= clubMakeupNow() || $targetPlanId === (int)$credit['source_plan_id']) throw new ClubMakeupException('Termín není dostupný pro náhradu.');
        $birth=$pdo->prepare('SELECT narozeni FROM sportovci WHERE id=?');$birth->execute([$personId]);$born=(string)$birth->fetchColumn();
        $year=(int)substr($born,0,4);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$born)!==1 || $year < (int)$slot['birth_year_from'] || $year > (int)$slot['birth_year_to']) throw new ClubMakeupException('Věk sportovce neodpovídá termínu.');
        if (!clubMakeupCreditUsableOn($pdo,$credit,(string)$slot['datum'])) throw new ClubMakeupException('Nárok není platný nebo chybí zaplacené navazující pololetí.');
        $count=$pdo->prepare("SELECT COUNT(*) FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");$count->execute([$targetPlanId]);
        if ((int)$count->fetchColumn() >= (int)$slot['capacity']) throw new ClubMakeupException('Kapacita náhradníků je obsazená.');
        $regular=$pdo->prepare("SELECT 1 FROM training_roster_links l JOIN club_roster_members m ON m.team_id=l.team_id WHERE l.plan_id=? AND m.sportovec_id=? AND m.status='active' AND m.valid_from<=? AND (m.valid_to IS NULL OR m.valid_to>=?) LIMIT 1");
        $regular->execute([$targetPlanId,$personId,(string)$slot['datum'],(string)$slot['datum']]);
        if ($regular->fetchColumn()) throw new ClubMakeupException('Dítě už patří do soupisek tohoto tréninku.');
        $pdo->prepare("INSERT INTO club_makeup_reservations(credit_id,target_plan_id,sportovec_id,status,active_token) VALUES(?,?,?,'booked','active')")->execute([$creditId,$targetPlanId,$personId]);
        $reservationId=(int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE club_makeup_credits SET status='reserved' WHERE id=?")->execute([$creditId]);
        clubMakeupEvent($pdo,$creditId,$reservationId,'reserved',$actorType,$actorId);
        $pdo->commit();return $reservationId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof ClubMakeupException || $exception instanceof InvalidArgumentException) throw $exception;
        throw new ClubMakeupException('Rezervaci se nepodařilo bezpečně uložit.',0,$exception);
    }
}

function clubMakeupCancelReservation(PDO $pdo, int $personId, int $reservationId, string $actorType, int $actorId): void
{
    if (!clubMakeupReady($pdo)) throw new ClubMakeupException('Náhradní lekce zatím nejsou dostupné.');
    if ($actorType === 'account' && !familyPortalCanViewPerson($pdo,$actorId,$personId)) throw new ClubMakeupException('K dítěti nemáte oprávnění.');
    if ($actorType === 'athlete' && (int)(childAccessIdentity($pdo,$actorId)['sportovec_id'] ?? 0) !== $personId) throw new ClubMakeupException('K profilu nemáte oprávnění.');
    if (!in_array($actorType,['account','athlete'],true)) throw new InvalidArgumentException('Neplatný typ účtu.');
    $pdo->beginTransaction();
    try {
        $sql="SELECT r.id,r.credit_id,p.datum,p.cas_od,q.excuse_hours FROM club_makeup_reservations r JOIN planovane_treninky p ON p.id=r.target_plan_id JOIN club_makeup_slots s ON s.plan_id=p.id JOIN club_program_offers o ON o.id=s.offer_id JOIN club_makeup_policies q ON q.program_id=o.program_id AND q.season_id=o.season_id AND q.team_id=o.team_id WHERE r.id=? AND r.sportovec_id=? AND r.status='booked'";
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $query=$pdo->prepare($sql);$query->execute([$reservationId,$personId]);$row=$query->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ClubMakeupException('Aktivní rezervace neexistuje.');
        if (clubMakeupNow() > clubMakeupStart($row)->modify('-' . (int)$row['excuse_hours'] . ' hours')) throw new ClubMakeupException('Na zrušení s vrácením nároku je pozdě. Kontaktujte trenéra.');
        $pdo->prepare("UPDATE club_makeup_reservations SET status='cancelled',active_token=NULL WHERE id=?")->execute([$reservationId]);
        $pdo->prepare("UPDATE club_makeup_credits SET status='available' WHERE id=? AND status='reserved'")->execute([(int)$row['credit_id']]);
        clubMakeupEvent($pdo,(int)$row['credit_id'],$reservationId,'cancelled',$actorType,$actorId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** @return list<array<string,mixed>> */
function clubMakeupReservationsForPlan(PDO $pdo, int $planId): array
{
    if (!clubMakeupReady($pdo)) return [];
    $query=$pdo->prepare("SELECT r.id,r.sportovec_id,sp.jmeno,sp.prijmeni,r.status,c.source_plan_id FROM club_makeup_reservations r JOIN sportovci sp ON sp.id=r.sportovec_id JOIN club_makeup_credits c ON c.id=r.credit_id WHERE r.target_plan_id=? AND r.status='booked' ORDER BY sp.prijmeni,sp.jmeno");
    $query->execute([$planId]);return $query->fetchAll(PDO::FETCH_ASSOC);
}

function clubMakeupFinalizeTarget(PDO $pdo, int $planId, int $trainingId, int $actorId): void
{
    if (!clubMakeupReady($pdo)) return;
    $query=$pdo->prepare("SELECT id,credit_id,sportovec_id FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");$query->execute([$planId]);
    $attendance=$pdo->prepare('SELECT 1 FROM trenink_sportovec WHERE trenink_id=? AND sportovec_id=?');
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attendance->execute([$trainingId,(int)$row['sportovec_id']]);$present=(bool)$attendance->fetchColumn();
        $pdo->prepare('UPDATE club_makeup_reservations SET status=?,active_token=NULL WHERE id=?')->execute([$present?'attended':'no_show',(int)$row['id']]);
        $pdo->prepare("UPDATE club_makeup_credits SET status='used' WHERE id=? AND status='reserved'")->execute([(int)$row['credit_id']]);
        clubMakeupEvent($pdo,(int)$row['credit_id'],(int)$row['id'],$present?'attended':'no_show','trainer',$actorId);
    }
}

function clubMakeupCancelPlannedTarget(PDO $pdo, int $planId, int $actorId): void
{
    if (!clubMakeupReady($pdo)) return;
    $query=$pdo->prepare("SELECT id,credit_id FROM club_makeup_reservations WHERE target_plan_id=? AND status='booked'");$query->execute([$planId]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pdo->prepare("UPDATE club_makeup_reservations SET status='cancelled',active_token=NULL WHERE id=?")->execute([(int)$row['id']]);
        $pdo->prepare("UPDATE club_makeup_credits SET status='available' WHERE id=? AND status='reserved'")->execute([(int)$row['credit_id']]);
        clubMakeupEvent($pdo,(int)$row['credit_id'],(int)$row['id'],'target_cancelled','trainer',$actorId);
    }
}

function clubMakeupReconcileRecordedAttendance(PDO $pdo, int $trainingId, int $actorId): void
{
    if (!clubMakeupReady($pdo)) return;
    $planQuery=$pdo->prepare('SELECT p.id,p.datum,t.datum AS actual_date FROM planovane_treninky p JOIN treninky t ON t.id=p.trenink_id WHERE p.trenink_id=?');
    $planQuery->execute([$trainingId]);$plan=$planQuery->fetch(PDO::FETCH_ASSOC);
    if (!$plan) return;
    if ((string)$plan['datum'] !== (string)$plan['actual_date']) throw new ClubMakeupException('Datum tréninku s náhradními lekcemi musí odpovídat plánovanému termínu.');
    $planId=(int)$plan['id'];
    $query=$pdo->prepare('SELECT c.id,c.sportovec_id,c.status,e.status AS enrollment_status,oi.line_amount_minor,r.response,r.makeup_excused_at FROM club_makeup_credits c JOIN club_program_enrollments e ON e.id=c.source_enrollment_id JOIN shop_order_items oi ON oi.id=e.source_order_item_id LEFT JOIN training_rsvps r ON r.plan_id=c.source_plan_id AND r.sportovec_id=c.sportovec_id WHERE c.source_plan_id=?');$query->execute([$planId]);
    $attendance=$pdo->prepare('SELECT 1 FROM trenink_sportovec WHERE trenink_id=? AND sportovec_id=?');
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $credit){
        $attendance->execute([$trainingId,(int)$credit['sportovec_id']]);$present=(bool)$attendance->fetchColumn();
        if($present && $credit['status']==='available'){
            $pdo->prepare("UPDATE club_makeup_credits SET status='revoked' WHERE id=?")->execute([(int)$credit['id']]);
            clubMakeupEvent($pdo,(int)$credit['id'],null,'attendance_revoke','trainer',$actorId);
        }elseif($present && in_array((string)$credit['status'],['reserved','used'],true)){
            throw new ClubMakeupException('Změna docházky by zneplatnila už rezervovaný nebo využitý nárok. Nejdřív případ vyřešte se správcem.');
        }elseif(!$present && $credit['status']==='revoked' && $credit['enrollment_status']==='active' && (int)$credit['line_amount_minor']>0 && $credit['response']==='not_going' && $credit['makeup_excused_at']!==null){
            $pdo->prepare("UPDATE club_makeup_credits SET status='available' WHERE id=?")->execute([(int)$credit['id']]);
            clubMakeupEvent($pdo,(int)$credit['id'],null,'attendance_restore','trainer',$actorId);
        }
    }
    clubMakeupFinalizeSource($pdo,$planId,$trainingId,$actorId);
    $reservations=$pdo->prepare("SELECT id,credit_id,sportovec_id,status FROM club_makeup_reservations WHERE target_plan_id=? AND status IN ('attended','no_show')");$reservations->execute([$planId]);
    foreach($reservations->fetchAll(PDO::FETCH_ASSOC) as $row){
        $attendance->execute([$trainingId,(int)$row['sportovec_id']]);$wanted=$attendance->fetchColumn()?'attended':'no_show';
        if($wanted!==$row['status']){
            $pdo->prepare('UPDATE club_makeup_reservations SET status=? WHERE id=?')->execute([$wanted,(int)$row['id']]);
            clubMakeupEvent($pdo,(int)$row['credit_id'],(int)$row['id'],'attendance_corrected','trainer',$actorId);
        }
    }
}

function clubMakeupOnEnrollmentCancelled(PDO $pdo, int $enrollmentId, int $actorId): void
{
    if (!clubMakeupReady($pdo)) return;
    if (!$pdo->inTransaction()) throw new ClubMakeupException('Ukončení nároků vyžaduje transakci.');
    $source=$pdo->prepare('SELECT id,status FROM club_makeup_credits WHERE source_enrollment_id=?');$source->execute([$enrollmentId]);
    foreach($source->fetchAll(PDO::FETCH_ASSOC) as $credit){
        if($credit['status']==='used') throw new ClubMakeupException('Účast má už vyčerpanou náhradní lekci. Storno vyžaduje individuální kontrolu správce.');
        if($credit['status']==='reserved'){
            $reservation=$pdo->prepare("SELECT id FROM club_makeup_reservations WHERE credit_id=? AND status='booked'");$reservation->execute([(int)$credit['id']]);
            foreach($reservation->fetchAll(PDO::FETCH_COLUMN) as $id){$pdo->prepare("UPDATE club_makeup_reservations SET status='cancelled',active_token=NULL WHERE id=?")->execute([(int)$id]);clubMakeupEvent($pdo,(int)$credit['id'],(int)$id,'source_cancelled','trainer',$actorId);}
        }
        $pdo->prepare("UPDATE club_makeup_credits SET status='revoked' WHERE id=? AND status IN ('available','reserved')")->execute([(int)$credit['id']]);
        clubMakeupEvent($pdo,(int)$credit['id'],null,'source_revoked','trainer',$actorId);
    }
    $follow=$pdo->prepare("SELECT c.id,c.status,c.sportovec_id,c.carry_offer_id,r.id AS reservation_id,r.status AS reservation_status,p.datum,o.ends_on AS source_ends_on,o.program_id,o.season_id,o.team_id FROM club_program_enrollments cancelled JOIN club_program_offers cancelled_offer ON cancelled_offer.id=cancelled.offer_id JOIN club_makeup_credits c ON c.sportovec_id=cancelled.sportovec_id AND c.carry_offer_id IS NOT NULL JOIN club_program_offers o ON o.id=c.source_offer_id AND o.program_id=cancelled_offer.program_id AND o.season_id=cancelled_offer.season_id AND o.team_id=cancelled_offer.team_id JOIN club_makeup_reservations r ON r.credit_id=c.id AND r.status IN ('booked','attended','no_show') JOIN planovane_treninky p ON p.id=r.target_plan_id AND p.datum>o.ends_on WHERE cancelled.id=?");
    $follow->execute([$enrollmentId]);
    $otherPaid=$pdo->prepare("SELECT 1 FROM club_program_enrollments e JOIN club_program_offers o ON o.id=e.offer_id JOIN shop_order_items oi ON oi.id=e.source_order_item_id AND oi.line_amount_minor>0 LEFT JOIN club_makeup_offer_periods period ON period.offer_id=o.id WHERE e.sportovec_id=? AND e.status='active' AND e.valid_from<=? AND e.valid_to>=? AND o.program_id=? AND o.season_id=? AND o.team_id=? AND (o.id=? OR period.period_kind='annual') LIMIT 1");
    foreach($follow->fetchAll(PDO::FETCH_ASSOC) as $credit){
        $otherPaid->execute([(int)$credit['sportovec_id'],(string)$credit['datum'],(string)$credit['datum'],(int)$credit['program_id'],(int)$credit['season_id'],(int)$credit['team_id'],(int)$credit['carry_offer_id']]);
        if ($otherPaid->fetchColumn()) continue;
        if ($credit['status']==='used') throw new ClubMakeupException('Navazující účast má už vyčerpanou převedenou náhradní lekci. Storno vyžaduje individuální kontrolu správce.');
        if ($credit['status']!=='reserved' || $credit['reservation_status']!=='booked') continue;
        $pdo->prepare("UPDATE club_makeup_reservations SET status='cancelled',active_token=NULL WHERE id=?")->execute([(int)$credit['reservation_id']]);
        $pdo->prepare("UPDATE club_makeup_credits SET status='available' WHERE id=? AND status='reserved'")->execute([(int)$credit['id']]);
        clubMakeupEvent($pdo,(int)$credit['id'],(int)$credit['reservation_id'],'continuation_cancelled','trainer',$actorId);
    }
}
