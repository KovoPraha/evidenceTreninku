<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/shop_checkout.php';

function w92CartDetailBaseline(PDO $pdo, int $accountId): array
{
    $cart = shopCartGetOrCreate($pdo, $accountId);
    $beneficiarySelect = shopBeneficiaryColumnExists($pdo, 'shop_cart_items')
        ? 'ci.beneficiary_sportovec_id'
        : 'NULL AS beneficiary_sportovec_id';
    $paymentPolicy = shopPaymentPolicyProductSelect($pdo, 'p');
    $deliveryMode = shopCheckoutColumnExists($pdo, 'shop_products', 'delivery_mode') ? 'p.delivery_mode' : "'pickup_only'";
    $statement = $pdo->prepare(
        'SELECT ci.id AS cart_item_id,ci.quantity,'.
            $beneficiarySelect.
            ',p.id AS product_id,p.offer_type,'.
            shopPacketaProductSelect($pdo).
            ' AS packeta_shipping_enabled,'.
            $deliveryMode.
            ' AS delivery_mode,'.
            $paymentPolicy.
            ' AS payment_method_policy,pub.public_name,'
        . 'v.id AS variant_id,v.sku,v.attributes_json,v.amount_minor,v.currency,v.catalog_status '
        . 'FROM shop_cart_items ci JOIN shop_variants v ON v.id=ci.variant_id '
        . 'JOIN shop_products p ON p.id=v.product_id '
        . 'JOIN shop_product_publications pub ON pub.product_id=p.id WHERE ci.cart_id=? ORDER BY ci.id'
    );
    $statement->execute([(int)$cart['id']]);
    $items = $statement->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    $currency = null;
    foreach ($items as &$item) {
        shopMemberPriceApplyToItem($pdo, $accountId, $item);
        $item['line_amount_minor'] = (int)$item['amount_minor'] * (int)$item['quantity'];
        $total += $item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        $decoded = json_decode((string)$item['attributes_json'], true);
        $item['attributes'] = is_array($decoded) ? $decoded : [];
        $item['program_terms'] = null;
        $item['availability_error'] = null;
        if (($item['catalog_status'] ?? null) !== 'active') {
            $item['availability_error'] = 'Tato nabídka již není v prodeji. Odeberte ji z košíku a vyberte aktuální kroužek.';
            continue;
        }
        if (($item['offer_type'] ?? null) === 'program') {
            $state = clubProgramVariantSaleState($pdo, (int)$item['variant_id']);
            $offer = $state['offer'];
            if (!$state['saleable']) {
                $item['availability_error'] = $state['reason'];
            } elseif ((int)($item['beneficiary_sportovec_id'] ?? 0) < 1) {
                $item['availability_error'] = 'U kroužku není vybrané dítě nebo účastník.';
            }
            if ($offer) {
                $item['program_terms'] = clubProgramTermsEffective($pdo, (int)$offer['program_id'], (int)$offer['id']);
            }
        } elseif (shopProductRequiresAthlete($pdo, (int)$item['product_id'])) {
            $item['availability_error'] = 'Tato nabídka vyžaduje sportovce a řádně vypsaný termín. Odeberte ji z košíku a přejděte na detail nabídky.';
        }
    }
    unset($item);
    $eventItems = clubEventShopCartItems($pdo, (int)$cart['id']);
    foreach ($eventItems as $item) {
        $total += (int)$item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        if ($currency !== (string)$item['currency']) {
            throw new ShopCheckoutException('Cart currency mismatch.');
        }
    }
    $velodromeItems = publicVelodromeShopCartItems($pdo, (int)$cart['id']);
    foreach ($velodromeItems as $item) {
        $total += (int)$item['line_amount_minor'];
        $currency ??= (string)$item['currency'];
        if ($currency !== (string)$item['currency']) {
            throw new ShopCheckoutException('Jeden košík nesmí míchat různé měny.');
        }
    }
    $coupon = null;
    $couponError = null;
    $couponBreakdown = null;
    if ($cart['coupon_id'] !== null) {
        try {
            $couponBreakdown = shopCouponBreakdownFromItems($pdo, $items, $eventItems, $velodromeItems);
            $coupon = shopCouponQuoteById($pdo, (int)$cart['coupon_id'], $couponBreakdown);
        } catch (ShopCouponException $exception) {
            $couponError = $exception->getMessage();
        }
    }
    $discount = $coupon !== null ? (int)$coupon['discount_minor'] : 0;
    $paymentPolicy = shopPaymentPolicyForItemGroups($items, $eventItems, $velodromeItems);
    return ['cart' => $cart,
        'items' => $items,
        'event_items' => $eventItems,
        'velodrome_items' => $velodromeItems,
        'subtotal_minor' => $total,
        'discount_minor' => $discount,
        'total_minor' => $total - $discount,
        'currency' => $currency,
        'coupon' => $coupon,
        'coupon_error' => $couponError,
        'payment_method_policy' => $paymentPolicy,
        'fingerprint' => shopCartFingerprint(
            $items,
            $coupon,
            $velodromeItems,
            $eventItems
        )];
}
