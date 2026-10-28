<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;

final class StorefrontCountingPdo extends PDO
{
    public array $queries = [];
    public int $productRows = 0;

    public function __construct(string $dsn = 'sqlite::memory:', ?string $user = null, ?string $password = null)
    {
        parent::__construct($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [StorefrontCountingStatement::class, [$this]]);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        $this->queries[] = $query;
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$args);
    }
}

final class StorefrontCountingStatement extends PDOStatement
{
    protected function __construct(private StorefrontCountingPdo $connection)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->connection->queries[] = $this->queryString;
        return parent::execute($params);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = parent::fetchAll($mode, ...$args);
        if (str_contains($this->queryString, 'FROM shop_products p JOIN shop_product_publications')) {
            $this->connection->productRows += count($rows);
        }
        return $rows;
    }
}

function storefrontReadFixture(int $count, bool $imported, ?StorefrontCountingPdo $connection = null): StorefrontCountingPdo
{
    $pdo = $connection ?? new StorefrontCountingPdo();
    $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT,origin TEXT,external_product_key TEXT)');
    $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
    $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode '
        . 'TEXT,amount_minor INTEGER,currency TEXT,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT)');
    $pdo->exec('CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY,product_id INTEGER,image_url TEXT,sort_order INTEGER)');
    $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)');
    $pdo->exec('CREATE TABLE shop_stock_balances(variant_id INTEGER PRIMARY KEY)');
    $pdo->exec('CREATE TABLE club_programs(id INTEGER PRIMARY KEY,status TEXT,name TEXT)');
    $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,product_id INTEGER,variant_id INTEGER,program_id INTEGER,status TEXT)');
    $pdo->exec('CREATE TABLE shop_member_category_rules(category_path TEXT)');
    $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT,display_name TEXT,parent_path TEXT,sort_order INTEGER,visible_in_menu INTEGER,description TEXT)');
    $product = $pdo->prepare("INSERT INTO shop_products VALUES(?,'goods','active',?,?)");
    $publication = $pdo->prepare("INSERT INTO shop_product_publications VALUES(?,'active',?,'LOCALHOST NEPLATIT')");
    $variant = $pdo->prepare("INSERT INTO shop_variants VALUES(?,?,?,'{}','fixed',10000,'CZK','10.000000',1,'active')");
    $category = $pdo->prepare("INSERT INTO shop_product_categories VALUES(?,?,'Test > Goods',1,0)");
    for ($id = 1; $id <= $count; $id++) {
        $product->execute([$id, $imported ? 'import' : 'manual', ($imported ? 'shoptet:pair:' : 'manual:') . $id]);
        $publication->execute([$id, 'LOCALHOST product ' . $id]);
        $variant->execute([$id, $id, 'LOCALHOST-' . $id]);
        $category->execute([$id, $id]);
    }
    $pdo->queries = [];
    return $pdo;
}
