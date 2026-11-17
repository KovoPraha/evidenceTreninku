<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CategoryMenuBaseline.php';

final class ShopCategoryMenuReadTest extends TestCase
{
    public function testHiddenAncestorsVirtualPathsAndDuplicateProductsMatchExactly(): void
    {
        $pdo = $this->database();
        $this->meta($pdo, 'Root', 'Root 10', null, 0, 0);
        $this->meta($pdo, 'Leaf', 'Leaf 2', 'Root');
        $this->meta($pdo, 'Hidden', 'Hidden', 'Leaf', 0, 0);
        $this->meta($pdo, 'Sibling', 'Leaf 10', 'Root');
        $this->meta($pdo, 'Empty', 'Empty', null);
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,'Virtual > Child'),(2,'Sibling')");
        $pdo->exec("INSERT INTO shop_member_category_rules VALUES('Rule > Only')");
        $products = [['product_id' => 1, 'categories' => ['Leaf', 'Hidden', 'Virtual > Child']],
            ['product_id' => 1, 'categories' => ['Sibling']], ['product_id' => 2, 'categories' => ['Leaf', 'Leaf']],
            ['categories' => ['Leaf', '', 'broken >']], ['product_id' => -3, 'categories' => ['Hidden']],
            ['product_id' => 9, 'categories' => []]];
        $original = $products;
        $actual = \shopStorefrontCategoryMenu($pdo, $products);
        self::assertSame(\categoryMenuBaseline($pdo, $products), $actual);
        self::assertSame($original, $products);
        $byPath = array_column($actual, null, 'category_path');
        self::assertArrayNotHasKey('Root', $byPath);
        self::assertArrayNotHasKey('Hidden', $byPath);
        self::assertArrayNotHasKey('Empty', $byPath);
        self::assertSame(4, $byPath['Leaf']['product_count']);
        self::assertSame(['Leaf', 'Hidden'], $byPath['Leaf']['descendant_paths']);
        self::assertSame('Root 10 › Leaf 2', $byPath['Leaf']['menu_label']);
    }

    public function testNumericPathsRetainStrictBfsTypesAndCyclesTerminate(): void
    {
        $pdo = $this->database();
        $this->meta($pdo, '0', 'Zero', '0');
        $this->meta($pdo, 'A', 'Á category', 'B');
        $this->meta($pdo, 'B', 'a category', 'A');
        $this->meta($pdo, 'C', 'Child', 'A');
        $this->meta($pdo, 'Orphan', 'Orphan', 'Missing');
        $products = [['product_id' => 1, 'categories' => ['0', 'C', 'Orphan']]];
        // Existing node construction rejects integer-coerced numeric paths.
        // Preserve that exception as well as successful graph results.
        $errors = [];
        foreach (['categoryMenuBaseline', 'shopStorefrontCategoryMenu'] as $call) {
            try {
                $call($pdo, $products);
                self::fail('Numeric path must preserve the existing TypeError.');
            } catch (\TypeError $error) {
                $errors[] = [$error::class, $error->getMessage()];
            }
        }
        self::assertSame($errors[0], $errors[1]);
        $pdo->exec("DELETE FROM shop_category_meta WHERE category_path='0'");
        $products[0]['categories'] = ['C', 'Orphan'];
        $actual = \shopStorefrontCategoryMenu($pdo, $products);
        self::assertSame(\categoryMenuBaseline($pdo, $products), $actual);
        $byPath = array_column($actual, null, 'category_path');
        self::assertSame(['A', 'B', 'C'], $byPath['A']['descendant_paths']);
        self::assertSame(1, $byPath['A']['product_count']);
        self::assertArrayHasKey('Orphan', $byPath);
    }

    public function testFreshCallsObserveMetadataProductsAndRollback(): void
    {
        $pdo = $this->database();
        $this->meta($pdo, 'Root', 'Root', null);
        $this->meta($pdo, 'Child', 'Child', 'Root');
        $products = [['product_id' => 1, 'categories' => ['Child']]];
        $before = \shopStorefrontCategoryMenu($pdo, $products);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE shop_category_meta SET visible_in_menu=0 WHERE category_path='Root'");
        $inside = \shopStorefrontCategoryMenu($pdo, $products);
        self::assertNotSame($before, $inside);
        self::assertSame(\categoryMenuBaseline($pdo, $products), $inside);
        $pdo->rollBack();
        self::assertSame($before, \shopStorefrontCategoryMenu($pdo, $products));
        self::assertSame([], \shopStorefrontCategoryMenu($pdo, []));
        $other = $this->database();
        self::assertSame([], \shopStorefrontCategoryMenu($other, $products));
    }

    public function testDeterministicMixedGraphsAndReadOnlyDataMatchFrozenMenu(): void
    {
        for ($seed = 0; $seed < 12; $seed++) {
            $pdo = $this->database();
            for ($i = 0; $i < 30; $i++) {
                $this->meta($pdo, 'N' . $i, 'Group ' . ($i % 7), $i % 5 === 0 ? null : 'N' . (($i * 17 + $seed * 5) % 30), $i % 3, $i % 4 === 0 ? 0 : 1);
            }
            $products = [];
            for ($i = 0; $i < 80; $i++) {
                $products[] = ['product_id' => $i % 23, 'categories' => ['N' . (($i * 11 + $seed) % 30), 'N' . (($i + $seed) % 30)]];
            }
            $rows = $pdo->query('SELECT * FROM shop_category_meta ORDER BY category_path')->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(\categoryMenuBaseline($pdo, $products), \shopStorefrontCategoryMenu($pdo, $products), 'Graph ' . $seed);
            self::assertSame($rows, $pdo->query('SELECT * FROM shop_category_meta ORDER BY category_path')->fetchAll(PDO::FETCH_ASSOC));
        }
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

    private function meta(PDO $pdo, string $path, string $label, ?string $parent, int $order = 0, int $visible = 1): void
    {
        $pdo->prepare('INSERT INTO shop_category_meta VALUES(?,?,?,?,?,NULL)')->execute([$path, $label, $parent, $order, $visible]);
    }
}
