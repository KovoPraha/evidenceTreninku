<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ShopStorefrontWiringTest extends TestCase
{
    public function testProductDetailIsAuthenticatedCsrfProtectedAndUsesReviewedFields(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/booking/produkt.php');

        self::assertStringContainsString("verejny_uzivatel_id", $source);
        self::assertStringContainsString('csrf_verify', $source);
        self::assertStringContainsString('shopStorefrontProductDetail', $source);
        self::assertStringContainsString('shopProductRequiresAthlete', $source);
        self::assertStringContainsString('shopCartSetQuantity', $source);
        self::assertStringContainsString('referrerpolicy="no-referrer"', $source);
        self::assertStringContainsString('Po přihlášení se zobrazí případná klubová cena.', $source);
        self::assertStringContainsString('Účet vytvoříme automaticky a e-mail ověříte následně.', $source);
        self::assertStringContainsString('rychla_prihlaska.php?product_id=', $source);
        self::assertStringContainsString('Přihlásit sportovce a zaplatit', $source);
        self::assertStringNotContainsString('Pro přihlášení dítěte nebo účastníka potřebujete účet.', $source);
        self::assertStringNotContainsString('>Přihlásit pro zobrazení klubové ceny<', $source);
        self::assertStringNotContainsString('description_html_untrusted', $source);
        self::assertStringNotContainsString('short_description', $source);
    }

    public function testQuickProgramCheckoutKeepsAgeMismatchAsWarningOnly():void
    {
        $root=dirname(__DIR__,2);$page=(string)file_get_contents($root.'/booking/rychla_prihlaska.php');$service=(string)file_get_contents($root.'/includes/shop_program_quick_checkout.php');
        self::assertStringContainsString('quick-age-warning',$page);
        self::assertStringContainsString('Přihlášení je přesto možné.',$page);
        self::assertStringContainsString('Objednat a zobrazit platbu',$page);
        self::assertStringContainsString('athleteRegistrationSubmit',$service);
        self::assertStringContainsString("'quick_program'",$service);
        self::assertStringContainsString('clubProgramBirthDateWarning',$service);
    }

    public function testStorefrontLinksToProductDetail(): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/booking/eshop.php');
        self::assertStringContainsString('produkt.php?id=', $source);
        self::assertStringContainsString('shopStorefrontCatalog', $source);
        self::assertStringContainsString("\$_GET['kategorie']",$source);
        self::assertStringContainsString("rawurlencode((string)\$category['category_path'])",$source);
        self::assertStringContainsString('shopCategoryDescendants',$source);
        self::assertStringContainsString('shopStorefrontCategoryMenu',$source);
        self::assertStringContainsString('clubProgramVariantSaleState',$source);
        self::assertStringNotContainsString('clubProgramOfferIsOnSale($offer)',$source);
        self::assertStringContainsString("listing_attributes",$source);
        $detail=(string)file_get_contents(dirname(__DIR__,2).'/booking/produkt.php');
        self::assertStringContainsString("attributes_detail",$detail);
        self::assertStringContainsString('clubProgramVariantSaleState',$detail);
        self::assertStringContainsString("name=\"razeni\"",$source);
        self::assertStringContainsString('Hledat v názvu a popisu',$source);
        $clubs=(string)file_get_contents(dirname(__DIR__,2).'/booking/krouzky.php');
        self::assertStringNotContainsString('shopStorefrontCatalog',$clubs);
        self::assertStringContainsString('rozcestník kroužků',$clubs);
        $clubLanding=(string)file_get_contents(dirname(__DIR__,2).'/booking/cyklisticke_krouzky.php');
        self::assertStringContainsString('clubProgramStorefrontCatalog',$clubLanding);
        self::assertStringContainsString('rychla_prihlaska.php?product_id=',$clubLanding);
    }
}
