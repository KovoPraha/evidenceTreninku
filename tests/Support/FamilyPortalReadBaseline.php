<?php

declare(strict_types=1);

// Frozen from 54013cdd:includes/family_portal.php; synthetic characterization only.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function familyReadBaselineAuthorizedPeople(PDO $pdo, int $accountId): array
{
    if ($accountId < 1
        || !familyPortalTableExists($pdo, 'account_person_roles')
        || !familyPortalTableExists($pdo, 'verejni_uzivatele')
        || !familyPortalTableExists($pdo, 'sportovci')
    ) {
        return [];
    }

    $statement = $pdo->prepare(
        'SELECT s.id AS sportovec_id, s.jmeno, s.prijmeni, s.narozeni, '
        . 's.stav_clenstvi, r.relation_role '
        . 'FROM account_person_roles r '
        . 'JOIN verejni_uzivatele vu ON vu.id=r.account_id '
        . 'JOIN sportovci s ON s.id=r.sportovec_id '
        . "WHERE r.account_id=? AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP '
        . 'AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . 'AND vu.aktivni=1 AND vu.email_overeno=1 '
        . "AND r.relation_role IN ('self','guardian') "
        . 'ORDER BY s.prijmeni, s.jmeno, s.id, r.relation_role'
    );
    $statement->execute([$accountId]);

    $people = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $personId = (int)$row['sportovec_id'];
        $role = (string)$row['relation_role'];
        if (!isset($people[$personId])) {
            $row = array_replace($row, sportovecMembershipFields($pdo, $personId));
            $row['sportovec_id'] = $personId;
            $row['relation_roles'] = [];
            unset($row['relation_role']);
            $people[$personId] = $row;
        }
        if (!in_array($role, $people[$personId]['relation_roles'], true)) {
            $people[$personId]['relation_roles'][] = $role;
        }
    }

    return array_values($people);
}

function familyReadBaselineCanViewPerson(PDO $pdo, int $accountId, int $sportovecId): bool
{
    if ($sportovecId < 1) {
        return false;
    }
    foreach (familyReadBaselineAuthorizedPeople($pdo, $accountId) as $person) {
        if ((int)$person['sportovec_id'] === $sportovecId) {
            return true;
        }
    }
    return false;
}
