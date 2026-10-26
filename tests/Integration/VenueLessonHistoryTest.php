<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/venue_operations.php';
final class VenueLessonHistoryTest extends TestCase
{
    public function testHistoryIsBoundedScopedAndDoesNotExposePayloadOrWrite(): void
    {
        $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT)');
        $pdo->exec('CREATE TABLE venue_operation_events(id INTEGER PRIMARY KEY,target_type TEXT,target_id INTEGER,actor_trainer_id INTEGER,action TEXT,reason TEXT,payload_json TEXT,created_at TEXT)');
        $pdo->exec("INSERT INTO treneri VALUES(7,'Testovací trenér')");
        $insert=$pdo->prepare('INSERT INTO venue_operation_events VALUES(?,?,?,?,?,?,?,?)');
        for($id=1;$id<=102;$id++)$insert->execute([$id,'lesson',5,7,'reservation_confirmed','Důvod <script>','{"private":"never render"}','2026-10-25 20:00:00']);
        $insert->execute([103,'lesson',6,7,'cancel','Cizí lekce','{}','2026-10-25']);
        $insert->execute([104,'venue_reservation',5,7,'cancel','Jiný typ','{}','2026-10-25']);
        $before=$pdo->query('SELECT * FROM venue_operation_events')->fetchAll(PDO::FETCH_ASSOC);
        $rows=\venueLessonHistory($pdo,5);
        self::assertCount(100,$rows);
        self::assertSame(102,(int)$rows[0]['id']);self::assertSame(3,(int)$rows[99]['id']);
        self::assertSame('Testovací trenér',$rows[0]['actor_name']);
        self::assertArrayNotHasKey('payload_json',$rows[0]);
        self::assertSame($before,$pdo->query('SELECT * FROM venue_operation_events')->fetchAll(PDO::FETCH_ASSOC));
    }
}
