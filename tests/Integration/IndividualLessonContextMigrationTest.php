<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/individual_lesson_context.php';

final class IndividualLessonContextMigrationTest extends TestCase
{
    public function testMigrationSeparatesPublicVelodromeFromIndividualLessonsAndIsIdempotent():void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT)');
        $pdo->exec("INSERT INTO sportovist VALUES(1,'velodrom'),(2,'hala')");
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY,sportoviste_id INTEGER,nazev TEXT,public_exclusive_booking INTEGER,datum TEXT,stav TEXT)');
        $pdo->exec("INSERT INTO individualni_lekce VALUES(10,1,'Veřejná hodina velodromu',0,'2030-01-01','aktivni'),(11,1,'Individuální dráha',0,'2030-01-02','aktivni'),(12,2,'Osobní trénink',0,'2030-01-03','aktivni'),(13,1,'Přejmenovaný veřejný slot',1,'2030-01-04','aktivni')");
        $pdo->exec('CREATE TABLE public_velodrome_cart_items(id INTEGER PRIMARY KEY,lesson_id INTEGER)');
        $pdo->exec('CREATE TABLE public_velodrome_order_items(id INTEGER PRIMARY KEY,lesson_id INTEGER)');
        $migration=require dirname(__DIR__,2).'/migrations/20260921120000_individual_lesson_context.php';
        $migration['up']($pdo);$migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        self::assertSame(['public_velodrome','individual_lesson','individual_lesson','public_velodrome'],$pdo->query('SELECT booking_context FROM individualni_lekce ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame("il.booking_context='individual_lesson'",\individualLessonContextCondition($pdo,'il',\INDIVIDUAL_LESSON_CONTEXT_LESSON));
    }
}
