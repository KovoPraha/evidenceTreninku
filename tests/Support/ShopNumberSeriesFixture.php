<?php
declare(strict_types=1);

/** Only synthetic tables; caller provides an isolated SQLite/test MariaDB DB. */
function shopNumberSeriesFixture(PDO $pdo, bool $migrate=true): void
{
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $big=$mysql?'BIGINT UNSIGNED':'INTEGER';$suffix=$mysql?' ENGINE=InnoDB':'';
    foreach ([
        'shop_orders'=>'id '.$big.' PRIMARY KEY',
        'shop_products'=>'id '.$big.' PRIMARY KEY,name VARCHAR(120),offer_type VARCHAR(32)',
        'shop_order_items'=>'id '.$big.' PRIMARY KEY,order_id '.$big.',product_id '.$big,
        'club_event_order_items'=>'id '.$big.' PRIMARY KEY,order_id '.$big,
        'public_velodrome_order_items'=>'id '.$big.' PRIMARY KEY,order_id '.$big,
        'payments'=>'id INT PRIMARY KEY,payable_type VARCHAR(32),payable_id '.$big.',variable_symbol VARCHAR(10)',
        'member_fee_standing_orders'=>'id INT PRIMARY KEY,variable_symbol VARCHAR(10)',
        'member_payer_variable_symbols'=>'payer_account_id INT PRIMARY KEY,variable_symbol VARCHAR(10)',
        'shop_product_categories'=>'id INT PRIMARY KEY,product_id '.$big.',category_path VARCHAR(500),is_default INT,sort_order INT',
        'shop_category_meta'=>'category_path VARCHAR(500) PRIMARY KEY,display_name VARCHAR(120),parent_path VARCHAR(500),sort_order INT,visible_in_menu INT,description TEXT',
        'shop_member_category_rules'=>'category_path VARCHAR(500)',
    ] as $table=>$columns) $pdo->exec('CREATE TABLE '.$table.'('.$columns.')'.$suffix);
    $pdo->exec("INSERT INTO shop_products VALUES(501,'LOCALHOST kniha','goods'),(502,'LOCALHOST tričko','goods'),(503,'LOCALHOST kroužek','program')");
    $pdo->exec('INSERT INTO shop_orders VALUES(1),(2),(3),(4),(5),(6),(7),(8)');
    $pdo->exec('INSERT INTO shop_order_items VALUES(101,1,501),(102,2,501),(103,2,502),(104,3,503),(105,4,501),(106,5,501),(107,6,501),(108,7,501),(109,8,501)');
    $pdo->exec("INSERT INTO shop_product_categories VALUES(1,501,'Zboží > Knihy',1,0),(2,502,'Zboží > Oblečení',1,0)");
    if ($migrate) {
        $migration=require dirname(__DIR__,2).'/migrations/20261021210000_shop_number_series.php';
        $migration['up']($pdo);$migration['up']($pdo);
        if (!$migration['verify']($pdo)) throw new RuntimeException('Series migration did not verify.');
    }
}
