<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2) . '/bin/restore-production-uat-program.php';

final class ProductionUatFixtureRestoreTest extends TestCase
{
    public function testPreviewIsReadOnlyForExactInactiveFixture(): void
    {
        $pdo = $this->fixture();
        $result = \kisUatFixtureRestore($pdo,false);
        self::assertSame('dry-run',$result['mode']);
        self::assertSame('inactive',$result['before']);
        self::assertFalse($result['changed']);
        self::assertSame('inactive',$pdo->query('SELECT catalog_status FROM shop_products')->fetchColumn());
        self::assertSame('inactive',$pdo->query('SELECT catalog_status FROM shop_variants')->fetchColumn());
    }

    public function testChangedPriceOrImportedProductFailsClosed(): void
    {
        foreach ([
            "UPDATE shop_variants SET amount_minor=10100",
            "UPDATE shop_products SET origin='import'",
            "UPDATE shop_products SET external_product_key='shoptet:1'",
        ] as $mutation) {
            $pdo = $this->fixture();
            $pdo->exec($mutation);
            $this->expectGuardFailure($pdo);
        }
    }

    public function testMixedStatusAndExtraVariantFailClosed(): void
    {
        foreach ([
            "UPDATE shop_variants SET catalog_status='active'",
            "INSERT INTO shop_variants(id,product_id,sku,origin,visible,price_mode,amount_minor,currency,catalog_status) VALUES(2,1,'KP-OTHER','manual',1,'fixed',10000,'CZK','inactive')",
        ] as $mutation) {
            $pdo = $this->fixture();
            $pdo->exec($mutation);
            $this->expectGuardFailure($pdo);
        }
    }

    private function expectGuardFailure(PDO $pdo): void
    {
        try {
            \kisUatFixtureRestore($pdo,false);
            self::fail('An altered fixture must be rejected.');
        } catch (\KisUatFixtureRestoreFailure $exception) {
            self::assertNotSame('', $exception->category);
            self::assertFalse($pdo->inTransaction());
        }
    }

    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,origin TEXT,external_product_key TEXT,offer_type TEXT,visibility TEXT,catalog_status TEXT)');
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,origin TEXT,visible INTEGER,price_mode TEXT,amount_minor INTEGER,currency TEXT,catalog_status TEXT)');
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec('CREATE TABLE club_programs(id INTEGER PRIMARY KEY,code TEXT)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,code TEXT)');
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,product_id INTEGER,variant_id INTEGER,program_id INTEGER,team_id INTEGER,code TEXT,status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(1,'TEST - Cyklistický kroužek','manual','manual:0123456789abcdef0123456789abcdef','program','visible','inactive')");
        $pdo->exec("INSERT INTO shop_variants VALUES(1,1,'KP-TEST-UAT-KROUZEK','manual',1,'fixed',10000,'CZK','inactive')");
        $pdo->exec("INSERT INTO shop_product_publications VALUES(1,'inactive','TEST - Cyklistický kroužek','Dočasná produkční UAT nabídka. Po skončení testu bude deaktivována.')");
        $pdo->exec("INSERT INTO club_programs VALUES(1,'TEST-UAT-KROUZEK')");
        $pdo->exec("INSERT INTO club_teams VALUES(1,'TEST-UAT-DETI')");
        $pdo->exec("INSERT INTO club_program_offers VALUES(1,1,1,1,1,'TEST-UAT-KROUZEK-2026','active')");
        return $pdo;
    }
}
