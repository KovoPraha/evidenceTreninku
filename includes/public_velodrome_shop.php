<?php
declare(strict_types=1);

require_once __DIR__ . '/public_velodrome.php';

final class PublicVelodromeShopException extends RuntimeException
{
}

function publicVelodromeShopAvailable(PDO $pdo): bool
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
            . "AND TABLE_NAME IN ('public_velodrome_cart_items','public_velodrome_order_items')"
        );
        $statement->execute();
        return (int)$statement->fetchColumn() === 2;
    }
    if ($driver === 'sqlite') {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type='table' "
            . "AND name IN ('public_velodrome_cart_items','public_velodrome_order_items')"
        )->fetchColumn() === 2;
    }
    return false;
}

/** @return array<string,mixed> */
function publicVelodromeShopAddToCart(PDO $pdo, int $accountId, int $lessonId, string $note = ''): array
{
    $note = trim($note);
    if ($accountId < 1 || $lessonId < 1 || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Placený termín vyžaduje účet, slot a platnou poznámku.');
    }
    if (!publicVelodromeShopAvailable($pdo)) {
        throw new PublicVelodromeShopException('Shop napojení velodromu zatím není migrováno.');
    }
    $pdo->beginTransaction();
    try {
        // Global checkout order: cart -> self profile -> lesson -> reservation rows.
        $cart = shopCartLockActive($pdo, $accountId);
        $profile = publicVelodromeLockProfile($pdo, $accountId);
        if (!$profile) {
            throw new PublicVelodromeShopException('Nejprve dokončete svůj veřejný profil.');
        }
        $lesson = publicVelodromeShopLockLesson($pdo, $lessonId);
        publicVelodromeShopAssertPaidLesson($lesson);
        $existingReservation = $pdo->prepare(
            "SELECT id FROM verejne_rezervace WHERE lekce_id=? AND sportovec_id=? "
            . "AND active_token='active' AND stav IN ('ceka','potvrzena') LIMIT 1"
        );
        $existingReservation->execute([$lessonId, (int)$profile['sportovec_id']]);
        if ($existingReservation->fetchColumn()) {
            throw new PublicVelodromeShopException('Tento termín už máte rezervovaný nebo čeká na úhradu.');
        }
        $existing = $pdo->prepare(
            'SELECT id FROM public_velodrome_cart_items WHERE cart_id=? AND lesson_id=? AND beneficiary_sportovec_id=?'
        );
        $existing->execute([(int)$cart['id'], $lessonId, (int)$profile['sportovec_id']]);
        $id = (int)$existing->fetchColumn();
        $created = $id < 1;
        if ($id < 1) {
            $pdo->prepare(
                'INSERT INTO public_velodrome_cart_items(cart_id,lesson_id,beneficiary_sportovec_id,note) VALUES (?,?,?,?)'
            )->execute([(int)$cart['id'], $lessonId, (int)$profile['sportovec_id'], $note !== '' ? $note : null]);
            $id = (int)$pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE public_velodrome_cart_items SET note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$note !== '' ? $note : null, $id]);
        }
        $pdo->commit();
        return ['id' => $id, 'cart_id' => (int)$cart['id'], 'created' => $created];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof InvalidArgumentException || $exception instanceof PublicVelodromeShopException) {
            throw $exception;
        }
        throw new PublicVelodromeShopException('Termín se nepodařilo vložit do košíku bez částečné změny.', 0, $exception);
    }
}

