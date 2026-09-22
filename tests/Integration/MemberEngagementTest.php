<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/member_engagement.php';

final class MemberEngagementTest extends TestCase
{
    public function testCompetitiveRosterWinsOverProgramAndPublicIsFallback(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE club_seasons(id INTEGER PRIMARY KEY,season_type TEXT,starts_on TEXT,ends_on TEXT,status TEXT);CREATE TABLE club_teams(id INTEGER PRIMARY KEY,season_id INTEGER,name TEXT,status TEXT);CREATE TABLE club_roster_members(sportovec_id INTEGER,team_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);CREATE TABLE club_programs(id INTEGER PRIMARY KEY,name TEXT);CREATE TABLE club_program_offers(id INTEGER PRIMARY KEY,program_id INTEGER);CREATE TABLE club_program_enrollments(sportovec_id INTEGER,offer_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)");
        $pdo->exec("INSERT INTO club_seasons VALUES(1,'school_year','2026-09-01','2027-06-30','active'),(2,'calendar_year','2026-01-01','2026-12-31','active');INSERT INTO club_teams VALUES(10,1,'Kroužek','active'),(20,2,'Závodní U17','active');INSERT INTO club_roster_members VALUES(1,10,'active','2026-09-01',NULL),(2,20,'active','2026-01-01',NULL);INSERT INTO club_programs VALUES(5,'Kurz techniky');INSERT INTO club_program_offers VALUES(6,5);INSERT INTO club_program_enrollments VALUES(2,6,'active','2026-09-01','2026-12-31')");

        $levels = memberEngagementMap($pdo, [1, 2, 3], '2026-09-22');
        self::assertSame('circle', $levels[1]['key']);
        self::assertSame('competitive', $levels[2]['key']);
        self::assertSame('public', $levels[3]['key']);
        self::assertStringContainsString('Závodní U17', implode(' ', $levels[2]['reasons']));
    }
}
