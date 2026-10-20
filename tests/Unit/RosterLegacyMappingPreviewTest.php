<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/roster_legacy_mapping_preview.php';

final class RosterLegacyMappingPreviewTest extends TestCase
{
    public function testOnlyUniqueExactNamesAreSuggestedAndTrainingCountsAreReadOnly(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE podskupiny(id INTEGER PRIMARY KEY,nazev TEXT,skupina_id INTEGER)');
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,team_kind TEXT,status TEXT)');
        $pdo->exec("INSERT INTO skupiny VALUES(1,' Kroužek  Třebešín '),(2,'Závodní tým')");
        $pdo->exec("INSERT INTO podskupiny VALUES(5,'Aligátoři',1),(6,'Aligátoři',2),(7,'Kadeti',2),(8,'Bez soupisek',1)");
        $pdo->exec("INSERT INTO club_teams VALUES(11,'kroužek třebešín','group','active'),(12,'Závodní tým','group','active'),(13,'Aligátoři','roster','active'),(14,'Kadeti','roster','active'),(15,'Kadeti','roster','active')");
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,skupina_id INTEGER,podskupina_id INTEGER,datum TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE planovane_treninky_podskupiny(plan_id INTEGER,podskupina_id INTEGER)');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,1,5,'2027-03-01','planovany'),(2,1,NULL,'2027-03-02','planovany'),(3,2,7,'2026-03-01','evidovany')");
        $pdo->exec('INSERT INTO planovane_treninky_podskupiny VALUES(1,5)');
        $pdo->exec('CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT)');
        $pdo->exec('CREATE TABLE trenink_skupina(trenink_id INTEGER,skupina_id INTEGER)');
        $pdo->exec('CREATE TABLE trenink_podskupina(trenink_id INTEGER,podskupina_id INTEGER)');
        $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER)');
        $pdo->exec("INSERT INTO treninky VALUES(1,'2026-03-01')");
        $pdo->exec('INSERT INTO trenink_skupina VALUES(1,2)');
        $pdo->exec('INSERT INTO trenink_podskupina VALUES(1,7)');
        $pdo->exec('INSERT INTO trenink_sportovec VALUES(1,100),(1,101)');
        $preview = \kisRosterLegacyMappingPreview($pdo,'2026-10-20');
        self::assertSame(['exact'=>2,'ambiguous'=>3,'none'=>1],$preview['counts']);
        $rows = [];
        foreach ($preview['rows'] as $row) $rows[$row['kind'].':'.$row['id']] = $row;
        self::assertSame([['id'=>11,'name'=>'kroužek třebešín']],$rows['group:1']['candidates']);
        self::assertSame(2,$rows['group:1']['future_plan_count']);
        self::assertSame(1,$rows['roster:5']['future_plan_count']);
        self::assertSame('ambiguous',$rows['roster:5']['status']);
        self::assertSame('ambiguous',$rows['roster:7']['status']);
        self::assertSame(1,$rows['roster:7']['historical_plan_count']);
        self::assertSame(2,$rows['roster:7']['historical_attendance_count']);
        self::assertSame('none',$rows['roster:8']['status']);
        self::assertSame(5,(int)$pdo->query('SELECT COUNT(*) FROM club_teams')->fetchColumn());
    }
}
