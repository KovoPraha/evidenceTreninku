<?php
declare(strict_types=1);

/** Read-only release preflight. Never load db.php, bootstrap, or migration code. */
function clubReleaseImpactIdentity(string $host, array $config): bool
{
    if ($host === 'kis.kovopraha.cz') {
        return ($config['JE_LOKALNE'] ?? null) === false
            && ($config['APP_BASE_URL'] ?? null) === 'https://kis.kovopraha.cz';
    }
    if (!in_array($host, ['localhost', '127.0.0.1', '127.0.0.1:18086'], true)
        || ($config['JE_LOKALNE'] ?? null) !== true) return false;
    $url = parse_url((string)($config['APP_BASE_URL'] ?? ''));
    return is_array($url) && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)
        && in_array($url['scheme'] ?? '', ['http', 'https'], true)
        && !isset($url['user']) && !isset($url['pass']);
}

function clubReleaseImpactPrivateStorage(string $appRoot, mixed $configured): array
{
    $path = is_string($configured) && trim($configured) !== '' ? $configured : dirname($appRoot, 2).'/private/evidencePavel';
    $resolved = realpath($path); $app = realpath($appRoot);
    $normalize = static fn(string $v): string => strtolower(rtrim(str_replace('\\', '/', $v), '/'));
    $outside = $resolved !== false && $app !== false && $normalize($resolved) !== $normalize($app)
        && !str_starts_with($normalize($resolved), $normalize($app).'/');
    return ['exists'=>$resolved !== false && is_dir($resolved), 'writable'=>$resolved !== false && is_writable($resolved),
        'outside_application_tree'=>$outside, 'public_webroot_not_proven_by_cli'=>true, 'write_probe_performed'=>false];
}

