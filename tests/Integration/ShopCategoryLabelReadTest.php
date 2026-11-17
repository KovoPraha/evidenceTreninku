<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CategoryLabelBaseline.php';
require_once dirname(__DIR__) . '/Support/CategoryMenuFixture.php';

final class ShopCategoryLabelReadTest extends TestCase
{
    public function testLabelsKeepExactOrderWhitespaceUnicodeAndParentTermination(): void
    {
        $nodes = [
            'Root' => ['display_name' => '  Kořen Ž  ', 'parent_path' => null],
            'Child' => ['display_name' => 'část › už uvnitř', 'parent_path' => 'Root'],
            'Leaf' => ['display_name' => '', 'parent_path' => 'Child'],
            'Orphan' => ['display_name' => 0, 'parent_path' => 'Missing'],
            'IntegerParent' => ['display_name' => false, 'parent_path' => 0],
            'EmptyParent' => ['display_name' => 'E', 'parent_path' => ''],
            0 => ['display_name' => 123, 'parent_path' => null],
            '01' => ['display_name' => true, 'parent_path' => '0'],
        ];
        foreach (['Root', 'Child', 'Leaf', 'Orphan', 'IntegerParent', 'EmptyParent', '0', '01', 'Missing', ''] as $path) {
            self::assertSame(\categoryLabelBaseline($nodes, $path), \shopCategoryMenuLabel($nodes, $path), $path);
        }
        self::assertSame('  Kořen Ž   › část › už uvnitř › ', \shopCategoryMenuLabel($nodes, 'Leaf'));
        self::assertSame('123 › 1', \shopCategoryMenuLabel($nodes, '01'));
        self::assertSame('', \shopCategoryMenuLabel($nodes, 'IntegerParent'));
    }

    public function testCycleLabelsPreserveTheStartingPointAndDoNotReuseStaleState(): void
    {
        $nodes = ['A' => ['display_name' => 'A', 'parent_path' => 'B'],
            'B' => ['display_name' => 'B', 'parent_path' => 'C'],
            'C' => ['display_name' => 'C', 'parent_path' => 'A'],
            'Self' => ['display_name' => 'Self', 'parent_path' => 'Self']];
        $original = $nodes;
        foreach (['A', 'B', 'C', 'Self', 'A'] as $path) {
            self::assertSame(\categoryLabelBaseline($nodes, $path), \shopCategoryMenuLabel($nodes, $path));
        }
        self::assertSame('C › B › A', \shopCategoryMenuLabel($nodes, 'A'));
        self::assertSame('A › C › B', \shopCategoryMenuLabel($nodes, 'B'));
        self::assertSame($original, $nodes);
        $nodes['A']['display_name'] = 'updated';
        self::assertSame('C › B › updated', \shopCategoryMenuLabel($nodes, 'A'));
        self::assertSame('C › B › A', \shopCategoryMenuLabel($original, 'A'));
    }

    public function testCompleteMenuMatchesCurrentFrozenBaselineAcrossGraphShapes(): void
    {
        foreach ([['flat', 30], ['tree', 1000], ['chain', 250], ['chain', 500], ['mixed', 80]] as [$shape, $count]) {
            $pdo = $this->database();
            $products = \categoryMenuFixture($pdo, $count, $shape);
            $rows = $pdo->query('SELECT * FROM shop_category_meta')->fetchAll(PDO::FETCH_ASSOC);
            self::assertSame(\categoryLabelMenuBaseline($pdo, $products), \shopStorefrontCategoryMenu($pdo, $products), $shape);
            self::assertSame($rows, $pdo->query('SELECT * FROM shop_category_meta')->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    public function testMetadataRollbackAndSeparateConnectionsRemainFresh(): void
    {
        $pdo = $this->database();
        $products = \categoryMenuFixture($pdo, 30, 'chain');
        $before = \shopStorefrontCategoryMenu($pdo, $products);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE shop_category_meta SET display_name='changed › Ž' WHERE category_path='LOCALHOST-0'");
        $changed = \shopStorefrontCategoryMenu($pdo, $products);
        self::assertNotSame($before, $changed);
        self::assertSame(\categoryLabelMenuBaseline($pdo, $products), $changed);
        $pdo->rollBack();
        self::assertSame($before, \shopStorefrontCategoryMenu($pdo, $products));
        self::assertSame([], \shopStorefrontCategoryMenu($this->database(), $products));
        self::assertSame([], \shopStorefrontCategoryMenu($pdo, []));
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
