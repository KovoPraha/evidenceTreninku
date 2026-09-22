<?php
declare(strict_types=1);

require_once __DIR__ . '/shop_checkout.php';
require_once __DIR__ . '/app_url.php';

/** @param array<string,mixed> $customer @return array<string,string|null> */
function shopGuestCustomerValidate(array $customer): array
{
    $firstName = trim((string)($customer['first_name'] ?? ''));
    $lastName = trim((string)($customer['last_name'] ?? ''));
    $email = strtolower(trim((string)($customer['email'] ?? '')));
    $phone = trim((string)($customer['phone'] ?? ''));
    $street = trim((string)($customer['address_street'] ?? ''));
    $city = trim((string)($customer['address_city'] ?? ''));
    $postcode = trim((string)($customer['address_postcode'] ?? ''));
    if ($firstName === '' || mb_strlen($firstName, 'UTF-8') > 100) throw new InvalidArgumentException('Zadejte platné jméno.');
    if ($lastName === '' || mb_strlen($lastName, 'UTF-8') > 100) throw new InvalidArgumentException('Zadejte platné příjmení.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 254) throw new InvalidArgumentException('Zadejte platný e-mail.');
    if (mb_strlen($phone, 'UTF-8') > 50) throw new InvalidArgumentException('Telefon je příliš dlouhý.');
    if (mb_strlen($street, 'UTF-8') > 200 || mb_strlen($city, 'UTF-8') > 100 || mb_strlen($postcode, 'UTF-8') > 20) {
        throw new InvalidArgumentException('Adresa je příliš dlouhá.');
    }
    $hasAddress = $street !== '' || $city !== '' || $postcode !== '';
    if ($hasAddress && ($street === '' || $city === '' || $postcode === '')) {
        throw new InvalidArgumentException('Pokud adresu vyplníte, doplňte ulici, obec i PSČ.');
    }
    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'address_street' => $street !== '' ? $street : null,
        'address_city' => $city !== '' ? $city : null,
        'address_postcode' => $postcode !== '' ? $postcode : null,
    ];
}

/** @return array<string,mixed> */
function shopGuestOrderByCode(PDO $pdo, string $publicCode, string $accessToken): array
{
    if (preg_match('/^KP[A-Z0-9]{10,20}$/D', $publicCode) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $accessToken) !== 1) {
        throw new ShopCheckoutException('Objednávka nebyla nalezena.');
    }
    $paymentPolicy = shopPaymentPolicyPaymentSelect($pdo, 'p');
    $statement = $pdo->prepare(
        'SELECT o.*,p.id AS payment_id,p.method,' . $paymentPolicy . ' AS accepted_payment_methods,'
        . 'p.status AS payment_record_status,p.variable_symbol,p.iban_snapshot,p.bic_snapshot,'
        . 'p.account_label_snapshot,p.spd_payload,p.due_at,p.paid_at,p.refund_sent_at,p.refund_reference,'
        . 'NULL AS coupon_code_snapshot,0 AS coupon_discount_minor '
        . "FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id "
        . "WHERE o.public_code=? AND o.checkout_mode='guest' AND o.guest_access_token_hash=?"
    );
    $statement->execute([$publicCode, hash('sha256', $accessToken)]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new ShopCheckoutException('Objednávka nebyla nalezena.');
    $items = $pdo->prepare('SELECT * FROM shop_order_items WHERE order_id=? ORDER BY id');
    $items->execute([(int)$order['id']]);
    $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
    $order['event_items'] = [];
    $order['velodrome_items'] = [];
    return $order;
}

