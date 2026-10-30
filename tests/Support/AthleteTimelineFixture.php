<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Reduced legacy schema deliberately permits cycles, duplicate paths and NULL/status edge cases. */
function athleteTimelineFixture(PDO $pdo, int $weeks = 52, int $teams = 10): void
{
    $pdo->exec('CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE)');
    $pdo->exec('CREATE TABLE club_roster_members(team_id INT,sportovec_id INT,status VARCHAR(24),valid_from DATE,valid_to DATE)');
    $pdo->exec('CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE,cas_od VARCHAR(10),cas_do VARCHAR(10),'
        . 'nazev VARCHAR(100),popis TEXT,stav VARCHAR(30),trenink_id INT)');
    $pdo->exec('CREATE TABLE training_roster_links(plan_id INT,trenink_id INT,team_id INT,team_name_snapshot VARCHAR(100))');
    $pdo->exec('CREATE TABLE training_rsvps(plan_id INT,sportovec_id INT,response VARCHAR(20))');
    $pdo->exec('CREATE TABLE treninky(id INT PRIMARY KEY,datum DATE,napln VARCHAR(100))');
    $pdo->exec('CREATE TABLE trenink_sportovec(trenink_id INT,sportovec_id INT)');
    $pdo->exec("INSERT INTO club_roster_members VALUES(2,8,'removed','2026-01-01','2026-06-30'),"
        . "(3,8,'active','2026-01-01',NULL),(4,8,'cancelled','2026-01-01',NULL),(5,8,'removed','2026-01-01',NULL)");
    $pdo->exec("INSERT INTO club_team_edges VALUES(1,2,'2026-01-01',NULL),(1,3,'2026-01-01','2026-09-30'),"
        . "(2,3,'2026-02-01','2026-03-31'),(3,1,'2026-02-01','2026-03-31')");
    $plan = $pdo->prepare('INSERT INTO planovane_treninky VALUES(?,?,?,?,?,?,?,?)');
    $link = $pdo->prepare('INSERT INTO training_roster_links VALUES(?,?,?,?)');
    $actual = $pdo->prepare('INSERT INTO treninky VALUES(?,?,?)');
    for ($week = 0; $week < $weeks; $week++) {
        $date = (new \DateTimeImmutable('2026-01-01'))->modify('+' . ($week * 7) . ' days')->format('Y-m-d');
        for ($team = 1; $team <= $teams; $team++) {
            $id = $week * $teams + $team;
            $trainingId = 10000 + $id;
            $plan->execute([$id, $date, '16:00', '17:00', 'Synthetic ' . $id, 'LOCALHOST',
                $week % 11 === 0 ? 'zruseny' : 'planovany', $trainingId]);
            $actual->execute([$trainingId, $date, 'Actual ' . $id]);
            $link->execute([$id, $trainingId, $team, 'Team ' . $team]);
        }
    }
    $pdo->exec("INSERT INTO treninky VALUES(99999,'2026-12-31','Direct historical attendance')");
    $pdo->exec('INSERT INTO trenink_sportovec VALUES(99999,8)');
    $pdo->exec("INSERT INTO training_rsvps VALUES(11,8,'going'),(12,999,'FOREIGN_SENTINEL')");
}
