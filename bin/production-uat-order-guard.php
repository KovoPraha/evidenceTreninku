<?php
declare(strict_types=1);

/** A narrowly scoped guard around one browser-submitted, unpaid TEST order. */
function kisUatOrderIdentity(PDO $pdo): array
{
    $account = $pdo->prepare('SELECT id FROM verejni_uzivatele WHERE LOWER(TRIM(email))=? AND jmeno=? AND prijmeni=? AND aktivni=1 AND email_overeno=1');
    $account->execute(['tester.karel@velocota.com', 'Tester', 'Karel']);
    $accounts = $account->fetchAll(PDO::FETCH_COLUMN);
    if (count($accounts) !== 1) throw new RuntimeException('TEST parent identity is not unique and active.');

    $child = $pdo->prepare("SELECT id FROM sportovci WHERE jmeno='Ema' AND prijmeni='Tester' AND narozeni='2018-04-12'");
    $child->execute();
    $children = $child->fetchAll(PDO::FETCH_COLUMN);
    if (count($children) !== 1) throw new RuntimeException('TEST child identity is not unique.');

    $variant = $pdo->prepare("SELECT v.id AS variant_id,p.id AS product_id FROM shop_variants v JOIN shop_products p ON p.id=v.product_id JOIN shop_product_publications pub ON pub.product_id=p.id JOIN club_program_offers o ON o.variant_id=v.id AND o.product_id=p.id JOIN club_programs cp ON cp.id=o.program_id JOIN club_teams t ON t.id=o.team_id WHERE v.sku='KP-TEST-UAT-KROUZEK' AND p.name='TEST - Cyklistický kroužek' AND p.offer_type='program' AND p.catalog_status='active' AND pub.status='active' AND v.catalog_status='active' AND v.visible=1 AND v.price_mode='fixed' AND v.amount_minor=10000 AND v.currency='CZK' AND o.status='active' AND cp.code='TEST-UAT-KROUZEK' AND t.code='TEST-UAT-DETI'");
    $variant->execute();
    $variants = $variant->fetchAll(PDO::FETCH_ASSOC);
    if (count($variants) !== 1) throw new RuntimeException('TEST program fixture is not unique and saleable at CZK 100.');

    $relation = $pdo->prepare("SELECT COUNT(*) FROM account_person_roles WHERE account_id=? AND sportovec_id=? AND relation_role='guardian' AND status='approved' AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)");
    $relation->execute([(int)$accounts[0], (int)$children[0]]);
    if ((int)$relation->fetchColumn() !== 1) throw new RuntimeException('TEST guardian link is not approved.');

    return ['account_id'=>(int)$accounts[0], 'child_id'=>(int)$children[0], 'product_id'=>(int)$variants[0]['product_id'], 'variant_id'=>(int)$variants[0]['variant_id']];
}

