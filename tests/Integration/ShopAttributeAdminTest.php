<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/shop_attribute_admin.php';

final class ShopAttributeAdminTest extends TestCase
{
    public function testDefinedAttributesAreFormattedAndUnknownImportKeysRemainVisible():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO shop_attribute_definitions(id,attribute_key,display_name,value_type,unit,sort_order,show_in_listing,show_in_detail,active) VALUES(1,'size','Velikost','choice',NULL,10,1,1,1),(2,'weight','Hmotnost','number','kg',20,0,1,1),(3,'secret','Interní','text',NULL,0,1,1,0)");
        $definitions=\shopAttributeDefinitions($pdo,true);
        $attributes=['mystery'=>'zůstává','weight'=>2,'size'=>'M','secret'=>'skrýt'];
        $listing=\shopAttributePresentation($pdo,$attributes,'listing',$definitions);
        self::assertSame(['Velikost','mystery'],array_column($listing,'display_name'));
        self::assertSame(['M','zůstává'],array_column($listing,'formatted_value'));
        $detail=\shopAttributePresentation($pdo,$attributes,'detail',$definitions);
        self::assertSame(['Velikost','Hmotnost','mystery'],array_column($detail,'display_name'));
        self::assertSame('2 kg',$detail[1]['formatted_value']);
    }

    public function testUnknownAndMalformedImportedJsonNeverBreakDiscovery():void
    {
        $pdo=$this->database();
        $pdo->exec("INSERT INTO shop_variants(attributes_json) VALUES('{\"Nový importovaný klíč\":\"hodnota\"}'),('{neplatný json')");
        self::assertSame(['Nový importovaný klíč'],\shopAttributeDiscoveredKeys($pdo));
        self::assertSame('hodnota',\shopAttributePresentation($pdo,['Nový importovaný klíč'=>'hodnota'])[0]['formatted_value']);
    }

    public function testAdminSavePersistsChoiceValuesAndAuditWithoutDeletingOldData():void
    {
        $pdo=$this->database();
        $result=\shopAttributeAdminSave($pdo,7,['attribute_key'=>'size','display_name'=>'Velikost','value_type'=>'choice','unit'=>'','sort_order'=>'5','show_in_listing'=>'1','show_in_detail'=>'1','active'=>'1','choice_values'=>"S\nM\nL"],'Založení číselníku',true);
        self::assertGreaterThan(0,$result['id']);self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM shop_attribute_choices WHERE active=1')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM shop_attribute_definition_events')->fetchColumn());
        \shopAttributeAdminSave($pdo,7,['definition_id'=>$result['id'],'attribute_key'=>'size','display_name'=>'Konfekční velikost','value_type'=>'choice','unit'=>'','sort_order'=>'5','show_in_listing'=>'1','show_in_detail'=>'1','active'=>'1','choice_values'=>"M\nL"],'Vyřazení staré volby',true);
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM shop_attribute_choices')->fetchColumn());self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_attribute_choices WHERE active=1')->fetchColumn());self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM shop_attribute_definition_events')->fetchColumn());
    }

    private function database():PDO
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE shop_variants(id INTEGER PRIMARY KEY AUTOINCREMENT,attributes_json TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE shop_attribute_definitions(id INTEGER PRIMARY KEY AUTOINCREMENT,attribute_key TEXT NOT NULL UNIQUE,display_name TEXT NOT NULL,value_type TEXT NOT NULL,unit TEXT NULL,sort_order INTEGER NOT NULL DEFAULT 0,show_in_listing INTEGER NOT NULL DEFAULT 0,show_in_detail INTEGER NOT NULL DEFAULT 1,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE shop_attribute_choices(id INTEGER PRIMARY KEY AUTOINCREMENT,attribute_id INTEGER NOT NULL,value TEXT NOT NULL,sort_order INTEGER NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE(attribute_id,value),FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT)');
        $pdo->exec('CREATE TABLE shop_attribute_definition_events(id INTEGER PRIMARY KEY AUTOINCREMENT,attribute_id INTEGER NOT NULL,attribute_key TEXT NOT NULL,actor_type TEXT NOT NULL,actor_id INTEGER NOT NULL,action TEXT NOT NULL,before_json TEXT NULL,after_json TEXT NOT NULL,reason TEXT NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(attribute_id) REFERENCES shop_attribute_definitions(id) ON DELETE RESTRICT)');return$pdo;
    }
}
