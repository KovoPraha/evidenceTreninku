<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class PlannerReadFixture
{
    public static function seed(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE planovane_treninky(id INT PRIMARY KEY,datum DATE,stav VARCHAR(30),trener_id INT,skupina_id INT);
            CREATE TABLE sportovci(id INT PRIMARY KEY,jmeno VARCHAR(100),prijmeni VARCHAR(100));
            CREATE TABLE training_roster_links(id INT PRIMARY KEY,plan_id INT,team_id INT,team_name_snapshot VARCHAR(100));
            CREATE TABLE training_roster_expected(id INT PRIMARY KEY,link_id INT,sportovec_id INT);
            CREATE TABLE club_roster_members(id INT PRIMARY KEY,team_id INT,sportovec_id INT,status VARCHAR(30),valid_from DATE,valid_to DATE);
            CREATE TABLE club_team_edges(parent_team_id INT,child_team_id INT,valid_from DATE,valid_to DATE);
            CREATE TABLE training_rsvps(plan_id INT,sportovec_id INT,response VARCHAR(30),member_note VARCHAR(100),responded_at DATETIME,
                UNIQUE(plan_id,sportovec_id));');
        $pdo->exec("INSERT INTO planovane_treninky VALUES
            (101,'2026-01-01','planovany',7,1),(102,'2026-02-01','planovany',8,1),
            (103,'2026-03-01','evidovany',7,2),(104,'2026-04-01','planovany',7,1),
            (105,'2026-05-01','zruseny',8,2),(106,'2026-03-31','planovany',8,2),
            (107,NULL,'planovany',7,1);
            INSERT INTO sportovci VALUES(1,'LOCALHOST','One'),(2,'LOCALHOST','Two'),(3,'LOCALHOST','Three'),
                (4,'LOCALHOST','Four'),(5,'LOCALHOST','Five'),(6,'LOCALHOST','Six'),(7,'LOCALHOST','Seven');
            INSERT INTO training_roster_links VALUES(1,101,1,'Group'),(2,102,1,'Group'),(3,103,1,'Group'),
                (4,103,3,'Roster'),(5,105,99,'Missing roster'),(6,106,2,'Roster'),(7,107,1,'Null date');
            INSERT INTO training_roster_expected VALUES(1,1,4);
            INSERT INTO club_team_edges VALUES(1,2,'2020-01-01','2026-01-31'),(1,3,'2026-02-01',NULL),
                (2,4,'2020-01-01',NULL),(3,4,'2020-01-01',NULL),(4,1,'2026-03-01','2026-03-31');
            INSERT INTO club_roster_members VALUES(1,2,1,'active','2020-01-01',NULL),(2,3,2,'active','2020-01-01',NULL),
                (3,4,3,'removed','2020-01-01','2026-02-01'),(4,4,4,'cancelled','2020-01-01',NULL),
                (5,4,5,'removed','2020-01-01',NULL),(6,4,6,'active','2026-03-01',NULL),
                (7,2,7,'active','2020-01-01',NULL),(8,3,7,'active','2020-01-01',NULL),
                (9,3,2,'active','2025-01-01',NULL),(10,4,999,'active','2020-01-01',NULL);
            INSERT INTO training_rsvps VALUES(101,1,'going','PRIVATE SENTINEL',NULL),(101,7,'not_going','PRIVATE SENTINEL',NULL),
                (101,2,'going','PRIVATE SENTINEL',NULL),(102,2,'going','PRIVATE SENTINEL',NULL),
                (102,3,'not_going','PRIVATE SENTINEL',NULL),(103,6,'going','PRIVATE SENTINEL',NULL);");
    }

    public static function summaryInputs(): array
    {
        return [[], [0, -1], [999], [101], [102], [103], [104], [105], [106], [107],
            [999, 103, 101, 101, 102, 0, -1, 106, 104, 105, 107]];
    }
}
