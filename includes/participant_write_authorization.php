<?php

declare(strict_types=1);

require_once __DIR__ . '/family_portal.php';

/**
 * Call after domain locks and before the first mutation. A locking current read
 * sees revocations committed while waiting, even with an older InnoDB snapshot,
 * and keeps the matching authority stable until the caller commits or rolls back.
 * Read-only family/athlete helpers intentionally retain their non-locking behavior.
 */
function participantWriteCanActFor(PDO $pdo, string $actorType, int $actorId, int $personId): bool
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Participant write authorization requires an active transaction.');
    }
    if ($actorId < 1 || $personId < 1) {
        return false;
    }
    if ($actorType === 'account') {
        if (!familyPortalHasTables($pdo, ['account_person_roles', 'verejni_uzivatele', 'sportovci'])) {
            return false;
        }
        $sql = 'SELECT 1 ' . familyPortalApprovedRelationsSql() . 'AND s.id=? LIMIT 1';
    } elseif ($actorType === 'athlete') {
        if (!familyPortalHasTables($pdo, ['child_access_accounts', 'sportovci'])) {
            return false;
        }
        $sql = 'SELECT 1 FROM child_access_accounts a JOIN sportovci s ON s.id=a.sportovec_id '
            . 'WHERE a.id=? AND a.active=1 AND s.id=? LIMIT 1';
    } else {
        return false;
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$actorId, $personId]);
    return (bool) $statement->fetchColumn();
}
