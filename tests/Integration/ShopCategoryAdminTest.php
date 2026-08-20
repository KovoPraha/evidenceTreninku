<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_category_admin.php';

final class ShopCategoryAdminTest extends TestCase
{
    public function testLegacyPathsBuildVirtualTreeAndParentFilterIncludesDescendants():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260820220000_shop_category_meta.php';
        $migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        $nodes=\shopCategoryNodes($pdo);
        self::assertFalse($nodes['Oblečení']['has_metadata']);
        self::assertSame('Oblečení',$nodes['Oblečení']['display_name']);
        self::assertSame('Oblečení > Dresy',$nodes['Oblečení > Dresy > Dlouhý rukáv']['parent_path']);

        \shopCategoryAdminSave($pdo,7,['category_path'=>'Oblečení','display_name'=>'Oblečení pro členy','parent_path'=>'','sort_order'=>'10','visible_in_menu'=>'1','description'=>'Klubová kolekce.'],'Zpřehlednění hlavní kategorie.',true);
        \shopCategoryAdminSave($pdo,7,['category_path'=>'Oblečení > Dresy','display_name'=>'Dresy','parent_path'=>'Oblečení','sort_order'=>'20','visible_in_menu'=>'1','description'=>''],'Založení druhé úrovně.',true);
        $nodes=\shopCategoryNodes($pdo);
        self::assertSame('Oblečení pro členy',$nodes['Oblečení']['display_name']);
        self::assertSame('Oblečení',$nodes['Oblečení > Dresy']['parent_path']);
        \shopCategoryAdminSave($pdo,7,['category_path'=>'Oblečení > Bundy','display_name'=>'Bundy','parent_path'=>'','sort_order'=>'30','visible_in_menu'=>'1','description'=>''],'Odvození rodiče z technické cesty.',true);
        self::assertSame('Oblečení',\shopCategoryAdminMeta($pdo,'Oblečení > Bundy')['parent_path']);
        \shopCategoryAdminSave($pdo,7,['category_path'=>'Oblečení > Volný kořen','display_name'=>'Volný kořen','parent_path'=>'__ROOT__','sort_order'=>'40','visible_in_menu'=>'0','description'=>''],'Výslovné přepsání odvozeného rodiče.',true);
        self::assertNull(\shopCategoryAdminMeta($pdo,'Oblečení > Volný kořen')['parent_path']);
        \shopCategoryAdminSave($pdo,7,['category_path'=>'Nový strom > Virtuální rodič > List','display_name'=>'Nový list','parent_path'=>'','sort_order'=>'0','visible_in_menu'=>'1','description'=>''],'Nová cesta bez existujících rodičů.',true);
        $newNodes=\shopCategoryNodes($pdo);
        self::assertFalse($newNodes['Nový strom']['has_metadata']);
        self::assertSame('Nový strom > Virtuální rodič',$newNodes['Nový strom > Virtuální rodič > List']['parent_path']);
        self::assertSame(
            ['Oblečení','Oblečení > Dresy','Oblečení > Bundy','Oblečení > Dresy > Dlouhý rukáv'],
            \shopCategoryDescendants($nodes,'Oblečení')
        );

