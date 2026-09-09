<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/sumup_gateway.php';

final class FakeSumUpGatewayClient implements \SumUpGatewayClient
{
    /** @var list<array<string,mixed>> */
    public array $created = [];
    /** @var list<array<string,mixed>> */
    public array $listed = [];
    /** @var array<string,mixed> */
    public array $checkout = [];
    public int $retrieved = 0;

    public function createCheckout(array $parameters): array
    {
        $this->created[] = $parameters;
        return $this->checkout;
    }

    public function listCheckouts(string $checkoutReference): array
    {
        return $this->listed;
    }

    public function retrieveCheckout(string $checkoutId): array
    {
        $this->retrieved++;
        return $this->checkout;
    }
}

final class SumUpGatewayTest extends TestCase
{
    /** @var array{enabled:bool,api_key:string,merchant_code:string,base_url:string} */
    private const SETTINGS = ['enabled' => true, 'api_key' => 'sup_' . 'sk_test_unit', 'merchant_code' => 'MELE4XUL', 'base_url' => 'https://example.test/evidence'];

    public function testHostedCheckoutUsesOnlyServerSnapshotAndStableReference(): void
    {
        $pdo = $this->database();
        $client = $this->pendingClient();
        $checkout = \sumupCreateCheckout($pdo, 11, 10, $client, self::SETTINGS);

        self::assertSame(25900, $checkout['amount_total']);
        self::assertSame('CZK', $checkout['currency']);
        self::assertSame('KIS-11-31', $checkout['reference']);
        self::assertCount(1, $client->created);
        self::assertSame('259.00', $client->created[0]['amount']);
        self::assertSame('CZK', $client->created[0]['currency']);
        self::assertSame('MELE4XUL', $client->created[0]['merchant_code']);
        self::assertSame(['enabled' => true], $client->created[0]['hosted_checkout']);
        self::assertSame('https://example.test/evidence/booking/sumup_webhook.php', $client->created[0]['return_url']);
        self::assertSame('sumup-checkout-unit', $pdo->query('SELECT sumup_checkout_id FROM payments WHERE id=31')->fetchColumn());
        self::assertSame('KIS-11-31', $pdo->query('SELECT sumup_checkout_reference FROM payments WHERE id=31')->fetchColumn());
    }

    public function testExistingCheckoutByReferenceIsReusedWithoutCreatingAnother(): void
    {
        $pdo = $this->database();
        $client = $this->pendingClient();
        $client->listed = [$client->checkout];
        \sumupCreateCheckout($pdo, 11, 10, $client, self::SETTINGS);
        self::assertCount(0, $client->created);
        self::assertSame('sumup-checkout-unit', $pdo->query('SELECT sumup_checkout_id FROM payments')->fetchColumn());
    }

    public function testPaidCallbackIsApiVerifiedIdempotentAndUsesCanonicalLifecycle(): void
    {
        $pdo = $this->database();
        $create = $this->pendingClient();
        \sumupCreateCheckout($pdo, 11, 10, $create, self::SETTINGS);
        $client = $this->paidClient();
        $payload = '{"event_type":"CHECKOUT_STATUS_CHANGED","id":"sumup-checkout-unit"}';

        $first = \sumupHandleWebhook($pdo, $payload, $client, self::SETTINGS);
        $second = \sumupHandleWebhook($pdo, $payload, $client, self::SETTINGS);

        self::assertSame('processed', $first['status']);
        self::assertTrue($first['changed']);
        self::assertSame('duplicate', $second['status']);
        self::assertSame(['method' => 'sumup', 'payment_source' => 'sumup', 'status' => 'paid', 'sumup_transaction_id' => 'sumup-transaction-unit', 'confirmed_by_trainer_id' => null], $pdo->query('SELECT method,payment_source,status,sumup_transaction_id,confirmed_by_trainer_id FROM payments')->fetch(PDO::FETCH_ASSOC));
        self::assertSame(['status' => 'processing', 'payment_status' => 'paid'], $pdo->query('SELECT status,payment_status FROM shop_orders')->fetch(PDO::FETCH_ASSOC));
        self::assertSame('confirm_sumup_payment', $pdo->query('SELECT action FROM shop_order_events')->fetchColumn());
        self::assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM club_event_notifications WHERE notification_type='shop_payment_received'")->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM sumup_webhook_events')->fetchColumn());
        self::assertSame(2, $client->retrieved, 'Každý callback se nejprve ověřuje přes SumUp API.');
    }

