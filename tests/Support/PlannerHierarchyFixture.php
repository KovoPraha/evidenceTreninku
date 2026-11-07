<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Synthetic plans only; no configuration or live connection is loaded. */
final class PlannerHierarchyFixture
{
    public static function seed(PDO $pdo, int $plans = 720): void
    {
        $pdo->exec('CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE,stav VARCHAR(30),trener_id INT,skupina_id INT);
            CREATE TABLE training_roster_links(id INT PRIMARY KEY,plan_id INT,team_id INT);
            CREATE INDEX planner_links_owner ON training_roster_links(plan_id,team_id);
            CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE)');
        $pdo->exec("INSERT INTO club_team_edges VALUES
            (1,2,'2020-01-01','2026-01-31'),(1,3,'2026-02-01',NULL),
            (2,4,'2020-01-01',NULL),(3,4,'2020-01-01',NULL),
            (4,1,'2026-03-01','2026-03-31'),(9,1,'2026-01-15','2026-02-15'),
            (9,10,'2020-01-01',NULL),(1,0,'2026-02-01',NULL),
            (1,-2,'2026-02-01',NULL),(-2,11,'2026-02-01',NULL)");
        $plan = $pdo->prepare('INSERT INTO planovane_treninky VALUES(?,?,?,?,?)');
        $link = $pdo->prepare('INSERT INTO training_roster_links VALUES(?,?,?)');
        $start = new \DateTimeImmutable('2026-01-01');
        $pdo->beginTransaction();
        for ($i = 1; $i <= $plans; $i++) {
            $date = $start->modify('+' . (($i - 1) % 90) . ' days')->format('Y-m-d');
            $plan->execute([$i, $date, $i % 13 === 0 ? 'evidovany' : ($i % 17 === 0 ? 'PLANOVANY' : 'planovany'),
                7 + $i % 2, 1 + $i % 3]);
            if ($i % 11 !== 0) {
                $link->execute([$i * 3, $i, [1, 2, 3, 4, 9, 10, 11, 0, -2, null][$i % 10]]);
            }
            if ($i % 7 === 0) {
                $link->execute([$i * 3 + 1, $i, 1]);
                $link->execute([$i * 3 + 2, $i, 1]);
            }
        }
        $pdo->commit();
    }
}
