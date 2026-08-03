<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_checkout.php';

final class PublicVelodromeShopTest extends TestCase
{
    private const BANK = [
        'iban' => 'CZ6508000000192000145399',
        'bic' => 'GIBACZPX',
        'account_label' => 'KOVO Praha',
        'due_days' => 7,
    ];

    public function testCheckoutCreatesSnapshotQrAndActivatesReservationExactlyOnce(): void
    {
        $pdo = $this->database();
        $slot = $this->slot($pdo, 2, 25000);
        $added = \publicVelodromeShopAddToCart($pdo, 10, $slot, 'Vlastní kolo.');
        self::assertTrue($added['created']);
        self::assertFalse(\publicVelodromeShopAddToCart($pdo, 10, $slot, 'Aktualizovaná poznámka.')['created']);
        $cart = \shopCartDetail($pdo, 10);
        self::assertSame(25000, $cart['total_minor']);
        self::assertCount(1, $cart['velodrome_items']);

        $key = bin2hex(random_bytes(16));
        $order = \shopCheckoutPlace($pdo, 10, $key, self::BANK, $cart['fingerprint']);
        self::assertFalse($order['replayed']);
        self::assertCount(1, $order['velodrome_items']);
        self::assertStringContainsString('AM:250.00', (string)$order['spd_payload']);
        $snapshot = $pdo->query('SELECT * FROM public_velodrome_order_items')->fetch(PDO::FETCH_ASSOC);
        self::assertSame($slot, (int)$snapshot['lesson_id']);
        self::assertSame('Veřejná hodina velodromu', $snapshot['lesson_name_snapshot']);
        self::assertSame(25000, (int)$snapshot['unit_amount_minor']);
        self::assertSame(10, (int)$pdo->query('SELECT uzivatel_id FROM verejne_rezervace')->fetchColumn());
        self::assertSame('ceka', $pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());

        $replay = \shopCheckoutPlace($pdo, 10, $key, self::BANK, $cart['fingerprint']);
        self::assertTrue($replay['replayed']);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM verejne_rezervace')->fetchColumn());

        $paid = \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'Platba ověřena.', true);
        self::assertSame(1, $paid['velodrome_activated']);
        self::assertSame('potvrzena', $pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT zaplaceno FROM verejne_rezervace')->fetchColumn());
        $again = \shopOrderAdminConfirmBankPayment($pdo, (int)$order['payment_id'], 7, 'Opakování.', true);
        self::assertSame(0, $again['velodrome_activated']);

        $reservationId = (int)$snapshot['reservation_id'];
        try {
            \publicVelodromeManualConfirm($pdo, $reservationId, 7, 'Obejití objednávky.', true);
            self::fail('Order-linked manual confirmation must fail.');
        } catch (\PublicVelodromeException $exception) {
            self::assertStringContainsString('objednávce', $exception->getMessage());
        }
        try {
            \publicVelodromeCancel($pdo, $reservationId, 10, 'Obejití storna.');
            self::fail('Order-linked direct cancellation must fail.');
        } catch (\PublicVelodromeException $exception) {
            self::assertStringContainsString('objednávky', $exception->getMessage());
        }

        $cancel = \shopOrderAdminCancel($pdo, (int)$order['id'], 7, 'Zákazník odstoupil.', true);
        self::assertSame('refund_required', $cancel['payment_status']);
        self::assertSame(1, $cancel['velodrome_cancelled']);
        self::assertSame('zrusena', $pdo->query('SELECT stav FROM verejne_rezervace')->fetchColumn());
        self::assertNull($pdo->query('SELECT active_token FROM verejne_rezervace')->fetchColumn() ?: null);
        self::assertTrue(\shopOrderAdminConfirmRefund($pdo, (int)$order['id'], 7, 'REF-VELO-1', 'Vratka odeslána.', true)['changed']);
    }

    public function testMixedGoodsAndVelodromeCartKeepsBothItemShapesIntact(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO shop_products(id,offer_type,catalog_status) VALUES(100,'goods','active')");
        $pdo->exec("INSERT INTO shop_variants(id,product_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(200,100,'TEST-MIX','{}','fixed',5000,'CZK',1,0,'10',1,'active')");
        $pdo->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary) VALUES(100,'active','Testovací zboží','Smíšený košík')");
        $cart = \shopCartGetOrCreate($pdo, 10);
        $pdo->prepare('INSERT INTO shop_cart_items(cart_id,variant_id,quantity) VALUES(?,?,1)')
            ->execute([(int)$cart['id'], 200]);
        \publicVelodromeShopAddToCart($pdo, 10, $this->slot($pdo, 2, 25000));

        $detail = \shopCartDetail($pdo, 10);

