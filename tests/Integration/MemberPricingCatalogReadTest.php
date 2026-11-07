<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/MemberPricingReadFixture.php';
require_once dirname(__DIR__) . '/Support/StorefrontReadFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';

final class MemberPricingCatalogReadTest extends TestCase
{
    private function database(int $products = 3): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        \memberPricingReadSeed($pdo, $products);
        return $pdo;
    }

    private function assertParity(PDO $pdo, array $catalog, int $account = 1, string $on = '2026-11-07'): array
    {
        $before = \beforeMemberPricingCatalog($pdo, $account, $catalog, $on);
        $current = $catalog;
        foreach ($current as &$product) {
            foreach ($product['variants'] as &$variant) {
                if (!isset($variant['monthly_tariff_id'])) {
                    \shopMemberPriceApplyToItem($pdo, $account, $variant, $on);
                }
            }
            unset($variant);
        }
        unset($product);
        self::assertSame($before, $current, 'Frozen baseline must also match the canonical checkout price helper.');
        $after = \shopMemberPricesApplyToCatalog($pdo, $account, $catalog, $on);
        self::assertSame($before, $after);
        return $after;
    }

    public function testCompleteVariantValuesAndOrderingWithMixedRulesCurrenciesAndTariffs(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_member_product_prices(team_id,product_id,amount_minor,currency,created_by_trainer_id) "
            . "VALUES(10,1,99999,'CZK',7),(11,2,8000,'EUR',7)");
        $pdo->exec("INSERT INTO shop_product_categories VALUES(10,1,'Doplňky',0,1),(11,2,'Doplňky',0,1)");
        $pdo->exec("INSERT INTO shop_member_category_rules(team_id,category_path,discount_type,value_minor_or_basis_points,currency,created_by_trainer_id) "
            . "VALUES(10,'Doplňky','percentage',5000,NULL,7),(11,'Doplňky','percentage',1000,NULL,7)");
        $catalog = \memberPricingReadCatalog(3, true);
        foreach (['EUR', 'CZK', '', 'USD'] as $index => $currency) {
            $catalog[0]['variants'][] = ['variant_id' => 30 + $index, 'product_id' => 1,
                'amount_minor' => 1000 + $index, 'currency' => $currency];
        }
        $catalog[0]['variants'][] = ['variant_id' => 40, 'product_id' => 1, 'amount_minor' => 0, 'currency' => 'CZK'];
        $catalog[0]['variants'][] = ['variant_id' => 41, 'product_id' => 1, 'amount_minor' => -1, 'currency' => 'CZK'];
        $catalog[0]['variants'][] = ['variant_id' => 42, 'product_id' => 0, 'currency' => 'CZK'];
        $catalog[0]['variants'][] = ['variant_id' => 43, 'product_id' => 1, 'amount_minor' => 220000,
            'currency' => 'CZK', 'monthly_tariff_id' => 8];
        foreach ([0, 1, 2, 3, 999] as $account) {
            $after = $this->assertParity($pdo, $catalog, $account);
            self::assertSame($catalog[0]['variants'][8], $after[0]['variants'][8]);
        }
    }

    public function testActualStorefrontPresentationCardsRetainEveryFieldAfterPricing(): void
    {
        $source = \Tests\Support\storefrontReadFixture(100, false);
        $source->exec("UPDATE shop_product_categories SET category_path='Oblečení'");
        $catalog = \shopPublicCatalogCards($source);
        self::assertCount(100, $catalog);
        $result = $this->assertParity($this->database(100), $catalog);
        self::assertTrue($result[0]['variants'][0]['member_price']['is_member_price']);
        self::assertSame($catalog[0]['images'], $result[0]['images']);
    }

    public function testMembershipAndRelationshipBoundariesStatusesAndChangesAreFresh(): void
    {
        $pdo = $this->database();
        $catalog = \memberPricingReadCatalog(3, true);
        $changes = [
            "UPDATE club_roster_members SET valid_to='2026-11-07'",
            "UPDATE account_person_roles SET valid_to='2026-11-07 00:00:00'",
            "UPDATE account_person_roles SET valid_to=NULL,valid_from='2026-11-08'",
            "UPDATE account_person_roles SET valid_from='2026-01-01',status='pending'",
            "UPDATE account_person_roles SET status='approved',relation_role='other'",
            "UPDATE account_person_roles SET relation_role='guardian'; UPDATE club_roster_members SET status='removed'",
            "UPDATE club_roster_members SET status='active'; UPDATE club_teams SET status='closed'",
            "UPDATE club_teams SET status='active'; UPDATE verejni_uzivatele SET aktivni=0 WHERE id=1",
            "UPDATE verejni_uzivatele SET aktivni=1,email_overeno=0 WHERE id=1",
            "UPDATE verejni_uzivatele SET email_overeno=1 WHERE id=1; UPDATE shop_member_category_rules SET active=0",
            "UPDATE shop_member_category_rules SET active=1,value_minor_or_basis_points=10000 WHERE discount_type='percentage'",
        ];
        foreach ($changes as $sql) {
            $this->assertParity($pdo, $catalog);
            $pdo->exec($sql);
            foreach (['2026-11-06', '2026-11-07', '2026-11-08'] as $on) {
                $this->assertParity($pdo, $catalog, 1, $on);
            }
        }
    }

    public function testEqualCandidateAmountsRetainTheOriginalSourceLabelAndTieBreak(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE shop_member_category_rules SET value_minor_or_basis_points=1000,discount_type='percentage'");
        $pdo->exec("INSERT INTO shop_product_categories VALUES(10,1,'Doplňky',0,1)");
        $pdo->exec("INSERT INTO shop_member_category_rules(team_id,category_path,discount_type,value_minor_or_basis_points,currency,created_by_trainer_id) "
            . "VALUES(10,'Doplňky','percentage',1000,NULL,7)");
        $after = $this->assertParity($pdo, \memberPricingReadCatalog(3, true));
        self::assertSame(10, $after[0]['variants'][0]['member_price']['team_id']);
    }

    public function testBatchBudgetAndDistinctCategoryRequestsAcrossChunkBoundaries(): void
    {
        foreach ([12, 100, 500, 501] as $count) {
            $pdo = $this->database($count);
            $catalog = \memberPricingReadCatalog($count, true);
            $this->assertParity($pdo, $catalog);
            $pdo->profile = [];
            $pdo->profiling = true;
            \shopMemberPricesApplyToCatalog($pdo, 1, $catalog, '2026-11-07');
            $pdo->profiling = false;
            self::assertLessThanOrEqual(2 + 2 * (int) ceil($count / 500), count($pdo->profile));
        }
        $pdo = $this->database(85);
        for ($id = 1; $id <= 85; $id++) {
            $pdo->prepare('UPDATE shop_product_categories SET category_path=? WHERE product_id=?')->execute(['Category ' . $id, $id]);
        }
        $this->assertParity($pdo, \memberPricingReadCatalog(85, true));
        $pdo->profile = [];
        $pdo->profiling = true;
        \shopMemberPricesApplyToCatalog($pdo, 1, \memberPricingReadCatalog(85, true), '2026-11-07');
        $pdo->profiling = false;
        self::assertCount(5, $pdo->profile); // Actor/teams, prices, categories, two rule batches.
    }

    public function testTransactionsUseTheExactOriginalSqlAndObserveRuleChanges(): void
    {
        $pdo = $this->database();
        $catalog = \memberPricingReadCatalog(12);
        $this->assertParity($pdo, $catalog);
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE shop_member_category_rules SET active=0');
            $pdo->profile = [];
            $pdo->profiling = true;
            $expected = \beforeMemberPricingCatalog($pdo, 1, $catalog, '2026-11-07');
            $queries = array_column($pdo->profile, 'sql');
            $pdo->profile = [];
            $actual = \shopMemberPricesApplyToCatalog($pdo, 1, $catalog, '2026-11-07');
            self::assertSame($expected, $actual);
            self::assertSame($queries, array_column($pdo->profile, 'sql'));
            self::assertFalse($actual[0]['variants'][0]['member_price']['is_member_price']);
        } finally {
            $pdo->profiling = false;
            $pdo->rollBack();
        }
    }

    public function testMissingPricingTablesAndEmptyCatalogKeepCanonicalPublicOutput(): void
    {
        $pdo = new PDO('sqlite::memory:');
        self::assertSame([], \shopMemberPricesApplyToCatalog($pdo, 1, [], '2026-11-07'));
        $this->assertParity($pdo, \memberPricingReadCatalog(2));
    }
}
