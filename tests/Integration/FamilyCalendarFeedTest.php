<?php
declare(strict_types=1);

namespace Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/family_calendar_feed.php';

final class FamilyCalendarFeedTest extends TestCase
{
    public function testTokenIsStoredOnlyAsHashAndCanBeRotatedAndRevoked(): void
    {
        $pdo = $this->database();
        $first = \familyCalendarFeedIssue($pdo, 1);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['token']);
        self::assertSame(1, \familyCalendarFeedResolveAccount($pdo, $first['token']));

        $stored = $pdo->query('SELECT token_hash,token_hint FROM family_calendar_feeds')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(hash('sha256', $first['token']), $stored['token_hash']);
        self::assertNotSame($first['token'], $stored['token_hash']);
        self::assertSame(substr($first['token'], -8), $stored['token_hint']);

        $second = \familyCalendarFeedIssue($pdo, 1);
        self::assertNull(\familyCalendarFeedResolveAccount($pdo, $first['token']));
        self::assertSame(1, \familyCalendarFeedResolveAccount($pdo, $second['token']));
        self::assertTrue(\familyCalendarFeedRevoke($pdo, 1)['changed']);
        self::assertNull(\familyCalendarFeedResolveAccount($pdo, $second['token']));
        self::assertFalse(\familyCalendarFeedRevoke($pdo, 1)['changed']);
        self::assertSame(['create', 'rotate', 'revoke'], $pdo->query('SELECT action FROM family_calendar_feed_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testFeedContainsOnlyCurrentlyAuthorizedFamilyData(): void
    {
        $pdo = $this->database();
        $items = \familyCalendarItems($pdo, 1, '2026-10-01', '2026-10-31');
        self::assertSame(
            ['family-training-1-person-10', 'family-event-1-session-1', 'family-reservation-1', 'family-charge-1'],
            array_column($items, 'uid')
        );
        $serialized = json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('Anna První', $serialized);
        self::assertStringNotContainsString('Cizí Osoba', $serialized);

        $calendar = \publicCalendarRender(
            $items,
            new DateTimeImmutable('2026-08-05 10:00:00', new DateTimeZone('UTC')),
            'Kovopraha – rodinný kalendář'
        );
        self::assertStringContainsString("X-WR-CALNAME:Kovopraha – rodinný kalendář\r\n", $calendar);
        self::assertStringContainsString("UID:family-training-1-person-10@data.kovopraha.cz\r\n", $calendar);
    }

    public function testRelationRevocationImmediatelyRemovesPersonWithoutRotatingToken(): void
    {
        $pdo = $this->database();
        $issued = \familyCalendarFeedIssue($pdo, 1);
        $pdo->exec("UPDATE account_person_roles SET status='revoked',valid_to='2026-08-05 00:00:00' WHERE account_id=1");

        self::assertSame(1, \familyCalendarFeedResolveAccount($pdo, $issued['token']));
        self::assertSame([], \familyCalendarItems($pdo, 1, '2026-10-01', '2026-10-31'));
    }

    public function testFamilyAgendaReusesAuthorizedCalendarItemsAndAddsCzechDisplayLabels(): void
    {
        $pdo = $this->database();
        $items = \familyCalendarAgenda($pdo, 1, '2026-10-01', 7);

        self::assertSame(
            ['family-training-1-person-10', 'family-event-1-session-1', 'family-reservation-1', 'family-charge-1'],
            array_column($items, 'uid')
        );
        self::assertSame('01. 10. 2026', $items[0]['date_label']);
        self::assertSame('16:00–17:30', $items[0]['time_label']);
        self::assertSame('celý den', $items[3]['time_label']);
        self::assertStringNotContainsString('Cizí Osoba', json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function testFamilyAgendaRejectsInvalidAccountDateAndRange(): void
    {
        $pdo = $this->database();
        foreach ([[0, '2026-10-01', 30], [1, 'bad-date', 30], [1, '2026-10-01', 0], [1, '2026-10-01', 91]] as [$accountId, $from, $days]) {
            try {
                \familyCalendarAgenda($pdo, $accountId, $from, $days);
                self::fail('Invalid agenda input must fail.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testUnverifiedAccountAndMalformedTokenCannotResolve(): void
    {
        $pdo = $this->database();
        $issued = \familyCalendarFeedIssue($pdo, 1);
        self::assertNull(\familyCalendarFeedResolveAccount($pdo, 'short'));
        $pdo->exec('UPDATE verejni_uzivatele SET email_overeno=0 WHERE id=1');
        self::assertNull(\familyCalendarFeedResolveAccount($pdo, $issued['token']));
    }

    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,aktivni INTEGER,email_overeno INTEGER)');
        $pdo->exec('CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,stav_clenstvi TEXT)');
        $pdo->exec('CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20260805010000_family_calendar_feeds.php';
        $migration['up']($pdo);
        self::assertTrue($migration['verify']($pdo));
        $migration['up']($pdo);

        $pdo->exec('CREATE TABLE sportovist(id INTEGER PRIMARY KEY,kod TEXT,nazev TEXT,je_verejne INTEGER,aktivni INTEGER)');
        $pdo->exec('CREATE TABLE skupiny(id INTEGER PRIMARY KEY,nazev TEXT)');
        $pdo->exec('CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,kategorie TEXT,sportoviste_id INTEGER,skupina_id INTEGER,stav TEXT)');
        $pdo->exec('CREATE TABLE training_roster_links(id INTEGER PRIMARY KEY,plan_id INTEGER,team_id INTEGER,team_name_snapshot TEXT)');
        $pdo->exec('CREATE TABLE club_roster_members(id INTEGER PRIMARY KEY,team_id INTEGER,sportovec_id INTEGER,status TEXT,valid_from TEXT,valid_to TEXT)');
        $pdo->exec('CREATE TABLE club_events(id INTEGER PRIMARY KEY,name TEXT)');
        $pdo->exec('CREATE TABLE club_event_sessions(id INTEGER PRIMARY KEY,event_id INTEGER,starts_at TEXT,ends_at TEXT,location TEXT,status TEXT)');
        $pdo->exec('CREATE TABLE club_event_registrations(id INTEGER PRIMARY KEY,event_id INTEGER,sportovec_id INTEGER,account_id INTEGER,status TEXT)');
        $pdo->exec('CREATE TABLE individualni_lekce(id INTEGER PRIMARY KEY,sportoviste_id INTEGER,datum TEXT,cas_od TEXT,cas_do TEXT,nazev TEXT,stav TEXT)');
        $pdo->exec('CREATE TABLE verejne_rezervace(id INTEGER PRIMARY KEY,lekce_id INTEGER,uzivatel_id INTEGER,sportovec_id INTEGER,stav TEXT)');
        $pdo->exec('CREATE TABLE club_member_charges(id INTEGER PRIMARY KEY,sportovec_id INTEGER,due_on TEXT,title_snapshot TEXT,amount_minor INTEGER,currency TEXT,status TEXT)');

        $pdo->exec('INSERT INTO verejni_uzivatele VALUES(1,1,1),(2,1,1)');
        $pdo->exec("INSERT INTO sportovci VALUES(10,'Anna','První','2012-01-01','aktivni'),(11,'Cizí','Osoba','2011-01-01','aktivni')");
        $pdo->exec("INSERT INTO account_person_roles VALUES(1,1,10,'guardian','approved','2020-01-01',NULL),(2,2,11,'guardian','approved','2020-01-01',NULL)");
        $pdo->exec("INSERT INTO sportovist VALUES(1,'velodrom','Velodrom',1,1)");
        $pdo->exec("INSERT INTO skupiny VALUES(1,'U15')");
        $pdo->exec("INSERT INTO planovane_treninky VALUES(1,'2026-10-01','16:00','17:30','Dráhový trénink','dráha',1,1,'planovany'),(2,'2026-10-01','18:00','19:00','Cizí trénink','dráha',1,1,'planovany')");
        $pdo->exec("INSERT INTO training_roster_links VALUES(1,1,100,'U15'),(2,2,200,'U17')");
        $pdo->exec("INSERT INTO club_roster_members VALUES(1,100,10,'active','2026-01-01',NULL),(2,200,11,'active','2026-01-01',NULL)");
        $pdo->exec("INSERT INTO club_events VALUES(1,'Soustředění'),(2,'Cizí závod')");
        $pdo->exec("INSERT INTO club_event_sessions VALUES(1,1,'2026-10-02 09:00:00','2026-10-02 12:00:00','Praha','scheduled'),(2,2,'2026-10-02 13:00:00','2026-10-02 15:00:00','Brno','scheduled')");
        $pdo->exec("INSERT INTO club_event_registrations VALUES(1,1,10,2,'confirmed'),(2,2,11,2,'confirmed')");
        $pdo->exec("INSERT INTO individualni_lekce VALUES(1,1,'2026-10-03','10:00','11:00','Rezervace velodromu','aktivni'),(2,1,'2026-10-03','12:00','13:00','Cizí rezervace','aktivni')");
        $pdo->exec("INSERT INTO verejne_rezervace VALUES(1,1,2,10,'potvrzena'),(2,2,2,11,'potvrzena')");
        $pdo->exec("INSERT INTO club_member_charges VALUES(1,10,'2026-10-04','Příspěvek',50000,'CZK','pending'),(2,11,'2026-10-04','Cizí příspěvek',60000,'CZK','pending')");
        return $pdo;
    }
}
