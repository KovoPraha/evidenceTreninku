<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/ShopInventoryReadBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/shop_inventory_read.php';

final class ShopInventoryReadTest extends TestCase
{
    public function testExactFiltersAndBoundedBalanceQueries(): void
    {
        $pdo = $this->database();
        foreach (['', 'ŽLUŤ', 'zlut', '100%_!', 'SKU-00', 'missing'] as $query) {
            $expected = \w91InventoryVariantsBaseline($pdo, $query);
            $actual = \shopInventoryReadVariants($pdo, $query);
            self::assertSame($expected, $actual);
            $baseline = iterator_to_array(\w91InventoryRowsBaseline($pdo, $expected));
            $pdo->profile = [];
            $pdo->profiling = true;
            $rows = iterator_to_array(\shopInventoryReadRows($pdo, $actual));
            $pdo->profiling = false;
            self::assertSame($baseline, $rows);
            self::assertLessThanOrEqual($actual === [] ? 0 : 1 + (int) ceil(count($actual) / 400), count($pdo->profile));
        }
    }

    public function testFreshUpdatesDuplicateRowsAndMissingLegacyTable(): void
    {
        $pdo = $this->database();
        $variants = \shopInventoryReadVariants($pdo, '');
        $variants = [$variants[0], $variants[0], $variants[2]];
        self::assertSame(iterator_to_array(\w91InventoryRowsBaseline($pdo, $variants)), iterator_to_array(\shopInventoryReadRows($pdo, $variants)));
        $pdo->exec('UPDATE shop_stock_balances SET physical_quantity=99,revision=2');
        self::assertSame(iterator_to_array(\w91InventoryRowsBaseline($pdo, $variants)), iterator_to_array(\shopInventoryReadRows($pdo, $variants)));
        $pdo->exec('DROP TABLE shop_stock_balances');
        self::assertSame(iterator_to_array(\w91InventoryRowsBaseline($pdo, $variants)), iterator_to_array(\shopInventoryReadRows($pdo, $variants)));
    }

    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT)');
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,stock_quantity_decimal TEXT)');
        $pdo->exec('CREATE TABLE shop_stock_balances(variant_id INTEGER PRIMARY KEY,physical_quantity TEXT,reserved_quantity TEXT,blocked_quantity TEXT,revision TEXT,opening_evidence TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(1,'Žluťoučký 100%_!','goods'),(2,'Zlutoucky','goods'),(3,'Hidden','program')");
        $variant = $pdo->prepare('INSERT INTO shop_variants VALUES(?,?,?,?)');
        for ($id = 1; $id <= 805; $id++) {
            $variant->execute([$id, ($id % 3) + 1, sprintf('SKU-%04d', $id), $id % 2 ? null : '0.000000']);
            if ($id % 2) {
                $pdo->prepare("INSERT INTO shop_stock_balances VALUES(?,'10','3','2','1','Synthetic')")->execute([$id]);
            }
        }
        return $pdo;
    }
}