function publicVelodromeShopRemoveFromCart(PDO $pdo, int $accountId, int $cartItemId): bool
{
    if ($accountId < 1 || $cartItemId < 1) {
        throw new InvalidArgumentException('Odebrání vyžaduje účet a položku.');
    }
    $pdo->beginTransaction();
    try {
        $cart = shopCartLockActive($pdo, $accountId);
        $statement = $pdo->prepare('DELETE FROM public_velodrome_cart_items WHERE id=? AND cart_id=?');
        $statement->execute([$cartItemId, (int)$cart['id']]);
        $changed = $statement->rowCount() === 1;
        $pdo->commit();
        return $changed;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException) throw $exception;
        throw new PublicVelodromeShopException('Položku se nepodařilo bezpečně odebrat.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function publicVelodromeShopCartItems(PDO $pdo, int $cartId): array
{
    if (!publicVelodromeShopAvailable($pdo) || $cartId < 1) return [];
    $statement = $pdo->prepare(
        'SELECT ci.id AS cart_item_id,ci.lesson_id,ci.beneficiary_sportovec_id,ci.note,'
        . 'il.nazev AS lesson_name,il.datum,il.cas_od,il.cas_do,il.public_exclusive_booking,il.cena_kc,'
        . 'sp.jmeno,sp.prijmeni FROM public_velodrome_cart_items ci '
        . 'JOIN individualni_lekce il ON il.id=ci.lesson_id JOIN sportovci sp ON sp.id=ci.beneficiary_sportovec_id '
        . 'WHERE ci.cart_id=? ORDER BY ci.lesson_id,ci.id'
    );
    $statement->execute([$cartId]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['quantity'] = 1;
        $item['amount_minor'] = publicVelodromeShopPriceMinor($item['cena_kc']);
        $item['line_amount_minor'] = $item['amount_minor'];
        $item['currency'] = 'CZK';
    }
    unset($item);
    return $items;
}

/**
 * Locks and validates service rows during checkout.
 * Caller already holds the active cart and all catalog variants.
 * @return list<array<string,mixed>>
 */
function publicVelodromeShopLockCheckoutItems(PDO $pdo, int $cartId, int $accountId): array
{
    if (!publicVelodromeShopAvailable($pdo)) return [];
    $profile = publicVelodromeLockProfile($pdo, $accountId);
    $sql = 'SELECT * FROM public_velodrome_cart_items WHERE cart_id=? ORDER BY lesson_id,id';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$cartId]);
    $cartItems = $statement->fetchAll(PDO::FETCH_ASSOC);
    if ($cartItems === []) return [];
    if (!$profile) throw new PublicVelodromeShopException('Veřejný profil účastníka není platný.');
    $result = [];
    $requestedPeriods = [];
    foreach ($cartItems as $cartItem) {
        if ((int)$cartItem['beneficiary_sportovec_id'] !== (int)$profile['sportovec_id']) {
            throw new PublicVelodromeShopException('Placený velodrom musí být objednán pro vlastní veřejný profil.');
        }
        $lesson = publicVelodromeShopLockLesson($pdo, (int)$cartItem['lesson_id']);
        publicVelodromeShopAssertPaidLesson($lesson);
        $existing = $pdo->prepare(
            "SELECT id FROM verejne_rezervace WHERE lekce_id=? AND sportovec_id=? "
            . "AND active_token='active' AND stav IN ('ceka','potvrzena') LIMIT 1"
        );
        $existing->execute([(int)$lesson['id'], (int)$profile['sportovec_id']]);
        if ($existing->fetchColumn()) throw new PublicVelodromeShopException('Termín už je pro tohoto účastníka rezervován.');
        $overlap = $pdo->prepare(
            'SELECT r.id FROM verejne_rezervace r JOIN individualni_lekce other ON other.id=r.lekce_id '
            . "WHERE r.sportovec_id=? AND r.active_token='active' AND r.stav IN ('ceka','potvrzena') "
            . 'AND other.datum=? AND other.cas_od<? AND other.cas_do>? LIMIT 1'
        );
        $overlap->execute([(int)$profile['sportovec_id'], $lesson['datum'], $lesson['cas_do'], $lesson['cas_od']]);
        if ($overlap->fetchColumn()) throw new PublicVelodromeShopException('Účastník už má v tomto čase jinou aktivní rezervaci.');
        foreach ($requestedPeriods as $period) {
            if ($period['date'] === $lesson['datum'] && $period['from'] < $lesson['cas_do'] && $period['to'] > $lesson['cas_od']) {
                throw new PublicVelodromeShopException('Košík obsahuje překrývající se termíny stejného účastníka.');
            }
        }
        $requestedPeriods[] = ['date' => $lesson['datum'], 'from' => $lesson['cas_od'], 'to' => $lesson['cas_do']];
        publicVelodromeShopAssertCapacity($pdo, $lesson);
        $result[] = $cartItem + $lesson + [
            'amount_minor' => publicVelodromeShopPriceMinor($lesson['cena_kc']),
            'currency' => 'CZK',
        ];
    }
    return $result;
}

