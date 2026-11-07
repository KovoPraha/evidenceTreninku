<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\AttendanceReadFixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/training_closure_people.php';
require_once dirname(__DIR__) . '/Support/AttendancePersonPickerFixture.php';

final class AttendancePersonPickerTest extends TestCase
{
    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        \Tests\Support\AttendancePersonPickerFixture::seed($pdo);
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testBrowsingKeepsEveryOriginalPersonAndPreferenceWithBoundedProjection(): void
    {
        $pdo = $this->database();
        $original = $pdo->query('SELECT s.id,s.jmeno,s.prijmeni FROM sportovci s WHERE ' . coachTrainingPersonPredicate($pdo)
            . ' ORDER BY CASE WHEN (' . coachTrainingPreferredPersonPredicate($pdo) . ') THEN 0 ELSE 1 END,s.prijmeni,s.jmeno,s.id')->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        $pdo->profile = [];
        $pdo->profiling = true;
        for ($page = 1; $page <= 25; $page++) {
            $result = trainingClosurePersonPage($pdo, ['page' => (string)$page]);
            self::assertLessThanOrEqual(50, count($result['rows']));
            array_push($rows, ...$result['rows']);
            if (!$result['has_more']) {
                break;
            }
        }
        self::assertSame($original, $rows);
        self::assertSame(1150, (int)$rows[0]['id']);
        foreach ($pdo->profile as $query) {
            if (str_contains($query['sql'], 'FROM sportovci s')) {
                self::assertLessThanOrEqual(51, $query['fetched_rows']);
                self::assertStringStartsWith('SELECT s.id,s.jmeno,s.prijmeni', $query['sql']);
            }
        }
    }

    public function testSearchSupportsNamesIdsAndLiteralWildcardsWithoutExcludingGuests(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE sportovci SET jmeno='Jiří',prijmeni='Novák 100%_!' WHERE id=3");
        foreach (['Jiří Novák', 'Novák Jiří', '100%_!'] as $query) {
            $page = trainingClosurePersonPage($pdo, ['q' => $query]);
            self::assertSame([3], array_map('intval', array_column($page['rows'], 'id')));
        }
        self::assertSame([1200], array_map('intval', array_column(trainingClosurePersonPage($pdo, ['q' => '1200'])['rows'], 'id')));
        self::assertSame([], trainingClosurePersonPage($pdo, ['q' => "' OR 1=1 --"])['rows']);
        self::assertSame([], trainingClosurePersonPage($pdo, ['q' => 'not-found'])['rows']);
        self::assertSame([], trainingClosurePersonPage($pdo, ['page' => '999999'])['rows']);
    }

    public function testPinnedSelectionsSurviveOtherPagesAndAreNotInventedForMissingIds(): void
    {
        $pdo = $this->database();
        $ids = [...range(1, 1200), 1, 0, -1, 999999];
        $pdo->profile = [];
        $pdo->profiling = true;
        $selected = trainingClosureSelectedPeople($pdo, $ids);
        self::assertCount(1200, $selected);
        self::assertArrayNotHasKey(999999, $selected);
        self::assertSame(['id', 'jmeno', 'prijmeni'], array_keys($selected[1]));
        foreach ($pdo->profile as $query) {
            if (str_contains($query['sql'], 'FROM sportovci s')) {
                self::assertLessThanOrEqual(500, $query['fetched_rows']);
            }
        }
        $page = trainingClosurePersonPage($pdo, ['q' => 'NOT FOUND']);
        self::assertSame($selected, trainingClosurePersonOptions($page, $selected));
        self::assertSame([], trainingClosurePersonOptions($page, trainingClosureSelectedPeople($pdo, [])));
    }

