<?php
declare(strict_types=1);

function memberChargeReadTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) return false;
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    if ($driver === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    return false;
}

/** @return list<array<string,mixed>> */
function memberChargeRowsForSportovec(PDO $pdo, int $sportovecId): array
{
    if ($sportovecId < 1
        || !memberChargeReadTableExists($pdo, 'club_member_charges')
        || !memberChargeReadTableExists($pdo, 'payments')
    ) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT c.id,c.public_code,c.charge_type,c.title_snapshot,c.period_from,c.period_to,'
        . 'c.amount_minor,c.currency,c.due_on,c.status,c.source_system,c.created_at,'
        . 'p.status AS payment_status,p.method AS payment_method,p.variable_symbol,p.paid_at '
        . 'FROM club_member_charges c '
        . "LEFT JOIN payments p ON p.id=(SELECT MAX(p2.id) FROM payments p2 WHERE p2.payable_type='member_charge' AND p2.payable_id=c.id) "
        . 'WHERE c.sportovec_id=? ORDER BY COALESCE(c.due_on,c.created_at) DESC,c.id DESC'
    );
    $statement->execute([$sportovecId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return list<array<string,mixed>> */
function memberChargeAdminRows(PDO $pdo, string $query = '', string $status = '', int $limit = 200): array
{
    if (!memberChargeReadTableExists($pdo, 'club_member_charges')
        || !memberChargeReadTableExists($pdo, 'payments')
        || !memberChargeReadTableExists($pdo, 'sportovci')
    ) {
        return [];
    }
    $query = mb_substr(trim($query), 0, 100, 'UTF-8');
    $status = in_array($status, ['pending', 'paid', 'cancelled'], true) ? $status : '';
    $limit = max(1, min(500, $limit));
    $where = [];
    $parameters = [];
    if ($status !== '') {
        $where[] = 'c.status=?';
        $parameters[] = $status;
    }
    if ($query !== '') {
        $where[] = '(c.public_code LIKE ? OR c.title_snapshot LIKE ? OR s.jmeno LIKE ? OR s.prijmeni LIKE ?)';
        foreach (range(1, 4) as $_) $parameters[] = '%' . $query . '%';
    }
    $sql = 'SELECT c.*,s.jmeno,s.prijmeni,s.narozeni,'
        . 'p.status AS payment_status,p.method AS payment_method,p.variable_symbol,p.paid_at '
        . 'FROM club_member_charges c JOIN sportovci s ON s.id=c.sportovec_id '
        . "LEFT JOIN payments p ON p.id=(SELECT MAX(p2.id) FROM payments p2 WHERE p2.payable_type='member_charge' AND p2.payable_id=c.id) ";
    if ($where !== []) $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
    $sql .= 'ORDER BY CASE c.status WHEN \'pending\' THEN 0 WHEN \'paid\' THEN 1 ELSE 2 END,'
        . 'COALESCE(c.due_on,c.created_at) DESC,c.id DESC LIMIT ' . $limit;
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
