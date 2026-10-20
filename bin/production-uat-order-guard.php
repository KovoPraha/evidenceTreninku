<?php
declare(strict_types=1);

/** A narrowly scoped guard around one browser-submitted, unpaid TEST order. */
final class KisUatOrderGuardFailure extends RuntimeException
{
    public function __construct(public readonly string $category)
    {
        parent::__construct($category);
    }
}

function kisUatOrderIdentity(PDO $pdo): array
{
    $account = $pdo->prepare('SELECT id,jmeno,prijmeni,aktivni,email_overeno FROM verejni_uzivatele WHERE LOWER(TRIM(email))=?');
    $account->execute(['tester.karel@velocota.com']);
    $accounts = $account->fetchAll(PDO::FETCH_ASSOC);
    if (count($accounts) !== 1) throw new KisUatOrderGuardFailure('parent_email_unique');
    if ((string)$accounts[0]['jmeno'] !== 'Tester' || (string)$accounts[0]['prijmeni'] !== 'Karel') throw new KisUatOrderGuardFailure('parent_name');
    if ((int)$accounts[0]['aktivni'] !== 1 || (int)$accounts[0]['email_overeno'] !== 1) throw new KisUatOrderGuardFailure('parent_active_verified');

    $child = $pdo->prepare("SELECT id FROM sportovci WHERE jmeno='Ema' AND prijmeni='Tester' AND narozeni='2018-04-12'");
    $child->execute();
    $children = $child->fetchAll(PDO::FETCH_COLUMN);
    if (count($children) !== 1) throw new KisUatOrderGuardFailure('child_identity_unique');

    $variant = $pdo->prepare("SELECT v.id AS variant_id,p.id AS product_id,p.name AS product_name,p.offer_type,p.catalog_status AS product_status,pub.status AS publication_status,v.catalog_status AS variant_status,v.visible,v.price_mode,v.amount_minor,v.currency,o.id AS offer_id,o.status AS offer_status,cp.code AS program_code,t.code AS team_code FROM shop_variants v JOIN shop_products p ON p.id=v.product_id LEFT JOIN shop_product_publications pub ON pub.product_id=p.id LEFT JOIN club_program_offers o ON o.variant_id=v.id AND o.product_id=p.id LEFT JOIN club_programs cp ON cp.id=o.program_id LEFT JOIN club_teams t ON t.id=o.team_id WHERE v.sku='KP-TEST-UAT-KROUZEK'");
    $variant->execute();
    $variants = $variant->fetchAll(PDO::FETCH_ASSOC);
    if (count($variants) !== 1) throw new KisUatOrderGuardFailure('fixture_sku_unique');
    $fixture = $variants[0];
    if ((string)$fixture['product_name'] !== 'TEST - Cyklistický kroužek') throw new KisUatOrderGuardFailure('fixture_product_name');
    if ((string)$fixture['offer_type'] !== 'program') throw new KisUatOrderGuardFailure('fixture_offer_type');
    if ((string)$fixture['product_status'] !== 'active') throw new KisUatOrderGuardFailure('fixture_product_active');
    if ((string)$fixture['publication_status'] !== 'active') throw new KisUatOrderGuardFailure('fixture_publication_active');
    if ((string)$fixture['variant_status'] !== 'active' || (int)$fixture['visible'] !== 1) throw new KisUatOrderGuardFailure('fixture_variant_active_visible');
    if ((string)$fixture['price_mode'] !== 'fixed' || (int)$fixture['amount_minor'] !== 10000 || (string)$fixture['currency'] !== 'CZK') throw new KisUatOrderGuardFailure('fixture_price_100_czk');
    if ((int)$fixture['offer_id'] < 1 || (string)$fixture['offer_status'] !== 'active') throw new KisUatOrderGuardFailure('fixture_program_offer_active');
    if ((string)$fixture['program_code'] !== 'TEST-UAT-KROUZEK') throw new KisUatOrderGuardFailure('fixture_program_code');
    if ((string)$fixture['team_code'] !== 'TEST-UAT-DETI') throw new KisUatOrderGuardFailure('fixture_team_code');

    $relation = $pdo->prepare("SELECT COUNT(*) FROM account_person_roles WHERE account_id=? AND sportovec_id=? AND relation_role='guardian' AND status='approved' AND valid_from<=CURRENT_TIMESTAMP AND (valid_to IS NULL OR valid_to>CURRENT_TIMESTAMP)");
    $relation->execute([(int)$accounts[0]['id'], (int)$children[0]]);
    if ((int)$relation->fetchColumn() !== 1) throw new KisUatOrderGuardFailure('guardian_approved_current');

    return ['account_id'=>(int)$accounts[0]['id'], 'child_id'=>(int)$children[0], 'product_id'=>(int)$fixture['product_id'], 'variant_id'=>(int)$fixture['variant_id']];
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
        if ((int)$existing->fetchColumn() !== 0) throw new KisUatOrderGuardFailure('earlier_pending_test_order');
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
    if (PHP_SAPI !== 'cli' || (string)getenv('KIS_UAT_ORDER_GUARD_CONFIRM') !== 'OVERIT-A-UKLIDIT-TEST-OBJEDNAVKU') throw new KisUatOrderGuardFailure('confirmation');
    $root = realpath((string)getenv('APP_ROOT'));
    $host = strtolower(trim((string)getenv('APP_HOST')));
    if ($root === false || $host !== 'kis.kovopraha.cz' || !is_file($root . '/config.php')) throw new KisUatOrderGuardFailure('production_target');
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = $host;
    try { require_once $root . '/config.php'; }
    catch (Throwable $exception) { throw new KisUatOrderGuardFailure('configuration_bootstrap'); }
    if (!defined('JE_LOKALNE') || JE_LOKALNE !== false) throw new KisUatOrderGuardFailure('production_mode');
    $baseline = (string)getenv('KIS_UAT_ORDER_BASELINE');
    if (!preg_match('/^[0-9]{1,12}$/D', $baseline)) throw new KisUatOrderGuardFailure('baseline_argument');
    foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $constant) if (!defined($constant)) throw new KisUatOrderGuardFailure('database_configuration');
    try { $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }
    catch (PDOException $exception) { throw new KisUatOrderGuardFailure('database_connect'); }
    try { $result = kisUatOrderGuard($pdo, (string)getenv('KIS_UAT_ORDER_MODE'), (int)$baseline); }
    catch (PDOException $exception) { throw new KisUatOrderGuardFailure('database_query'); }
    echo json_encode($result, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

/** The remote wrapper uses this entrypoint so no exception message can leak into Actions logs. */
function kisUatOrderGuardEntry(): void
{
    try {
        kisUatOrderGuardMain();
    } catch (KisUatOrderGuardFailure $exception) {
        fwrite(STDERR, 'uat_order_guard_failed category=' . $exception->category . PHP_EOL);
        exit(1);
    } catch (PDOException $exception) {
        fwrite(STDERR, 'uat_order_guard_failed category=database_error' . PHP_EOL);
        exit(1);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'uat_order_guard_failed category=unexpected_error' . PHP_EOL);
        exit(1);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    kisUatOrderGuardEntry();
}
