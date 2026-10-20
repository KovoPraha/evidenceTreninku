<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Synthetic SQLite schema assembled from the production migration contracts. */
final class ParentJourneyAcceptanceFixture
{
    public static function create():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY AUTOINCREMENT,jmeno TEXT,prijmeni TEXT,email TEXT,heslo_hash TEXT DEFAULT \'\',telefon TEXT NULL,verifikacni_token TEXT NULL,verifikacni_token_expires_at TEXT NULL,aktivni INTEGER,email_overeno INTEGER)');$pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno) VALUES(10,'Rodič','A','a@test.test',1,1),(11,'Rodič','B','b@test.test',1,1)");
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');$pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");$pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT NULL)');$pdo->exec("INSERT INTO sportovci VALUES(101,'Dítě','Jedna','2017-04-03'),(103,'Dítě','Cizí','2012-05-06'),(104,'Bez','Data',NULL)");
        $roles=require dirname(__DIR__,2).'/migrations/20260802230000_account_person_roles.php';$roles['up']($pdo);$pdo->exec("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,source,valid_from,created_by_trainer_id,approved_by_trainer_id,decision_note) VALUES(10,101,'guardian','approved','admin','2020-01-01',7,7,'test'),(11,103,'guardian','approved','admin','2020-01-01',7,7,'test'),(10,104,'guardian','approved','admin','2020-01-01',7,7,'test')");
        $pdo->exec('CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,offer_type TEXT,catalog_status TEXT)');$pdo->exec("INSERT INTO shop_products VALUES(501,'Kroužek A','program','active'),(502,'Kroužek B','program','active')");
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,attributes_json TEXT,price_mode TEXT,amount_minor INTEGER,currency TEXT,includes_vat INTEGER,vat_rate_basis_points INTEGER,stock_quantity_decimal TEXT,visible INTEGER,catalog_status TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');$pdo->exec("INSERT INTO shop_variants VALUES(601,501,'A','{}','fixed',10000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP),(602,502,'B','{}','fixed',12000,'CZK',1,0,NULL,1,'active',CURRENT_TIMESTAMP)");
        $pdo->exec('CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT)');$pdo->exec("INSERT INTO shop_product_publications VALUES(501,'active','Kroužek A','A'),(502,'active','Kroužek B','B')");
        $pdo->exec("CREATE TABLE club_event_notifications(id INTEGER PRIMARY KEY AUTOINCREMENT,registration_id INTEGER NULL,registration_event_id INTEGER NULL,order_id INTEGER NULL,notification_type TEXT NOT NULL,recipient_email TEXT NOT NULL,recipient_name TEXT NOT NULL,subject_plain TEXT NOT NULL,body_plain TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',attempts INTEGER NOT NULL DEFAULT 0,available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,claimed_at TEXT NULL,claim_token TEXT NULL,sent_at TEXT NULL,last_error TEXT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(order_id,notification_type))");
        $pdo->exec('CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT NOT NULL,scope_key TEXT NOT NULL,consent_purpose TEXT NOT NULL,event_id INTEGER NULL,terms_version TEXT NOT NULL,consent_text_plain TEXT NOT NULL,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))');
        foreach(['20260802233000_account_person_claim_requests.php','20260816143000_athlete_registration_foundation.php','20260816180000_registration_terms_scope.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);\PHPUnit\Framework\Assert::assertTrue($migration['verify']($pdo));}
        $pdo->exec("UPDATE club_event_term_versions SET consent_text_plain='Syntetické schválené informace pro integrační test.' WHERE scope_type='athlete_registration' AND consent_purpose IN ('member_data_notice','birth_number_legal_notice')");
        foreach(['20260803230000_shop_checkout.php','20260804010000_shop_order_fulfillment.php','20260804030000_shop_order_refunds.php','20260804050000_shop_coupons.php','20260804120000_shop_item_beneficiaries.php','20260804090000_kis_teams_rosters.php','20260809090000_stripe_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);\PHPUnit\Framework\Assert::assertTrue($migration['verify']($pdo));}
        $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'SCHOOL','Školní rok','2026-09-01','2027-08-31','active',7)");$pdo->exec("INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES(10,1,'A','Kroužek A','vše','děti','active',7)");
        foreach(['20260804140000_club_programs.php','20260804160000_club_program_lifecycle.php','20260804235000_club_program_repeat_enrollment.php','20260817090000_club_program_events.php','20260817130000_club_program_offer_age.php','20260817150000_club_program_terms.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);\PHPUnit\Framework\Assert::assertTrue($migration['verify']($pdo));}
        $expiration=require dirname(__DIR__,2).'/migrations/20260804210000_shop_order_expiration.php';$expiration['up']($pdo);\PHPUnit\Framework\Assert::assertTrue($expiration['verify']($pdo));
        foreach(['20260922140000_low_friction_storefront.php','20260922150000_quick_program_checkout.php']as$file){$migration=require dirname(__DIR__,2).'/migrations/'.$file;$migration['up']($pdo);\PHPUnit\Framework\Assert::assertTrue($migration['verify']($pdo));}
        \Tests\Support\ParentJourneyNotificationFixture::install($pdo);
                $pdo->exec("ALTER TABLE verejni_uzivatele ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1");
        $pdo->exec("ALTER TABLE verejni_uzivatele ADD COLUMN trener_id INTEGER NULL");
        foreach (['email','telefon','adresa_ulice','adresa_cp','adresa_co','adresa_obec','adresa_psc','hash','kis_external_id'] as $column) $pdo->exec('ALTER TABLE sportovci ADD COLUMN '.$column.' TEXT');
        foreach (['uci','stav_manualni'] as $column) $pdo->exec('ALTER TABLE sportovci ADD COLUMN '.$column.' INTEGER DEFAULT 0');
        $pdo->exec('ALTER TABLE sportovci ADD COLUMN stav_clenstvi TEXT');
        $pdo->exec('CREATE TABLE ucto_audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT,uzivatel_id INTEGER,akce TEXT,tabulka TEXT,zaznam_id INTEGER NULL,detail TEXT,ip_adresa TEXT,user_agent TEXT)');
        $pdo->exec('CREATE TABLE child_access_accounts(id INTEGER PRIMARY KEY)');
        $migration=require dirname(__DIR__,2).'/migrations/20260804235900_password_reset_tokens.php';$migration['up']($pdo);
        ParentJourneyNotificationFixture::install($pdo);
        return $pdo;
    }
}
