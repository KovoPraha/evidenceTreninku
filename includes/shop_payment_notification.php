<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';

final class ShopPaymentNotificationException extends RuntimeException
{
}

function shopPaymentNotificationTableExists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[a-z0-9_]+$/D', $table) !== 1) {
        throw new InvalidArgumentException('Neplatný název tabulky oznámení.');
    }
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1"
    );
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
}

function shopPaymentNotificationMoney(int $amountMinor, string $currency): string
{
    $currency = strtoupper(trim($currency));
    if ($amountMinor < 0 || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
        throw new ShopPaymentNotificationException('Oznámení má neplatnou částku nebo měnu.');
    }
    return number_format($amountMinor / 100, 2, ',', ' ') . ' ' . $currency;
}

/** @return list<array{label:string,quantity:int,next:string}> */
function shopPaymentNotificationItems(PDO $pdo, int $orderId): array
{
    $items = [];
    $programVariants = [];
    if (shopPaymentNotificationTableExists($pdo, 'club_program_offers')) {
        $statement = $pdo->prepare('SELECT variant_id FROM club_program_offers');
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $variantId) {
            $programVariants[(int)$variantId] = true;
        }
    }
    if (shopPaymentNotificationTableExists($pdo, 'shop_order_items')) {
        $statement = $pdo->prepare(
            'SELECT variant_id,product_name_snapshot,quantity FROM shop_order_items '
            . 'WHERE order_id=? ORDER BY id'
        );
        $statement->execute([$orderId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $program = isset($programVariants[(int)$row['variant_id']]);
            $items[] = [
                'label' => trim((string)$row['product_name_snapshot']),
                'quantity' => max(1, (int)$row['quantity']),
                'next' => $program ? 'program' : 'pickup',
            ];
        }
    }
    if (shopPaymentNotificationTableExists($pdo, 'club_event_order_items')) {
        $statement = $pdo->prepare(
            'SELECT event_name_snapshot,quantity FROM club_event_order_items WHERE order_id=? ORDER BY id'
        );
        $statement->execute([$orderId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'label' => trim((string)$row['event_name_snapshot']),
                'quantity' => max(1, (int)$row['quantity']),
                'next' => 'reservation',
            ];
        }
    }
    if (shopPaymentNotificationTableExists($pdo, 'public_velodrome_order_items')) {
        $statement = $pdo->prepare(
            'SELECT lesson_name_snapshot,lesson_date_snapshot,starts_at_snapshot,quantity '
            . 'FROM public_velodrome_order_items WHERE order_id=? ORDER BY id'
        );
        $statement->execute([$orderId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $when = trim((string)$row['lesson_date_snapshot'] . ' ' . substr((string)$row['starts_at_snapshot'], 0, 5));
            $items[] = [
                'label' => trim((string)$row['lesson_name_snapshot']) . ($when !== '' ? ' – ' . $when : ''),
                'quantity' => max(1, (int)$row['quantity']),
                'next' => 'reservation',
            ];
        }
    }
    if ($items === []) {
        throw new ShopPaymentNotificationException('Zaplacená objednávka nemá položky pro oznámení.');
    }
    return $items;
}

/** @param list<array{label:string,quantity:int,next:string}> $items */
function shopPaymentNotificationBody(array $order, array $items): string
{
    $paidAt = new DateTimeImmutable((string)$order['paid_at']);
    $lines = [];
    foreach ($items as $item) {
        $label = trim($item['label']);
        if ($label === '') {
            throw new ShopPaymentNotificationException('Položka oznámení nemá bezpečný název.');
        }
        $lines[] = '- ' . $label . ' × ' . $item['quantity'];
    }
    $next = array_values(array_unique(array_column($items, 'next')));
    $nextLines = [];
    if (in_array('pickup', $next, true)) {
        $nextLines[] = '- Zboží klub připraví k osobnímu odběru. Až bude připravené, stav uvidíte v Moje objednávky.';
    }
    if (in_array('program', $next, true)) {
        $nextLines[] = '- Kroužek byl po přijetí platby aktivován; aktuální stav najdete ve svém účtu.';
    }
    if (in_array('reservation', $next, true)) {
        $nextLines[] = '- Přihláška nebo rezervace byla po přijetí platby potvrzena.';
    }
    $ordersUrl = appUrl('booking/prihlaseni.php?redirect=moje_objednavky.php');
    return "Dobrý den,\n\n"
        . 'potvrzujeme přijetí platby za objednávku ' . (string)$order['public_code'] . ".\n\n"
        . 'Datum přijetí platby: ' . $paidAt->format('d. m. Y H:i') . "\n"
        . 'Přijatá částka: ' . shopPaymentNotificationMoney((int)$order['total_minor'], (string)$order['currency']) . "\n\n"
        . "Položky objednávky:\n" . implode("\n", $lines) . "\n\n"
        . "Co se děje dál:\n" . implode("\n", $nextLines) . "\n\n"
        . "Stav objednávky najdete po přihlášení v Moje objednávky:\n" . $ordersUrl . "\n\n"
        . 'Klub KOVO Praha';
}

function shopPaymentNotificationEnqueue(PDO $pdo, int $orderId): int
{
    if (!$pdo->inTransaction() || $orderId < 1) {
        throw new LogicException('Oznámení o přijaté platbě vyžaduje aktivní transakci objednávky.');
    }
    $statement = $pdo->prepare(
        'SELECT o.id,o.public_code,o.customer_email_snapshot,o.customer_name_snapshot,o.total_minor,o.currency,'
        . 'p.paid_at FROM shop_orders o JOIN payments p ON p.payable_type=\'shop_order\' '
        . 'AND p.payable_id=o.id AND p.status=\'paid\' '
        . 'WHERE o.id=? AND o.payment_status=\'paid\' AND o.status IN (\'processing\',\'ready\',\'completed\')'
    );
    $statement->execute([$orderId]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$order || !filter_var((string)$order['customer_email_snapshot'], FILTER_VALIDATE_EMAIL)) {
        throw new ShopPaymentNotificationException('Zaplacenou objednávku nelze zařadit k oznámení.');
    }
    $items = shopPaymentNotificationItems($pdo, $orderId);
    $subject = 'Platba přijata – objednávka ' . (string)$order['public_code'];
    $body = shopPaymentNotificationBody($order, $items);
    try {
        $insert = $pdo->prepare(
            'INSERT INTO club_event_notifications '
            . '(registration_id,registration_event_id,order_id,notification_type,recipient_email,'
            . 'recipient_name,subject_plain,body_plain) VALUES (NULL,NULL,?,\'shop_payment_received\',?,?,?,?)'
        );
        $insert->execute([
            $orderId,
            (string)$order['customer_email_snapshot'],
            trim((string)$order['customer_name_snapshot']),
            $subject,
            $body,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') {
            throw $exception;
        }
        $existing = $pdo->prepare(
            "SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type='shop_payment_received'"
        );
        $existing->execute([$orderId]);
        $id = (int)$existing->fetchColumn();
        if ($id < 1) {
            throw $exception;
        }
        return $id;
    }
}
