<?php

declare(strict_types=1);

require_once __DIR__ . '/ProgramStorefrontFixture.php';
require_once __DIR__ . '/QueryProfilePdo.php';
require_once __DIR__ . '/CatalogPageBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_page_read.php';

/** Synthetic contract fixture, no config, network or real account data. */
function catalogPageFixture(int $count, bool $mixed = false): PDO
{
    $pdo = $mixed ? Tests\Support\programStorefrontFixture($count) : Tests\Support\storefrontReadFixture($count, false);
    if (!$mixed) {
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT)');
    }
    $pdo->exec("ALTER TABLE club_teams ADD COLUMN status TEXT DEFAULT 'active'");
    if (!$mixed) {
        $pdo->exec("INSERT INTO club_teams(id,name) VALUES(1,'Synthetic team')");
    }
    $pdo->exec('ALTER TABLE shop_products ADD COLUMN sort_order INTEGER DEFAULT 0');
    $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
    $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
    $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
    $pdo->exec('DROP TABLE shop_member_category_rules');
    $pdo->exec('CREATE TABLE shop_member_category_rules(id INTEGER PRIMARY KEY,team_id INTEGER,category_path TEXT,'
        . 'discount_type TEXT,value_minor_or_basis_points INTEGER,currency TEXT,active INTEGER)');
    $pdo->exec('CREATE TABLE shop_member_product_prices(id INTEGER PRIMARY KEY,team_id INTEGER,product_id INTEGER,amount_minor INTEGER,currency TEXT,active INTEGER)');
    $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,0),(3,1,1)');
    $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,50,'guardian','approved','2020-01-01',NULL)");
    $pdo->exec("INSERT INTO club_roster_members VALUES(1,1,50,'active','2020-01-01',NULL)");
    $pdo->exec("INSERT INTO shop_member_category_rules VALUES(1,1,'Test > Goods','percentage',1000,NULL,1)");
    $pdo->exec("INSERT INTO shop_member_product_prices VALUES(1,1,13,0,'CZK',1)");
    $pub = $pdo->prepare('UPDATE shop_product_publications SET public_name=?,public_summary=? WHERE product_id=?');
    $attrs = $pdo->prepare('UPDATE shop_variants SET attributes_json=?,amount_minor=? WHERE id=?');
    $image = $pdo->prepare('INSERT INTO shop_product_images VALUES(?,?,?,?)');
    for ($i = 1; $i <= $count; $i++) {
        $pub->execute(['Žlutý dres ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), str_repeat('LOCALHOST NEPLATIT syntetický popis. ', 40) . ' suffix_% ' . $i, $i]);
        $attrs->execute([json_encode(['Barva' => 'Žlutá', 'Materiál' => 'Bavlna', 'Velikost' => $i % 5, 'Podrobnosti' => str_repeat('synthetic ', 30)], JSON_THROW_ON_ERROR), 10000 + $i, $i]);
        for ($j = 0; $j < 3; $j++) {
            $image->execute([$i * 3 + $j, $i, 'uploads/shop-products/' . md5('synthetic-' . $i . '-' . $j) . '.jpg', $j]);
        }
    }
    $pdo->exec('CREATE INDEX idx_catalog_categories ON shop_product_categories(product_id,is_default,sort_order,id)');
    $pdo->exec('CREATE INDEX idx_catalog_variants ON shop_variants(product_id)');
    $pdo->exec('CREATE INDEX idx_catalog_images ON shop_product_images(product_id,sort_order,id)');
    return $pdo;
}

/** Copy a synthetic SQLite contract fixture into an owned empty PDO database. */
function catalogPageCopyFixture(PDO $source, PDO $target): void
{
    foreach ($source->query("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_ASSOC) as $table) {
        $sql = $table['sql'];
        if ($target->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $target->exec($sql);
        $rows = $source->query('SELECT * FROM ' . $table['name'])->fetchAll(PDO::FETCH_ASSOC);
        if ($rows !== []) {
            $stmt = $target->prepare('INSERT INTO ' . $table['name'] . ' VALUES(' . implode(',', array_fill(0, count($rows[0]), '?')) . ')');
            foreach ($rows as $row) {
                $stmt->execute(array_values($row));
            }
        }
    }
    foreach ($source->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
        $target->exec($sql);
    }
}