/** Only aggregate counts leave the database. Missing old/new schema is explicit. */
function clubReleaseImpact(PDO $pdo, string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$date || $date->format('Y-m-d') !== $today || $pdo->inTransaction()) {
        throw new RuntimeException('impact_invalid_context');
    }
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true)) throw new RuntimeException('impact_unsupported_driver');
    $sqliteReadOnly = $driver === 'sqlite' ? (int)$pdo->query('PRAGMA query_only')->fetchColumn() : null;
    if ($driver === 'mysql') {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    } else {
        $pdo->exec('PRAGMA query_only=ON');
        $pdo->beginTransaction();
    }
    try {
        $cache = [];
        $columns = static function (string $table) use ($pdo, $driver, &$cache): array {
            if (isset($cache[$table])) return $cache[$table];
            if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('impact_invalid_table');
            if ($driver === 'mysql') {
                $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $q->execute([$table]);
                return $cache[$table] = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            return $cache[$table] = array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        };
        $has = static function (string $table, array $required) use ($columns): bool {
            return array_diff($required, $columns($table)) === [];
        };
        $count = static function (string $sql, array $params = []) use ($pdo): int {
            $q = $pdo->prepare($sql); $q->execute($params); return (int)$q->fetchColumn();
        };
        $checks = [];
        $unknown = static function (string $key) use (&$checks): void {
            $checks[$key] = ['status'=>'unknown_schema', 'count'=>null];
        };
        $record = static function (string $key, int $value, string $basis) use (&$checks): void {
            $checks[$key] = ['status'=>'counted', 'count'=>$value, 'basis'=>$basis];
        };
        $scopeAbsent = $columns('coach_scope_grants') === [];
        $stockAbsent = $columns('shop_stock_balances') === [];
        $agreementsAbsent = $columns('member_fee_agreements') === [];
        $decisionsAbsent = $columns('member_fee_period_decisions') === [];
        $coachBase = "FROM treneri t WHERE t.aktivni=1 AND EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='coach')";
        if ($has('treneri', ['id','aktivni']) && $has('staff_user_positions', ['trainer_id','position_code'])) {
            $record('active_staff_with_coach_position', $count('SELECT COUNT(*) '.$coachBase), 'explicit_coach_position');
            if ($scopeAbsent || $has('coach_scope_grants', ['trainer_id','revoked_at','valid_from','valid_to'])) {
                $condition = $scopeAbsent ? '' : " AND NOT EXISTS(SELECT 1 FROM coach_scope_grants g WHERE g.trainer_id=t.id AND g.revoked_at IS NULL AND g.valid_from<=CURRENT_TIMESTAMP AND (g.valid_to IS NULL OR g.valid_to>CURRENT_TIMESTAMP))";
                $record('coaches_without_current_scope_grant', $count('SELECT COUNT(*) '.$coachBase.$condition), $scopeAbsent ? 'scope_table_absent_no_grants_inferred' : 'current_nonrevoked_grant_any_scope');
            } else $unknown('coaches_without_current_scope_grant');
        } else { $unknown('active_staff_with_coach_position'); $unknown('coaches_without_current_scope_grant'); }
        $goodsBase = "FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='goods' AND p.catalog_status='active' AND v.catalog_status='active' AND v.visible=1 AND EXISTS(SELECT 1 FROM shop_product_publications pub WHERE pub.product_id=p.id AND pub.status='active')";
        if ($has('shop_variants', ['id','product_id','catalog_status','visible','stock_quantity_decimal'])
            && $has('shop_products', ['id','offer_type','catalog_status']) && $has('shop_product_publications', ['product_id','status'])) {
            $record('published_goods_variants', $count('SELECT COUNT(*) '.$goodsBase), 'active_visible_published_goods');
            $record('published_goods_null_legacy_quantity', $count('SELECT COUNT(*) '.$goodsBase.' AND v.stock_quantity_decimal IS NULL'), 'new_checkout_rejects_null_stock_quantity');
            if ($stockAbsent || $has('shop_stock_balances', ['variant_id'])) {
                $condition = $stockAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM shop_stock_balances b WHERE b.variant_id=v.id)';
                $record('published_goods_without_stock_opening', $count('SELECT COUNT(*) '.$goodsBase.$condition), $stockAbsent ? 'stock_table_absent_no_openings_inferred' : 'no_physical_stock_balance_row');
            } else $unknown('published_goods_without_stock_opening');
        } else { foreach (['published_goods_variants','published_goods_null_legacy_quantity','published_goods_without_stock_opening'] as $key) $unknown($key); }
        $from = $date->format('Y-m-01'); $to = $date->format('Y-m-t');
        $planBase = "FROM member_fee_plans p WHERE p.status='active' AND p.starts_on<=? AND (p.ends_on IS NULL OR p.ends_on>=?)";
        if ($has('member_fee_plans', ['id','status','starts_on','ends_on'])) {
            $record('active_fee_plans_current_month', $count('SELECT COUNT(*) '.$planBase, [$to,$from]), 'active_plans_overlapping_report_month');
            if ($agreementsAbsent || $has('member_fee_agreements', ['plan_id','valid_from','valid_to'])) {
                $condition = $agreementsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_agreements a WHERE a.plan_id=p.id AND a.valid_from<=? AND a.valid_to>=?)';
                $record('active_fee_plans_without_any_month_agreement', $count('SELECT COUNT(*) '.$planBase.$condition, $agreementsAbsent ? [$to,$from] : [$to,$from,$to,$from]), $agreementsAbsent ? 'agreement_table_absent' : 'no_agreement_overlapping_month_for_any_person');
            } else $unknown('active_fee_plans_without_any_month_agreement');
            if ($decisionsAbsent || $has('member_fee_period_decisions', ['plan_id','period_key'])) {
                $condition = $decisionsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_period_decisions d WHERE d.plan_id=p.id AND d.period_key=?)';
                $record('active_fee_plans_without_any_month_amount_decision', $count('SELECT COUNT(*) '.$planBase.$condition, $decisionsAbsent ? [$to,$from] : [$to,$from,$date->format('Y-m')]), $decisionsAbsent ? 'decision_table_absent' : 'no_amount_decision_for_any_person_in_month');
            } else $unknown('active_fee_plans_without_any_month_amount_decision');
        } else { foreach (['active_fee_plans_current_month','active_fee_plans_without_any_month_agreement','active_fee_plans_without_any_month_amount_decision'] as $key) $unknown($key); }
        if ($has('family_weekly_summaries', ['id','status'])) {
            $record('pending_family_weekly_summaries', $count("SELECT COUNT(*) FROM family_weekly_summaries WHERE status='pending'"), 'pending_queue');
            $snapshotAbsent = $columns('notification_authorization_snapshots') === [];
            if ($snapshotAbsent || $has('notification_authorization_snapshots', ['queue_key','message_id'])) {
                $condition = $snapshotAbsent ? '' : " AND NOT EXISTS(SELECT 1 FROM notification_authorization_snapshots a WHERE a.queue_key='family_weekly_summaries' AND a.message_id=s.id)";
                $record('pending_family_summaries_without_authorization_snapshot', $count("SELECT COUNT(*) FROM family_weekly_summaries s WHERE s.status='pending'".$condition), 'worker_will_hold_missing_snapshot');
            } else $unknown('pending_family_summaries_without_authorization_snapshot');
        } else { $unknown('pending_family_weekly_summaries'); $unknown('pending_family_summaries_without_authorization_snapshot'); }
        return ['ok'=>true, 'read_only'=>true, 'read_only_session'=>true, 'deployment_ready'=>false, 'as_of'=>$today, 'month'=>$date->format('Y-m'),
            'complete'=>!in_array('unknown_schema', array_column($checks, 'status'), true),
            'checks'=>$checks,
            'limitations'=>[
                'Counts are an impact inventory, not permission to deploy or evidence of complete operating readiness.',
                'A current coach grant is not proof of coverage of every assigned athlete or historical training.',
                'A stock balance row is not proof of an approved physical inventory or valid valuation.',
                'One agreement or amount decision does not prove coverage of all members, all days, or a valid payer; plans with partial coverage need separate review.',
                'No roles, payers, amounts, stock quantities, or source identities are inferred or changed.',
            ]];
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($driver === 'sqlite') $pdo->exec('PRAGMA query_only='.($sqliteReadOnly ? 'ON' : 'OFF'));
    }
}