/** @param array{iban:string,bic:string,account_label:string,due_days:int} $bank @param array<string,mixed> $customer @return array<string,mixed> */
function shopGuestCheckoutPlace(
    PDO $pdo,
    int $variantId,
    int $quantity,
    array $customer,
    string $idempotencyKey,
    string $accessToken,
    array $bank
): array {
    if ($variantId < 1 || $quantity < 1 || $quantity > 99
        || preg_match('/^[a-f0-9]{32}$/D', $idempotencyKey) !== 1
        || preg_match('/^[a-f0-9]{64}$/D', $accessToken) !== 1
    ) {
        throw new InvalidArgumentException('Rychlý nákup má neplatné vstupní údaje.');
    }
    $customer = shopGuestCustomerValidate($customer);
    $bank = shopBankValidateSettings($bank);
    $keyHash = hash('sha256', 'guest:' . $idempotencyKey);
    $tokenHash = hash('sha256', $accessToken);
    $checkoutLockName = null;
    $orderId = 0;
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $checkoutLockName = 'shop_guest:' . substr($keyHash, 0, 48);
        $lock = $pdo->prepare('SELECT GET_LOCK(?,5)');
        $lock->execute([$checkoutLockName]);
        if ((int)$lock->fetchColumn() !== 1) throw new ShopCheckoutException('Objednávku právě zpracovává jiný požadavek. Zkuste to za okamžik.');
    }
    try {
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare("SELECT public_code FROM shop_orders WHERE idempotency_key_hash=? AND checkout_mode='guest'");
            $existing->execute([$keyHash]);
            $existingCode = $existing->fetchColumn();
            if ($existingCode !== false) {
                $pdo->commit();
                return shopGuestOrderByCode($pdo, (string)$existingCode, $accessToken) + ['replayed' => true];
            }
            $variant = shopCheckoutLockVariant($pdo, $variantId);
            if (!$variant || ($variant['offer_type'] ?? null) !== 'goods' || !shopCheckoutVariantIsSaleable($variant, $pdo, null, true)) {
                throw new ShopCheckoutException('Tuto položku nelze koupit bez registrace.');
            }
            $unit = (int)$variant['amount_minor'];
            $currency = (string)$variant['currency'];
            if ($unit < 1 || $currency !== 'CZK' || $unit > intdiv(PHP_INT_MAX, $quantity)) {
                throw new ShopCheckoutException('Položka má nepodporovanou cenu.');
            }
            $total = $unit * $quantity;
            $publicCode = 'KP' . date('ymd') . strtoupper(bin2hex(random_bytes(5)));
            $dueAt = (new DateTimeImmutable('now +' . $bank['due_days'] . ' days'))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $columns = 'public_code,account_id,source_cart_id,checkout_mode,guest_access_token_hash,idempotency_key_hash,status,payment_status,fulfillment_method,customer_name_snapshot,customer_email_snapshot,customer_phone_snapshot,address_street_snapshot,address_city_snapshot,address_postcode_snapshot,subtotal_minor,discount_minor,total_minor,currency,placed_at';
            $values = [$publicCode, null, null, 'guest', $tokenHash, $keyHash, trim($customer['first_name'] . ' ' . $customer['last_name']), $customer['email'], $customer['phone'], $customer['address_street'], $customer['address_city'], $customer['address_postcode'], $total, 0, $total, $currency];
            if (shopOrderExpirationAvailable($pdo)) {
                $insert = $pdo->prepare('INSERT INTO shop_orders(' . $columns . ',payment_expires_at) '
                    . "VALUES (?,?,?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?)");
                $values[] = $dueAt;
            } else {
                $insert = $pdo->prepare('INSERT INTO shop_orders(' . $columns . ') '
                    . "VALUES (?,?,?,?,?,?,'placed','pending','personal_pickup',?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)");
            }
            $insert->execute($values);
            $orderId = (int)$pdo->lastInsertId();
            $managedStock = $variant['stock_quantity_decimal'] !== null;
            if ($managedStock) {
                $reserve = $pdo->prepare('UPDATE shop_variants SET stock_quantity_decimal=stock_quantity_decimal-?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND stock_quantity_decimal>=?');
                $reserve->execute([$quantity, $variantId, $quantity]);
                if ($reserve->rowCount() !== 1) throw new ShopCheckoutException('Vybraná varianta se mezitím vyprodala.');
            }
            $item = $pdo->prepare('INSERT INTO shop_order_items(order_id,product_id,variant_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,unit_amount_minor,line_amount_minor,currency,includes_vat_snapshot,vat_rate_basis_points_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $item->execute([$orderId, (int)$variant['product_id'], $variantId, (string)$variant['public_name'], (string)$variant['sku'], (string)$variant['attributes_json'], $quantity, $unit, $total, $currency, $variant['includes_vat'], $variant['vat_rate_basis_points']]);
            $orderItemId = (int)$pdo->lastInsertId();
            if ($managedStock) {
                $stock = $pdo->prepare('SELECT stock_quantity_decimal FROM shop_variants WHERE id=?');
                $stock->execute([$variantId]);
                $pdo->prepare("INSERT INTO shop_inventory_movements(variant_id,order_id,order_item_id,movement_type,quantity_delta_decimal,stock_after_decimal) VALUES (?,?,?,'reserve',?,?)")
                    ->execute([$variantId, $orderId, $orderItemId, (string)(-$quantity), (string)$stock->fetchColumn()]);
            }
            $variableSymbol = shopPaymentVariableSymbol($orderId);
            $spd = shopPaymentSpdPayload($bank['iban'], $total, $currency, $variableSymbol, 'OBJEDNAVKA ' . $publicCode);
            if (shopPaymentPolicyColumnExists($pdo, 'payments', 'accepted_payment_methods')) {
                $pdo->prepare("INSERT INTO payments(payable_type,payable_id,method,accepted_payment_methods,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) VALUES ('shop_order',?,'bank_transfer',?,'pending',?,?,?,?,?,?,?,?)")
                    ->execute([$orderId, SHOP_PAYMENT_POLICY_BANK_ONLY, $total, $currency, $variableSymbol, $bank['iban'], $bank['bic'] !== '' ? $bank['bic'] : null, $bank['account_label'], $spd, $dueAt]);
            } else {
                $pdo->prepare("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,iban_snapshot,bic_snapshot,account_label_snapshot,spd_payload,due_at) VALUES ('shop_order',?,'bank_transfer','pending',?,?,?,?,?,?,?,?)")
                    ->execute([$orderId, $total, $currency, $variableSymbol, $bank['iban'], $bank['bic'] !== '' ? $bank['bic'] : null, $bank['account_label'], $spd, $dueAt]);
            }
            $pdo->prepare("INSERT INTO shop_order_events(order_id,actor_type,actor_id,action,from_status,to_status,note) VALUES (?,'guest',NULL,'place',NULL,'placed','Objednávka vytvořena rychlým nákupem bez účtu.')")
                ->execute([$orderId]);
            if (shopPaymentNotificationTableExists($pdo, 'club_event_notifications')) {
                $orderUrl = appUrl('booking/objednavka.php?code=' . rawurlencode($publicCode) . '&access=' . rawurlencode($accessToken));
                $subject = 'Objednávka ' . $publicCode . ' – platební údaje';
                $body = "Dobrý den,\n\nobjednávka {$publicCode} byla přijata.\n"
                    . 'Položka: ' . (string)$variant['public_name'] . ' × ' . $quantity . "\n"
                    . 'Částka: ' . number_format($total / 100, 2, ',', ' ') . " CZK\n"
                    . 'Variabilní symbol: ' . $variableSymbol . "\n"
                    . 'Splatnost: ' . $dueAt . "\n\n"
                    . "Bezpečný odkaz na objednávku a QR platbu:\n{$orderUrl}\n\nKlub KOVO Praha";
                $pdo->prepare('INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES (NULL,NULL,?,\'shop_guest_order_placed\',?,?,?,?)')
                    ->execute([$orderId, $customer['email'], trim($customer['first_name'] . ' ' . $customer['last_name']), $subject, $body]);
            }
            $pdo->commit();
            return shopGuestOrderByCode($pdo, $publicCode, $accessToken) + ['replayed' => false];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($exception instanceof InvalidArgumentException || $exception instanceof ShopCheckoutException) throw $exception;
            $reference = shopCheckoutDiagnosticReference($keyHash, $orderId);
            error_log('shop_guest_checkout failed: ' . $reference . ' ' . shopCheckoutDiagnosticTrace($exception));
            throw new ShopCheckoutException('Objednávku se nepodařilo vytvořit bez částečného zápisu.', 0, $exception);
        }
    } finally {
        if ($checkoutLockName !== null) {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$checkoutLockName]);
            } catch (Throwable $releaseError) {
                error_log('shop_guest_checkout lock release: ' . get_class($releaseError));
            }
        }
    }
}
