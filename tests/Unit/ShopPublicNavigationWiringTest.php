<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_public_navigation.php';

final class ShopPublicNavigationWiringTest extends TestCase
{
    public function testNavigationRendersHomeAndEscapedHorizontalCategories():void
    {
        $html=\shopPublicNavigationHtml([
            ['category_path'=>'Oblečení','display_name'=>'Oblečení','depth'=>0],
            ['category_path'=>'Oblečení > Děti','display_name'=>'Děti & mládež','menu_label'=>'Oblečení › Děti & mládež','depth'=>1],
        ],'Oblečení > Děti');
        self::assertStringContainsString('aria-label="Navigace e-shopu"',$html);
        self::assertStringContainsString('E-shop – domů',$html);
        self::assertStringContainsString('kategorie=Oble%C4%8Den%C3%AD%20%3E%20D%C4%9Bti',$html);
        self::assertStringContainsString('Oblečení › Děti &amp; mládež',$html);
        self::assertSame(1,substr_count($html,'aria-current="page"'));
    }

    public function testHomeItemCanBeMarkedAsCurrentPage():void
    {
        $html=\shopPublicNavigationHtml([],null,true);
        self::assertStringContainsString('app-shop-home active',$html);
        self::assertSame(1,substr_count($html,'aria-current="page"'));
    }

    public function testEveryPublicShopPageUsesSharedNavigation():void
    {
        $root=dirname(__DIR__,2);
        foreach(['eshop.php','produkt.php','rychly_nakup.php','rychla_prihlaska.php','objednavka.php','moje_objednavky.php']as$file){
            $source=(string)file_get_contents($root.'/booking/'.$file);
            self::assertStringContainsString("includes/shop_public_navigation.php",$source,$file);
            self::assertStringContainsString('shopPublicNavigation($pdo',$source,$file);
        }
        $product=(string)file_get_contents($root.'/booking/produkt.php');
        self::assertStringContainsString('Hlavní stránka e-shopu',$product);
        self::assertStringNotContainsString('← Zpět do e-shopu',$product);
    }

    public function testCategoryAdministrationExplainsSharedMenuCheckbox():void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/eshop_categories_admin.php');
        self::assertStringContainsString('Zobrazit ve vodorovném menu na všech stránkách e-shopu',$source);
        self::assertStringContainsString('visible_in_menu',$source);
    }
}
