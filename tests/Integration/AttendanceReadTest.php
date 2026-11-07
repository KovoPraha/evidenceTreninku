<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Support\AttendanceReadFixture;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__, 2) . '/includes/coach_training_access.php';

final class AttendanceReadTest extends TestCase
{
    private function database(): QueryProfilePdo
    {
        $pdo = new QueryProfilePdo('sqlite::memory:');
        AttendanceReadFixture::seed($pdo);
        return $pdo;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testBatchedExistenceChecksKeepCanonicalResultsAcrossRolesAndInvalidInputs(): void
    {
        $pdo = $this->database();
        foreach ([[7, 'coach'], [7, 'sports_lead'], [9, 'coach'], [8, 'coach'], [7, 'registrar'], [0, 'coach']] as [$actor, $position]) {
            AttendanceReadFixture::session($actor, $position);
            foreach (AttendanceReadFixture::inputs() as $ids) {
                self::assertSame(
                    AttendanceReadFixture::outcome(fn () => AttendanceReadFixture::original($pdo, $ids)),
                    AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, $ids))
                );
            }
        }
    }

    public function testDataAndRoleChangesAreFreshAndAuthorizationPrecedesPersonReads(): void
    {
        $pdo = $this->database();
        coachTrainingAssertPeople($pdo, [1]);
        $pdo->exec('DELETE FROM sportovci WHERE id=1');
        self::assertSame([RuntimeException::class, 'Vybraný sportovec neexistuje.'], AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, [1])));
        $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=7;DELETE FROM staff_superadmins");
        $pdo->profile = [];
        $pdo->profiling = true;
        self::assertSame([RuntimeException::class, 'Pracovní oprávnění trenéra již neplatí.'], AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, [1])));
        foreach ($pdo->profile as $q) {
            self::assertStringNotContainsString('FROM sportovci', $q['sql']);
        }
    }

    public function testTransactionsKeepExactOriginalSqlOrderIncludingFailuresAndEmptyInputs(): void
    {
        $pdo = $this->database();
        $pdo->beginTransaction();
        $pdo->profiling = true;
        foreach ([[], [2, 1, 2], [1, 1201, 2], [0, 1]] as $ids) {
            $pdo->profile = [];
            $before = AttendanceReadFixture::outcome(fn () => AttendanceReadFixture::original($pdo, $ids));
            $sql = array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile);
            $pdo->profile = [];
            self::assertSame($before, AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, $ids)));
            self::assertSame($sql, array_map(static fn (array $q): array => [$q['sql'], $q['parameters']], $pdo->profile));
        }
        $pdo->rollBack();
    }

    public function testLargeInputIsChunkedAndDoesNotLoadPrivateFields(): void
    {
        $pdo = $this->database();
        $pdo->profiling = true;
        coachTrainingAssertPeople($pdo, range(1, 1200));
        self::assertCount(5, $pdo->profile); // Two fresh role checks and three participant batches.
        foreach (array_slice($pdo->profile, 2) as $q) {
            self::assertLessThanOrEqual(500, count($q['parameters']));
            self::assertStringStartsWith('SELECT id FROM sportovci WHERE id IN (', $q['sql']);
        }
    }

    public function testEmptyInputStillChecksActorAndPreservesMissingTableBehavior(): void
    {
        $pdo = $this->database();
        $pdo->exec('DROP TABLE sportovci');
        self::assertSame(
            AttendanceReadFixture::outcome(fn () => AttendanceReadFixture::original($pdo, [])),
            AttendanceReadFixture::outcome(fn () => coachTrainingAssertPeople($pdo, []))
        );
    }
}
