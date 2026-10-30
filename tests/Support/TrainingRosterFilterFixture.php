<?php

declare(strict_types=1);

/** @return list<array<string,mixed>> */
function w82FilterFixture(PDO $pdo, int $weeks = 52): array
{
    $pdo->exec('CREATE TABLE training_roster_links(plan_id INT,trenink_id INT,team_id INT)');
    $pdo->exec('CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE)');
    $pdo->exec("INSERT INTO club_team_edges VALUES(1,2,'2026-01-01','2026-06-30'),(1,3,'2026-04-01',NULL),"
        . "(2,3,'2026-02-01','2026-03-31'),(3,1,'2026-02-01','2026-03-31')");
    $insert = $pdo->prepare('INSERT INTO training_roster_links VALUES(?,?,?)');
    $rows = [];
    for ($week = 0; $week < $weeks; $week++) {
        $date = (new DateTimeImmutable('2026-01-01'))->modify('+' . ($week * 7) . ' days')->format('Y-m-d');
        for ($team = 1; $team <= 20; $team++) {
            $id = $week * 20 + $team;
            $rows[] = ['id' => $id, 'datum' => $date, 'private_note' => 'Synthetic preserved payload'];
            if ($team % 7 !== 0) {
                $insert->execute([$id, null, $team]);
                $insert->execute([null, $id, $team]);
            }
        }
    }
    return $rows;
}
