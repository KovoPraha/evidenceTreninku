<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_checkout.php';

final class ShopCheckoutTest extends TestCase
{
    private const BANK=['iban'=>'CZ6508000000192000145399','bic'=>'GIBACZPX','account_label'=>'KOVO Praha','due_days'=>7];

    public function testCheckoutUsesCurrentServerPriceThenKeepsImmutableSnapshotAndIsIdempotent():void
    {
        $pdo=$this->database();
        $products=\shopStorefrontProducts($pdo);self::assertCount(1,$products);self::assertSame(601,(int)$products[0]['variant_id']);
        \shopCartSetQuantity($pdo,10,601,2);
        $oldFingerprint=\shopCartDetail($pdo,10)['fingerprint'];
        $pdo->exec('UPDATE shop_variants SET amount_minor=13000 WHERE id=601');
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$oldFingerprint);self::fail('Silent repricing must be rejected.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        $key=bin2hex(random_bytes(16));$order=\shopCheckoutPlace($pdo,10,$key,self::BANK,\shopCartDetail($pdo,10)['fingerprint']);
        self::assertFalse($order['replayed']);self::assertSame(26000,(int)$order['total_minor']);self::assertSame('placed',$order['status']);
        self::assertSame('pending',$order['payment_record_status']);self::assertSame('personal_pickup',$order['fulfillment_method']);
        self::assertSame(26000,(int)$pdo->query('SELECT line_amount_minor FROM shop_order_items')->fetchColumn());
        self::assertSame('Tričko KOVO',$pdo->query('SELECT product_name_snapshot FROM shop_order_items')->fetchColumn());
        self::assertSame(3.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_inventory_movements')->fetchColumn());
        $replay=\shopCheckoutPlace($pdo,10,$key,self::BANK,str_repeat('0',64));self::assertTrue($replay['replayed']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
        $pdo->exec("UPDATE shop_variants SET amount_minor=99999,sku='CHANGED' WHERE id=601");
        $pdo->exec("UPDATE shop_product_publications SET public_name='Nový název' WHERE product_id=501");
        $snapshot=$pdo->query('SELECT product_name_snapshot,sku_snapshot,unit_amount_minor FROM shop_order_items')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['product_name_snapshot'=>'Tričko KOVO','sku_snapshot'=>'TRIKO-M','unit_amount_minor'=>13000],$snapshot);
        self::assertMatchesRegularExpression('/^SPD\*1\.0\*ACC:CZ6508000000192000145399\*AM:260\.00\*CC:CZK\*X-VS:[0-9]{10}\*MSG:/',(string)$order['spd_payload']);
        self::assertStringStartsWith('data:image/svg+xml',\shopPaymentQrDataUri((string)$order['spd_payload']));
        try{\shopOrderByCode($pdo,11,(string)$order['public_code']);self::fail('Foreign order must not be readable.');}catch(\ShopCheckoutException){}
    }

    public function testInvalidBankOrInsufficientStockRollsBackEverything():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,6);
        $fingerprint=\shopCartDetail($pdo,10)['fingerprint'];
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),['iban'=>'bad','bic'=>'','account_label'=>'','due_days'=>0],$fingerprint);self::fail('Invalid bank config must fail closed.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        try{\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,$fingerprint);self::fail('Stock must not go negative.');}catch(\ShopCheckoutException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(5.0,(float)$pdo->query('SELECT stock_quantity_decimal FROM shop_variants WHERE id=601')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM shop_carts')->fetchColumn());
    }

    public function testAdminBankConfirmationIsExplicitAuditedAndIdempotent():void
    {
        $pdo=$this->database();\shopCartSetQuantity($pdo,10,601,1);$order=\shopCheckoutPlace($pdo,10,bin2hex(random_bytes(16)),self::BANK,\shopCartDetail($pdo,10)['fingerprint']);$paymentId=(int)$order['payment_id'];
        try{\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Ověřeno ve Fio.',false);self::fail('Explicit confirmation is required.');}catch(\InvalidArgumentException){}
        self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
        $result=\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Ověřeno v bankovnictví podle VS a částky.',true);self::assertTrue($result['changed']);
        self::assertSame('paid',$pdo->query('SELECT status FROM payments')->fetchColumn());self::assertSame('processing',$pdo->query('SELECT status FROM shop_orders')->fetchColumn());self::assertSame('paid',$pdo->query('SELECT payment_status FROM shop_orders')->fetchColumn());
        self::assertSame('confirm_bank_payment',$pdo->query('SELECT action FROM shop_order_events ORDER BY id DESC LIMIT 1')->fetchColumn());
        self::assertFalse(\shopOrderAdminConfirmBankPayment($pdo,$paymentId,7,'Opakování.',true)['changed']);
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testCartRejectsInactiveNonGoodsAndMixedOrInvalidQuantity():void
    {
        $pdo=$this->database();
        foreach([[602,1],[603,1],[601,100]]as[$variant,$quantity]){
            try{\shopCartSetQuantity($pdo,10,$variant,$quantity);self::fail('Unsupported cart input must fail.');}
            catch(\InvalidArgumentException|\ShopCheckoutException){}
        }
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_cart_items')->fetchColumn());
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','parent@example.test',1,1),(11,'Cizí','Účet','foreign@example.test',1,1)");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'goods','active'),(502,'club_event','active'),(503,'goods','inactive')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'TRIKO-M','{\"size\":\"M\"}','fixed',12500,'CZK',1,2100,'5.000000',1,'active',CURRENT_TIMESTAMP),(602,502,'EVENT','{}','fixed',100,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(603,503,'OLD','{}','fixed',100,'CZK',1,0,NULL,1,'inactive',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Tričko KOVO','Klubové tričko.'),(502,'active','Kroužek','Nejde do košíku.'),(503,'inactive','Staré','Neaktivní.')");
        $migration=require dirname(__DIR__,2).'/migrations/20260803230000_shop_checkout.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));return $pdo;
    }
}
