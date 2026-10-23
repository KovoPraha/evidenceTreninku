<?php
declare(strict_types=1);
namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/bin/club-release-impact.php';

final class ClubReleaseImpactTest extends TestCase
{
    private function database(): PDO
    {
        $p=new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $p->exec("CREATE TABLE treneri(id INTEGER,aktivni INTEGER,name TEXT);
            CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);
            CREATE TABLE shop_products(id INTEGER,offer_type TEXT,catalog_status TEXT);
            CREATE TABLE shop_variants(id INTEGER,product_id INTEGER,catalog_status TEXT,visible INTEGER,stock_quantity_decimal TEXT);
            CREATE TABLE shop_product_publications(product_id INTEGER,status TEXT);
            CREATE TABLE member_fee_plans(id INTEGER,status TEXT,starts_on TEXT,ends_on TEXT);
            CREATE TABLE family_weekly_summaries(id INTEGER,status TEXT);
            INSERT INTO treneri VALUES(1,1,'private-name-must-not-leak'),(2,1,'second'),(3,0,'inactive');
            INSERT INTO staff_user_positions VALUES(1,'coach'),(2,'coach'),(3,'coach');
            INSERT INTO shop_products VALUES(1,'goods','active'),(2,'program','active');
            INSERT INTO shop_variants VALUES(1,1,'active',1,NULL),(2,1,'active',1,'3'),(3,2,'active',1,NULL),(4,1,'draft',1,NULL);
            INSERT INTO shop_product_publications VALUES(1,'active'),(2,'active');
            INSERT INTO member_fee_plans VALUES(1,'active','2026-01-01',NULL),(2,'active','2026-01-01','2026-09-30'),(3,'active','2026-01-01',NULL);
            INSERT INTO family_weekly_summaries VALUES(1,'pending'),(2,'sent');");
        return $p;
    }

    public function testLegacySchemaIsCountedWithoutCreatingNewTablesOrLeakingNames(): void
    {
        $p=$this->database(); $before=$p->query('SELECT name,sql FROM sqlite_master ORDER BY name')->fetchAll();
        $r=\clubReleaseImpact($p,'2026-10-23');
        self::assertTrue($r['complete']); self::assertTrue($r['read_only_session']); self::assertFalse($r['deployment_ready']);
        foreach (['coaches_without_current_scope_grant'=>2,'published_goods_without_stock_opening'=>2,'published_goods_null_legacy_quantity'=>1,
            'active_fee_plans_without_any_month_agreement'=>2,'active_fee_plans_without_any_month_amount_decision'=>2,'pending_family_summaries_without_authorization_snapshot'=>1] as $key=>$expected) {
            self::assertSame($expected,$r['checks'][$key]['count']);
        }
        self::assertSame($before,$p->query('SELECT name,sql FROM sqlite_master ORDER BY name')->fetchAll());
        self::assertStringNotContainsString('private-name',json_encode($r));
        self::assertFalse($p->inTransaction()); self::assertSame(0,(int)$p->query('PRAGMA query_only')->fetchColumn());
    }

