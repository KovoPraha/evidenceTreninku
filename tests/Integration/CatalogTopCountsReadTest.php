<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogTopCountsBaseline.php';
require_once dirname(__DIR__) . '/Support/CatalogTopCountsFixture.php';

final class CatalogTopCountsReadTest extends TestCase
{
    public function testLiteralPrefixesDuplicatesAndFullCatalogCountsMatch(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,'Root > Child'),(2,'Other'),(3,'Ž > á')");
        $products = [];
        foreach ([['Root', 'Root > Child', 'Root > Child'], ['Root > > bad', 'Root>Odd'],
            [' Root > Child ', 'Rootish'], ['Other', 'Other > Nested'], ['Ž > á', 'Ž > č'], ['root'], ['Root']] as $i => $paths) {
            $products[] = $this->product($i % 2, $paths, 'Item ' . $i);
        }
        $original = $products;
        foreach ([[], ['q' => 'Item 0'], ['kategorie' => 'Other'], ['kategorie' => '>'], ['strana' => 999, 'razeni' => 'price'],
            ['q' => 'NO_MATCH'], ['razeni' => 'name']] as $input) {
            $expected = \catalogTopCountsBaseline($pdo, $products, $input);
            $actual = \shopCatalogPageSelect($pdo, $products, $input);
            self::assertSame($expected, $actual);
            self::assertSame($original, $products);
            $counts = array_column($actual['topCategories'], 'count', 'path');
            self::assertSame(3, $counts['Root']);
            self::assertSame(1, $counts['Other']);
            self::assertSame(1, $counts['Ž']);
        }
    }

    public function testMetadataHierarchyDoesNotReplaceLiteralTechnicalPaths(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_category_meta VALUES('Hidden','Hidden',NULL,0,0,NULL),"
            . "('Leaf','Leaf','Hidden',1,1,NULL),('Root','Root',NULL,0,1,NULL),"
            . "('Root > Branch','Branch','Root',0,1,NULL)");
        $products = [$this->product(1, ['Leaf']), $this->product(2, ['Root > Branch'])];
        $actual = \shopCatalogPageSelect($pdo, $products, []);
        self::assertSame(\catalogTopCountsBaseline($pdo, $products), $actual);
        self::assertSame(['Leaf' => 1, 'Root' => 1], array_column($actual['topCategories'], 'count', 'path'));
    }

    public function testFallbackKeepsUnreachableValuesAndExactTypeErrors(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,'Root')");
        $products = [$this->product(1, ['Root', 7])];
        self::assertSame(\catalogTopCountsBaseline($pdo, $products), \shopCatalogPageSelect($pdo, $products, []));
        $products = [$this->product(1, [7, 'Root'])];
        $errors = [];
        foreach (['catalogTopCountsBaseline', 'shopCatalogPageSelect'] as $call) {
            try {
                $call($pdo, $products, []);
                self::fail('A reachable non-string must retain the existing TypeError.');
            } catch (\TypeError $error) {
                $errors[] = [$error::class, $error->getMessage()];
            }
        }
        self::assertSame($errors[0], $errors[1]);
        // No eligible root: original code must not inspect the non-string again.
        $pdo->exec("INSERT INTO shop_category_meta VALUES('Root','Root',NULL,0,0,NULL)");
        self::assertSame(\catalogTopCountsBaseline($pdo, $products), \shopCatalogPageSelect($pdo, $products, []));
    }

    public function testPagingAndSearchMatchesDoNotLimitTopCountsAndRollbackIsFresh(): void
    {
        $pdo = $this->database();
        $products = \catalogTopCountsFixture($pdo, 150, 6);
        $before = \shopCatalogPageSelect($pdo, $products, ['strana' => 3]);
        self::assertSame(\catalogTopCountsBaseline($pdo, $products, ['strana' => 3]), $before);
        self::assertCount(12, $before['products']);
        self::assertSame(50, $before['topCategories'][0]['count']);
        $input = ['q' => 'ignored search', 'razeni' => 'name'];
        self::assertSame(
            \catalogTopCountsBaseline($pdo, $products, $input, [4 => true]),
            \shopCatalogPageSelect($pdo, $products, $input, [4 => true])
        );
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO shop_category_meta VALUES('LOCALHOST-0','Changed',NULL,0,0,NULL)");
        self::assertSame(\catalogTopCountsBaseline($pdo, $products), \shopCatalogPageSelect($pdo, $products, []));
        $pdo->rollBack();
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, ['strana' => 3]));
        self::assertSame([], \shopCatalogPageSelect($this->database(), $products, [])['topCategories']);
    }

    public function testNumericLookingRootsRetainExactStringPrefixes(): void
    {
        $pdo = $this->database();
        $paths = ['01', '+1', '1.0', '9223372036854775808'];
        $insert = $pdo->prepare('INSERT INTO shop_product_categories VALUES(?,?)');
        $products = [];
        foreach ($paths as $i => $path) {
            $insert->execute([$i + 1, $path . ' > Child']);
            $products[] = $this->product($i + 1, [$path, $path . ' > Child', $path]);
        }
        $actual = \shopCatalogPageSelect($pdo, $products, []);
        self::assertSame(\catalogTopCountsBaseline($pdo, $products), $actual);
        $counts = array_column($actual['topCategories'], 'count', 'path');
        self::assertCount(4, $counts);
        foreach ($paths as $path) {
            self::assertSame(1, $counts[$path]);
        }
    }

    public function testNonArrayCategoryWarningsAndTraversableFallbackStayIdentical(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,'Root'),(2,'Other')");
        foreach ([null, 'Root', new \ArrayIterator(['Root', 'Other'])] as $categories) {
            $products = [$this->product(1, []), $this->product(2, ['Root', 'Other'])];
            $products[0]['categories'] = $categories;
            $records = [];
            foreach (['catalogTopCountsBaseline', 'shopCatalogPageSelect'] as $call) {
                $warnings = [];
                set_error_handler(static function (int $code, string $message) use (&$warnings): bool {
                    $warnings[] = [$code, $message];
                    return true;
                });
                try {
                    $state = $call($pdo, $products, []);
                    $records[] = [$state, $warnings];
                } finally {
                    restore_error_handler();
                }
            }
            self::assertSame($records[0], $records[1]);
        }
    }

    private function product(int $id, array $categories, string $name = 'Item'): array
    {
        return ['product_id' => $id, 'public_name' => $name, 'public_summary' => 'Description',
            'min_amount_minor' => 100, 'categories' => $categories];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE shop_product_categories(product_id INT,category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_member_category_rules(category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,display_name TEXT,parent_path TEXT NULL,'
            . 'sort_order INT,visible_in_menu INT,description TEXT NULL)');
        return $pdo;
    }
}