    public function testPendingCallbackDoesNotBlockLaterPaidStatus(): void
    {
        $pdo = $this->database();
        $create = $this->pendingClient();
        \sumupCreateCheckout($pdo, 11, 10, $create, self::SETTINGS);
        $payload = '{"event_type":"CHECKOUT_STATUS_CHANGED","id":"sumup-checkout-unit"}';
        $pending = \sumupHandleWebhook($pdo, $payload, $create, self::SETTINGS);
        $paid = \sumupHandleWebhook($pdo, $payload, $this->paidClient(), self::SETTINGS);
        self::assertSame('ignored', $pending['status']);
        self::assertSame('processed', $paid['status']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM sumup_webhook_events')->fetchColumn());
    }

    public function testApiMismatchRollsBackWithoutConfirmingPayment(): void
    {
        $pdo = $this->database();
        $create = $this->pendingClient();
        \sumupCreateCheckout($pdo, 11, 10, $create, self::SETTINGS);
        $client = $this->paidClient();
        $client->checkout['amount'] = '1.00';
        try {
            \sumupHandleWebhook($pdo, '{"event_type":"CHECKOUT_STATUS_CHANGED","id":"sumup-checkout-unit"}', $client, self::SETTINGS);
            self::fail('Neshodná částka musí být odmítnuta.');
        } catch (\SumUpGatewayException) {
        }
        self::assertSame('pending', $pdo->query('SELECT status FROM payments')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM sumup_webhook_events')->fetchColumn());
    }

    public function testUnknownCallbackIsIgnoredWithoutApiCall(): void
    {
        $client = $this->paidClient();
        $result = \sumupHandleWebhook($this->database(), '{"event_type":"NEW_EVENT","id":"sumup-checkout-unit"}', $client, self::SETTINGS);
        self::assertSame('ignored', $result['status']);
        self::assertSame(0, $client->retrieved);
    }

    public function testIncompleteConfigurationFailsClosed(): void
    {
        self::assertFalse(\sumupIsEnabled(['enabled' => false]));
        self::assertFalse(\sumupIsEnabled(['enabled' => true, 'api_key' => 'sup_' . 'pk_public', 'merchant_code' => 'MELE4XUL', 'base_url' => 'https://example.test']));
        self::assertFalse(\sumupIsEnabled(['enabled' => true, 'api_key' => 'sup_' . 'sk_test', 'merchant_code' => 'INVALID', 'base_url' => 'https://example.test']));
        self::assertFalse(\sumupIsEnabled(['enabled' => true, 'api_key' => 'sup_' . 'sk_test', 'merchant_code' => 'MELE4XUL', 'base_url' => 'http://example.test']));
    }

    public function testBankOnlyPaymentCannotCreateSumUpCheckout(): void
    {
        $pdo=$this->database();
        $pdo->exec("ALTER TABLE payments ADD COLUMN accepted_payment_methods TEXT NOT NULL DEFAULT 'bank_transfer'");
        $this->expectException(\SumUpGatewayDisabledException::class);
        \sumupCreateCheckout($pdo,11,10,$this->pendingClient(),self::SETTINGS);
    }

    private function pendingClient(): FakeSumUpGatewayClient
    {
        $client = new FakeSumUpGatewayClient();
        $client->checkout = [
            'id' => 'sumup-checkout-unit',
            'checkout_reference' => 'KIS-11-31',
            'amount' => '259.00',
            'currency' => 'CZK',
            'merchant_code' => 'MELE4XUL',
            'status' => 'PENDING',
            'hosted_checkout_url' => 'https://checkout.sumup.com/pay/sumup-checkout-unit',
            'transactions' => [],
        ];
        return $client;
    }

    private function paidClient(): FakeSumUpGatewayClient
    {
        $client = $this->pendingClient();
        $client->checkout['status'] = 'PAID';
        $client->checkout['transactions'] = [[
            'id' => 'sumup-transaction-unit',
            'status' => 'SUCCESSFUL',
            'amount' => '259.00',
            'currency' => 'CZK',
            'merchant_code' => 'MELE4XUL',
            'payment_type' => 'ECOM',
        ]];
        return $client;
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,public_code TEXT,account_id INTEGER,status TEXT,payment_status TEXT,customer_name_snapshot TEXT,customer_email_snapshot TEXT,total_minor INTEGER,currency TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO shop_orders VALUES(11,'KP260809UNIT',10,'placed','pending','Testovací účet','sumup@example.test',25900,'CZK',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_order_items(id INTEGER PRIMARY KEY,order_id INTEGER,variant_id INTEGER,product_name_snapshot TEXT,quantity INTEGER)');
        $pdo->exec("INSERT INTO shop_order_items VALUES(1,11,601,'SumUp test položka',1)");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE payments(id INTEGER PRIMARY KEY,payable_type TEXT,payable_id INTEGER,method TEXT,status TEXT,amount_minor INTEGER,currency TEXT,paid_at TEXT,confirmed_by_trainer_id INTEGER,confirmation_note TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO payments VALUES(31,'shop_order',11,'bank_transfer','pending',25900,'CZK',NULL,NULL,NULL,CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE shop_order_events(id INTEGER PRIMARY KEY AUTOINCREMENT,order_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $stripe = require dirname(__DIR__, 2) . '/migrations/20260809090000_stripe_checkout.php';
        $stripe['up']($pdo);
        $sumup = require dirname(__DIR__, 2) . '/migrations/20260908120000_sumup_checkout.php';
        $sumup['up']($pdo);
        $sumup['up']($pdo);
        self::assertTrue($sumup['verify']($pdo));
        return $pdo;
    }
}
