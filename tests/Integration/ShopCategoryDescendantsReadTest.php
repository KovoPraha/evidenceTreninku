<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CategoryDescendantsBaseline.php';
require_once dirname(__DIR__) . '/Support/CategoryMenuFixture.php';
require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';

final class ShopCategoryDescendantsReadTest extends TestCase
{
    public function testSingleCallNodeReuseQueryBudgetAndStandaloneMenuAreEquivalent(): void
    {
        $pdo = new \Tests\Support\QueryProfilePdo('sqlite::memory:');
        $pdo->exec('CREATE TABLE shop_product_categories(product_id INT,category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_member_category_rules(category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,display_name TEXT,parent_path TEXT NULL,'
            . 'sort_order INT,visible_in_menu INT,description TEXT NULL)');
        $products = \categoryMenuFixture($pdo, 80, 'tree');
        $products[0] += ['public_name' => 'LOCALHOST', 'public_summary' => '', 'min_amount_minor' => 100];
        $pdo->profiling = true;
        $pdo->profile = [];
        $before = \categoryDescendantsSelectBaseline($pdo, $products, ['kategorie' => 'LOCALHOST-0']);
        self::assertCount(6, $pdo->profile);
        $pdo->profile = [];
        $after = \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'LOCALHOST-0']);
        self::assertCount(3, $pdo->profile);
        self::assertSame($before, $after);
        $pdo->beginTransaction();
        $pdo->profile = [];
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'LOCALHOST-0']));
        self::assertCount(6, $pdo->profile);
        $pdo->rollBack();
        $nodes = \shopCategoryNodes($pdo);
        $pdo->profile = [];
        $pure = \shopStorefrontCategoryMenuFromNodes($nodes, $products);
        self::assertSame([], $pdo->profile);
        $wrapper = \shopStorefrontCategoryMenu($pdo, $products);
        self::assertCount(3, $pdo->profile);
        self::assertSame($pure, $wrapper);
        self::assertSame(\categoryDescendantsMenuBaseline($pdo, $products), $wrapper);
        self::assertSame($nodes, \shopCategoryNodes($pdo));
        self::assertSame([], \shopStorefrontCategoryMenuFromNodes([], $products));
    }

    public function testBreadthFirstOrderAndCycleReentryAreExact(): void
    {
        $nodes = ['D' => ['parent_path' => 'B'], 'C' => ['parent_path' => 'R'],
            'B' => ['parent_path' => 'R'], 'E' => ['parent_path' => 'C'], 'R' => ['parent_path' => 'D']];
        $original = $nodes;
        self::assertSame(['R', 'C', 'B', 'E', 'D'], \shopCategoryDescendants($nodes, 'R'));
        foreach (array_keys($nodes) as $path) {
            self::assertSame(\categoryDescendantsBaseline($nodes, $path), \shopCategoryDescendants($nodes, $path));
        }
        self::assertSame($original, $nodes);
    }

    public function testIntegerAndStringKeysKeepStrictParentSemantics(): void
    {
        $nodes = [0 => ['parent_path' => '0'], 1 => ['parent_path' => 0],
            '01' => ['parent_path' => '0'], '+1' => ['parent_path' => '01'],
            '1.0' => ['parent_path' => 1], 'Other' => ['parent_path' => '1'],
            'Float' => ['parent_path' => 1.0], 'Array' => ['parent_path' => []],
            'Bool' => ['parent_path' => true], 'None' => ['parent_path' => null]];
        self::assertSame(['0', 0, '01', 1, '+1', '1.0'], \shopCategoryDescendants($nodes, '0'));
        foreach (['0', '1', '01', '+1', '1.0', 'Other', 'Float', 'Array', 'Bool', 'None', 'missing'] as $path) {
            self::assertSame(\categoryDescendantsBaseline($nodes, $path), \shopCategoryDescendants($nodes, $path));
        }
    }

    public function testNormalizationMissingRootsAndExceptionsRemainExact(): void
    {
        $nodes = ['Žluťoučký > Díl' => ['parent_path' => null], 'Leaf' => ['parent_path' => 'Žluťoučký > Díl']];
        foreach ([' Žluťoučký>Díl ', 'absent', '', 'broken >', 'two >> empty', "bad\npath", str_repeat('x', 501)] as $path) {
            $results = [];
            foreach (['categoryDescendantsBaseline', 'shopCategoryDescendants'] as $call) {
                try {
                    $results[] = ['result' => $call($nodes, $path)];
                } catch (\InvalidArgumentException $error) {
                    $results[] = [$error::class, $error->getMessage()];
                }
            }
            self::assertSame($results[0], $results[1]);
        }
        self::assertSame([], \shopCategoryDescendants([], 'Missing'));
    }

    public function testDeterministicGraphsAllStartingPointsAndReorderingMatch(): void
    {
        $seed = 137;
        for ($graph = 0; $graph < 60; $graph++) {
            $nodes = [];
            for ($i = 0; $i < 35; $i++) {
                $seed = ($seed * 48271) % 2147483647;
                $parent = $seed % 39;
                $nodes['Node-' . $i] = ['parent_path' => $parent < 35 ? 'Node-' . $parent : null];
            }
            if ($graph % 2 === 0) {
                $nodes = array_reverse($nodes, true);
            }
            foreach (array_keys($nodes) as $path) {
                self::assertSame(\categoryDescendantsBaseline($nodes, $path), \shopCategoryDescendants($nodes, $path));
            }
        }
    }

    public function testWholeSelectionFiltersSearchPagingFreshMetadataAndRollback(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE shop_product_categories(product_id INT,category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_member_category_rules(category_path TEXT)');
        $pdo->exec('CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,display_name TEXT,parent_path TEXT NULL,'
            . 'sort_order INT,visible_in_menu INT,description TEXT NULL)');
        \categoryMenuFixture($pdo, 80, 'tree');
        $products = [];
        for ($i = 1; $i <= 150; $i++) {
            $products[] = ['product_id' => $i, 'categories' => ['LOCALHOST-' . ($i % 80)],
                'public_name' => 'Žluťoučký ' . $i, 'public_summary' => 'LOCALHOST', 'min_amount_minor' => 1000 - $i];
        }
        $inputs = [[], ['kategorie' => 'LOCALHOST-0'], ['kategorie' => 'LOCALHOST-1', 'strana' => 3],
            ['kategorie' => 'LOCALHOST-79'], ['kategorie' => 'missing'], ['kategorie' => 'bad >'],
            ['kategorie' => 'LOCALHOST-0', 'q' => 'ŽLUŤOUČKÝ 1', 'razeni' => 'price'],
            ['kategorie' => 'LOCALHOST-0', 'razeni' => 'name', 'strana' => 999]];
        foreach ($inputs as $input) {
            self::assertSame(\categoryDescendantsSelectBaseline($pdo, $products, $input), \shopCatalogPageSelect($pdo, $products, $input));
        }
        $input = ['kategorie' => 'LOCALHOST-0', 'q' => 'ignored'];
        self::assertSame(
            \categoryDescendantsSelectBaseline($pdo, $products, $input, [4 => true]),
            \shopCatalogPageSelect($pdo, $products, $input, [4 => true])
        );
        $before = \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'LOCALHOST-0']);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE shop_category_meta SET parent_path=NULL WHERE category_path='LOCALHOST-1'");
        $inside = \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'LOCALHOST-0']);
        self::assertNotSame($before, $inside);
        self::assertSame(\categoryDescendantsSelectBaseline($pdo, $products, ['kategorie' => 'LOCALHOST-0']), $inside);
        $pdo->rollBack();
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, ['kategorie' => 'LOCALHOST-0']));
    }
}
