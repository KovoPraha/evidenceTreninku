<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/athlete_training_timeline.php';
require_once dirname(__DIR__, 2) . '/includes/public_training_schedule.php';

final class AthleteRosterCalendarSeparationTest extends TestCase
{
    public function testPersonalRosterAndPublicPublicationHaveSeparateRules(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec(<<<'SQL'
CREATE TABLE club_roster_members(team_id INTEGER, sportovec_id INTEGER, status TEXT, valid_from TEXT, valid_to TEXT);
CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY, datum TEXT, cas_od TEXT, cas_do TEXT,
    nazev TEXT, popis TEXT, stav TEXT, trenink_id INTEGER, kategorie TEXT,
    sportoviste_id INTEGER, skupina_id INTEGER, je_verejny INTEGER);
CREATE TABLE training_roster_links(plan_id INTEGER, trenink_id INTEGER, team_id INTEGER, team_name_snapshot TEXT);
CREATE TABLE sportovist(id INTEGER PRIMARY KEY, nazev TEXT);
CREATE TABLE skupiny(id INTEGER PRIMARY KEY, nazev TEXT);
INSERT INTO club_roster_members VALUES
    (1, 8, 'active', '2026-01-01', NULL),
    (2, 8, 'removed', '2026-01-01', '2026-11-15'),
    (3, 9, 'active', '2026-01-01', NULL);
INSERT INTO planovane_treninky VALUES
    (1, '2026-11-14', '16:00', '17:00', 'Aligátoři', '', 'planovany', NULL, 'mtb', NULL, NULL, 1),
    (2, '2026-11-15', '17:00', '18:00', 'Akademie poslední den', '', 'planovany', NULL, 'mtb', NULL, NULL, 0),
    (3, '2026-11-16', '17:00', '18:00', 'Akademie po odebrání', '', 'planovany', NULL, 'mtb', NULL, NULL, 0),
    (4, '2026-11-14', '18:00', '19:00', 'Technika MTB', '', 'planovany', NULL, 'mtb', NULL, NULL, 1),
    (5, '2026-11-14', '19:00', '20:00', 'Společný', '', 'planovany', NULL, 'mtb', NULL, NULL, 1);
INSERT INTO training_roster_links VALUES
    (1, NULL, 1, 'Aligátoři'), (2, NULL, 2, 'Závodní akademie'),
    (3, NULL, 2, 'Závodní akademie'), (4, NULL, 3, 'Technika MTB'),
    (5, NULL, 1, 'Aligátoři'), (5, NULL, 2, 'Závodní akademie');
SQL);

        $personal = \athleteTrainingTimeline($pdo, 8, 2026);
        self::assertSame([2, 5, 1], array_column($personal, 'id'));
        self::assertSame(['Aligátoři', 'Závodní akademie'], $personal[1]['targets']);
        self::assertSame([1, 4, 5], array_column(\publicTrainingSchedule(
            $pdo,
            '2026-11-01',
            '2026-11-30'
        ), 'id'));

        $pdo->exec("UPDATE club_roster_members SET status='cancelled' WHERE team_id=2 AND sportovec_id=8");
        self::assertSame([5, 1], array_column(\athleteTrainingTimeline($pdo, 8, 2026), 'id'));
    }
}
