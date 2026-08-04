<?php
declare(strict_types=1);

final class ShopCouponException extends RuntimeException {}

const SHOP_COUPON_GOODS = 1;
const SHOP_COUPON_CLUB_PROGRAM = 2;
const SHOP_COUPON_CLUB_EVENT = 4;
const SHOP_COUPON_VELODROME = 8;
const SHOP_COUPON_ALL = 15;

function shopCouponNormalizeCode(string $code): string
{
    $code = strtoupper(trim($code));
    if (preg_match('/^[A-Z0-9][A-Z0-9_-]{3,31}$/D', $code) !== 1) {
        throw new InvalidArgumentException('Kód musí mít 4–32 znaků A–Z, 0–9, pomlčku nebo podtržítko.');
    }
    return $code;
}

/** @return array<string,mixed> */
function shopCouponAdminCreate(PDO $pdo, int $actorTrainerId, string $code, string $type, int $value, int $minimumOrderMinor, ?int $maximumDiscountMinor, ?int $usageLimit, string $validFrom, string $validUntil, string $note, bool $confirmed, int $applicabilityMask = SHOP_COUPON_GOODS): array
{
    $code = shopCouponNormalizeCode($code);
    $note = trim($note);
    $validFrom = trim($validFrom);
    $validUntil = trim($validUntil);
    if ($actorTrainerId < 1 || !$confirmed || $note === '' || mb_strlen($note, 'UTF-8') > 1000) {
        throw new InvalidArgumentException('Vytvoření kupónu vyžaduje administrátora, poznámku a výslovné potvrzení.');
    }
    if (!in_array($type, ['fixed', 'percentage'], true)) throw new InvalidArgumentException('Nepodporovaný typ slevy.');
    if ($minimumOrderMinor < 0 || $minimumOrderMinor > 1000000000) throw new InvalidArgumentException('Minimální objednávka je mimo podporovaný rozsah.');
    if ($usageLimit !== null && ($usageLimit < 1 || $usageLimit > 1000000)) throw new InvalidArgumentException('Limit použití musí být 1 až 1 000 000 nebo prázdný.');
    if ($applicabilityMask < 1 || ($applicabilityMask & ~SHOP_COUPON_ALL) !== 0) throw new InvalidArgumentException('Kupón musí mít alespoň jeden podporovaný rozsah použití.');
    if ($type === 'fixed') {
        if ($value < 1 || $value > 1000000000 || $maximumDiscountMinor !== null) throw new InvalidArgumentException('Pevná sleva nebo její limit nejsou platné.');
    } elseif ($value < 1 || $value > 9900 || ($maximumDiscountMinor !== null && ($maximumDiscountMinor < 1 || $maximumDiscountMinor > 1000000000))) {
        throw new InvalidArgumentException('Procentní sleva musí být 0,01–99 % a maximální sleva musí být kladná.');
    }
    $from = shopCouponAdminDate($validFrom);
    $until = shopCouponAdminDate($validUntil);
    if ($from !== null && $until !== null && $from >= $until) throw new InvalidArgumentException('Konec platnosti musí být později než začátek.');

    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare('SELECT id FROM shop_coupons WHERE code=?');
        $existing->execute([$code]);
        if ($existing->fetchColumn() !== false) throw new ShopCouponException('Kupón s tímto kódem už existuje; jeho ekonomická pravidla jsou neměnná.');
        $insert = $pdo->prepare('INSERT INTO shop_coupons(code,discount_type,value_minor_or_basis_points,currency,minimum_order_minor,maximum_discount_minor,usage_limit_total,valid_from,valid_until,active,created_by_trainer_id,applicability_mask) VALUES (?,?,?,\'CZK\',?,?,?,?,?,1,?,?)');
        $insert->execute([$code, $type, $value, $minimumOrderMinor, $maximumDiscountMinor, $usageLimit, $from, $until, $actorTrainerId, $applicabilityMask]);
        $couponId = (int)$pdo->lastInsertId();
        $coupon = shopCouponAdminFind($pdo, $couponId, false);
        $after = shopCouponAuditSnapshot($coupon);
        $pdo->prepare('INSERT INTO shop_coupon_events(coupon_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,\'create\',NULL,?,?)')
            ->execute([$couponId, $actorTrainerId, json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $note]);
        $pdo->commit();
        return $coupon;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCouponException) throw $exception;
        throw new ShopCouponException('Kupón se nepodařilo vytvořit bez částečného zápisu.', 0, $exception);
    }
}