    public function testFreshRoleRevocationPrecedesAnyNameRead(): void
    {
        $pdo = $this->database();
        self::assertNotEmpty(trainingClosurePersonPage($pdo, [])['rows']);
        $pdo->exec('DELETE FROM staff_user_positions WHERE trainer_id=7');
        $pdo->profile = [];
        $pdo->profiling = true;
        foreach ([fn () => trainingClosurePersonPage($pdo, []), fn () => trainingClosureSelectedPeople($pdo, [1])] as $operation) {
            self::assertSame([RuntimeException::class, 'Pracovní oprávnění trenéra již neplatí.'], AttendanceReadFixture::outcome($operation));
        }
        foreach ($pdo->profile as $query) {
            self::assertStringNotContainsString('FROM sportovci', $query['sql']);
        }
    }

    public function testBothAllowedRolesSeeGuestsAndInactiveOrOtherPositionsAreDenied(): void
    {
        $pdo = $this->database();
        foreach (['coach', 'sports_lead'] as $role) {
            AttendanceReadFixture::session(7, $role);
            self::assertSame([1200], array_map('intval', array_column(trainingClosurePersonPage($pdo, ['q' => '1200'])['rows'], 'id')));
        }
        foreach ([[8, 'coach'], [7, 'registrar'], [0, 'coach']] as [$actor, $role]) {
            AttendanceReadFixture::session($actor, $role);
            self::assertNotNull(AttendanceReadFixture::outcome(fn () => trainingClosurePersonPage($pdo, [])));
        }
    }

    public function testSearchActionAndPagingCannotTurnOtherWritesIntoReadOnlySubmits(): void
    {
        foreach (['find_participants', 'find_participants_2', 'find_excuse_person', 'find_excuse_person_999999'] as $action) {
            self::assertTrue(trainingClosureSearchAction(['action' => $action]));
        }
        foreach (['close', 'void', 'personal_excuse', 'find_participants_0', 'find_participants_bad', ['find_participants']] as $action) {
            self::assertFalse(trainingClosureSearchAction(['action' => $action]));
        }
        self::assertSame(['q' => 'Anna', 'page' => '2'], trainingClosurePickerInput(
            ['action' => 'find_participants_2', 'participant_q' => 'Anna', 'participant_last_q' => 'Anna'],
            'participant'
        ));
        self::assertSame(['q' => 'New', 'page' => '1'], trainingClosurePickerInput(
            ['action' => 'find_participants_2', 'participant_q' => 'New', 'participant_last_q' => 'Anna'],
            'participant'
        ));
        self::assertSame(['q' => 'Anna', 'page' => '3'], trainingClosurePickerInput(
            ['action' => 'find_excuse_person', 'participant_q' => 'Anna', 'participant_last_q' => 'Anna', 'participant_current_page' => '3'],
            'participant'
        ));
    }

    public function testExcuseDraftKeepsItsNamespaceAndLegacyPayloadWithoutBorrowingAttendanceReason(): void
    {
        self::assertSame([], trainingClosureExcuseDraft([]));
        self::assertSame(['received_at' => '', 'source' => '', 'reason' => ''], trainingClosureExcuseDraft(['action' => 'find_participants', 'reason' => 'Attendance only']));
        self::assertSame(['received_at' => '2026-11-07T06:00', 'source' => 'paper', 'reason' => '  Excuse draft  '], trainingClosureExcuseDraft([
            'action' => 'find_participants', 'reason' => 'Attendance draft', 'excuse_received_at' => '2026-11-07T06:00',
            'excuse_source' => 'paper', 'excuse_reason' => '  Excuse draft  ',
        ]));
        self::assertSame(['received_at' => '2026-11-07T06:00', 'source' => 'phone', 'reason' => 'Legacy API'], trainingClosureExcuseDraft([
            'action' => 'personal_excuse', 'received_at' => '2026-11-07T06:00', 'source' => 'phone', 'reason' => 'Legacy API',
        ]));
        self::assertSame(['received_at' => '', 'source' => '', 'reason' => ''], trainingClosureExcuseDraft([
            'action' => 'personal_excuse', 'excuse_received_at' => [], 'excuse_source' => [], 'excuse_reason' => [],
        ]));
    }
}
