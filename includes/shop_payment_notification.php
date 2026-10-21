<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';
require_once __DIR__ . '/shop_system_email.php';

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

function shopPaymentNotificationColumnExists(PDO $pdo, string $table, string $column): bool
{
    if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $statement->execute([$table, $column]);
        return (bool)$statement->fetchColumn();
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((string)$row['name'] === $column) return true;
    }
    return false;
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
        $pendingIdentitySelect=shopPaymentNotificationColumnExists($pdo,'shop_order_items','athlete_registration_request_id')
            ? ',athlete_registration_request_id,beneficiary_sportovec_id'
            : ',NULL AS athlete_registration_request_id,NULL AS beneficiary_sportovec_id';
        $statement = $pdo->prepare(
            'SELECT variant_id,product_name_snapshot,quantity'.$pendingIdentitySelect.' FROM shop_order_items '
            . 'WHERE order_id=? ORDER BY id'
        );
        $statement->execute([$orderId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $program = isset($programVariants[(int)$row['variant_id']]);
            $items[] = [
                'label' => trim((string)$row['product_name_snapshot']),
                'quantity' => max(1, (int)$row['quantity']),
                'next' => $program
                    ? (((int)($row['athlete_registration_request_id']??0)>0&&$row['beneficiary_sportovec_id']===null)?'program_pending_identity':'program')
                    : 'pickup',
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
function shopPaymentNotificationValues(array $order, array $items): array
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
        if (($order['fulfillment_method']??'')==='ppl_box') {
            $nextLines[] = '- Zboží klub připraví k odeslání do PPL Parcelboxu '.(string)$order['ppl_access_point_name'].' ('.(string)$order['ppl_access_point_code'].'). Stav uvidíte v detailu objednávky.';
        } elseif (($order['fulfillment_method']??'')==='packeta_zbox') {
            $nextLines[] = '- Zboží klub připraví k odeslání do Z-BOXu '.(string)$order['packeta_point_name'].' ('.(string)$order['packeta_point_id'].'). Stav uvidíte v detailu objednávky.';
        } else {
            $nextLines[] = '- Zboží klub připraví k osobnímu odběru. Až bude připravené, stav uvidíte v Moje objednávky.';
        }
    }
    if (in_array('program', $next, true)) {
        $nextLines[] = '- Kroužek byl po přijetí platby aktivován; aktuální stav najdete ve svém účtu.';
    }
    if (in_array('program_pending_identity', $next, true)) {
        $nextLines[] = '- Platbu evidujeme. Údaje sportovce nyní zkontroluje klub; místo zůstává rezervované.';
    }
    if (in_array('reservation', $next, true)) {
        $nextLines[] = '- Přihláška nebo rezervace byla po přijetí platby potvrzena.';
    }
    $ordersUrl = appUrl('booking/prihlaseni.php?redirect=moje_objednavky.php');
    $statusInstruction = in_array(($order['checkout_mode']??'account'),['guest','quick_program'],true)
        ? 'Stav objednávky otevřete bezpečným odkazem z původního e-mailu s platebními údaji.'
        : "Stav objednávky najdete po přihlášení v Moje objednávky:\n" . $ordersUrl;
    return ['order_code'=>(string)$order['public_code'],'customer_name'=>(string)($order['customer_name_snapshot']??''),
        'paid_at'=>$paidAt->format('d. m. Y H:i'),'amount'=>shopPaymentNotificationMoney((int)$order['total_minor'],(string)$order['currency']),
        'items'=>implode("\n",$lines),'next_steps'=>implode("\n",$nextLines),'status_instruction'=>$statusInstruction];
}

function shopPaymentNotificationBody(array $order, array $items): string
{
    $definition=shopSystemEmailCatalog()['shop_payment_received'];
    return shopSystemEmailRender(['template_key'=>'shop_payment_received','version'=>0,'subject_plain'=>$definition['subject'],'body_plain'=>$definition['body']],shopPaymentNotificationValues($order,$items))['body'];
}

function shopPaymentNotificationEnqueue(PDO $pdo, int $orderId): int
{
    if (!$pdo->inTransaction() || $orderId < 1) {
        throw new LogicException('Oznámení o přijaté platbě vyžaduje aktivní transakci objednávky.');
    }
    $checkoutModeSelect = shopPaymentNotificationColumnExists($pdo, 'shop_orders', 'checkout_mode')
        ? 'o.checkout_mode'
        : "'account' AS checkout_mode";
    $pplSelect=shopPaymentNotificationColumnExists($pdo,'shop_orders','ppl_access_point_code')
        ? 'o.fulfillment_method,o.ppl_access_point_code,o.ppl_access_point_name,'
        : "'personal_pickup' AS fulfillment_method,NULL AS ppl_access_point_code,NULL AS ppl_access_point_name,";
    $packetaSelect=shopPaymentNotificationColumnExists($pdo,'shop_orders','packeta_point_id')?'o.packeta_point_id,o.packeta_point_name,':'NULL AS packeta_point_id,NULL AS packeta_point_name,';
    $statement = $pdo->prepare(
        'SELECT o.id,o.public_code,' . $checkoutModeSelect . ','.$pplSelect.$packetaSelect.'o.customer_email_snapshot,o.customer_name_snapshot,o.total_minor,o.currency,'
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
    $message = shopSystemEmailMessage($pdo,'shop_payment_received',shopPaymentNotificationValues($order,$items));
    $subject = $message['subject']; $body = $message['body'];
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
        $id=(int)$pdo->lastInsertId();shopSystemEmailRecord($pdo,$id,$message);return $id;
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

function shopOrderReadyNotificationEnqueue(PDO $pdo, int $orderId): int
{
    if (!$pdo->inTransaction() || $orderId < 1) throw new LogicException('Oznámení o připravenosti vyžaduje aktivní transakci objednávky.');
    $statement = $pdo->prepare("SELECT id,public_code,customer_email_snapshot,customer_name_snapshot FROM shop_orders WHERE id=? AND status='ready'");
    $statement->execute([$orderId]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$order || !filter_var((string)$order['customer_email_snapshot'], FILTER_VALIDATE_EMAIL)) {
        throw new ShopPaymentNotificationException('Připravenou objednávku nelze zařadit k oznámení.');
    }
    $items = shopPaymentNotificationItems($pdo, $orderId);
    $lines = [];
    foreach ($items as $item) $lines[] = '- ' . $item['label'] . ' × ' . $item['quantity'];
    $message=shopSystemEmailMessage($pdo,'shop_order_ready',['order_code'=>$order['public_code'],'customer_name'=>$order['customer_name_snapshot'],'items'=>implode("\n",$lines)]);
    $subject=$message['subject'];$body=$message['body'];
    try {
        $insert = $pdo->prepare(
            'INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) '
            . "VALUES (NULL,NULL,?,'shop_order_ready',?,?,?,?)"
        );
        $insert->execute([$orderId, (string)$order['customer_email_snapshot'], trim((string)$order['customer_name_snapshot']), $subject, $body]);
        $id=(int)$pdo->lastInsertId();shopSystemEmailRecord($pdo,$id,$message);return $id;
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') throw $exception;
        $existing = $pdo->prepare("SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type='shop_order_ready'");
        $existing->execute([$orderId]);
        $id = (int)$existing->fetchColumn();
        if ($id < 1) throw $exception;
        return $id;
    }
}

function shopPplDispatchedNotificationEnqueue(PDO $pdo,int $orderId):int
{
    if(!$pdo->inTransaction()||$orderId<1)throw new LogicException('Oznámení o předání PPL vyžaduje aktivní transakci.');
    $statement=$pdo->prepare("SELECT public_code,customer_email_snapshot,customer_name_snapshot,ppl_access_point_code,ppl_access_point_name,ppl_access_point_address,ppl_tracking_number FROM shop_orders WHERE id=? AND fulfillment_method='ppl_box' AND status='completed'");
    $statement->execute([$orderId]);$order=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$order||!filter_var((string)$order['customer_email_snapshot'],FILTER_VALIDATE_EMAIL)||trim((string)$order['ppl_tracking_number'])==='')throw new ShopPaymentNotificationException('Odeslanou PPL objednávku nelze zařadit k oznámení.');
    $message=shopSystemEmailMessage($pdo,'shop_ppl_dispatched',['order_code'=>$order['public_code'],'customer_name'=>$order['customer_name_snapshot'],'tracking_number'=>$order['ppl_tracking_number'],'point_name'=>$order['ppl_access_point_name'],'point_code'=>$order['ppl_access_point_code'],'point_address'=>$order['ppl_access_point_address']]);
    $subject=$message['subject'];$body=$message['body'];
    try{
        $insert=$pdo->prepare("INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES (NULL,NULL,?,'shop_ppl_dispatched',?,?,?,?)");
        $insert->execute([$orderId,$order['customer_email_snapshot'],$order['customer_name_snapshot'],$subject,$body]);
        $id=(int)$pdo->lastInsertId();shopSystemEmailRecord($pdo,$id,$message);return $id;
    }catch(PDOException $error){
        if((string)$error->getCode()!=='23000')throw $error;
        $existing=$pdo->prepare("SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type='shop_ppl_dispatched'");$existing->execute([$orderId]);$id=(int)$existing->fetchColumn();
        if($id<1)throw $error;return $id;
    }
}

function shopPacketaDispatchedNotificationEnqueue(PDO $pdo,int $orderId):int
{
    if(!$pdo->inTransaction()||$orderId<1)throw new LogicException('Oznámení o podání Zásilkovny vyžaduje aktivní transakci.');
    $statement=$pdo->prepare("SELECT public_code,customer_email_snapshot,customer_name_snapshot,packeta_point_id,packeta_point_name,packeta_point_address,packeta_tracking_number FROM shop_orders WHERE id=? AND fulfillment_method='packeta_zbox' AND status='completed' AND packeta_deposited_at IS NOT NULL");$statement->execute([$orderId]);$order=$statement->fetch(PDO::FETCH_ASSOC);
    if(!$order||!filter_var((string)$order['customer_email_snapshot'],FILTER_VALIDATE_EMAIL)||!preg_match('/^Z[0-9]{10}$/D',(string)$order['packeta_tracking_number']))throw new ShopPaymentNotificationException('Podanou zásilku Zásilkovny nelze zařadit k oznámení.');
    $message=shopSystemEmailMessage($pdo,'shop_packeta_dispatched',['order_code'=>$order['public_code'],'customer_name'=>$order['customer_name_snapshot'],'tracking_number'=>$order['packeta_tracking_number'],'point_name'=>$order['packeta_point_name'],'point_code'=>$order['packeta_point_id'],'point_address'=>$order['packeta_point_address']]);
    $subject=$message['subject'];$body=$message['body'];
    try{$insert=$pdo->prepare("INSERT INTO club_event_notifications(registration_id,registration_event_id,order_id,notification_type,recipient_email,recipient_name,subject_plain,body_plain) VALUES (NULL,NULL,?,'shop_packeta_dispatched',?,?,?,?)");$insert->execute([$orderId,$order['customer_email_snapshot'],$order['customer_name_snapshot'],$subject,$body]);$id=(int)$pdo->lastInsertId();shopSystemEmailRecord($pdo,$id,$message);return $id;}
    catch(PDOException $error){if((string)$error->getCode()!=='23000')throw$error;$existing=$pdo->prepare("SELECT id FROM club_event_notifications WHERE order_id=? AND notification_type='shop_packeta_dispatched'");$existing->execute([$orderId]);$id=(int)$existing->fetchColumn();if($id<1)throw$error;return$id;}
}
