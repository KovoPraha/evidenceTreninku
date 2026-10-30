<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\QueryProfilePdo;

require_once dirname(__DIR__) . '/Support/QueryProfilePdo.php';
require_once dirname(__DIR__) . '/Support/RosterReadDatabase.php';
require_once dirname(__DIR__) . '/Support/TrainingRosterFilterFixture.php';
require_once dirname(__DIR__) . '/Support/PlannerCopyCountBaseline.php';
require_once dirname(__DIR__, 2) . '/includes/training_planner_read.php';

final class PlannerCopyCountTest extends TestCase
{
    use \Tests\Support\RosterReadDatabase;

    public function testCopyCountPreservesAllFiltersWithOneBaseRead(): void
    {
        $pdo = $this->database();
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateFunction('FOUND_ROWS', static fn (): int => 0);
        }
        $rows = \w82FilterFixture($pdo, 52);
        $pdo->exec('CREATE TABLE planovane_treninky(id INT,datum DATE,stav VARCHAR(30),trener_id INT,skupina_id INT)');
        $insert = $pdo->prepare('INSERT INTO planovane_treninky VALUES(?,?,?,?,?)');
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $insert->execute([$id, $row['datum'], ['planovany', 'evidovany', 'zruseny', 'PLANOVANY'][$id % 4], $id % 3 + 7, $id % 2 + 1]);
        }
        foreach ([false, true] as $mine) {
            foreach ([0, 8] as $trainer) {
                foreach ([0, 1, 999] as $group) {
                    foreach ([0, 1, 3] as $team) {
                        $arguments = [$pdo, '2026-01-01', '2026-12-31', $mine, 7, $trainer, $group, $team];
                        $expected = \w82CopyCountBaseline(...$arguments);
                        $pdo->profile = [];
                        $pdo->profiling = true;
                        $actual = \trainingPlannerCopyCount(...$arguments);
                        $pdo->profiling = false;
                        self::assertSame($expected, $actual);
                        $base = array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FROM planovane_treninky'));
                        self::assertCount(1, $base);
                        self::assertFalse((bool) array_filter($pdo->profile, static fn (array $q): bool => str_contains($q['sql'], 'FOUND_ROWS')));
                    }
                }
            }
        }
    }
}
