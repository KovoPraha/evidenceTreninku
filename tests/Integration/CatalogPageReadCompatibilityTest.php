<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogPageFixture.php';

final class CatalogPageReadCompatibilityTest extends TestCase
{
    private function parity(PDO $pdo, ?int $account, array $input = []): array
    {
        $before = \beforeShopCatalogPage($pdo, $account, $input);
        $after = \shopCatalogPageRead($pdo, $account, $input);
        self::assertSame($before, $after);
        return $after;
    }

    public function testPageEdgesSearchCategoriesAndEverySortHaveIdenticalCardsAndCounts(): void
    {
        foreach ([0, 1, 12, 13, 48] as $size) {
            $pdo = \catalogPageFixture($size);
            foreach ([null, 0, 1, 2, 3] as $account) {
                foreach ([[], ['strana' => -3], ['strana' => 99], ['strana' => 2, 'razeni' => 'price'],
                    ['razeni' => 'name'], ['razeni' => 'invalid'], ['kategorie' => 'Test'],
                    ['kategorie' => ' Test> Goods '], ['kategorie' => 'missing'], ['kategorie' => '>'],
                    ['q' => 'ŽLUTÝ'], ['q' => 'suffix_%'], ['q' => '00001 LOCALHOST'],
                    ['q' => '00_01'], ['q' => ' no match '], ['q' => 'suffix_%', 'razeni' => 'price', 'strana' => 2]] as $input) {
                    $state = $this->parity($pdo, $account, $input);
                    self::assertLessThanOrEqual(12, count($state['products']));
                }
            }
        }
    }

    public function testVariantOrderPriceSortingStockImportAndFreshReads(): void
    {
        $pdo = \catalogPageFixture(160);
        $pdo->exec("INSERT INTO shop_variants VALUES(1000,13,'A-first','{\"Barva\":\"Modrá\"}','fixed',800,'CZK','0',1,'active')");
        $pdo->exec("INSERT INTO shop_variants VALUES(1001,13,'B-hidden','{}','fixed',1,'CZK','10',0,'active')");
        $pdo->exec('UPDATE shop_products SET sort_order=999-id');
        $pdo->exec("UPDATE shop_variants SET stock_quantity_decimal=NULL WHERE id=2");
        $pdo->exec("UPDATE shop_variants SET stock_quantity_decimal='0' WHERE id=3");
        $pdo->exec("UPDATE shop_product_publications SET status='draft' WHERE product_id=4");
        $pdo->exec("UPDATE shop_products SET origin='import',external_product_key='shoptet:pair:' || id WHERE id IN (157,158,159)");
        $pdo->exec("UPDATE shop_product_publications SET public_name='Kroužek nejmenších' WHERE product_id IN (157,158,159)");
        $pdo->exec("INSERT INTO shop_product_categories VALUES(900,13,' Other > Path ',0,1),(901,13,'Test > Goods',0,2),(902,13,'Test>Odd',0,3)");
        foreach ([null, 1] as $account) {
            foreach ([[], ['razeni' => 'price'], ['strana' => 14], ['kategorie' => 'Other'], ['q' => 'nejmen']] as $input) {
                $this->parity($pdo, $account, $input);
            }
        }
        $state = $this->parity($pdo, 1, ['razeni' => 'price']);
        self::assertSame(13, $state['products'][0]['product_id']);
        self::assertTrue($state['products'][0]['has_member_price']);
        $pdo->exec("UPDATE account_person_roles SET status='revoked'");
        $state = $this->parity($pdo, 1, ['razeni' => 'price']);
        self::assertFalse($state['products'][0]['has_member_price']);
        $pdo->exec("UPDATE shop_member_product_prices SET active=0");
        $this->parity($pdo, 1);
    }

    public function testProgramsCapacityTermsAndMonthlyFlagsRemainCanonical(): void
    {
        $pdo = \catalogPageFixture(15, true);
        $this->parity($pdo, null);
        $pdo->exec('UPDATE club_program_offers SET capacity=0 WHERE id=1');
        $pdo->exec("UPDATE club_program_offers SET sales_open_at='2099-01-01' WHERE id=2");
        $pdo->exec("UPDATE club_program_offers SET sales_close_at='2000-01-01' WHERE id=3");
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn' WHERE scope_key='program:4'");
        $this->parity($pdo, 1, ['razeni' => 'price']);
        $pdo->exec("UPDATE club_program_offers SET status='inactive' WHERE id=5");
        $this->parity($pdo, null, ['q' => 'suffix_%']);
        $pdo->exec('CREATE TABLE monthly_membership_tariffs(id INTEGER PRIMARY KEY,variant_id INTEGER,status TEXT,amount_minor INTEGER,billing_interval TEXT)');
        $pdo->exec("INSERT INTO monthly_membership_tariffs VALUES(1,6,'active',10006,'month')");
        $this->parity($pdo, 1);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMonthlyTariffsEnabledDoNotReceiveMemberPrice(): void
    {
        define('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', true);
        $pdo = \catalogPageFixture(15, true);
        $pdo->exec('CREATE TABLE monthly_membership_tariffs(id INTEGER PRIMARY KEY,variant_id INTEGER,status TEXT,amount_minor INTEGER,billing_interval TEXT)');
        $pdo->exec("INSERT INTO monthly_membership_tariffs VALUES(1,1,'active',10001,'year'),(2,2,'inactive',10002,'month'),(3,3,'active',1,'month')");
        $state = $this->parity($pdo, 1);
        self::assertSame(1, $state['products'][0]['variants'][0]['monthly_tariff_id']);
        self::assertArrayNotHasKey('member_price', $state['products'][0]['variants'][0]);
    }

    public function testTransactionsUseOriginalReadsAndEmptySelectionLoadsNoFullVariants(): void
    {
        $source = \catalogPageFixture(30);
        $pdo = new \Tests\Support\QueryProfilePdo('sqlite::memory:');
        \catalogPageCopyFixture($source, $pdo);
        $pdo->beginTransaction();
        $this->parity($pdo, 1);
        $pdo->profiling = true;
        $pdo->profile = [];
        \beforeShopCatalogPage($pdo, 1, []);
        $before = $pdo->profile;
        $pdo->profile = [];
        \shopCatalogPageRead($pdo, 1, []);
        $shape = static fn (array $q): array => [$q['sql'], $q['parameters'], $q['fetched_rows']];
        self::assertSame(array_map($shape, $before), array_map($shape, $pdo->profile));
        $pdo->profiling = false;
        $pdo->rollBack();
        $pdo->profile = [];
        $pdo->profiling = true;
        $state = \shopCatalogPageRead($pdo, null, ['q' => 'missing']);
        $pdo->profiling = false;
        self::assertSame([], $state['products']);
        foreach ($pdo->profile as $query) {
            self::assertFalse(str_contains($query['sql'], 'v.attributes_json'));
        }
        $pdo->profile = [];
        $pdo->profiling = true;
        $this->parity($pdo, null);
        $pdo->profiling = false;
        self::assertSame([], \shopPublicCatalogCards($pdo, []));
    }
}
