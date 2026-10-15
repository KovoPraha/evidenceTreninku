<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_order_customer_status.php';
require_once dirname(__DIR__,2).'/includes/shop_manual_catalog.php';

final class ShopOrderCustomerStatusTest extends TestCase
{
    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_type TEXT NOT NULL,item_type TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY AUTOINCREMENT,public_code TEXT NOT NULL,status TEXT NOT NULL,payment_status TEXT NOT NULL,customer_email_snapshot TEXT NOT NULL,customer_name_snapshot TEXT NOT NULL,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER NOT NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,action TEXT NOT NULL,from_status TEXT NULL,to_status TEXT NOT NULL,note TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT \'pending\',UNIQUE(order_id,notification_type))');
        $migration=require dirname(__DIR__,2).'/migrations/20261015120000_shop_order_customer_statuses.php';
        $migration['up']($pdo);$migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $pdo->exec("INSERT INTO shop_orders(id,public_code,status,payment_status,customer_email_snapshot,customer_name_snapshot) VALUES(1,'KPTEST01','placed','pending','buyer@example.test','Testovací Kupující')");
        return $pdo;
    }

    public function testChangeQueuesExactlyOneEmailWithoutChangingPaymentOrCanonicalFulfillment(): void
    {
        $pdo=$this->database();
        $created=\shopOrderCustomerStatusSave($pdo,7,0,[
            'label'=>'Objednávka přijata','email_enabled'=>'1','email_subject'=>'Objednávka {order_code}: {status}',
            'email_body'=>'Dobrý den, {customer_name}. Připravujeme {order_code}.','active'=>'1',
        ],'Zavedení pracovního postupu.');
        self::assertSame('Vytvořená objednávka',\shopOrderCustomerStatusLabel(['status'=>'placed']));
        $changed=\shopOrderCustomerStatusAssign($pdo,1,$created['id'],7,'Objednávka převzata.');
        self::assertTrue($changed['changed']);self::assertNotNull($changed['notification_id']);
        self::assertFalse(\shopOrderCustomerStatusAssign($pdo,1,$created['id'],7,'Opakované kliknutí.')['changed']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
        self::assertSame('Objednávka KPTEST01: Objednávka přijata',$pdo->query('SELECT subject_plain FROM club_event_notifications')->fetchColumn());
        self::assertSame('Dobrý den, Testovací Kupující. Připravujeme KPTEST01.',$pdo->query('SELECT body_plain FROM club_event_notifications')->fetchColumn());
        $order=$pdo->query('SELECT * FROM shop_orders WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('placed',$order['status']);self::assertSame('pending',$order['payment_status']);
        self::assertSame('Objednávka přijata',\shopOrderCustomerStatusLabel(\shopOrderCustomerStatusAttach($pdo,$order)));
        self::assertSame('set_customer_status',$pdo->query('SELECT action FROM shop_order_events')->fetchColumn());
    }

    public function testInactiveAndTerminalOrdersCannotReceiveCustomStatus(): void
    {
        $pdo=$this->database();
        $created=\shopOrderCustomerStatusSave($pdo,7,0,['label'=>'Zpracováváme'],'Založení.');
        \shopOrderCustomerStatusSave($pdo,7,$created['id'],['label'=>'Zpracováváme'],'Vyřazení.');
        try { \shopOrderCustomerStatusAssign($pdo,1,$created['id'],7,'Pokus.');self::fail('Inactive status must be rejected.'); }
        catch (\ShopOrderCustomerStatusException) {}
        self::assertNull($pdo->query('SELECT customer_status_id FROM shop_orders WHERE id=1')->fetchColumn());
        $pdo->exec("UPDATE shop_orders SET status='cancelled' WHERE id=1");
        try { \shopOrderCustomerStatusAssign($pdo,1,0,7,'Pokus.');self::fail('Terminal order must be rejected.'); }
        catch (\ShopOrderCustomerStatusException) {}
        self::assertSame('Stornováno',\shopOrderCustomerStatusLabel(['status'=>'cancelled','customer_status_label'=>'Zpracováváme']));
    }

    public function testInvalidRecipientRollsBackStatusEventAndEmailTogether(): void
    {
        $pdo=$this->database();
        $created=\shopOrderCustomerStatusSave($pdo,7,0,[
            'label'=>'Potvrzena','email_enabled'=>'1','email_subject'=>'Objednávka {order_code}',
            'email_body'=>'Zdravíme vás.','active'=>'1',
        ],'Nastavení zprávy.');
        $pdo->exec("UPDATE shop_orders SET customer_email_snapshot='invalid-address' WHERE id=1");
        try { \shopOrderCustomerStatusAssign($pdo,1,$created['id'],7,'Potvrzení.');self::fail('Invalid recipient must reject the transition.'); }
        catch (\ShopOrderCustomerStatusException) {}
        self::assertNull($pdo->query('SELECT customer_status_id FROM shop_orders WHERE id=1')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_event_notifications')->fetchColumn());
    }

    public function testOnlyPhysicalGoodsCanBeMarkedShippingEligible(): void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO shop_products(offer_type,item_type) VALUES('goods','product')");
        self::assertSame('pickup_only',$pdo->query('SELECT delivery_mode FROM shop_products WHERE id=1')->fetchColumn());
        $goods=\shopManualCatalogProductInput(['name'=>'Tričko','offer_type'=>'goods','item_type'=>'product','visibility'=>'visible','delivery_mode'=>'shipping_eligible']);
        self::assertSame('shipping_eligible',$goods['delivery_mode']);
        $this->expectException(\InvalidArgumentException::class);
        \shopManualCatalogProductInput(['name'=>'Trénink','offer_type'=>'program','item_type'=>'service','visibility'=>'visible','delivery_mode'=>'shipping_eligible']);
    }
}
