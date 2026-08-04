<?php
declare(strict_types=1);

namespace Tests\Integration;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public_training_schedule.php';

final class PublicTrainingScheduleTest extends TestCase
{
    public function testOnlyExplicitlyPublicPlannedTrainingIsReturnedWithoutPrivateFields(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,kategorie TEXT,sportoviste_id INTEGER,skupina_id INTEGER,stav TEXT,je_verejny INTEGER,popis TEXT)');
        $pdo->exec("INSERT INTO sportovist VALUES(1,'Velodrom'); INSERT INTO skupiny VALUES(1,'U15')");
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'2026-10-01','16:00','17:30','Veřejný','draha',1,1,'planovany',1,'tajná poznámka'),(2,'2026-10-02','16:00','17:30','Soukromý','draha',1,1,'planovany',0,'tajná poznámka'),(3,'2026-10-03','16:00','17:30','Zrušený','draha',1,1,'zruseny',1,'tajná poznámka')");
        $rows = \publicTrainingSchedule($pdo, '2026-10-01', '2026-10-31');
        self::assertCount(1, $rows);
        self::assertSame('Veřejný', $rows[0]['nazev']);
        self::assertArrayNotHasKey('popis', $rows[0]);
    }

    public function testInvalidRangeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        \publicTrainingSchedule(new PDO('sqlite::memory:'), '2026-11-01', '2026-10-01');
    }
}
