<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public_calendar_feed.php';

final class PublicCalendarFeedTest extends TestCase
{
    public function testFeedCombinesOnlyAlreadyPublicSourcesWithoutPrivateDescriptions(): void
    {
        $items = \publicCalendarItems($this->database(), '2026-10-01', '2026-10-31');

        self::assertSame(
            ['training-1', 'club-event-session-10', 'velodrome-slot-20'],
            array_column($items, 'uid')
        );
        $serialized = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('soukromá poznámka', $serialized);
        self::assertStringNotContainsString('Soukromý trénink', $serialized);
        self::assertStringNotContainsString('Interní porada', $serialized);
        self::assertStringNotContainsString('Posilovna', $serialized);
    }

    public function testIcsUsesUtcEscapingStableUidsAndCrLf(): void
    {
        $calendar = \publicCalendarRender(
            \publicCalendarItems($this->database(), '2026-10-01', '2026-10-31'),
            new DateTimeImmutable('2026-08-04 12:00:00', new DateTimeZone('UTC'))
        );

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $calendar);
        self::assertStringContainsString("UID:training-1@data.kovopraha.cz\r\n", $calendar);
        self::assertStringContainsString("DTSTART:20261001T140000Z\r\n", $calendar);
        self::assertStringContainsString('SUMMARY:Veřejný trénink\, dráha', $calendar);
        self::assertStringContainsString("END:VCALENDAR\r\n", $calendar);
        self::assertStringNotContainsString("\nBEGIN:VEVENT\n", $calendar);
        foreach (explode("\r\n", trim($calendar)) as $line) {
            self::assertLessThanOrEqual(75, strlen($line), 'ICS řádek překročil 75 oktetů.');
        }
    }

    public function testAllDayTrainingAndLongUtf8LineAreStandardsCompatible(): void
    {
        $pdo = $this->database();
        $pdo->exec("INSERT INTO planovane_treninky VALUES(4,'2026-10-05',NULL,NULL,'Celodenní soustředění s velmi dlouhým českým názvem pro ověření skládání řádků','draha',1,1,'planovany',1,'soukromá poznámka')");
        $calendar = \publicCalendarRender(\publicCalendarItems($pdo, '2026-10-01', '2026-10-31'));

        self::assertStringContainsString("DTSTART;VALUE=DATE:20261005\r\n", $calendar);
        self::assertStringContainsString("DTEND;VALUE=DATE:20261006\r\n", $calendar);
        self::assertMatchesRegularExpression('/\r\n [^\r\n]+\r\n/', $calendar);
        self::assertSame(1, preg_match('//u', $calendar));
    }

    public function testInvalidOrExcessiveRangeIsRejectedAndMissingTablesAreSafe(): void
    {
        self::assertSame([], \publicCalendarItems(new PDO('sqlite::memory:'), '2026-10-01', '2026-10-02'));
        foreach ([['2026-11-01', '2026-10-01'], ['2026-01-01', '2027-02-01'], ['x', '2026-10-01']] as [$from, $to]) {
            try {
                \publicCalendarItems($this->database(), $from, $to);
                self::fail('Neplatné období nebylo odmítnuto.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT,nazev TEXT,je_verejne INTEGER,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,kategorie TEXT,sportoviste_id INTEGER,skupina_id INTEGER,stav TEXT,je_verejny INTEGER,popis TEXT)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT,audience_label TEXT,event_type TEXT,status TEXT,description_plain TEXT)');
        $pdo->exec('CREATE TABLE club_event_sessions(id INTEGER PRIMARY KEY,event_id INTEGER,starts_at TEXT,ends_at TEXT,location TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY,sportoviste_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,stav TEXT,popis TEXT)');
        $pdo->exec("INSERT INTO sportovist VALUES(1,'velodrom','Velodrom',1,1),(2,'gym','Posilovna',1,1),(3,'private','Soukromé',0,1)");
        $pdo->exec("INSERT INTO skupiny VALUES(1,'U15')");
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'2026-10-01','16:00','17:30','Veřejný trénink, dráha','draha',1,1,'planovany',1,'soukromá poznámka'),(2,'2026-10-02','16:00','17:30','Soukromý trénink','draha',1,1,'planovany',0,'soukromá poznámka'),(3,'2026-10-03','16:00','17:30','Zrušený trénink','draha',1,1,'zruseny',1,'soukromá poznámka')");
        $pdo->exec("INSERT INTO club_events VALUES(5,'Výjezd U15','U15','club_event','open','veřejný popis'),(6,'Interní porada','Trenéři','club_event','draft','neveřejný popis')");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(10,5,'2026-10-02 09:00:00','2026-10-02 12:00:00','Praha','scheduled'),(11,6,'2026-10-02 13:00:00','2026-10-02 14:00:00','Kancelář','scheduled'),(12,5,'2026-10-03 09:00:00','2026-10-03 12:00:00','Praha','cancelled')");
        $pdo->exec("INSERT INTO individualni_lekce VALUES(20,1,'2026-10-04','10:00','11:00','Veřejná hodina','aktivni','veřejný popis'),(21,2,'2026-10-04','11:00','12:00','Posilovna','aktivni','veřejný popis'),(22,3,'2026-10-04','12:00','13:00','Soukromý slot','aktivni','neveřejný popis'),(23,1,'2026-10-04','13:00','14:00','Zrušený slot','zrusena','veřejný popis')");
        return $pdo;
    }
}
