<?php
declare(strict_types=1);

require_once __DIR__.'/training_day.php';
require_once __DIR__.'/training_rsvp.php';
require_once __DIR__.'/club_makeup.php';

/** One planned lesson, its dated roster, excuses, reservations and saved revision. */
function coachAttendanceLoad(PDO $pdo, int $planId, int $actor): array
{
    $plan = trainingDayPlan($pdo, $actor, $planId);
    if (!staffActivePositionIs('sports_lead') && (int)$plan['trener_id'] !== $actor) {
        throw new TrainingClosureException('Docházku tohoto termínu smí zapsat přiřazený trenér.');
    }
    $trainingId = (int)($plan['trenink_id'] ?? 0);
    $current = $trainingId ? trainingClosureCurrent($pdo, $trainingId) : null;
    $rawPresent = [];
    if ($trainingId && $current === null) {
        $rawQuery = $pdo->prepare('SELECT sportovec_id FROM trenink_sportovec WHERE trenink_id=?');
        $rawQuery->execute([$trainingId]);
        $rawPresent = array_map('intval', $rawQuery->fetchAll(PDO::FETCH_COLUMN));
    }
    $people = [];
    $hasCurrentRoster = trainingRsvpHasTables($pdo, ['training_roster_links','club_roster_members','planovane_treninky']);
    $eligible = $hasCurrentRoster ? trainingRsvpEligibleMemberIds($pdo, $planId, (string)$plan['datum']) : [];
    if (!$hasCurrentRoster) {
        $eligible = array_map('intval', array_column(trainingRosterBridgeExpectedForPlan($pdo, $planId), 'id'));
    }
    foreach ($eligible as $id) {
        $people[(int)$id] = ['id' => (int)$id, 'makeup' => false, 'reservation_id' => null,
            'excuse' => null, 'state' => ''];
    }
    if (clubMakeupReady($pdo)) {
        $q = $pdo->prepare("SELECT r.id,r.sportovec_id,r.status FROM club_makeup_reservations r WHERE r.target_plan_id=? AND r.status IN ('booked','attended','no_show')");
        $q->execute([$planId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['sportovec_id'];
            $people[$id] ??= ['id' => $id, 'makeup' => false, 'reservation_id' => null, 'excuse' => null, 'state' => ''];
            $people[$id]['makeup'] = true;
            $people[$id]['reservation_id'] = (int)$row['id'];
        }
    }
    foreach (array_keys($current['snapshot']['attendance_states'] ?? []) as $id) {
        $id = (int)$id;
        $people[$id] ??= ['id' => $id, 'makeup' => false, 'reservation_id' => null, 'excuse' => null, 'state' => ''];
    }
    foreach ($current['snapshot']['makeup_reservation_ids'] ?? [] as $id => $reservationId) {
        $id = (int)$id;
        $people[$id] ??= ['id' => $id, 'makeup' => false, 'reservation_id' => null, 'excuse' => null, 'state' => ''];
        $people[$id]['makeup'] = true;
        $people[$id]['reservation_id'] = (int)$reservationId;
    }
    foreach ($current['snapshot']['participant_ids'] ?? [] as $id) {
        $id = (int)$id;
        $people[$id] ??= ['id' => $id, 'makeup' => false, 'reservation_id' => null, 'excuse' => null, 'state' => ''];
    }
    foreach ($rawPresent as $id) {
        $people[$id] ??= ['id' => $id, 'makeup' => false, 'reservation_id' => null, 'excuse' => null, 'state' => ''];
    }
    if ($people !== []) {
        $ids = array_keys($people);
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $q = $pdo->prepare("SELECT id,jmeno,prijmeni FROM sportovci WHERE id IN ($marks)");
            $q->execute($chunk);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $person) {
                $people[(int)$person['id']]['label'] = trim($person['prijmeni'].' '.$person['jmeno']);
            }
        }
    }
    $q = $pdo->prepare("SELECT sportovec_id,member_note FROM training_rsvps WHERE plan_id=? AND response='not_going'");
    $q->execute([$planId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['sportovec_id'];
        if (isset($people[$id])) {
            $people[$id]['excuse'] = trim((string)($row['member_note'] ?? '')) ?: 'Omluveno rodičem nebo sportovcem.';
        }
    }
    $q = $pdo->prepare('SELECT sportovec_id,source,reason FROM training_personal_excuses WHERE plan_id=?');
    $q->execute([$planId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)$row['sportovec_id'];
        if (isset($people[$id])) {
            $people[$id]['excuse'] = trim((string)$row['reason']) ?: 'Osobně přijatá omluva.';
        }
    }
    $saved = $current['snapshot']['attendance_states'] ?? [];
    $present = $current ? array_map('intval', $current['snapshot']['participant_ids'] ?? []) : $rawPresent;
    foreach ($people as $id => &$person) {
        if (!isset($person['label'])) {
            unset($people[$id]);
            continue;
        }
        $person['state'] = $saved[$id] ?? (in_array($id, $present, true) ? 'present' : ($person['excuse'] !== null ? 'excused' : ''));
    }
    unset($person);
    uasort($people, static fn (array $a, array $b): int => ($a['makeup'] <=> $b['makeup']) ?: strcasecmp($a['label'], $b['label']));
    return ['plan' => $plan, 'people' => $people, 'current' => $current, 'training_id' => $trainingId];
}

