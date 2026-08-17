<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_publication.php';

final class ShopCatalogPublicationTest extends TestCase
{
    public function testGoodsActivationIsExplicitAuditedAndIdempotent(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = $this->readyGoodsId($pdo);

        $first = \shopCatalogPublicationActivate(
            $pdo,
            $productId,
            7,
            'Veřejný název produktu',
            'Bezpečný popis určený pro budoucí veřejný katalog.',
            'Ručně zkontrolována cena, viditelnost a text.',
            true
        );
        $same = \shopCatalogPublicationActivate(
            $pdo,
            $productId,
            7,
            'Veřejný název produktu',
            'Bezpečný popis určený pro budoucí veřejný katalog.',
            'Opakované odeslání.',
            true
        );

        self::assertTrue($first['changed']);
        self::assertFalse($same['changed']);
        self::assertSame('active', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
        self::assertGreaterThan(0, (int)$pdo->query(
            "SELECT COUNT(*) FROM shop_variants WHERE product_id=$productId AND catalog_status='active'"
        )->fetchColumn());
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publication_events')->fetchColumn());

        try {
            \shopCatalogPublicationActivate($pdo, $productId, 7, 'Jiný název', 'Jiný bezpečný popis.', 'Pokus o přepis.', true);
            self::fail('Active public copy must not be silently replaced.');
        } catch (\ShopCatalogPublicationException) {
            self::assertSame('Veřejný název produktu', $pdo->query(
                "SELECT public_name FROM shop_product_publications WHERE product_id=$productId"
            )->fetchColumn());
        }

        $disabled = \shopCatalogPublicationDeactivate($pdo, $productId, 7, 'Dočasně staženo z nabídky.');
        $sameDisabled = \shopCatalogPublicationDeactivate($pdo, $productId, 7, 'Opakování.');
        self::assertTrue($disabled['changed']);
        self::assertFalse($sameDisabled['changed']);
        self::assertSame('inactive', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
        self::assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publication_events')->fetchColumn());

        $reactivated = \shopCatalogPublicationActivate(
            $pdo,
            $productId,
            7,
            'Nový veřejný název',
            'Nově schválený bezpečný veřejný popis.',
            'Nová ruční kontrola.',
            true
        );
        self::assertTrue($reactivated['changed']);
        self::assertSame(3, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publication_events')->fetchColumn());
    }

    public function testK3OfferTypesStayBlockedWithoutAnyPublicationWrite(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = (int)$pdo->query(
            "SELECT id FROM shop_products WHERE offer_type<>'goods' ORDER BY id LIMIT 1"
        )->fetchColumn();
        self::assertGreaterThan(0, $productId);

        $readiness = \shopCatalogPublicationReadiness($pdo, $productId);
        self::assertFalse($readiness['ready']);
        self::assertStringContainsString('K3', implode(' ', $readiness['blockers']));
        try {
            \shopCatalogPublicationActivate($pdo, $productId, 7, 'Akce', 'Popis klubové akce.', 'Pokus.', true);
            self::fail('K3 product must stay blocked.');
        } catch (\ShopCatalogPublicationException) {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publications')->fetchColumn());
            self::assertSame('draft', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
        }
    }

    public function testProgramRequiresOfferLinkAndStaysActiveWhenOfferIsNoLongerSaleable(): void
    {
        $pdo=$this->databaseWithDraftCatalog();
        $productId=(int)$pdo->query("SELECT id FROM shop_products WHERE offer_type<>'goods' ORDER BY id LIMIT 1")->fetchColumn();
        $pdo->exec("UPDATE shop_products SET offer_type='program' WHERE id=$productId");
        $pdo->exec("CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT NOT NULL,scope_key TEXT NOT NULL,consent_purpose TEXT NOT NULL,event_id INTEGER NULL,terms_version TEXT NOT NULL,consent_text_plain TEXT NOT NULL,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT NOT NULL,actor_id INTEGER NULL,status TEXT NOT NULL DEFAULT 'active',archived_at TEXT NULL,archived_by_trainer_id INTEGER NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))");
        $variantId=(int)$pdo->query("SELECT id FROM shop_variants WHERE product_id=$productId ORDER BY id LIMIT 1")->fetchColumn();
        $blocked=\shopCatalogPublicationReadiness($pdo,$productId);self::assertFalse($blocked['ready']);self::assertStringContainsString('nemá navázanou',implode(' ',$blocked['blockers']));
        $pdo->exec("INSERT INTO club_programs(id,name,status) VALUES(1,'Rajčátka','active')");
        $pdo->exec("INSERT INTO club_program_offers(id,program_id,product_id,variant_id,status,ends_on,capacity) VALUES(1,1,$productId,$variantId,'draft','2027-08-31',12)");
        $missingTerms=\shopCatalogPublicationReadiness($pdo,$productId);self::assertFalse($missingTerms['ready']);self::assertStringContainsString('storno podmínky',implode(' ',$missingTerms['blockers']));
        \clubProgramTermsConfigure($pdo,7,'program',1,'program_cancellation','Storno podmínky programu.',true);
        \clubProgramTermsConfigure($pdo,7,'program',1,'program_consent','Souhlas s přihlášením.',true);
        self::assertTrue(\shopCatalogPublicationReadiness($pdo,$productId)['ready']);
        \shopCatalogPublicationActivate($pdo,$productId,7,'Rajčátka','Cyklistický kroužek pro děti.','Navázaná nabídka byla ověřena.',true);
        $pdo->exec("UPDATE club_program_offers SET status='active',ends_on='2000-01-01'");
        $row=array_values(array_filter(\shopCatalogPublicationProducts($pdo),static fn(array $row):bool=>(int)$row['id']===$productId))[0];
        self::assertSame('active',$row['catalog_status']);self::assertFalse($row['program_saleable']);self::assertStringContainsString('skončila',$row['program_sale_reason']);
    }

    public function testInvalidVisibleVariantRollsBackActivation(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = $this->readyGoodsId($pdo);
        $pdo->exec(
            "UPDATE shop_variants SET amount_minor=NULL WHERE product_id=$productId "
            . "AND (visible=1 OR visible IS NULL) AND price_mode='fixed'"
        );

        $this->expectException(\ShopCatalogPublicationException::class);
        try {
            \shopCatalogPublicationActivate($pdo, $productId, 7, 'Produkt', 'Bezpečný popis produktu.', 'Kontrola.', true);
        } finally {
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publications')->fetchColumn());
            self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publication_events')->fetchColumn());
            self::assertSame('draft', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
        }
    }

    public function testConfirmationAndPlainPublicCopyAreRequiredBeforeWriting(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = $this->readyGoodsId($pdo);
        foreach ([
            [$productId, 7, 'Produkt', 'Bezpečný popis.', 'Důvod.', false],
            [$productId, 7, '<b>Produkt</b>', 'Bezpečný popis.', 'Důvod.', true],
            [$productId, 7, 'Produkt', '', 'Důvod.', true],
        ] as $arguments) {
            try {
                \shopCatalogPublicationActivate($pdo, ...$arguments);
                self::fail('Invalid publication decision must be rejected.');
            } catch (\InvalidArgumentException) {
                // Expected before the transaction.
            }
        }
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publications')->fetchColumn());
    }

    private function databaseWithDraftCatalog(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE treneri (id INTEGER PRIMARY KEY, jmeno TEXT NOT NULL)');
        $pdo->exec("INSERT INTO treneri VALUES (7, 'Admin')");
        foreach ([
            '20260802170000_shop_catalog_staging.php',
            '20260802190000_shop_catalog_review.php',
            '20260802210000_shop_canonical_catalog.php',
            '20260816200000_shop_manual_catalog_origin.php',
            '20260803090000_shop_product_publication.php',
        ] as $filename) {
            $migration = require dirname(__DIR__, 2) . '/migrations/' . $filename;
            $migration['up']($pdo);
            $migration['up']($pdo);
            self::assertTrue($migration['verify']($pdo));
        }
        $input = dirname(__DIR__) . '/fixtures/shoptet/products-offer-types.csv';
        $catalog = \ShopCatalogContract::build(\ShoptetProductInput::read($input));
        $run = \shopCatalogStage($pdo, $catalog);
        $pending = $pdo->query(
            "SELECT id FROM shop_catalog_product_candidates WHERE run_id={$run['run_id']} AND review_status='pending'"
        )->fetchColumn();
        \shopCatalogReviewProduct($pdo, $run['run_id'], (int)$pending, 7, 'approve', 'goods', 'Fyzické zboží.');
        \shopCatalogPromote($pdo, $run['run_id'], 7, true);
        $pdo->exec('CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,product_id INTEGER,variant_id INTEGER,status TEXT,ends_on TEXT,sales_open_at TEXT NULL,sales_close_at TEXT NULL,capacity INTEGER NULL)');
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,status TEXT)');
        return $pdo;
    }

    private function readyGoodsId(PDO $pdo): int
    {
        foreach ($pdo->query("SELECT id FROM shop_products WHERE offer_type='goods' ORDER BY id")->fetchAll() as $row) {
            $readiness = \shopCatalogPublicationReadiness($pdo, (int)$row['id']);
            if ($readiness['ready']) {
                return (int)$row['id'];
            }
        }
        self::fail('Fixture must contain at least one publication-ready goods product.');
    }
}
