<?php
declare(strict_types=1);

require_once __DIR__ . '/account_person_role.php';

final class AccountPersonClaimException extends RuntimeException
{
}

/** @return array{id:int,status:string,created:bool} */
function accountPersonClaimSubmit(
    PDO $pdo,
    int $accountId,
    string $role,
    string $jmeno,
    string $prijmeni,
    string $narozeni,
    string $message
): array {
    [$jmeno, $prijmeni, $narozeni, $message] = accountPersonClaimValidateInput(
        $accountId,
        $role,
        $jmeno,
        $prijmeni,
        $narozeni,
        $message
    );
    $fingerprint = accountPersonClaimFingerprint($role, $jmeno, $prijmeni, $narozeni);

    $pdo->beginTransaction();
    try {
        $accountSql = 'SELECT id, aktivni, email_overeno FROM verejni_uzivatele WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $accountSql .= ' FOR UPDATE';
        }
        $account = $pdo->prepare($accountSql);
        $account->execute([$accountId]);
        $account = $account->fetch(PDO::FETCH_ASSOC);
        if (!$account || !(int)$account['aktivni'] || !(int)$account['email_overeno']) {
            throw new AccountPersonClaimException('Žádost může odeslat pouze aktivní účet s ověřeným e-mailem.');
        }

        $existing = $pdo->prepare(
            "SELECT id FROM account_person_claim_requests "
            . "WHERE account_id=? AND status='pending' AND active_fingerprint=?"
        );
        $existing->execute([$accountId, $fingerprint]);
        $existingId = $existing->fetchColumn();
        if ($existingId !== false) {
            $pdo->commit();
            return ['id' => (int)$existingId, 'status' => 'pending', 'created' => false];
        }

        $pendingCount = $pdo->prepare(
            "SELECT COUNT(*) FROM account_person_claim_requests WHERE account_id=? AND status='pending'"
        );
        $pendingCount->execute([$accountId]);
        if ((int)$pendingCount->fetchColumn() >= 5) {
            throw new AccountPersonClaimException('Máte již pět nevyřízených žádostí. Vyčkejte na kontrolu administrátorem.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO account_person_claim_requests '
            . '(account_id, requested_role, claimed_jmeno, claimed_prijmeni, claimed_narozeni, '
            . 'requester_message, status, active_fingerprint) '
            . "VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)"
        );
        $insert->execute([$accountId, $role, $jmeno, $prijmeni, $narozeni, $message, $fingerprint]);
        $requestId = (int)$pdo->lastInsertId();
        accountPersonClaimEvent($pdo, $requestId, 'account', $accountId, 'submit', null, 'pending', $message);
        $pdo->commit();
        return ['id' => $requestId, 'status' => 'pending', 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof AccountPersonClaimException) {
            throw $exception;
        }
        throw new AccountPersonClaimException('Žádost se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function accountPersonClaimListForAccount(PDO $pdo, int $accountId): array
{
    $statement = $pdo->prepare(
        'SELECT c.*, s.jmeno AS matched_jmeno, s.prijmeni AS matched_prijmeni '
        . 'FROM account_person_claim_requests c '
        . 'LEFT JOIN sportovci s ON s.id=c.matched_sportovec_id '
        . 'WHERE c.account_id=? ORDER BY c.created_at DESC, c.id DESC'
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function accountPersonClaimListForAdmin(PDO $pdo, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    return $pdo->query(
        'SELECT c.*, vu.jmeno AS account_jmeno, vu.prijmeni AS account_prijmeni, vu.email AS account_email, '
        . 's.jmeno AS matched_jmeno, s.prijmeni AS matched_prijmeni, t.jmeno AS decider_name '
        . 'FROM account_person_claim_requests c '
        . 'JOIN verejni_uzivatele vu ON vu.id=c.account_id '
        . 'LEFT JOIN sportovci s ON s.id=c.matched_sportovec_id '
        . 'LEFT JOIN treneri t ON t.id=c.decided_by_trainer_id '
        . "ORDER BY CASE c.status WHEN 'pending' THEN 0 ELSE 1 END, c.created_at, c.id LIMIT " . $limit
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array{id:int,status:string,changed:bool} */
function accountPersonClaimCancel(PDO $pdo, int $requestId, int $accountId): array
{
    return accountPersonClaimTransition($pdo, $requestId, 'account', $accountId, 'cancelled', 'Zrušeno žadatelem.');
}

/** @return array{id:int,status:string,changed:bool} */
function accountPersonClaimReject(PDO $pdo, int $requestId, int $trainerId, string $note): array
{
    return accountPersonClaimTransition($pdo, $requestId, 'trainer', $trainerId, 'rejected', $note);
}

/** @return array{id:int,status:string,changed:bool,relation_id:int} */
function accountPersonClaimApprove(
    PDO $pdo,
    int $requestId,
    int $sportovecId,
    int $trainerId,
    string $note
): array {
    $note = accountPersonClaimValidateDecision($requestId, $trainerId, $note);
    if ($sportovecId < 1) {
        throw new InvalidArgumentException('Vyberte sportovce, kterého jste skutečně ověřili.');
    }

    $pdo->beginTransaction();
    try {
        $claim = accountPersonClaimLock($pdo, $requestId);
        if (!$claim) {
            throw new AccountPersonClaimException('Žádost nebyla nalezena.');
        }
        if ($claim['status'] !== 'pending') {
            throw new AccountPersonClaimException('Vyřídit lze pouze čekající žádost.');
        }
        $relation = accountPersonRoleApprove(
            $pdo,
            (int)$claim['account_id'],
            $sportovecId,
            (string)$claim['requested_role'],
            $trainerId,
            'Schváleno ze žádosti #' . $requestId . ': ' . $note
        );
        $update = $pdo->prepare(
            "UPDATE account_person_claim_requests SET status='approved', active_fingerprint=NULL, "
            . 'matched_sportovec_id=?, decided_by_trainer_id=?, decision_note=?, '
            . 'decided_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([$sportovecId, $trainerId, $note, $requestId]);
        accountPersonClaimEvent($pdo, $requestId, 'trainer', $trainerId, 'approve', 'pending', 'approved', $note);
        $pdo->commit();
        return [
            'id' => $requestId,
            'status' => 'approved',
            'changed' => true,
            'relation_id' => $relation['relation_id'],
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof AccountPersonClaimException
            || $exception instanceof AccountPersonRoleException
        ) {
            throw $exception;
        }
        throw new AccountPersonClaimException('Žádost se nepodařilo bezpečně schválit.', 0, $exception);
    }
}

/** @return array{id:int,status:string,changed:bool} */
function accountPersonClaimTransition(
    PDO $pdo,
    int $requestId,
    string $actorType,
    int $actorId,
    string $toStatus,
    string $note
): array {
    $note = accountPersonClaimValidateDecision($requestId, $actorId, $note);
    if (!in_array($actorType, ['account', 'trainer'], true)
        || !in_array($toStatus, ['cancelled', 'rejected'], true)
    ) {
        throw new InvalidArgumentException('Neplatný přechod žádosti.');
    }
    $pdo->beginTransaction();
    try {
        $claim = accountPersonClaimLock($pdo, $requestId);
        if (!$claim) {
            throw new AccountPersonClaimException('Žádost nebyla nalezena.');
        }
        if ($actorType === 'account' && (int)$claim['account_id'] !== $actorId) {
            throw new AccountPersonClaimException('Tuto žádost nemůžete změnit.');
        }
        if ($claim['status'] === $toStatus) {
            $pdo->commit();
            return ['id' => $requestId, 'status' => $toStatus, 'changed' => false];
        }
        if ($claim['status'] !== 'pending') {
            throw new AccountPersonClaimException('Vyřídit lze pouze čekající žádost.');
        }
        $update = $pdo->prepare(
            'UPDATE account_person_claim_requests SET status=?, active_fingerprint=NULL, '
            . 'decided_by_trainer_id=?, decision_note=?, decided_at=CURRENT_TIMESTAMP, '
            . 'updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $decider = $actorType === 'trainer' ? $actorId : null;
        $update->execute([$toStatus, $decider, $note, $requestId]);
        accountPersonClaimEvent($pdo, $requestId, $actorType, $actorId, $toStatus, 'pending', $toStatus, $note);
        $pdo->commit();
        return ['id' => $requestId, 'status' => $toStatus, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof AccountPersonClaimException) {
            throw $exception;
        }
        throw new AccountPersonClaimException('Žádost se nepodařilo bezpečně změnit.', 0, $exception);
    }
}

/** @return array<string,mixed>|false */
function accountPersonClaimLock(PDO $pdo, int $requestId): array|false
{
    $sql = 'SELECT * FROM account_person_claim_requests WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$requestId]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

/** @return array{string,string,string,string} */
function accountPersonClaimValidateInput(
    int $accountId,
    string $role,
    string $jmeno,
    string $prijmeni,
    string $narozeni,
    string $message
): array {
    $jmeno = trim($jmeno);
    $prijmeni = trim($prijmeni);
    $narozeni = trim($narozeni);
    $message = trim($message);
    if ($accountId < 1 || !in_array($role, accountPersonRelationRoles(), true)) {
        throw new InvalidArgumentException('Neplatný účet nebo typ vztahu.');
    }
    if ($jmeno === '' || $prijmeni === '' || mb_strlen($jmeno, 'UTF-8') > 100 || mb_strlen($prijmeni, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Zadejte platné jméno a příjmení osoby.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $narozeni);
    if (!$date || $date->format('Y-m-d') !== $narozeni || $date > new DateTimeImmutable('today')) {
        throw new InvalidArgumentException('Zadejte platné datum narození, které není v budoucnosti.');
    }
    if (mb_strlen($message, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    return [$jmeno, $prijmeni, $narozeni, $message];
}

function accountPersonClaimValidateDecision(int $requestId, int $actorId, string $note): string
{
    $note = trim($note);
    if ($requestId < 1 || $actorId < 1 || $note === '') {
        throw new InvalidArgumentException('Rozhodnutí vyžaduje žádost, oprávněnou osobu a zdůvodnění.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
    return $note;
}

function accountPersonClaimFingerprint(string $role, string $jmeno, string $prijmeni, string $narozeni): string
{
    $normalize = static fn (string $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
    return hash('sha256', implode('|', [$role, $normalize($jmeno), $normalize($prijmeni), $narozeni]));
}

function accountPersonClaimEvent(
    PDO $pdo,
    int $requestId,
    string $actorType,
    int $actorId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    string $note
): void {
    $statement = $pdo->prepare(
        'INSERT INTO account_person_claim_events '
        . '(request_id, actor_type, actor_id, action, from_status, to_status, note) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$requestId, $actorType, $actorId, $action, $fromStatus, $toStatus, $note]);
}
