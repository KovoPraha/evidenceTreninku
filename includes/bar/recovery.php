<?php

declare(strict_types=1);

require_once __DIR__ . '/checkout_read.php';

function barCashboxOwnerAvailable(PDO $pdo, int $ownerId, string $suffix = ''): bool
{
    $query = $pdo->prepare('SELECT aktivni FROM treneri WHERE id=?' . $suffix);
    $query->execute([$ownerId]);
    if ((int)$query->fetchColumn() !== 1) {
        return false;
    }
    $query = $pdo->prepare("SELECT p.position_code FROM staff_user_positions p JOIN staff_positions s ON s.code=p.position_code
        WHERE p.trainer_id=? AND p.position_code IN ('bar_operator','bar_admin') AND s.active=1" . $suffix);
    $query->execute([$ownerId]);
    if ($query->fetchColumn()) {
        return true;
    }
    $query = $pdo->prepare('SELECT trainer_id FROM staff_superadmins WHERE trainer_id=?' . $suffix);
    $query->execute([$ownerId]);
    return (bool)$query->fetchColumn()
        && (int)$pdo->query("SELECT COUNT(*) FROM staff_positions WHERE code IN ('bar_operator','bar_admin') AND active=1")->fetchColumn() > 0;
}

function barRecoveryCashbox(PDO $pdo, int $id, string $suffix = ''): array
{
    barActor($pdo, 'cash_recovery', $suffix !== '');
    $query = $pdo->prepare('SELECT b.*,t.jmeno AS owner_name FROM kis_bar_cashboxes b JOIN treneri t ON t.id=b.owner_id WHERE b.id=?' . $suffix);
    $query->execute([$id]);
    $box = $query->fetch(PDO::FETCH_ASSOC);
    if (!$box) {
        throw new DomainException('Kasa nebyla nalezena.');
    }
    $box['owner_available'] = barCashboxOwnerAvailable($pdo, (int)$box['owner_id'], $suffix);
    return $box;
}

/** Only unavailable owners qualify. The administrator never assumes their identity or transfers historical ownership. */
function barRecoverCashbox(
    PDO $pdo,
    int $cashboxId,
    int $version,
    string $action,
    int $saleId,
    int $countedMinor,
    string $reference,
    bool $confirmed,
    string $reason,
    string $token
): array {
    barActor($pdo, 'cash_recovery');
    $reason = barReason($reason);
    $reference = trim(barSourceText($reference, 255));
    barCheckMinor($countedMinor);
    if (!$confirmed || !in_array($action, ['confirm', 'cancel', 'close'], true)
        || ($action !== 'close' && $saleId < 1) || ($action === 'confirm' && mb_strlen($reference, 'UTF-8') < 3)) {
        throw new InvalidArgumentException('Vyberte operaci, zkontrolujte podklad a výslovně potvrďte skutečný stav.');
    }
    $ownerQuery = $pdo->prepare('SELECT owner_id FROM kis_bar_cashboxes WHERE id=?');
    $ownerQuery->execute([$cashboxId]);
    $ownerId = (int)$ownerQuery->fetchColumn();
    if ($ownerId < 1) {
        throw new DomainException('Kasa nebyla nalezena.');
    }
    return barRunRequest(
        $pdo,
        'cashbox_recovery',
        $token,
        [$cashboxId, $version, $action, $saleId, $countedMinor, $reference, $confirmed, $reason],
        function (array $actor, string $suffix) use ($pdo, $cashboxId, $version, $action, $saleId, $countedMinor, $reference, $reason): array {
            $box = barRecoveryCashbox($pdo, $cashboxId, $suffix);
            if ($box['closed_at'] !== null || (int)$box['lock_version'] !== $version) {
                throw new BarConflictException('Kasa se změnila nebo již byla uzavřena. Načtěte aktuální stav.');
            }
            if ($box['owner_available']) {
                throw new DomainException('Vlastník má přístup do baru. Svou kasu musí vypořádat sám.');
            }
            if ($action === 'close') {
                return barCloseCashboxLocked($pdo, $actor, $box, $countedMinor, $reason, 'recovery');
            }
            $query = $pdo->prepare("SELECT * FROM kis_bar_sales WHERE id=? AND cashbox_id=? AND kind='sale'" . $suffix);
            $query->execute([$saleId, $cashboxId]);
            $sale = $query->fetch(PDO::FETCH_ASSOC);
            $payment = barSalePayment($pdo, $saleId, $suffix);
            if (!$sale || !$payment || $payment['status'] !== 'pending') {
                throw new DomainException('Doklad nepatří do této kasy nebo jeho platba už nečeká.');
            }
            barRequireUnvoidedSale($pdo, $saleId);
            if ($action === 'confirm') {
                $result = barConfirmSalePaymentLocked($pdo, $actor, $sale, $reference, $suffix);
                barEvent(
                    $pdo,
                    $actor['id'],
                    'cashbox',
                    $cashboxId,
                    'recovery_payment_confirmed',
                    ['sale_id' => $saleId, 'owner_id' => (int)$box['owner_id'], 'reason' => $reason, 'reference' => $reference]
                );
                return ['cashbox_id' => $cashboxId] + $result;
            }
            $pdo->prepare('INSERT INTO kis_bar_voids(sale_id,actor_id,refund_cashbox_id,goods_minor,deposit_minor,cup_quantity,'
                . 'reversed_minor,refunded_minor,refund_method,refund_reference,reason,created_at) VALUES(?,?,NULL,?,?,?,?,0,?,NULL,?,'
                . barSqlUtcNow($pdo) . ')')
                ->execute([$saleId, $actor['id'], $sale['goods_net_minor'], $sale['deposit_minor'], $sale['cup_quantity'], $sale['total_minor'], 'none', $reason]);
            $voidId = (int)$pdo->lastInsertId();
            barRestoreSaleComponents($pdo, $actor, $saleId, $voidId, false, $suffix);
            $pdo->prepare("UPDATE kis_bar_sale_payments SET status='cancelled' WHERE sale_id=?")->execute([$saleId]);
            $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$cashboxId]);
            barEvent(
                $pdo,
                $actor['id'],
                'cashbox',
                $cashboxId,
                'recovery_pending_cancelled',
                ['sale_id' => $saleId, 'void_id' => $voidId, 'owner_id' => (int)$box['owner_id'], 'reason' => $reason, 'goods_returned' => true]
            );
            return ['cashbox_id' => $cashboxId, 'sale_id' => $saleId, 'void_id' => $voidId, 'refunded_minor' => 0];
        },
        'cash_recovery',
        [$ownerId]
    );
}

function barRecoveryView(PDO $pdo, ?int $id): array
{
    barActor($pdo, 'cash_recovery');
    $rows = $pdo->query('SELECT b.id,b.owner_id,b.opened_at,b.closed_at,b.closure_kind,t.jmeno AS owner_name
        FROM kis_bar_cashboxes b JOIN treneri t ON t.id=b.owner_id ORDER BY b.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    if ($id === null) {
        return ['history' => $rows, 'box' => null, 'totals' => null, 'pending' => []];
    }
    $box = barRecoveryCashbox($pdo, $id);
    $query = $pdo->prepare("SELECT s.id,s.created_at,s.total_minor,p.method FROM kis_bar_sales s
        JOIN kis_bar_sale_payments p ON p.sale_id=s.id WHERE s.cashbox_id=? AND p.status='pending' ORDER BY s.id");
    $query->execute([$id]);
    return ['history' => $rows, 'box' => $box,
        'totals' => $box['closed_at'] === null ? barCashboxTotals($pdo, $id) : json_decode($box['closing_snapshot_json'], true, 16, JSON_THROW_ON_ERROR),
        'pending' => $query->fetchAll(PDO::FETCH_ASSOC)];
}
