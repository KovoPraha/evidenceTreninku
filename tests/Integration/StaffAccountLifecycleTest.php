<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/staff_account_lifecycle.php';
final class StaffAccountLifecycleTest extends TestCase
{
    public function testAccountCanBeDeactivatedAndReactivatedWithSessionInvalidationAndAudit():void
    {
        $pdo=$this->database();self::assertTrue(\staffAccountSetActive($pdo,2,1,false,'Pracovník ukončil spolupráci.',true)['changed']);self::assertSame(0,(int)$pdo->query('SELECT aktivni FROM treneri WHERE id=2')->fetchColumn());self::assertSame(2,(int)$pdo->query('SELECT session_version FROM treneri WHERE id=2')->fetchColumn());self::assertTrue(\staffAccountSetActive($pdo,2,1,true,'Pracovník se vrátil do klubu.',true)['changed']);self::assertSame(1,(int)$pdo->query('SELECT aktivni FROM treneri WHERE id=2')->fetchColumn());self::assertSame(3,(int)$pdo->query('SELECT session_version FROM treneri WHERE id=2')->fetchColumn());self::assertSame(['deactivate','activate'],$pdo->query('SELECT action FROM staff_account_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }
    public function testSelfDeactivationAndDuplicateEmailReactivationAreBlocked():void
    {
        $pdo=$this->database();try{\staffAccountSetActive($pdo,1,1,false,'Pokus o vlastní deaktivaci.',true);self::fail('Self deactivation must fail.');}catch(\StaffAccountLifecycleException){self::assertTrue(true);}\staffAccountSetActive($pdo,2,1,false,'Dočasné vyřazení účtu.',true);$pdo->exec("UPDATE treneri SET email='admin@test' WHERE id=2");$this->expectException(\StaffAccountLifecycleException::class);\staffAccountSetActive($pdo,2,1,true,'Pokus o kolizní aktivaci.',true);
    }
    private function database():PDO{$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,email TEXT,role TEXT,aktivni INTEGER,session_version INTEGER)');$pdo->exec("INSERT INTO treneri VALUES(1,'Admin','admin@test','admin',1,1),(2,'Účet','worker@test','trener',1,1)");$migration=require dirname(__DIR__,2).'/migrations/20260822180000_staff_account_events.php';$migration['up']($pdo);$migration['up']($pdo);self::assertTrue($migration['verify']($pdo));return$pdo;}
}
