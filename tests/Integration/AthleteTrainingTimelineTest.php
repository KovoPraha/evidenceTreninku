<?php
declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__,2).'/includes/athlete_training_timeline.php';

final class AthleteTrainingTimelineTest extends TestCase
{
    public function testParentTargetAppearsOnceAndHistoricalMembershipIsDateSensitive():void
    {
        $pdo=new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
        $pdo->exec("CREATE TABLE club_team_edges(parent_team_id INTEGER,child_team_id INTEGER,valid_from TEXT,valid_to TEXT);
CREATE TABLE club_roster_members(team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT);
CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,popis TEXT,stav TEXT,trenink_id INTEGER);
CREATE TABLE training_roster_links(plan_id INTEGER,trenink_id INTEGER,team_id INTEGER,team_name_snapshot TEXT);
CREATE TABLE training_rsvps(plan_id INTEGER,sportovec_id INTEGER,response TEXT);
CREATE TABLE treninky(id INTEGER PRIMARY KEY,datum TEXT,napln TEXT);
CREATE TABLE trenink_sportovec(trenink_id INTEGER,sportovec_id INTEGER);
INSERT INTO club_team_edges VALUES(1,2,'2026-01-01',NULL),(1,3,'2026-01-01',NULL);
INSERT INTO club_roster_members VALUES(2,8,'removed','2026-01-01','2026-02-28'),(3,8,'active','2026-01-01','2026-04-30');
INSERT INTO planovane_treninky VALUES(10,'2026-02-10','17:00','18:00','Únorový trénink','','planovany',NULL),(11,'2026-03-10','17:00','18:00','Březnový trénink','','planovany',NULL),(12,'2026-05-10','17:00','18:00','Květnový trénink','','planovany',NULL);
INSERT INTO training_roster_links VALUES(10,NULL,1,'Závodní tým'),(10,NULL,3,'Kadeti'),(11,NULL,1,'Závodní tým'),(12,NULL,1,'Závodní tým');
INSERT INTO training_rsvps VALUES(10,8,'going');
INSERT INTO treninky VALUES(20,'2026-06-10','Historická účast');INSERT INTO trenink_sportovec VALUES(20,8)");
        $items=\athleteTrainingTimeline($pdo,8,2026);
        self::assertSame([20,11,10],array_column($items,'id'));
        self::assertSame(['Závodní tým','Kadeti'],$items[2]['targets']);
        self::assertSame('going',$items[2]['response']);
        self::assertTrue($items[0]['attended']);
    }
}