    public function testCurrentGrantsAndOverlappingAgreementsCountOnlyActualEvidence(): void
    {
        $p=$this->database();
        $p->exec("CREATE TABLE coach_scope_grants(trainer_id INTEGER,revoked_at TEXT,valid_from TEXT,valid_to TEXT);
            INSERT INTO coach_scope_grants VALUES(1,NULL,'2000-01-01',NULL),(2,NULL,'2000-01-01','2001-01-01');
            CREATE TABLE shop_stock_balances(variant_id INTEGER); INSERT INTO shop_stock_balances VALUES(2);
            CREATE TABLE member_fee_agreements(plan_id INTEGER,valid_from TEXT,valid_to TEXT);
            INSERT INTO member_fee_agreements VALUES(1,'2026-10-01','2026-10-31'),(3,'2026-09-01','2026-09-30');
            CREATE TABLE member_fee_period_decisions(plan_id INTEGER,period_key TEXT); INSERT INTO member_fee_period_decisions VALUES(1,'2026-10');
            CREATE TABLE notification_authorization_snapshots(queue_key TEXT,message_id INTEGER);
            INSERT INTO notification_authorization_snapshots VALUES('family_weekly_summaries',1);");
        $p->exec('PRAGMA query_only=ON');
        $r=\clubReleaseImpact($p,'2026-10-23');
        foreach (['coaches_without_current_scope_grant'=>1,'published_goods_without_stock_opening'=>1,'active_fee_plans_without_any_month_agreement'=>1,
            'active_fee_plans_without_any_month_amount_decision'=>1,'pending_family_summaries_without_authorization_snapshot'=>0] as $key=>$expected) {
            self::assertSame($expected,$r['checks'][$key]['count']);
        }
        self::assertSame(1,(int)$p->query('PRAGMA query_only')->fetchColumn());
    }

    public function testIncompleteSchemaIsUnknownNotFalseZero(): void
    {
        $p=new PDO('sqlite::memory:'); $r=\clubReleaseImpact($p,'2026-10-23');
        self::assertFalse($r['complete']); self::assertNull($r['checks']['coaches_without_current_scope_grant']['count']);
        self::assertSame('unknown_schema',$r['checks']['coaches_without_current_scope_grant']['status']);
    }

    public function testIdentityFailsClosedAndStorageCheckCreatesNothing(): void
    {
        self::assertTrue(\clubReleaseImpactIdentity('kis.kovopraha.cz',['JE_LOKALNE'=>false,'APP_BASE_URL'=>'https://kis.kovopraha.cz']));
        self::assertFalse(\clubReleaseImpactIdentity('kis.kovopraha.cz',['JE_LOKALNE'=>true,'APP_BASE_URL'=>'https://kis.kovopraha.cz']));
        self::assertFalse(\clubReleaseImpactIdentity('evil.example',['JE_LOKALNE'=>false,'APP_BASE_URL'=>'https://kis.kovopraha.cz']));
        self::assertFalse(\clubReleaseImpactIdentity('localhost',['JE_LOKALNE'=>true,'APP_BASE_URL'=>'https://kis.kovopraha.cz']));
        $root=dirname(__DIR__,2); $path=$root.'/missing-impact-storage-'.bin2hex(random_bytes(8));
        $r=\clubReleaseImpactPrivateStorage($root,$path);
        self::assertFalse($r['exists']); self::assertFalse($r['write_probe_performed']); self::assertDirectoryDoesNotExist($path);
        self::assertFalse(\clubReleaseImpactPrivateStorage($root,$root)['outside_application_tree']);
        $unconfigured=\clubReleaseImpactPrivateStorage($root,null);
        self::assertFalse($unconfigured['configured']);
        self::assertFalse($unconfigured['local_fallback']);
        self::assertFalse($unconfigured['exists']);
    }

    public function testProbeHasExplicitReadOnlyTransactionAndNoApplicationBootstrap(): void
    {
        $source=file_get_contents(dirname(__DIR__,2).'/bin/club-release-impact.php');
        self::assertStringContainsString('WITH CONSISTENT SNAPSHOT, READ ONLY',$source);
        self::assertStringContainsString("getenv('CLUB_RELEASE_IMPACT_CONFIRM') !== 'OVERIT-DOPAD-VYDANI'",$source);
        self::assertStringNotContainsString("require_once __DIR__.'/../db.php'",$source);
        self::assertDoesNotMatchRegularExpression('/\b(?:INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE)\b/i',$source);
    }

    public function testWorkflowUsesFixedHostAndSeparateExplicitReadOnlyOperation(): void
    {
        $workflow=file_get_contents(dirname(__DIR__,2).'/.github/workflows/production-drills.yml');
        self::assertStringContainsString("if: inputs.operace == 'overit-dopad-kluboveho-vydani'",$workflow);
        self::assertStringContainsString("test \"\$APP_HOST\" = 'kis.kovopraha.cz'",$workflow);
        self::assertStringContainsString("putenv('CLUB_RELEASE_IMPACT_CONFIRM=OVERIT-DOPAD-VYDANI');",$workflow);
        self::assertStringContainsString('.ok == true and .read_only_session == true',$workflow);
        self::assertStringContainsString("if: always() && inputs.operace == 'overit-dopad-kluboveho-vydani'",$workflow);
    }
}
