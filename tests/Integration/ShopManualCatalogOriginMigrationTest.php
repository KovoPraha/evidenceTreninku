<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ShopManualCatalogOriginMigrationTest extends TestCase
{
    public function testMigrationPreservesImportRowsAndEnablesManualRows(): void
    {
        $pdo = $this->legacyCatalog();

        $migration = require dirname(__DIR__, 2)
            . '/migrations/20260816200000_shop_manual_catalog_origin.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));

        $product = $pdo->query(
            'SELECT source_candidate_id,source_run_id,origin,created_by_trainer_id '
            . 'FROM shop_products WHERE id=501'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertSame(
            ['source_candidate_id' => 101, 'source_run_id' => 1, 'origin' => 'import',
                'created_by_trainer_id' => null],
            $product
        );
        $variant = $pdo->query(
            'SELECT source_candidate_id,origin,created_by_trainer_id FROM shop_variants WHERE id=601'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertSame(
            ['source_candidate_id' => 201, 'origin' => 'import', 'created_by_trainer_id' => null],
            $variant
        );
        self::assertSame(501, (int)$pdo->query(
            'SELECT product_id FROM shop_product_publications WHERE product_id=501'
        )->fetchColumn());

        $pdo->exec(
            "INSERT INTO shop_products "
            . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,'
            . "offer_type,catalog_status) VALUES(NULL,NULL,'manual',7,'manual:abc','Ruční produkt','goods','draft')"
        );
        $manualProductId = (int)$pdo->lastInsertId();
        $statement = $pdo->prepare(
            'INSERT INTO shop_variants '
            . '(product_id,source_candidate_id,origin,created_by_trainer_id,sku,attributes_json,price_mode,'
            . "amount_minor,currency,catalog_status) VALUES(?,NULL,'manual',7,'KP-TEST','{}','fixed',10000,"
            . "'CZK','draft')"
        );
        $statement->execute([$manualProductId]);

        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_products WHERE origin='manual' "
            . 'AND source_candidate_id IS NULL AND source_run_id IS NULL AND created_by_trainer_id=7'
        )->fetchColumn());
        self::assertSame(1, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_variants WHERE origin='manual' "
            . 'AND source_candidate_id IS NULL AND created_by_trainer_id=7'
        )->fetchColumn());
        self::assertFalse($pdo->query('PRAGMA foreign_key_check')->fetch(PDO::FETCH_ASSOC));
    }

    public function testCreatorForeignKeyRemainsFailClosed(): void
    {
        $pdo = $this->legacyCatalog();
        $migration = require dirname(__DIR__, 2)
            . '/migrations/20260816200000_shop_manual_catalog_origin.php';
        $migration['up']($pdo);

        $this->expectException(PDOException::class);
        $pdo->exec(
            "INSERT INTO shop_products "
            . '(source_candidate_id,source_run_id,origin,created_by_trainer_id,external_product_key,name,'
            . "offer_type,catalog_status) VALUES(NULL,NULL,'manual',999,'manual:bad','Neplatný','goods','draft')"
        );
    }

    private function legacyCatalog(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Admin')");
        foreach ([
            '20260802170000_shop_catalog_staging.php',
            '20260802190000_shop_catalog_review.php',
            '20260802210000_shop_canonical_catalog.php',
        ] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $pdo->exec(
            "INSERT INTO shop_catalog_import_runs "
            . '(id,source_sha256,source_filename,contract_version,status,product_count,variant_count,'
            . "warning_count,manual_review_count) VALUES(1,'" . str_repeat('a', 64)
            . "','test.csv','shop-v1','promoted',1,1,0,0)"
        );
        $pdo->exec(
            "INSERT INTO shop_catalog_product_candidates "
            . '(id,run_id,external_product_key,name,offer_type,classification_confidence,'
            . "needs_manual_review,payload_json,review_status) VALUES"
            . "(101,1,'shoptet:101','Importovaný produkt','goods','high',0,'{}','auto_classified')"
        );
        $pdo->exec(
            "INSERT INTO shop_catalog_variant_candidates "
            . '(id,run_id,product_candidate_id,sku,price_mode,amount_minor,currency,payload_json) '
            . "VALUES(201,1,101,'IMPORT-1','fixed',5000,'CZK','{}')"
        );
        $pdo->exec(
            "INSERT INTO shop_products "
            . '(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) '
            . "VALUES(501,101,1,'shoptet:101','Importovaný produkt','goods','active')"
        );
        $pdo->exec(
            "INSERT INTO shop_variants "
            . '(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
            . "catalog_status) VALUES(601,501,201,'IMPORT-1','{}','fixed',5000,'CZK','active')"
        );
        $publication = require dirname(__DIR__, 2)
            . '/migrations/20260803090000_shop_product_publication.php';
        $publication['up']($pdo);
        $pdo->exec(
            "INSERT INTO shop_product_publications "
            . '(product_id,status,public_name,public_summary,decision_note) '
            . "VALUES(501,'active','Importovaný produkt','Souhrn','Test')"
        );
        $pdo->exec(
            'CREATE TABLE variant_reference('
            . 'id INTEGER PRIMARY KEY,variant_id INTEGER NOT NULL,'
            . 'FOREIGN KEY(variant_id) REFERENCES shop_variants(id) ON DELETE RESTRICT)'
        );
        $pdo->exec('INSERT INTO variant_reference VALUES(1,601)');
        return $pdo;
    }
}