        $products=[
            ['product_id'=>1,'categories'=>['Oblečení > Dresy > Dlouhý rukáv']],
            ['product_id'=>2,'categories'=>['Oblečení > Bundy']],
            ['product_id'=>3,'categories'=>[]],
        ];
        $menu=\shopStorefrontCategoryMenu($pdo,$products);$byPath=[];foreach($menu as$row)$byPath[$row['category_path']]=$row;
        self::assertSame(2,$byPath['Oblečení']['product_count']);
        self::assertSame(1,$byPath['Oblečení > Dresy']['product_count']);
        self::assertSame('Bundy',$byPath['Oblečení > Bundy']['display_name'],'Cesta bez metadat zůstává viditelná.');
        self::assertArrayNotHasKey('Skryté',$byPath,'Kategorie bez prodejného produktu v menu není.');
    }

    public function testDeleteRefusesProductPriceRuleAndChildrenButAllowsUnusedMetadata():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260820220000_shop_category_meta.php';$migration['up']($pdo);
        \shopCategoryAdminSave($pdo,7,$this->meta('Oblečení > Dresy > Dlouhý rukáv','Dlouhý rukáv','Oblečení > Dresy'),'Metadata používané kategorie.',true);
        try{\shopCategoryAdminDelete($pdo,7,'Oblečení > Dresy > Dlouhý rukáv','Pokus o smazání používané kategorie.',true);self::fail('Kategorie s produktem nesmí jít smazat.');}catch(\ShopCategoryException$exception){self::assertStringContainsString('produkt nebo cenové pravidlo',$exception->getMessage());}

        \shopCategoryAdminSave($pdo,7,$this->meta('Cenová kategorie','Cenová kategorie',null),'Kategorie pro cenové pravidlo.',true);
        $pdo->exec("INSERT INTO shop_member_category_rules(category_path) VALUES('Cenová kategorie')");
        try{\shopCategoryAdminDelete($pdo,7,'Cenová kategorie','Pokus o smazání cenové kategorie.',true);self::fail('Kategorie s pravidlem nesmí jít smazat.');}catch(\ShopCategoryException$exception){self::assertStringContainsString('produkt nebo cenové pravidlo',$exception->getMessage());}

        \shopCategoryAdminSave($pdo,7,$this->meta('Prázdná','Prázdná',null),'Dočasná prázdná kategorie.',true);
        $deleted=\shopCategoryAdminDelete($pdo,7,'Prázdná','Kategorie už nebude použita.',true);
        self::assertTrue($deleted['changed']);self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM shop_category_meta WHERE category_path='Prázdná'")->fetchColumn());
        self::assertSame('delete',$pdo->query("SELECT action FROM shop_category_meta_events WHERE category_path='Prázdná' ORDER BY id DESC LIMIT 1")->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testProductAssignmentKeepsOneDefaultAndWritesCatalogAudit():void
    {
        $pdo=$this->database();$migration=require dirname(__DIR__,2).'/migrations/20260820220000_shop_category_meta.php';$migration['up']($pdo);
        \shopCategoryAdminSave($pdo,7,$this->meta('Kroužky','Kroužky',null),'Kořen kroužků.',true);
        \shopCategoryAdminSave($pdo,7,$this->meta('Kroužky > Dětské','Dětské','Kroužky'),'Dětské kroužky.',true);
        $result=\shopCategoryAdminAssignProduct($pdo,7,3,['Kroužky > Dětské','Kroužky'],'Kroužky > Dětské','Přiřazení produktu do stromu.',true);
        self::assertTrue($result['changed']);self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_categories WHERE product_id=3')->fetchColumn());
        self::assertSame('Kroužky > Dětské',$pdo->query('SELECT category_path FROM shop_product_categories WHERE product_id=3 AND is_default=1')->fetchColumn());
        self::assertSame('assign_categories',$pdo->query('SELECT action FROM shop_catalog_admin_events WHERE product_id=3')->fetchColumn());

        \shopCategoryAdminAssignProduct($pdo,7,3,[],'','Vědomé ponechání pouze pod Vše.',true);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_product_categories WHERE product_id=3')->fetchColumn());
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM shop_catalog_admin_events WHERE product_id=3 AND action='assign_categories'")->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    /** @return array<string,mixed> */
    private function meta(string$path,string$name,?string$parent):array
    {
        return['category_path'=>$path,'display_name'=>$name,'parent_path'=>$parent??'','sort_order'=>'0','visible_in_menu'=>'1','description'=>''];
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(1,'Dres'),(2,'Bunda'),(3,'Kroužek')");
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL,category_path TEXT NOT NULL,is_default INTEGER NOT NULL DEFAULT 0,sort_order INTEGER NOT NULL DEFAULT 0,UNIQUE(product_id,category_path))');
        $pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES(1,'Oblečení > Dresy > Dlouhý rukáv',1,0),(2,'Oblečení > Bundy',1,0)");
        $pdo->exec('CREATE TABLE shop_member_category_rules(id INTEGER PRIMARY KEY AUTOINCREMENT,category_path TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL,variant_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NOT NULL,action TEXT NOT NULL,before_json TEXT NULL,after_json TEXT NOT NULL,reason TEXT NOT NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        return$pdo;
    }
}
