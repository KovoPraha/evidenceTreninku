<?php

declare(strict_types=1);

require_once __DIR__ . '/FamilyOverviewPageFixture.php';
require_once __DIR__ . '/ShopCartReadBaseline.php';

$schema = 'w92_cart_' . bin2hex(random_bytes(8));
$admin = w72Connection();
$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo = w72Connection($schema, true);
    w72Seed($pdo);
    $pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
        . "VALUES(10,1,'guardian','approved','2020-01-01',7,'Synthetic pricing scope')");
    $pdo->exec("INSERT INTO shop_member_product_prices(team_id,product_id,amount_minor,currency,created_by_trainer_id) VALUES(1,501,10001,'CZK',7)");
    $pdo->exec("INSERT INTO shop_products(id,origin,created_by_trainer_id,external_product_key,name,offer_type,catalog_status) "
        . "VALUES(10000,'manual',7,'W92-GOODS','Synthetic goods','goods','active')");
    $pdo->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) "
        . "VALUES(10000,'active','Synthetic goods','Synthetic','Synthetic')");
    $pdo->exec("INSERT INTO shop_member_product_prices(team_id,product_id,amount_minor,currency,created_by_trainer_id) VALUES(1,10000,10001,'CZK',7)");
    $goods = $pdo->prepare("INSERT INTO shop_variants(id,product_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,catalog_status) "
        . "VALUES(?,10000,'manual',7,?,'{}','fixed',12500,'CZK','active')");
    $term = $pdo->prepare("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id,status) "
        . "VALUES('club_program','program:1',?,?,'Published synthetic conditions','trainer',7,'active')");
    foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
        $term->execute([$purpose, hash('sha256', $purpose)]);
    }
    $variant = $pdo->prepare("INSERT INTO shop_variants(id,product_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,amount_minor,currency,catalog_status) "
        . "VALUES(?,501,'manual',7,?,'{}','fixed',12500,'CZK','active')");
    $offer = $pdo->prepare("INSERT INTO club_program_offers(program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,capacity,status,created_by_trainer_id) "
        . "VALUES(1,1,1,501,?,?,'Synthetic','2026-01-01','2027-12-31',20000,'active',7)");
    for ($i = 1; $i <= 50; $i++) {
        $variant->execute([1000 + $i, 'W92-' . $i]);
        $goods->execute([2000 + $i, 'W92-GOODS-' . $i]);
        $offer->execute([1000 + $i, 'W92-' . $i]);
    }
    $results = [];
    $plain = w72Connection($schema);
    $compare = static function (PDO $pdo, int $account): array {
        $counts = [];
        $before = null;
        foreach (['baseline' => 'w92CartDetailBaseline', 'current' => 'shopCartDetail'] as $mode => $call) {
            $pdo->profile = [];
            $pdo->profiling = true;
            $value = $call($pdo, $account);
            $pdo->profiling = false;
            $counts[$mode] = ['sql' => count($pdo->profile), 'rows' => array_sum(array_column($pdo->profile, 'fetched_rows')),
                'program_metadata' => count(array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'information_schema'))),
                'capacity' => count(array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'club_program_enrollments'))),
                'terms' => count(array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM club_event_term_versions'))),
                'bytes' => strlen(json_encode($value, JSON_THROW_ON_ERROR))];
            if ($before !== null && $before !== $value) {
                throw new RuntimeException('Cart data/types/order/fingerprint changed.');
            }
            $before = $value;
        }
        return $counts;
    };
    foreach ([1, 10, 50] as $size) {
        foreach (['goods', 'program'] as $kind) {
            foreach ([10, 11] as $account) {
                $cart = shopCartGetOrCreate($pdo, $account);
                $pdo->prepare('DELETE FROM shop_cart_items WHERE cart_id=?')->execute([$cart['id']]);
                $item = $pdo->prepare('INSERT INTO shop_cart_items(cart_id,variant_id,quantity,beneficiary_sportovec_id) VALUES(?,?,1,?)');
                for ($i = 1; $i <= $size; $i++) {
                    $item->execute([$cart['id'], ($kind === 'goods' ? 2000 : 1000) + $i, $account === 10 ? 1 : 999]);
                }
                // First access may create a cart; only the established cart read is profiled.
                $warm = shopCartDetail($pdo, $account);
                if ($warm['total_minor'] !== ($account === 10 ? 10001 : 12500) * $size
                    || $warm['items'][0]['member_price']['is_member_price'] !== ($account === 10)
                    || $warm['items'][0]['availability_error'] !== null) {
                    throw new RuntimeException('Synthetic cart/member price must be genuinely saleable.');
                }
                unset($warm);
                $counts = $compare($pdo, $account);
                if (in_array('--enforce', $argv, true) && $kind === 'program' && $size === 50 && $counts['current']['terms'] > 2) {
                    throw new RuntimeException('Fifty program cart terms exceed two bounded queries: ' . json_encode($counts));
                }
                $samples = ['baseline' => [], 'current' => []];
                $extended = in_array('--extended', $argv, true);
                for ($sample = $extended ? -5 : -1; !in_array('--counts-only', $argv, true) && $sample < ($extended ? 30 : 5); $sample++) {
                    foreach ($sample % 2 === 0 ? ['baseline', 'current'] : ['current', 'baseline'] as $mode) {
                        $call = $mode === 'baseline' ? 'w92CartDetailBaseline' : 'shopCartDetail';
                        memory_reset_peak_usage();
                        $memory = memory_get_usage();
                        $start = hrtime(true);
                        $value = $call($plain, $account);
                        $elapsed = (hrtime(true) - $start) / 1e6;
                        $peak = memory_get_peak_usage() - $memory;
                        unset($value);
                        if ($sample >= 0) {
                            $samples[$mode][] = ['ms' => $elapsed, 'peak_bytes' => $peak];
                        }
                    }
                }
                $results[] = ['size' => $size, 'kind' => $kind, 'member' => $account === 10,
                    'same_result' => true, 'counts' => $counts, 'samples' => $samples];
            }
        }
    }
    $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn'");
    $compare($pdo, 10);
    $pdo->exec("UPDATE account_person_roles SET status='revoked' WHERE account_id=10");
    $compare($pdo, 10);
    $pdo->exec("UPDATE shop_variants SET catalog_status='inactive' WHERE id=1001");
    $compare($pdo, 10);
    echo json_encode(['database_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'migrated_schema' => true,
        'foreign_keys' => (int) $pdo->query('SELECT @@foreign_key_checks')->fetchColumn(),
        'production_mutations' => false, 'fresh_terms_roles_variant_status' => true, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE `' . $schema . '`');
}
