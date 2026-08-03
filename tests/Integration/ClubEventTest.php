<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';
require_once dirname(__DIR__, 2) . '/includes/club_event.php';

final class ClubEventTest extends TestCase
{
    public function testDraftEventSessionAndProductLinkAreAuditedAndReversible(): void
    {
        $pdo=$this->database();$event=\clubEventCreateDraft($pdo,7,$this->eventInput());
        $session=\clubEventAddSession($pdo,$event['id'],7,'2026-09-01T16:00','2026-09-01T17:30','Velodrom',18);
        self::assertGreaterThan(0,$session['id']);
        try { \clubEventAddSession($pdo,$event['id'],7,'2026-09-01T17:00','2026-09-01T18:00','Velodrom',null);self::fail('Overlap must be blocked.'); }
        catch (\ClubEventException) { self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_sessions')->fetchColumn()); }
        $productId=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='club_event' LIMIT 1")->fetchColumn();
        $link=\clubEventLinkProduct($pdo,$event['id'],$productId,7,'Produkt odpovídá tomuto kroužku.');
        $same=\clubEventLinkProduct($pdo,$event['id'],$productId,7,'Opakování.');
        self::assertTrue($link['created']);self::assertFalse($same['created']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn());
        $detail=\clubEventDetail($pdo,$event['id']);self::assertNotNull($detail);self::assertCount(1,$detail['sessions']);self::assertCount(1,$detail['products']);
        \clubEventUnlinkProduct($pdo,$event['id'],$link['id'],7,'Oprava mapování.');
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn());
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM club_event_admin_events')->fetchColumn());
        self::assertSame('draft',$pdo->query('SELECT status FROM club_events WHERE id='.$event['id'])->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('club_event_registrations','shop_orders','payments')")->fetchColumn());
    }

    public function testWrongProductTypeAndMissingSessionAreBlockedAtomically(): void
    {
        $pdo=$this->database();$event=\clubEventCreateDraft($pdo,7,$this->eventInput());
        $clubProduct=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='club_event' LIMIT 1")->fetchColumn();
        try { \clubEventLinkProduct($pdo,$event['id'],$clubProduct,7,'Bez termínu.');self::fail('Session is required.'); }
        catch (\ClubEventException) { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn()); }
        \clubEventAddSession($pdo,$event['id'],7,'2026-09-02T16:00','2026-09-02T17:00','Velodrom',null);
        $campProduct=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='camp' LIMIT 1")->fetchColumn();
        try { \clubEventLinkProduct($pdo,$event['id'],$campProduct,7,'Špatný typ.');self::fail('Type mismatch must fail.'); }
        catch (\ClubEventException) { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn()); }
    }

    public function testFreeEventRejectsPaidProduct(): void
    {
        $pdo=$this->database();$input=$this->eventInput();$input['pricing_policy']='free';
        $event=\clubEventCreateDraft($pdo,7,$input);\clubEventAddSession($pdo,$event['id'],7,'2026-09-03T16:00','2026-09-03T17:00','Velodrom',null);
        $productId=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='club_event' LIMIT 1")->fetchColumn();
        $this->expectException(\ClubEventException::class);
        try { \clubEventLinkProduct($pdo,$event['id'],$productId,7,'Bezplatná akce.'); }
        finally { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn()); }
    }

    public function testProductCurrencyMustMatchEventCurrency(): void
    {
        $pdo=$this->database();$input=$this->eventInput();$input['currency']='EUR';
        $event=\clubEventCreateDraft($pdo,7,$input);\clubEventAddSession($pdo,$event['id'],7,'2026-09-04T16:00','2026-09-04T17:00','Velodrom',null);
        $productId=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='club_event' LIMIT 1")->fetchColumn();
        $this->expectException(\ClubEventException::class);
        try { \clubEventLinkProduct($pdo,$event['id'],$productId,7,'Špatná měna.'); }
        finally { self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_event_links')->fetchColumn()); }
    }

    public function testInvalidDraftAndDatesWriteNothing(): void
    {
        $pdo=$this->database();$bad=$this->eventInput();$bad['capacity']=0;
        try { \clubEventCreateDraft($pdo,7,$bad);self::fail('Invalid capacity must fail.'); } catch (\InvalidArgumentException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_events')->fetchColumn());
        $event=\clubEventCreateDraft($pdo,7,$this->eventInput());
        try { \clubEventAddSession($pdo,$event['id'],7,'2026-09-01T18:00','2026-09-01T17:00','Velodrom',null);self::fail('Reverse interval must fail.'); } catch (\InvalidArgumentException) {}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_sessions')->fetchColumn());
    }

    private function eventInput(): array
    {
        return ['code'=>'KROUZEK-2026','event_type'=>'club_event','name'=>'Zebry 6–7 let','description_plain'=>'Pracovní návrh kroužku.','audience_label'=>'Děti 6–7 let','min_age'=>'6','max_age'=>'7','capacity'=>'20','pricing_policy'=>'product_variants','currency'=>'CZK','registration_starts_at'=>'2026-08-10T08:00','registration_ends_at'=>'2026-08-31T20:00'];
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        foreach(['20260802170000_shop_catalog_staging.php','20260802190000_shop_catalog_review.php','20260802210000_shop_canonical_catalog.php','20260803110000_club_events.php'] as $file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));}
        $catalog=\ShopCatalogContract::build(\ShoptetProductInput::read(dirname(__DIR__).'/fixtures/shoptet/products-offer-types.csv'));$run=\shopCatalogStage($pdo,$catalog);$pending=$pdo->query("SELECT id FROM shop_catalog_product_candidates WHERE run_id={$run['run_id']} AND review_status='pending'")->fetchColumn();\shopCatalogReviewProduct($pdo,$run['run_id'],(int)$pending,7,'approve','goods','Fyzické zboží.');\shopCatalogPromote($pdo,$run['run_id'],7,true);return $pdo;
    }
}
