<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/roster_target_selector.php';

final class CoachTrainingAccessTest extends TestCase
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY, aktivni INTEGER);
            INSERT INTO treneri VALUES(8,1);
            CREATE TABLE staff_user_positions(trainer_id INTEGER, position_code TEXT);
            INSERT INTO staff_user_positions VALUES(8,'coach');
            CREATE TABLE sportovci(id INTEGER PRIMARY KEY);
            INSERT INTO sportovci VALUES(1),(2);
            CREATE TABLE club_teams(id INTEGER PRIMARY KEY,name TEXT,status TEXT,team_kind TEXT);
            INSERT INTO club_teams VALUES(1,'A ostatní','active','roster'),(2,'Z vlastní','active','roster'),(3,'Archiv','closed','roster');
            CREATE TABLE club_team_edges(parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT);
            CREATE TABLE coach_scope_grants(trainer_id INTEGER,scope_kind TEXT,scope_id INTEGER,valid_from TEXT,valid_to TEXT,revoked_at TEXT);
            CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)");
        $_SESSION = ['trener_id' => 8, 'staff_positions' => ['coach'], 'staff_active_position' => 'coach'];
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCoachWithoutAnyGrantCanSelectAndValidateEveryActiveRosterAndParticipant(): void
    {
        $pdo = $this->database();
        $html = rosterTargetSelectorHtml($pdo, '2026-11-06', training: true);
        self::assertStringContainsString('A ostatní', $html);
        self::assertStringContainsString('Z vlastní', $html);
        self::assertStringNotContainsString('Archiv', $html);
        coachTrainingAssertTeams($pdo, [1,2], '2026-11-06');
        coachTrainingAssertPeople($pdo, [1,2]);
        self::assertFalse(coachScopeCanViewPerson($pdo, 1));
        self::assertStringContainsString('nejsou dostupné', rosterTargetSelectorHtml($pdo, '2026-11-06'));
    }

    public function testOwnRosterIsFirstAndOtherRostersStaySelectable(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO coach_scope_grants VALUES(8,'team',2,'2020-01-01',NULL,NULL)");
        $html = rosterTargetSelectorHtml($pdo, '2026-11-06', training: true);
        self::assertLessThan(strpos($html, 'A ostatní'), strpos($html, 'Z vlastní'));
        self::assertSame(1, substr_count($html, 'value="2"'));
        self::assertStringNotContainsString('disabled', $html);
        $pdo->exec('UPDATE coach_scope_grants SET revoked_at=CURRENT_TIMESTAMP');
        coachTrainingAssertTeams($pdo, [1,2], '2026-11-06');
        self::assertSame([], coachTrainingPreferredTeams($pdo, '2026-11-06'));
    }

    public function testClosedRosterAndMissingParticipantCannotBeSubmitted(): void
    {
        $pdo = $this->database();
        foreach ([fn () => coachTrainingAssertTeams($pdo, [3]), fn () => coachTrainingAssertPeople($pdo, [999])] as $operation) {
            try {
                $operation();
                self::fail('Invalid target was accepted.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function testRoleRevocationAndInactiveAccountBlockTrainingEvenWithCachedSession(): void
    {
        $pdo = $this->database();
        $pdo->exec('DELETE FROM staff_user_positions');
        self::assertFalse(coachTrainingActorAllowed($pdo));
        $pdo->exec("INSERT INTO staff_user_positions VALUES(8,'coach')");
        $pdo->exec('UPDATE treneri SET aktivni=0');
        self::assertFalse(coachTrainingActorAllowed($pdo));
        $this->expectException(RuntimeException::class);
        coachTrainingAssertPeople($pdo, [1]);
    }
}
