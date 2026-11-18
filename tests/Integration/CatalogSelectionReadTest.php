<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogSelectionBaseline.php';
require_once dirname(__DIR__) . '/Support/CatalogSelectionFixture.php';
require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';

final class CatalogSelectionReadTest extends TestCase
{
    public function testLiteralPathsWhitespaceUnicodeDuplicatesAndAllSelectionModesMatch(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_category_meta VALUES('Root','Root',NULL,0,1,NULL),('Root > Child','Child','Root',1,1,NULL),"
            . "('Leaf','Leaf','Root',2,1,NULL),('Other','Other',NULL,3,1,NULL),('Ž_%','Unicode','Root',4,1,NULL)");
        $categories = [['Root'], ['Root > Child', 'Root > Child'], ['Root>Child'], [' Root '], ['Leaf', 'Other'],
            ['Ž_%'], ['ROOT'], [''], [], ['Other', 'Other']];
        $products = [];
        foreach ($categories as $i => $paths) {
            $products[] = $this->product($i % 3, $paths, 'Žluťoučký ' . $i);
        }
        $inputs = [[], ['kategorie' => 'Root'], ['kategorie' => ' Root>Child '], ['kategorie' => 'Leaf'],
            ['kategorie' => 'Ž_%'], ['kategorie' => 'missing'], ['kategorie' => '>'],
            ['kategorie' => 'Root', 'q' => 'ŽLUŤOUČKÝ', 'razeni' => 'name', 'strana' => 99],
            ['kategorie' => 'Root', 'razeni' => 'price']];
        $original = $products;
        foreach ($inputs as $input) {
            self::assertSame(\catalogSelectionBaseline($pdo, $products, $input), \shopCatalogPageSelect($pdo, $products, $input));
        }
        self::assertSame($original, $products);
        $state = \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'Root']);
        self::assertSame(4, $state['filteredProductCount']);
    }

    public function testAllPagesAndSearchOverrideRetainCountsAndStableTies(): void
    {
        $pdo = $this->database();
        $products = \catalogSelectionFixture($pdo, 150, 80, 'tree');
        foreach ($products as $i => &$product) {
            $product['product_id'] = $i % 7;
            $product['public_name'] = $i % 2 === 0 ? '02' : '2';
            $product['min_amount_minor'] = 100;
        }
        unset($product);
        foreach (['order', 'price', 'name', 'invalid'] as $sort) {
            foreach ([1, 2, 13, 999] as $page) {
                $input = ['kategorie' => 'LOCALHOST-0', 'razeni' => $sort, 'strana' => $page];
                $before = \catalogSelectionBaseline($pdo, $products, $input);
                self::assertSame($before, \shopCatalogPageSelect($pdo, $products, $input));
                self::assertSame(150, $before['filteredProductCount']);
                self::assertSame(3, $before['topCategories'][0]['count']);
            }
        }
        $input = ['kategorie' => 'LOCALHOST-0', 'q' => 'ignored', 'razeni' => 'price'];
        self::assertSame(
            \catalogSelectionBaseline($pdo, $products, $input, [4 => true]),
            \shopCatalogPageSelect($pdo, $products, $input, [4 => true])
        );
    }

    public function testFallbackPreservesExceptionsWarningsAndReachableInvalidCategories(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_category_meta VALUES('Root','Root',NULL,0,1,NULL),('Leaf','Leaf','Root',1,1,NULL)");
        foreach ([null, 'Root', new \ArrayIterator(['Root']), [7, 'Root'], [null, 'Root'], [[], 'Root'],
            [new class () implements \Stringable {
                public function __toString(): string
                {
                    return 'Root';
                }
            }, 'Root']] as $categories) {
            foreach (['Root', 'missing'] as $selected) {
                $products = [$this->product(1, ['Leaf']), $this->product(2, [])];
                $products[1]['categories'] = $categories;
                $records = [];
                foreach (['catalogSelectionBaseline', 'shopCatalogPageSelect'] as $call) {
                    $warnings = [];
                    set_error_handler(static function (int $code, string $message) use (&$warnings): bool {
                        $warnings[] = [$code, $message];
                        return true;
                    });
                    try {
                        $records[] = ['state' => $call($pdo, $products, ['kategorie' => $selected]), 'warnings' => $warnings];
                    } catch (\Throwable $error) {
                        $records[] = ['error' => [$error::class, $error->getMessage()], 'warnings' => $warnings];
                    } finally {
                        restore_error_handler();
                    }
                }
                self::assertSame($records[0], $records[1]);
            }
        }
    }

    public function testEmptyProductsAndCategoriesPreserveResults(): void
    {
        $pdo = $this->database();
        foreach ([[], [$this->product(1, [])], [$this->product(1, ['Root'])]] as $products) {
            foreach ([[], ['kategorie' => 'Root'], ['kategorie' => 'missing']] as $input) {
                self::assertSame(\catalogSelectionBaseline($pdo, $products, $input), \shopCatalogPageSelect($pdo, $products, $input));
            }
        }
    }

    public function testNumericLookingStringPathsKeepExactMembership(): void
    {
        $pdo = $this->database();
        $insert = $pdo->prepare('INSERT INTO shop_category_meta VALUES(?,?,NULL,0,1,NULL)');
        $paths = ['01', '+1', '1.0', '9223372036854775808'];
        foreach ($paths as $path) {
            $insert->execute([$path, $path]);
        }
        $products = [];
        foreach (array_merge($paths, ['1', '0', ' 01 ']) as $i => $path) {
            $products[] = $this->product($i, [$path]);
        }
        foreach ($paths as $path) {
            $input = ['kategorie' => $path];
            $state = \shopCatalogPageSelect($pdo, $products, $input);
            self::assertSame(\catalogSelectionBaseline($pdo, $products, $input), $state);
            self::assertSame(1, $state['filteredProductCount']);
        }
    }

    public function testFreshMetadataConnectionsRollbackAndTransactionalQueryShape(): void
    {
        $pdo = $this->database();
        $products = \catalogSelectionFixture($pdo, 60, 30, 'tree');
        $input = ['kategorie' => 'LOCALHOST-0'];
        $pdo->profiling = true;
        $pdo->profile = [];
        $before = \catalogSelectionBaseline($pdo, $products, $input);
        self::assertCount(3, $pdo->profile);
        $pdo->profile = [];
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, $input));
        self::assertCount(3, $pdo->profile);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE shop_category_meta SET parent_path=NULL WHERE category_path='LOCALHOST-1'");
        $pdo->profile = [];
        $inside = \catalogSelectionBaseline($pdo, $products, $input);
        $oldProfile = $pdo->profile;
        $pdo->profile = [];
        self::assertSame($inside, \shopCatalogPageSelect($pdo, $products, $input));
        self::assertNotSame($before, $inside);
        self::assertCount(6, $pdo->profile);
        $shape = static fn (array $query): array => [$query['sql'], $query['parameters'], $query['fetched_rows']];
        self::assertSame(array_map($shape, $oldProfile), array_map($shape, $pdo->profile));
        $pdo->rollBack();
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, $input));
        self::assertSame([], \shopCatalogPageSelect($this->database(), $products, $input)['products']);
    }

    private function product(int $id, array $categories, string $name = 'LOCALHOST'): array
    {
        return ['product_id' => $id, 'categories' => $categories, 'public_name' => $name,
            'public_summary' => 'NEPLATIT', 'min_amount_minor' => 100];
    }

    private function database(): \Tests\Support\QueryProfilePdo
    {
        $pdo = new \Tests\Support\QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE shop_product_categories(product_id INT,category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_member_category_rules(category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,display_name TEXT,parent_path TEXT NULL,'
            . 'sort_order INT,visible_in_menu INT,description TEXT NULL)');
        return $pdo;
    }
}
