<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/CatalogCardsFixture.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_cards.php';

final class CatalogCardsReadTest extends TestCase
{
    public function testCardsMatchOriginalPresentationIncludingMultipleVariantsAndSharedCapacity(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(3);
        $pdo->exec("INSERT INTO shop_variants VALUES(4,1,'LOCALHOST-extra','{}','fixed',12000,'CZK','10',1,'active')");
        $pdo->exec("INSERT INTO club_program_offers VALUES(4,1,4,1,'active',1,1,'2027-01-01','2099-12-31',NULL,NULL,10)");
        $pdo->exec('UPDATE club_program_offers SET team_id=1');
        $pdo->exec('INSERT INTO club_program_physical_teams VALUES(1,1),(2,1),(3,1)');
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,2,42,NULL,'active')");
        $pdo->exec("INSERT INTO shop_product_images VALUES(1,1,'uploads/shop-products/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',0),"
            . "(2,1,'https://other.invalid/image.jpg',1)");
        $cards = \shopPublicCatalogCards($pdo);
        self::assertSame(\Tests\Support\catalogCardsReference($pdo), $cards);
        self::assertCount(2, $cards[0]['program_offers']);
        self::assertCount(1, $cards[0]['program_restrictions']);
        self::assertSame(9, $cards[0]['program_offers'][0]['available_count']);
        self::assertCount(1, $cards[0]['images']);
    }

    public function testRewrittenImportedTitleStillUsesOriginalProgramClassification(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        $pdo->exec("UPDATE shop_products SET id=157,offer_type='goods',origin='import',external_product_key='shoptet:pair:157'");
        $pdo->exec("UPDATE shop_product_publications SET product_id=157,public_name='Kurz pro batolata v pondělí'");
        $pdo->exec('UPDATE shop_variants SET product_id=157');
        $pdo->exec('UPDATE shop_product_categories SET product_id=157');
        $pdo->exec("UPDATE club_program_offers SET product_id=157,status='closed'");
        $cards = \shopPublicCatalogCards($pdo);
        self::assertSame(\Tests\Support\catalogCardsReference($pdo), $cards);
        self::assertSame('Děti 8–14 měsíců', $cards[0]['public_name']);
        self::assertTrue($cards[0]['is_program']);
        self::assertSame([], $cards[0]['program_offers']);
        self::assertTrue(\shopProductIsInterestOnlyYoungestCourse($cards[0]));
    }

    public function testGoodsExceptionsAndCategorySignalsRemainIdentical(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(3);
        $pdo->exec("UPDATE shop_products SET offer_type='goods',origin='import',external_product_key='shoptet:pair:250' WHERE id=1");
        $pdo->exec("UPDATE shop_product_publications SET public_name='Trička' WHERE product_id=1");
        $pdo->exec("UPDATE shop_product_categories SET category_path='Klub > Kroužky' WHERE product_id=1");
        $pdo->exec("UPDATE shop_products SET offer_type='goods',origin='import',external_product_key='shoptet:sku:course' WHERE id=2");
        $pdo->exec("UPDATE shop_product_categories SET category_path='Klub > Členství' WHERE product_id=2");
        $pdo->exec("UPDATE shop_products SET offer_type='goods',origin='import',external_product_key='shoptet:pair:852' WHERE id=3");
        $pdo->exec("UPDATE shop_product_publications SET public_name='Uskladnění kol na školní pololetí' WHERE product_id=3");
        $cards = \shopPublicCatalogCards($pdo);
        self::assertSame(\Tests\Support\catalogCardsReference($pdo), $cards);
        $flags = array_column($cards, 'is_program', 'product_id');
        self::assertFalse($flags[1]);
        self::assertTrue($flags[2]);
        self::assertFalse($flags[3]);
    }

    public function testEachNewReadSeesCapacityTermsAndPublicationChanges(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(1);
        self::assertSame(10, \shopPublicCatalogCards($pdo)[0]['program_offers'][0]['available_count']);
        $pdo->exec("INSERT INTO club_program_enrollments VALUES(1,1,42,NULL,'active')");
        self::assertSame(9, \shopPublicCatalogCards($pdo)[0]['program_offers'][0]['available_count']);
        $pdo->exec('UPDATE club_program_offers SET capacity=1');
        self::assertSame([], \shopPublicCatalogCards($pdo));
        $pdo->exec('UPDATE club_program_offers SET capacity=10');
        $pdo->exec("UPDATE club_event_term_versions SET status='withdrawn'");
        self::assertSame([], \shopPublicCatalogCards($pdo));
        $pdo->exec("UPDATE club_event_term_versions SET status='active'");
        self::assertCount(1, \shopPublicCatalogCards($pdo));
        $pdo->exec("UPDATE shop_product_publications SET status='draft'");
        self::assertSame([], \shopPublicCatalogCards($pdo));
    }

    public function testProgramCardsDoNotReloadOffersOrProductIdentity(): void
    {
        $pdo = \Tests\Support\programStorefrontFixture(50);
        $cards = \shopPublicCatalogCards($pdo);
        self::assertCount(50, $cards);
        self::assertSame(array_fill(0, 50, true), array_column($cards, 'is_program'));
        self::assertSame(array_fill(0, 50, 10), array_map(
            static fn (array $card): int => $card['program_offers'][0]['available_count'],
            $cards
        ));
        $metadata = array_filter($pdo->queries, static fn (string $sql): bool =>
            str_contains($sql, 'PRAGMA') || str_contains($sql, 'sqlite_master'));
        self::assertLessThanOrEqual(25, count($metadata), 'Card metadata must share the saleability projection.');
        self::assertLessThanOrEqual(450, count($pdo->queries), 'Cards must not reload known offers and identities.');
    }
}