function kisUatOrderCandidates(PDO $pdo, array $identity, int $baseline): array
{
    $query = $pdo->prepare("SELECT o.id,o.status,o.payment_status,o.total_minor,o.currency,p.id AS payment_id,p.status AS payment_record_status,p.method,p.amount_minor AS payment_amount,COUNT(oi.id) AS item_count,MIN(oi.variant_id) AS variant_id,MIN(oi.beneficiary_sportovec_id) AS child_id,MIN(oi.quantity) AS quantity,MIN(oi.line_amount_minor) AS line_amount FROM shop_orders o JOIN payments p ON p.payable_type='shop_order' AND p.payable_id=o.id JOIN shop_order_items target ON target.order_id=o.id AND target.variant_id=? LEFT JOIN shop_order_items oi ON oi.order_id=o.id WHERE o.account_id=? AND o.id>? GROUP BY o.id,o.status,o.payment_status,o.total_minor,o.currency,p.id,p.status,p.method,p.amount_minor ORDER BY o.id");
    $query->execute([$identity['variant_id'], $identity['account_id'], $baseline]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function kisUatOrderGuard(PDO $pdo, string $mode, int $baseline): array
{
    $identity = kisUatOrderIdentity($pdo);
    if ($mode === 'baseline') {
        $existing = $pdo->prepare("SELECT COUNT(*) FROM shop_orders o JOIN shop_order_items oi ON oi.order_id=o.id WHERE o.account_id=? AND oi.variant_id=? AND o.status='placed' AND o.payment_status='pending'");
        $existing->execute([$identity['account_id'], $identity['variant_id']]);
        if ((int)$existing->fetchColumn() !== 0) throw new RuntimeException('An earlier TEST order is still pending; inspect it before a new run.');
        return ['ok'=>true, 'baseline'=>(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM shop_orders')->fetchColumn(), 'product_id'=>$identity['product_id'], 'variant_id'=>$identity['variant_id']];
    }
    if ($mode !== 'cleanup' || $baseline < 0) throw new InvalidArgumentException('Invalid UAT guard mode or baseline.');
    $rows = kisUatOrderCandidates($pdo, $identity, $baseline);
    if (count($rows) > 1) throw new RuntimeException('More than one new TEST parent order exists; no automatic cancellation.');
    if ($rows === []) return ['ok'=>true, 'matched'=>0, 'cancelled'=>0];
    $row = $rows[0];
    if ((int)$row['item_count'] !== 1 || (int)$row['variant_id'] !== $identity['variant_id'] || (int)$row['child_id'] !== $identity['child_id'] || (int)$row['quantity'] !== 1 || (int)$row['line_amount'] !== 10000 || (int)$row['total_minor'] !== 10000 || (string)$row['currency'] !== 'CZK' || (int)$row['payment_amount'] !== 10000 || (string)$row['method'] !== 'bank_transfer') {
        throw new RuntimeException('New order does not match the exact unpaid TEST fixture; no automatic cancellation.');
    }
    if ((string)$row['status'] === 'cancelled' && (string)$row['payment_status'] === 'cancelled' && (string)$row['payment_record_status'] === 'cancelled') {
        return ['ok'=>true, 'matched'=>1, 'cancelled'=>1, 'already_cancelled'=>true, 'order_id'=>(int)$row['id']];
    }
    if ((string)$row['status'] !== 'placed' || (string)$row['payment_status'] !== 'pending' || (string)$row['payment_record_status'] !== 'pending') {
        throw new RuntimeException('New TEST order is no longer unpaid and pending; no automatic cancellation.');
    }
    $admin = $pdo->prepare("SELECT id FROM treneri WHERE LOWER(TRIM(email))='tester.spravce@velocota.com' AND jmeno='Tester Správce' AND role='admin' AND aktivni=1");
    $admin->execute();
    $admins = $admin->fetchAll(PDO::FETCH_COLUMN);
    if (count($admins) !== 1) throw new RuntimeException('Dedicated TEST administrator is not unique and active.');
    require_once (realpath((string)getenv('APP_ROOT')) ?: dirname(__DIR__)) . '/includes/shop_checkout.php';
    $result = shopOrderAdminCancel($pdo, (int)$row['id'], (int)$admins[0], 'Ukončení neuhrazené objednávky vyhrazeného produkčního UAT.', true);
    if (($result['payment_status'] ?? null) !== 'cancelled' || !($result['changed'] ?? false)) throw new RuntimeException('TEST order cancellation did not complete.');
    return ['ok'=>true, 'matched'=>1, 'cancelled'=>1, 'order_id'=>(int)$row['id']];
}

function kisUatOrderGuardMain(): void
{
    if (PHP_SAPI !== 'cli' || (string)getenv('KIS_UAT_ORDER_GUARD_CONFIRM') !== 'OVERIT-A-UKLIDIT-TEST-OBJEDNAVKU') throw new RuntimeException('Missing guarded UAT confirmation.');
    $root = realpath((string)getenv('APP_ROOT'));
    $host = strtolower(trim((string)getenv('APP_HOST')));
    if ($root === false || $host !== 'kis.kovopraha.cz' || !is_file($root . '/config.php')) throw new RuntimeException('Production target is unavailable.');
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    require_once $root . '/config.php';
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) throw new RuntimeException('Target is not production.');
    $baseline = (string)getenv('KIS_UAT_ORDER_BASELINE');
    if (!preg_match('/^[0-9]{1,12}$/D', $baseline)) throw new InvalidArgumentException('Invalid order baseline.');
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(kisUatOrderGuard($pdo, (string)getenv('KIS_UAT_ORDER_MODE'), (int)$baseline), JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    try { kisUatOrderGuardMain(); } catch (Throwable $exception) { fwrite(STDERR, 'UAT order guard failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
}
