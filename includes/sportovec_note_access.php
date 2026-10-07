<?php
declare(strict_types=1);

/**
 * Poznámku sportovce smí měnit jen přihlášený sportovní účet dané osoby
 * nebo aktivní veřejný účet s platnou rolí self/guardian. Veřejný profilový
 * hash zůstává pouze oprávněním ke čtení.
 */
function sportovecNoteCanWrite(PDO $pdo, int $sportovecId): bool
{
    if ($sportovecId < 1) {
        return false;
    }

    $childAccessId = filter_var($_SESSION['sportovec_pristup_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($childAccessId !== false) {
        $statement = $pdo->prepare(
            'SELECT 1 FROM child_access_accounts '
            . 'WHERE id=? AND sportovec_id=? AND active=1 LIMIT 1'
        );
        $statement->execute([(int)$childAccessId, $sportovecId]);
        if ($statement->fetchColumn()) {
            return true;
        }
    }

    $accountId = filter_var($_SESSION['verejny_uzivatel_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($accountId === false) {
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT 1 FROM account_person_roles '
        . "WHERE account_id=? AND sportovec_id=? AND relation_role IN ('self','guardian') "
        . "AND status='approved' AND valid_from<=CURRENT_TIMESTAMP "
        . 'AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP) LIMIT 1'
    );
    $statement->execute([(int)$accountId, $sportovecId]);
    return (bool)$statement->fetchColumn();
}
