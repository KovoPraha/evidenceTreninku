<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shop_bank_settings.php';

/**
 * Všechny účty jsou smyšlené: bankovní kód 9999 žádné bance nepatří.
 */
if (!defined('SHOP_BANK_IBAN')) define('SHOP_BANK_IBAN', 'CZ7599999999999999999999');
if (!defined('SHOP_BANK_BIC')) define('SHOP_BANK_BIC', '');
if (!defined('SHOP_BANK_ACCOUNT_LABEL')) define('SHOP_BANK_ACCOUNT_LABEL', 'FIKTIVNI ZALOHA Z CONFIGU');
if (!defined('SHOP_BANK_DUE_DAYS')) define('SHOP_BANK_DUE_DAYS', 7);

final class ShopBankSettingsTest extends TestCase
{
    private const DB_IBAN = 'CZ2799990000000011111111';
    private const DB_LABEL = 'FIKTIVNI UCET Z ADMINISTRACE';
    private const SECOND_IBAN = 'CZ7899990000000022222222';

    public function testDatabaseWinsOverConfigAndConfigCoversAnEmptyDatabase(): void
    {
        $pdo = $this->database();

        $withoutRow = \shopBankSettingsResolve($pdo);
        self::assertSame('config', $withoutRow['source']);
        self::assertSame(SHOP_BANK_IBAN, $withoutRow['settings']['iban']);
        self::assertFalse($withoutRow['conflict']);
        self::assertSame('z config.php', \shopBankSettingsSourceLabel($withoutRow['source']));

        $saved = \shopBankSettingsSave($pdo, 7, [
            'iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 5,
        ], 'Změna klubového účtu.', true);
        self::assertTrue($saved['changed']);

        $withRow = \shopBankSettingsResolve($pdo);
        self::assertSame('database', $withRow['source']);
        self::assertSame(self::DB_IBAN, $withRow['settings']['iban']);
        self::assertSame(5, $withRow['settings']['due_days']);
        self::assertTrue($withRow['conflict'], 'Rozdíl mezi databází a config.php se musí ohlásit.');
        self::assertSame(SHOP_BANK_IBAN, $withRow['config']['iban']);
        self::assertSame('z administrace', \shopBankSettingsSourceLabel($withRow['source']));
        self::assertSame(self::DB_IBAN, \shopBankSettingsEffective($pdo)['iban']);

        // Jeden řádek pravdy: opakované uložení nezaloží druhý záznam.
        \shopBankSettingsSave($pdo, 7, [
            'iban' => self::SECOND_IBAN, 'bic' => 'AAAACZPP', 'account_label' => self::DB_LABEL, 'due_days' => 9,
        ], 'Druhá změna.', true);
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings')->fetchColumn());
        self::assertSame(self::SECOND_IBAN, \shopBankSettingsEffective($pdo)['iban']);
    }

