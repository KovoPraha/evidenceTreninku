<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/training_planner_view.php';

final class TrainerBacklogTest extends TestCase
{
    public function testOlderUnrecordedTrainingDoesNotDisappearAndForeignPlansStayExcluded(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE planovane_treninky (id INTEGER PRIMARY KEY, trener_id INTEGER, datum TEXT, stav TEXT)');
        $insert = $pdo->prepare('INSERT INTO planovane_treninky VALUES (?,?,?,?)');
        foreach ([
            [1, 7, '2026-05-30', 'planovany'],
            [2, 7, '2026-10-24', 'planovany'],
            [3, 7, '2026-10-25', 'planovany'],
            [4, 7, '2026-10-26', 'planovany'],
            [5, 7, '2026-01-01', 'zruseny'],
            [6, 7, '2026-01-02', 'evidovano'],
            [7, 8, '2025-01-01', 'planovany'],
        ] as $row) $insert->execute($row);

        self::assertSame(['count' => 2, 'oldest_date' => '2026-05-30'], trainingPlannerTrainerBacklog($pdo, 7, '2026-10-25'));
        self::assertSame(['count' => 0, 'oldest_date' => null], trainingPlannerTrainerBacklog($pdo, 9, '2026-10-25'));
        $pdo->exec("UPDATE planovane_treninky SET stav='evidovano' WHERE id=1");
        self::assertSame(['count' => 1, 'oldest_date' => '2026-10-24'], trainingPlannerTrainerBacklog($pdo, 7, '2026-10-25'));
        self::assertSame(7, (int)$pdo->query('SELECT COUNT(*) FROM planovane_treninky')->fetchColumn());
    }
}
