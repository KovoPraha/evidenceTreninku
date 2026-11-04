<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_engagement.php';

final class MemberEngagementDirectoryTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,email TEXT,stav_clenstvi TEXT);
            CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,activity_kind TEXT);
            CREATE TABLE club_roster_members(sportovec_id INTEGER,team_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);
            CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT);
            CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER);
            CREATE TABLE club_program_enrollments(sportovec_id INTEGER,offer_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);');
        $q = $pdo->prepare("INSERT INTO sportovci VALUES(?,'LOCALHOST',?,'2015-01-02','','cekajici')");
        for ($id = 1; $id <= 1002; $id++) {
            $q->execute([$id, sprintf('LOCALHOST R7 %04d', $id)]);
        }
        $pdo->exec("INSERT INTO club_teams VALUES(1,'LOCALHOST kroužek','circle'),(2,'LOCALHOST závodní','competitive');
            INSERT INTO club_roster_members VALUES(1002,1,'active','2026-01-01',NULL);");
        return $pdo;
    }

    public function testLevelFilterFindsPersonBeyondFormerThousandRowLimit(): void
    {
        $result = \memberEngagementDirectory($this->database(), 'LOCALHOST R7', 'circle', 1, '2026-11-04');
        self::assertSame([1002], array_map('intval', array_column($result['rows'], 'id')));
        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['counts']['circle']);
    }

    public function testPaginationAndCountsCoverEveryMatchingPerson(): void
    {
        $pdo = $this->database();
        $first = \memberEngagementDirectory($pdo, 'LOCALHOST R7', '', 1, '2026-11-04');
        $last = \memberEngagementDirectory($pdo, 'LOCALHOST R7', '', 999, '2026-11-04');
        self::assertSame(1002, $first['total']);
        self::assertCount(25, $first['rows']);
        self::assertSame(41, $first['pages']);
        self::assertSame(41, $last['page']);
        self::assertSame([1001, 1002], array_map('intval', array_column($last['rows'], 'id')));
        self::assertSame(['public' => 1001, 'circle' => 1, 'competitive' => 0], $first['counts']);
        self::assertSame($first['counts'], $last['counts']);
    }

    public function testDateBoundariesAndCompetitivePriorityMatchExplanation(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_roster_members VALUES(1002,2,'removed','2026-11-04','2026-11-04'),(1001,2,'cancelled','2026-01-01',NULL);
            INSERT INTO club_programs VALUES(1,'LOCALHOST program'); INSERT INTO club_program_offers VALUES(1,1);
            INSERT INTO club_program_enrollments VALUES(1002,1,'active','2026-11-04','2026-11-04');");
        $today = \memberEngagementDirectory($pdo, '', 'competitive', 1, '2026-11-04');
        self::assertSame([1002], array_map('intval', array_column($today['rows'], 'id')));
        self::assertSame('competitive', $today['levels'][1002]['key']);
        self::assertCount(3, $today['levels'][1002]['reasons']);
        $tomorrow = \memberEngagementDirectory($pdo, '', 'circle', 1, '2026-11-05');
        self::assertSame([1002], array_map('intval', array_column($tomorrow['rows'], 'id')));
        self::assertSame(0, \memberEngagementDirectory($pdo, '', 'competitive', 1, '2026-11-05')['total']);
    }

    public function testArchivedPeopleDoNotAffectCountsOrPages(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE sportovci SET stav_clenstvi='archiv' WHERE id=1002");
        $result = \memberEngagementDirectory($pdo, 'LOCALHOST R7', '', 1, '2026-11-04');
        self::assertSame(1001, $result['total']);
        self::assertSame(0, $result['counts']['circle']);
    }

    public function testProgramOnlyClassificationUsesInclusiveValidity(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO club_programs VALUES(1,'LOCALHOST program'); INSERT INTO club_program_offers VALUES(1,1);
            INSERT INTO club_program_enrollments VALUES(1001,1,'active','2026-11-04','2026-11-04'),(1000,1,'active','2026-11-05','2026-12-01');");
        $today = \memberEngagementDirectory($pdo, '', 'circle', 1, '2026-11-04');
        self::assertSame([1001, 1002], array_map('intval', array_column($today['rows'], 'id')));
        self::assertSame('circle', $today['levels'][1001]['key']);
        $tomorrow = \memberEngagementDirectory($pdo, '', 'circle', 1, '2026-11-05');
        self::assertSame([1000, 1002], array_map('intval', array_column($tomorrow['rows'], 'id')));
    }
}
