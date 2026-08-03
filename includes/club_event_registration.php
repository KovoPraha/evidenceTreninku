<?php
declare(strict_types=1);

require_once __DIR__ . '/club_event.php';
require_once __DIR__ . '/account_person_role.php';

final class ClubEventRegistrationException extends RuntimeException
{
}

/** @return array{id:int,status:string,changed:bool} */
function clubEventOpenFreeRegistration(
    PDO $pdo,
    int $eventId,
    int $actorTrainerId,
    string $note,
    bool $confirmed
): array {
    $note = trim($note);
    if ($eventId < 1 || $actorTrainerId < 1 || $note === '' || !$confirmed) {
        throw new InvalidArgumentException('Otevření vyžaduje akci, administrátora, důvod a výslovné potvrzení.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }

    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event) {
            throw new ClubEventRegistrationException('Kroužek nebyl nalezen.');
        }
        if ($event['status'] === 'open') {
            $pdo->commit();
            return ['id' => $eventId, 'status' => 'open', 'changed' => false];
        }
        if ($event['status'] !== 'draft'
            || $event['event_type'] !== 'club_event'
            || $event['pricing_policy'] !== 'free'
        ) {
            throw new ClubEventRegistrationException('Otevřít lze pouze bezplatný kroužek ve stavu draft.');
        }
        $sessions = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_sessions WHERE event_id=? AND status='scheduled'"
        );
        $sessions->execute([$eventId]);
        if ((int)$sessions->fetchColumn() < 1) {
            throw new ClubEventRegistrationException('Před otevřením přidejte alespoň jeden termín.');
        }
        $products = $pdo->prepare('SELECT COUNT(*) FROM shop_product_event_links WHERE event_id=?');
        $products->execute([$eventId]);
        if ((int)$products->fetchColumn() < 1) {
            throw new ClubEventRegistrationException('Před otevřením propojte kroužek s bezplatným produktem.');
        }
        $variants = $pdo->prepare(
            'SELECT v.price_mode,v.amount_minor FROM shop_product_event_links l '
            . 'JOIN shop_variants v ON v.product_id=l.product_id '
            . 'WHERE l.event_id=? AND (v.visible IS NULL OR v.visible=1)'
        );
        $variants->execute([$eventId]);
        $currentVariants = $variants->fetchAll(PDO::FETCH_ASSOC);
        if ($currentVariants === []) {
            throw new ClubEventRegistrationException('Propojený produkt nemá použitelnou bezplatnou variantu.');
        }
        foreach ($currentVariants as $variant) {
            if ($variant['price_mode'] !== 'free' && (int)($variant['amount_minor'] ?? -1) !== 0) {
                throw new ClubEventRegistrationException('Propojený produkt už není bezplatný.');
            }
        }

        $pdo->prepare("UPDATE club_events SET status='open', updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$eventId]);
        clubEventAudit($pdo, $eventId, $actorTrainerId, 'open_registration', 'event', $eventId, $note, [
            'pricing_policy' => 'free',
            'capacity' => clubEventEffectiveCapacity($pdo, $eventId, (int)$event['capacity']),
        ]);
        $pdo->commit();
        return ['id' => $eventId, 'status' => 'open', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Kroužek se nepodařilo bezpečně otevřít.', 0, $exception);
    }
}

/** @return array{id:int,status:string,changed:bool} */
function clubEventCloseRegistration(PDO $pdo, int $eventId, int $actorTrainerId, string $note): array
{
    $note = trim($note);
    if ($eventId < 1 || $actorTrainerId < 1 || $note === '') {
        throw new InvalidArgumentException('Uzavření vyžaduje akci, administrátora a důvod.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event) {
            throw new ClubEventRegistrationException('Kroužek nebyl nalezen.');
        }
        if ($event['status'] === 'closed') {
            $pdo->commit();
            return ['id' => $eventId, 'status' => 'closed', 'changed' => false];
        }
        if ($event['status'] !== 'open') {
            throw new ClubEventRegistrationException('Uzavřít lze pouze otevřený kroužek.');
        }
        $pdo->prepare("UPDATE club_events SET status='closed', updated_at=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([$eventId]);
        clubEventAudit($pdo, $eventId, $actorTrainerId, 'close_registration', 'event', $eventId, $note, []);
        $pdo->commit();
        return ['id' => $eventId, 'status' => 'closed', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Kroužek se nepodařilo bezpečně uzavřít.', 0, $exception);
    }
}

/** @return array{id:int,status:string,created:bool} */
function clubEventRegisterParticipant(PDO $pdo, int $eventId, int $accountId, int $sportovecId): array
{
    if ($eventId < 1 || $accountId < 1 || $sportovecId < 1) {
        throw new InvalidArgumentException('Přihlášení vyžaduje kroužek, účet a schválenou osobu.');
    }
    $pdo->beginTransaction();
    try {
        // The event row is the capacity mutex in MariaDB. Every registration and reactivation locks it first.
        $event = clubEventLock($pdo, $eventId);
        if (!$event || $event['status'] !== 'open' || $event['event_type'] !== 'club_event'
            || $event['pricing_policy'] !== 'free'
        ) {
            throw new ClubEventRegistrationException('Kroužek není otevřený pro bezplatné přihlášení.');
        }
        clubEventAssertRegistrationWindow($event);

        $relation = clubEventEligibleRelation($pdo, $accountId, $sportovecId);
        if (!$relation) {
            throw new ClubEventRegistrationException('Tuto osobu nemáte v K2 schválenou pro přihlášení.');
        }
        clubEventAssertAge($pdo, $eventId, $relation, $event);

        $existingSql = 'SELECT * FROM club_event_registrations WHERE event_id=? AND sportovec_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $existingSql .= ' FOR UPDATE';
        }
        $existingStatement = $pdo->prepare($existingSql);
        $existingStatement->execute([$eventId, $sportovecId]);
        $existing = $existingStatement->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['status'] === 'confirmed') {
            $pdo->commit();
            return ['id' => (int)$existing['id'], 'status' => 'confirmed', 'created' => false];
        }

        $capacity = clubEventEffectiveCapacity($pdo, $eventId, (int)$event['capacity']);
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='confirmed'"
        );
        $count->execute([$eventId]);
        if ((int)$count->fetchColumn() >= $capacity) {
            throw new ClubEventRegistrationException('Kapacita kroužku je již naplněna.');
        }

        if ($existing) {
            $update = $pdo->prepare(
                "UPDATE club_event_registrations SET account_id=?, relation_role_snapshot=?, status='confirmed', "
                . 'registered_at=CURRENT_TIMESTAMP, cancelled_at=NULL, cancellation_note=NULL, '
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([$accountId, $relation['relation_role'], (int)$existing['id']]);
            $registrationId = (int)$existing['id'];
            $action = 'reactivate';
            $fromStatus = (string)$existing['status'];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO club_event_registrations '
                . '(event_id,account_id,sportovec_id,relation_role_snapshot,status,registered_at) '
                . "VALUES (?,?,?,?,'confirmed',CURRENT_TIMESTAMP)"
            );
            $insert->execute([$eventId, $accountId, $sportovecId, $relation['relation_role']]);
            $registrationId = (int)$pdo->lastInsertId();
            $action = 'register';
            $fromStatus = null;
        }
        clubEventRegistrationAudit(
            $pdo,
            $registrationId,
            'account',
            $accountId,
            $action,
            $fromStatus,
            'confirmed',
            'Bezplatná přihláška schválené osoby z K2.'
        );
        $pdo->commit();
        return ['id' => $registrationId, 'status' => 'confirmed', 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Přihlášku se nepodařilo uložit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,status:string,changed:bool} */
function clubEventCancelRegistration(PDO $pdo, int $registrationId, int $accountId, string $note): array
{
    $note = trim($note);
    if ($registrationId < 1 || $accountId < 1 || $note === '') {
        throw new InvalidArgumentException('Zrušení přihlášky vyžaduje přihlášku, účet a důvod.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT event_id FROM club_event_registrations WHERE id=?');
        $lookup->execute([$registrationId]);
        $eventId = (int)$lookup->fetchColumn();
        if ($eventId < 1) {
            throw new ClubEventRegistrationException('Přihláška nebyla nalezena.');
        }
        clubEventLock($pdo, $eventId);
        $sql = 'SELECT * FROM club_event_registrations WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$registrationId]);
        $registration = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$registration || (int)$registration['account_id'] !== $accountId) {
            throw new ClubEventRegistrationException('Přihláška nebyla nalezena.');
        }
        if ($registration['status'] === 'cancelled') {
            $pdo->commit();
            return ['id' => $registrationId, 'status' => 'cancelled', 'changed' => false];
        }
        if ($registration['status'] !== 'confirmed') {
            throw new ClubEventRegistrationException('Přihlášku v tomto stavu nelze zrušit.');
        }
        $pdo->prepare(
            "UPDATE club_event_registrations SET status='cancelled', cancelled_at=CURRENT_TIMESTAMP, "
            . 'cancellation_note=?, updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$note, $registrationId]);
        clubEventRegistrationAudit(
            $pdo,
            $registrationId,
            'account',
            $accountId,
            'cancel',
            'confirmed',
            'cancelled',
            $note
        );
        $pdo->commit();
        return ['id' => $registrationId, 'status' => 'cancelled', 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Přihlášku se nepodařilo bezpečně zrušit.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function clubEventOpenFreeList(PDO $pdo): array
{
    $events = $pdo->query(
        "SELECT e.* FROM club_events e WHERE e.status='open' AND e.event_type='club_event' "
        . "AND e.pricing_policy='free' AND (e.registration_starts_at IS NULL OR e.registration_starts_at<=CURRENT_TIMESTAMP) "
        . "AND (e.registration_ends_at IS NULL OR e.registration_ends_at>=CURRENT_TIMESTAMP) "
        . 'ORDER BY e.registration_ends_at IS NULL, e.registration_ends_at, e.name'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as &$event) {
        $event['sessions'] = clubEventSessions($pdo, (int)$event['id']);
        $event['effective_capacity'] = clubEventEffectiveCapacity($pdo, (int)$event['id'], (int)$event['capacity']);
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='confirmed'"
        );
        $count->execute([(int)$event['id']]);
        $event['registration_count'] = (int)$count->fetchColumn();
        $event['remaining_capacity'] = max(0, (int)$event['effective_capacity'] - (int)$event['registration_count']);
    }
    unset($event);
    return $events;
}

/** @return list<array<string,mixed>> */
function clubEventMyRegistrations(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT r.*, e.name AS event_name, e.code AS event_code, s.jmeno, s.prijmeni '
        . 'FROM club_event_registrations r JOIN club_events e ON e.id=r.event_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id WHERE r.account_id=? '
        . 'ORDER BY r.registered_at DESC, r.id DESC'
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function clubEventSessions(PDO $pdo, int $eventId): array
{
    $statement = $pdo->prepare(
        "SELECT * FROM club_event_sessions WHERE event_id=? AND status='scheduled' ORDER BY starts_at,id"
    );
    $statement->execute([$eventId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function clubEventEffectiveCapacity(PDO $pdo, int $eventId, int $eventCapacity): int
{
    $statement = $pdo->prepare(
        "SELECT MIN(capacity_override) FROM club_event_sessions "
        . "WHERE event_id=? AND status='scheduled' AND capacity_override IS NOT NULL"
    );
    $statement->execute([$eventId]);
    $sessionCapacity = $statement->fetchColumn();
    return $sessionCapacity === false || $sessionCapacity === null
        ? $eventCapacity
        : min($eventCapacity, (int)$sessionCapacity);
}

/** @return array<string,mixed>|false */
function clubEventEligibleRelation(PDO $pdo, int $accountId, int $sportovecId): array|false
{
    $statement = $pdo->prepare(
        'SELECT r.relation_role,s.narozeni FROM account_person_roles r '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . "WHERE r.account_id=? AND r.sportovec_id=? AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . 'AND vu.aktivni=1 AND vu.email_overeno=1'
    );
    $statement->execute([$accountId, $sportovecId]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

/** @param array<string,mixed> $event */
function clubEventAssertRegistrationWindow(array $event): void
{
    $now = new DateTimeImmutable('now');
    if ($event['registration_starts_at'] !== null
        && $now < new DateTimeImmutable((string)$event['registration_starts_at'])
    ) {
        throw new ClubEventRegistrationException('Přihlašování ještě nebylo zahájeno.');
    }
    if ($event['registration_ends_at'] !== null
        && $now > new DateTimeImmutable((string)$event['registration_ends_at'])
    ) {
        throw new ClubEventRegistrationException('Přihlašování již skončilo.');
    }
}

/** @param array<string,mixed> $relation @param array<string,mixed> $event */
function clubEventAssertAge(PDO $pdo, int $eventId, array $relation, array $event): void
{
    if ($event['min_age'] === null && $event['max_age'] === null) {
        return;
    }
    if (empty($relation['narozeni'])) {
        throw new ClubEventRegistrationException('U osoby chybí datum narození potřebné pro věkovou kontrolu.');
    }
    $session = $pdo->prepare(
        "SELECT starts_at FROM club_event_sessions WHERE event_id=? AND status='scheduled' ORDER BY starts_at,id LIMIT 1"
    );
    $session->execute([$eventId]);
    $startsAt = $session->fetchColumn();
    if (!$startsAt) {
        throw new ClubEventRegistrationException('Kroužek nemá platný termín.');
    }
    $birth = new DateTimeImmutable((string)$relation['narozeni']);
    $firstSession = new DateTimeImmutable((string)$startsAt);
    $age = $birth->diff($firstSession)->y;
    if ($birth > $firstSession
        || ($event['min_age'] !== null && $age < (int)$event['min_age'])
        || ($event['max_age'] !== null && $age > (int)$event['max_age'])
    ) {
        throw new ClubEventRegistrationException('Osoba nesplňuje věkové omezení kroužku.');
    }
}

function clubEventRegistrationAudit(
    PDO $pdo,
    int $registrationId,
    string $actorType,
    ?int $actorId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    string $note
): void {
    $statement = $pdo->prepare(
        'INSERT INTO club_event_registration_events '
        . '(registration_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)'
    );
    $statement->execute([$registrationId, $actorType, $actorId, $action, $fromStatus, $toStatus, $note]);
}
