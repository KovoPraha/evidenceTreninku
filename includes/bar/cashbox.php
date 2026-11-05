<?php

declare(strict_types=1);

require_once __DIR__ . '/requests.php';

function barOwnedCashbox(PDO $pdo, int $id, int $ownerId, bool $open = true, string $suffix = ''): array
{
    $query = $pdo->prepare('SELECT * FROM kis_bar_cashboxes WHERE id=? AND owner_id=?' . $suffix);
    $query->execute([$id, $ownerId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if (!$row || ($open && ($row['closed_at'] !== null || (int)$row['active_owner_id'] !== $ownerId))) {
        throw new DomainException('Kasa není vaše nebo už byla uzavřená.');
    }
    return $row;
}

function barOwnOpenCashbox(PDO $pdo): ?array
{
    $actor = barActor($pdo, 'sell');
    $query = $pdo->prepare('SELECT * FROM kis_bar_cashboxes WHERE active_owner_id=? AND closed_at IS NULL');
    $query->execute([$actor['id']]);
    return $query->fetch(PDO::FETCH_ASSOC) ?: null;
}

function barCashBalance(PDO $pdo, int $id): int
{
    $query = $pdo->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM kis_bar_cash_movements WHERE cashbox_id=?');
    $query->execute([$id]);
    return (int)$query->fetchColumn();
}

function barAppendCash(PDO $pdo, array $actor, int $cashboxId, int $amount, string $kind, string $source, ?int $sourceId, string $reason): int
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Pohyb hotovosti vyžaduje transakci.');
    }
    barOwnedCashbox($pdo, $cashboxId, $actor['id']);
    $balance = barCashBalance($pdo, $cashboxId) + $amount;
    if ($balance < 0) {
        throw new DomainException('V kase není dost hotovosti pro tuto operaci.');
    }
    barCheckMinor($balance);
    $pdo->prepare('INSERT INTO kis_bar_cash_movements(cashbox_id,actor_id,kind,amount_minor,source_type,source_id,reason) VALUES(?,?,?,?,?,?,?)')
        ->execute([$cashboxId, $actor['id'], $kind, $amount, $source, $sourceId, $reason]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE kis_bar_cashboxes SET lock_version=lock_version+1 WHERE id=?')->execute([$cashboxId]);
    return $id;
}

function barOpenCashbox(PDO $pdo, int $openingMinor, string $reason, string $token): array
{
    barCheckMinor($openingMinor);
    $reason = barReason($reason);
    return barRunRequest($pdo, 'cashbox_open', $token, [$openingMinor, $reason], function (array $actor, string $suffix) use ($pdo, $openingMinor, $reason): array {
        $query = $pdo->prepare('SELECT id FROM kis_bar_cashboxes WHERE active_owner_id=?' . $suffix);
        $query->execute([$actor['id']]);
        if ($query->fetchColumn()) {
            throw new BarConflictException('Už máte otevřenou kasu. Nejprve ji uzavřete.');
        }
        $pdo->prepare('INSERT INTO kis_bar_cashboxes(owner_id,active_owner_id,opening_minor,opening_reason) VALUES(?,?,?,?)')
            ->execute([$actor['id'], $actor['id'], $openingMinor, $reason]);
        $id = (int)$pdo->lastInsertId();
        barAppendCash($pdo, $actor, $id, $openingMinor, 'opening', 'cashbox_open', $id, $reason);
        barEvent($pdo, $actor['id'], 'cashbox', $id, 'opened', ['opening_minor' => $openingMinor, 'reason' => $reason]);
        return ['cashbox_id' => $id];
    });
}

function barAdjustCash(PDO $pdo, int $id, int $deltaMinor, string $reason, string $token): array
{
    if ($deltaMinor === 0) {
        throw new InvalidArgumentException('Zadejte nenulovou částku vkladu nebo výběru.');
    }
    barCheckMinor(abs($deltaMinor));
    $reason = barReason($reason);
    return barRunRequest($pdo, 'cash_adjust', $token, [$id, $deltaMinor, $reason], function (array $actor, string $suffix) use ($pdo, $id, $deltaMinor, $reason): array {
        barOwnedCashbox($pdo, $id, $actor['id'], true, $suffix);
        $movementId = barAppendCash($pdo, $actor, $id, $deltaMinor, $deltaMinor > 0 ? 'cash_in' : 'cash_out', 'manual', null, $reason);
        barEvent($pdo, $actor['id'], 'cashbox', $id, 'cash_adjusted', ['movement_id' => $movementId, 'amount_minor' => $deltaMinor, 'reason' => $reason]);
        return ['cashbox_id' => $id, 'movement_id' => $movementId];
    });
}

function barCashboxTotals(PDO $pdo, int $id): array
{
    $query = $pdo->prepare("SELECT p.method,p.status,COALESCE(SUM(p.amount_minor),0) AS amount,COUNT(*) AS orders
        FROM kis_bar_sale_payments p JOIN kis_bar_sales s ON s.id=p.sale_id WHERE s.cashbox_id=? GROUP BY p.method,p.status");
    $query->execute([$id]);
    $totals = ['cash' => 0, 'card' => 0, 'qr' => 0, 'pending_count' => 0, 'pending_minor' => 0];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['status'] === 'confirmed') {
            $totals[$row['method']] += (int)$row['amount'];
        } elseif ($row['status'] === 'pending') {
            $totals['pending_count'] += (int)$row['orders'];
            $totals['pending_minor'] += (int)$row['amount'];
        }
    }
    $totals['expected_minor'] = barCashBalance($pdo, $id);
    $query = $pdo->prepare('SELECT refund_method,COALESCE(SUM(refunded_minor),0) AS amount FROM kis_bar_voids WHERE refund_cashbox_id=? GROUP BY refund_method');
    $query->execute([$id]);
    $totals['refunds'] = $query->fetchAll(PDO::FETCH_ASSOC);
    $query = $pdo->prepare('SELECT COALESCE(SUM(total_minor),0) FROM kis_bar_cup_returns WHERE cashbox_id=?');
    $query->execute([$id]);
    $totals['cup_refunds_minor'] = (int)$query->fetchColumn();
    return $totals;
}

