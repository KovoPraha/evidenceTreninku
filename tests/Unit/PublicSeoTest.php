<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/public_seo.php';
require_once dirname(__DIR__,2).'/includes/session_security.php';

final class PublicSeoTest extends TestCase
{
    private string|false $previousBaseUrl;

    protected function setUp():void
    {
        $this->previousBaseUrl=getenv('APP_BASE_URL');
        putenv('APP_BASE_URL=https://kis.kovopraha.cz');
    }

    protected function tearDown():void
    {
        $this->previousBaseUrl===false?putenv('APP_BASE_URL'):putenv('APP_BASE_URL='.$this->previousBaseUrl);
    }

    public function testHeadUsesTrustedCanonicalAndEscapesContent():void
    {
        ob_start();
        \publicSeoHead('Tričko <test>','Pohodlné & modré tričko.','booking/produkt.php?id=12');
        $html=(string)ob_get_clean();
        self::assertStringContainsString('href="https://kis.kovopraha.cz/booking/produkt.php?id=12"',$html);
        self::assertStringContainsString('content="Pohodlné &amp; modré tričko."',$html);
        self::assertStringContainsString('Tričko &lt;test&gt;',$html);
    }

    public function testDraftPageRendersNoindexWithoutCanonical():void
    {
        ob_start();
        \publicSeoHead('TEST - Tričko','Popis','booking/produkt.php?id=13',null,null,false);
        $html=(string)ob_get_clean();
        self::assertStringContainsString('name="robots" content="noindex,follow"',$html);
        self::assertStringNotContainsString('rel="canonical"',$html);
    }

    public function testSitemapExcludesTestProductsAndDeduplicatesPaths():void
    {
        $paths=\publicSeoSitemapPaths([
            ['product_id'=>42,'public_name'=>'Klubové tričko','categories'=>['Oblečení > Dresy']],
            ['product_id'=>42,'public_name'=>'Klubové tričko'],
            ['product_id'=>43,'public_name'=>'TEST - Klubová láhev'],
        ]);
        self::assertContains('booking/produkt.php?id=42',$paths);
        self::assertContains('booking/eshop.php?kategorie=Oble%C4%8Den%C3%AD%20%3E%20Dresy',$paths);
        self::assertNotContains('booking/produkt.php?id=43',$paths);
        self::assertSame(count($paths),count(array_unique($paths)));
    }

    public function testProductSchemaUsesOnlyRealGoodsOffers():void
    {
        $schema=\publicSeoProductSchema([
            'public_name'=>'Klubové tričko','public_summary'=>'Modré tričko.',
            'images'=>['https://kis.kovopraha.cz/uploads/tricko.webp'],
            'variants'=>[
                ['offer_type'=>'goods','amount_minor'=>129900,'currency'=>'CZK','in_stock'=>true,'sku'=>'TRIKO-M'],
                ['offer_type'=>'goods','amount_minor'=>129900,'currency'=>'CZK','in_stock'=>false,'sku'=>'TRIKO-L'],
            ],
        ],'https://kis.kovopraha.cz/booking/produkt.php?id=42');
        self::assertSame('Product',$schema['@type']);
        self::assertSame('1299.00',$schema['offers'][0]['price']);
        self::assertSame('https://schema.org/OutOfStock',$schema['offers'][1]['availability']);
        self::assertNull(\publicSeoProductSchema(['public_name'=>'TEST - Láhev','variants'=>[['offer_type'=>'goods','amount_minor'=>100,'currency'=>'CZK','in_stock'=>true,'sku'=>'X']]],'https://kis.kovopraha.cz/booking/produkt.php?id=43'));
    }

    public function testSparseProductSummaryGetsUsefulFactualDescription():void
    {
        $product=['public_name'=>'Bunda ACTIVE 23 MicroFiber Junior','public_summary'=>'bunda'];
        $description=\publicSeoProductDescription($product);
        self::assertStringContainsString('Bunda ACTIVE 23 MicroFiber Junior',$description);
        self::assertStringContainsString('aktuální cena a dostupnost',$description);
        self::assertSame('Pohodlné cyklistické oblečení pro děti ve více velikostech.',\publicSeoProductDescription([
            'public_name'=>'Bunda','public_summary'=>'Pohodlné cyklistické oblečení pro děti ve více velikostech.',
        ]));
    }

    public function testPrivateBookingStepsAreNoindexRoutes():void
    {
        self::assertTrue(\app_session_is_private_booking_route(['SCRIPT_NAME'=>'/booking/objednavka.php']));
        self::assertTrue(\app_session_is_private_booking_route(['SCRIPT_NAME'=>'/booking/prihlaseni.php']));
        self::assertFalse(\app_session_is_private_booking_route(['SCRIPT_NAME'=>'/booking/eshop.php']));
        self::assertFalse(\app_session_is_private_booking_route(['SCRIPT_NAME'=>'/index.php']));
    }
    public function testRobotsAndSitemapIndexPointToDynamicSitemap():void
    {
        $root=dirname(__DIR__,2);
        self::assertStringContainsString('Sitemap: https://kis.kovopraha.cz/sitemap.xml',(string)file_get_contents($root.'/robots.txt'));
        $xml=new \DOMDocument();
        self::assertTrue($xml->load($root.'/sitemap.xml'));
        self::assertSame('https://kis.kovopraha.cz/sitemap.php',$xml->getElementsByTagName('loc')->item(0)?->textContent);
    }
}
