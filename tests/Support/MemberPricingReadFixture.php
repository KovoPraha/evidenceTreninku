<?php

declare(strict_types=1);

require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/MemberPricingReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/shop_member_pricing_read.php';

/** Synthetic contract schema plus the real member-pricing migration. No config or transports. */
function memberPricingReadSeed(PDO $pdo, int $products = 1): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED PRIMARY KEY' : 'INTEGER PRIMARY KEY';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    foreach ([
        'treneri(id INT PRIMARY KEY)',
        'verejni_uzivatele(id INT PRIMARY KEY,aktivni INT,email_overeno INT)',
        "shop_products(id $id)",
        "club_teams(id $id,name VARCHAR(100),status VARCHAR(20))",
        "account_person_roles(id $id,account_id INT,sportovec_id INT,relation_role VARCHAR(20),status VARCHAR(20),"
            . 'valid_from DATETIME,valid_to DATETIME NULL)',
        "club_roster_members(id $id,team_id BIGINT UNSIGNED,sportovec_id INT,status VARCHAR(20),valid_from DATE,valid_to DATE NULL)",
        "shop_product_categories(id $id,product_id BIGINT UNSIGNED,category_path VARCHAR(500),is_default INT,sort_order INT)",
    ] as $definition) {
        $pdo->exec('CREATE TABLE ' . $definition . $suffix);
    }
    $pdo->exec('CREATE INDEX idx_pricing_category_product ON shop_product_categories(product_id,is_default,sort_order,id)');
    $pdo->exec('INSERT INTO treneri VALUES(7)');
    $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1),(3,1,0)');
    $pdo->exec("INSERT INTO club_teams VALUES(10,'U15','active'),(11,'Dráha','active'),(12,'Closed','closed')");
    $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,50,'guardian','approved','2020-01-01',NULL),"
        . "(2,1,51,'self','approved','2020-01-01',NULL),(3,3,50,'guardian','approved','2020-01-01',NULL)");
    $pdo->exec("INSERT INTO club_roster_members VALUES(1,10,50,'active','2026-01-01',NULL),"
        . "(2,11,50,'active','2026-01-01',NULL),(3,10,51,'active','2026-01-01',NULL)");
    $p = $pdo->prepare('INSERT INTO shop_products VALUES(?)');
    $c = $pdo->prepare("INSERT INTO shop_product_categories VALUES(?,?,'Oblečení',1,0)");
    for ($i = 1; $i <= $products; $i++) {
        $p->execute([$i]);
        $c->execute([$i, $i]);
    }
    $migration = require dirname(__DIR__, 2) . '/migrations/20260805010000_shop_member_pricing.php';
    $migration['up']($pdo);
    $pdo->exec("INSERT INTO shop_member_category_rules(team_id,category_path,discount_type,value_minor_or_basis_points,currency,created_by_trainer_id) "
        . "VALUES(10,'Oblečení','percentage',1000,NULL,7),(11,'Oblečení','fixed_discount',500,'CZK',7)");
}

/** @return list<array<string,mixed>> */
function memberPricingReadCatalog(int $variants, bool $distinctProducts = false): array
{
    $products = [];
    for ($i = 1; $i <= $variants; $i++) {
        $id = $distinctProducts ? $i : 1;
        $products[$id] ??= ['product_id' => $id, 'public_name' => 'Synthetic ' . $id,
            'public_summary' => 'LOCALHOST NEPLATIT', 'categories' => ['Oblečení'], 'variants' => []];
        $products[$id]['variants'][] = ['variant_id' => $i, 'product_id' => $id,
            'amount_minor' => 10000 + $i, 'currency' => 'CZK', 'sku' => 'SYNTHETIC-' . $i, 'in_stock' => true];
    }
    return array_values($products);
}
