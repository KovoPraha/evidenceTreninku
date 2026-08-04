<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_member_pricing.php';

final class ShopMemberPricingTest extends TestCase
{
    public function testAnonymousKeepsPublicPriceAndParentGetsCheapestEligibleRosterPrice():void
    {
        $pdo=$this->database();
        \shopMemberPricingSetCategoryRule($pdo,10,'Oblečení','percentage',1000,null,7,'Deset procent.');
        \shopMemberPricingSetCategoryRule($pdo,11,'Oblečení','fixed_discount',15000,'CZK',7,'Sleva 150 Kč.');
        $variant=['product_id'=>100,'amount_minor'=>100000,'currency'=>'CZK'];
        self::assertSame(100000,\shopMemberPriceQuoteForVariant($pdo,0,$variant,'2026-08-04')['effective_amount_minor']);
        $quote=\shopMemberPriceQuoteForVariant($pdo,1,$variant,'2026-08-04');
        self::assertSame(85000,$quote['effective_amount_minor']);
        self::assertSame(11,$quote['team_id']);
        self::assertSame('category',$quote['source_type']);
    }

    public function testProductPriceOverridesCategoryInsideTeamAndInactiveMembershipIsIgnored():void
    {
        $pdo=$this->database();
        \shopMemberPricingSetCategoryRule($pdo,10,'Oblečení','percentage',3000,null,7,'Kategorie.');
        \shopMemberPricingSetCategoryRule($pdo,11,'Oblečení','fixed_discount',15000,'CZK',7,'Druhá soupiska.');
        \shopMemberPricingSetProductPrice($pdo,10,100,80000,'CZK',7,'Přesná cena.');
        $quote=\shopMemberPriceQuoteForVariant($pdo,1,['product_id'=>100,'amount_minor'=>100000,'currency'=>'CZK'],'2026-08-04');
        self::assertSame(80000,$quote['effective_amount_minor']);
        self::assertSame('product',$quote['source_type']);
        $pdo->exec("UPDATE club_roster_members SET valid_to='2026-01-01' WHERE team_id=10");
        $quote=\shopMemberPriceQuoteForVariant($pdo,1,['product_id'=>100,'amount_minor'=>100000,'currency'=>'CZK'],'2026-08-04');
        self::assertSame(85000,$quote['effective_amount_minor']);
        self::assertSame(11,$quote['team_id']);
    }

    public function testRuleChangesAreAuditedAndDeactivationFallsBackToPublicPrice():void
    {
        $pdo=$this->database();
        $rule=\shopMemberPricingSetProductPrice($pdo,10,100,75000,'CZK',7,'Členská cena.');
        \shopMemberPricingDeactivate($pdo,'product',(int)$rule['id'],7,'Akce skončila.');
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_member_price_events')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT active FROM shop_member_product_prices')->fetchColumn());
        self::assertSame(100000,\shopMemberPriceQuoteForVariant($pdo,1,['product_id'=>100,'amount_minor'=>100000,'currency'=>'CZK'],'2026-08-04')['effective_amount_minor']);
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY)');$pdo->exec('INSERT INTO treneri VALUES(7)');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY)');$pdo->exec('INSERT INTO shop_products VALUES(100)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');
        $pdo->exec("INSERT INTO club_teams VALUES(10,'U15','active'),(11,'Dráha','active')");
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,50,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,10,50,'active','2026-01-01',NULL),(2,11,50,'active','2026-01-01',NULL)");
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)');
        $pdo->exec("INSERT INTO shop_product_categories VALUES(1,100,'Oblečení',1,0)");
        $migration=require dirname(__DIR__,2).'/migrations/20260805010000_shop_member_pricing.php';$migration['up']($pdo);
        return $pdo;
    }
}
