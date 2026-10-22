<?php
declare(strict_types=1);
namespace Tests\Integration;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_checkout.php';
require_once dirname(__DIR__).'/Support/ShopNumberSeriesFixture.php';

final class ShopNumberSeriesTest extends TestCase
{
    private function database(bool $migrate=true): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');\shopNumberSeriesFixture($pdo,$migrate);return $pdo;
    }

    private function save(PDO $pdo, array $input): void
    {
        \shopNumberSeriesSave($pdo,7,$input+['revision'=>(int)$pdo->query('SELECT revision FROM shop_number_series_settings')->fetchColumn()],'LOCALHOST ověřené nastavení',true);
    }

    private function assign(PDO $pdo, int $order, string $date='2026-10-21 12:00:00+02:00'): string
    {
        $pdo->beginTransaction();
        try {$vs=\shopOrderAssignVariableSymbol($pdo,$order,new DateTimeImmutable($date));$pdo->commit();return $vs;}
        catch (\Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function testClassificationSequenceAndReplayDoNotConsumeSecondNumber(): void
    {
        $pdo=$this->database();$this->save($pdo,['action'=>'mapping','mapping_type'=>'catalog','mapping_key'=>'Zboží > Knihy','category_id'=>31]);
        self::assertSame('2630100001',$this->assign($pdo,1));
        self::assertSame('2630100001',$this->assign($pdo,1));
        $row=$pdo->query('SELECT * FROM shop_order_number_assignments')->fetch();
        self::assertSame('2026-3-01-00001',$row['number_snapshot']);self::assertSame('Knihy',$row['category_name_snapshot']);
        self::assertSame(2,(int)$pdo->query('SELECT next_number FROM shop_number_series_counters')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_accounting_items')->fetchColumn());
        self::assertSame('Zboží > Knihy',$pdo->query('SELECT catalog_path_snapshot FROM shop_order_accounting_items')->fetchColumn());
    }

    public function testMixedBasketRetainsPerItemCategoriesAndAllThreeSources(): void
    {
        $pdo=$this->database();$this->save($pdo,['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'501','category_id'=>31]);
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'502','category_id'=>32]);
        $pdo->exec('INSERT INTO club_event_order_items VALUES(1,2)');$pdo->exec('INSERT INTO public_velodrome_order_items VALUES(1,2)');
        self::assertSame('2690000001',$this->assign($pdo,2));
        self::assertSame([31,32,80,80],array_map('intval',$pdo->query("SELECT category_id FROM shop_order_accounting_items ORDER BY CASE source_type WHEN 'product' THEN 0 WHEN 'event' THEN 1 ELSE 2 END,source_item_id")->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame('2610000001',$this->assign($pdo,3));
        self::assertSame('2630100001',$this->assign($pdo,1));
    }

    public function testNearestConfiguredAncestorAndExplicitProductOverride(): void
    {
        $pdo=$this->database();$this->save($pdo,['action'=>'mapping','mapping_type'=>'catalog','mapping_key'=>'Zboží','category_id'=>30]);
        self::assertSame('2630000001',$this->assign($pdo,1));
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'catalog','mapping_key'=>'Zboží > Knihy','category_id'=>31]);
        self::assertSame('2630100001',$this->assign($pdo,4));
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'501','category_id'=>32]);
        self::assertSame('2630200001',$this->assign($pdo,5));
        $this->save($pdo,['action'=>'category','category_id'=>31,'group_code'=>3,'category_code'=>'01','name'=>'Nový název','active'=>'1']);
        $pdo->exec("UPDATE shop_product_categories SET category_path='Jiná cesta' WHERE product_id=501");
        self::assertSame('Knihy',$pdo->query('SELECT category_name_snapshot FROM shop_order_number_assignments WHERE order_id=4')->fetchColumn());
        self::assertSame('Zboží > Knihy',$pdo->query('SELECT catalog_path_snapshot FROM shop_order_accounting_items WHERE order_id=4')->fetchColumn());
    }

    public function testExplicitMetadataParentAndCycleRefusal(): void
    {
        $pdo=$this->database();$pdo->exec("INSERT INTO shop_category_meta VALUES('Zboží > Knihy','Knihy','Jiný kořen',0,1,NULL),('Jiný kořen','Jiný kořen',NULL,0,1,NULL)");
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'catalog','mapping_key'=>'Jiný kořen','category_id'=>31]);
        self::assertSame('2630100001',$this->assign($pdo,1));
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'catalog','mapping_key'=>'Jiný kořen','category_id'=>0]);
        $pdo->exec("UPDATE shop_category_meta SET parent_path='Zboží > Knihy' WHERE category_path='Jiný kořen'");
        try {$this->assign($pdo,4);self::fail('Cycle must fail.');} catch(\ShopNumberSeriesException) {}
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_number_assignments')->fetchColumn());
    }

    public function testCollisionsWithPaymentsAndBothStandingReservationsAreSkipped(): void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO payments VALUES(1,'member_charge',99,'2680000001')");
        $pdo->exec("INSERT INTO member_fee_standing_orders VALUES(1,'2680000002')");
        $pdo->exec("INSERT INTO member_payer_variable_symbols VALUES(10,'2680000003')");
        self::assertSame('2680000004',$this->assign($pdo,1));
        self::assertSame('2680000001',$pdo->query('SELECT variable_symbol FROM payments')->fetchColumn());
        self::assertSame('2680000003',$pdo->query('SELECT variable_symbol FROM member_payer_variable_symbols')->fetchColumn());
    }

    public function testMigrationNeverRenumbersOldPaymentsAndLegacySchemaStillWorks(): void
    {
        $pdo=$this->database(false);self::assertSame('0000000001',$this->assign($pdo,1));
        $pdo->exec("INSERT INTO payments VALUES(1,'shop_order',1,'0000000001')");
        $migration=require dirname(__DIR__,2).'/migrations/20261021210000_shop_number_series.php';$migration['up']($pdo);
        self::assertSame('0000000001',$this->assign($pdo,1));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_number_assignments')->fetchColumn());
    }

    public function testYearBoundaryUsesPragueAndIndependentCounter(): void
    {
        $pdo=$this->database();
        self::assertSame('2680000001',$this->assign($pdo,1,'2026-12-31T22:30:00Z'));
        self::assertSame('2780000001',$this->assign($pdo,4,'2026-12-31T23:30:00Z'));
        self::assertSame('2780000002',$this->assign($pdo,5,'2027-01-01T00:30:00Z'));
    }

    public function testExhaustionAndRollbackLeaveNoPartialAssignmentOrCounter(): void
    {
        $pdo=$this->database();$this->save($pdo,['action'=>'counter','category_id'=>80,'series_year'=>2026,'next_number'=>99999]);
        self::assertSame('2680099999',$this->assign($pdo,1));
        try {$this->assign($pdo,4);self::fail('Exhausted series must refuse.');} catch(\ShopNumberSeriesException) {}
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_number_assignments')->fetchColumn());
        self::assertSame(100000,(int)$pdo->query('SELECT next_number FROM shop_number_series_counters')->fetchColumn());
        self::assertSame('2780000001',$this->assign($pdo,4,'2027-01-01'));
        $pdo->exec("CREATE TRIGGER reject_snapshot BEFORE INSERT ON shop_order_accounting_items BEGIN SELECT RAISE(ABORT,'snapshot failure'); END");
        try {$this->assign($pdo,5,'2027-01-01');self::fail('Snapshot failure must roll back.');} catch(\PDOException) {}
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_number_assignments')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT next_number FROM shop_number_series_counters WHERE series_year=2027')->fetchColumn());
    }

    public function testAdminCreatesCategoriesAndProtectsCodesCountersAndStaleForms(): void
    {
        $pdo=$this->database();$this->save($pdo,['action'=>'group','group_code'=>4,'name'=>'LOCALHOST služby']);
        $this->save($pdo,['action'=>'category','group_code'=>4,'category_code'=>'03','name'=>'LOCALHOST služba','active'=>'1']);
        $id=(int)$pdo->query('SELECT id FROM shop_accounting_categories WHERE group_code=4')->fetchColumn();
        $this->save($pdo,['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'501','category_id'=>$id]);
        $this->save($pdo,['action'=>'counter','category_id'=>$id,'series_year'=>2026,'next_number'=>13]);
        self::assertSame('2640300013',$this->assign($pdo,1));
        foreach ([
            ['action'=>'counter','category_id'=>$id,'series_year'=>2026,'next_number'=>1],
            ['action'=>'counter','category_id'=>$id,'series_year'=>'2026bad','next_number'=>15],
            ['action'=>'counter','category_id'=>$id,'series_year'=>2026,'next_number'=>'15.5'],
            ['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'501','category_id'=>-1],
            ['action'=>'category','category_id'=>$id,'group_code'=>4,'category_code'=>'04','name'=>'Nový kód','active'=>'1'],
            ['action'=>'category','category_id'=>$id,'group_code'=>4,'category_code'=>'03','name'=>'Neaktivní','active'=>'0'],
            ['action'=>'mapping','mapping_type'=>'product','mapping_key'=>'501','category_id'=>90],
            ['action'=>'group','group_code'=>9,'name'=>'Nelze'],
            ['action'=>'group','group_code'=>5,'name'=>'Stará verze','revision'=>0],
        ] as $invalid) {
            try {$this->save($pdo,$invalid);self::fail('Invalid admin change must refuse.');} catch(\InvalidArgumentException|\ShopNumberSeriesException) {}
        }
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM shop_number_series_events')->fetchColumn());
    }

    public function testAdminAuditFailureRollsBackAndRequiresConfirmation(): void
    {
        $pdo=$this->database();
        try {\shopNumberSeriesSave($pdo,7,['action'=>'group','group_code'=>4,'name'=>'Změna','revision'=>0],'LOCALHOST důvod',false);self::fail('Confirmation required.');} catch(\InvalidArgumentException) {}
        $pdo->exec("CREATE TRIGGER reject_audit BEFORE INSERT ON shop_number_series_events BEGIN SELECT RAISE(ABORT,'audit failure'); END");
        try {$this->save($pdo,['action'=>'group','group_code'=>4,'name'=>'LOCALHOST nová']);self::fail('Audit failure must roll back.');} catch(\PDOException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_accounting_groups WHERE code=4')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT revision FROM shop_number_series_settings')->fetchColumn());
    }
}