/** @return array{id:int,active:bool,changed:bool} */
function shopCouponAdminSetActive(PDO $pdo, int $couponId, int $actorTrainerId, bool $active, string $note, bool $confirmed): array
{
    $note = trim($note);
    if ($couponId < 1 || $actorTrainerId < 1 || !$confirmed || $note === '' || mb_strlen($note, 'UTF-8') > 1000) throw new InvalidArgumentException('Změna kupónu vyžaduje kupón, administrátora, poznámku a potvrzení.');
    $pdo->beginTransaction();
    try {
        $coupon = shopCouponAdminFind($pdo, $couponId, true);
        $before = shopCouponAuditSnapshot($coupon);
        if ((bool)$coupon['active'] === $active) {
            $pdo->commit();
            return ['id' => $couponId, 'active' => $active, 'changed' => false];
        }
        $pdo->prepare('UPDATE shop_coupons SET active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$active ? 1 : 0, $couponId]);
        $coupon['active'] = $active ? 1 : 0;
        $after = shopCouponAuditSnapshot($coupon);
        $pdo->prepare('INSERT INTO shop_coupon_events(coupon_id,actor_trainer_id,action,before_json,after_json,note) VALUES (?,?,?,?,?,?)')
            ->execute([$couponId, $actorTrainerId, $active ? 'activate' : 'deactivate', json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $note]);
        $pdo->commit();
        return ['id' => $couponId, 'active' => $active, 'changed' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCouponException) throw $exception;
        throw new ShopCouponException('Stav kupónu se nepodařilo změnit bez částečného zápisu.', 0, $exception);
    }
}

/** @return list<array<string,mixed>> */
function shopCouponAdminList(PDO $pdo): array
{
    return $pdo->query('SELECT c.*,e.action AS last_action,e.actor_trainer_id AS last_actor_id,e.note AS last_note,e.created_at AS last_event_at FROM shop_coupons c LEFT JOIN shop_coupon_events e ON e.id=(SELECT MAX(e2.id) FROM shop_coupon_events e2 WHERE e2.coupon_id=c.id) ORDER BY c.active DESC,c.created_at DESC,c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed> */
function shopCouponApplyToCart(PDO $pdo, int $accountId, string $code): array
{
    $code = shopCouponNormalizeCode($code);
    $pdo->beginTransaction();
    try {
        $cart = shopCartLockActive($pdo, $accountId);
        $breakdown = shopCouponCartBreakdown($pdo, (int)$cart['id']);
        $sql = 'SELECT * FROM shop_coupons WHERE code=?';
        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $statement = $pdo->prepare($sql);
        $statement->execute([$code]);
        $coupon = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) throw new ShopCouponException('Kupón nebyl nalezen.');
        $quote = shopCouponQuoteForBreakdown($coupon, $breakdown);
        $pdo->prepare('UPDATE shop_carts SET coupon_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$coupon['id'], (int)$cart['id']]);
        $pdo->commit();
        return $quote;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCouponException) throw $exception;
        throw new ShopCouponException('Kupón se nepodařilo použít bez částečného zápisu.', 0, $exception);
    }
}

function shopCouponRemoveFromCart(PDO $pdo, int $accountId): void
{
    $pdo->beginTransaction();
    try {
        $cart = shopCartLockActive($pdo, $accountId);
        $pdo->prepare('UPDATE shop_carts SET coupon_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$cart['id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

/** @return array<string,mixed>|null */
function shopCouponQuoteById(PDO $pdo, ?int $couponId, array|int $amounts, bool $lock = false): ?array
{
    if ($couponId === null || $couponId < 1) return null;
    $sql = 'SELECT * FROM shop_coupons WHERE id=?';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$couponId]);
    $coupon = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) throw new ShopCouponException('Použitý kupón už neexistuje.');
    $breakdown = is_int($amounts) ? ['goods' => $amounts, 'club_program' => 0, 'club_event' => 0, 'velodrome' => 0, 'total' => $amounts] : $amounts;
    return shopCouponQuoteForBreakdown($coupon, $breakdown);
}

/** @param array<string,int> $breakdown @return array<string,mixed> */
function shopCouponQuoteForBreakdown(array $coupon, array $breakdown): array
{
    foreach (['goods', 'club_program', 'club_event', 'velodrome', 'total'] as $key) {
        if (!isset($breakdown[$key]) || !is_int($breakdown[$key]) || $breakdown[$key] < 0) throw new ShopCouponException('Rozpad košíku pro kupón není platný.');
    }
    $computedTotal = 0;
    foreach (['goods', 'club_program', 'club_event', 'velodrome'] as $key) $computedTotal = shopCouponCheckedAdd($computedTotal, $breakdown[$key]);
    if ($computedTotal !== $breakdown['total']) throw new ShopCouponException('Rozpad košíku neodpovídá jeho celkové částce.');
    $mask = (int)($coupon['applicability_mask'] ?? SHOP_COUPON_GOODS);
    if ($mask < 1 || ($mask & ~SHOP_COUPON_ALL) !== 0) throw new ShopCouponException('Kupón má neplatný rozsah použití.');
    $eligible = 0;
    foreach (['goods' => SHOP_COUPON_GOODS, 'club_program' => SHOP_COUPON_CLUB_PROGRAM, 'club_event' => SHOP_COUPON_CLUB_EVENT, 'velodrome' => SHOP_COUPON_VELODROME] as $key => $flag) {
        if (($mask & $flag) !== 0) $eligible = shopCouponCheckedAdd($eligible, $breakdown[$key]);
    }
    $quote = shopCouponValidateRow($coupon, $eligible, $breakdown['total']);
    $quote['eligible_subtotal_minor'] = $eligible;
    $quote['applicability_mask'] = $mask;
    return $quote;
}

/** @return array<string,mixed> */
function shopCouponValidateRow(array $coupon, int $eligibleSubtotal, ?int $orderSubtotal = null): array
{
    $orderSubtotal ??= $eligibleSubtotal;
    $now = new DateTimeImmutable();
    if ((int)$coupon['active'] !== 1) throw new ShopCouponException('Kupón není aktivní.');
    if ($coupon['currency'] !== 'CZK') throw new ShopCouponException('Kupón nepodporuje měnu košíku.');
    if ($coupon['valid_from'] !== null && $now < new DateTimeImmutable((string)$coupon['valid_from'])) throw new ShopCouponException('Platnost kupónu ještě nezačala.');
    if ($coupon['valid_until'] !== null && $now > new DateTimeImmutable((string)$coupon['valid_until'])) throw new ShopCouponException('Platnost kupónu skončila.');
    if ($eligibleSubtotal < 1) throw new ShopCouponException('Kupón se nevztahuje na žádnou položku v košíku.');
    if ($eligibleSubtotal < (int)$coupon['minimum_order_minor']) throw new ShopCouponException('Položky povolené pro tento kupón nedosahují minimální částky.');
    if ($coupon['usage_limit_total'] !== null && (int)$coupon['usage_count'] >= (int)$coupon['usage_limit_total']) throw new ShopCouponException('Limit použití kupónu byl vyčerpán.');
    $value = (int)$coupon['value_minor_or_basis_points'];
    if ($coupon['discount_type'] === 'fixed') {
        $discount = $value;
    } elseif ($coupon['discount_type'] === 'percentage') {
        if ($value < 1 || $value > 9900 || $eligibleSubtotal > intdiv(PHP_INT_MAX, $value)) throw new ShopCouponException('Procentní sleva je mimo podporovaný rozsah.');
        $discount = intdiv($eligibleSubtotal * $value, 10000);
        if ($coupon['maximum_discount_minor'] !== null) $discount = min($discount, (int)$coupon['maximum_discount_minor']);
    } else {
        throw new ShopCouponException('Kupón má nepodporovaný typ slevy.');
    }
    if ($discount < 1) throw new ShopCouponException('Sleva kupónu je pro tento košík nulová.');
    if ($discount > $eligibleSubtotal) throw new ShopCouponException('Pevná sleva přesahuje součet položek, na které se kupón vztahuje.');
    if ($discount >= $orderSubtotal) throw new ShopCouponException('První bankovní checkout nepodporuje objednávku plně uhrazenou kupónem.');
    $coupon['discount_minor'] = $discount;
    return $coupon;
}

function shopCouponCartSubtotal(PDO $pdo, int $cartId): int
{
    return shopCouponCartBreakdown($pdo, $cartId)['total'];
}

/** @return array{goods:int,club_program:int,club_event:int,velodrome:int,total:int} */
function shopCouponCartBreakdown(PDO $pdo, int $cartId): array
{
    $statement = $pdo->prepare('SELECT ci.quantity,v.id AS variant_id,v.amount_minor,v.currency FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id WHERE ci.cart_id=? ORDER BY ci.id');
    $statement->execute([$cartId]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    $eventItems = function_exists('clubEventShopCartItems') ? clubEventShopCartItems($pdo, $cartId) : [];
    $velodromeItems = function_exists('publicVelodromeShopCartItems') ? publicVelodromeShopCartItems($pdo, $cartId) : [];
    return shopCouponBreakdownFromItems($pdo, $items, $eventItems, $velodromeItems);
}

/** @param list<array<string,mixed>> $items @param list<array<string,mixed>> $eventItems @param list<array<string,mixed>> $velodromeItems @return array{goods:int,club_program:int,club_event:int,velodrome:int,total:int} */
function shopCouponBreakdownFromItems(PDO $pdo, array $items, array $eventItems, array $velodromeItems): array
{
    $result = ['goods' => 0, 'club_program' => 0, 'club_event' => 0, 'velodrome' => 0, 'total' => 0];
    $programStatement = null;
    if (function_exists('clubProgramLifecycleAvailable') && clubProgramLifecycleAvailable($pdo)) $programStatement = $pdo->prepare('SELECT 1 FROM club_program_offers WHERE variant_id=? LIMIT 1');
    foreach ($items as $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        $amount = (int)($item['amount_minor'] ?? -1);
        if (($item['currency'] ?? null) !== 'CZK' || $quantity < 1 || $amount < 0 || $amount > intdiv(PHP_INT_MAX, $quantity)) throw new ShopCouponException('Košík není platný pro kupón.');
        $line = $quantity * $amount;
        $key = 'goods';
        if ($programStatement !== null) {
            $programStatement->execute([(int)($item['variant_id'] ?? 0)]);
            if ($programStatement->fetchColumn() !== false) $key = 'club_program';
        }
        $result[$key] = shopCouponCheckedAdd($result[$key], $line);
    }
    foreach (['club_event' => $eventItems, 'velodrome' => $velodromeItems] as $key => $serviceItems) {
        foreach ($serviceItems as $item) {
            if (($item['currency'] ?? null) !== 'CZK' || (int)($item['line_amount_minor'] ?? 0) < 1) throw new ShopCouponException('Služba v košíku není platná pro kupón.');
            $result[$key] = shopCouponCheckedAdd($result[$key], (int)$item['line_amount_minor']);
        }
    }
    foreach (['goods', 'club_program', 'club_event', 'velodrome'] as $key) $result['total'] = shopCouponCheckedAdd($result['total'], $result[$key]);
    if ($result['total'] < 1) throw new ShopCouponException('Kupón nelze použít na prázdný košík.');
    return $result;
}

function shopCouponCheckedAdd(int $left, int $right): int
{
    if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) throw new ShopCouponException('Součet košíku je mimo podporovaný rozsah.');
    return $left + $right;
}

/** @return list<string> */
function shopCouponApplicabilityLabels(int $mask): array
{
    $labels = [];
    foreach ([SHOP_COUPON_GOODS => 'zboží', SHOP_COUPON_CLUB_PROGRAM => 'kroužky', SHOP_COUPON_CLUB_EVENT => 'události', SHOP_COUPON_VELODROME => 'velodrom'] as $flag => $label) {
        if (($mask & $flag) !== 0) $labels[] = $label;
    }
    return $labels;
}

/** @return array<string,mixed> */
function shopCouponAdminFind(PDO $pdo, int $couponId, bool $lock): array
{
    $sql = 'SELECT * FROM shop_coupons WHERE id=?';
    if ($lock && (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $statement = $pdo->prepare($sql);
    $statement->execute([$couponId]);
    $coupon = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) throw new ShopCouponException('Kupón nebyl nalezen.');
    return $coupon;
}

function shopCouponAdminDate(string $value): ?string
{
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d H:i:s') !== $value) throw new InvalidArgumentException('Platnost kupónu musí mít formát RRRR-MM-DD HH:MM:SS.');
    return $value;
}

/** @return array<string,mixed> */
function shopCouponAuditSnapshot(array $coupon): array
{
    return array_intersect_key($coupon, array_flip(['id', 'code', 'discount_type', 'value_minor_or_basis_points', 'currency', 'minimum_order_minor', 'maximum_discount_minor', 'usage_limit_total', 'usage_count', 'valid_from', 'valid_until', 'active', 'applicability_mask']));
}
