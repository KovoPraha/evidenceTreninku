<?php

declare(strict_types=1);

namespace Tests\Integration;

use Closure;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/TrainingDayChoicesFixture.php';

final class LessonChoiceMutationPdo extends PDO
{
    public ?Closure $afterCandidates = null;
    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LessonChoiceMutationStatement::class, [$this]]);
    }
}
final class LessonChoiceMutationStatement extends PDOStatement
{
    protected function __construct(private LessonChoiceMutationPdo $connection)
    {
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = parent::fetchAll($mode, ...$args);
        if (str_starts_with($this->queryString, 'SELECT p.* FROM planovane_treninky p WHERE ') && $this->connection->afterCandidates !== null) {
            $callback = $this->connection->afterCandidates;
            $this->connection->afterCandidates = null;
            $callback($this->connection);
        }
        return $rows;
    }
}

final class TrainingDayChoicesReadTest extends TestCase
{
    private function outcome(PDO $pdo, string $call, int $actor, string $search, string $date): array
    {
        try {
            return ['rows' => $call($pdo, $actor, $search, $date)];
        } catch (\Throwable $error) {
            return ['exception' => $error::class, 'message' => $error->getMessage()];
        }
    }
    private function parity(PDO $pdo, int $actor, string $search = '', string $date = ''): array
    {
        $before = $this->outcome($pdo, 'beforeTrainingDayChoices', $actor, $search, $date);
        $after = $this->outcome($pdo, 'trainingDayChoices', $actor, $search, $date);
        self::assertSame($before, $after);
        return $after;
    }
    public function testExactRowsOrderFiltersAndLimitsForEveryWorkingIdentity(): void
    {
        foreach ([0, 1, 49, 50, 51, 1000] as $count) {
            $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            \trainingDayChoicesSeed($pdo, $count);
            foreach ([[1, 'coach'], [2, 'sports_lead'], [3, 'coach'], [4, 'coach'], [4, 'sports_lead']] as [$actor, $position]) {
                \trainingDayChoicesSession($actor, $position);
                foreach ([['', ''], ['', '2026-11-07'], ['Synthetic', ''], ['žlutý', ''], ['kurz_%', ''],
                    ['Synthetic 1', ''], ['NOTFOUND', ''], ['  lesson  ', '2026-11-06'], ['', 'invalid'], ['', '2026-02-30']] as [$search, $date]) {
                    $result = $this->parity($pdo, $actor, $search, $date);
                    self::assertLessThanOrEqual(50, count($result['rows']));
                }
            }
        }
    }
    public function testSubstitutionPreservesNullInactiveMissingAndLegacyPlanOwners(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        \trainingDayChoicesSeed($pdo, 10);
        $pdo->exec('UPDATE planovane_treninky SET trener_id=NULL WHERE id=1');
        $pdo->exec('UPDATE planovane_treninky SET trener_id=5 WHERE id=2');
        $pdo->exec('UPDATE planovane_treninky SET trener_id=999 WHERE id=3');
        $pdo->exec("INSERT INTO planovane_treninky VALUES(0,NULL,'2026-11-07','14:00','15:00','Legacy zero','LOCALHOST',NULL)");
        foreach ([[1, 'coach'], [2, 'sports_lead'], [4, 'coach']] as [$actor, $position]) {
            \trainingDayChoicesSession($actor, $position);
            $result = $this->parity($pdo, $actor);
            self::assertCount(11, $result['rows']);
        }
    }

    public function testRevocationDeactivationAndDifferentSessionsAreFresh(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        \trainingDayChoicesSeed($pdo, 100);
        \trainingDayChoicesSession(1, 'coach');
        self::assertCount(50, $this->parity($pdo, 1)['rows']);
        $pdo->exec("DELETE FROM staff_user_positions WHERE trainer_id=1");
        self::assertArrayHasKey('exception', $this->parity($pdo, 1));
        $pdo->exec("INSERT INTO staff_user_positions VALUES(1,'coach')");
        $pdo->exec('UPDATE treneri SET aktivni=0 WHERE id=1');
        self::assertArrayHasKey('exception', $this->parity($pdo, 1));
        \trainingDayChoicesSession(4, 'coach');
        self::assertCount(50, $this->parity($pdo, 4)['rows']);
        $pdo->exec('DELETE FROM staff_superadmins');
        self::assertArrayHasKey('exception', $this->parity($pdo, 4));
        foreach ([[0, 'coach', 0], [5, 'coach', 5], [3, 'registrar', 3], [3, 'coach', 1]] as [$sessionActor, $position, $actor]) {
            \trainingDayChoicesSession($sessionActor, $position);
            self::assertArrayHasKey('exception', $this->parity($pdo, $actor));
        }
    }
    public function testDeletionAndRevocationBetweenCandidateReadAndCurrentCheckFailClosed(): void
    {
        foreach (["DELETE FROM planovane_treninky WHERE id IN (1,2,3,4)",
            "DELETE FROM staff_user_positions WHERE trainer_id=1", 'UPDATE treneri SET aktivni=0 WHERE id=1',
            'DROP TABLE planovane_treninky'] as $mutation) {
            $outcomes = [];
            foreach (['beforeTrainingDayChoices', 'trainingDayChoices'] as $call) {
                $pdo = new LessonChoiceMutationPdo();
                \trainingDayChoicesSeed($pdo, 12);
                \trainingDayChoicesSession(1, 'coach');
                $pdo->afterCandidates = static fn (PDO $connection) => $connection->exec($mutation);
                $outcomes[] = $call($pdo, 1, '', '');
            }
            self::assertSame($outcomes[0], $outcomes[1]);
        }
    }
    public function testQueryBudgetAndUnchangedTransactionalReadOrder(): void
    {
        $pdo = new \Tests\Support\QueryProfilePdo('sqlite::memory:');
        \trainingDayChoicesSeed($pdo, 100);
        \trainingDayChoicesSession(1, 'coach');
        $pdo->profiling = true;
        $this->parity($pdo, 1);
        $pdo->profile = [];
        \trainingDayChoices($pdo, 1, '', '');
        self::assertCount(6, $pdo->profile);
        $pdo->profile = [];
        \trainingDayChoices($pdo, 1, 'NOTFOUND', '');
        self::assertCount(3, $pdo->profile);
        $pdo->beginTransaction();
        $pdo->profile = [];
        $before = \beforeTrainingDayChoices($pdo, 1, '', '');
        $queries = $pdo->profile;
        $pdo->profile = [];
        self::assertSame($before, \trainingDayChoices($pdo, 1, '', ''));
        $shape = static fn (array $q): array => [$q['sql'], $q['parameters'], $q['fetched_rows']];
        self::assertSame(array_map($shape, $queries), array_map($shape, $pdo->profile));
        $pdo->rollBack();
    }
}
