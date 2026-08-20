<?php
declare(strict_types=1);
namespace Tests\Integration;

use FilesystemIterator;
use PDO;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

require_once dirname(__DIR__,2).'/includes/club_program_wizard.php';

final class ClubProgramWizardTest extends TestCase
{
    private string $root;

    protected function setUp():void{$this->root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'club-program-wizard-'.bin2hex(random_bytes(6));mkdir($this->root,0750,true);}
    protected function tearDown():void
    {
        if(!is_dir($this->root))return;$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as$item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());rmdir($this->root);
    }

    public function testWizardCreatesCompletePublishedProgramInOneTransaction():void
    {
        $pdo=$this->database();$result=\clubProgramWizardCreate($pdo,7,$this->input(),null,false);
        self::assertSame('active',$pdo->query('SELECT catalog_status FROM shop_products')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT catalog_status FROM shop_variants')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM shop_product_publications')->fetchColumn());
        self::assertSame('active',$pdo->query('SELECT status FROM club_program_offers')->fetchColumn());
        self::assertSame('Kroužky > Dětské',$pdo->query('SELECT category_path FROM shop_product_categories')->fetchColumn());
        self::assertSame(2,(int)$pdo->query("SELECT COUNT(*) FROM club_event_term_versions WHERE status='active'")->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_seasons')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
        self::assertSame((int)$result['team_id'],(int)$pdo->query('SELECT team_id FROM club_program_offers')->fetchColumn());
        self::assertSame(['create_product','assign_category','create_program_wizard'],$pdo->query("SELECT action FROM shop_catalog_admin_events ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
        self::assertFalse($pdo->inTransaction());
    }

    public function testLateFailureRollsBackEveryWizardRow():void
    {
        $pdo=$this->database();$input=$this->input();$input['team_mode']='existing';$input['team_id']=999;
        $source=dirname(__DIR__,2).'/icons/icon-192.png';
        try{\clubProgramWizardCreate($pdo,7,$input,$source,false,$this->root);self::fail('Missing target roster must fail.');}
        catch(\ClubProgramWizardException $exception){self::assertStringContainsString('soupiska',$exception->getMessage());}
        foreach(['shop_products','shop_variants','shop_product_categories','shop_catalog_admin_events','shop_product_publications','shop_product_publication_events','club_programs','club_program_offers','club_program_events','club_event_term_versions']as$table){
            self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(),$table.' must stay empty');
        }
        self::assertSame([],glob($this->root.'/uploads/shop-products/*.jpg')?:[]);
        self::assertCount(1,glob($this->root.'/var/_to_delete/shop-product-images/*.jpg')?:[]);
        self::assertFalse($pdo->inTransaction());
    }

    public function testExistingTermVersionsAreCopiedIntoNewProgramSnapshotSource():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO club_event_term_versions(scope_type,scope_key,consent_purpose,terms_version,consent_text_plain,actor_type,actor_id,status) VALUES('club_event','event:1','program_cancellation','v7','Existující storno vzor.','trainer',7,'active'),('club_event','event:1','program_consent','v4','Existující vzor souhlasu.','trainer',7,'active')");
        $input=$this->input();$input['program_cancellation_source']='existing';$input['program_cancellation_version_id']=1;$input['program_consent_source']='existing';$input['program_consent_version_id']=2;
        $result=\clubProgramWizardCreate($pdo,7,$input,null,false);
        $statement=$pdo->prepare("SELECT consent_purpose,consent_text_plain FROM club_event_term_versions WHERE scope_type='club_program' AND scope_key=? ORDER BY consent_purpose");$statement->execute(['program:'.$result['program_id']]);
        self::assertSame([
            ['consent_purpose'=>'program_cancellation','consent_text_plain'=>'Existující storno vzor.'],
            ['consent_purpose'=>'program_consent','consent_text_plain'=>'Existující vzor souhlasu.'],
        ],$statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testSkuSlugDoesNotBreakCzechWordInside():void
    {
        self::assertSame('RAJCATKA',\clubProgramWizardSlug('Rajčátka'));
        self::assertStringNotContainsString('-ATKA',\clubProgramWizardSlug('Rajčátka'));
    }

    /** @return array<string,mixed> */
    private function input():array{return[
        'request_key'=>'abcdef0123456789abcdef0123456789','name'=>'Rajčátka','description'=>'Cyklistický kroužek pro děti.',
        'currency'=>'CZK','amount_minor'=>150000,'includes_vat'=>1,'vat_rate_basis_points'=>2100,'category_path'=>'Kroužky > Dětské',
        'attributes_json'=>'{"úroveň":"začátečníci"}','starts_on'=>'2026-09-01','ends_on'=>'2027-06-30',
        'sales_open_at'=>'2026-08-01T00:00','sales_close_at'=>'2026-09-30T23:59','capacity'=>12,
        'birth_year_from'=>2018,'birth_year_to'=>2020,'program_cancellation_source'=>'new','program_cancellation_text'=>'Storno podmínky Rajčátek.',
        'program_consent_source'=>'new','program_consent_text'=>'Souhlasím s účastí dítěte.','team_mode'=>'new',
        'season_code'=>'SCHOOL-2026-27','season_name'=>'Školní rok 2026/27','season_type'=>'school_year','season_starts_on'=>'2026-09-01','season_ends_on'=>'2027-08-31',
        'team_code'=>'RAJCATA-2026','team_name'=>'Rajčátka 2026/27','team_discipline'=>'Cyklistika','team_age_label'=>'2018 až 2020',
        'reason'=>'Vypsání kroužku integračním testem.','confirmed'=>true,
    ];}

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $schema=[
            'CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT NOT NULL)',
            "INSERT INTO treneri VALUES(7,'Admin')",
            "CREATE TABLE shop_products(id INTEGER PRIMARY KEY AUTOINCREMENT,source_candidate_id INTEGER NULL,source_run_id INTEGER NULL,origin TEXT NOT NULL,created_by_trainer_id INTEGER NULL,external_product_key TEXT NOT NULL UNIQUE,name TEXT NOT NULL,short_description TEXT NULL,offer_type TEXT NOT NULL,visibility TEXT NULL,item_type TEXT NULL,catalog_status TEXT NOT NULL DEFAULT 'draft',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER NOT NULL,source_candidate_id INTEGER NULL,origin TEXT NOT NULL,created_by_trainer_id INTEGER NULL,sku TEXT NOT NULL UNIQUE,ean TEXT NULL,attributes_json TEXT NOT NULL,price_mode TEXT NOT NULL,amount_minor INTEGER NULL,compare_at_amount_minor INTEGER NULL,currency TEXT NULL,includes_vat INTEGER NULL,vat_rate_basis_points INTEGER NULL,stock_quantity_decimal TEXT NULL,unit_code TEXT NULL,visible INTEGER NULL,catalog_status TEXT NOT NULL DEFAULT 'draft',created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE shop_product_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,category_path TEXT,is_default INTEGER,sort_order INTEGER)',
            'CREATE TABLE shop_product_images(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,image_url TEXT,sort_order INTEGER)',
            'CREATE TABLE shop_catalog_admin_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,variant_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,reason TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE shop_product_publications(product_id INTEGER PRIMARY KEY,status TEXT,public_name TEXT,public_summary TEXT,decision_note TEXT,activated_by_trainer_id INTEGER,activated_at TEXT,deactivated_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE shop_product_publication_events(id INTEGER PRIMARY KEY AUTOINCREMENT,product_id INTEGER,actor_trainer_id INTEGER,action TEXT,from_status TEXT,to_status TEXT,public_name TEXT,public_summary TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,season_type TEXT,starts_on TEXT,ends_on TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER,series_id INTEGER NULL,code TEXT,name TEXT,discipline TEXT,age_label TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(season_id,code))",
            'CREATE TABLE club_team_series(id INTEGER PRIMARY KEY,season_type TEXT,status TEXT)',
            'CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,roster_member_id INTEGER NULL,actor_trainer_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,note TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_programs(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,description TEXT,status TEXT DEFAULT 'active',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            "CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,season_id INTEGER,team_id INTEGER,product_id INTEGER,variant_id INTEGER UNIQUE,code TEXT UNIQUE,name TEXT,starts_on TEXT,ends_on TEXT,sales_open_at TEXT NULL,sales_close_at TEXT NULL,capacity INTEGER NULL,birth_year_from INTEGER NULL,birth_year_to INTEGER NULL,status TEXT DEFAULT 'draft',created_by_trainer_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)",
            'CREATE TABLE club_program_enrollments(id INTEGER PRIMARY KEY AUTOINCREMENT,offer_id INTEGER,status TEXT)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,offer_id INTEGER NULL,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            "CREATE TABLE club_event_term_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,scope_type TEXT,scope_key TEXT,consent_purpose TEXT,event_id INTEGER NULL,terms_version TEXT,consent_text_plain TEXT,cancellation_policy_plain TEXT NULL,cancellation_deadline_at TEXT NULL,actor_trainer_id INTEGER NULL,actor_type TEXT,actor_id INTEGER NULL,status TEXT DEFAULT 'active',archived_at TEXT NULL,archived_by_trainer_id INTEGER NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(scope_type,scope_key,consent_purpose,terms_version))",
        ];foreach($schema as$sql)$pdo->exec($sql);return$pdo;
    }
}
