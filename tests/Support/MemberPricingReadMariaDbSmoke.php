<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/MemberPricingReadFixture.php';
$port = getenv('EVIDENCE_MARIADB_SMOKE_PORT') ?: '3306';
$host = getenv('EVIDENCE_MARIADB_SMOKE_HOST') ?: '127.0.0.1';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !ctype_digit($port)) {
    throw new RuntimeException('Only an isolated localhost MariaDB fixture is permitted.');
}
$dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
$admin = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = 'pricing_parity_' . bin2hex(random_bytes(8));
$checks = 0;
$check = static function (PDO $pdo, array $catalog, int $account = 1, string $on = '2026-11-07') use (&$checks): array {
    $before = beforeMemberPricingCatalog($pdo, $account, $catalog, $on);
    $after = shopMemberPricesApplyToCatalog($pdo, $account, $catalog, $on);
    if ($before !== $after) {
        throw new RuntimeException('MariaDB complete price/source-label/type parity failed at check ' . $checks);
    }
    $checks++;
    return $after;
};
try {
    $admin->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new Tests\Support\QueryProfilePdo($dsn . ';dbname=' . $schema, 'root', '');
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    memberPricingReadSeed($pdo, 501);
    $catalog = memberPricingReadCatalog(501, true);
    foreach ([0, 1, 2, 3, 999] as $account) {
        $check($pdo, $catalog, $account);
    }
    // The batch boundary was covered above; use a small result for the semantic mutation matrix.
    $catalog = array_slice($catalog, 0, 3);
    $pdo->exec('ALTER TABLE shop_product_categories MODIFY category_path VARCHAR(500) COLLATE utf8mb4_bin');
    $pdo->exec("UPDATE shop_member_category_rules SET category_path='ÓBLÉČENÍ ' WHERE team_id=10");
    $result = $check($pdo, $catalog);
    if ($result[0]['variants'][0]['member_price']['source_label'] !== 'Sleva kategorie ÓBLÉČENÍ ') {
        throw new RuntimeException('Unicode/case/accent/trailing-space fixture did not exercise a real SQL match.');
    }
    $pdo->exec("INSERT INTO shop_member_product_prices(team_id,product_id,amount_minor,currency,created_by_trainer_id) VALUES(10,1,8000,'cZk',7)");
    $catalog[0]['variants'][] = ['variant_id' => 999, 'product_id' => 1, 'amount_minor' => 10000, 'currency' => 'CZK '];
    $catalog[0]['variants'][] = ['variant_id' => 1000, 'product_id' => 1, 'amount_minor' => 10000, 'currency' => 'czk'];
    $result = $check($pdo, $catalog);
    if ($result[0]['variants'][0]['amount_minor'] !== 8000 || $result[0]['variants'][1]['amount_minor'] !== 8000) {
        throw new RuntimeException('Product currency collation fixture did not match.');
    }
    $pdo->exec("INSERT INTO shop_product_categories VALUES(10000,2,'Doplňky',0,1),(10001,2,'OBLEČENÍ',0,2)");
    $pdo->exec("INSERT INTO shop_member_category_rules(team_id,category_path,discount_type,value_minor_or_basis_points,currency,created_by_trainer_id) "
        . "VALUES(10,'Doplňky','percentage',1000,NULL,7)");
    $check($pdo, $catalog); // Equal amounts in one team retain the canonical label.
    $changes = [
        "UPDATE shop_member_product_prices SET active=0",
        "UPDATE shop_member_category_rules SET active=0",
        "UPDATE shop_member_category_rules SET active=1; UPDATE account_person_roles SET valid_to='2026-11-07 00:00:00'",
        "UPDATE account_person_roles SET valid_to=NULL; UPDATE club_roster_members SET valid_to='2026-11-07'",
        "UPDATE account_person_roles SET status='pending'",
        "UPDATE account_person_roles SET status='approved'; UPDATE club_roster_members SET status='removed'",
        "UPDATE club_roster_members SET status='active'; UPDATE club_teams SET status='closed'",
        "UPDATE club_teams SET status='active'; UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=1",
        "UPDATE verejni_uzivatele SET email_overeno=1,aktivni=0 WHERE id=1",
        "UPDATE verejni_uzivatele SET aktivni=1 WHERE id=1",
    ];
    foreach ($changes as $sql) {
        // Native prepares deliberately remain enabled: execute each synthetic update separately.
        foreach (explode(';', $sql) as $statement) {
            $pdo->exec(trim($statement));
        }
        foreach (['2026-11-06', '2026-11-07', '2026-11-08'] as $on) {
            $check($pdo, $catalog, 1, $on);
        }
    }
    $pdo->beginTransaction();
    try {
        $pdo->profile = [];
        $pdo->profiling = true;
        beforeMemberPricingCatalog($pdo, 1, $catalog, '2026-11-07');
        $expectedSql = array_column($pdo->profile, 'sql');
        $pdo->profile = [];
        shopMemberPricesApplyToCatalog($pdo, 1, $catalog, '2026-11-07');
        if ($expectedSql !== array_column($pdo->profile, 'sql')) {
            throw new RuntimeException('Transaction SQL path changed.');
        }
        $pdo->profiling = false;
        $check($pdo, $catalog);
    } finally {
        $pdo->profiling = false;
        $pdo->rollBack();
    }
    echo json_encode(['server' => $admin->query('SELECT VERSION()')->fetchColumn(), 'checks' => $checks,
        'strict_parity' => true, 'collation' => true, 'transaction_sql_identical' => true,
        'schema_cleanup' => true], JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
    $pdo = null;
    $admin->exec("DROP DATABASE IF EXISTS `$schema`");
}
