<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use RuntimeException;
use EvidenceMigrationCatalog;
use EvidenceMigrationRunner;

require_once dirname(__DIR__, 2) . '/includes/migration_runner.php';

/** Minimal legacy bootstrap used by checkout smoke, followed by the entire migration catalog. */
function programCapacityMigratedSchema(PDO $pdo, ?callable $prepareLegacy = null): void
{
    $pdo->exec('CREATE TABLE nastaveni(klic VARCHAR(100) PRIMARY KEY,hodnota TEXT NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO nastaveni(klic,hodnota) VALUES(?,?)')->execute(['schema_version', LEGACY_SCHEMA_VERSION]);
    $pdo->exec(<<<'SQL'
        CREATE TABLE treneri(id INT AUTO_INCREMENT PRIMARY KEY,jmeno VARCHAR(100),email VARCHAR(255) UNIQUE,heslo VARCHAR(255),role VARCHAR(30),
        aktivni TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB
        SQL);
    $pdo->exec('CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(160),narozeni DATE NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE sportovist(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec(<<<'SQL'
        CREATE TABLE individualni_lekce(id INT PRIMARY KEY,nazev VARCHAR(255),datum DATE,cas_od TIME,cas_do TIME,
        public_exclusive_booking TINYINT NOT NULL DEFAULT 0,cena_kc DECIMAL(10,2)) ENGINE=InnoDB
        SQL);
    $pdo->exec("CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE NOT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'planovany') ENGINE=InnoDB");
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE mereni_zaznamy(id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE zavod_sportovec(zavod_id INT NOT NULL,sportovec_id INT NULL) ENGINE=InnoDB');
    $pdo->exec(<<<'SQL'
        CREATE TABLE verejni_uzivatele(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(160),email VARCHAR(255),
        aktivni TINYINT NOT NULL DEFAULT 1,email_overeno TINYINT NOT NULL DEFAULT 0,verifikacni_token VARCHAR(255) NULL,
        registrovan DATETIME NOT NULL) ENGINE=InnoDB
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE verejne_rezervace(id INT PRIMARY KEY,lekce_id INT NULL,stav VARCHAR(30) NOT NULL DEFAULT 'ceka',slot_cas_od TIME NULL,
        potvrzovaci_token VARCHAR(255) NULL,cas_rezervace DATETIME NOT NULL) ENGINE=InnoDB
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE kis_import_runs(id INT AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_by INT NULL,
        status ENUM('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview',source_users VARCHAR(255),source_payments VARCHAR(255),
        source_rosters VARCHAR(255),stats_json LONGTEXT,warnings_json LONGTEXT,applied_at DATETIME,note TEXT) ENGINE=InnoDB
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE kis_import_rows(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,person_key VARCHAR(180) NOT NULL,
        jmeno VARCHAR(100) NOT NULL,prijmeni VARCHAR(160) NOT NULL,narozeni DATE,email VARCHAR(255),uciid VARCHAR(80),oddil VARCHAR(160),
        kis_aktivni TINYINT NOT NULL DEFAULT 0,kis_platebne_aktivni TINYINT NOT NULL DEFAULT 0,kis_neuhrazeno DECIMAL(10,2) NOT NULL DEFAULT 0,
        kis_posledni_uhrada DATE,kis_soupisky TEXT,raw_json LONGTEXT) ENGINE=InnoDB
        SQL);
    $pdo->exec(<<<SQL
        CREATE TABLE kis_import_matches(id INT AUTO_INCREMENT PRIMARY KEY,run_id INT NOT NULL,row_id INT NOT NULL,sportovec_id INT,
        match_status ENUM('new','matched','ambiguous','conflict','ignored') NOT NULL,confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
        reason VARCHAR(255),candidate_json LONGTEXT,resolved_by INT,resolved_at DATETIME,resolved_action ENUM('create','update','link','ignore',
        'manual')) ENGINE=InnoDB
        SQL);

    if ($prepareLegacy !== null) {
        $prepareLegacy($pdo);
    }
    $catalog = EvidenceMigrationCatalog::load(dirname(__DIR__, 2) . '/migrations');
    $result = (new EvidenceMigrationRunner($pdo, $catalog))->apply();
    if (!$result['current']) {
        throw new RuntimeException('MariaDB migration catalog is not current before capacity profile.');
    }

}

/** Synthetic rows only; foreign keys stay enabled. */
function programCapacityMigratedSeed(PDO $pdo): void
{
    $pdo->exec("INSERT INTO treneri(id,jmeno,email,heslo,role,aktivni) VALUES(7,'Smoke Admin','smoke-admin@example.test','not-a-login-secret','admin',1)");
    $pdo->exec("INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,aktivni,email_overeno,registrovan) VALUES(10,'Race','Tester','race@example.test',1,1,CURRENT_TIMESTAMP)");
    $pdo->exec(<<<SQL
        INSERT INTO shop_catalog_import_runs(id,source_sha256,source_filename,contract_version,status,product_count,variant_count,warning_count,
        manual_review_count) VALUES(1,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','smoke.csv','smoke-v1','promoted',1,1,0,0)
        SQL);
    $pdo->exec(<<<SQL
        INSERT INTO shop_catalog_product_candidates(id,run_id,external_product_key,name,offer_type,classification_confidence,needs_manual_review,
        payload_json) VALUES(501,1,'race-product','Race item','program','high',0,'{}')
        SQL);
    $pdo->exec(<<<SQL
        INSERT INTO shop_catalog_variant_candidates(id,run_id,product_candidate_id,sku,price_mode,amount_minor,currency,stock_quantity_decimal,
        payload_json) VALUES(601,1,501,'RACE-ONE','fixed',12500,'CZK','1.000000','{}')
        SQL);
    $pdo->exec(<<<SQL
        INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) VALUES(501,501,1,
        'race-product','Race item','program','active')
        SQL);
    $pdo->exec(<<<SQL
        INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,includes_vat,
        vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(601,501,601,'RACE-ONE','{}','fixed',12500,'CZK',1,2100,
        '1.000000',1,'active')
        SQL);
    $pdo->exec(<<<SQL
        INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) VALUES(501,'active','Race item',
        'Concurrency smoke','Approved smoke fixture')
        SQL);

    $pdo->exec(<<<SQL

        INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,visible,

        catalog_status) VALUES(602,501,NULL,'SECOND','{}','fixed',12500,'CZK',1,'active')

        SQL);
    $pdo->exec("INSERT INTO club_seasons(id,code,name,starts_on,ends_on,status,created_by_trainer_id) VALUES(1,'PROFILE','Synthetic','2026-01-01','2027-12-31','active',7)");
    foreach ([1, 2] as $id) {
        $pdo->exec(<<<SQL
            INSERT INTO club_teams(id,season_id,code,name,discipline,age_label,status,created_by_trainer_id) VALUES($id,1,'PROFILE-$id','Synthetic',
            'all','synthetic','active',7)
            SQL);
        $pdo->exec("INSERT INTO club_programs(id,code,name,created_by_trainer_id) VALUES($id,'PROFILE-$id','Synthetic',7)");
        $variant = 600 + $id;
        $pdo->exec(<<<SQL
            INSERT INTO club_program_offers(id,program_id,season_id,team_id,product_id,variant_id,code,name,starts_on,ends_on,capacity,status,
            created_by_trainer_id) VALUES($id,$id,1,$id,501,$variant,'PROFILE-$id','Synthetic','2026-01-01','2027-12-31',20000,'active',7)
            SQL);
    }
    $pdo->exec("INSERT INTO shop_carts(id,cart_key,account_id,status) VALUES(1,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',10,'converted')");
}

function programCapacityMigratedLoad(PDO $pdo, int $size, bool $duplicates, bool $fanout): void
{
    foreach (['club_program_enrollments', 'shop_order_items', 'shop_orders', 'sportovci', 'club_program_physical_teams'] as $table) {
        $pdo->exec('DELETE FROM ' . $table);
    }
    $pdo->beginTransaction();
    if ($fanout) {
        $pdo->exec('INSERT INTO club_program_physical_teams(program_id,team_id) VALUES(1,1),(1,2),(2,1),(2,2)');
    }
    $person = $pdo->prepare("INSERT INTO sportovci(id,jmeno,prijmeni) VALUES(?,'Synthetic','LOCALHOST NEPLATIT')");
    $order = $pdo->prepare(<<<SQL
        INSERT INTO shop_orders(id,public_code,account_id,source_cart_id,idempotency_key_hash,status,payment_status,fulfillment_method,
        customer_name_snapshot,customer_email_snapshot,subtotal_minor,total_minor,currency,placed_at,payment_expires_at) VALUES(?,?,10,1,?,'placed',
        'pending','program','Synthetic','synthetic@example.test',12500,12500,'CZK','2026-10-29 11:00:00','2026-10-29 13:00:00')
        SQL);
    $item = $pdo->prepare(<<<SQL
        INSERT INTO shop_order_items(id,order_id,product_id,variant_id,product_name_snapshot,sku_snapshot,attributes_json_snapshot,quantity,
        unit_amount_minor,line_amount_minor,currency,club_program_offer_id,beneficiary_sportovec_id) VALUES(?,?,501,?,'Synthetic','PROFILE','{}',1,
        12500,12500,'CZK',?,?)
        SQL);
    $enrollment = $pdo->prepare(<<<SQL
        INSERT INTO club_program_enrollments(offer_id,sportovec_id,account_id,source_order_item_id,status,valid_from,valid_to,
        activated_at) VALUES(?,?,10,?,'active','2026-01-01','2027-12-31','2026-10-29 11:00:00')
        SQL);
    for ($i = 1; $i <= $size; $i++) {
        $personId = $duplicates ? 1 : $i;
        if (!$duplicates || $i === 1) {
            $person->execute([$personId]);
        }
        $offerId = $fanout && $i % 2 === 0 ? 2 : 1;
        $order->execute([$i, sprintf('PROFILE%011d', $i), hash('sha256', 'profile-' . $i)]);
        $item->execute([$i, $i, 600 + $offerId, $offerId, $personId]);
        if (!$duplicates && $i % 10 === 0) {
            $enrollment->execute([$offerId, $personId, $i]);
        }
    }
    $pdo->commit();
}
