<?php
declare(strict_types=1);

/** State conflicts are safe to show to the financial operator. */
final class FinancialEventConflict extends RuntimeException {}

function financialEventBlockedMessage(int $id): string
{
    return "Finanční akce #{$id} je uzavřená. Záznam ani jeho přílohy nelze změnit. "
        . 'Opravu předejte hospodáři; samostatný opravný postup zatím není dostupný.';
}

/** Every writer must retain these locks through its mutation AND audit commit. */
function financialEventLockSuffix(PDO $pdo): string
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Financial evidence requires an active transaction.');
    }
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
}

function financialEventLock(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM ucto_udalosti WHERE id = ?' . financialEventLockSuffix($pdo));
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        throw new FinancialEventConflict("Finanční akce #{$id} nebyla nalezena. Obnovte formulář.");
    }
    return $event;
}

function financialEventAssertOpen(array $event): void
{
    if (($event['stav'] ?? '') === 'uzavrena') {
        throw new FinancialEventConflict(financialEventBlockedMessage((int)$event['id']));
    }
    if (($event['stav'] ?? '') !== 'otevrena') {
        throw new FinancialEventConflict('Finanční akce #' . (int)$event['id']
            . ' má neznámý stav. Změnu musí nejprve prověřit hospodář.');
    }
}

function financialReceiptLock(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM ucto_uctenky WHERE id = ?' . financialEventLockSuffix($pdo));
    $stmt->execute([$id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) {
        throw new FinancialEventConflict("Účtenka #{$id} nebyla nalezena. Obnovte formulář.");
    }
    return $receipt;
}

/** Lock the receipt first, then both event IDs in order. Never trust a posted source ID. */
function financialReceiptAssertMutable(PDO $pdo, ?array $lockedReceipt, ?int $targetEventId): void
{
    financialEventLockSuffix($pdo);
    $ids = [];
    foreach ([$lockedReceipt['udalost_id'] ?? null, $targetEventId] as $id) {
        if ($id === null || $id === '') continue;
        if ((int)$id <= 0) throw new FinancialEventConflict('Neplatná finanční akce. Obnovte formulář.');
        $ids[(int)$id] = (int)$id;
    }
    sort($ids, SORT_NUMERIC);
    foreach ($ids as $id) {
        financialEventAssertOpen(financialEventLock($pdo, $id));
    }
}
