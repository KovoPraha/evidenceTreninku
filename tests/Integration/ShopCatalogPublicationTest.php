<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shoptet_product_input.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_stage.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_review.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_promotion.php';
require_once dirname(__DIR__, 2) . '/includes/shop_catalog_publication.php';
require_once dirname(__DIR__) . '/Support/ShopAccountingPublicationFixture.php';

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

    public function testCampRequiresDomainLinkAndCanThenBeActivated(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = (int)$pdo->query(
            "SELECT id FROM shop_products WHERE offer_type='camp' ORDER BY id LIMIT 1"
        )->fetchColumn();
        self::assertGreaterThan(0, $productId);
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,event_type TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE shop_product_event_links(product_id INTEGER NOT NULL,event_id INTEGER NOT NULL)');

        $readiness = \shopCatalogPublicationReadiness($pdo, $productId);
        self::assertFalse($readiness['ready']);
        self::assertStringContainsString('propojte', implode(' ', $readiness['blockers']));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_product_publications')->fetchColumn());

        $pdo->exec("INSERT INTO club_events VALUES(1,'camp')");
        $pdo->exec("INSERT INTO shop_product_event_links VALUES($productId,1)");
        self::assertTrue(\shopCatalogPublicationReadiness($pdo, $productId)['ready']);
        $result = \shopCatalogPublicationActivate(
            $pdo, $productId, 7, 'Letní tábor', 'Přihlášení na klubový letní tábor.',
            'Tábor je propojený s pracovní akcí a připravený k otevření přihlášek.', true
        );
        self::assertTrue($result['changed']);
        self::assertSame('active', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
    }

    public function testClubEventRequiresMatchingDomainLinkAndCanThenBeActivated(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = (int)$pdo->query("SELECT id FROM shop_products WHERE offer_type='camp' ORDER BY id LIMIT 1")->fetchColumn();
        self::assertGreaterThan(0, $productId);
        $pdo->exec("UPDATE shop_products SET offer_type='club_event' WHERE id=$productId");
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,event_type TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE shop_product_event_links(product_id INTEGER NOT NULL,event_id INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO club_events VALUES(1,'camp'),(2,'club_event')");
        $pdo->exec("INSERT INTO shop_product_event_links VALUES($productId,1)");
        $mismatched=\shopCatalogPublicationReadiness($pdo,$productId);
        self::assertFalse($mismatched['ready']);
        self::assertStringContainsString('Klubová akce',implode(' ',$mismatched['blockers']));
        $pdo->exec('DELETE FROM shop_product_event_links');
        $pdo->exec("INSERT INTO shop_product_event_links VALUES($productId,2)");
        self::assertTrue(\shopCatalogPublicationReadiness($pdo,$productId)['ready']);
        $result=\shopCatalogPublicationActivate($pdo,$productId,7,'Klubový závod','Jednorázová klubová akce.','Propojení a cena byly ověřeny.',true);
        self::assertTrue($result['changed']);
        self::assertSame('active',$pdo->query("SELECT catalog_status FROM shop_products WHERE id=$productId")->fetchColumn());
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

    public function testRemoteImageBlocksPublicationUntilItIsReplacedByLocalImage(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $productId = $this->readyGoodsId($pdo);
        $pdo->prepare('INSERT INTO shop_product_images(product_id,image_url,sort_order) VALUES(?,?,0)')
            ->execute([$productId, 'https://cdn.example.test/product.jpg']);

        $blocked = \shopCatalogPublicationReadiness($pdo, $productId);
        self::assertFalse($blocked['ready']);
        self::assertStringContainsString('lokálního úložiště KIS', implode(' ', $blocked['blockers']));

        $pdo->prepare('UPDATE shop_product_images SET image_url=? WHERE product_id=?')
            ->execute(['uploads/shop-products/' . str_repeat('a', 32) . '.jpg', $productId]);
        self::assertTrue(\shopCatalogPublicationReadiness($pdo, $productId)['ready']);
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

    public static function invalidAccounting(): iterable
    {
        yield 'unassigned fallback' => ['unassigned', 'Nezařazeno'];
        yield 'no fallback' => ['missing_mapping', 'Chybí výchozí'];
        yield 'multiple categories without default' => ['no_default', 'výchozí kategorii'];
        yield 'multiple defaults' => ['two_defaults', 'výchozí kategorii'];
        yield 'inactive target' => ['inactive', 'neaktivní'];
        yield 'mixed is order only' => ['mixed', 'Smíšená řada'];
        yield 'category ancestry cycle' => ['cycle', 'cyklus'];
        yield 'unmigrated schema' => ['missing_schema', 'migraci'];
        yield 'missing settings row' => ['missing_settings', 'Chybí nastavení'];
    }

    #[DataProvider('invalidAccounting')]
    public function testInvalidAccountingBlocksActivationWithoutChangingPaymentsOrCatalog(string $fault, string $message): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $id = $this->readyGoodsId($pdo);
        $pdo->exec("DELETE FROM shop_product_categories WHERE product_id=$id");
        if ($fault === 'unassigned') {
            $pdo->exec("UPDATE shop_accounting_mappings SET category_id=80 WHERE mapping_type='kind' AND mapping_key='goods'");
        } elseif ($fault === 'missing_mapping') {
            $pdo->exec("DELETE FROM shop_accounting_mappings WHERE mapping_type='kind' AND mapping_key='goods'");
        } elseif (in_array($fault, ['no_default', 'two_defaults'], true)) {
            $default = $fault === 'two_defaults' ? 1 : 0;
            $pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES($id,'Knihy',$default,0),($id,'Oblečení',$default,1)");
        } elseif (in_array($fault, ['inactive', 'mixed'], true)) {
            $target = $fault === 'mixed' ? 90 : 31;
            $pdo->exec("INSERT INTO shop_accounting_mappings VALUES('product','$id',$target)");
            if ($fault === 'inactive') $pdo->exec('UPDATE shop_accounting_categories SET active=0 WHERE id=31');
        } elseif ($fault === 'cycle') {
            $pdo->exec("CREATE TABLE shop_category_meta(category_path TEXT PRIMARY KEY,parent_path TEXT)");
            $pdo->exec("INSERT INTO shop_category_meta VALUES('A','B'),('B','A')");
            $pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES($id,'A',1,0)");
        } elseif ($fault === 'missing_schema') {
            $pdo->exec('DROP TABLE shop_accounting_mappings');
        } else {
            $pdo->exec('DELETE FROM shop_number_series_settings');
        }
        $this->seedHistoricalSymbols($pdo);
        $before = $this->publicationState($pdo);
        $ready = \shopCatalogPublicationReadiness($pdo, $id);
        self::assertFalse($ready['ready']);
        self::assertStringContainsString($message, implode(' ', $ready['blockers']));
        self::assertSame($before, $this->publicationState($pdo), 'Readiness must only read.');
        try {
            \shopCatalogPublicationActivate($pdo, $id, 7, 'Nová nabídka', 'Veřejný popis nabídky.', 'Kontrola účetní řady.', true);
            self::fail('Invalid accounting must block publication.');
        } catch (\ShopCatalogPublicationException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        self::assertFalse($pdo->inTransaction());
        self::assertSame($before, $this->publicationState($pdo), 'Failed publication must leave no partial changes or renumbering.');
    }

    public function testAccountingInheritanceAndExplicitOverrideMatchCheckoutClassification(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $id = $this->readyGoodsId($pdo);
        $pdo->exec("UPDATE shop_accounting_mappings SET category_id=80 WHERE mapping_type='kind' AND mapping_key='goods'");
        $pdo->exec("DELETE FROM shop_product_categories WHERE product_id=$id");
        $pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES($id,'Zboží > Knihy > Dětské',1,0)");
        $pdo->exec("INSERT INTO shop_accounting_mappings VALUES('catalog','Zboží',30),('catalog','Zboží > Knihy',31)");
        self::assertTrue(\shopCatalogPublicationReadiness($pdo, $id)['ready']);
        self::assertSame(31, (int)\shopNumberSeriesClassify($pdo, $id, 'goods')['id']);
        $pdo->exec("INSERT INTO shop_product_categories(product_id,category_path,is_default,sort_order) VALUES($id,'Kroužky',1,1)");
        self::assertFalse(\shopCatalogPublicationReadiness($pdo, $id)['ready']);
        $pdo->exec("INSERT INTO shop_accounting_mappings VALUES('product','$id',32)");
        self::assertTrue(\shopCatalogPublicationReadiness($pdo, $id)['ready']);
        self::assertSame(32, (int)\shopNumberSeriesClassify($pdo, $id, 'goods')['id']);
        $this->seedHistoricalSymbols($pdo);
        $symbols = $this->historicalSymbols($pdo);
        \shopCatalogPublicationActivate($pdo, $id, 7, 'Tričko', 'Ověřená produktová výjimka.', 'Schválené účetní přiřazení.', true);
        self::assertSame('active', $pdo->query("SELECT catalog_status FROM shop_products WHERE id=$id")->fetchColumn());
        self::assertSame($symbols, $this->historicalSymbols($pdo));
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_number_series_counters')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM shop_order_number_assignments')->fetchColumn());
    }

    public function testExistingDisputedProductsAreNotAutomaticallyChangedByReadiness(): void
    {
        $pdo = $this->databaseWithDraftCatalog();
        $pdo->exec("UPDATE shop_accounting_mappings SET category_id=80 WHERE mapping_type='kind' AND mapping_key='goods'");
        foreach ([274 => 'Magazin', 243 => 'Předpřípravka', 245 => 'TEST - Cyklistický kroužek'] as $id => $name) {
            $pdo->prepare("INSERT INTO shop_products(id,origin,external_product_key,name,offer_type,catalog_status) VALUES(?,'manual',?,?,'goods','active')")
                ->execute([$id, 'manual:disputed:' . $id, $name]);
            $pdo->prepare("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note,activated_by_trainer_id) VALUES(?,'active',?,'Původní nabídka.','Původní schválení.',7)")->execute([$id, $name]);
        }
        $this->seedHistoricalSymbols($pdo);
        $before = $this->publicationState($pdo);
        foreach ([274, 243, 245] as $id) self::assertFalse(\shopCatalogPublicationReadiness($pdo, $id)['ready']);
        self::assertSame($before, $this->publicationState($pdo));
        self::assertSame(3, (int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE id IN (274,243,245) AND catalog_status='active'")->fetchColumn());
    }

    private function seedHistoricalSymbols(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE payments(id INTEGER PRIMARY KEY,variable_symbol TEXT)");
        $pdo->exec("INSERT INTO payments VALUES(1,'0000000013')");
        $pdo->exec("CREATE TABLE member_fee_standing_orders(id INTEGER PRIMARY KEY,variable_symbol TEXT)");
        $pdo->exec("INSERT INTO member_fee_standing_orders VALUES(1,'1234567890')");
        $pdo->exec("CREATE TABLE member_payer_variable_symbols(id INTEGER PRIMARY KEY,variable_symbol TEXT)");
        $pdo->exec("INSERT INTO member_payer_variable_symbols VALUES(1,'9876543210')");
    }

    private function historicalSymbols(PDO $pdo): array
    {
        return array_map(static fn(string $table): array => $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll(),
            ['payments', 'member_fee_standing_orders', 'member_payer_variable_symbols']);
    }

    private function publicationState(PDO $pdo): array
    {
        $state = $this->historicalSymbols($pdo);
        foreach (['shop_products', 'shop_variants', 'shop_product_publications', 'shop_product_publication_events',
            'shop_number_series_counters', 'shop_order_number_assignments', 'shop_order_accounting_items', 'shop_number_series_events'] as $table) {
            $state[$table] = $pdo->query("SELECT * FROM $table")->fetchAll();
        }
        return $state;
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
        // Základní publikační testy pracují s produktem bez obrázku; samostatný
        // test výše ověřuje blokaci vzdálených a přijetí lokálních obrázků.
        $pdo->exec('DELETE FROM shop_product_images');
        $pdo->exec('CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER,product_id INTEGER,variant_id INTEGER,status TEXT,ends_on TEXT,sales_open_at TEXT NULL,sales_close_at TEXT NULL,capacity INTEGER NULL)');
        $pdo->exec('CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY,offer_id INTEGER,status TEXT)');
        \shopAccountingPublicationFixture($pdo);
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
