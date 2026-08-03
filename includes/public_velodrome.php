<?php
declare(strict_types=1);

require_once __DIR__ . '/public_profile.php';

final class PublicVelodromeException extends RuntimeException
{
}

/** @return array{id:int,created:bool} */
function publicVelodromeCreateSlot(
    PDO $pdo,
    int $actorTrainerId,
    string $date,
    string $startsAt,
    string $endsAt,
    int $capacity,
    bool $exclusive,
    int $priceMinor
): array {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($date) . ' ' . trim($startsAt));
    $end = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($date) . ' ' . trim($endsAt));
    if ($actorTrainerId < 1 || !$start || !$end || $end <= $start || $start <= new DateTimeImmutable('now')
        || $capacity < 1 || $capacity > 1000 || $priceMinor < 0 || $priceMinor > 100000000
    ) {
        throw new InvalidArgumentException('Termín, kapacita nebo cena nejsou platné.');
    }
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare("SELECT id FROM treneri WHERE id=? AND aktivni=1 AND role='admin'");
        $actor->execute([$actorTrainerId]);
        if (!$actor->fetchColumn()) {
            throw new PublicVelodromeException('Aktivní administrátor nebyl nalezen.');
        }
        $placeSql = "SELECT id FROM sportovist WHERE kod='velodrom' AND je_verejne=1 AND aktivni=1";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $placeSql .= ' FOR UPDATE';
        }
        $sportovisteId = (int)$pdo->query($placeSql)->fetchColumn();
        if ($sportovisteId < 1) {
            throw new PublicVelodromeException('Aktivní veřejný velodrom nebyl nalezen.');
        }
        $overlap = $pdo->prepare(
            "SELECT id FROM individualni_lekce WHERE sportoviste_id=? AND datum=? AND stav='aktivni' "
            . 'AND cas_od<? AND cas_do>? LIMIT 1'
        );
        $overlap->execute([
            $sportovisteId, $start->format('Y-m-d'), $end->format('H:i:s'), $start->format('H:i:s'),
        ]);
        if ($overlap->fetchColumn()) {
            throw new PublicVelodromeException('Termín se překrývá s jinou aktivní lekcí velodromu.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO individualni_lekce '
            . '(trener_id,sportoviste_id,datum,cas_od,cas_do,slot_delka_min,typ,nazev,popis,cena_kc,max_osob, '
            . "vyjimka_3_dny,stav,public_exclusive_booking) VALUES (?,?,?,?,?,?,'zelena',?,?,?, ?,1,'aktivni',?)"
        );
        $minutes = max(1, (int)(($end->getTimestamp() - $start->getTimestamp()) / 60));
        $insert->execute([
            $actorTrainerId,
            $sportovisteId,
            $start->format('Y-m-d'),
            $start->format('H:i:s'),
            $end->format('H:i:s'),
            $minutes,
            'Veřejná hodina velodromu',
            $exclusive ? 'Výhradní rezervace celého slotu.' : 'Veřejná rezervace místa ve slotu.',
            number_format($priceMinor / 100, 2, '.', ''),
            $exclusive ? 1 : $capacity,
            $exclusive ? 1 : 0,
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return ['id' => $id, 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicVelodromeException) {
            throw $exception;
        }
        throw new PublicVelodromeException('Termín se nepodařilo vytvořit bez částečné změny.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function publicVelodromeSlots(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT il.*,s.nazev AS sportoviste_name, "
        . "(SELECT COUNT(*) FROM verejne_rezervace r WHERE r.lekce_id=il.id "
        . "AND r.stav IN ('ceka','potvrzena')) AS reserved_count "
        . 'FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id '
        . "WHERE s.kod='velodrom' AND s.je_verejne=1 AND s.aktivni=1 "
        . "AND il.stav='aktivni' AND il.datum>=CURRENT_DATE ORDER BY il.datum,il.cas_od,il.id"
    );
    $slots = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($slots as &$slot) {
        $capacity = (int)$slot['public_exclusive_booking'] === 1 ? 1 : (int)$slot['max_osob'];
        $slot['effective_capacity'] = $capacity;
        $slot['remaining_capacity'] = max(0, $capacity - (int)$slot['reserved_count']);
    }
    unset($slot);
    return $slots;
}

/** @return list<array<string,mixed>> */
function publicVelodromeReservationsForAccount(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT r.*,il.nazev,il.datum,il.cas_od,il.cas_do,il.cena_kc, '
        . 's.nazev AS sportoviste_name,sp.jmeno,sp.prijmeni '
        . 'FROM verejne_rezervace r JOIN individualni_lekce il ON il.id=r.lekce_id '
        . 'JOIN sportovist s ON s.id=il.sportoviste_id JOIN sportovci sp ON sp.id=r.sportovec_id '
        . "WHERE r.uzivatel_id=? AND s.kod='velodrom' AND r.sportovec_id IS NOT NULL "
        . 'ORDER BY il.datum DESC,il.cas_od DESC,r.id DESC'
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function publicVelodromeAdminReservations(PDO $pdo): array
{
    return $pdo->query(
        'SELECT r.*,il.nazev,il.datum,il.cas_od,il.cas_do,il.cena_kc, '
        . 'vu.email,sp.jmeno,sp.prijmeni FROM verejne_rezervace r '
        . 'JOIN individualni_lekce il ON il.id=r.lekce_id '
        . 'JOIN sportovist s ON s.id=il.sportoviste_id '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.uzivatel_id '
        . 'JOIN sportovci sp ON sp.id=r.sportovec_id '
        . "WHERE s.kod='velodrom' AND r.sportovec_id IS NOT NULL ORDER BY r.id DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,status:string,changed:bool} */
function publicVelodromeManualConfirm(
    PDO $pdo,
    int $reservationId,
    int $actorTrainerId,
    string $note,
    bool $confirmed
): array {
    $note = trim($note);
    if ($reservationId < 1 || $actorTrainerId < 1 || $note === '' || !$confirmed
        || mb_strlen($note, 'UTF-8') > 1000
    ) {
        throw new InvalidArgumentException('Potvrzení platby vyžaduje rezervaci, správce, důvod a souhlas.');
    }
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare("SELECT id FROM treneri WHERE id=? AND aktivni=1 AND role='admin'");
        $actor->execute([$actorTrainerId]);
        if (!$actor->fetchColumn()) {
            throw new PublicVelodromeException('Aktivní správce nebyl nalezen.');
        }
        $sql = 'SELECT r.*,il.cena_kc,s.kod FROM verejne_rezervace r '
            . 'JOIN individualni_lekce il ON il.id=r.lekce_id '
            . 'JOIN sportovist s ON s.id=il.sportoviste_id WHERE r.id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$reservationId]);
        $reservation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$reservation || $reservation['kod'] !== 'velodrom' || $reservation['sportovec_id'] === null) {
            throw new PublicVelodromeException('Placená rezervace velodromu nebyla nalezena.');
        }
        if ($reservation['stav'] === 'potvrzena' && (int)$reservation['zaplaceno'] === 1) {
            $pdo->commit();
            return ['id' => $reservationId, 'status' => 'potvrzena', 'changed' => false];
        }
        if ($reservation['stav'] !== 'ceka' || (int)$reservation['zaplaceno'] !== 0
            || (float)$reservation['cena_kc'] <= 0.0 || $reservation['active_token'] !== 'active'
        ) {
            throw new PublicVelodromeException('Rezervace není v platném stavu pro ruční potvrzení platby.');
        }
        $pdo->prepare(
            "UPDATE verejne_rezervace SET stav='potvrzena',zaplaceno=1,cas_potvrzeni=CURRENT_TIMESTAMP WHERE id=?"
        )->execute([$reservationId]);
        publicVelodromeAudit(
            $pdo,
            $reservationId,
            'trainer',
            $actorTrainerId,
            'manual_payment_confirm',
            'ceka',
            'potvrzena',
            $note
        );
        $pdo->commit();
        return ['id' => $reservationId, 'status' => 'potvrzena', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicVelodromeException) {
            throw $exception;
        }
        throw new PublicVelodromeException('Potvrzení platby selhalo bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,status:string,created:bool} */
function publicVelodromeReserve(PDO $pdo, int $lessonId, int $accountId, string $note = ''): array
{
    $note = trim($note);
    if ($lessonId < 1 || $accountId < 1 || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Rezervace vyžaduje termín, účet a platnou poznámku.');
    }
    $pdo->beginTransaction();
    try {
        $profile = publicVelodromeLockProfile($pdo, $accountId);
        if (!$profile) {
            throw new PublicVelodromeException('Nejprve dokončete svůj veřejný profil.');
        }
        $lessonSql = 'SELECT il.*,s.kod,s.je_verejne,s.aktivni FROM individualni_lekce il '
            . 'JOIN sportovist s ON s.id=il.sportoviste_id WHERE il.id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lessonSql .= ' FOR UPDATE';
        }
        $lessonStatement = $pdo->prepare($lessonSql);
        $lessonStatement->execute([$lessonId]);
        $lesson = $lessonStatement->fetch(PDO::FETCH_ASSOC);
        if (!$lesson || $lesson['kod'] !== 'velodrom' || (int)$lesson['je_verejne'] !== 1
            || (int)$lesson['aktivni'] !== 1 || $lesson['stav'] !== 'aktivni'
        ) {
            throw new PublicVelodromeException('Veřejný termín velodromu nebyl nalezen.');
        }
        $startsAt = new DateTimeImmutable((string)$lesson['datum'] . ' ' . (string)$lesson['cas_od']);
        if ($startsAt <= new DateTimeImmutable('now')) {
            throw new PublicVelodromeException('Termín už začal nebo skončil.');
        }
        $existing = $pdo->prepare(
            "SELECT id,stav FROM verejne_rezervace WHERE lekce_id=? AND sportovec_id=? "
            . "AND active_token='active' AND stav IN ('ceka','potvrzena') LIMIT 1"
        );
        $existing->execute([$lessonId, (int)$profile['sportovec_id']]);
        $reservation = $existing->fetch(PDO::FETCH_ASSOC);
        if ($reservation) {
            $pdo->commit();
            return ['id' => (int)$reservation['id'], 'status' => (string)$reservation['stav'], 'created' => false];
        }
        $overlap = $pdo->prepare(
            'SELECT r.id FROM verejne_rezervace r JOIN individualni_lekce other ON other.id=r.lekce_id '
            . "WHERE r.sportovec_id=? AND r.active_token='active' AND r.stav IN ('ceka','potvrzena') "
            . 'AND other.datum=? AND other.cas_od<? AND other.cas_do>? LIMIT 1'
        );
        $overlap->execute([
            (int)$profile['sportovec_id'], $lesson['datum'], $lesson['cas_do'], $lesson['cas_od'],
        ]);
        if ($overlap->fetchColumn()) {
            throw new PublicVelodromeException('Účastník už má v tomto čase jinou aktivní rezervaci.');
        }
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=? AND stav IN ('ceka','potvrzena')"
        );
        $count->execute([$lessonId]);
        $capacity = (int)$lesson['public_exclusive_booking'] === 1 ? 1 : (int)$lesson['max_osob'];
        if ($capacity < 1 || (int)$count->fetchColumn() >= $capacity) {
            throw new PublicVelodromeException('Kapacita termínu je naplněna.');
        }
        $paid = (float)$lesson['cena_kc'] <= 0.0;
        $status = $paid ? 'potvrzena' : 'ceka';
        $insert = $pdo->prepare(
            'INSERT INTO verejne_rezervace '
            . '(lekce_id,uzivatel_id,sportovec_id,stav,zaplaceno,poznamka_klienta,slot_cas_od,slot_cas_do,active_token) '
            . "VALUES (?,?,?,?,?,?,?,?, 'active')"
        );
        $insert->execute([
            $lessonId,
            $accountId,
            (int)$profile['sportovec_id'],
            $status,
            $paid ? 1 : 0,
            $note !== '' ? $note : null,
            $lesson['cas_od'],
            $lesson['cas_do'],
        ]);
        $reservationId = (int)$pdo->lastInsertId();
        publicVelodromeAudit(
            $pdo,
            $reservationId,
            'account',
            $accountId,
            'reserve',
            null,
            $status,
            $paid ? 'Bezplatná rezervace velodromu.' : 'Rezervace čeká na ruční potvrzení platby.'
        );
        $pdo->commit();
        return ['id' => $reservationId, 'status' => $status, 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicVelodromeException) {
            throw $exception;
        }
        throw new PublicVelodromeException('Rezervace selhala bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,status:string,changed:bool} */
function publicVelodromeCancel(PDO $pdo, int $reservationId, int $accountId, string $note): array
{
    $note = trim($note);
    if ($reservationId < 1 || $accountId < 1 || $note === '' || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Storno vyžaduje rezervaci, vlastníka a důvod.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT r.* FROM verejne_rezervace r JOIN individualni_lekce il ON il.id=r.lekce_id '
            . "JOIN sportovist s ON s.id=il.sportoviste_id WHERE r.id=? AND s.kod='velodrom' "
            . 'AND r.sportovec_id IS NOT NULL';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$reservationId]);
        $reservation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$reservation || (int)$reservation['uzivatel_id'] !== $accountId) {
            throw new PublicVelodromeException('Rezervace nebyla nalezena.');
        }
        if ($reservation['stav'] === 'zrusena') {
            $pdo->commit();
            return ['id' => $reservationId, 'status' => 'zrusena', 'changed' => false];
        }
        if (!in_array($reservation['stav'], ['ceka', 'potvrzena'], true)) {
            throw new PublicVelodromeException('Tuto rezervaci už nelze zrušit.');
        }
        $pdo->prepare(
            "UPDATE verejne_rezervace SET stav='zrusena',active_token=NULL,poznamka_trenera=? WHERE id=?"
        )->execute([$note, $reservationId]);
        publicVelodromeAudit(
            $pdo,
            $reservationId,
            'account',
            $accountId,
            'cancel',
            (string)$reservation['stav'],
            'zrusena',
            $note
        );
        $pdo->commit();
        return ['id' => $reservationId, 'status' => 'zrusena', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicVelodromeException) {
            throw $exception;
        }
        throw new PublicVelodromeException('Storno selhalo bez částečného zápisu.', 0, $exception);
    }
}

/** @return array<string,mixed>|false */
function publicVelodromeLockProfile(PDO $pdo, int $accountId): array|false
{
    $sql = 'SELECT p.sportovec_id,vu.email_overeno,vu.aktivni FROM public_self_profiles p '
        . 'JOIN verejni_uzivatele vu ON vu.id=p.account_id JOIN sportovci s ON s.id=p.sportovec_id '
        . 'WHERE p.account_id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$accountId]);
    $profile = $statement->fetch(PDO::FETCH_ASSOC);
    return $profile && (int)$profile['aktivni'] === 1 && (int)$profile['email_overeno'] === 1
        ? $profile
        : false;
}

function publicVelodromeAudit(
    PDO $pdo,
    int $reservationId,
    string $actorType,
    ?int $actorId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    string $note
): void {
    $pdo->prepare(
        'INSERT INTO public_velodrome_reservation_events '
        . '(reservation_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)'
    )->execute([$reservationId, $actorType, $actorId, $action, $fromStatus, $toStatus, $note]);
}