function clubReleaseImpactMain(): int
{
    if (PHP_SAPI !== 'cli') return 2;
    ini_set('display_errors', '0');
    $rootInput = (string)getenv('APP_ROOT'); $root = $rootInput === '' ? false : realpath($rootInput); $host = (string)getenv('APP_HOST');
    if (getenv('CLUB_RELEASE_IMPACT_CONFIRM') !== 'OVERIT-DOPAD-VYDANI' || !$root || !is_file($root.'/config.php') || !in_array($host, ['kis.kovopraha.cz','localhost','127.0.0.1','127.0.0.1:18086'], true)) {
        echo "{\"ok\":false,\"error\":\"impact_invalid_target\"}\n"; return 2;
    }
    $_SERVER['HTTP_HOST'] = $host; $_SERVER['SERVER_NAME'] = explode(':', $host)[0];
    ob_start();
    try { require $root.'/config.php'; }
    catch (Throwable) { ob_end_clean(); echo "{\"ok\":false,\"error\":\"impact_config_failed\"}\n"; return 2; }
    ob_end_clean();
    $config=[]; foreach (['JE_LOKALNE','APP_BASE_URL','DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $key) {
        if (!defined($key)) { echo "{\"ok\":false,\"error\":\"impact_config_incomplete\"}\n"; return 2; }
        $config[$key]=constant($key);
    }
    if (!clubReleaseImpactIdentity($host, $config)) { echo "{\"ok\":false,\"error\":\"impact_identity_mismatch\"}\n"; return 2; }
    try {
        $pdo = new PDO('mysql:host='.$config['DB_HOST'].';dbname='.$config['DB_NAME'].';charset=utf8mb4', $config['DB_USER'], $config['DB_PASS'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($host !== 'kis.kovopraha.cz') {
            $identity=$pdo->query('SELECT @@port AS port,DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
            if ((int)$identity['port'] !== 33426 || $identity['db'] !== 'evidence_b1') throw new RuntimeException('impact_wrong_local_database');
        }
        $report=clubReleaseImpact($pdo, (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d'));
        $storage = getenv('APP_PRIVATE_STORAGE_ROOT');
        if ($storage === false || trim($storage) === '') $storage = defined('APP_PRIVATE_STORAGE_ROOT') ? constant('APP_PRIVATE_STORAGE_ROOT') : null;
        $report['private_storage']=clubReleaseImpactPrivateStorage($root, $storage);
        echo json_encode($report, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
        return 0;
    } catch (Throwable) { echo "{\"ok\":false,\"error\":\"impact_probe_failed\"}\n"; return 2; }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(clubReleaseImpactMain());
