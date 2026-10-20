<?php
declare(strict_types=1);

/**
 * Settle only orders exclusively belonging to this registration. A mixed order must
 * be reviewed by an administrator rather than cancelling unrelated purchases.
 * Caller owns the transaction and has locked the registration request.
 *
 * @param array<string,mixed> $claim
 * @return list<array<string,mixed>>
 */
function athleteRegistrationSettleOrdersInTransaction(
    PDO $pdo,
    array $claim,
    string $actorType,
    int $actorId,
    string $reason
): array {
    if (!$pdo->inTransaction() || !in_array($actorType, ['account', 'trainer'], true) || $actorId < 1) {
        throw new InvalidArgumentException('Vypořádání přihlášky vyžaduje transakci a auditora.');
    }
    if ($actorType === 'account' && (int)$claim['account_id'] !== $actorId) {
        throw new AthleteRegistrationException('Tuto žádost nemůžete změnit.');
    }
    require_once __DIR__ . '/shop_checkout.php';
    if (!shopCheckoutColumnExists($pdo, 'shop_order_items', 'athlete_registration_request_id')) return [];

    $statement = $pdo->prepare(
        'SELECT DISTINCT order_id FROM shop_order_items WHERE athlete_registration_request_id=? ORDER BY order_id'
    );
    $statement->execute([(int)$claim['id']]);
    $orderIds = $statement->fetchAll(PDO::FETCH_COLUMN);
    $results = [];
    $lock = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    foreach ($orderIds as $orderId) {
        // Keep the canonical payment -> order -> items locking order.
        $payment = $pdo->prepare("SELECT * FROM payments WHERE payable_type='shop_order' AND payable_id=?" . $lock);
        $payment->execute([(int)$orderId]);
        $payment = $payment->fetch(PDO::FETCH_ASSOC);
        $order = shopOrderAdminLockOrder($pdo, (int)$orderId);
        if (!$order || !$payment || (int)$order['account_id'] !== (int)$claim['account_id']) {
            throw new AthleteRegistrationException('Objednávka přihlášky nemá odpovídající účet nebo platbu. Musí ji prověřit správce.');
        }
        if ((string)$order['status'] === 'cancelled') {
            // An administrator may already have settled a mixed order explicitly.
            // Verify its completed cancellation, then close the request without
            // touching any unrelated items or repeating the order audit.
            if (clubProgramLifecycleAvailable($pdo)) clubProgramAssertOrderHasNoActiveEnrollments($pdo, (int)$orderId);
            clubEventShopAssertRefundableInTransaction($pdo, (int)$orderId);
            publicVelodromeShopAssertRefundableInTransaction($pdo, (int)$orderId);
            $results[] = shopOrderCancelStateInTransaction($pdo, $payment, $order, $actorType, $actorId, $reason);
            continue;
        }
        $items = $pdo->prepare(
            'SELECT oi.athlete_registration_request_id,oi.beneficiary_sportovec_id,co.id AS program_offer_id '
            . 'FROM shop_order_items oi LEFT JOIN club_program_offers co ON co.product_id=oi.product_id AND co.variant_id=oi.variant_id '
            . 'WHERE oi.order_id=? ORDER BY oi.id' . $lock
        );
        $items->execute([(int)$orderId]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if ((int)($item['athlete_registration_request_id'] ?? 0) !== (int)$claim['id']
                || $item['beneficiary_sportovec_id'] !== null
                || (int)($item['program_offer_id'] ?? 0) < 1
            ) {
                throw new AthleteRegistrationException('Objednávka obsahuje jinou nebo již přiřazenou položku. Nejdříve ji musí vypořádat správce.');
            }
        }
        if (clubProgramLifecycleAvailable($pdo)) clubProgramAssertOrderHasNoActiveEnrollments($pdo, (int)$orderId);
        if (clubEventShopOrderRows($pdo, (int)$orderId) !== []
            || publicVelodromeShopOrderRows($pdo, (int)$orderId) !== []
        ) {
            throw new AthleteRegistrationException('Objednávka obsahuje další rezervace. Nejdříve ji musí vypořádat správce.');
        }
        $results[] = shopOrderCancelStateInTransaction($pdo, $payment, $order, $actorType, $actorId, $reason);
    }
    return $results;
}
