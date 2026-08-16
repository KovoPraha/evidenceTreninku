<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/stripe_gateway.php';

final class FakeStripeGatewayClient implements \StripeGatewayClient
{
    /** @var list<array{parameters:array<string,mixed>,idempotency_key:string}> */
    public array $created=[];
    /** @var array<string,mixed> */
    public array $event=[];

    public function createCheckoutSession(array $parameters,string $idempotencyKey):array
    {
        $this->created[]=['parameters'=>$parameters,'idempotency_key'=>$idempotencyKey];
        return ['id'=>'cs_test_unit123','url'=>'https://checkout.stripe.test/c/pay_unit123'];
    }

    public function constructWebhookEvent(string $payload,string $signature,string $secret):array
    {
        if($signature!=='valid-signature'||$secret!=='whsec_unit')throw new \StripeWebhookSignatureException('Bad signature.');
        return $this->event;
    }
}

final class StripeGatewayTest extends TestCase
{
    /** @var array{enabled:bool,secret_key:string,publishable_key:string,webhook_secret:string,base_url:string} */
    private const SETTINGS=['enabled'=>true,'secret_key'=>'sk_test_unit','publishable_key'=>'pk_test_unit','webhook_secret'=>'whsec_unit','base_url'=>'https://example.test/evidence'];

    public function testSessionUsesOnlyPendingServerSnapshotAmountAndStableIdempotency():void
    {
        $pdo=$this->database();$client=new FakeStripeGatewayClient();
        $session=\stripeCreateCheckoutSession($pdo,11,10,$client,self::SETTINGS);
        self::assertSame(25900,$session['amount_total']);self::assertSame('CZK',$session['currency']);self::assertCount(1,$client->created);
        $request=$client->created[0];self::assertSame(25900,$request['parameters']['line_items'][0]['price_data']['unit_amount']);self::assertSame('czk',$request['parameters']['line_items'][0]['price_data']['currency']);
        self::assertSame('kis_checkout_qkmrztax',$request['parameters']['integration_identifier']);
        self::assertArrayNotHasKey('payment_method_types',$request['parameters']);
        self::assertSame('11',$request['parameters']['metadata']['shop_order_id']);self::assertSame('31',$request['parameters']['metadata']['payment_id']);self::assertSame('shop-order-11-payment-31',$request['idempotency_key']);
        self::assertSame('cs_test_unit123',$pdo->query('SELECT stripe_checkout_session_id FROM payments WHERE id=31')->fetchColumn());
        $pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='cancelled' WHERE id=11");$pdo->exec("UPDATE payments SET status='cancelled' WHERE id=31");
        try{\stripeCreateCheckoutSession($pdo,11,10,$client,self::SETTINGS);self::fail('Cancelled order must not create a Stripe session.');}catch(\StripeGatewayException){}
        self::assertCount(1,$client->created);
    }