    public function testInvalidSettingsAreRejectedAndCheckoutStaysFailClosed(): void
    {
        $pdo = $this->database();

        foreach ([
            ['iban' => 'CZ2899990000000011111111', 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 7],
            ['iban' => self::DB_IBAN, 'bic' => '', 'account_label' => 'ab', 'due_days' => 7],
            ['iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 0],
            ['iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 31],
            ['iban' => self::DB_IBAN, 'bic' => 'NE', 'account_label' => self::DB_LABEL, 'due_days' => 7],
        ] as $index => $invalid) {
            try {
                \shopBankSettingsSave($pdo, 7, $invalid, 'Pokus o neplatné uložení.', true);
                self::fail('Neplatné nastavení #' . $index . ' nesmí projít.');
            } catch (\ShopCheckoutException) {
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings_events')->fetchColumn());

        // Bez potvrzení, bez důvodu a bez správce se rovněž nic neuloží.
        foreach ([[7, '', true], [7, 'Důvod', false], [0, 'Důvod', true]] as [$actor, $reason, $confirmed]) {
            try {
                \shopBankSettingsSave($pdo, $actor, [
                    'iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 7,
                ], $reason, $confirmed);
                self::fail('Uložení bez správce, důvodu nebo potvrzení nesmí projít.');
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings')->fetchColumn());

        // Uložený, ale poškozený záznam se nikdy tiše neobejde konstantami.
        $pdo->exec("INSERT INTO shop_bank_settings(id,iban,bic,account_label,due_days,updated_by_trainer_id) VALUES(1,'CZ0000000000000000000000','','Poškozený',7,7)");
        $broken = \shopBankSettingsResolve($pdo);
        self::assertNotSame('', $broken['database_error']);
        self::assertSame('config', $broken['source']);
        try {
            \shopBankSettingsEffective($pdo);
            self::fail('Poškozený záznam musí checkout zastavit.');
        } catch (\ShopCheckoutException $exception) {
            self::assertStringContainsString('není platný', $exception->getMessage());
        }
    }

    public function testChangingTheAccountKeepsExistingOrdersUntouched(): void
    {
        $pdo = $this->database();
        \shopBankSettingsSave($pdo, 7, [
            'iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 7,
        ], 'První účet.', true);

        \shopCartSetQuantity($pdo, 10, 601, 1);
        $first = \shopCheckoutPlace(
            $pdo, 10, bin2hex(random_bytes(16)), \shopBankSettingsEffective($pdo),
            \shopCartDetail($pdo, 10)['fingerprint']
        );
        self::assertSame(self::DB_IBAN, $first['iban_snapshot']);
        self::assertStringContainsString('ACC:' . self::DB_IBAN, (string)$first['spd_payload']);

        \shopBankSettingsSave($pdo, 7, [
            'iban' => self::SECOND_IBAN, 'bic' => '', 'account_label' => 'FIKTIVNI NOVY UCET', 'due_days' => 7,
        ], 'Klub změnil banku.', true);

        $reloaded = \shopOrderByCode($pdo, 10, (string)$first['public_code']);
        self::assertSame(self::DB_IBAN, $reloaded['iban_snapshot'], 'Starší objednávka musí držet původní účet.');
        self::assertSame(self::DB_LABEL, $reloaded['account_label_snapshot']);
        self::assertSame((string)$first['spd_payload'], (string)$reloaded['spd_payload']);
        self::assertSame((string)$first['variable_symbol'], (string)$reloaded['variable_symbol']);

        \shopCartSetQuantity($pdo, 11, 601, 1);
        $second = \shopCheckoutPlace(
            $pdo, 11, bin2hex(random_bytes(16)), \shopBankSettingsEffective($pdo),
            \shopCartDetail($pdo, 11)['fingerprint']
        );
        self::assertSame(self::SECOND_IBAN, $second['iban_snapshot'], 'Nová objednávka musí použít nový účet.');
    }

    public function testSampleQrWritesNothing(): void
    {
        $pdo = $this->database();
        \shopBankSettingsSave($pdo, 7, [
            'iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 6,
        ], 'Účet pro ukázku.', true);

        $tables = ['shop_orders', 'shop_order_items', 'payments', 'shop_inventory_movements',
            'shop_carts', 'shop_cart_items', 'shop_order_events', 'shop_bank_settings', 'shop_bank_settings_events'];
        $before = [];
        foreach ($tables as $table) $before[$table] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();

        $sample = \shopBankSampleQr(\shopBankSettingsEffective($pdo), new \DateTimeImmutable('2026-08-19 09:00:00'));

        foreach ($tables as $table) {
            self::assertSame($before[$table], (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(), $table);
        }
        self::assertSame(SHOP_BANK_SAMPLE_VARIABLE_SYMBOL, $sample['variable_symbol']);
        self::assertSame('9999999999', $sample['variable_symbol']);
        self::assertSame(100, $sample['amount_minor']);
        self::assertStringContainsString('ACC:' . self::DB_IBAN, $sample['payload']);
        self::assertStringContainsString('X-VS:9999999999', $sample['payload']);
        self::assertStringContainsString(SHOP_BANK_SAMPLE_MESSAGE, $sample['payload']);
        self::assertStringStartsWith('data:image/svg+xml', $sample['data_uri']);
        self::assertSame('2026-08-25 23:59:59', $sample['due_at']);
    }

    public function testEveryChangeIsAuditedWithActorAndPreviousValue(): void
    {
        $pdo = $this->database();
        \shopBankSettingsSave($pdo, 7, [
            'iban' => self::DB_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 7,
        ], 'Zavedení účtu.', true);
        \shopBankSettingsSave($pdo, 8, [
            'iban' => self::SECOND_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 10,
        ], 'Klub změnil banku.', true);

        $events = $pdo->query('SELECT * FROM shop_bank_settings_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $events);
        self::assertSame('trainer', $events[0]['actor_type']);
        self::assertSame(7, (int)$events[0]['actor_id']);
        self::assertSame('configure', $events[0]['action']);
        self::assertNull($events[0]['before_json']);
        self::assertSame('Zavedení účtu.', $events[0]['reason']);

        self::assertSame(8, (int)$events[1]['actor_id']);
        $before = json_decode((string)$events[1]['before_json'], true, 8, JSON_THROW_ON_ERROR);
        $after = json_decode((string)$events[1]['after_json'], true, 8, JSON_THROW_ON_ERROR);
        self::assertSame(self::DB_IBAN, $before['iban'], 'Audit musí nést předchozí hodnotu.');
        self::assertSame(7, $before['due_days']);
        self::assertSame(self::SECOND_IBAN, $after['iban']);
        self::assertSame(10, $after['due_days']);

        // Uložení beze změny nezaloží prázdný auditní řádek.
        $unchanged = \shopBankSettingsSave($pdo, 8, [
            'iban' => self::SECOND_IBAN, 'bic' => '', 'account_label' => self::DB_LABEL, 'due_days' => 10,
        ], 'Opakované uložení.', true);
        self::assertFalse($unchanged['changed']);
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings_events')->fetchColumn());
        self::assertSame(8, (int)$pdo->query('SELECT updated_by_trainer_id FROM shop_bank_settings WHERE id=1')->fetchColumn());
    }

    public function testMigrationIsRepeatable(): void
    {
        $pdo = $this->database();
        $migration = require dirname(__DIR__, 2) . '/migrations/20260819120000_shop_bank_settings.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_bank_settings')->fetchColumn());
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,email TEXT,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec("INSERT INTO verejni_uzivatele VALUES(10,'Rodič','Test','parent@example.test',1,1),(11,'Druhý','Test','second@example.test',1,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin'),(8,'Druhý admin')");
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT NULL)');
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,offer_type TEXT,catalog_status TEXT)');
        $pdo->exec("INSERT INTO shop_products VALUES(501,'goods','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec("INSERT INTO shop_variants VALUES(601,501,'TRIKO-M','{}','fixed',12500,'CZK',1,2100,'50.000000',1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');
        $pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Tričko KOVO','Klubové tričko.')");
        $pdo->exec('CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');

        foreach ([
            '20260803230000_shop_checkout.php',
            '20260804010000_shop_order_fulfillment.php',
            '20260804030000_shop_order_refunds.php',
            '20260804050000_shop_coupons.php',
            '20260804120000_shop_item_beneficiaries.php',
            '20260804210000_shop_order_expiration.php',
            '20260804234000_shop_coupon_applicability.php',
            '20260805010000_shop_member_pricing.php',
            '20260809090000_stripe_checkout.php',
            '20260819120000_shop_bank_settings.php',
        ] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo), $filename);
        }
        return $pdo;
    }
}
