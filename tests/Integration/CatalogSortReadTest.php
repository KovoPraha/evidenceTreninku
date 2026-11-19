<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogSortBaseline.php';
require_once dirname(__DIR__) . '/Support/CatalogSortFixture.php';
require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';

final class CatalogSortReadTest extends TestCase
{
    public function testEveryPageSortFilterAndDuplicateRecordPreservesTheCompleteTypedState(): void
    {
        $pdo = $this->database();
        $products = \catalogSortFixture($pdo, 180, 80, 'tree');
        $names = ['02', '2', '2.0', '+2', '2e0', '10', '1a', 'ŽLUŤOUČKÝ', 'žluťoučký', 'Ö', '', ' A ', "\xFF"];
        foreach ($products as $i => &$product) {
            $product['public_name'] = $names[(($i * 17) % count($names))];
            $product['product_id'] = $i % 5;
            $product['min_amount_minor'] = ($i % 4) - 2;
            $product['sort_order'] = $i % 3 === 0 ? null : $i % 2;
            $product['record_position'] = $i;
            $product['public_summary'] = $i % 2 === 0 ? 'NEPLATIT Needle' : 'NEPLATIT';
        }
        unset($product);
        $products[] = $products[0];
        $original = $products;
        foreach (['order', 'name', 'price', 'invalid'] as $sort) {
            foreach ([[], ['kategorie' => 'LOCALHOST-0'], ['q' => 'NEEDLE'], ['q' => 'override']] as $filter) {
                foreach (range(1, 17) as $page) {
                    $input = $filter + ['razeni' => $sort, 'strana' => $page];
                    $matches = ($filter['q'] ?? '') === 'override' ? [2 => true, 4 => true] : null;
                    $before = \catalogSortBaseline($pdo, $products, $input, $matches);
                    $after = \shopCatalogPageSelect($pdo, $products, $input, $matches);
                    self::assertSame($before, $after);
                    self::assertSame(hash('sha256', serialize($before)), hash('sha256', serialize($after)));
                }
            }
        }
        self::assertSame($original, $products);
    }

    public function testCompletelyEqualTuplesKeepInputOrderWithoutIdDeduplication(): void
    {
        $pdo = $this->database();
        $products = [];
        for ($i = 0; $i < 31; $i++) {
            $products[] = ['product_id' => 7, 'public_name' => $i % 2 === 0 ? '02' : '2',
                'categories' => [], 'min_amount_minor' => 100, 'record_position' => $i];
        }
        foreach (['order', 'price', 'name'] as $sort) {
            $positions = [];
            for ($page = 1; $page <= 3; $page++) {
                $state = \shopCatalogPageSelect($pdo, $products, ['razeni' => $sort, 'strana' => $page]);
                $positions = array_merge($positions, array_column($state['products'], 'record_position'));
                self::assertSame(31, $state['filteredProductCount']);
            }
            self::assertSame(range(0, 30), $positions);
        }
    }

    public function testFallbackPreservesConversionsWarningsExceptionsAndStringableCallCounts(): void
    {
        $pdo = $this->database();
        $cases = [['public_name', null], ['public_name', 7], ['public_name', []],
            ['product_id', '02'], ['product_id', []], ['product_id', null],
            ['min_amount_minor', '0100'], ['min_amount_minor', []], ['sort_order', '2'], ['sort_order', []],
            ['public_name', 'missing'], ['product_id', 'missing'], ['min_amount_minor', 'missing']];
        foreach (['order', 'name', 'price'] as $sort) {
            foreach (array_merge($cases, [['public_name', 'stringable'], ['public_name', 'throwing']]) as [$key, $value]) {
                $records = [];
                foreach (['catalogSortBaseline', 'shopCatalogPageSelect'] as $call) {
                    $counter = (object) ['calls' => 0];
                    $products = $this->products();
                    if (in_array($value, ['stringable', 'throwing'], true)) {
                        $products[3][$key] = new class ($counter, $value === 'throwing') implements \Stringable {
                            public function __construct(private object $counter, private bool $throw)
                            {
                            }

                            public function __toString(): string
                            {
                                $this->counter->calls++;
                                if ($this->throw) {
                                    throw new \RuntimeException('LOCALHOST conversion');
                                }
                                return 'Žluťoučký';
                            }
                        };
                    } elseif ($value === 'missing') {
                        unset($products[3][$key]);
                    } else {
                        $products[3][$key] = $value;
                    }
                    $warnings = [];
                    set_error_handler(static function (int $code, string $message) use (&$warnings): bool {
                        $warnings[] = [$code, $message];
                        return true;
                    });
                    try {
                        $state = $call($pdo, $products, ['razeni' => $sort]);
                        // Strip only the intentionally fresh Stringable instance, after observing conversions.
                        foreach ($state['products'] as &$product) {
                            if (is_object($product['public_name'] ?? null)) {
                                $product['public_name'] = 'OBSERVED_STRINGABLE';
                            }
                        }
                        unset($product);
                        $records[] = ['state' => $state, 'warnings' => $warnings, 'calls' => $counter->calls];
                    } catch (\Throwable $error) {
                        $records[] = ['error' => [$error::class, $error->getMessage()], 'warnings' => $warnings, 'calls' => $counter->calls];
                    } finally {
                        restore_error_handler();
                    }
                }
                self::assertSame($records[0], $records[1], $sort . '/' . $key);
            }
        }
    }

    public function testEmptySingletonAndFilteredAwayInvalidFieldsAreNotEvaluatedEarly(): void
    {
        $pdo = $this->database();
        foreach ([[], [['categories' => []]], [['categories' => [], 'product_id' => 9, 'public_summary' => 'NEPLATIT']]] as $products) {
            foreach (['order', 'price', 'name'] as $sort) {
                self::assertSame(\catalogSortBaseline($pdo, $products, ['razeni' => $sort]), \shopCatalogPageSelect($pdo, $products, ['razeni' => $sort]));
            }
        }
        $products = $this->products();
        unset($products[3]['product_id'], $products[3]['min_amount_minor']);
        $products[3]['public_name'] = [];
        $input = ['kategorie' => 'missing', 'razeni' => 'price'];
        self::assertSame(\catalogSortBaseline($pdo, $products, $input), \shopCatalogPageSelect($pdo, $products, $input));
    }

    public function testMetadataRollbackAndBothSqlBudgetsKeepTheOriginalTransactionSequence(): void
    {
        $pdo = $this->database();
        $products = \catalogSortFixture($pdo, 60, 30, 'tree');
        $input = ['kategorie' => 'LOCALHOST-0'];
        $pdo->profiling = true;
        $pdo->profile = [];
        $before = \catalogSortBaseline($pdo, $products, $input);
        $profile = $pdo->profile;
        self::assertCount(3, $profile);
        $pdo->profile = [];
        self::assertSame($before, \shopCatalogPageSelect($pdo, $products, $input));
        self::assertCount(3, $pdo->profile);
        $pdo->beginTransaction();
        $pdo->exec("UPDATE shop_category_meta SET parent_path=NULL WHERE category_path='LOCALHOST-1'");
        $pdo->profile = [];
        $inside = \catalogSortBaseline($pdo, $products, $input);
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

    private function products(): array
    {
        $products = [];
        for ($i = 0; $i < 8; $i++) {
            $products[] = ['product_id' => $i, 'categories' => [], 'public_name' => 'LOCALHOST ' . (8 - $i),
                'public_summary' => 'NEPLATIT', 'min_amount_minor' => 100 + $i];
        }
        return $products;
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