    public function testWebhookRejectsBadSignatureBeforeAnyWrite():void
    {
        $pdo=$this->database();$client=$this->completedClient();
        try{\stripeHandleWebhook($pdo,'{"id":"evt_unit"}','bad-signature',$client,self::SETTINGS);self::fail('Bad signature must be rejected.');}catch(\StripeWebhookSignatureException){}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM stripe_webhook_events')->fetchColumn());self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testSdkAdapterRejectsInvalidCryptographicSignatureWithoutNetwork():void
    {
        $client=new \StripeSdkGatewayClient('sk_test_unit');$payload='{"id":"evt_sdk_signature","type":"ping"}';
        $this->expectException(\StripeWebhookSignatureException::class);
        $client->constructWebhookEvent($payload,'t='.time().',v1=invalid','whsec_unit');
    }

    public function testUnknownSignedEventIsRecordedAndAcknowledgedAsIgnored():void
    {
        $pdo=$this->database();$client=new FakeStripeGatewayClient();$client->event=['id'=>'evt_unknown_unit','type'=>'customer.updated','data'=>['object'=>[]]];
        $result=\stripeHandleWebhook($pdo,'{"id":"evt_unknown_unit"}','valid-signature',$client,self::SETTINGS);
        self::assertSame('ignored',$result['status']);self::assertFalse($result['changed']);self::assertSame('ignored',$pdo->query('SELECT processing_status FROM stripe_webhook_events')->fetchColumn());self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testCompletedWebhookIsIdempotentAuditedAndUsesSystemActor():void
    {
        $pdo=$this->database();$createClient=new FakeStripeGatewayClient();\stripeCreateCheckoutSession($pdo,11,10,$createClient,self::SETTINGS);
        $client=$this->completedClient();$payload='{"id":"evt_checkout_completed_unit"}';
        $first=\stripeHandleWebhook($pdo,$payload,'valid-signature',$client,self::SETTINGS);self::assertSame('processed',$first['status']);self::assertTrue($first['changed']);
        $second=\stripeHandleWebhook($pdo,$payload,'valid-signature',$client,self::SETTINGS);self::assertSame('duplicate',$second['status']);self::assertTrue($second['duplicate']);self::assertFalse($second['changed']);
        $payment=$pdo->query('SELECT method,payment_source,status,stripe_payment_intent_id,confirmed_by_trainer_id FROM payments')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['method'=>'stripe','payment_source'=>'stripe','status'=>'paid','stripe_payment_intent_id'=>'pi_unit123','confirmed_by_trainer_id'=>null],$payment);
        self::assertSame(['status'=>'processing','payment_status'=>'paid'],$pdo->query('SELECT status,payment_status FROM shop_orders')->fetch(PDO::FETCH_ASSOC));
        $audit=$pdo->query('SELECT actor_type,actor_id,action,from_status,to_status,note FROM shop_order_events')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('system',$audit['actor_type']);self::assertNull($audit['actor_id']);self::assertSame('confirm_stripe_payment',$audit['action']);self::assertSame('placed',$audit['from_status']);self::assertSame('processing',$audit['to_status']);self::assertStringContainsString('evt_checkout_completed_unit',$audit['note']);
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM stripe_webhook_events')->fetchColumn());self::assertSame('processed',$pdo->query('SELECT processing_status FROM stripe_webhook_events')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
        self::assertStringContainsString('KP260809UNIT',(string)$pdo->query('SELECT body_plain FROM club_event_notifications')->fetchColumn());
    }

    public function testUnpaidCompletedWebhookIsRecordedAndAcknowledgedAsIgnored():void
    {
        $pdo=$this->database();$createClient=new FakeStripeGatewayClient();\stripeCreateCheckoutSession($pdo,11,10,$createClient,self::SETTINGS);
        $client=$this->completedClient();$client->event['id']='evt_checkout_unpaid_unit';$client->event['data']['object']['payment_status']='unpaid';$client->event['data']['object']['payment_intent']=null;
        $result=\stripeHandleWebhook($pdo,'{"id":"evt_checkout_unpaid_unit"}','valid-signature',$client,self::SETTINGS);
        self::assertSame('ignored',$result['status']);self::assertFalse($result['changed']);
        self::assertSame(['status'=>'pending','stripe_payment_intent_id'=>null],$pdo->query('SELECT status,stripe_payment_intent_id FROM payments')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(['status'=>'placed','payment_status'=>'pending'],$pdo->query('SELECT status,payment_status FROM shop_orders')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(['payment_id'=>31,'processing_status'=>'ignored'],$pdo->query('SELECT payment_id,processing_status FROM stripe_webhook_events')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testUnpaidCompletedWebhookStillRejectsAnUnlinkedSession():void
    {
        $pdo=$this->database();$client=$this->completedClient();$client->event['id']='evt_checkout_unlinked_unit';$client->event['data']['object']['id']='cs_test_unlinked';$client->event['data']['object']['payment_status']='unpaid';
        try{\stripeHandleWebhook($pdo,'{"id":"evt_checkout_unlinked_unit"}','valid-signature',$client,self::SETTINGS);self::fail('Unlinked unpaid session must remain an error.');}catch(\StripeGatewayException $exception){self::assertStringContainsString('navázána',$exception->getMessage());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM stripe_webhook_events')->fetchColumn());self::assertSame('pending',$pdo->query('SELECT status FROM payments')->fetchColumn());
    }

    public function testLatePaidWebhookDoesNotAttachMoneyToCancelledOrder():void
    {
        $pdo=$this->database();$createClient=new FakeStripeGatewayClient();\stripeCreateCheckoutSession($pdo,11,10,$createClient,self::SETTINGS);
        $pdo->exec("UPDATE shop_orders SET status='cancelled',payment_status='cancelled' WHERE id=11");$pdo->exec("UPDATE payments SET status='cancelled' WHERE id=31");
        try{\stripeHandleWebhook($pdo,'{"id":"evt_checkout_completed_unit"}','valid-signature',$this->completedClient(),self::SETTINGS);self::fail('Late payment must not be attached to a cancelled order.');}catch(\ShopCheckoutException){}
        self::assertSame(['status'=>'cancelled','stripe_payment_intent_id'=>null],$pdo->query('SELECT status,stripe_payment_intent_id FROM payments')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(['status'=>'cancelled','payment_status'=>'cancelled'],$pdo->query('SELECT status,payment_status FROM shop_orders')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM stripe_webhook_events')->fetchColumn());self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM shop_order_events')->fetchColumn());
    }

    public function testIncompleteConfigurationKeepsFlagFailClosed():void
    {
        self::assertFalse(\stripeIsEnabled(['enabled'=>false]));
        self::assertFalse(\stripeIsEnabled(['enabled'=>true,'secret_key'=>'','publishable_key'=>'pk_test_unit','webhook_secret'=>'whsec_unit','base_url'=>'https://example.test']));
        self::assertFalse(\stripeIsEnabled(['enabled'=>true,'secret_key'=>'sk_test_unit','publishable_key'=>'pk_live_unit','webhook_secret'=>'whsec_unit','base_url'=>'https://example.test']));
        $pdo=$this->database();$client=new FakeStripeGatewayClient();
        try{\stripeCreateCheckoutSession($pdo,11,10,$client,['enabled'=>true]);self::fail('Missing keys must disable Stripe.');}catch(\StripeGatewayDisabledException){}
        self::assertSame([],$client->created);self::assertNull($pdo->query('SELECT stripe_checkout_session_id FROM payments')->fetchColumn());
    }

    private function completedClient():FakeStripeGatewayClient
    {
        $client=new FakeStripeGatewayClient();$client->event=[
            'id'=>'evt_checkout_completed_unit','type'=>'checkout.session.completed','data'=>['object'=>[
                'id'=>'cs_test_unit123','mode'=>'payment','payment_status'=>'paid','amount_total'=>25900,'currency'=>'czk','payment_intent'=>'pi_unit123',
                'metadata'=>['shop_order_id'=>'11','payment_id'=>'31','public_code'=>'KP260809UNIT'],
            ]],
        ];return $client;
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,account_id INTEGER,status TEXT,payment_status TEXT,customer_name_snapshot TEXT,customer_email_snapshot TEXT,total_minor INTEGER,currency TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO shop_orders VALUES(11,'KP260809UNIT',10,'placed','pending','Testovací účet','stripe@example.test',25900,'CZK',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,quantity INTEGER)');
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,11,601,'Stripe test položka',1)");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO payments VALUES(31,'shop_order',11,'bank_transfer','pending',25900,'CZK',NULL,NULL,NULL,CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $migration=require dirname(__DIR__,2).'/migrations/20260809090000_stripe_checkout.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));
        return $pdo;
    }
}
