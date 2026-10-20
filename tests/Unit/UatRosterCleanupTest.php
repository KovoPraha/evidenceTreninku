<?php
declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/uat_roster_cleanup.php';

final class UatRosterCleanupTest extends TestCase
{
    public function testOnlyDedicatedActiveUatMembershipsAreEndedWithoutOpeningFutureDay(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE club_teams(id INTEGER PRIMARY KEY,code TEXT)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,source TEXT,valid_from TEXT,valid_to TEXT,updated_at TEXT)');
        $pdo->exec("INSERT INTO club_teams VALUES(1,'TEST-UAT-DETI'),(2,'REAL-TEAM')");
        $pdo->exec("INSERT INTO sportovci VALUES(1,'Ema','Tester'),(2,'Adam','Tester'),(3,'Josef','Tester'),(4,'Ema','Jiná')");
        $pdo->exec("INSERT INTO club_roster_members(id,team_id,sportovec_id,status,source,valid_from,valid_to) VALUES
            (1,1,1,'active','admin','2026-11-01',NULL),
            (2,1,2,'active','admin','2026-09-01',NULL),
            (3,1,3,'active','admin','2026-09-01',NULL),
            (4,2,1,'active','admin','2026-09-01',NULL),
            (5,1,1,'active','shop','2026-09-01',NULL),
            (6,1,4,'active','admin','2026-09-01',NULL),
            (7,1,2,'removed','admin','2026-01-01','2026-03-01')");
        self::assertSame(2,\kisUatCleanupRosterMemberships($pdo,'2026-10-20'));
        self::assertSame([
            [1,'cancelled','2026-11-01'],[2,'removed','2026-10-20'],
            [3,'active',null],[4,'active',null],[5,'active',null],[6,'active',null],[7,'removed','2026-03-01'],
        ],$pdo->query('SELECT id,status,valid_to FROM club_roster_members ORDER BY id')->fetchAll(PDO::FETCH_NUM));
        self::assertSame(0,\kisUatCleanupRosterMemberships($pdo,'2026-10-20'));
    }
}