/** @param list<array<string,mixed>> $items */
function publicVelodromeShopFingerprintItems(array $items): array
{
    return array_map(static fn(array $item): array => [
        'lesson_id' => (int)$item['lesson_id'],
        'beneficiary_sportovec_id' => (int)$item['beneficiary_sportovec_id'],
        'amount_minor' => isset($item['amount_minor'])
            ? (int)$item['amount_minor']
            : publicVelodromeShopPriceMinor($item['cena_kc']),
        'currency' => 'CZK',
    ], $items);
}

/** @param list<array<string,mixed>> $items */
function publicVelodromeShopCreateOrderItemsInTransaction(PDO $pdo, int $orderId, int $accountId, array $items): int
{
    $created = 0;
    foreach ($items as $item) {
        $lessonId = (int)$item['lesson_id'];
        $sportovecId = (int)$item['beneficiary_sportovec_id'];
        $status = 'ceka';
        $pdo->prepare(
            'INSERT INTO verejne_rezervace '
            . '(lekce_id,uzivatel_id,sportovec_id,stav,zaplaceno,poznamka_klienta,slot_cas_od,slot_cas_do,active_token) '
            . "VALUES (?,?,?,'ceka',0,?,?,?,'active')"
        )->execute([
            $lessonId, $accountId, $sportovecId, $item['note'] ?: null, $item['cas_od'], $item['cas_do'],
        ]);
        $reservationId = (int)$pdo->lastInsertId();
        $amount = (int)$item['amount_minor'];
        $pdo->prepare(
            'INSERT INTO public_velodrome_order_items '
            . '(order_id,reservation_id,lesson_id,beneficiary_sportovec_id,lesson_name_snapshot,lesson_date_snapshot,'
            . 'starts_at_snapshot,ends_at_snapshot,exclusive_snapshot,note_snapshot,quantity,unit_amount_minor,line_amount_minor,currency) '
            . "VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?,'CZK')"
        )->execute([
            $orderId, $reservationId, $lessonId, $sportovecId, (string)$item['nazev'], (string)$item['datum'],
            (string)$item['cas_od'], (string)$item['cas_do'], (int)$item['public_exclusive_booking'],
            $item['note'] ?: null, $amount, $amount,
        ]);
        publicVelodromeAudit(
            $pdo, $reservationId, 'account', $accountId, 'shop_order_hold', null, $status,
            'Kapacita držena objednávkou #' . $orderId . '; čeká na úhradu.'
        );
        $created++;
    }
    return $created;
}

/** @return array{items:int,activated:int} */
function publicVelodromeShopActivatePaidOrderInTransaction(PDO $pdo, int $orderId, int $actorTrainerId): array
{
    $items = publicVelodromeShopOrderRows($pdo, $orderId);
    $activated = 0;
    foreach ($items as $item) {
        publicVelodromeShopLockLesson($pdo, (int)$item['lesson_id']);
        $reservation = publicVelodromeShopLockReservation($pdo, (int)$item['reservation_id']);
        if ($reservation['stav'] === 'potvrzena' && (int)$reservation['zaplaceno'] === 1) continue;
        if ($reservation['stav'] !== 'ceka' || (int)$reservation['zaplaceno'] !== 0 || $reservation['active_token'] !== 'active') {
            throw new PublicVelodromeShopException('Rezervace objednávky není v platném stavu pro přijetí platby.');
        }
        $pdo->prepare("UPDATE verejne_rezervace SET stav='potvrzena',zaplaceno=1,cas_potvrzeni=CURRENT_TIMESTAMP WHERE id=?")
            ->execute([(int)$reservation['id']]);
        publicVelodromeAudit($pdo, (int)$reservation['id'], 'trainer', $actorTrainerId, 'shop_payment_paid', 'ceka', 'potvrzena', 'Platba potvrzena na objednávce #' . $orderId . '.');
        $activated++;
    }
    return ['items' => count($items), 'activated' => $activated];
}

/** @return array{items:int,cancelled:int} */
function publicVelodromeShopCancelOrderInTransaction(PDO $pdo, int $orderId, int $actorTrainerId, string $reason): array
{
    $items = publicVelodromeShopOrderRows($pdo, $orderId);
    $cancelled = 0;
    foreach ($items as $item) {
        publicVelodromeShopLockLesson($pdo, (int)$item['lesson_id']);
        $reservation = publicVelodromeShopLockReservation($pdo, (int)$item['reservation_id']);
        if ($reservation['stav'] === 'zrusena' && $reservation['active_token'] === null) continue;
        if (!in_array($reservation['stav'], ['ceka', 'potvrzena'], true) || $reservation['active_token'] !== 'active') {
            throw new PublicVelodromeShopException('Rezervaci objednávky nelze bezpečně stornovat.');
        }
        $from = (string)$reservation['stav'];
        $pdo->prepare("UPDATE verejne_rezervace SET stav='zrusena',active_token=NULL,poznamka_trenera=? WHERE id=?")
            ->execute([$reason, (int)$reservation['id']]);
        publicVelodromeAudit($pdo, (int)$reservation['id'], 'trainer', $actorTrainerId, 'shop_order_cancel', $from, 'zrusena', $reason);
        $cancelled++;
    }
    return ['items' => count($items), 'cancelled' => $cancelled];
}

