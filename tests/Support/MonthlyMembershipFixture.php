<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use ReflectionMethod;

require_once dirname(__DIR__).'/Integration/MemberFeePlanTest.php';
require_once dirname(__DIR__, 2).'/includes/staff_workspaces.php';
require_once dirname(__DIR__, 2).'/includes/monthly_membership_worker.php';
require_once dirname(__DIR__, 2).'/includes/fio_sync.php';

final class MonthlyMembershipFixture
{
    public static function database(): PDO
    {
        if (!defined('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED')) {
            define('MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED', true);
        }
        $base = new \Tests\Integration\MemberFeePlanTest('testPreviewSkipsMissingPayerAndGenerationIsIdempotent');
        $p = (new ReflectionMethod($base, 'database'))->invoke($base);
        foreach (['20260922120000_member_fee_plans','20260922190000_member_fee_standing_orders','20261021120000_member_payer_variable_symbols'
    ,'20261022221000_accounting_documents','20261105150000_monthly_memberships'] as $name) {
            $m = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $m['up']($p);
        }
        $p->exec(('ALTER TABLE treneri ADD COLUMN aktivni INTEGER DEFAULT 1;
            ALTER TABLE payments ADD COLUMN payment_source TEXT;
' .
    '            ALTER TABLE payments ADD COLUMN bank_movement_id TEXT;
            DROP TABLE club_roster_members;
            ' .
    'CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,sportovec_id INTEGER,status '
    .
    'TEXT,source TEXT,source_ref TEXT,valid_from TEXT,valid_to TEXT,created_by_trainer_id INTEGER,updated_at TEXT DEFAULT '
    .
    'CURRENT_TIMESTAMP);
            CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id ' .
    'INTEGER,roster_member_id INTEGER,actor_trainer_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,note TEXT);
         ' .
    '   CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);
            CREATE TABLE ' .
    'staff_superadmins(trainer_id INTEGER);
            INSERT INTO staff_user_positions VALUES(7,\'finance_manager\');
          ' .
    '  INSERT INTO verejni_uzivatele VALUES(30,\'Cizí\',\'Účet\',\'other@example.test\',1,1);
            INSERT INTO ' .
    'account_person_roles VALUES(2,20,2,\'guardian\',\'approved\',\'2020-01-01\',NULL);
            CREATE TABLE shop_products(id ' .
    'INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT);
            CREATE TABLE shop_variants(id INTEGER ' .
    'PRIMARY KEY,product_id INTEGER,sku TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,catalog_status TEXT,visible '
    .
    'INTEGER,stock_quantity_decimal TEXT);
            CREATE TABLE shop_product_publications(product_id INTEGER,status ' .
    'TEXT,public_name TEXT);
            CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,variant_id INTEGER,product_id INTEGER);
          ' .
    '  INSERT INTO shop_products VALUES(1,\'LOCALHOST Závodní tým\',\'program\',\'active\');
            INSERT INTO shop_variants ' .
    'VALUES(1,1,\'LOCALHOST-MONTHLY\',\'fixed\',120000,\'CZK\',\'active\',1,NULL);
            INSERT INTO shop_product_publications ' .
    'VALUES(1,\'active\',\'LOCALHOST Závodní tým\');
            CREATE TABLE club_finance_settings(id INTEGER PRIMARY ' .
    'KEY,enforcement_enabled INTEGER DEFAULT 0,grace_days INTEGER DEFAULT 14,registration_limit_minor INTEGER DEFAULT '
    .
    '200000,training_notice_minor INTEGER DEFAULT 500000);
            INSERT INTO club_finance_settings(id) VALUES(1);
        ' .
    '    CREATE TABLE club_finance_receipts(id INTEGER PRIMARY KEY,reference TEXT,amount_minor INTEGER,paid_on TEXT,sportovec_id '
    .
    'INTEGER,currency TEXT);
            CREATE TABLE club_finance_allocations(receipt_id INTEGER,charge_id INTEGER,order_id ' .
    'INTEGER,amount_minor INTEGER);
            CREATE TABLE shop_orders(id INTEGER PRIMARY KEY,account_id INTEGER,public_code ' .
    'TEXT,total_minor INTEGER,currency TEXT,status TEXT,payment_status TEXT,finance_sportovec_id INTEGER,payment_expires_at '
    .
    'TEXT);
        '));
        $p->exec(('ALTER TABLE shop_variants ADD COLUMN attributes_json TEXT DEFAULT \'{}\';
            ALTER TABLE shop_product_publications ' .
    'ADD COLUMN public_summary TEXT DEFAULT \'LOCALHOST měsíční nabídka\';
            CREATE TABLE shop_product_images(id INTEGER ' .
    'PRIMARY KEY,product_id INTEGER,image_url TEXT,sort_order INTEGER);
            CREATE TABLE shop_product_categories(id ' .
    'INTEGER PRIMARY KEY,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER);
        '));
        $fio = require dirname(__DIR__, 2).'/migrations/20260804070000_fio_readonly_import.php';
        $fio['up']($p);
        $p->exec(('ALTER TABLE fio_account_movements ADD COLUMN confirmed_at TEXT; ALTER TABLE fio_account_movements ADD COLUMN '
    .
    'confirmed_by_trainer_id INTEGER; ALTER TABLE fio_account_movements ADD COLUMN confirmation_note TEXT'));
        $_SESSION = ['trener_id' => 7,'staff_positions' => ['finance_manager'],'staff_active_position' => 'finance_manager'];
        \accountingDocumentConfigureIssuer($p, ['name' => 'LOCALHOST Klub','address' => 'LOCALHOST Praha 1','registration_id'
    => 'LOCALHOST','confirmed'
    => '1','approval_evidence' => 'LOCALHOST schválená metodika']);
        \accountingDocumentConfigureRule($p, ['source_type' => 'member_charge','document_kind' => 'contribution_confirmation'
    ,'category_code' => 'CLEN','activity'
    => 'ODDIL','title' => 'LOCALHOST měsíční členství','valid_from' => '2020-01-01','confirmed' => '1','approval_evidence'
    => 'LOCALHOST účetní podklad']);
        foreach ([2026,2027] as $year) {
            \accountingDocumentConfigureSeries($p, ['kind' => 'contribution_confirmation','series_year' => $year
    ,'prefix' => 'CLEN','next_number' =>
    1,'confirmed' => '1','reason' => 'LOCALHOST testovací řada']);
        }
        return $p;
    }

    public static function tariff(PDO $p): int
    {
        return \monthlyMembershipConfigure($p, ['variant_id' => 1,'team_id' => 10,'due_day' => 15,'terms_text'
    => 'LOCALHOST podmínky: měsíční plná cena, ukončení na konci měsíce.','issuer_id'
    => 1,'rule_id' => 1,'document_kind' => 'contribution_confirmation','confirmed' => '1']);
    }

    public static function subscribe(PDO $p, int $tariff, int $person = 1, string $date = '2026-09-01'): int
    {
        $hash = $p->query('SELECT terms_sha256 FROM monthly_membership_tariffs WHERE id='.(int)$tariff)->fetchColumn();
        return \monthlyMembershipSubscribe($p, 20, $person, $tariff, ['name' => 'LOCALHOST Rodič','address' => 'LOCALHOST Praha'
    ,'registration_id'
    => ''], $hash, hash('sha256', 'request:'.$person.':'.$date), true, new \DateTimeImmutable($date));
    }

    public static function movement(PDO $p, string $reference, string $amount, string $date = '2026-09-16', ?string $vs = null): int
    {
        $vs ??= (string)$p->query('SELECT variable_symbol FROM member_payer_variable_symbols WHERE payer_account_id=20')
    ->fetchColumn();
        $payload = ['accountStatement' => ['info' => ['iban' => 'CZ6508000000192000145399'],'transactionList' => ['transaction'
    => [[
            'column0' => ['value' => $date.'+02:00'],'column1' => ['value' => $amount],'column5' => ['value' => $vs]
    ,
            'column8' => ['value' => 'Převod'],'column14' => ['value' => 'CZK'],'column22' => ['value' => $reference]
    ,
        ]]]]];
        \fioImportJson($p, json_encode($payload, JSON_THROW_ON_ERROR), $date, $date, 'CZ6508000000192000145399');
        $q = $p->prepare('SELECT id FROM fio_account_movements WHERE fio_movement_id=?');
        $q->execute([$reference]);
        return (int)$q->fetchColumn();
    }
}