/** Save a draft or a final revision in the existing attendance history. */
function coachAttendanceSave(PDO $pdo, int $planId, int $actor, int $expectedVersion, array $posted): array
{
    $pdo->beginTransaction();
    try {
        $view = coachAttendanceLoad($pdo, $planId, $actor);
        $plan = $view['plan'];
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $q = $pdo->prepare('SELECT id,trenink_id,stav FROM planovane_treninky WHERE id=?'.$lock);
        $q->execute([$planId]);
        $locked = $q->fetch(PDO::FETCH_ASSOC);
        if (!$locked || $locked['stav'] === 'zruseny' || (int)$locked['trenink_id'] !== $view['training_id']) {
            throw new TrainingClosureException('Termín se mezitím změnil. Načtěte docházku znovu.');
        }
        if ($expectedVersion !== (int)($view['current']['version'] ?? 0)) {
            throw new TrainingClosureException('Docházku mezitím změnil jiný pracovník. Načtěte ji znovu.');
        }
        $end = trainingClosureDateTime((string)($view['current']['actual_ended_at']
            ?? ($plan['datum'].' '.substr((string)$plan['cas_do'], 0, 5).':00')));
        $start = trainingClosureDateTime((string)($view['current']['actual_started_at']
            ?? ($plan['datum'].' '.substr((string)$plan['cas_od'], 0, 5).':00')));
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        if ($start > $now) {
            throw new TrainingClosureException('Docházku lze začít zapisovat nejdříve v den konání lekce.');
        }
        $deadline = $end->setTimezone(new DateTimeZone('UTC'))->add(new DateInterval('PT72H'));
        if (staffActivePositionIs('coach') && $now > $deadline) {
            throw new TrainingClosureException('Lhůta 72 hodin pro opravu uplynula. Obraťte se na vedoucího sportu.');
        }
        if (!is_array($posted)) {
            throw new TrainingClosureException('Neplatný formulář docházky.');
        }
        if ($view['people'] === []) {
            throw new TrainingClosureException('Termín nemá očekávané děti ani rezervované náhradníky. Zkontrolujte soupisku v plánovači.');
        }
        $states = [];
        $present = [];
        foreach ($view['people'] as $id => $person) {
            $value = $posted[$id] ?? '';
            if (!is_string($value) || !in_array($value, ['', 'present', 'absent', 'excused'], true)) {
                throw new TrainingClosureException('Neplatný stav docházky.');
            }
            if ($value === 'absent' && $person['excuse'] !== null) {
                throw new TrainingClosureException('Evidovanou omluvu nelze změnit na neomluvenou absenci.');
            }
            if ($value === 'excused' && $person['excuse'] === null) {
                throw new TrainingClosureException('Omluvu nejprve zaznamenejte v přípravě lekce.');
            }
            if ($value !== '') {
                $states[$id] = $value;
            }
            if ($value === 'present') {
                $present[] = $id;
            }
        }
        foreach ($posted as $id => $unused) {
            if (!ctype_digit((string)$id) || !isset($view['people'][(int)$id])) {
                throw new TrainingClosureException('Docházka obsahuje osobu mimo tento termín.');
            }
        }
        $draft = count($states) < count($view['people']) || $end > $now;
        if ($draft && ($view['current']['status'] ?? '') === 'closed') {
            throw new TrainingClosureException('Uzavřenou docházku lze opravit jen úplným novým zápisem.');
        }
        if (($view['current']['status'] ?? '') === 'void' && staffActivePositionIs('coach')) {
            throw new TrainingClosureException('Zneplatněnou lekci může obnovit jen vedoucí sportu.');
        }
        $trainingId = $view['training_id'];
        if (!$trainingId) {
            if ($locked['stav'] !== 'planovany') {
                throw new TrainingClosureException('Termín už má jinou evidenci.');
            }
            $duration = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            $q = $pdo->prepare('INSERT INTO treninky(datum,delka,napln,poznamka) VALUES(?,?,?,?)');
            $q->execute([$plan['datum'], $duration, (string)$plan['nazev'], null]);
            $trainingId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO trenink_trener(trenink_id,trener_id) VALUES(?,?)')->execute([$trainingId,$actor]);
            if ((int)($plan['skupina_id'] ?? 0) > 0) {
                $pdo->prepare('INSERT INTO trenink_skupina(trenink_id,skupina_id) VALUES(?,?)')->execute([$trainingId,(int)$plan['skupina_id']]);
            }
            trainingRosterBridgeCopyPlanToTraining($pdo, $planId, $trainingId, $actor);
            $pdo->prepare("UPDATE planovane_treninky SET trenink_id=?,stav='evidovany' WHERE id=? AND stav='planovany'")
                ->execute([$trainingId,$planId]);
        }
        $snapshotExpected = array_map('intval', array_column(trainingRosterBridgeExpectedForPlan($pdo, $planId), 'id'));
        $guestReasons = (array)($view['current']['snapshot']['guest_reasons'] ?? []);
        $reservationIds = (array)($view['current']['snapshot']['makeup_reservation_ids'] ?? []);
        foreach ($view['people'] as $personId => $person) {
            if ($person['makeup'] && (int)$person['reservation_id'] > 0) {
                $reservationIds[$personId] = (int)$person['reservation_id'];
            }
        }
        foreach (array_diff($present, $snapshotExpected) as $personId) {
            $guestReasons[$personId] ??= 'Člen platné soupisky nebo rezervovaný náhradník k termínu.';
        }
        $coachesQuery = $pdo->prepare('SELECT trener_id FROM trenink_trener WHERE trenink_id=?');
        $coachesQuery->execute([$trainingId]);
        $coachIds = array_map('intval', $coachesQuery->fetchAll(PDO::FETCH_COLUMN));
        if ($coachIds === []) {
            $coachIds = [$actor];
        }
        $result = trainingClosureClose($pdo, $trainingId, $expectedVersion, [
            'actual_started_at' => $start->format('Y-m-d H:i:s'),
            'actual_ended_at' => $end->format('Y-m-d H:i:s'),
            'participant_ids' => $present, 'coach_ids' => $coachIds,
            'attendance_states' => $states, 'makeup_reservation_ids' => $reservationIds,
            'guest_reasons' => $guestReasons,
            'reason' => $expectedVersion ? 'Oprava docházky trenérem.' : 'Zápis docházky trenérem.',
            'confirmed' => true, 'draft' => $draft,
            'record_source' => $view['current']['snapshot']['record_source'] ?? 'coach',
        ], $actor);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