function publicVelodromeShopAssertRefundableInTransaction(PDO $pdo, int $orderId): void
{
    foreach (publicVelodromeShopOrderRows($pdo, $orderId) as $item) {
        publicVelodromeShopLockLesson($pdo, (int)$item['lesson_id']);
        $reservation = publicVelodromeShopLockReservation($pdo, (int)$item['reservation_id']);
        if ($reservation['stav'] !== 'zrusena' || $reservation['active_token'] !== null) {
            throw new PublicVelodromeShopException('Vratku nelze potvrdit, dokud není rezervace bezpečně ukončena.');
        }
    }
}

function publicVelodromeShopReservationIsOrderLinked(PDO $pdo, int $reservationId): bool
{
    if (!publicVelodromeShopAvailable($pdo)) return false;
    $statement = $pdo->prepare('SELECT 1 FROM public_velodrome_order_items WHERE reservation_id=?');
    $statement->execute([$reservationId]);
    return (bool)$statement->fetchColumn();
}

/** @return list<array<string,mixed>> */
function publicVelodromeShopOrderRows(PDO $pdo, int $orderId): array
{
    if (!publicVelodromeShopAvailable($pdo)) return [];
    $sql = 'SELECT * FROM public_velodrome_order_items WHERE order_id=? ORDER BY lesson_id,id';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$orderId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function publicVelodromeShopLockLesson(PDO $pdo, int $lessonId): array
{
    $sql = 'SELECT il.*,s.kod,s.je_verejne,s.aktivni FROM individualni_lekce il JOIN sportovist s ON s.id=il.sportoviste_id WHERE il.id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$lessonId]);
    $lesson = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$lesson) throw new PublicVelodromeShopException('Termín velodromu nebyl nalezen.');
    return $lesson;
}

/** @param array<string,mixed> $lesson */
function publicVelodromeShopAssertPaidLesson(array $lesson): void
{
    $starts = new DateTimeImmutable((string)$lesson['datum'] . ' ' . (string)$lesson['cas_od']);
    if ($lesson['kod'] !== 'velodrom' || (int)$lesson['je_verejne'] !== 1 || (int)$lesson['aktivni'] !== 1
        || $lesson['stav'] !== 'aktivni' || $starts <= new DateTimeImmutable('now')
        || publicVelodromeShopPriceMinor($lesson['cena_kc']) < 1
    ) {
        throw new PublicVelodromeShopException('Placený veřejný termín už není dostupný.');
    }
}

/** @param array<string,mixed> $lesson */
function publicVelodromeShopAssertCapacity(PDO $pdo, array $lesson): void
{
    $count = $pdo->prepare("SELECT COUNT(*) FROM verejne_rezervace WHERE lekce_id=? AND active_token='active' AND stav IN ('ceka','potvrzena')");
    $count->execute([(int)$lesson['id']]);
    $capacity = (int)$lesson['public_exclusive_booking'] === 1 ? 1 : (int)$lesson['max_osob'];
    if ($capacity < 1 || (int)$count->fetchColumn() >= $capacity) {
        throw new PublicVelodromeShopException('Kapacita termínu byla mezitím naplněna. Objednávka nevznikla.');
    }
}

/** @return array<string,mixed> */
function publicVelodromeShopLockReservation(PDO $pdo, int $reservationId): array
{
    $sql = 'SELECT * FROM verejne_rezervace WHERE id=?';
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$reservationId]);
    $reservation = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$reservation) throw new PublicVelodromeShopException('Rezervace objednávky nebyla nalezena.');
    return $reservation;
}

function publicVelodromeShopPriceMinor(mixed $price): int
{
    return (int)round((float)$price * 100, 0, PHP_ROUND_HALF_UP);
}
