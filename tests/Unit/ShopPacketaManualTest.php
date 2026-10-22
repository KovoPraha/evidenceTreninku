<?php
declare(strict_types=1);
namespace Tests\Unit;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/shop_packeta.php';

final class ShopPacketaManualTest extends TestCase
{
    public function testMigrationEnablesPhysicalGoodsAndSeedsPriceOnceWithoutActivation():void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,item_type TEXT,delivery_mode TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(1,'goods','product','pickup_only'),(2,'program','service','pickup_only'),(3,'goods','service','pickup_only')");
        $pdo->exec('CREATE TABLE shop_packeta_settings(id INTEGER PRIMARY KEY,enabled INTEGER,price_minor INTEGER,widget_api_key TEXT)');
        $pdo->exec("INSERT INTO shop_packeta_settings VALUES(1,0,0,'')");
        $migration=require dirname(__DIR__,2).'/migrations/20261021200000_shop_packeta_manual_products.php';$migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame([1,0,0],array_map('intval',$pdo->query('SELECT packeta_shipping_enabled FROM shop_products ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(15000,(int)$pdo->query('SELECT price_minor FROM shop_packeta_settings')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT enabled FROM shop_packeta_settings')->fetchColumn());
        self::assertSame('pickup_only',$pdo->query('SELECT delivery_mode FROM shop_products WHERE id=1')->fetchColumn());
        $pdo->exec('UPDATE shop_packeta_settings SET price_minor=18000');$pdo->exec('UPDATE shop_products SET packeta_shipping_enabled=0 WHERE id=1');$migration['up']($pdo);
        self::assertSame(18000,(int)$pdo->query('SELECT price_minor FROM shop_packeta_settings')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT packeta_shipping_enabled FROM shop_products WHERE id=1')->fetchColumn());
    }

    public function testOnlyExplicitlyAllowedGoodsAreEligibleIndependentlyOfPpl():void
    {
        self::assertTrue(\shopPacketaCartEligible([['offer_type'=>'goods','packeta_shipping_enabled'=>1,'delivery_mode'=>'pickup_only']]));
        foreach([[],[['offer_type'=>'goods','delivery_mode'=>'shipping_eligible']],[['offer_type'=>'program','packeta_shipping_enabled'=>1]],[['offer_type'=>'goods','packeta_shipping_enabled'=>1],['offer_type'=>'goods','packeta_shipping_enabled'=>0]]]as$items)self::assertFalse(\shopPacketaCartEligible($items));
    }

    public function testCurrentValidationAcceptsOnlyValidCzechZboxAndCompleteAddress():void
    {
        $response=['isValid'=>true,'point'=>['name'=>'Z-BOX Praha','group'=>'zbox','address'=>['street'=>'Testovací 1','city'=>'Praha','zip'=>'10000','country'=>'cz']]];
        self::assertSame('123',\shopPacketaNormalizeValidatedBox($response,'123')['id']);
        foreach([array_replace($response,['isValid'=>false]),array_replace_recursive($response,['point'=>['group'=>'']]),array_replace_recursive($response,['point'=>['address'=>['country'=>'sk']]]),array_replace($response,['point'=>'invalid'])]as$bad){
            try{\shopPacketaNormalizeValidatedBox($bad,'123');self::fail('Invalid validation response accepted.');}catch(\ShopPacketaException){}
        }
    }
}
