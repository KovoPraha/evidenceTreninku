<?php
declare(strict_types=1);

require_once __DIR__ . '/club_event.php';
require_once __DIR__ . '/account_person_role.php';
require_once __DIR__ . '/club_event_notification.php';

final class ClubEventRegistrationException extends RuntimeException
{
}

/** @return array{id:int,terms_version:string,changed:bool} */
function clubEventConfigureRegistrationTerms(
    PDO $pdo,
    int $eventId,
    int $actorTrainerId,
    string $termsVersion,
    string $consentText,
    string $cancellationPolicy,
    string $cancellationDeadline,
    bool $confirmed
): array {
    $termsVersion = trim($termsVersion);
    $consentText = trim($consentText);
    $cancellationPolicy = trim($cancellationPolicy);
    if ($eventId < 1 || $actorTrainerId < 1 || !$confirmed
        || preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $termsVersion) !== 1
        || $consentText === '' || $cancellationPolicy === ''
    ) {
        throw new InvalidArgumentException(
            'Podmínky vyžadují akci, administrátora, verzi, oba texty a výslovné potvrzení.'
        );
    }
    if (mb_strlen($consentText, 'UTF-8') > 4000 || mb_strlen($cancellationPolicy, 'UTF-8') > 4000) {
        throw new InvalidArgumentException('Text souhlasu a storna smí mít každý nejvýše 4000 znaků.');
    }
    $deadline = clubEventDateTime($cancellationDeadline);
    if (new DateTimeImmutable($deadline) <= new DateTimeImmutable('now')) {
        throw new InvalidArgumentException('Termín bezplatného storna musí být v budoucnosti.');
    }

    $pdo->beginTransaction();
    try {
        $event = clubEventLock($pdo, $eventId);
        if (!$event || $event['status'] !== 'draft' || $event['event_type'] !== 'club_event'
            || $event['pricing_policy'] !== 'free'
        ) {
            throw new ClubEventRegistrationException(
                'Podmínky lze nastavit pouze bezplatnému kroužku ve stavu draft.'
            );
        }
        $session = $pdo->prepare(
            "SELECT starts_at FROM club_event_sessions WHERE event_id=? AND status='scheduled' "
            . 'ORDER BY starts_at,id LIMIT 1'
        );
        $session->execute([$eventId]);
        $firstSession = $session->fetchColumn();
        if (!$firstSession) {
            throw new ClubEventRegistrationException('Před nastavením podmínek přidejte alespoň jeden termín.');
        }
        if (new DateTimeImmutable($deadline) >= new DateTimeImmutable((string)$firstSession)) {
            throw new ClubEventRegistrationException('Termín bezplatného storna musí být před prvním termínem.');
        }

        $versionStatement = $pdo->prepare(
            'SELECT * FROM club_event_term_versions WHERE event_id=? AND terms_version=?'
        );
        $versionStatement->execute([$eventId, $termsVersion]);
        $storedVersion = $versionStatement->fetch(PDO::FETCH_ASSOC);
        if ($storedVersion) {
            if ($storedVersion['consent_text_plain'] !== $consentText
                || $storedVersion['cancellation_policy_plain'] !== $cancellationPolicy
                || $storedVersion['cancellation_deadline_at'] !== $deadline
            ) {
                throw new ClubEventRegistrationException(
                    'Tato verze už označuje jiné neměnné znění. Pro změnu použijte novou verzi.'
                );
            }
        } else {
            $insertVersion = $pdo->prepare(
                'INSERT INTO club_event_term_versions '
                . '(event_id,terms_version,consent_text_plain,cancellation_policy_plain, '
                . 'cancellation_deadline_at,actor_trainer_id) VALUES (?,?,?,?,?,?)'
            );
            $insertVersion->execute([
                $eventId, $termsVersion, $consentText, $cancellationPolicy, $deadline, $actorTrainerId,
            ]);
        }

        $changed = $event['terms_version'] !== $termsVersion
            || $event['consent_text_plain'] !== $consentText
            || $event['cancellation_policy_plain'] !== $cancellationPolicy
            || $event['cancellation_deadline_at'] !== $deadline;
        if (!$changed) {
            $pdo->commit();
            return ['id' => $eventId, 'terms_version' => $termsVersion, 'changed' => false];
        }
        $update = $pdo->prepare(
            'UPDATE club_events SET terms_version=?,consent_text_plain=?,cancellation_policy_plain=?, '
            . 'cancellation_deadline_at=?,terms_configured_at=CURRENT_TIMESTAMP, '
            . 'terms_configured_by_trainer_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([
            $termsVersion, $consentText, $cancellationPolicy, $deadline, $actorTrainerId, $eventId,
        ]);
        clubEventAudit($pdo, $eventId, $actorTrainerId, 'configure_terms', 'event', $eventId,
            'Nastaveny podmínky registrace a storna.', [
                'terms_version' => $termsVersion,
                'cancellation_deadline_at' => $deadline,
                'consent_sha256' => hash('sha256', $consentText),
                'cancellation_sha256' => hash('sha256', $cancellationPolicy),
            ]);
        $pdo->commit();
        return ['id' => $eventId, 'terms_version' => $termsVersion, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Podmínky se nepodařilo uložit bez částečného zápisu.', 0, $exception);
    }
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
        if (empty($event['terms_version']) || empty($event['consent_text_plain'])
            || empty($event['cancellation_policy_plain']) || empty($event['cancellation_deadline_at'])
        ) {
            throw new ClubEventRegistrationException('Před otevřením nastavte verzi souhlasu a storno podmínky.');
        }
        $sessions = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_sessions WHERE event_id=? AND status='scheduled'"
        );
        $sessions->execute([$eventId]);
        if ((int)$sessions->fetchColumn() < 1) {
            throw new ClubEventRegistrationException('Před otevřením přidejte alespoň jeden termín.');
        }
        $firstSessionStatement = $pdo->prepare(
            "SELECT MIN(starts_at) FROM club_event_sessions WHERE event_id=? AND status='scheduled'"
        );
        $firstSessionStatement->execute([$eventId]);
        $firstSession = $firstSessionStatement->fetchColumn();
        if (!$firstSession || new DateTimeImmutable((string)$event['cancellation_deadline_at'])
            >= new DateTimeImmutable((string)$firstSession)
        ) {
            throw new ClubEventRegistrationException(
                'Termín bezplatného storna musí zůstat před prvním termínem kroužku.'
            );
        }
        if (new DateTimeImmutable((string)$event['cancellation_deadline_at']) <= new DateTimeImmutable('now')) {
            throw new ClubEventRegistrationException('Kroužek nelze otevřít po termínu bezplatného storna.');
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
function clubEventRegisterParticipant(
    PDO $pdo,
    int $eventId,
    int $accountId,
    int $sportovecId,
    string $consentVersion,
    bool $consented
): array
{
    $consentVersion = trim($consentVersion);
    if ($eventId < 1 || $accountId < 1 || $sportovecId < 1 || !$consented || $consentVersion === '') {
        throw new InvalidArgumentException(
            'Přihlášení vyžaduje kroužek, účet, schválenou osobu a potvrzený aktuální souhlas.'
        );
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
        if (empty($event['terms_version']) || !hash_equals((string)$event['terms_version'], $consentVersion)
            || empty($event['consent_text_plain']) || empty($event['cancellation_policy_plain'])
            || empty($event['cancellation_deadline_at'])
        ) {
            throw new ClubEventRegistrationException(
                'Podmínky kroužku se změnily. Obnovte stránku a potvrďte aktuální znění.'
            );
        }

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
        if ($existing && in_array($existing['status'], ['confirmed', 'waitlisted'], true)) {
            $pdo->commit();
            return ['id' => (int)$existing['id'], 'status' => (string)$existing['status'], 'created' => false];
        }

        $capacity = clubEventEffectiveCapacity($pdo, $eventId, (int)$event['capacity']);
        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='confirmed'"
        );
        $count->execute([$eventId]);
        $targetStatus = (int)$count->fetchColumn() < $capacity ? 'confirmed' : 'waitlisted';

        if ($existing) {
            $update = $pdo->prepare(
                'UPDATE club_event_registrations SET account_id=?, relation_role_snapshot=?, status=?, '
                . 'registered_at=CURRENT_TIMESTAMP, cancelled_at=NULL, cancellation_note=NULL, '
                . 'consent_version_snapshot=?,consent_text_snapshot=?,consented_at=CURRENT_TIMESTAMP, '
                . 'cancellation_policy_snapshot=?,cancellation_deadline_snapshot=?, '
                . "waitlisted_at=CASE WHEN ?='waitlisted' THEN CURRENT_TIMESTAMP ELSE NULL END,promoted_at=NULL, "
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([
                $accountId, $relation['relation_role'], $targetStatus,
                $event['terms_version'], $event['consent_text_plain'],
                $event['cancellation_policy_plain'], $event['cancellation_deadline_at'],
                $targetStatus, (int)$existing['id'],
            ]);
            $registrationId = (int)$existing['id'];
            $action = $targetStatus === 'confirmed' ? 'reactivate' : 'reactivate_waitlist';
            $fromStatus = (string)$existing['status'];
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO club_event_registrations '
                . '(event_id,account_id,sportovec_id,relation_role_snapshot,status,registered_at, '
                . 'consent_version_snapshot,consent_text_snapshot,consented_at, '
                . 'cancellation_policy_snapshot,cancellation_deadline_snapshot,waitlisted_at) '
                . "VALUES (?,?,?,?,?,CURRENT_TIMESTAMP,?,?,CURRENT_TIMESTAMP,?,?,"
                . "CASE WHEN ?='waitlisted' THEN CURRENT_TIMESTAMP ELSE NULL END)"
            );
            $insert->execute([
                $eventId, $accountId, $sportovecId, $relation['relation_role'], $targetStatus,
                $event['terms_version'], $event['consent_text_plain'],
                $event['cancellation_policy_plain'], $event['cancellation_deadline_at'], $targetStatus,
            ]);
            $registrationId = (int)$pdo->lastInsertId();
            $action = $targetStatus === 'confirmed' ? 'register' : 'waitlist';
            $fromStatus = null;
        }
        clubEventRegistrationAudit(
            $pdo,
            $registrationId,
            'account',
            $accountId,
            $action,
            $fromStatus,
            $targetStatus,
            $targetStatus === 'confirmed'
                ? 'Bezplatná přihláška schválené osoby z K2.'
                : 'Kapacita je plná; schválená osoba z K2 byla zařazena na čekací listinu.'
        );
        $pdo->commit();
        return ['id' => $registrationId, 'status' => $targetStatus, 'created' => true];
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

/** @return array{id:int,status:string,changed:bool,promoted_registration_id:?int} */
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
            return ['id' => $registrationId, 'status' => 'cancelled', 'changed' => false, 'promoted_registration_id' => null];
        }
        if (!in_array($registration['status'], ['confirmed', 'waitlisted'], true)) {
            throw new ClubEventRegistrationException('Přihlášku v tomto stavu nelze zrušit.');
        }
        $fromStatus = (string)$registration['status'];
        if ($fromStatus === 'confirmed' && empty($registration['cancellation_deadline_snapshot'])) {
            throw new ClubEventRegistrationException('U přihlášky chybí auditované storno pravidlo.');
        }
        if ($fromStatus === 'confirmed'
            && new DateTimeImmutable('now') > new DateTimeImmutable((string)$registration['cancellation_deadline_snapshot'])
        ) {
            throw new ClubEventRegistrationException(
                'Termín bezplatného storna již uplynul. Kontaktujte administrátora.'
            );
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
            $fromStatus,
            'cancelled',
            $note
        );
        $promotedRegistrationId = $fromStatus === 'confirmed'
            ? clubEventPromoteNextWaitlisted($pdo, $eventId)
            : null;
        $pdo->commit();
        return [
            'id' => $registrationId,
            'status' => 'cancelled',
            'changed' => true,
            'promoted_registration_id' => $promotedRegistrationId,
        ];
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

/** @return array{id:int,status:string,changed:bool,promoted_registration_id:?int} */
function clubEventAdminCancelRegistration(
    PDO $pdo,
    int $registrationId,
    int $actorTrainerId,
    string $note,
    bool $confirmed
): array {
    $note = trim($note);
    if ($registrationId < 1 || $actorTrainerId < 1 || $note === '' || !$confirmed) {
        throw new InvalidArgumentException(
            'Správní storno vyžaduje přihlášku, administrátora, důvod a výslovné potvrzení.'
        );
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT event_id FROM club_event_registrations WHERE id=?');
        $lookup->execute([$registrationId]);
        $eventId = (int)$lookup->fetchColumn();
        if ($eventId < 1 || !clubEventLock($pdo, $eventId)) {
            throw new ClubEventRegistrationException('Přihláška nebyla nalezena.');
        }
        $sql = 'SELECT * FROM club_event_registrations WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$registrationId]);
        $registration = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$registration) {
            throw new ClubEventRegistrationException('Přihláška nebyla nalezena.');
        }
        if ($registration['status'] === 'cancelled') {
            $pdo->commit();
            return [
                'id' => $registrationId,
                'status' => 'cancelled',
                'changed' => false,
                'promoted_registration_id' => null,
            ];
        }
        if (!in_array($registration['status'], ['confirmed', 'waitlisted'], true)) {
            throw new ClubEventRegistrationException('Přihlášku v tomto stavu nelze správně zrušit.');
        }
        $fromStatus = (string)$registration['status'];
        $late = $fromStatus === 'confirmed'
            && !empty($registration['cancellation_deadline_snapshot'])
            && new DateTimeImmutable('now') > new DateTimeImmutable(
                (string)$registration['cancellation_deadline_snapshot']
            );
        $pdo->prepare(
            "UPDATE club_event_registrations SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,"
            . 'cancellation_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([$note, $registrationId]);
        clubEventRegistrationAudit(
            $pdo,
            $registrationId,
            'trainer',
            $actorTrainerId,
            $late ? 'admin_cancel_late' : 'admin_cancel',
            $fromStatus,
            'cancelled',
            $note
        );
        $promotedRegistrationId = $fromStatus === 'confirmed'
            ? clubEventPromoteNextWaitlisted($pdo, $eventId)
            : null;
        $pdo->commit();
        return [
            'id' => $registrationId,
            'status' => 'cancelled',
            'changed' => true,
            'promoted_registration_id' => $promotedRegistrationId,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof ClubEventRegistrationException
        ) {
            throw $exception;
        }
        throw new ClubEventRegistrationException('Správní storno selhalo bez částečného zápisu.', 0, $exception);
    }
}

function clubEventPromoteNextWaitlisted(PDO $pdo, int $eventId): ?int
{
    if (!$pdo->inTransaction() || $eventId < 1) {
        throw new LogicException('Povýšení čekací listiny vyžaduje aktivní transakci a zamčenou akci.');
    }
    $event = clubEventLock($pdo, $eventId);
    if (!$event) {
        throw new ClubEventRegistrationException('Kroužek pro čekací listinu nebyl nalezen.');
    }
    $capacity = clubEventEffectiveCapacity($pdo, $eventId, (int)$event['capacity']);
    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='confirmed'"
    );
    $count->execute([$eventId]);
    if ((int)$count->fetchColumn() >= $capacity) {
        return null;
    }

    while (true) {
        $sql = "SELECT * FROM club_event_registrations WHERE event_id=? AND status='waitlisted' "
            . 'ORDER BY waitlisted_at,id LIMIT 1';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$eventId]);
        $candidate = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$candidate) {
            return null;
        }
        $relation = clubEventEligibleRelation(
            $pdo,
            (int)$candidate['account_id'],
            (int)$candidate['sportovec_id']
        );
        if (!$relation || empty($candidate['consent_version_snapshot'])) {
            $pdo->prepare(
                "UPDATE club_event_registrations SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP, "
                . "cancellation_note='Automaticky vyřazeno: K2 vazba nebo souhlas už není platný.', "
                . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
            )->execute([(int)$candidate['id']]);
            clubEventRegistrationAudit(
                $pdo,
                (int)$candidate['id'],
                'system',
                null,
                'waitlist_ineligible',
                'waitlisted',
                'cancelled',
                'Automaticky vyřazeno při uvolnění místa: K2 vazba nebo souhlas už není platný.'
            );
            continue;
        }
        $pdo->prepare(
            "UPDATE club_event_registrations SET status='confirmed',registered_at=CURRENT_TIMESTAMP, "
            . 'promoted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        )->execute([(int)$candidate['id']]);
        $promotionEventId = clubEventRegistrationAudit(
            $pdo,
            (int)$candidate['id'],
            'system',
            null,
            'promote_waitlist',
            'waitlisted',
            'confirmed',
            'Automaticky povýšeno z čekací listiny po uvolnění kapacity.'
        );
        clubEventNotificationEnqueuePromotion($pdo, (int)$candidate['id'], $promotionEventId);
        return (int)$candidate['id'];
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
        $waitlist = $pdo->prepare(
            "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='waitlisted'"
        );
        $waitlist->execute([(int)$event['id']]);
        $event['waitlist_count'] = (int)$waitlist->fetchColumn();
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
    $registrations = $statement->fetchAll(PDO::FETCH_ASSOC);
    $position = $pdo->prepare(
        "SELECT COUNT(*) FROM club_event_registrations WHERE event_id=? AND status='waitlisted' "
        . 'AND (waitlisted_at<? OR (waitlisted_at=? AND id<=?))'
    );
    foreach ($registrations as &$registration) {
        $registration['waitlist_position'] = null;
        if ($registration['status'] === 'waitlisted' && $registration['waitlisted_at'] !== null) {
            $position->execute([
                (int)$registration['event_id'],
                $registration['waitlisted_at'],
                $registration['waitlisted_at'],
                (int)$registration['id'],
            ]);
            $registration['waitlist_position'] = (int)$position->fetchColumn();
        }
    }
    unset($registration);
    return $registrations;
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
): int {
    $statement = $pdo->prepare(
        'INSERT INTO club_event_registration_events '
        . '(registration_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,?,?,?,?,?,?)'
    );
    $statement->execute([$registrationId, $actorType, $actorId, $action, $fromStatus, $toStatus, $note]);
    return (int)$pdo->lastInsertId();
}
