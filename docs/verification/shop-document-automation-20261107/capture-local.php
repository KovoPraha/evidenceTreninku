<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 3).'/config.php';
if (DB_HOST !== '127.0.0.1;port=33427' || DB_NAME !== 'evidence_b1' || !JE_LOKALNE) {
    throw new RuntimeException(
        'Isolated fixture only.'
    );
}
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$identity = $pdo->query('SELECT @@port port,@@datadir datadir')->fetch();
if ((int)$identity['port'] !== 33427 || strtolower(str_replace('\\', '/', $identity['datadir'])) !== 'c:/xampp/tmp/course-uat-20261107-db/'
) {
    throw new RuntimeException('Wrong database.');
}
require_once dirname(__DIR__, 3).'/includes/shop_checkout.php';
require_once dirname(__DIR__, 3).'/includes/shop_document_automation.php';
$q = $pdo->prepare((('SELECT o.id,p.id payment_id,o.customer_email_snapshot FROM shop_orders o JOIN payments p ON ' .
    'p.payable_type=\'shop_order\' AND ') .
    'p.payable_id=o.id WHERE o.public_code=?'));
$q->execute(['KP261107BE5C4A205A']);
$order = $q->fetch();
if (!$order || $order['customer_email_snapshot'] !== 'rodic@localhost.test') {
    throw new RuntimeException(
        'Only the observed synthetic browser order.'
    );
}
$id = (int)$order['id'];
$actor = (int)shopDocumentPolicy($pdo)['approved_by'];
AccountingDocumentWorker::assertMandate($pdo, $actor);
$phase = $argv[1] ?? 'placed';
switch ($phase) {
    case 'placed': break;
    case 'paid': shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], $actor, 'LOCALHOST syntetické potvrzení pro UAT dokladů.', true);
        break;
    case 'cancelled': shopOrderAdminCancel($pdo, $id, $actor, 'LOCALHOST storno pro UAT dokladů.', true);
        break;
    case 'refunded': shopOrderAdminConfirmRefund($pdo, $id, $actor, 'LOCALHOST-DOC-REFUND', 'LOCALHOST potvrzená syntetická vratka.', true);
        break;
    default: throw new RuntimeException('Unknown phase.');
}
$captured = [];
$sender = static function (string $email, string $subject, string $body, array $files = []) use (&$captured): bool {
    if ($email !== 'rodic@localhost.test') {
        throw new RuntimeException('Unexpected recipient.');
    }
    $attachments = [];
    foreach ($files as $file) {
        if (!preg_match('/^doklad-[0-9]+\.pdf$/D', $file['name'])) {
            throw new RuntimeException('Unsafe filename.');
        }
        file_put_contents(__DIR__.'/'.$file['name'], $file['bytes']);
        $attachments[] = ['name' => $file['name'],'sha256' => hash('sha256', $file['bytes']),'bytes' => strlen($file['bytes'])];
    }
    $captured[] = compact('email', 'subject', 'body', 'attachments');
    return true;
};
$result = shopDocumentDrain($pdo, $sender);
$again = shopDocumentDrain($pdo, $sender);
$out = ['synthetic_only' => true,'phase' => $phase,'checked_at' => date(DATE_ATOM),'worker' => $result,'repeat' => $again,'captured_mail'
    => $captured];
foreach ([
    'order' => 'SELECT id,status,payment_status,total_minor FROM shop_orders WHERE id='.$id,
    'documents' => ('SELECT id,kind,status,number,total_minor,pdf_sha256 FROM accounting_documents WHERE ' .
    'source_type=\'order\' AND source_id=')
    .$id,
    'notifications' => 'SELECT notification_type,status,attempts FROM club_event_notifications WHERE order_id='.$id,
    'enrollments' => ('SELECT e.id,e.sportovec_id,e.status FROM club_program_enrollments e JOIN shop_order_items i ON ' .
    'i.id=e.source_order_item_id WHERE i.order_id=')
    .$id,
    'monthly_count' => 'SELECT COUNT(*) total FROM monthly_memberships',
] as $key => $sql) {
    $out[$key] = $pdo->query($sql)->fetchAll();
}
file_put_contents(__DIR__.'/'.$phase.'.json', json_encode(
    $out,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
));
echo json_encode(['phase' => $phase,'worker' => $result,'repeat' => $again,'documents' => $out['documents'],'notifications' => $out
    ['notifications']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
if ($result['failed'] || $result['attention'] || $again['documents'] || $again['sent'] || $again['failed']) {
    exit(1);
}
