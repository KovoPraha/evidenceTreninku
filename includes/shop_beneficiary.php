<?php
declare(strict_types=1);

function shopBeneficiaryColumnExists(PDO $pdo, string $table): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=\'beneficiary_sportovec_id\'');
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === 'beneficiary_sportovec_id') return true;
    }
    return false;
}

/** @return array<string,mixed>|false */
function shopBeneficiaryActiveRelation(PDO $pdo, int $accountId, int $sportovecId, bool $lock = false): array|false
{
    if ($accountId < 1 || $sportovecId < 1) return false;
    $sql = 'SELECT id,account_id,sportovec_id,relation_role,status,valid_from,valid_to '
        . 'FROM account_person_roles WHERE account_id=? AND sportovec_id=? '
        . "AND relation_role IN ('self','guardian') AND status='approved' "
        . 'AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$accountId, $sportovecId]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function shopBeneficiaryAssertAccessible(PDO $pdo, int $accountId, int $sportovecId, bool $lock = false): void
{
    if (!shopBeneficiaryActiveRelation($pdo, $accountId, $sportovecId, $lock)) {
        throw new ShopCheckoutException('Vybraný příjemce není propojen s účtem aktivní schválenou vazbou.');
    }
}

function shopCartSetBeneficiary(PDO $pdo, int $accountId, int $cartItemId, ?int $sportovecId): void
{
    if ($accountId < 1 || $cartItemId < 1 || ($sportovecId !== null && $sportovecId < 1)) {
        throw new InvalidArgumentException('Změna příjemce vyžaduje účet, položku košíku a platného příjemce nebo NULL.');
    }
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT ci.id,ci.cart_id FROM shop_cart_items ci JOIN shop_carts c ON c.id=ci.cart_id '
            . "WHERE ci.id=? AND c.active_account_id=? AND c.status='active'";
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$cartItemId, $accountId]);
        if (!$statement->fetch(PDO::FETCH_ASSOC)) throw new ShopCheckoutException('Položka aktivního košíku nebyla nalezena.');
        if ($sportovecId !== null) shopBeneficiaryAssertAccessible($pdo, $accountId, $sportovecId, true);
        $pdo->prepare('UPDATE shop_cart_items SET beneficiary_sportovec_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$sportovecId, $cartItemId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCheckoutException) throw $exception;
        throw new ShopCheckoutException('Příjemce košíku se nepodařilo změnit bez částečného zápisu.', 0, $exception);
    }
}

/**
 * Returns immutable order-item snapshots currently visible through an active self/guardian relation.
 * Purchaser-owned goods without a beneficiary intentionally do not belong to this family query.
 *
 * @return list<array<string,mixed>>
 */
function shopBeneficiaryOrderItemsForAccount(PDO $pdo, int $accountId, int $limit = 200): array
{
    if ($accountId < 1) throw new InvalidArgumentException('Rodinný finanční přehled vyžaduje přihlášený účet.');
    $limit = max(1, min(500, $limit));
    $statement = $pdo->prepare(
        'SELECT oi.*,o.public_code,o.account_id AS purchaser_account_id,o.status AS order_status,'
        . 'o.payment_status,o.placed_at,s.jmeno AS beneficiary_first_name,s.prijmeni AS beneficiary_last_name,'
        . 'r.relation_role AS viewer_relation_role '
        . 'FROM account_person_roles r '
        . 'JOIN shop_order_items oi ON oi.beneficiary_sportovec_id=r.sportovec_id '
        . 'JOIN shop_orders o ON o.id=oi.order_id JOIN sportovci s ON s.id=oi.beneficiary_sportovec_id '
        . "WHERE r.account_id=? AND r.relation_role IN ('self','guardian') AND r.status='approved' "
        . 'AND r.valid_from<=CURRENT_TIMESTAMP AND (r.valid_to IS NULL OR r.valid_to>CURRENT_TIMESTAMP) '
        . 'ORDER BY o.created_at DESC,o.id DESC,oi.id ASC LIMIT ' . $limit
    );
    $statement->execute([$accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}
