<?php
declare(strict_types=1);
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/club_youngest_prepare.php';

final class ClubYoungestPrepareTest extends TestCase
{
    public function testPreparesThreePrivateProgramRostersWithoutAnySaleableOffer(): void
    {
        $pdo=$this->database();
        $preview=\clubYoungestPrepare($pdo,7,false);
        self::assertSame(0,$preview['created']);
        self::assertCount(3,$preview['groups']);
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_programs')->fetchColumn());

        $first=\clubYoungestPrepare($pdo,7,true);
        $second=\clubYoungestPrepare($pdo,7,true);
        self::assertSame(3,$first['created']);
        self::assertSame(0,$second['created']);
        self::assertSame($first['groups'],$second['groups']);
        self::assertCount(3,array_unique(array_column($first['groups'],'team_id')));
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM club_programs')->fetchColumn());
        self::assertSame(3,(int)$pdo->query("SELECT COUNT(*) FROM club_program_presentations WHERE listing_status='draft' AND interest_enabled=0")->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_program_offers')->fetchColumn());
        self::assertSame(3,(int)$pdo->query("SELECT COUNT(*) FROM shop_products WHERE offer_type='goods' AND catalog_status='active'")->fetchColumn());
        self::assertSame([25000,25000,25000],array_map('intval',$pdo->query('SELECT amount_minor FROM shop_variants ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testChangedImportedCourseFailsClosedBeforeAnyWrite(): void
    {
        $pdo=$this->database();
        $pdo->exec('UPDATE shop_variants SET amount_minor=30000 WHERE id=687');
        $this->expectException(\ClubYoungestPrepareException::class);
        try{\clubYoungestPrepare($pdo,7,true);}
        finally{
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
            self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM club_programs')->fetchColumn());
        }
    }

    private function database(): PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        foreach([
            'CREATE TABLE treneri(id INTEGER PRIMARY KEY)',
            'INSERT INTO treneri(id) VALUES(7)',
            'CREATE TABLE shop_products(id INTEGER PRIMARY KEY,name TEXT,origin TEXT,external_product_key TEXT,offer_type TEXT,catalog_status TEXT)',
            'CREATE TABLE shop_variants(id INTEGER PRIMARY KEY,product_id INTEGER,sku TEXT,amount_minor INTEGER,currency TEXT,catalog_status TEXT)',
            'CREATE TABLE club_seasons(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,season_type TEXT,starts_on TEXT,ends_on TEXT,status TEXT,created_by_trainer_id INTEGER)',
            'CREATE TABLE club_teams(id INTEGER PRIMARY KEY AUTOINCREMENT,season_id INTEGER,series_id INTEGER,code TEXT,name TEXT,discipline TEXT,age_label TEXT,status TEXT,created_by_trainer_id INTEGER,UNIQUE(season_id,code))',
            'CREATE TABLE club_roster_events(id INTEGER PRIMARY KEY AUTOINCREMENT,team_id INTEGER,roster_member_id INTEGER,actor_trainer_id INTEGER,action TEXT,before_json TEXT,after_json TEXT,note TEXT)',
            'CREATE TABLE club_programs(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE,name TEXT,description TEXT,status TEXT,created_by_trainer_id INTEGER)',
            'CREATE TABLE club_program_events(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER,offer_id INTEGER,actor_type TEXT,actor_id INTEGER,action TEXT,before_json TEXT,after_json TEXT)',
            'CREATE TABLE club_program_presentations(program_id INTEGER PRIMARY KEY,public_name TEXT,public_summary TEXT,age_label TEXT,listing_status TEXT,sort_order INTEGER,interest_enabled INTEGER)',
            'CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY AUTOINCREMENT,program_id INTEGER)',
        ] as $sql)$pdo->exec($sql);
        foreach(\clubYoungestGroups() as $group){
            $pdo->prepare("INSERT INTO shop_products(id,name,origin,external_product_key,offer_type,catalog_status) VALUES(?,?,'import',?,'goods','active')")
                ->execute([$group['product_id'],$group['name'],'shoptet:pair:'.$group['product_id']]);
            $pdo->prepare("INSERT INTO shop_variants(id,product_id,sku,amount_minor,currency,catalog_status) VALUES(?,?,?,25000,'CZK','active')")
                ->execute([$group['variant_id'],$group['product_id'],$group['sku']]);
        }
        return $pdo;
    }
}
