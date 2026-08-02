<?php
declare(strict_types=1);

final class AccountPersonRoleException extends RuntimeException
{
}

/** @return list<string> */
function accountPersonRelationRoles(): array
{
    return ['self', 'guardian'];
}

/** @return list<array<string,mixed>> */
function accountPersonRoleList(PDO $pdo, int $accountId = 0): array
{
    $sql = 'SELECT r.*, vu.jmeno AS account_jmeno, vu.prijmeni AS account_prijmeni, '
        . 'vu.email AS account_email, vu.aktivni AS account_active, vu.email_overeno, '
        . 's.jmeno AS person_jmeno, s.prijmeni AS person_prijmeni, s.narozeni, '
        . 'creator.jmeno AS creator_name, approver.jmeno AS approver_name '
        . 'FROM account_person_roles r '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . 'LEFT JOIN treneri creator ON creator.id=r.created_by_trainer_id '
        . 'LEFT JOIN treneri approver ON approver.id=r.approved_by_trainer_id';
    $parameters = [];
    if ($accountId > 0) {
        $sql .= ' WHERE r.account_id=?';
        $parameters[] = $accountId;
    }
    $sql .= " ORDER BY CASE r.status WHEN 'approved' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END, "
        . 'vu.prijmeni, vu.jmeno, s.prijmeni, s.jmeno, r.id';
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function accountPersonRoleEvents(PDO $pdo, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    return $pdo->query(
        'SELECT e.*, vu.email AS account_email, s.jmeno AS person_jmeno, '
        . 's.prijmeni AS person_prijmeni, t.jmeno AS actor_name '
        . 'FROM account_person_role_events e '
        . 'JOIN account_person_roles r ON r.id=e.relation_id '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . 'LEFT JOIN treneri t ON t.id=e.actor_trainer_id '
        . 'ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Admin-only decision: create or reactivate an already approved relation.
 * No relation is inferred from matching email, name or KIS data.
 *
 * @return array{relation_id:int,created:bool,status:string,relation_role:string}
 */
function accountPersonRoleApprove(
    PDO $pdo,
    int $accountId,
    int $sportovecId,
    string $role,
    int $actorTrainerId,
    string $note
): array {
    accountPersonRoleValidateDecision($accountId, $sportovecId, $role, $actorTrainerId, $note);

    $pdo->beginTransaction();
    try {
        $account = $pdo->prepare('SELECT id FROM verejni_uzivatele WHERE id=?');
        $account->execute([$accountId]);
        if (!$account->fetchColumn()) {
            throw new AccountPersonRoleException('Veřejný účet nebyl nalezen.');
        }
        $person = $pdo->prepare('SELECT id FROM sportovci WHERE id=?');
        $person->execute([$sportovecId]);
        if (!$person->fetchColumn()) {
            throw new AccountPersonRoleException('Sportovec nebyl nalezen.');
        }

        $sql = 'SELECT * FROM account_person_roles '
            . 'WHERE account_id=? AND sportovec_id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $existing = $pdo->prepare($sql);
        $existing->execute([$accountId, $sportovecId]);
        $relation = $existing->fetch(PDO::FETCH_ASSOC);
        if ($relation && $relation['status'] === 'approved' && $relation['valid_to'] === null) {
            if ($relation['relation_role'] !== $role) {
                throw new AccountPersonRoleException(
                    'Účet už má k této osobě jinou aktivní roli. Nejprve ji zrušte.'
                );
            }
            $pdo->commit();
            return [
                'relation_id' => (int)$relation['id'],
                'created' => false,
                'status' => 'approved',
                'relation_role' => $role,
            ];
        }

        $fromStatus = $relation ? (string)$relation['status'] : null;
        if ($relation) {
            $update = $pdo->prepare(
                "UPDATE account_person_roles SET relation_role=?, status='approved', source='admin', "
                . 'valid_from=CURRENT_TIMESTAMP, valid_to=NULL, approved_by_trainer_id=?, '
                . 'decision_note=?, updated_at=CURRENT_TIMESTAMP WHERE id=?'
            );
            $update->execute([$role, $actorTrainerId, trim($note), (int)$relation['id']]);
            $relationId = (int)$relation['id'];
            $created = false;
            $action = 'reactivate';
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO account_person_roles '
                . '(account_id, sportovec_id, relation_role, status, source, valid_from, '
                . 'created_by_trainer_id, approved_by_trainer_id, decision_note) '
                . "VALUES (?, ?, ?, 'approved', 'admin', CURRENT_TIMESTAMP, ?, ?, ?)"
            );
            $insert->execute([$accountId, $sportovecId, $role, $actorTrainerId, $actorTrainerId, trim($note)]);
            $relationId = (int)$pdo->lastInsertId();
            $created = true;
            $action = 'approve';
        }
        accountPersonRoleEvent(
            $pdo,
            $relationId,
            $actorTrainerId,
            $action,
            $fromStatus,
            'approved',
            $role,
            trim($note)
        );
        $pdo->commit();
        return [
            'relation_id' => $relationId,
            'created' => $created,
            'status' => 'approved',
            'relation_role' => $role,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof AccountPersonRoleException
        ) {
            throw $exception;
        }
        throw new AccountPersonRoleException('Vazbu se nepodařilo bezpečně uložit.', 0, $exception);
    }
}

/** @return array{relation_id:int,changed:bool,status:string} */
function accountPersonRoleRevoke(
    PDO $pdo,
    int $relationId,
    int $actorTrainerId,
    string $note
): array {
    $note = trim($note);
    if ($relationId < 1 || $actorTrainerId < 1 || $note === '') {
        throw new InvalidArgumentException('Zrušení vazby vyžaduje identifikátor a zdůvodnění.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }

    $pdo->beginTransaction();
    try {
        $sql = 'SELECT * FROM account_person_roles WHERE id=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $pdo->prepare($sql);
        $statement->execute([$relationId]);
        $relation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$relation) {
            throw new AccountPersonRoleException('Vazba účtu a osoby nebyla nalezena.');
        }
        if ($relation['status'] === 'revoked') {
            $pdo->commit();
            return ['relation_id' => $relationId, 'changed' => false, 'status' => 'revoked'];
        }
        $update = $pdo->prepare(
            "UPDATE account_person_roles SET status='revoked', valid_to=CURRENT_TIMESTAMP, "
            . 'decision_note=?, approved_by_trainer_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $update->execute([$note, $actorTrainerId, $relationId]);
        accountPersonRoleEvent(
            $pdo,
            $relationId,
            $actorTrainerId,
            'revoke',
            (string)$relation['status'],
            'revoked',
            (string)$relation['relation_role'],
            $note
        );
        $pdo->commit();
        return ['relation_id' => $relationId, 'changed' => true, 'status' => 'revoked'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException
            || $exception instanceof AccountPersonRoleException
        ) {
            throw $exception;
        }
        throw new AccountPersonRoleException('Vazbu se nepodařilo bezpečně zrušit.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function accountPersonEligibleParticipants(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT s.id AS sportovec_id, s.jmeno, s.prijmeni, s.narozeni, r.relation_role '
        . 'FROM account_person_roles r '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . "WHERE r.account_id=? AND r.status='approved' AND r.valid_from<=CURRENT_TIMESTAMP "
        . 'AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . 'AND vu.aktivni=1 AND vu.email_overeno=1 '
        . 'ORDER BY s.prijmeni, s.jmeno, s.id'
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function accountPersonCanManage(PDO $pdo, int $accountId, int $sportovecId): bool
{
    foreach (accountPersonEligibleParticipants($pdo, $accountId) as $participant) {
        if ((int)$participant['sportovec_id'] === $sportovecId) {
            return true;
        }
    }
    return false;
}

function accountPersonRoleValidateDecision(
    int $accountId,
    int $sportovecId,
    string $role,
    int $actorTrainerId,
    string $note
): void {
    if ($accountId < 1 || $sportovecId < 1 || $actorTrainerId < 1) {
        throw new InvalidArgumentException('Chybí účet, sportovec nebo administrátor.');
    }
    if (!in_array($role, accountPersonRelationRoles(), true)) {
        throw new InvalidArgumentException('Neplatný typ vztahu účtu a osoby.');
    }
    $note = trim($note);
    if ($note === '') {
        throw new InvalidArgumentException('Schválení vazby vyžaduje zdůvodnění.');
    }
    if (mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Poznámka smí mít nejvýše 1000 znaků.');
    }
}

function accountPersonRoleEvent(
    PDO $pdo,
    int $relationId,
    int $actorTrainerId,
    string $action,
    ?string $fromStatus,
    string $toStatus,
    string $role,
    string $note
): void {
    $event = $pdo->prepare(
        'INSERT INTO account_person_role_events '
        . '(relation_id, actor_trainer_id, action, from_status, to_status, relation_role, note) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $event->execute([$relationId, $actorTrainerId, $action, $fromStatus, $toStatus, $role, $note]);
}