function barCloseCashbox(PDO $pdo, int $id, int $version, int $countedMinor, string $reason, string $token): array
{
    barCheckMinor($countedMinor);
    $reason = barReason($reason);
    return barRunRequest($pdo, 'cashbox_close', $token, [$id, $version, $countedMinor, $reason], function (array $actor, string $suffix) use ($pdo, $id, $version, $countedMinor, $reason): array {
        $box = barOwnedCashbox($pdo, $id, $actor['id'], true, $suffix);
        if ((int)$box['lock_version'] !== $version) {
            throw new BarConflictException('V kase přibyla operace. Obnovte přehled a před uzávěrkou hotovost znovu zkontrolujte.');
        }
        $totals = barCashboxTotals($pdo, $id);
        if ($totals['pending_count'] !== 0) {
            throw new DomainException('Nejprve potvrďte nebo stornujte všechny čekající platby této kasy.');
        }
        $difference = $countedMinor - $totals['expected_minor'];
        $pdo->prepare('UPDATE kis_bar_cashboxes SET active_owner_id=NULL,closed_at=CURRENT_TIMESTAMP,lock_version=lock_version+1,'
            . 'expected_minor=?,counted_minor=?,difference_minor=?,closing_reason=?,closing_snapshot_json=? WHERE id=?')
            ->execute([$totals['expected_minor'], $countedMinor, $difference, $reason, json_encode($totals, JSON_THROW_ON_ERROR), $id]);
        barEvent($pdo, $actor['id'], 'cashbox', $id, 'closed', ['expected_minor' => $totals['expected_minor'], 'counted_minor' => $countedMinor,
            'difference_minor' => $difference, 'reason' => $reason]);
        return ['cashbox_id' => $id, 'expected_minor' => $totals['expected_minor'], 'counted_minor' => $countedMinor, 'difference_minor' => $difference];
    });
}

function barSetCupDeposit(PDO $pdo, int $minor, int $version, string $reason, string $token): array
{
    barCheckMinor($minor);
    $reason = barReason($reason);
    return barRunRequest($pdo, 'cup_deposit_setting', $token, [$minor, $version, $reason], function (array $actor, string $suffix) use ($pdo, $minor, $version, $reason): array {
        $old = $pdo->query('SELECT * FROM kis_bar_settings WHERE id=1' . $suffix)->fetch(PDO::FETCH_ASSOC);
        if (!$old || (int)$old['lock_version'] !== $version) {
            throw new BarConflictException('Nastavení se mezitím změnilo. Načtěte stránku znovu.');
        }
        $pdo->prepare('UPDATE kis_bar_settings SET cup_deposit_minor=?,lock_version=lock_version+1 WHERE id=1')->execute([$minor]);
        barEvent($pdo, $actor['id'], 'settings', 1, 'cup_deposit_changed', ['before_minor' => (int)$old['cup_deposit_minor'], 'after_minor' => $minor, 'reason' => $reason]);
        return ['cup_deposit_minor' => $minor];
    }, 'catalog_manage');
}