        self::assertSame(200, (int)$detail['items'][0]['variant_id']);
        self::assertSame('Testovací zboží', $detail['items'][0]['public_name']);
        self::assertArrayNotHasKey('lesson_id', $detail['items'][0]);
        self::assertArrayHasKey('lesson_id', $detail['velodrome_items'][0]);
        self::assertSame(30000, $detail['total_minor']);
    }

    public function testCapacityIsHeldAtCheckoutAndLosingCheckoutRollsBackCompletely(): void
    {
        $pdo = $this->database();
        $slot = $this->slot($pdo, 1, 10000);
        \publicVelodromeShopAddToCart($pdo, 10, $slot);
        \publicVelodromeShopAddToCart($pdo, 11, $slot);
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM verejne_rezervace')->fetchColumn());

        $firstCart = \shopCartDetail($pdo, 10);
        \shopCheckoutPlace($pdo, 10, bin2hex(random_bytes(16)), self::BANK, $firstCart['fingerprint']);
        $secondCart = \shopCartDetail($pdo, 11);
        try {
            \shopCheckoutPlace($pdo, 11, bin2hex(random_bytes(16)), self::BANK, $secondCart['fingerprint']);
            self::fail('Second checkout must lose capacity race.');
        } catch (\ShopCheckoutException $exception) {
            self::assertStringContainsString('Kapacita', $exception->getMessage());
        }
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM verejne_rezervace')->fetchColumn());
        self::assertFalse($pdo->inTransaction());
    }

    public function testFreeReservationPathIsUnchanged(): void
    {
        $pdo = $this->database();
        $slot = $this->slot($pdo, 2, 0);
        $reservation = \publicVelodromeReserve($pdo, $slot, 10);
        self::assertSame('potvrzena', $reservation['status']);
        self::assertSame(1, (int)$pdo->query('SELECT zaplaceno FROM verejne_rezervace')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM public_velodrome_order_items')->fetchColumn());
    }

    public function testForwardMigrationIsRepeatableOnSqlite(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260804200000_public_velodrome_shop.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
    }

    private function slot(PDO $pdo, int $capacity, int $priceMinor): int
    {
        $year = (int)date('Y') + 1;
        return (int)\publicVelodromeCreateSlot(
            $pdo, 7, "$year-07-01", '10:00', '11:00', $capacity, false, $priceMinor
        )['id'];
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,heslo TEXT,role TEXT,aktivni INTEGER)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin','admin@example.test','x','admin',1)");
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,telefon TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Účet','První','a@example.test','777111222',1,1),(11,'Účet','Druhý','b@example.test','777222333',1,1)");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT NOT NULL,prijmeni TEXT NOT NULL,narozeni TEXT NOT NULL,email TEXT NOT NULL,telefon TEXT NULL,hash TEXT NOT NULL,uci INTEGER NOT NULL,stav_clenstvi TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT UNIQUE,nazev TEXT,je_verejne INTEGER,aktivni INTEGER,max_kapacita INTEGER)');
        $pdo->exec("INSERT INTO sportovist VALUES(20,'velodrom','Velodrom',1,1,10)");
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY AUTOINCREMENT,trener_id INTEGER NOT NULL,sportoviste_id INTEGER NOT NULL,datum TEXT NOT NULL,cas_od TEXT NOT NULL,cas_do TEXT NOT NULL,slot_delka_min INTEGER NOT NULL,typ TEXT NOT NULL,nazev TEXT NOT NULL,popis TEXT,cena_kc NUMERIC NOT NULL,max_osob INTEGER NOT NULL,vyjimka_3_dny INTEGER NOT NULL,stav TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE verejne_rezervace(id INTEGER PRIMARY KEY AUTOINCREMENT,lekce_id INTEGER NOT NULL,uzivatel_id INTEGER NOT NULL,stav TEXT NOT NULL,zaplaceno INTEGER NOT NULL DEFAULT 0,poznamka_klienta TEXT,poznamka_trenera TEXT,potvrzovaci_token TEXT,cas_rezervace TEXT DEFAULT CURRENT_TIMESTAMP,cas_potvrzeni TEXT,slot_cas_od TEXT,slot_cas_do TEXT,potvrzovaci_token_expires_at TEXT)');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        foreach ([
            '20260802230000_account_person_roles.php',
            '20260803230000_shop_checkout.php',
            '20260804010000_shop_order_fulfillment.php',
            '20260804030000_shop_order_refunds.php',
            '20260804050000_shop_coupons.php',
            '20260804180000_public_velodrome.php',
            '20260804200000_public_velodrome_shop.php',
        ] as $file) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $file;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo), $file);
        }
        \publicProfileSave($pdo, 10, 'Anna', 'První', '1990-01-01', '777111222');
        \publicProfileSave($pdo, 11, 'Běla', 'Druhá', '1991-01-01', '777222333');
        return $pdo;
    }
}
